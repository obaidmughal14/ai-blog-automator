<?php
/**
 * Google Gemini API client.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gemini API wrapper.
 */
class AIBA_Gemini_API {

	private const ENDPOINT_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';
	private const MODEL         = 'gemini-2.0-flash';

	/**
	 * Calls in current PHP request (for throttling).
	 *
	 * @var int
	 */
	private int $request_index = 0;

	/**
	 * Get API key from options.
	 */
	private function get_api_key(): string {
		return (string) get_option( 'aiba_gemini_api_key', '' );
	}

	/**
	 * Throttle after many calls in one run (free tier ~15 RPM).
	 */
	public function throttle_if_needed(): void {
		++$this->request_index;
		if ( $this->request_index > 2 ) {
			sleep( 5 );
		}
	}

	/**
	 * Reset per-run counter (e.g. new job).
	 */
	public function reset_throttle_counter(): void {
		$this->request_index = 0;
	}

	/**
	 * Enforce simple global rate limit via transient (optional safety).
	 */
	/**
	 * Minimum seconds between outbound Gemini requests (reduces 429 bursts on free tier).
	 */
	private function wait_for_rate_slot(): void {
		$min_gap = max( 4, (int) apply_filters( 'aiba_gemini_min_seconds_between_requests', 8 ) );
		$key     = 'aiba_gemini_last_request';
		$last    = (int) get_transient( $key );
		$now     = time();
		if ( $last > 0 && ( $now - $last ) < $min_gap ) {
			sleep( $min_gap - ( $now - $last ) );
		}
		set_transient( $key, time(), 120 );
	}

	/**
	 * Generate plain text completion.
	 *
	 * @param string $prompt Prompt text.
	 * @param array  $options Optional overrides for generationConfig.
	 * @return string|WP_Error
	 */
	public function generate_text( string $prompt, array $options = array() ) {
		$this->throttle_if_needed();
		$this->wait_for_rate_slot();

		$key = $this->get_api_key();
		if ( '' === $key ) {
			AIBA_Core::log( 0, 'gemini_call', 'error', 'Missing Gemini API key.' );
			return new WP_Error( 'aiba_no_key', __( 'Gemini API key is not configured.', 'ai-blog-automator' ) );
		}

		$url  = self::ENDPOINT_BASE . self::MODEL . ':generateContent?key=' . rawurlencode( $key );
		$body = array(
			'contents'         => array(
				array(
					'parts' => array( array( 'text' => $prompt ) ),
				),
			),
			'generationConfig' => array_merge(
				array(
					'temperature'     => 0.7,
					'topK'            => 40,
					'topP'            => 0.95,
					'maxOutputTokens' => 8192,
				),
				$options
			),
			'safetySettings'   => array(
				array(
					'category'  => 'HARM_CATEGORY_DANGEROUS_CONTENT',
					'threshold' => 'BLOCK_ONLY_HIGH',
				),
			),
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 60,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		return $this->parse_generate_response( $response );
	}

	/**
	 * Generate with Google Search grounding.
	 *
	 * @param string $prompt Prompt.
	 * @return string|WP_Error
	 */
	public function generate_with_search( string $prompt ) {
		$this->throttle_if_needed();
		$this->wait_for_rate_slot();

		$key = $this->get_api_key();
		if ( '' === $key ) {
			AIBA_Core::log( 0, 'gemini_search', 'error', 'Missing Gemini API key.' );
			return new WP_Error( 'aiba_no_key', __( 'Gemini API key is not configured.', 'ai-blog-automator' ) );
		}

		$url  = self::ENDPOINT_BASE . self::MODEL . ':generateContent?key=' . rawurlencode( $key );
		$body = array(
			'tools'          => array(
				array( 'google_search' => (object) array() ),
			),
			'contents'       => array(
				array(
					'parts' => array( array( 'text' => $prompt ) ),
				),
			),
			'generationConfig' => array(
				'temperature'     => 0.7,
				'topK'            => 40,
				'topP'            => 0.95,
				'maxOutputTokens' => 8192,
			),
			'safetySettings' => array(
				array(
					'category'  => 'HARM_CATEGORY_DANGEROUS_CONTENT',
					'threshold' => 'BLOCK_ONLY_HIGH',
				),
			),
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 60,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		return $this->parse_generate_response( $response );
	}

	/**
	 * Parse Gemini generateContent response.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @return string|WP_Error
	 */
	private function parse_generate_response( $response ) {
		if ( is_wp_error( $response ) ) {
			AIBA_Core::log( 0, 'gemini_call', 'error', $response->get_error_message() );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( $code < 200 || $code >= 300 ) {
			$msg    = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : $raw;
			$status = isset( $data['error']['status'] ) ? (string) $data['error']['status'] : '';

			$is_quota = ( 429 === $code || 503 === $code )
				|| str_contains( strtoupper( $status ), 'RESOURCE_EXHAUSTED' )
				|| str_contains( strtolower( $msg ), 'quota' )
				|| str_contains( strtolower( $msg ), 'rate limit' )
				|| str_contains( strtolower( $msg ), 'resource exhausted' );

			if ( $is_quota ) {
				$retry_after = self::parse_retry_after_seconds( $response, $data );
				AIBA_Core::log( 0, 'gemini_call', 'warning', 'HTTP ' . $code . ' (rate limit) — retry in ' . $retry_after . 's. ' . $msg );
				return new WP_Error(
					'aiba_gemini_rate_limit',
					$msg,
					array(
						'retry_after' => $retry_after,
						'http_code'   => $code,
					)
				);
			}

			AIBA_Core::log( 0, 'gemini_call', 'error', 'HTTP ' . $code . ' — ' . $msg );
			return new WP_Error( 'aiba_gemini_http', $msg );
		}

		$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
		if ( ! is_string( $text ) || '' === $text ) {
			AIBA_Core::log( 0, 'gemini_call', 'error', 'Empty Gemini response: ' . $raw );
			return new WP_Error( 'aiba_gemini_empty', __( 'Empty response from Gemini.', 'ai-blog-automator' ) );
		}

		return $text;
	}

	/**
	 * Seconds to wait before retrying after a rate-limited response.
	 *
	 * @param array|WP_Error         $response HTTP response.
	 * @param array<string, mixed>|null $body   Decoded JSON body if any.
	 */
	private static function parse_retry_after_seconds( $response, ?array $body = null ): int {
		$default = 900;
		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			$h = wp_remote_retrieve_header( $response, 'retry-after' );
			if ( is_string( $h ) && is_numeric( trim( $h ) ) ) {
				return min( 3600, max( 60, (int) $h ) );
			}
		}
		if ( is_array( $body ) && isset( $body['error']['details'] ) && is_array( $body['error']['details'] ) ) {
			foreach ( $body['error']['details'] as $detail ) {
				if ( ! is_array( $detail ) ) {
					continue;
				}
				if ( isset( $detail['@type'] ) && str_contains( (string) $detail['@type'], 'RetryInfo' ) && isset( $detail['retryDelay'] ) ) {
					$d = (string) $detail['retryDelay'];
					if ( preg_match( '/^(\d+)s$/', $d, $m ) ) {
						return min( 3600, max( 60, (int) $m[1] ) );
					}
				}
			}
		}
		return $default;
	}

	/**
	 * Whether a WP_Error from this client indicates quota / rate limit (do not tight-loop retry).
	 */
	public static function is_rate_limit_error( WP_Error $error ): bool {
		if ( 'aiba_gemini_rate_limit' === $error->get_error_code() ) {
			return true;
		}
		$msg = strtolower( $error->get_error_message() );
		return str_contains( $msg, 'quota' )
			|| str_contains( $msg, 'rate limit' )
			|| str_contains( $msg, 'resource exhausted' )
			|| str_contains( $msg, '429' );
	}

	/**
	 * Retry-after seconds from error data if present.
	 */
	public static function get_retry_after_from_error( WP_Error $error ): int {
		$data = $error->get_error_data();
		if ( is_array( $data ) && isset( $data['retry_after'] ) ) {
			return min( 3600, max( 60, (int) $data['retry_after'] ) );
		}
		return 900;
	}

	/**
	 * Validate API key with a tiny request; cached 1 hour.
	 */
	public function validate_api_key(): bool {
		$cached = get_transient( 'aiba_api_key_valid' );
		if ( false !== $cached ) {
			return (bool) $cached;
		}

		$key = $this->get_api_key();
		if ( '' === $key ) {
			set_transient( 'aiba_api_key_valid', 0, HOUR_IN_SECONDS );
			return false;
		}

		$url  = self::ENDPOINT_BASE . self::MODEL . ':generateContent?key=' . rawurlencode( $key );
		$body = array(
			'contents'         => array(
				array( 'parts' => array( array( 'text' => 'Reply with exactly: OK' ) ) ),
			),
			'generationConfig' => array(
				'maxOutputTokens' => 8,
				'temperature'     => 0,
			),
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		$result = $this->parse_generate_response( $response );
		$ok     = is_string( $result ) && '' !== $result;
		set_transient( 'aiba_api_key_valid', $ok ? 1 : 0, HOUR_IN_SECONDS );
		return $ok;
	}
}

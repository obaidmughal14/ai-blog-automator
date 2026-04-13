<?php
/**
 * Anthropic Claude Messages API.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Claude client.
 */
class AIBA_Anthropic_API {

	private const API_URL = 'https://api.anthropic.com/v1/messages';

	private int $request_index = 0;

	private function get_api_key(): string {
		return (string) get_option( 'aiba_anthropic_api_key', '' );
	}

	private function get_model(): string {
		$m = (string) get_option( 'aiba_anthropic_model', 'claude-sonnet-4-20250514' );
		return $m !== '' ? $m : 'claude-sonnet-4-20250514';
	}

	public function reset_throttle_counter(): void {
		$this->request_index = 0;
	}

	public function throttle_if_needed(): void {
		++$this->request_index;
		if ( $this->request_index > 2 ) {
			sleep( 4 );
		}
		$this->wait_between_requests();
	}

	private function wait_between_requests(): void {
		$min_gap = max( 3, (int) apply_filters( 'aiba_anthropic_min_seconds_between_requests', 6 ) );
		$key     = 'aiba_anthropic_last_request';
		$last    = (int) get_transient( $key );
		$now     = time();
		if ( $last > 0 && ( $now - $last ) < $min_gap ) {
			sleep( $min_gap - ( $now - $last ) );
		}
		set_transient( $key, time(), 120 );
	}

	/**
	 * @param array<string, mixed> $options maxOutputTokens etc.
	 * @return string|WP_Error
	 */
	public function generate_text( string $prompt, array $options = array() ) {
		$this->throttle_if_needed();

		$key = $this->get_api_key();
		if ( '' === $key ) {
			return new WP_Error( 'aiba_anthropic_no_key', __( 'Anthropic API key is not configured.', 'ai-blog-automator' ) );
		}

		$max_tokens = isset( $options['maxOutputTokens'] ) ? (int) $options['maxOutputTokens'] : 8192;
		$max_tokens = min( 8192, max( 256, $max_tokens ) );

		$body = array(
			'model'       => $this->get_model(),
			'max_tokens'  => $max_tokens,
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);

		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => 120,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $key,
					'anthropic-version' => '2023-06-01',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		return $this->parse_response( $response );
	}

	/**
	 * @param array|WP_Error $response HTTP response.
	 * @return string|WP_Error
	 */
	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			AIBA_Core::log( 0, 'anthropic_call', 'error', $response->get_error_message() );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : $raw;
			if ( 429 === $code ) {
				$retry = 900;
				$h     = wp_remote_retrieve_header( $response, 'retry-after' );
				if ( is_string( $h ) && is_numeric( trim( $h ) ) ) {
					$retry = min( 3600, max( 60, (int) $h ) );
				}
				return new WP_Error(
					'aiba_anthropic_rate_limit',
					$msg,
					array( 'retry_after' => $retry, 'http_code' => $code )
				);
			}
			return new WP_Error( 'aiba_anthropic_http', $msg );
		}

		$text = '';
		if ( isset( $data['content'][0]['text'] ) && is_string( $data['content'][0]['text'] ) ) {
			$text = $data['content'][0]['text'];
		}
		if ( '' === trim( $text ) ) {
			return new WP_Error( 'aiba_anthropic_empty', __( 'Empty response from Claude.', 'ai-blog-automator' ) );
		}

		return $text;
	}

	public static function is_rate_limit_error( WP_Error $error ): bool {
		return 'aiba_anthropic_rate_limit' === $error->get_error_code();
	}
}

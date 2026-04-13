<?php
/**
 * Custom OpenAI-compatible chat endpoint.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * POST JSON to a user-defined URL (OpenAI-style chat/completions shape).
 */
class AIBA_Custom_LLM_API {

	private int $request_index = 0;

	public function reset_throttle_counter(): void {
		$this->request_index = 0;
	}

	private function get_url(): string {
		return esc_url_raw( (string) get_option( 'aiba_custom_llm_url', '' ) );
	}

	private function get_model(): string {
		return sanitize_text_field( (string) get_option( 'aiba_custom_llm_model', 'default' ) );
	}

	private function get_api_key(): string {
		return (string) get_option( 'aiba_custom_llm_api_key', '' );
	}

	private function get_header_name(): string {
		$h = sanitize_text_field( (string) get_option( 'aiba_custom_llm_auth_header', 'Authorization' ) );
		return $h !== '' ? $h : 'Authorization';
	}

	public function throttle_if_needed(): void {
		++$this->request_index;
		if ( $this->request_index > 2 ) {
			sleep( 3 );
		}
	}

	/**
	 * @param array<string, mixed> $options
	 * @return string|WP_Error
	 */
	public function generate_text( string $prompt, array $options = array() ) {
		$this->throttle_if_needed();

		$url = $this->get_url();
		if ( '' === $url ) {
			return new WP_Error( 'aiba_custom_llm_no_url', __( 'Custom LLM URL is not set.', 'ai-blog-automator' ) );
		}

		$max_tokens = isset( $options['maxOutputTokens'] ) ? (int) $options['maxOutputTokens'] : 8192;
		$max_tokens = min( 32000, max( 256, $max_tokens ) );

		$body = array(
			'model'       => $this->get_model(),
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'max_tokens'  => $max_tokens,
			'temperature' => isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.7,
		);

		$body = apply_filters( 'aiba_custom_llm_request_body', $body, $prompt, $options );

		$headers = array( 'Content-Type' => 'application/json' );
		$key     = $this->get_api_key();
		if ( '' !== $key ) {
			$hname = $this->get_header_name();
			if ( strcasecmp( $hname, 'Authorization' ) === 0 && ! str_contains( $key, ' ' ) ) {
				$headers['Authorization'] = 'Bearer ' . $key;
			} else {
				$headers[ $hname ] = $key;
			}
		}

		$headers = apply_filters( 'aiba_custom_llm_request_headers', $headers, $prompt );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 120,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);

		return $this->parse_openai_shape_response( $response );
	}

	/**
	 * Parse OpenAI-compatible { choices[0].message.content } or { content[0].text }.
	 *
	 * @param array|WP_Error $response
	 * @return string|WP_Error
	 */
	private function parse_openai_shape_response( $response ) {
		if ( is_wp_error( $response ) ) {
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
				return new WP_Error( 'aiba_custom_llm_rl', $msg, array( 'retry_after' => 900 ) );
			}
			return new WP_Error( 'aiba_custom_llm_http', $msg );
		}

		$text = '';
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			$text = (string) $data['choices'][0]['message']['content'];
		} elseif ( isset( $data['content'][0]['text'] ) ) {
			$text = (string) $data['content'][0]['text'];
		}

		if ( '' === trim( $text ) ) {
			return new WP_Error( 'aiba_custom_llm_empty', __( 'Empty response from custom endpoint.', 'ai-blog-automator' ) );
		}

		return $text;
	}
}

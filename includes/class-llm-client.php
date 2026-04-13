<?php
/**
 * Routes text generation across Gemini, OpenAI, Claude, and custom endpoints.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Unified LLM facade for the plugin.
 */
class AIBA_LLM_Client {

	public function reset_throttle_counter(): void {
		AIBA_Core::gemini()->reset_throttle_counter();
		AIBA_Core::openai()->reset_throttle_counter();
		AIBA_Core::anthropic()->reset_throttle_counter();
		AIBA_Core::custom_llm()->reset_throttle_counter();
	}

	/**
	 * @param array<string, mixed> $options Passed to the active backend.
	 * @return string|WP_Error
	 */
	public function generate_text( string $prompt, array $options = array() ) {
		$mode = (string) get_option( 'aiba_llm_provider', 'auto' );

		if ( 'gemini' === $mode ) {
			return AIBA_Core::gemini()->generate_text( $prompt, $options );
		}
		if ( 'openai' === $mode ) {
			return AIBA_Core::openai()->generate_text( $prompt, $options );
		}
		if ( 'claude' === $mode ) {
			return AIBA_Core::anthropic()->generate_text( $prompt, $options );
		}
		if ( 'custom' === $mode ) {
			return AIBA_Core::custom_llm()->generate_text( $prompt, $options );
		}

		return $this->generate_auto_chain( $prompt, $options, array() );
	}

	/**
	 * Auto mode: try providers in order until success or non–rate-limit failure.
	 *
	 * @param array<string, mixed> $options
	 * @param array<int, string>   $skip_slugs Providers to skip (e.g. gemini after search RL).
	 * @return string|WP_Error
	 */
	private function generate_auto_chain( string $prompt, array $options, array $skip_slugs ) {
		$chain = $this->auto_provider_chain();
		$last  = null;

		foreach ( $chain as $slug ) {
			if ( in_array( $slug, $skip_slugs, true ) ) {
				continue;
			}
			$last = $this->call_provider( $slug, $prompt, $options );
			if ( ! is_wp_error( $last ) ) {
				return $last;
			}
			if ( ! self::is_rate_limit_error( $last ) ) {
				return $last;
			}
			AIBA_Core::log(
				0,
				'llm',
				'warning',
				sprintf(
					/* translators: %s: provider slug */
					__( 'Provider "%s" rate-limited or over quota; trying next in chain.', 'ai-blog-automator' ),
					$slug
				)
			);
		}

		return $last instanceof WP_Error ? $last : new WP_Error( 'aiba_llm_empty', __( 'No LLM provider available.', 'ai-blog-automator' ) );
	}

	/**
	 * @return array<int, string>
	 */
	private function auto_provider_chain(): array {
		$chain = array( 'gemini' );
		if ( '' !== (string) get_option( 'aiba_openai_api_key', '' ) ) {
			$chain[] = 'openai';
		}
		if ( '' !== (string) get_option( 'aiba_anthropic_api_key', '' ) ) {
			$chain[] = 'claude';
		}
		if ( '' !== (string) get_option( 'aiba_custom_llm_url', '' ) ) {
			$chain[] = 'custom';
		}
		return apply_filters( 'aiba_llm_auto_chain', $chain );
	}

	/**
	 * @param array<string, mixed> $options
	 * @return string|WP_Error
	 */
	private function call_provider( string $slug, string $prompt, array $options ) {
		return match ( $slug ) {
			'gemini' => AIBA_Core::gemini()->generate_text( $prompt, $options ),
			'openai' => AIBA_Core::openai()->generate_text( $prompt, $options ),
			'claude' => AIBA_Core::anthropic()->generate_text( $prompt, $options ),
			'custom' => AIBA_Core::custom_llm()->generate_text( $prompt, $options ),
			default  => new WP_Error( 'aiba_llm_unknown', $slug ),
		};
	}

	/**
	 * Gemini + Google Search when possible; other providers without live search.
	 *
	 * @return string|WP_Error
	 */
	public function generate_with_search( string $prompt ) {
		$mode = (string) get_option( 'aiba_llm_provider', 'auto' );

		$no_search_note = __( 'You do not have live web access. Use the dates and niche in the request; suggest realistic, useful blog topics from general knowledge (no fake “breaking” claims).', 'ai-blog-automator' );
		$openai_prompt  = $no_search_note . "\n\n" . $prompt;

		if ( 'openai' === $mode ) {
			return AIBA_Core::openai()->generate_text( $openai_prompt );
		}
		if ( 'claude' === $mode ) {
			return AIBA_Core::anthropic()->generate_text( $openai_prompt );
		}
		if ( 'custom' === $mode ) {
			return AIBA_Core::custom_llm()->generate_text( $openai_prompt );
		}
		if ( 'gemini' === $mode ) {
			return AIBA_Core::gemini()->generate_with_search( $prompt );
		}

		$gemini = AIBA_Core::gemini()->generate_with_search( $prompt );
		if ( ! is_wp_error( $gemini ) ) {
			return $gemini;
		}

		if ( self::is_rate_limit_error( $gemini ) ) {
			AIBA_Core::log( 0, 'llm', 'warning', __( 'Gemini (with search) rate-limited; falling back to other providers without live web search.', 'ai-blog-automator' ) );
			return $this->generate_auto_chain( $openai_prompt, array(), array( 'gemini' ) );
		}

		return $gemini;
	}

	/**
	 * True if either provider reports quota / rate limit.
	 */
	public static function is_rate_limit_error( WP_Error $error ): bool {
		if ( AIBA_Gemini_API::is_rate_limit_error( $error ) ) {
			return true;
		}
		$code = $error->get_error_code();
		if ( in_array( $code, array( 'aiba_openai_rate_limit', 'aiba_anthropic_rate_limit', 'aiba_custom_llm_rl' ), true ) ) {
			return true;
		}
		$msg = strtolower( $error->get_error_message() );
		if ( 'aiba_openai_http' === $code && ( str_contains( $msg, '429' ) || str_contains( $msg, 'rate limit' ) || str_contains( $msg, 'too many requests' ) ) ) {
			return true;
		}
		if ( str_contains( $msg, 'insufficient_quota' ) || str_contains( $msg, 'exceeded your current quota' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Retry delay from error payload (either provider).
	 */
	public static function get_retry_after_from_error( WP_Error $error ): int {
		return AIBA_Gemini_API::get_retry_after_from_error( $error );
	}
}

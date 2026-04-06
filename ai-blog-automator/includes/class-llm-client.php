<?php
/**
 * Routes text generation to Gemini and/or OpenAI (fallback on quota).
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
	}

	/**
	 * @param array<string, mixed> $options Passed to the active backend.
	 * @return string|WP_Error
	 */
	public function generate_text( string $prompt, array $options = array() ) {
		$mode      = (string) get_option( 'aiba_llm_provider', 'auto' );
		$openai_on = '' !== (string) get_option( 'aiba_openai_api_key', '' );

		if ( 'openai' === $mode ) {
			return AIBA_Core::openai()->generate_text( $prompt, $options );
		}

		if ( 'gemini' === $mode ) {
			return AIBA_Core::gemini()->generate_text( $prompt, $options );
		}

		// auto: Gemini first, OpenAI on Gemini rate limit if key present.
		$gemini = AIBA_Core::gemini()->generate_text( $prompt, $options );
		if ( ! is_wp_error( $gemini ) ) {
			return $gemini;
		}

		if ( $openai_on && AIBA_Gemini_API::is_rate_limit_error( $gemini ) ) {
			AIBA_Core::log( 0, 'llm', 'warning', __( 'Gemini rate-limited or over quota; using OpenAI for this request.', 'ai-blog-automator' ) );
			return AIBA_Core::openai()->generate_text( $prompt, $options );
		}

		return $gemini;
	}

	/**
	 * Gemini + Google Search when possible; OpenAI fallback without live search.
	 *
	 * @return string|WP_Error
	 */
	public function generate_with_search( string $prompt ) {
		$mode      = (string) get_option( 'aiba_llm_provider', 'auto' );
		$openai_on = '' !== (string) get_option( 'aiba_openai_api_key', '' );

		$openai_prompt = __( 'You do not have live web access. Use the dates and niche in the request; suggest realistic, useful blog topics from general knowledge (no fake “breaking” claims).', 'ai-blog-automator' )
			. "\n\n"
			. $prompt;

		if ( 'openai' === $mode ) {
			return AIBA_Core::openai()->generate_text( $openai_prompt );
		}

		if ( 'gemini' === $mode ) {
			return AIBA_Core::gemini()->generate_with_search( $prompt );
		}

		$gemini = AIBA_Core::gemini()->generate_with_search( $prompt );
		if ( ! is_wp_error( $gemini ) ) {
			return $gemini;
		}

		if ( $openai_on && AIBA_Gemini_API::is_rate_limit_error( $gemini ) ) {
			AIBA_Core::log( 0, 'llm', 'warning', __( 'Gemini (with search) rate-limited; using OpenAI without live web search.', 'ai-blog-automator' ) );
			return AIBA_Core::openai()->generate_text( $openai_prompt );
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
		if ( 'aiba_openai_rate_limit' === $code ) {
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

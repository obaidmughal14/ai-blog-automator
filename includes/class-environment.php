<?php
/**
 * Server requirements, HTTP defaults for outbound APIs, and light rate limits.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * End-user reliability and abuse prevention (Envato-friendly defaults).
 */
class AIBA_Environment {

	public static function init(): void {
		add_filter( 'http_request_args', array( __CLASS__, 'extend_timeouts_for_llm_hosts' ), 10, 2 );
		if ( is_admin() ) {
			add_action( 'admin_notices', array( __CLASS__, 'maybe_requirements_notice' ) );
		}
	}

	/**
	 * Longer timeouts for known LLM / media hosts to reduce cURL 28 errors on slow responses.
	 *
	 * @param array<string, mixed> $args Request args.
	 * @param string                 $url  URL.
	 * @return array<string, mixed>
	 */
	public static function extend_timeouts_for_llm_hosts( array $args, string $url ): array {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		if ( $host === '' ) {
			return $args;
		}
		$host   = strtolower( $host );
		$match  = false;
		if ( $host === 'googleapis.com' || str_ends_with( $host, '.googleapis.com' ) ) {
			$match = true;
		}
		if ( ! $match ) {
			$exact = array( 'api.openai.com', 'api.anthropic.com', 'api.pexels.com', 'api.unsplash.com' );
			$match = in_array( $host, $exact, true );
		}
		if ( ! $match ) {
			$custom = (string) get_option( 'aiba_custom_llm_url', '' );
			if ( $custom !== '' ) {
				$ch = strtolower( (string) wp_parse_url( $custom, PHP_URL_HOST ) );
				if ( $ch !== '' && $ch === $host ) {
					$match = true;
				}
			}
		}
		if ( ! $match ) {
			return $args;
		}
		$min = (int) apply_filters( 'aiba_outbound_api_timeout_seconds', 120, $url );
		$min = max( 30, min( 300, $min ) );
		$cur = isset( $args['timeout'] ) ? (int) $args['timeout'] : 5;
		$args['timeout'] = max( $cur, $min );
		return $args;
	}

	/**
	 * @return array<int, array{type: string, message: string}>
	 */
	public static function get_requirement_issues(): array {
		$out = array();
		if ( PHP_VERSION_ID < 80000 ) {
			$out[] = array(
				'type'    => 'error',
				'message' => sprintf(
					/* translators: %s: current PHP version */
					__( 'This plugin requires PHP 8.0 or newer. This site is running PHP %s.', 'ai-blog-automator' ),
					PHP_VERSION
				),
			);
		}
		if ( ! extension_loaded( 'curl' ) ) {
			$out[] = array(
				'type'    => 'warning',
				'message' => __( 'PHP cURL extension is not loaded. Outbound HTTPS to LLM and image APIs may be unreliable; enable cURL in php.ini if you see connection errors.', 'ai-blog-automator' ),
			);
		}
		if ( ! extension_loaded( 'dom' ) ) {
			$out[] = array(
				'type'    => 'warning',
				'message' => __( 'PHP DOM extension is not loaded. Block editor conversion for generated posts may fail until dom is enabled.', 'ai-blog-automator' ),
			);
		}
		if ( ! extension_loaded( 'mbstring' ) ) {
			$out[] = array(
				'type'    => 'warning',
				'message' => __( 'PHP mbstring is not loaded. UTF-8 text handling may be limited; enabling mbstring is recommended.', 'ai-blog-automator' ),
			);
		}
		return apply_filters( 'aiba_environment_requirement_issues', $out );
	}

	public static function maybe_requirements_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! isset( $screen->id ) ) {
			return;
		}
		$id = (string) $screen->id;
		if ( ! str_contains( $id, 'ai-blog-automator' ) && ! str_contains( $id, '_page_aiba-' ) ) {
			return;
		}
		$issues = self::get_requirement_issues();
		if ( empty( $issues ) ) {
			return;
		}
		foreach ( $issues as $row ) {
			$cls = 'notice-error' === ( $row['type'] ?? '' ) ? 'notice-error' : 'notice-warning';
			echo '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p><strong>AI Blog Automator:</strong> ' . esc_html( (string) ( $row['message'] ?? '' ) ) . '</p></div>';
		}
	}

	/**
	 * Sliding-window rate limit. Returns true if request is allowed and consumes a slot.
	 *
	 * @param string $bucket Short slug (e.g. generate, feedback).
	 * @param int    $user_id WordPress user ID.
	 * @param int    $max     Max events per window.
	 * @param int    $window_sec Window length in seconds.
	 */
	public static function rate_limit_allow( string $bucket, int $user_id, int $max, int $window_sec ): bool {
		$bucket = preg_replace( '/[^a-z0-9_-]/i', '', $bucket );
		if ( '' === $bucket || $user_id < 1 || $max < 1 || $window_sec < 10 ) {
			return true;
		}
		$key   = 'aiba_rl_' . $bucket . '_' . $user_id;
		$times = get_transient( $key );
		if ( ! is_array( $times ) ) {
			$times = array();
		}
		$now   = time();
		$times = array_values(
			array_filter(
				$times,
				static function ( $t ) use ( $now, $window_sec ) {
					return is_int( $t ) && $t > $now - $window_sec;
				}
			)
		);
		if ( count( $times ) >= $max ) {
			return false;
		}
		$times[] = $now;
		set_transient( $key, $times, $window_sec + 10 );
		return true;
	}

	/**
	 * @return array{max: int, window: int}
	 */
	public static function generate_rate_limit_params(): array {
		$defaults = array(
			'max'    => 6,
			'window' => 120,
		);
		$cfg = apply_filters( 'aiba_generate_rate_limit', $defaults );
		if ( ! is_array( $cfg ) ) {
			return $defaults;
		}
		$max    = isset( $cfg['max'] ) ? max( 1, min( 30, (int) $cfg['max'] ) ) : $defaults['max'];
		$window = isset( $cfg['window'] ) ? max( 30, min( 3600, (int) $cfg['window'] ) ) : $defaults['window'];
		return array( 'max' => $max, 'window' => $window );
	}
}

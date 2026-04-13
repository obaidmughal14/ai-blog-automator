<?php
/**
 * Premium unlock and boosted defaults (single access code).
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Premium tier helpers.
 */
class AIBA_Premium {

	/**
	 * Public product / checkout page (override in wp-config if needed).
	 */
	public static function product_url(): string {
		$default = 'https://devigontech.com/ai-blog-automator';
		$url     = ( defined( 'AIBA_PRODUCT_URL' ) && is_string( AIBA_PRODUCT_URL ) && AIBA_PRODUCT_URL !== '' )
			? AIBA_PRODUCT_URL
			: $default;
		$san = esc_url_raw( $url );
		return '' !== $san ? $san : esc_url_raw( $default );
	}

	/**
	 * Bullet points for Upgrade screen and front-end demo shortcode (marketing copy aligned with code behaviour).
	 *
	 * @return array<int, string>
	 */
	public static function premium_marketing_points(): array {
		return array(
			__( 'Higher effective article length cap when generating (extra headroom on top of your slider target).', 'ai-blog-automator' ),
			__( 'More in-article images per post and a higher ceiling for automatic internal links.', 'ai-blog-automator' ),
			__( 'More LLM retries when a provider rate-limits or errors, for smoother queue runs.', 'ai-blog-automator' ),
			__( 'Longer activity log retention so you can audit generations and failures.', 'ai-blog-automator' ),
			__( 'One-time unlock code from your purchase is entered under Settings; no subscription hook is required in the free plugin.', 'ai-blog-automator' ),
		);
	}

	/**
	 * Core plugin capabilities for landing / demo shortcode (free tier included).
	 *
	 * @return array<int, string>
	 */
	public static function product_free_highlights(): array {
		return array(
			__( 'Gemini, OpenAI, Claude, or custom OpenAI-compatible APIs with optional Auto failover.', 'ai-blog-automator' ),
			__( 'One-click Generate and a full content queue with bulk import and staggered publishing.', 'ai-blog-automator' ),
			__( 'Stock images via Pexels and Unsplash, internal linking, FAQ block, and Article/FAQ schema.', 'ai-blog-automator' ),
			__( 'SEO title, meta description, and focus keywords for Yoast, Rank Math, AIOSEO, or native meta.', 'ai-blog-automator' ),
			__( 'Optional Google Indexing API notifications for new URLs.', 'ai-blog-automator' ),
		);
	}

	/**
	 * SHA-256 (hex) of the plaintext access code. Code is not stored in the plugin.
	 * Override with wp-config: define( 'AIBA_PREMIUM_CODE_HASH', '...' );
	 */
	private const DEFAULT_CODE_HASH = 'c01b60f58cec2207c0cccbc4eca99ff642894c803115a5926d620acce215ab37';

	/**
	 * Whether premium features are active.
	 */
	public static function is_active(): bool {
		if ( defined( 'AIBA_PREMIUM_UNLOCK' ) && AIBA_PREMIUM_UNLOCK ) {
			return true;
		}
		return '1' === (string) get_option( 'aiba_premium_unlocked', '0' );
	}

	/**
	 * Hash used for verification (constant or built-in default).
	 */
	private static function expected_hash(): string {
		if ( defined( 'AIBA_PREMIUM_CODE_HASH' ) && is_string( AIBA_PREMIUM_CODE_HASH ) && strlen( AIBA_PREMIUM_CODE_HASH ) === 64 ) {
			return strtolower( AIBA_PREMIUM_CODE_HASH );
		}
		return self::DEFAULT_CODE_HASH;
	}

	/**
	 * Verify access code and persist unlock.
	 */
	public static function unlock_with_code( string $code ): bool {
		$code = trim( $code );
		if ( '' === $code ) {
			return false;
		}
		$hash = hash( 'sha256', $code );
		if ( ! hash_equals( self::expected_hash(), $hash ) ) {
			return false;
		}
		update_option( 'aiba_premium_unlocked', '1' );
		self::apply_premium_option_defaults();
		return true;
	}

	/**
	 * Turn off premium (keeps normal settings).
	 */
	public static function revoke(): void {
		update_option( 'aiba_premium_unlocked', '0' );
	}

	/**
	 * When unlocking, enable “power user” defaults once (only updates if still at stock defaults).
	 */
	private static function apply_premium_option_defaults(): void {
		$boosts = array(
			'aiba_max_internal_links' => 12,
			'aiba_images_per_post'    => 6,
			'aiba_max_retries'        => 5,
			'aiba_log_retention'      => 90,
		);
		foreach ( $boosts as $opt => $val ) {
			$cur = get_option( $opt, null );
			if ( null === $cur || '' === $cur ) {
				update_option( $opt, $val );
				continue;
			}
			if ( 'aiba_max_internal_links' === $opt && (int) $cur <= 5 ) {
				update_option( $opt, $val );
			}
			if ( 'aiba_images_per_post' === $opt && (int) $cur <= 3 ) {
				update_option( $opt, $val );
			}
			if ( 'aiba_max_retries' === $opt && (int) $cur <= 3 ) {
				update_option( $opt, $val );
			}
		}
	}

	public static function enhance_word_count( int $base ): int {
		if ( ! self::is_active() ) {
			return $base;
		}
		return min( 5800, $base + 800 );
	}

	public static function enhance_max_internal_links( int $base ): int {
		if ( ! self::is_active() ) {
			return $base;
		}
		return min( 15, max( $base, 10 ) );
	}

	public static function enhance_images_per_post( int $base ): int {
		if ( ! self::is_active() ) {
			return $base;
		}
		return min( 8, max( $base, 5 ) );
	}

	public static function enhance_max_retries( int $base ): int {
		if ( ! self::is_active() ) {
			return $base;
		}
		return min( 8, max( $base, 5 ) );
	}
}

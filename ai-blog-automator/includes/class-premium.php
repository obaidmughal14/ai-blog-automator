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

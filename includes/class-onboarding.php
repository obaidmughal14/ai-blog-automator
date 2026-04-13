<?php
/**
 * First-run welcome and Envato-friendly onboarding.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin notices and dismiss handlers for new installs.
 */
class AIBA_Onboarding {

	private const USER_META_DISMISS = 'aiba_welcome_dismissed_v1';

	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_init', array( __CLASS__, 'handle_dismiss_welcome' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_welcome_notice' ) );
	}

	/**
	 * Whether current screen belongs to this plugin.
	 */
	private static function is_plugin_admin_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! isset( $screen->id ) ) {
			return false;
		}
		$id = (string) $screen->id;
		return str_contains( $id, 'ai-blog-automator' ) || str_contains( $id, '_page_aiba-' );
	}

	public static function handle_dismiss_welcome(): void {
		if ( ! isset( $_GET['aiba_dismiss_welcome'] ) || '1' !== (string) wp_unslash( $_GET['aiba_dismiss_welcome'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'aiba_dismiss_welcome' ) ) {
			return;
		}
		update_user_meta( get_current_user_id(), self::USER_META_DISMISS, time() );
		wp_safe_redirect( remove_query_arg( array( 'aiba_dismiss_welcome', '_wpnonce' ), wp_get_referer() ?: admin_url( 'admin.php?page=ai-blog-automator' ) ) );
		exit;
	}

	public static function maybe_welcome_notice(): void {
		if ( ! self::is_plugin_admin_screen() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( '1' !== (string) get_option( 'aiba_show_onboarding', '0' ) ) {
			return;
		}
		if ( get_user_meta( get_current_user_id(), self::USER_META_DISMISS, true ) ) {
			return;
		}

		$dismiss = wp_nonce_url(
			add_query_arg(
				array(
					'page'                 => 'ai-blog-automator',
					'aiba_dismiss_welcome' => '1',
				),
				admin_url( 'admin.php' )
			),
			'aiba_dismiss_welcome'
		);
		$settings = admin_url( 'admin.php?page=aiba-settings' );
		$docs     = AIBA_PLUGIN_URL . 'docs/USER-GUIDE.html';

		echo '<div class="notice notice-info aiba-welcome-notice is-dismissible"><p><strong>' . esc_html__( 'Welcome to AI Blog Automator', 'ai-blog-automator' ) . '</strong> — ';
		echo esc_html__( 'Add at least one LLM API key under Settings, save, then use Test API connections. Use Generate for a single draft or Queue for bulk jobs.', 'ai-blog-automator' );
		echo '</p><p>';
		printf(
			'<a class="button button-primary" href="%1$s">%2$s</a> ',
			esc_url( $settings ),
			esc_html__( 'Open Settings', 'ai-blog-automator' )
		);
		printf(
			'<a class="button" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a> ',
			esc_url( $docs ),
			esc_html__( 'Open user guide (HTML)', 'ai-blog-automator' )
		);
		printf(
			'<a class="button" href="%1$s">%2$s</a> ',
			esc_url( admin_url( 'admin.php?page=aiba-upgrade' ) ),
			esc_html__( 'Upgrade & documentation', 'ai-blog-automator' )
		);
		printf(
			'<a class="button" href="%1$s">%2$s</a> ',
			esc_url( admin_url( 'admin.php?page=aiba-feedback' ) ),
			esc_html__( 'Send feedback', 'ai-blog-automator' )
		);
		echo '</p><p><a href="' . esc_url( $dismiss ) . '" class="aiba-welcome-dismiss">' . esc_html__( 'Dismiss for my user', 'ai-blog-automator' ) . '</a></p></div>';
	}
}

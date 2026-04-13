<?php
/**
 * Admin menus, settings, AJAX, and screens.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI.
 */
class AIBA_Admin_UI {

	private const OPTION_GROUP = 'aiba_settings_group';

	/**
	 * Settings group id for options.php / settings_fields().
	 */
	public static function option_group_name(): string {
		return self::OPTION_GROUP;
	}

	/**
	 * Boot hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_aiba_generate_post', array( __CLASS__, 'ajax_generate_post' ) );
		add_action( 'wp_ajax_aiba_test_apis', array( __CLASS__, 'ajax_test_apis' ) );
		add_action( 'wp_ajax_aiba_process_queue_now', array( __CLASS__, 'ajax_process_queue_now' ) );
		add_action( 'wp_ajax_aiba_fetch_trends_now', array( __CLASS__, 'ajax_fetch_trends_now' ) );
		add_action( 'wp_ajax_aiba_clear_logs', array( __CLASS__, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_aiba_queue_bulk', array( __CLASS__, 'ajax_queue_bulk' ) );
		add_action( 'admin_post_aiba_export_logs', array( __CLASS__, 'export_logs_csv' ) );
		add_action( 'admin_post_aiba_add_queue', array( __CLASS__, 'handle_add_queue' ) );
		add_action( 'admin_post_aiba_bulk_queue_keywords', array( __CLASS__, 'handle_bulk_queue_keywords' ) );
		add_action( 'admin_post_aiba_premium_unlock', array( __CLASS__, 'handle_premium_unlock' ) );
		add_action( 'admin_post_aiba_feedback_submit', array( __CLASS__, 'handle_feedback_submit' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
	}

	/**
	 * Plugin admin pages (for script enqueue whitelist).
	 *
	 * @return array<int, string>
	 */
	private static function plugin_page_hooks(): array {
		return array(
			'toplevel_page_ai-blog-automator',
			'ai-blog-automator_page_aiba-generate',
			'ai-blog-automator_page_aiba-queue',
			'ai-blog-automator_page_aiba-settings',
			'ai-blog-automator_page_aiba-logs',
			'ai-blog-automator_page_aiba-upgrade',
			'ai-blog-automator_page_aiba-feedback',
		);
	}

	public static function register_menus(): void {
		add_menu_page(
			__( 'AI Blog Automator', 'ai-blog-automator' ),
			__( 'AI Automator', 'ai-blog-automator' ),
			'manage_options',
			'ai-blog-automator',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-edit-large',
			30
		);

		add_submenu_page(
			'ai-blog-automator',
			__( 'Dashboard', 'ai-blog-automator' ),
			__( 'Dashboard', 'ai-blog-automator' ),
			'manage_options',
			'ai-blog-automator',
			array( __CLASS__, 'render_dashboard' )
		);

		add_submenu_page(
			'ai-blog-automator',
			__( 'Generate Now', 'ai-blog-automator' ),
			__( 'Generate Now', 'ai-blog-automator' ),
			'manage_options',
			'aiba-generate',
			array( __CLASS__, 'render_generate' )
		);

		add_submenu_page(
			'ai-blog-automator',
			__( 'Queue', 'ai-blog-automator' ),
			__( 'Queue', 'ai-blog-automator' ),
			'manage_options',
			'aiba-queue',
			array( __CLASS__, 'render_queue' )
		);

		add_submenu_page(
			'ai-blog-automator',
			__( 'Settings', 'ai-blog-automator' ),
			__( 'Settings', 'ai-blog-automator' ),
			'manage_options',
			'aiba-settings',
			array( __CLASS__, 'render_settings' )
		);

		add_submenu_page(
			'ai-blog-automator',
			__( 'Logs', 'ai-blog-automator' ),
			__( 'Logs', 'ai-blog-automator' ),
			'manage_options',
			'aiba-logs',
			array( __CLASS__, 'render_logs' )
		);

		add_submenu_page(
			'ai-blog-automator',
			__( 'Upgrade', 'ai-blog-automator' ),
			__( 'Upgrade', 'ai-blog-automator' ),
			'manage_options',
			'aiba-upgrade',
			array( __CLASS__, 'render_upgrade' )
		);

		add_submenu_page(
			'ai-blog-automator',
			__( 'Feedback', 'ai-blog-automator' ),
			__( 'Feedback', 'ai-blog-automator' ),
			'manage_options',
			'aiba-feedback',
			array( __CLASS__, 'render_feedback' )
		);
	}

	public static function register_settings(): void {
		$opts = array(
			'aiba_gemini_api_key',
			'aiba_llm_provider',
			'aiba_openai_api_key',
			'aiba_openai_model',
			'aiba_anthropic_api_key',
			'aiba_anthropic_model',
			'aiba_custom_llm_url',
			'aiba_custom_llm_api_key',
			'aiba_custom_llm_model',
			'aiba_custom_llm_auth_header',
			'aiba_pexels_api_key',
			'aiba_unsplash_access_key',
			'aiba_google_credentials',
			'aiba_site_niche',
			'aiba_word_count',
			'aiba_tone',
			'aiba_language',
			'aiba_author_id',
			'aiba_category_id',
			'aiba_category_ids',
			'aiba_article_template',
			'aiba_ai_tag_expansion',
			'aiba_ai_suggest_categories',
			'aiba_prompt_outline_prefix',
			'aiba_prompt_outline_suffix',
			'aiba_prompt_section_prefix',
			'aiba_prompt_section_suffix',
			'aiba_prompt_global_append',
			'aiba_auto_tags',
			'aiba_max_internal_links',
			'aiba_images_per_post',
			'aiba_auto_trends',
			'aiba_auto_publish',
			'aiba_publish_status',
			'aiba_posts_per_day',
			'aiba_publish_time',
			'aiba_auto_index',
			'aiba_queue_frequency',
			'aiba_queue_custom_minutes',
			'aiba_seo_plugin',
			'aiba_add_faq_schema',
			'aiba_add_article_schema',
			'aiba_canonical',
			'aiba_og_tags',
			'aiba_delete_on_uninstall',
			'aiba_max_retries',
			'aiba_log_retention',
			'aiba_disabled_types',
			'aiba_faq_css',
		);

		foreach ( $opts as $opt ) {
			$cb = array( __CLASS__, 'sanitize_option_' . $opt );
			if ( ! is_callable( $cb ) ) {
				$cb = 'sanitize_text_field';
			}
			register_setting(
				self::OPTION_GROUP,
				$opt,
				array(
					'sanitize_callback' => $cb,
				)
			);
		}
	}

	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Settings API passes value.

	public static function sanitize_option_aiba_gemini_api_key( $value ): string {
		$value = is_string( $value ) ? sanitize_text_field( $value ) : '';
		return $value;
	}

	public static function sanitize_option_aiba_llm_provider( $value ): string {
		$allowed = array( 'auto', 'gemini', 'openai', 'claude', 'custom' );
		$v       = is_string( $value ) ? $value : 'auto';
		return in_array( $v, $allowed, true ) ? $v : 'auto';
	}

	public static function sanitize_option_aiba_openai_api_key( $value ): string {
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	public static function sanitize_option_aiba_openai_model( $value ): string {
		$allowed = array(
			'gpt-4o-mini',
			'gpt-4o',
			'gpt-4-turbo',
			'gpt-3.5-turbo',
		);
		$v       = is_string( $value ) ? sanitize_text_field( $value ) : 'gpt-4o-mini';
		return in_array( $v, $allowed, true ) ? $v : 'gpt-4o-mini';
	}

	public static function sanitize_option_aiba_anthropic_api_key( $value ): string {
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	public static function sanitize_option_aiba_anthropic_model( $value ): string {
		$v = is_string( $value ) ? sanitize_text_field( $value ) : '';
		return $v !== '' ? $v : 'claude-sonnet-4-20250514';
	}

	public static function sanitize_option_aiba_custom_llm_url( $value ): string {
		return is_string( $value ) ? esc_url_raw( trim( $value ) ) : '';
	}

	public static function sanitize_option_aiba_custom_llm_api_key( $value ): string {
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	public static function sanitize_option_aiba_custom_llm_model( $value ): string {
		$v = is_string( $value ) ? sanitize_text_field( $value ) : '';
		return $v !== '' ? $v : 'default';
	}

	public static function sanitize_option_aiba_custom_llm_auth_header( $value ): string {
		$v = is_string( $value ) ? sanitize_text_field( $value ) : '';
		return $v !== '' ? $v : 'Authorization';
	}

	public static function sanitize_option_aiba_pexels_api_key( $value ): string {
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	public static function sanitize_option_aiba_unsplash_access_key( $value ): string {
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	public static function sanitize_option_aiba_google_credentials( $value ): string {
		return is_string( $value ) ? sanitize_textarea_field( $value ) : '';
	}

	public static function sanitize_option_aiba_site_niche( $value ): string {
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	public static function sanitize_option_aiba_word_count( $value ): int {
		return max( 300, min( 5000, (int) $value ) );
	}

	public static function sanitize_option_aiba_tone( $value ): string {
		return is_string( $value ) ? sanitize_text_field( $value ) : 'Professional';
	}

	public static function sanitize_option_aiba_language( $value ): string {
		return is_string( $value ) ? sanitize_text_field( $value ) : 'English';
	}

	public static function sanitize_option_aiba_author_id( $value ): int {
		return max( 1, (int) $value );
	}

	public static function sanitize_option_aiba_category_id( $value ): int {
		return max( 0, (int) $value );
	}

	public static function sanitize_option_aiba_category_ids( $value ): array {
		if ( ! is_array( $value ) ) {
			return (array) get_option( 'aiba_category_ids', array() );
		}
		$out = array_values( array_filter( array_map( 'intval', $value ) ) );
		if ( ! empty( $out ) ) {
			update_option( 'aiba_category_id', (int) $out[0] );
		}
		return $out;
	}

	public static function sanitize_option_aiba_article_template( $value ): string {
		return AIBA_LLM_Templates::sanitize_article_template( is_string( $value ) ? $value : 'standard' );
	}

	public static function sanitize_option_aiba_ai_tag_expansion( $value ): string {
		return ( '1' === (string) $value || true === $value || 1 === $value ) ? '1' : '0';
	}

	public static function sanitize_option_aiba_ai_suggest_categories( $value ): string {
		return ( '1' === (string) $value || true === $value || 1 === $value ) ? '1' : '0';
	}

	public static function sanitize_option_aiba_prompt_outline_prefix( $value ): string {
		return is_string( $value ) ? sanitize_textarea_field( $value ) : '';
	}

	public static function sanitize_option_aiba_prompt_outline_suffix( $value ): string {
		return is_string( $value ) ? sanitize_textarea_field( $value ) : '';
	}

	public static function sanitize_option_aiba_prompt_section_prefix( $value ): string {
		return is_string( $value ) ? sanitize_textarea_field( $value ) : '';
	}

	public static function sanitize_option_aiba_prompt_section_suffix( $value ): string {
		return is_string( $value ) ? sanitize_textarea_field( $value ) : '';
	}

	public static function sanitize_option_aiba_prompt_global_append( $value ): string {
		return is_string( $value ) ? sanitize_textarea_field( $value ) : '';
	}

	public static function sanitize_option_aiba_auto_tags( $value ): string {
		return ( '1' === (string) $value || true === $value || 1 === $value ) ? '1' : '0';
	}

	public static function sanitize_option_aiba_max_internal_links( $value ): int {
		return max( 1, min( 20, (int) $value ) );
	}

	public static function sanitize_option_aiba_images_per_post( $value ): int {
		return max( 0, min( 10, (int) $value ) );
	}

	public static function sanitize_option_aiba_auto_trends( $value ): string {
		return ( '1' === (string) $value || true === $value || 1 === $value ) ? '1' : '0';
	}

	public static function sanitize_option_aiba_auto_publish( $value ): string {
		return ( '1' === (string) $value || true === $value || 1 === $value ) ? '1' : '0';
	}

	public static function sanitize_option_aiba_publish_status( $value ): string {
		$allowed = array( 'draft', 'publish', 'scheduled' );
		return in_array( (string) $value, $allowed, true ) ? (string) $value : 'draft';
	}

	public static function sanitize_option_aiba_posts_per_day( $value ): int {
		return max( 1, min( 20, (int) $value ) );
	}

	public static function sanitize_option_aiba_publish_time( $value ): string {
		return is_string( $value ) ? sanitize_text_field( $value ) : '09:00';
	}

	public static function sanitize_option_aiba_auto_index( $value ): string {
		return ( '1' === (string) $value || true === $value || 1 === $value ) ? '1' : '0';
	}

	public static function sanitize_option_aiba_queue_frequency( $value ): string {
		$allowed = array( 'daily', '12hr', '6hr', '3hr', '2hr', 'custom' );
		$v       = is_string( $value ) ? $value : 'daily';
		return in_array( $v, $allowed, true ) ? $v : 'daily';
	}

	public static function sanitize_option_aiba_queue_custom_minutes( $value ): int {
		return max( 30, min( 1440, (int) $value ) );
	}

	public static function sanitize_option_aiba_seo_plugin( $value ): string {
		$allowed = array( 'auto', 'yoast', 'rankmath', 'aioseo', 'native' );
		$v       = is_string( $value ) ? $value : 'auto';
		return in_array( $v, $allowed, true ) ? $v : 'auto';
	}

	public static function sanitize_option_aiba_add_faq_schema( $value ): string {
		return ( '1' === (string) $value || true === $value || 1 === $value ) ? '1' : '0';
	}

	public static function sanitize_option_aiba_add_article_schema( $value ): string {
		return ( '1' === (string) $value || true === $value || 1 === $value ) ? '1' : '0';
	}

	public static function sanitize_option_aiba_canonical( $value ): string {
		return ( '1' === (string) $value || true === $value || 1 === $value ) ? '1' : '0';
	}

	public static function sanitize_option_aiba_og_tags( $value ): string {
		return ( '1' === (string) $value || true === $value || 1 === $value ) ? '1' : '0';
	}

	public static function sanitize_option_aiba_delete_on_uninstall( $value ): string {
		return ( '1' === (string) $value || true === $value || 1 === $value ) ? '1' : '0';
	}

	public static function sanitize_option_aiba_max_retries( $value ): int {
		return max( 0, min( 10, (int) $value ) );
	}

	public static function sanitize_option_aiba_log_retention( $value ): int {
		return max( 1, min( 365, (int) $value ) );
	}

	public static function sanitize_option_aiba_disabled_types( $value ): array {
		if ( ! is_array( $value ) ) {
			return (array) get_option( 'aiba_disabled_types', array() );
		}
		return array_values( array_filter( array_map( 'sanitize_key', $value ) ) );
	}

	public static function sanitize_option_aiba_faq_css( $value ): string {
		return ( '1' === (string) $value || true === $value || 1 === $value ) ? '1' : '0';
	}

	// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.Found

	public static function enqueue_assets( string $hook ): void {
		$allowed = self::plugin_page_hooks();
		$ok      = in_array( $hook, $allowed, true );
		if ( ! $ok && is_string( $hook ) && ( str_contains( $hook, 'ai-blog-automator' ) || str_contains( $hook, '_page_aiba-' ) ) ) {
			$ok = true;
		}
		if ( ! $ok ) {
			return;
		}
		wp_enqueue_style( 'aiba-admin', AIBA_PLUGIN_URL . 'assets/css/admin.css', array( 'dashicons' ), AIBA_VERSION );
		wp_register_script(
			'aiba-boot',
			AIBA_PLUGIN_URL . 'assets/js/admin-boot.js',
			array(),
			AIBA_VERSION,
			true
		);
		wp_enqueue_script( 'aiba-boot' );
		wp_localize_script(
			'aiba-boot',
			'aibaAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'adminBase' => admin_url( 'admin.php' ),
				'nonce'     => wp_create_nonce( 'aiba_admin' ),
				'genNonce'  => wp_create_nonce( 'aiba_generate' ),
			)
		);
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'aiba-admin', AIBA_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'aiba-boot' ), AIBA_VERSION, true );
	}

	/**
	 * Add body class when premium is active (scoped styling).
	 *
	 * @param string $classes Space-separated classes.
	 */
	public static function admin_body_class( string $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && str_contains( (string) $screen->id, 'ai-blog-automator' ) && AIBA_Premium::is_active() ) {
			$classes .= ' aiba-premium-admin';
		}
		return $classes;
	}

	public static function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$aiba_premium    = AIBA_Premium::is_active();
		$aiba_page_title = __( 'Dashboard', 'ai-blog-automator' );
		$aiba_page_sub   = __( 'Overview, quick actions, and recent activity', 'ai-blog-automator' );
		$stats           = self::get_dashboard_stats();
		$logs            = self::get_recent_logs( 10 );
		$queue           = self::get_queue_preview( 5 );
		include AIBA_PLUGIN_DIR . 'templates/admin-dashboard.php';
	}

	public static function render_generate(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$aiba_premium    = AIBA_Premium::is_active();
		$aiba_page_title = __( 'Generate now', 'ai-blog-automator' );
		$aiba_page_sub   = __( 'Run the full pipeline on a topic in one click', 'ai-blog-automator' );
		$niche           = (string) get_option( 'aiba_site_niche', '' );
		$trends = array();
		if ( $niche !== '' ) {
			$slug = substr( preg_replace( '/[^a-z0-9]+/i', '_', strtolower( $niche ) ), 0, 80 );
			$cached = get_transient( 'aiba_trends_' . $slug );
			if ( is_array( $cached ) ) {
				$trends = $cached;
			}
		}
		$aiba_generate_alerts = self::get_generate_screen_log_alerts( 10 );
		include AIBA_PLUGIN_DIR . 'templates/admin-generate.php';
	}

	public static function render_queue(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$aiba_premium    = AIBA_Premium::is_active();
		$aiba_page_title = __( 'Content queue', 'ai-blog-automator' );
		$aiba_page_sub   = __( 'Scheduled and pending generation jobs', 'ai-blog-automator' );
		$status          = isset( $_GET['aiba_status'] ) ? sanitize_text_field( wp_unslash( $_GET['aiba_status'] ) ) : '';
		$rows   = self::get_queue_rows( $status );
		include AIBA_PLUGIN_DIR . 'templates/admin-queue.php';
	}

	public static function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$aiba_premium    = AIBA_Premium::is_active();
		$aiba_page_title = __( 'Settings', 'ai-blog-automator' );
		$aiba_page_sub   = __( 'API keys, content defaults, automation, and SEO', 'ai-blog-automator' );
		if ( isset( $_GET['aiba_premium'] ) && '1' === $_GET['aiba_premium'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Premium unlocked. Boosted limits are active.', 'ai-blog-automator' ) . '</p></div>';
		}
		if ( isset( $_GET['aiba_premium_err'] ) && '1' === $_GET['aiba_premium_err'] ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Invalid premium access code.', 'ai-blog-automator' ) . '</p></div>';
		}
		if ( isset( $_GET['aiba_premium_revoked'] ) && '1' === $_GET['aiba_premium_revoked'] ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Premium was turned off for this site.', 'ai-blog-automator' ) . '</p></div>';
		}
		if ( isset( $_GET['settings-updated'] ) ) {
			AIBA_Scheduler::reschedule_queue_event();
			AIBA_Scheduler::prune_logs();
			delete_transient( 'aiba_api_key_valid' );
			delete_transient( 'aiba_openai_key_valid' );
			delete_transient( 'aiba_google_token' );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'ai-blog-automator' ) . '</p></div>';
		}
		include AIBA_PLUGIN_DIR . 'templates/admin-settings.php';
	}

	public static function render_logs(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$aiba_premium    = AIBA_Premium::is_active();
		$aiba_page_title = __( 'Activity logs', 'ai-blog-automator' );
		$aiba_page_sub   = __( 'API calls, queue events, and errors', 'ai-blog-automator' );
		$status          = isset( $_GET['aiba_log_status'] ) ? sanitize_text_field( wp_unslash( $_GET['aiba_log_status'] ) ) : '';
		$action = isset( $_GET['aiba_log_action'] ) ? sanitize_text_field( wp_unslash( $_GET['aiba_log_action'] ) ) : '';
		$from   = isset( $_GET['aiba_from'] ) ? sanitize_text_field( wp_unslash( $_GET['aiba_from'] ) ) : '';
		$to     = isset( $_GET['aiba_to'] ) ? sanitize_text_field( wp_unslash( $_GET['aiba_to'] ) ) : '';
		$rows   = self::get_log_rows( $status, $action, $from, $to );
		include AIBA_PLUGIN_DIR . 'templates/admin-logs.php';
	}

	public static function render_upgrade(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$aiba_premium    = AIBA_Premium::is_active();
		$aiba_page_title = __( 'Upgrade', 'ai-blog-automator' );
		$aiba_page_sub   = __( 'Premium benefits, purchase link, and unlock instructions', 'ai-blog-automator' );
		include AIBA_PLUGIN_DIR . 'templates/admin-upgrade.php';
	}

	public static function render_feedback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$aiba_premium          = AIBA_Premium::is_active();
		$aiba_page_title       = __( 'Feedback', 'ai-blog-automator' );
		$aiba_page_sub         = __( 'Help improve AI Blog Automator', 'ai-blog-automator' );
		$aiba_feedback_inbox   = self::get_feedback_inbox();
		include AIBA_PLUGIN_DIR . 'templates/admin-feedback.php';
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_feedback_inbox(): array {
		$raw = get_option( 'aiba_feedback_inbox', array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return array_slice( $raw, 0, 25 );
	}

	/**
	 * Store feedback and email site admin.
	 */
	public static function handle_feedback_submit(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to submit feedback.', 'ai-blog-automator' ) );
		}
		check_admin_referer( 'aiba_feedback_submit' );

		$message = isset( $_POST['aiba_feedback_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['aiba_feedback_message'] ) ) : '';
		if ( strlen( $message ) < 10 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=aiba-feedback&aiba_feedback_err=1' ) );
			exit;
		}

		$topic = isset( $_POST['aiba_feedback_topic'] ) ? sanitize_key( wp_unslash( $_POST['aiba_feedback_topic'] ) ) : 'other';
		$allowed_topics = array( 'bug', 'feature', 'ux', 'seo', 'other' );
		if ( ! in_array( $topic, $allowed_topics, true ) ) {
			$topic = 'other';
		}

		$name  = isset( $_POST['aiba_feedback_name'] ) ? sanitize_text_field( wp_unslash( $_POST['aiba_feedback_name'] ) ) : '';
		$email = isset( $_POST['aiba_feedback_email'] ) ? sanitize_email( wp_unslash( $_POST['aiba_feedback_email'] ) ) : '';
		$user  = wp_get_current_user();

		$entry = array(
			't'       => time(),
			'user_id' => (int) $user->ID,
			'login'   => $user->user_login,
			'topic'   => $topic,
			'name'    => $name,
			'email'   => $email,
			'message' => $message,
			'site'    => home_url(),
			'plugin'  => AIBA_VERSION,
		);

		$inbox = get_option( 'aiba_feedback_inbox', array() );
		if ( ! is_array( $inbox ) ) {
			$inbox = array();
		}
		array_unshift( $inbox, $entry );
		$inbox = array_slice( $inbox, 0, 50 );
		update_option( 'aiba_feedback_inbox', $inbox, false );

		$admin_mail = (string) get_option( 'admin_email' );
		if ( is_email( $admin_mail ) ) {
			$subj = sprintf(
				/* translators: 1: site hostname, 2: topic slug */
				__( '[AI Blog Automator] Feedback from %1$s (%2$s)', 'ai-blog-automator' ),
				wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'site',
				$topic
			);
			$body  = "Topic: {$topic}\n";
			$body .= 'User: ' . $user->user_login . " (ID {$user->ID})\n";
			if ( $name !== '' ) {
				$body .= 'Name: ' . $name . "\n";
			}
			if ( $email !== '' ) {
				$body .= 'Email: ' . $email . "\n";
			}
			$body .= 'Site: ' . home_url() . "\n";
			$body .= 'Plugin: ' . AIBA_VERSION . "\n\n";
			$body .= $message . "\n";

			$headers = array();
			if ( $email !== '' && is_email( $email ) ) {
				$headers[] = 'Reply-To: ' . $email;
			}

			wp_mail( $admin_mail, $subj, $body, $headers );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=aiba-feedback&aiba_feedback_sent=1' ) );
		exit;
	}

	/**
	 * @return array<string, int|float>
	 */
	private static function get_dashboard_stats(): array {
		global $wpdb;
		$logs_table = $wpdb->prefix . 'aiba_logs';
		$queue_table = $wpdb->prefix . 'aiba_queue';

		$total_gen = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$logs_table} WHERE action = %s AND status = %s",
				'publish',
				'success'
			)
		);

		$month_start = gmdate( 'Y-m-01 00:00:00' );
		$this_month  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$logs_table} WHERE action = %s AND status = %s AND created_at >= %s",
				'publish',
				'success',
				$month_start
			)
		);

		$pending = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue_table} WHERE status = %s",
				'pending'
			)
		);

		$failed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$queue_table} WHERE status = %s",
				'failed'
			)
		);

		$avg_seo = self::estimate_avg_seo_score();

		return array(
			'total_generated' => $total_gen,
			'this_month'      => $this_month,
			'pending'         => $pending,
			'failed'          => $failed,
			'avg_seo'         => $avg_seo,
		);
	}

	private static function estimate_avg_seo_score(): float {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		if ( empty( $posts ) ) {
			return 0.0;
		}
		$sum = 0;
		$n   = 0;
		foreach ( $posts as $p ) {
			$kw = '';
			if ( defined( 'WPSEO_VERSION' ) ) {
				$kw = (string) get_post_meta( $p->ID, '_yoast_wpseo_focuskw', true );
			} elseif ( defined( 'RANK_MATH_VERSION' ) ) {
				$kw = (string) get_post_meta( $p->ID, 'rank_math_focus_keyword', true );
			} else {
				$kw = (string) get_post_meta( $p->ID, '_aiba_focus_keyword', true );
			}
			if ( $kw === '' ) {
				continue;
			}
			$meta = (string) get_post_meta( $p->ID, '_aiba_meta_description', true );
			if ( $meta === '' && defined( 'WPSEO_VERSION' ) ) {
				$meta = (string) get_post_meta( $p->ID, '_yoast_wpseo_metadesc', true );
			}
			$sum += AIBA_SEO_Handler::calculate_seo_score( $p->post_content, $kw, $p->post_title, $meta );
			++$n;
		}
		return $n > 0 ? round( $sum / $n, 1 ) : 0.0;
	}

	/**
	 * @return array<int, object>
	 */
	private static function get_recent_logs( int $limit ): array {
		global $wpdb;
		$limit = max( 1, min( 50, $limit ) );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}aiba_logs ORDER BY id DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Recent error/warning log lines that affect or explain generation failures (Generate screen; newest first).
	 * No date cutoff so the latest issue always surfaces regardless of site timezone quirks.
	 *
	 * @return array<int, object{ id: int, action: string, status: string, message: string, created_at: string }>
	 */
	private static function get_generate_screen_log_alerts( int $limit ): array {
		global $wpdb;
		$limit = max( 1, min( 30, $limit ) );
		$table = $wpdb->prefix . 'aiba_logs';
		$actions = array(
			'generate',
			'llm',
			'publish',
			'exception',
			'gemini_call',
			'gemini_search',
			'openai_call',
			'anthropic_call',
			'queue',
			'feature_image',
			'pexels',
			'sideload',
		);
		$in_actions = implode( ', ', array_fill( 0, count( $actions ), '%s' ) );
		$sql  = "SELECT id, action, status, message, created_at FROM {$table}
			WHERE status IN ('error','warning') AND action IN ({$in_actions})
			ORDER BY id DESC LIMIT %d";
		$args = array_merge( $actions, array( $limit ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
	}

	/**
	 * @return array<int, object>
	 */
	private static function get_queue_preview( int $limit ): array {
		global $wpdb;
		$limit = max( 1, min( 20, $limit ) );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}aiba_queue ORDER BY id ASC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * @return array<int, object>
	 */
	private static function get_queue_rows( string $status ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'aiba_queue';
		if ( $status !== '' && in_array( $status, array( 'pending', 'processing', 'completed', 'failed' ), true ) ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT %d",
					$status,
					200
				)
			);
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}aiba_queue ORDER BY id DESC LIMIT %d",
				200
			)
		);
	}

	/**
	 * @return array<int, object>
	 */
	private static function get_log_rows( string $status, string $action, string $from, string $to ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'aiba_logs';
		$where = array( '1=1' );
		$args  = array();

		if ( $status !== '' && in_array( $status, array( 'success', 'error', 'warning' ), true ) ) {
			$where[] = 'status = %s';
			$args[]  = $status;
		}
		if ( $action !== '' ) {
			$where[] = 'action = %s';
			$args[]  = $action;
		}
		if ( $from !== '' ) {
			$where[] = 'created_at >= %s';
			$args[]  = $from . ' 00:00:00';
		}
		if ( $to !== '' ) {
			$where[] = 'created_at <= %s';
			$args[]  = $to . ' 23:59:59';
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT 500';
		if ( ! empty( $args ) ) {
			$sql = $wpdb->prepare( $sql, $args );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql );
	}

	public static function ajax_generate_post(): void {
		check_ajax_referer( 'aiba_generate', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'ai-blog-automator' ) ), 403 );
		}

		if ( function_exists( 'ignore_user_abort' ) ) {
			ignore_user_abort( true );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			// LLM + images + block conversion can exceed default 30s on shared hosts.
			$max = (int) apply_filters( 'aiba_generate_max_execution_seconds', 300 );
			if ( $max > 0 ) {
				set_time_limit( $max );
			}
		}

		$topic      = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
		$primary    = isset( $_POST['primary_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['primary_keyword'] ) ) : '';
		$secondary  = isset( $_POST['secondary_keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['secondary_keywords'] ) ) : '';
		$word_count = isset( $_POST['word_count'] ) ? (int) $_POST['word_count'] : (int) get_option( 'aiba_word_count', 1500 );
		$word_count = max( 300, min( 5000, $word_count ) );
		$tone       = isset( $_POST['tone'] ) ? sanitize_text_field( wp_unslash( $_POST['tone'] ) ) : (string) get_option( 'aiba_tone', 'Professional' );
		$publish_now = ! empty( $_POST['publish_now'] );

		$cat_ids = isset( $_POST['category_ids'] ) && is_array( $_POST['category_ids'] ) ? array_map( 'intval', wp_unslash( $_POST['category_ids'] ) ) : array();
		$cat_ids = array_values( array_filter( $cat_ids ) );
		if ( empty( $cat_ids ) && isset( $_POST['category_id'] ) ) {
			$cat_ids = array( (int) $_POST['category_id'] );
		}
		if ( empty( $cat_ids ) ) {
			$cat_ids = AIBA_Core::get_default_category_ids();
		}
		$primary_cat = ! empty( $cat_ids ) ? (int) $cat_ids[0] : (int) get_option( 'aiba_category_id', 0 );

		$tpl = isset( $_POST['article_template'] ) ? sanitize_key( wp_unslash( $_POST['article_template'] ) ) : '';
		$tpl = AIBA_LLM_Templates::sanitize_article_template( $tpl );

		$sec_arr = array_filter( array_map( 'trim', explode( ',', $secondary ) ) );

		$job = array(
			'topic'                => $topic,
			'primary_keyword'      => $primary,
			'secondary_keywords'   => $sec_arr,
			'category_id'          => $primary_cat,
			'category_ids'         => $cat_ids,
			'word_count'           => $word_count,
			'tone'                 => $tone,
			'language'             => (string) get_option( 'aiba_language', 'English' ),
			'article_template'     => $tpl,
		);

		$article = AIBA_Core::content_generator()->generate_article( $job );
		if ( is_wp_error( $article ) ) {
			$err_msg = $article->get_error_message();
			if ( AIBA_LLM_Client::is_rate_limit_error( $article ) ) {
				AIBA_Core::log( 0, 'generate', 'warning', $err_msg );
				wp_send_json_error(
					array(
						'code'    => 'rate_limit',
						'message' => __( 'LLM quota or rate limit reached. Wait and retry, check provider billing, add fallback API keys under Settings → API, or slow the queue. Nothing was saved.', 'ai-blog-automator' ),
					)
				);
			}
			AIBA_Core::log( 0, 'generate', 'error', $err_msg );
			wp_send_json_error( array( 'message' => $err_msg ) );
		}

		$settings = array(
			'author_id'      => (int) get_option( 'aiba_author_id', get_current_user_id() ),
			'category_id'    => $primary_cat,
			'category_ids'   => $cat_ids,
			'auto_publish'   => $publish_now,
			'publish_status' => $publish_now ? 'publish' : (string) get_option( 'aiba_publish_status', 'draft' ),
			'scheduled_time' => null,
			'topic'          => $topic,
		);

		$post_id = AIBA_Core::post_publisher()->publish_post( $article, $settings );
		if ( is_wp_error( $post_id ) ) {
			AIBA_Core::log( 0, 'publish', 'error', $post_id->get_error_message() );
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
		}

		$p = get_post( $post_id );
		if ( ! $p instanceof WP_Post ) {
			AIBA_Core::log( $post_id, 'publish', 'error', 'Post missing after publish.' );
			wp_send_json_error( array( 'message' => __( 'Post was created but could not be loaded for the response.', 'ai-blog-automator' ) ) );
		}

		$score = AIBA_SEO_Handler::calculate_seo_score(
			(string) $p->post_content,
			(string) ( $article['primary_keyword'] ?? '' ),
			(string) $p->post_title,
			(string) ( $article['meta_description'] ?? '' )
		);

		$edit_url = get_edit_post_link( $post_id, 'raw' );
		if ( ! is_string( $edit_url ) || $edit_url === '' ) {
			$edit_url = admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' );
		}

		wp_send_json_success(
			array(
				'post_id'   => $post_id,
				'post_url'  => $edit_url,
				'view_url'  => get_permalink( $post_id ) ?: '',
				'seo_score' => $score,
			)
		);
	}

	public static function ajax_test_apis(): void {
		check_ajax_referer( 'aiba_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'ai-blog-automator' ) ), 403 );
		}

		$gemini = AIBA_Core::gemini()->validate_api_key();
		$openai = true;
		$okey   = (string) get_option( 'aiba_openai_api_key', '' );
		if ( '' !== $okey ) {
			$openai = AIBA_Core::openai()->validate_api_key();
		}
		$claude         = true;
		$claude_skipped = true;
		$ckey           = (string) get_option( 'aiba_anthropic_api_key', '' );
		if ( '' !== $ckey ) {
			$claude_skipped = false;
			$test           = AIBA_Core::anthropic()->generate_text( 'Reply with exactly: OK', array( 'maxOutputTokens' => 32 ) );
			$claude         = ! is_wp_error( $test ) && str_contains( strtoupper( (string) $test ), 'OK' );
		}
		$custom         = true;
		$custom_skipped = true;
		$curl           = (string) get_option( 'aiba_custom_llm_url', '' );
		if ( '' !== $curl ) {
			$custom_skipped = false;
			$test_c         = AIBA_Core::custom_llm()->generate_text( 'Reply with exactly: OK', array( 'maxOutputTokens' => 32 ) );
			$custom         = ! is_wp_error( $test_c );
		}
		$pexels = false;
		$key    = (string) get_option( 'aiba_pexels_api_key', '' );
		if ( $key !== '' ) {
			$r = wp_remote_get(
				'https://api.pexels.com/v1/search?query=nature&per_page=1',
				array(
					'timeout' => 15,
					'headers' => array( 'Authorization' => $key ),
				)
			);
			$pexels = ! is_wp_error( $r ) && wp_remote_retrieve_response_code( $r ) >= 200 && wp_remote_retrieve_response_code( $r ) < 300;
		}

		$unsplash          = false;
		$unsplash_skipped  = true;
		$unsplash_key      = trim( (string) get_option( 'aiba_unsplash_access_key', '' ) );
		if ( $unsplash_key !== '' ) {
			$unsplash_skipped = false;
			$ur               = wp_remote_get(
				'https://api.unsplash.com/search/photos?query=nature&per_page=1',
				array(
					'timeout' => 15,
					'headers' => array( 'Authorization' => 'Client-ID ' . $unsplash_key ),
				)
			);
			$unsplash = ! is_wp_error( $ur ) && (int) wp_remote_retrieve_response_code( $ur ) === 200;
		}

		$google = true;
		$creds  = (string) get_option( 'aiba_google_credentials', '' );
		if ( $creds !== '' ) {
			$google = AIBA_Google_Indexing::instance()->verify_credentials_fresh();
		}

		wp_send_json_success(
			array(
				'gemini'          => $gemini,
				'openai'          => $openai,
				'openai_skipped'  => '' === $okey,
				'claude'          => $claude,
				'claude_skipped'  => $claude_skipped,
				'custom'          => $custom,
				'custom_skipped'  => $custom_skipped,
				'pexels'          => $pexels,
				'pexels_skipped'  => $key === '',
				'unsplash'        => $unsplash,
				'unsplash_skipped'=> $unsplash_skipped,
				'google'          => $google,
				'google_skipped'  => $creds === '',
			)
		);
	}

	public static function ajax_process_queue_now(): void {
		check_ajax_referer( 'aiba_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'ai-blog-automator' ) ), 403 );
		}
		AIBA_Scheduler::process_next_in_queue();
		wp_send_json_success( array( 'message' => __( 'Queue processed.', 'ai-blog-automator' ) ) );
	}

	public static function ajax_fetch_trends_now(): void {
		check_ajax_referer( 'aiba_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'ai-blog-automator' ) ), 403 );
		}
		AIBA_Scheduler::auto_fetch_trends_and_queue();
		wp_send_json_success( array( 'message' => __( 'Trend fetch finished.', 'ai-blog-automator' ) ) );
	}

	public static function ajax_clear_logs(): void {
		check_ajax_referer( 'aiba_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'ai-blog-automator' ) ), 403 );
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}aiba_logs" );
		wp_send_json_success();
	}

	public static function ajax_queue_bulk(): void {
		check_ajax_referer( 'aiba_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Forbidden.', 'ai-blog-automator' ) ), 403 );
		}

		$action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$ids    = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'intval', $_POST['ids'] ) : array();
		$ids    = array_filter( $ids );

		global $wpdb;
		$table = $wpdb->prefix . 'aiba_queue';

		if ( 'delete' === $action && ! empty( $ids ) ) {
			$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ($in)", $ids ) );
		}

		if ( 'requeue' === $action && ! empty( $ids ) ) {
			foreach ( $ids as $id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->update(
					$table,
					array(
						'status'       => 'pending',
						'scheduled_at' => null,
					),
					array( 'id' => $id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}
		}

		wp_send_json_success();
	}

	public static function export_logs_csv(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'ai-blog-automator' ) );
		}
		check_admin_referer( 'aiba_export_logs' );

		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}aiba_logs ORDER BY id DESC LIMIT %d",
				5000
			),
			ARRAY_A
		);

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=aiba-logs.csv' );
		$out = fopen( 'php://output', 'w' );
		if ( $out ) {
			fputcsv( $out, array( 'id', 'post_id', 'action', 'status', 'message', 'created_at' ) );
			foreach ( $rows as $r ) {
				fputcsv( $out, $r );
			}
			fclose( $out );
		}
		exit;
	}

	/**
	 * Add a row to the generation queue (admin_post).
	 */
	public static function handle_add_queue(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'ai-blog-automator' ) );
		}
		check_admin_referer( 'aiba_add_queue' );

		$topic   = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';

		$cat_ids = isset( $_POST['category_ids'] ) && is_array( $_POST['category_ids'] ) ? array_map( 'intval', wp_unslash( $_POST['category_ids'] ) ) : array();
		$cat_ids = array_values( array_filter( $cat_ids ) );
		if ( empty( $cat_ids ) && isset( $_POST['category_id'] ) ) {
			$cat_ids = array( (int) $_POST['category_id'] );
		}
		if ( empty( $cat_ids ) ) {
			$cat_ids = AIBA_Core::get_default_category_ids();
		}
		$primary_cat = ! empty( $cat_ids ) ? (int) $cat_ids[0] : (int) get_option( 'aiba_category_id', 0 );

		if ( $topic === '' || $keyword === '' ) {
			wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=aiba-queue' ) );
			exit;
		}

		$secondary_in = isset( $_POST['secondary_keywords'] ) ? sanitize_textarea_field( wp_unslash( $_POST['secondary_keywords'] ) ) : '';
		$secondary_arr = array_values(
			array_filter(
				array_map(
					'sanitize_text_field',
					array_map( 'trim', preg_split( '/[,;\r\n]+/', $secondary_in ) ?: array() )
				)
			)
		);
		$sec_store     = ! empty( $secondary_arr ) ? implode( ', ', $secondary_arr ) : null;
		$tpl_sel       = isset( $_POST['article_template'] ) ? sanitize_key( wp_unslash( $_POST['article_template'] ) ) : '';
		$tpl_sel       = AIBA_LLM_Templates::sanitize_article_template(
			$tpl_sel !== '' ? $tpl_sel : (string) get_option( 'aiba_article_template', 'standard' )
		);

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'aiba_queue',
			array(
				'topic'              => $topic,
				'keyword'            => $keyword,
				'category_id'        => $primary_cat,
				'category_ids'       => AIBA_Core::encode_queue_category_ids( $cat_ids ),
				'secondary_keywords' => $sec_store,
				'article_template'   => $tpl_sel,
				'scheduled_at'       => null,
				'status'             => 'pending',
				'post_id'            => 0,
				'created_at'         => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		wp_safe_redirect( admin_url( 'admin.php?page=aiba-queue&added=1' ) );
		exit;
	}

	/**
	 * Premium unlock / revoke (admin_post).
	 */
	public static function handle_premium_unlock(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'ai-blog-automator' ) );
		}
		check_admin_referer( 'aiba_premium_unlock' );

		$do = isset( $_POST['aiba_premium_action'] ) ? sanitize_text_field( wp_unslash( $_POST['aiba_premium_action'] ) ) : '';
		$to = admin_url( 'admin.php?page=aiba-settings' );

		if ( 'revoke' === $do ) {
			AIBA_Premium::revoke();
			wp_safe_redirect( add_query_arg( 'aiba_premium_revoked', '1', $to ) );
			exit;
		}

		if ( 'unlock' === $do ) {
			$code = isset( $_POST['aiba_premium_code'] ) ? sanitize_text_field( wp_unslash( $_POST['aiba_premium_code'] ) ) : '';
			if ( AIBA_Premium::unlock_with_code( $code ) ) {
				wp_safe_redirect( add_query_arg( 'aiba_premium', '1', $to ) );
			} else {
				wp_safe_redirect( add_query_arg( 'aiba_premium_err', '1', $to ) );
			}
			exit;
		}

		wp_safe_redirect( $to );
		exit;
	}

	/**
	 * Bulk-add queue rows from pasted keywords (admin_post).
	 */
	public static function handle_bulk_queue_keywords(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'ai-blog-automator' ) );
		}
		check_admin_referer( 'aiba_bulk_queue' );

		$lines = isset( $_POST['bulk_keywords'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bulk_keywords'] ) ) : '';

		$cat_ids = isset( $_POST['bulk_category_ids'] ) && is_array( $_POST['bulk_category_ids'] ) ? array_map( 'intval', wp_unslash( $_POST['bulk_category_ids'] ) ) : array();
		$cat_ids = array_values( array_filter( $cat_ids ) );
		if ( empty( $cat_ids ) ) {
			$cat_ids = AIBA_Core::get_default_category_ids();
		}

		$default_tpl = isset( $_POST['bulk_default_article_template'] ) ? sanitize_key( wp_unslash( $_POST['bulk_default_article_template'] ) ) : '';
		$default_tpl = AIBA_LLM_Templates::sanitize_article_template(
			$default_tpl !== '' ? $default_tpl : (string) get_option( 'aiba_article_template', 'standard' )
		);

		$schedule_mode  = isset( $_POST['bulk_schedule_mode'] ) ? sanitize_key( wp_unslash( $_POST['bulk_schedule_mode'] ) ) : 'none';
		$schedule_start = isset( $_POST['bulk_schedule_start'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_schedule_start'] ) ) : '';
		$time_hm        = (string) get_option( 'aiba_publish_time', '09:00' );
		if ( ! preg_match( '/^\d{2}:\d{2}$/', $time_hm ) ) {
			$time_hm = '09:00';
		}

		global $wpdb;
		$n           = 0;
		$line_index  = 0;
		$table       = $wpdb->prefix . 'aiba_queue';
		foreach ( preg_split( '/\r\n|\r|\n/', $lines ) as $line ) {
			$parsed = self::parse_queue_bulk_line( $line, $cat_ids, $default_tpl );
			if ( null === $parsed ) {
				continue;
			}
			$primary_cat = ! empty( $parsed['category_ids'] ) ? (int) $parsed['category_ids'][0] : (int) get_option( 'aiba_category_id', 0 );
			$scheduled   = self::bulk_queue_scheduled_mysql( $schedule_mode, $schedule_start, $line_index, $time_hm );
			$sec_store   = ! empty( $parsed['secondary'] ) ? implode( ', ', $parsed['secondary'] ) : null;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table,
				array(
					'topic'              => $parsed['topic'],
					'keyword'            => $parsed['keyword'],
					'category_id'        => $primary_cat,
					'category_ids'       => AIBA_Core::encode_queue_category_ids( $parsed['category_ids'] ),
					'secondary_keywords' => $sec_store,
					'article_template'   => $parsed['article_template'],
					'scheduled_at'       => $scheduled,
					'status'             => 'pending',
					'post_id'            => 0,
					'created_at'         => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
			);
			if ( $wpdb->insert_id ) {
				++$n;
				++$line_index;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'aiba-queue',
					'bulk_added' => (string) $n,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Next scheduled datetime for staggered bulk queue rows.
	 */
	private static function bulk_queue_scheduled_mysql( string $mode, string $start_ymd, int $line_index, string $time_hm ): ?string {
		$mode = sanitize_key( $mode );
		if ( 'none' === $mode || '' === $start_ymd || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_ymd ) ) {
			return null;
		}
		try {
			$base = new \DateTimeImmutable( $start_ymd . ' ' . $time_hm . ':00', wp_timezone() );
		} catch ( \Exception $e ) {
			return null;
		}
		if ( 'daily' === $mode ) {
			$d = $base->modify( '+' . $line_index . ' days' );
		} elseif ( 'weekly' === $mode ) {
			$d = $base->modify( '+' . ( $line_index * 7 ) . ' days' );
		} else {
			return null;
		}
		return $d->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Parse one bulk line: tabs (spreadsheet paste) or pipes. Up to 5 fields:
	 * title, focus keyphrase, other keywords (comma), category term IDs (comma), article format slug.
	 * Legacy: title|focus or title only (same for both).
	 *
	 * @param array<int, int> $fallback_cat_ids Categories when line omits IDs.
	 * @return array{ topic: string, keyword: string, secondary: array<int, string>, category_ids: array<int, int>, article_template: string }|null
	 */
	private static function parse_queue_bulk_line( string $line, array $fallback_cat_ids, string $default_template ): ?array {
		$line = trim( $line );
		if ( '' === $line ) {
			return null;
		}
		if ( str_contains( $line, "\t" ) ) {
			$parts = explode( "\t", $line );
		} else {
			$parts = explode( '|', $line );
		}
		$parts = array_map( 'trim', $parts );
		$n     = count( $parts );
		if ( $n < 1 ) {
			return null;
		}
		$topic = sanitize_text_field( (string) $parts[0] );
		$kw    = ( $n > 1 && $parts[1] !== '' ) ? sanitize_text_field( (string) $parts[1] ) : $topic;
		if ( '' === $topic || '' === $kw ) {
			return null;
		}
		$secondary_raw = $n > 2 ? (string) $parts[2] : '';
		$secondary     = array_values(
			array_filter(
				array_map(
					'sanitize_text_field',
					array_map( 'trim', preg_split( '/[,;]/', $secondary_raw ) ?: array() )
				)
			)
		);
		$cat_raw = $n > 3 ? trim( (string) $parts[3] ) : '';
		if ( $cat_raw !== '' ) {
			$parsed_cats = array_values(
				array_filter(
					array_map( 'intval', array_map( 'trim', explode( ',', $cat_raw ) ) )
				)
			);
			$use_cats    = ! empty( $parsed_cats ) ? $parsed_cats : $fallback_cat_ids;
		} else {
			$use_cats = $fallback_cat_ids;
		}
		$tpl_raw = $n > 4 ? sanitize_key( (string) $parts[4] ) : '';
		$tpl     = $tpl_raw !== '' ? AIBA_LLM_Templates::sanitize_article_template( $tpl_raw ) : $default_template;

		return array(
			'topic'              => $topic,
			'keyword'            => $kw,
			'secondary'          => $secondary,
			'category_ids'       => $use_cats,
			'article_template'   => $tpl,
		);
	}
}

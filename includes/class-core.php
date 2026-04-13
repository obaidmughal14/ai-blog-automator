<?php
/**
 * Plugin bootstrap.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Core class.
 */
class AIBA_Core {

	/**
	 * Light includes (front-end visitors).
	 *
	 * @var bool
	 */
	private static bool $light_includes_loaded = false;

	/**
	 * Full stack (admin, cron, CLI, or on demand).
	 *
	 * @var bool
	 */
	private static bool $full_includes_loaded = false;

	/**
	 * Shared service instances.
	 *
	 * @var array<string, object>
	 */
	private static array $services = array();

	/**
	 * Initialize plugin.
	 */
	public static function init(): void {
		if ( self::needs_full_bootstrap() ) {
			self::load_full_includes();
		} else {
			self::load_light_includes();
		}
		add_action( 'init', array( __CLASS__, 'maybe_upgrade_db' ), 5 );
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );

		AIBA_Google_Indexing::instance();
		AIBA_SEO_Handler::init();
		AIBA_Scheduler::init();
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			AIBA_Admin_UI::init();
		}
	}

	/**
	 * Load heavy dependencies only in admin, cron, or CLI — not on public page views.
	 */
	private static function needs_full_bootstrap(): bool {
		// Activation "sandbox" include runs before the plugin is active; load full stack (matches pre-split behavior).
		if ( defined( 'WP_SANDBOX_SCRAPING' ) && WP_SANDBOX_SCRAPING ) {
			return true;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return true;
		}
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return true;
		}
		return false;
	}

	/**
	 * Load translations.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'ai-blog-automator', false, dirname( plugin_basename( AIBA_PLUGIN_DIR . 'ai-blog-automator.php' ) ) . '/languages' );
	}

	/**
	 * Minimal files for public requests (meta/schema, cron hooks, indexing on publish).
	 */
	public static function load_light_includes(): void {
		if ( self::$light_includes_loaded || self::$full_includes_loaded ) {
			return;
		}
		$dir = AIBA_PLUGIN_DIR . 'includes/';
		require_once $dir . 'class-google-indexing.php';
		require_once $dir . 'class-seo-handler.php';
		require_once $dir . 'class-scheduler.php';
		self::$light_includes_loaded = true;
	}

	/**
	 * All PHP includes (generation, admin, APIs).
	 */
	public static function load_full_includes(): void {
		if ( self::$full_includes_loaded ) {
			return;
		}
		$dir = AIBA_PLUGIN_DIR . 'includes/';
		require_once $dir . 'class-gemini-api.php';
		require_once $dir . 'class-openai-api.php';
		require_once $dir . 'class-anthropic-api.php';
		require_once $dir . 'class-custom-llm-api.php';
		require_once $dir . 'class-llm-templates.php';
		require_once $dir . 'class-llm-client.php';
		require_once $dir . 'class-premium.php';
		require_once $dir . 'class-trend-fetcher.php';
		require_once $dir . 'class-content-generator.php';
		require_once $dir . 'class-seo-handler.php';
		require_once $dir . 'class-image-handler.php';
		require_once $dir . 'class-internal-linker.php';
		require_once $dir . 'class-post-publisher.php';
		require_once $dir . 'class-google-indexing.php';
		require_once $dir . 'class-scheduler.php';
		require_once $dir . 'class-admin-ui.php';
		self::$full_includes_loaded  = true;
		self::$light_includes_loaded = true;
	}

	/**
	 * @deprecated Use load_full_includes().
	 */
	public static function load_includes(): void {
		self::load_full_includes();
	}

	/**
	 * Activation callback.
	 *
	 * @param bool $network_wide Multisite: whether the plugin was network-activated (WordPress passes this).
	 */
	public static function activate( $network_wide = false ): void {
		// Activation must stay lean: avoid loading LLM/API/admin classes here (sandbox + low memory hosts).
		require_once AIBA_PLUGIN_DIR . 'includes/class-scheduler.php';
		self::apply_db_schema();
		update_option( 'aiba_db_schema', 3 );
		self::add_default_options();
		AIBA_Scheduler::register_cron_schedules_filter();
		$recurrence = self::map_queue_frequency_to_recurrence( (string) get_option( 'aiba_queue_frequency', 'daily' ) );
		if ( ! wp_next_scheduled( 'aiba_process_queue' ) ) {
			wp_schedule_event( time() + 60, $recurrence, 'aiba_process_queue' );
		}
		if ( ! wp_next_scheduled( 'aiba_daily_trends' ) ) {
			wp_schedule_event( time() + 120, 'daily', 'aiba_daily_trends' );
		}
	}

	/**
	 * Map queue frequency option to WP cron schedule key.
	 *
	 * @param string $freq Frequency slug.
	 * @return string
	 */
	public static function map_queue_frequency_to_recurrence( $freq ): string {
		$freq = is_string( $freq ) ? $freq : (string) $freq;
		return match ( $freq ) {
			'2hr' => 'aiba_every_2_hours',
			'3hr' => 'aiba_every_3_hours',
			'6hr' => 'aiba_every_6_hours',
			'12hr' => 'aiba_every_12_hours',
			'custom' => 'aiba_queue_custom',
			default => 'aiba_daily',
		};
	}

	/**
	 * dbDelta queue table when schema version bumps (adds category_ids, etc.).
	 */
	public static function maybe_upgrade_db(): void {
		$ver = (int) get_option( 'aiba_db_schema', 0 );
		if ( $ver >= 3 ) {
			return;
		}
		self::apply_db_schema();
		update_option( 'aiba_db_schema', 3 );
	}

	/**
	 * JSON list of category term IDs for queue rows.
	 *
	 * @param array<int, int> $ids Term IDs.
	 */
	public static function encode_queue_category_ids( array $ids ): string {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		return $ids ? wp_json_encode( $ids ) : '';
	}

	/**
	 * @return array<int, int>
	 */
	public static function decode_queue_category_ids( string $json ): array {
		if ( '' === trim( $json ) ) {
			return array();
		}
		$a = json_decode( $json, true );
		return is_array( $a ) ? array_values( array_filter( array_map( 'intval', $a ) ) ) : array();
	}

	/**
	 * Default category IDs from settings (multi + legacy single).
	 *
	 * @return array<int, int>
	 */
	public static function get_default_category_ids(): array {
		$multi = get_option( 'aiba_category_ids', array() );
		$multi = is_array( $multi ) ? array_filter( array_map( 'intval', $multi ) ) : array();
		if ( ! empty( $multi ) ) {
			return array_values( array_unique( $multi ) );
		}
		$one = (int) get_option( 'aiba_category_id', 0 );
		return $one ? array( $one ) : array();
	}

	/**
	 * Deactivation callback.
	 *
	 * @param bool $network_deactivating Multisite: whether the plugin is deactivated network-wide (WordPress passes this).
	 */
	public static function deactivate( $network_deactivating = false ): void {
		wp_clear_scheduled_hook( 'aiba_process_queue' );
		wp_clear_scheduled_hook( 'aiba_daily_trends' );
	}

	/**
	 * Create / upgrade database tables.
	 */
	private static function apply_db_schema(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$logs    = "CREATE TABLE {$wpdb->prefix}aiba_logs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(100) NOT NULL,
			status varchar(20) NOT NULL,
			message text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY status (status),
			KEY action (action),
			KEY created_at (created_at)
		) $charset;";
		$queue   = "CREATE TABLE {$wpdb->prefix}aiba_queue (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			topic varchar(500) NOT NULL,
			keyword varchar(255) NOT NULL,
			category_id bigint(20) unsigned NOT NULL DEFAULT 0,
			category_ids text NULL,
			scheduled_at datetime NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY scheduled_at (scheduled_at)
		) $charset;";
		dbDelta( $logs );
		dbDelta( $queue );
	}

	/**
	 * Add default options (activation only).
	 */
	private static function add_default_options(): void {
		$admin_id = (int) get_option( 'aiba_author_id', 0 );
		if ( $admin_id < 1 ) {
			$users = get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
					'fields' => array( 'ID' ),
				)
			);
			$admin_id = $users ? (int) $users[0]->ID : 1;
		}

		add_option( 'aiba_gemini_api_key', '' );
		add_option( 'aiba_pexels_api_key', '' );
		add_option( 'aiba_google_credentials', '' );
		add_option( 'aiba_site_niche', '' );
		add_option( 'aiba_word_count', 1500 );
		add_option( 'aiba_tone', 'Professional' );
		add_option( 'aiba_language', 'English' );
		add_option( 'aiba_author_id', $admin_id );
		add_option( 'aiba_category_id', (int) get_option( 'default_category' ) );
		add_option( 'aiba_auto_tags', '1' );
		add_option( 'aiba_max_internal_links', 5 );
		add_option( 'aiba_images_per_post', 3 );
		add_option( 'aiba_auto_trends', '1' );
		add_option( 'aiba_auto_publish', '0' );
		add_option( 'aiba_publish_status', 'draft' );
		add_option( 'aiba_posts_per_day', 1 );
		add_option( 'aiba_publish_time', '09:00' );
		add_option( 'aiba_auto_index', '1' );
		add_option( 'aiba_queue_frequency', 'daily' );
		add_option( 'aiba_seo_plugin', 'auto' );
		add_option( 'aiba_add_faq_schema', '1' );
		add_option( 'aiba_add_article_schema', '1' );
		add_option( 'aiba_canonical', '1' );
		add_option( 'aiba_og_tags', '1' );
		add_option( 'aiba_delete_on_uninstall', '0' );
		add_option( 'aiba_max_retries', 3 );
		add_option( 'aiba_log_retention', 30 );
		add_option( 'aiba_disabled_types', array() );
		add_option( 'aiba_faq_css', '1' );
		add_option( 'aiba_llm_provider', 'auto' );
		add_option( 'aiba_openai_api_key', '' );
		add_option( 'aiba_openai_model', 'gpt-4o-mini' );
		add_option( 'aiba_anthropic_api_key', '' );
		add_option( 'aiba_anthropic_model', 'claude-sonnet-4-20250514' );
		add_option( 'aiba_custom_llm_url', '' );
		add_option( 'aiba_custom_llm_api_key', '' );
		add_option( 'aiba_custom_llm_model', 'default' );
		add_option( 'aiba_custom_llm_auth_header', 'Authorization' );
		add_option( 'aiba_queue_custom_minutes', 120 );
		add_option( 'aiba_category_ids', array() );
		add_option( 'aiba_article_template', 'standard' );
		add_option( 'aiba_ai_tag_expansion', '0' );
		add_option( 'aiba_ai_suggest_categories', '0' );
		add_option( 'aiba_prompt_outline_prefix', '' );
		add_option( 'aiba_prompt_outline_suffix', '' );
		add_option( 'aiba_prompt_section_prefix', '' );
		add_option( 'aiba_prompt_section_suffix', '' );
		add_option( 'aiba_prompt_global_append', '' );
		add_option( 'aiba_premium_unlocked', '0' );
	}

	/**
	 * Insert log row.
	 *
	 * @param int    $post_id Post ID (0 for global).
	 * @param string $action Action slug.
	 * @param string $status success|error|warning.
	 * @param string $message Message.
	 */
	public static function log( int $post_id, string $action, string $status, string $message ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			"{$wpdb->prefix}aiba_logs",
			array(
				'post_id'    => $post_id,
				'action'     => sanitize_text_field( $action ),
				'status'     => sanitize_text_field( $status ),
				'message'    => sanitize_textarea_field( $message ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Lazy-loaded Gemini client.
	 */
	public static function gemini(): AIBA_Gemini_API {
		self::load_full_includes();
		if ( ! isset( self::$services['gemini'] ) ) {
			self::$services['gemini'] = new AIBA_Gemini_API();
		}
		return self::$services['gemini'];
	}

	/**
	 * OpenAI client.
	 */
	public static function openai(): AIBA_OpenAI_API {
		self::load_full_includes();
		if ( ! isset( self::$services['openai'] ) ) {
			self::$services['openai'] = new AIBA_OpenAI_API();
		}
		return self::$services['openai'];
	}

	/**
	 * Anthropic Claude client.
	 */
	public static function anthropic(): AIBA_Anthropic_API {
		self::load_full_includes();
		if ( ! isset( self::$services['anthropic'] ) ) {
			self::$services['anthropic'] = new AIBA_Anthropic_API();
		}
		return self::$services['anthropic'];
	}

	/**
	 * Custom OpenAI-compatible endpoint.
	 */
	public static function custom_llm(): AIBA_Custom_LLM_API {
		self::load_full_includes();
		if ( ! isset( self::$services['custom_llm'] ) ) {
			self::$services['custom_llm'] = new AIBA_Custom_LLM_API();
		}
		return self::$services['custom_llm'];
	}

	/**
	 * Unified LLM (Gemini + optional OpenAI fallback).
	 */
	public static function llm(): AIBA_LLM_Client {
		self::load_full_includes();
		if ( ! isset( self::$services['llm'] ) ) {
			self::$services['llm'] = new AIBA_LLM_Client();
		}
		return self::$services['llm'];
	}

	/**
	 * Trend fetcher.
	 */
	public static function trend_fetcher(): AIBA_Trend_Fetcher {
		self::load_full_includes();
		if ( ! isset( self::$services['trends'] ) ) {
			self::$services['trends'] = new AIBA_Trend_Fetcher( self::llm() );
		}
		return self::$services['trends'];
	}

	/**
	 * Content generator.
	 */
	public static function content_generator(): AIBA_Content_Generator {
		self::load_full_includes();
		if ( ! isset( self::$services['content'] ) ) {
			self::$services['content'] = new AIBA_Content_Generator( self::llm() );
		}
		return self::$services['content'];
	}

	/**
	 * Post publisher (full pipeline dependencies).
	 */
	public static function post_publisher(): AIBA_Post_Publisher {
		self::load_full_includes();
		if ( ! isset( self::$services['publisher'] ) ) {
			self::$services['publisher'] = new AIBA_Post_Publisher(
				new AIBA_SEO_Handler(),
				new AIBA_Image_Handler(),
				new AIBA_Internal_Linker( self::llm() ),
				AIBA_Google_Indexing::instance()
			);
		}
		return self::$services['publisher'];
	}
}

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
	 * Prevent duplicate hook registration if the bootstrap runs twice in one request.
	 *
	 * @var bool
	 */
	private static bool $plugin_booted = false;

	/**
	 * Whether all PHP includes were loaded.
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
	 * Initialize plugin (idempotent).
	 */
	public static function init(): void {
		if ( self::$plugin_booted ) {
			return;
		}
		self::$plugin_booted = true;

		self::load_full_includes();
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
	 * Load translations.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'ai-blog-automator', false, dirname( plugin_basename( AIBA_PLUGIN_DIR . 'ai-blog-automator.php' ) ) . '/languages' );
	}

	/**
	 * All PHP includes (generation, admin, APIs).
	 */
	public static function load_full_includes(): void {
		if ( self::$full_includes_loaded ) {
			return;
		}
		$dir         = AIBA_PLUGIN_DIR . 'includes/';
		$manifest    = $dir . 'bootstrap-manifest.php';
		$class_files = file_exists( $manifest ) ? require $manifest : array();

		if ( ! is_array( $class_files ) || array() === $class_files ) {
			$class_files = array(
				'class-gemini-api.php',
				'class-openai-api.php',
				'class-anthropic-api.php',
				'class-custom-llm-api.php',
				'class-llm-templates.php',
				'class-llm-client.php',
				'class-premium.php',
				'class-trend-fetcher.php',
				'class-content-generator.php',
				'class-seo-handler.php',
				'class-image-handler.php',
				'class-internal-linker.php',
				'class-post-publisher.php',
				'class-google-indexing.php',
				'class-scheduler.php',
				'class-admin-ui.php',
			);
		}

		foreach ( $class_files as $file ) {
			require_once $dir . $file;
		}

		self::$full_includes_loaded = true;
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
		update_option( 'aiba_db_schema', 4 );
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
		switch ( $freq ) {
			case '2hr':
				return 'aiba_every_2_hours';
			case '3hr':
				return 'aiba_every_3_hours';
			case '6hr':
				return 'aiba_every_6_hours';
			case '12hr':
				return 'aiba_every_12_hours';
			case 'custom':
				return 'aiba_queue_custom';
			default:
				return 'aiba_daily';
		}
	}

	/**
	 * dbDelta queue table when schema version bumps (adds category_ids, etc.).
	 */
	public static function maybe_upgrade_db(): void {
		$ver = (int) get_option( 'aiba_db_schema', 0 );
		if ( $ver >= 4 ) {
			return;
		}
		self::apply_db_schema();
		update_option( 'aiba_db_schema', 4 );
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
	 * @param mixed $json Stored JSON string or empty from DB.
	 * @return array<int, int>
	 */
	public static function decode_queue_category_ids( $json ): array {
		if ( null === $json || false === $json ) {
			return array();
		}
		$json = is_string( $json ) ? $json : '';
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
			secondary_keywords text NULL,
			article_template varchar(64) NOT NULL DEFAULT '',
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
		add_option( 'aiba_unsplash_access_key', '' );
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

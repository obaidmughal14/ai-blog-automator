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
	 * Whether includes were loaded.
	 *
	 * @var bool
	 */
	private static bool $includes_loaded = false;

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
		self::load_includes();
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );

		AIBA_Google_Indexing::instance();
		AIBA_SEO_Handler::init();
		AIBA_Scheduler::init();
		AIBA_Admin_UI::init();
	}

	/**
	 * Load translations.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'ai-blog-automator', false, dirname( plugin_basename( AIBA_PLUGIN_DIR . 'ai-blog-automator.php' ) ) . '/languages' );
	}

	/**
	 * Load PHP includes once.
	 */
	public static function load_includes(): void {
		if ( self::$includes_loaded ) {
			return;
		}
		$dir = AIBA_PLUGIN_DIR . 'includes/';
		require_once $dir . 'class-gemini-api.php';
		require_once $dir . 'class-openai-api.php';
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
		self::$includes_loaded = true;
	}

	/**
	 * Activation callback.
	 */
	public static function activate(): void {
		self::load_includes();
		self::create_tables();
		self::add_default_options();
		AIBA_Scheduler::register_cron_schedules_filter();
		$recurrence = self::map_queue_frequency_to_recurrence( get_option( 'aiba_queue_frequency', 'daily' ) );
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
	public static function map_queue_frequency_to_recurrence( string $freq ): string {
		return match ( $freq ) {
			'6hr' => 'aiba_every_6_hours',
			'12hr' => 'aiba_every_12_hours',
			default => 'aiba_daily',
		};
	}

	/**
	 * Deactivation callback.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'aiba_process_queue' );
		wp_clear_scheduled_hook( 'aiba_daily_trends' );
	}

	/**
	 * Create database tables.
	 */
	private static function create_tables(): void {
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

		add_option( 'aiba_gemini_api_key', 'AIzaSyBtbj6SCyPwYGEGLIim3SXNqs_sTc7RjKM' );
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
		if ( ! isset( self::$services['gemini'] ) ) {
			self::$services['gemini'] = new AIBA_Gemini_API();
		}
		return self::$services['gemini'];
	}

	/**
	 * OpenAI client.
	 */
	public static function openai(): AIBA_OpenAI_API {
		if ( ! isset( self::$services['openai'] ) ) {
			self::$services['openai'] = new AIBA_OpenAI_API();
		}
		return self::$services['openai'];
	}

	/**
	 * Unified LLM (Gemini + optional OpenAI fallback).
	 */
	public static function llm(): AIBA_LLM_Client {
		if ( ! isset( self::$services['llm'] ) ) {
			self::$services['llm'] = new AIBA_LLM_Client();
		}
		return self::$services['llm'];
	}

	/**
	 * Trend fetcher.
	 */
	public static function trend_fetcher(): AIBA_Trend_Fetcher {
		if ( ! isset( self::$services['trends'] ) ) {
			self::$services['trends'] = new AIBA_Trend_Fetcher( self::llm() );
		}
		return self::$services['trends'];
	}

	/**
	 * Content generator.
	 */
	public static function content_generator(): AIBA_Content_Generator {
		if ( ! isset( self::$services['content'] ) ) {
			self::$services['content'] = new AIBA_Content_Generator( self::llm() );
		}
		return self::$services['content'];
	}

	/**
	 * Post publisher (full pipeline dependencies).
	 */
	public static function post_publisher(): AIBA_Post_Publisher {
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

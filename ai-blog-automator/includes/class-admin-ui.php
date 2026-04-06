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
	}

	public static function register_settings(): void {
		$opts = array(
			'aiba_gemini_api_key',
			'aiba_llm_provider',
			'aiba_openai_api_key',
			'aiba_openai_model',
			'aiba_pexels_api_key',
			'aiba_google_credentials',
			'aiba_site_niche',
			'aiba_word_count',
			'aiba_tone',
			'aiba_language',
			'aiba_author_id',
			'aiba_category_id',
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
		$allowed = array( 'auto', 'gemini', 'openai' );
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

	public static function sanitize_option_aiba_pexels_api_key( $value ): string {
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	public static function sanitize_option_aiba_google_credentials( $value ): string {
		return is_string( $value ) ? sanitize_textarea_field( $value ) : '';
	}

	public static function sanitize_option_aiba_site_niche( $value ): string {
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	public static function sanitize_option_aiba_word_count( $value ): int {
		return max( 300, (int) $value );
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
		$allowed = array( 'daily', '12hr', '6hr' );
		$v       = is_string( $value ) ? $value : 'daily';
		return in_array( $v, $allowed, true ) ? $v : 'daily';
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
		if ( ! in_array( $hook, self::plugin_page_hooks(), true ) ) {
			return;
		}
		wp_enqueue_style( 'aiba-admin', AIBA_PLUGIN_URL . 'assets/css/admin.css', array(), AIBA_VERSION );
		wp_enqueue_script( 'aiba-admin', AIBA_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), AIBA_VERSION, true );
		wp_localize_script(
			'aiba-admin',
			'aibaAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'adminBase' => admin_url( 'admin.php' ),
				'nonce'     => wp_create_nonce( 'aiba_admin' ),
				'genNonce'  => wp_create_nonce( 'aiba_generate' ),
			)
		);
	}

	public static function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$stats = self::get_dashboard_stats();
		$logs  = self::get_recent_logs( 10 );
		$queue = self::get_queue_preview( 5 );
		include AIBA_PLUGIN_DIR . 'templates/admin-dashboard.php';
	}

	public static function render_generate(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$niche = (string) get_option( 'aiba_site_niche', '' );
		$trends = array();
		if ( $niche !== '' ) {
			$slug = substr( preg_replace( '/[^a-z0-9]+/i', '_', strtolower( $niche ) ), 0, 80 );
			$cached = get_transient( 'aiba_trends_' . $slug );
			if ( is_array( $cached ) ) {
				$trends = $cached;
			}
		}
		include AIBA_PLUGIN_DIR . 'templates/admin-generate.php';
	}

	public static function render_queue(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$status = isset( $_GET['aiba_status'] ) ? sanitize_text_field( wp_unslash( $_GET['aiba_status'] ) ) : '';
		$rows   = self::get_queue_rows( $status );
		include AIBA_PLUGIN_DIR . 'templates/admin-queue.php';
	}

	public static function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
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
		$status = isset( $_GET['aiba_log_status'] ) ? sanitize_text_field( wp_unslash( $_GET['aiba_log_status'] ) ) : '';
		$action = isset( $_GET['aiba_log_action'] ) ? sanitize_text_field( wp_unslash( $_GET['aiba_log_action'] ) ) : '';
		$from   = isset( $_GET['aiba_from'] ) ? sanitize_text_field( wp_unslash( $_GET['aiba_from'] ) ) : '';
		$to     = isset( $_GET['aiba_to'] ) ? sanitize_text_field( wp_unslash( $_GET['aiba_to'] ) ) : '';
		$rows   = self::get_log_rows( $status, $action, $from, $to );
		include AIBA_PLUGIN_DIR . 'templates/admin-logs.php';
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

		$topic      = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
		$primary    = isset( $_POST['primary_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['primary_keyword'] ) ) : '';
		$secondary  = isset( $_POST['secondary_keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['secondary_keywords'] ) ) : '';
		$category   = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : (int) get_option( 'aiba_category_id', 0 );
		$word_count = isset( $_POST['word_count'] ) ? (int) $_POST['word_count'] : (int) get_option( 'aiba_word_count', 1500 );
		$tone       = isset( $_POST['tone'] ) ? sanitize_text_field( wp_unslash( $_POST['tone'] ) ) : (string) get_option( 'aiba_tone', 'Professional' );
		$publish_now = ! empty( $_POST['publish_now'] );

		$sec_arr = array_filter( array_map( 'trim', explode( ',', $secondary ) ) );

		$job = array(
			'topic'               => $topic,
			'primary_keyword'     => $primary,
			'secondary_keywords'    => $sec_arr,
			'category_id'         => $category,
			'word_count'          => $word_count,
			'tone'                => $tone,
			'language'            => (string) get_option( 'aiba_language', 'English' ),
		);

		$article = AIBA_Core::content_generator()->generate_article( $job );
		if ( is_wp_error( $article ) ) {
			if ( AIBA_LLM_Client::is_rate_limit_error( $article ) ) {
				wp_send_json_error(
					array(
						'code'    => 'rate_limit',
						'message' => __( 'Gemini quota or rate limit reached. Wait several minutes, enable billing in Google AI Studio if needed, or reduce how often the queue runs. Nothing was saved.', 'ai-blog-automator' ),
					)
				);
			}
			wp_send_json_error( array( 'message' => $article->get_error_message() ) );
		}

		$settings = array(
			'author_id'      => (int) get_option( 'aiba_author_id', get_current_user_id() ),
			'category_id'    => $category,
			'auto_publish'   => $publish_now,
			'publish_status' => $publish_now ? 'publish' : (string) get_option( 'aiba_publish_status', 'draft' ),
			'scheduled_time' => null,
			'topic'          => $topic,
		);

		$post_id = AIBA_Core::post_publisher()->publish_post( $article, $settings );
		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
		}

		$p = get_post( $post_id );
		$score = AIBA_SEO_Handler::calculate_seo_score(
			(string) $p->post_content,
			(string) ( $article['primary_keyword'] ?? '' ),
			(string) $p->post_title,
			(string) ( $article['meta_description'] ?? '' )
		);

		wp_send_json_success(
			array(
				'post_id'   => $post_id,
				'post_url'  => get_edit_post_link( $post_id, 'raw' ),
				'view_url'  => get_permalink( $post_id ),
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

		$google = true;
		$creds  = (string) get_option( 'aiba_google_credentials', '' );
		if ( $creds !== '' ) {
			$google = AIBA_Google_Indexing::instance()->verify_credentials_fresh();
		}

		wp_send_json_success(
			array(
				'gemini'         => $gemini,
				'openai'         => $openai,
				'openai_skipped' => '' === $okey,
				'pexels'         => $pexels,
				'pexels_skipped' => $key === '',
				'google'         => $google,
				'google_skipped' => $creds === '',
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

		$topic    = isset( $_POST['topic'] ) ? sanitize_text_field( wp_unslash( $_POST['topic'] ) ) : '';
		$keyword  = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		$category = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : (int) get_option( 'aiba_category_id', 0 );

		if ( $topic === '' || $keyword === '' ) {
			wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=aiba-queue' ) );
			exit;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'aiba_queue',
			array(
				'topic'        => $topic,
				'keyword'      => $keyword,
				'category_id'  => $category,
				'scheduled_at' => null,
				'status'       => 'pending',
				'post_id'      => 0,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
		);

		wp_safe_redirect( admin_url( 'admin.php?page=aiba-queue&added=1' ) );
		exit;
	}
}

<?php
/**
 * WP-Cron scheduling and queue processing.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Scheduler.
 */
class AIBA_Scheduler {

	public static function init(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'filter_cron_schedules' ) );
		add_action( 'aiba_process_queue', array( __CLASS__, 'process_next_in_queue' ) );
		add_action( 'aiba_daily_trends', array( __CLASS__, 'auto_fetch_trends_and_queue' ) );
		add_action( 'init', array( __CLASS__, 'maybe_reschedule_on_boot' ), 20 );
	}

	/**
	 * Register cron schedules filter (activation + runtime).
	 */
	public static function register_cron_schedules_filter(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'filter_cron_schedules' ) );
	}

	/**
	 * Custom intervals.
	 *
	 * @param array<string, array<string, int|string>> $schedules Schedules.
	 * @return array<string, array<string, int|string>>
	 */
	public static function filter_cron_schedules( array $schedules ): array {
		$schedules['aiba_every_2_hours']  = array(
			'interval' => 7200,
			'display'  => __( 'Every 2 Hours', 'ai-blog-automator' ),
		);
		$schedules['aiba_every_3_hours']  = array(
			'interval' => 10800,
			'display'  => __( 'Every 3 Hours', 'ai-blog-automator' ),
		);
		$schedules['aiba_every_6_hours']  = array(
			'interval' => 21600,
			'display'  => __( 'Every 6 Hours', 'ai-blog-automator' ),
		);
		$schedules['aiba_every_12_hours'] = array(
			'interval' => 43200,
			'display'  => __( 'Every 12 Hours', 'ai-blog-automator' ),
		);
		$schedules['aiba_daily']          = array(
			'interval' => 86400,
			'display'  => __( 'Daily (AIBA)', 'ai-blog-automator' ),
		);
		$min = max( 30, min( 1440, (int) get_option( 'aiba_queue_custom_minutes', 120 ) ) );
		$schedules['aiba_queue_custom']   = array(
			'interval' => $min * MINUTE_IN_SECONDS,
			/* translators: %d: minutes between queue runs */
			'display'  => sprintf( __( 'Every %d minutes (custom)', 'ai-blog-automator' ), $min ),
		);
		return $schedules;
	}

	/**
	 * Ensure queue cron recurrence matches settings.
	 */
	public static function maybe_reschedule_on_boot(): void {
		if ( ! wp_next_scheduled( 'aiba_process_queue' ) ) {
			$rec = AIBA_Core::map_queue_frequency_to_recurrence( (string) get_option( 'aiba_queue_frequency', 'daily' ) );
			wp_schedule_event( time() + 60, $rec, 'aiba_process_queue' );
		}
	}

	/**
	 * Reschedule queue processor when settings saved.
	 */
	public static function reschedule_queue_event(): void {
		wp_clear_scheduled_hook( 'aiba_process_queue' );
		$rec = AIBA_Core::map_queue_frequency_to_recurrence( (string) get_option( 'aiba_queue_frequency', 'daily' ) );
		wp_schedule_event( time() + 60, $rec, 'aiba_process_queue' );
	}

	/**
	 * Process oldest pending queue row.
	 */
	public static function process_next_in_queue(): void {
		global $wpdb;
		$now = current_time( 'mysql' );
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}aiba_queue WHERE status = %s AND (scheduled_at IS NULL OR scheduled_at <= %s) ORDER BY id ASC LIMIT 1",
				'pending',
				$now
			)
		);
		if ( ! $row ) {
			return;
		}

		$id = (int) $row->id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$wpdb->prefix . 'aiba_queue',
			array( 'status' => 'processing' ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		$max_retries = max( 0, (int) get_option( 'aiba_max_retries', 3 ) );
		$max_retries = AIBA_Premium::enhance_max_retries( $max_retries );
		$attempt     = 0;
		$last_error  = '';

		$cat_json = ( isset( $row->category_ids ) && is_string( $row->category_ids ) ) ? $row->category_ids : '';
		$job_cats = AIBA_Core::decode_queue_category_ids( $cat_json );
		if ( empty( $job_cats ) && (int) $row->category_id ) {
			$job_cats = array( (int) $row->category_id );
		}

		$job = array(
			'topic'                => $row->topic,
			'primary_keyword'      => $row->keyword,
			'secondary_keywords'   => array(),
			'category_id'          => (int) $row->category_id,
			'category_ids'         => $job_cats,
			'word_count'           => max( 300, min( 5000, (int) get_option( 'aiba_word_count', 1500 ) ) ),
			'tone'                 => (string) get_option( 'aiba_tone', 'Professional' ),
			'language'             => (string) get_option( 'aiba_language', 'English' ),
			'article_template'     => AIBA_LLM_Templates::sanitize_article_template( (string) get_option( 'aiba_article_template', 'standard' ) ),
		);

		while ( $attempt <= $max_retries ) {
			++$attempt;
			try {
				$article = AIBA_Core::content_generator()->generate_article( $job );
				if ( is_wp_error( $article ) ) {
					if ( AIBA_LLM_Client::is_rate_limit_error( $article ) ) {
						self::defer_queue_job_rate_limited( $id, $article, $row->topic );
						return;
					}
					$last_error = $article->get_error_message();
					AIBA_Core::log( 0, 'generate', 'error', $last_error );
					continue;
				}

				$settings = array(
					'author_id'      => (int) get_option( 'aiba_author_id', get_current_user_id() ),
					'category_id'    => (int) $row->category_id,
					'category_ids'   => $job_cats,
					'auto_publish'   => '1' === (string) get_option( 'aiba_auto_publish', '0' ),
					'publish_status' => (string) get_option( 'aiba_publish_status', 'draft' ),
					'scheduled_time' => null,
					'topic'          => $row->topic,
				);

				$post_id = AIBA_Core::post_publisher()->publish_post( $article, $settings );
				if ( is_wp_error( $post_id ) ) {
					$last_error = $post_id->get_error_message();
					AIBA_Core::log( 0, 'publish', 'error', $last_error );
					continue;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->update(
					$wpdb->prefix . 'aiba_queue',
					array(
						'status'  => 'completed',
						'post_id' => (int) $post_id,
					),
					array( 'id' => $id ),
					array( '%s', '%d' ),
					array( '%d' )
				);
				AIBA_Core::log( (int) $post_id, 'queue', 'success', 'Queue job completed: ' . $row->topic );
				return;
			} catch ( Exception $e ) {
				$last_error = $e->getMessage();
				AIBA_Core::log( 0, 'exception', 'error', $last_error );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$wpdb->prefix . 'aiba_queue',
			array( 'status' => 'failed' ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
		AIBA_Core::log( 0, 'queue', 'error', 'Job failed after retries: ' . $last_error );
	}

	/**
	 * Put job back to pending after Gemini quota / 429 (avoid burning retries in seconds).
	 *
	 * @param int    $queue_id Queue row ID.
	 * @param WP_Error $error  Rate-limit error.
	 * @param string $topic   Topic label for log.
	 */
	private static function defer_queue_job_rate_limited( int $queue_id, WP_Error $error, string $topic ): void {
		global $wpdb;
		$secs      = AIBA_LLM_Client::get_retry_after_from_error( $error );
		$run_after = time() + $secs;
		$scheduled = wp_date( 'Y-m-d H:i:s', $run_after );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$wpdb->prefix . 'aiba_queue',
			array(
				'status'       => 'pending',
				'scheduled_at' => $scheduled,
			),
			array( 'id' => $queue_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		AIBA_Core::log(
			0,
			'queue',
			'warning',
			sprintf(
				/* translators: 1: topic, 2: datetime, 3: seconds */
				__( 'LLM rate limit / quota. Deferred "%1$s" until %2$s (in ~%3$d min). Check API billing or wait.', 'ai-blog-automator' ),
				$topic,
				$scheduled,
				(int) ceil( $secs / 60 )
			)
		);
	}

	/**
	 * Daily trends fetch and enqueue.
	 */
	public static function auto_fetch_trends_and_queue(): void {
		if ( '1' !== (string) get_option( 'aiba_auto_trends', '1' ) ) {
			return;
		}

		$niche = trim( (string) get_option( 'aiba_site_niche', '' ) );
		if ( '' === $niche ) {
			$niche = sanitize_text_field( get_bloginfo( 'name' ) );
			if ( '' !== $niche ) {
				AIBA_Core::log(
					0,
					'trends',
					'warning',
					__( 'Site Niche is empty — using WordPress site title for trend fetch. Set “Site niche / topic” under Settings → Content for better control.', 'ai-blog-automator' )
				);
			}
		}
		if ( '' === $niche ) {
			AIBA_Core::log(
				0,
				'trends',
				'warning',
				__( 'Auto trends skipped: set Site niche in Settings → Content and ensure Settings → General has a Site Title.', 'ai-blog-automator' )
			);
			return;
		}

		$per_day = max( 1, (int) get_option( 'aiba_posts_per_day', 1 ) );
		$topics  = AIBA_Core::trend_fetcher()->get_trending_topics( $niche, $per_day * 3 );
		if ( is_wp_error( $topics ) ) {
			AIBA_Core::log( 0, 'trends', 'error', $topics->get_error_message() );
			return;
		}

		$titles = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'future', 'draft', 'pending' ),
				'posts_per_page' => 200,
				'fields'         => 'post_title',
			)
		);
		$existing = array_map(
			static function ( $t ) {
				return strtolower( $t->post_title );
			},
			$titles
		);

		global $wpdb;
		$cat_ids = AIBA_Core::get_default_category_ids();
		$primary = ! empty( $cat_ids ) ? (int) $cat_ids[0] : (int) get_option( 'aiba_category_id', 0 );
		$added   = 0;

		foreach ( $topics as $t ) {
			if ( $added >= $per_day ) {
				break;
			}
			$title_lower = strtolower( $t['topic'] );
			if ( in_array( $title_lower, $existing, true ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$wpdb->prefix . 'aiba_queue',
				array(
					'topic'         => $t['topic'],
					'keyword'       => $t['primary_keyword'],
					'category_id'   => $primary,
					'category_ids'  => AIBA_Core::encode_queue_category_ids( $cat_ids ),
					'scheduled_at'  => null,
					'status'        => 'pending',
					'post_id'       => 0,
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s' )
			);
			if ( $wpdb->insert_id ) {
				++$added;
				$existing[] = $title_lower;
			}
		}

		AIBA_Core::log( 0, 'trends', 'success', 'Enqueued ' . $added . ' trend topics.' );
	}

	/**
	 * Prune logs older than retention setting.
	 */
	public static function prune_logs(): void {
		global $wpdb;
		$days   = max( 1, (int) get_option( 'aiba_log_retention', 30 ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}aiba_logs WHERE created_at < %s",
				$cutoff
			)
		);
	}
}

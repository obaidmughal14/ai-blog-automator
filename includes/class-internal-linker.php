<?php
/**
 * Internal link injection via LLM suggestions.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Internal linker.
 */
class AIBA_Internal_Linker {

	private AIBA_LLM_Client $llm;

	public function __construct( AIBA_LLM_Client $llm ) {
		$this->llm = $llm;
	}

	/**
	 * Machine token pattern (optional anchor after colon). Case-insensitive INTERNAL_LINK_PLACEHOLDER.
	 */
	private static function placeholder_regex(): string {
		return '/\[\s*INTERNAL_LINK_PLACEHOLDER\s*(?::\s*([^\]]*))?\s*\]/iu';
	}

	/**
	 * Replace internal link placeholders with real links. Never leaves a placeholder visible.
	 *
	 * @param string $content Current HTML.
	 * @param int    $current_post_id Post being built.
	 * @param string $primary_keyword Focus keyword.
	 * @param string $topic Article topic for context.
	 */
	public function inject_internal_links( string $content, int $current_post_id, string $primary_keyword, string $topic = '' ): string {
		$max = max( 1, (int) get_option( 'aiba_max_internal_links', 5 ) );
		$max = AIBA_Premium::enhance_max_internal_links( $max );

		$existing_posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => 80,
				'post__not_in'   => array( $current_post_id ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$queue = $this->fetch_internal_link_queue( $existing_posts, $primary_keyword, $topic, $max );

		$used_ids = array();
		$content  = $this->replace_all_internal_placeholders( $content, $queue, $existing_posts, $used_ids );

		if ( empty( $existing_posts ) ) {
			if ( ! empty( $used_ids ) ) {
				update_post_meta( $current_post_id, '_aiba_internal_links', array_map( 'intval', array_unique( $used_ids ) ) );
			}
			return $content;
		}

		$linked = array_values( array_unique( array_merge( $used_ids, $this->linked_ids_from_content( $content, $existing_posts ) ) ) );

		foreach ( $queue as $row ) {
			if ( count( $used_ids ) >= $max ) {
				break;
			}
			$pid    = (int) ( $row['post_id'] ?? 0 );
			$anchor = sanitize_text_field( (string) ( $row['anchor_text'] ?? '' ) );
			if ( ! $pid || $anchor === '' || ! get_post( $pid ) || in_array( $pid, $used_ids, true ) ) {
				continue;
			}
			$new = $this->wrap_first_occurrence( $content, $anchor, $pid );
			if ( $new !== $content ) {
				$content    = $new;
				$used_ids[] = $pid;
				$linked[]   = $pid;
			}
		}

		if ( ! empty( $linked ) ) {
			update_post_meta( $current_post_id, '_aiba_internal_links', array_map( 'intval', array_unique( $linked ) ) );
		}

		return $content;
	}

	/**
	 * One LLM call: suggested targets for internal links.
	 *
	 * @param array<int, WP_Post> $existing_posts Pool.
	 * @return array<int, array<string, mixed>>
	 */
	private function fetch_internal_link_queue( array $existing_posts, string $primary_keyword, string $topic, int $max ): array {
		if ( empty( $existing_posts ) ) {
			return array();
		}
		$this->llm->reset_throttle_counter();
		$lines = array();
		foreach ( $existing_posts as $p ) {
			$focus = '';
			if ( defined( 'WPSEO_VERSION' ) ) {
				$focus = (string) get_post_meta( $p->ID, '_yoast_wpseo_focuskw', true );
			} elseif ( defined( 'RANK_MATH_VERSION' ) ) {
				$focus = (string) get_post_meta( $p->ID, 'rank_math_focus_keyword', true );
			}
			$lines[] = sprintf(
				'Post ID: %d | Type: %s | Title: %s | Keywords: %s',
				$p->ID,
				$p->post_type,
				$p->post_title,
				$focus
			);
		}
		$posts_list = implode( "\n", $lines );
		$ctx        = $topic !== '' ? $topic : $primary_keyword;
		$prompt     = sprintf(
			'Given this new article about "%1$s" with keyword "%2$s",
which of these existing posts or pages are most relevant for internal linking?
Return ONLY JSON array of top %3$d:
[{"post_id": 123, "anchor_text": "natural anchor text", "reason": "why relevant"}]

Existing posts and pages:
%4$s',
			$ctx,
			$primary_keyword,
			$max,
			$posts_list
		);
		$suggestions = $this->llm->generate_text( $prompt );
		if ( is_wp_error( $suggestions ) ) {
			return array();
		}
		return $this->parse_suggestions( (string) $suggestions );
	}

	/**
	 * Replace every placeholder token with a real <a>. Queue is consumed; $used_ids collects target post IDs.
	 *
	 * @param array<int, array<string, mixed>> $queue Suggestions (by ref).
	 * @param array<int, WP_Post>              $existing_posts Pool.
	 * @param array<int, int>                  $used_ids Target post IDs used (by ref).
	 */
	private function replace_all_internal_placeholders( string $content, array &$queue, array $existing_posts, array &$used_ids ): string {
		$rx = self::placeholder_regex();

		while ( preg_match( $rx, $content, $match, PREG_OFFSET_CAPTURE ) ) {
			$offset = (int) $match[0][1];
			$len    = strlen( $match[0][0] );
			$anchor_raw = '';
			if ( isset( $match[1] ) && is_array( $match[1] ) && ( ! isset( $match[1][1] ) || (int) $match[1][1] >= 0 ) ) {
				$anchor_raw = sanitize_text_field( trim( (string) ( $match[1][0] ?? '' ) ) );
			}

			$replacement = $this->build_placeholder_link( $anchor_raw, $queue, $existing_posts, $used_ids );

			$content = substr_replace( $content, $replacement, $offset, $len );
		}

		$content = (string) preg_replace( '/\[\s*INTERNAL_LINK_PLACEHOLDER[^\]]*\]/iu', '', $content );
		$content = (string) preg_replace( '/\bINTERNAL_LINK_PLACEHOLDER\b[^\s\]\[]*/iu', '', $content );

		return $content;
	}

	/**
	 * @param array<int, array<string, mixed>> $queue LLM rows (consumed).
	 * @param array<int, WP_Post>              $existing_posts Pool.
	 * @param array<int, int>                  $used_ids Post IDs already chosen (updated).
	 */
	private function build_placeholder_link( string $anchor_raw, array &$queue, array $existing_posts, array &$used_ids ): string {
		$replacement = '';

		while ( '' === $replacement && ! empty( $queue ) ) {
			$row = array_shift( $queue );
			$pid = (int) ( $row['post_id'] ?? 0 );
			if ( ! $pid || ! get_post( $pid ) ) {
				continue;
			}
			$link_text = sanitize_text_field( (string) ( $row['anchor_text'] ?? '' ) );
			if ( $link_text === '' ) {
				$link_text = $anchor_raw !== '' ? $anchor_raw : wp_trim_words( get_the_title( $pid ), 8, '…' );
			}
			$replacement = $this->link_html( $pid, $link_text );
			$used_ids[]  = $pid;
		}

		if ( '' === $replacement && $anchor_raw !== '' && ! empty( $existing_posts ) ) {
			$pid = $this->find_post_for_anchor( $anchor_raw, $existing_posts, $used_ids );
			if ( $pid ) {
				$replacement = $this->link_html( $pid, $anchor_raw );
				$used_ids[]  = $pid;
			}
		}

		if ( '' === $replacement && ! empty( $existing_posts ) ) {
			$pid = $this->pick_round_robin_post( $existing_posts, $used_ids );
			if ( $pid ) {
				$label       = $anchor_raw !== '' ? $anchor_raw : wp_trim_words( get_the_title( $pid ), 8, '…' );
				$replacement = $this->link_html( $pid, $label );
				$used_ids[]  = $pid;
			}
		}

		if ( '' === $replacement ) {
			$replacement = $this->fallback_site_link( $anchor_raw );
		}

		return $replacement;
	}

	private function link_html( int $post_id, string $text ): string {
		$text = $text !== '' ? $text : wp_trim_words( get_the_title( $post_id ), 8, '…' );
		return sprintf(
			'<a href="%s" title="%s">%s</a>',
			esc_url( get_permalink( $post_id ) ),
			esc_attr( get_the_title( $post_id ) ),
			esc_html( $text )
		);
	}

	/**
	 * When there are no suitable posts, still return a useful internal URL.
	 */
	private function fallback_site_link( string $anchor_raw ): string {
		$posts_page = (int) get_option( 'page_for_posts' );
		if ( $posts_page > 0 ) {
			$url = get_permalink( $posts_page );
		} else {
			$url = get_post_type_archive_link( 'post' );
		}
		if ( ! is_string( $url ) || $url === '' ) {
			$url = home_url( '/' );
		}
		$label = $anchor_raw !== '' ? $anchor_raw : __( 'More articles', 'ai-blog-automator' );
		return sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	/**
	 * @param array<int, WP_Post> $posts Pool.
	 * @param array<int, int>     $used_ids Prefer unused; then allow reuse by round-robin.
	 */
	private function pick_round_robin_post( array $posts, array $used_ids ): int {
		if ( empty( $posts ) ) {
			return 0;
		}
		foreach ( $posts as $p ) {
			if ( ! in_array( (int) $p->ID, $used_ids, true ) ) {
				return (int) $p->ID;
			}
		}
		$idx = count( $used_ids ) % count( $posts );
		return (int) $posts[ $idx ]->ID;
	}

	/**
	 * Collect post IDs already linked in HTML (rough scan for internal permalinks).
	 *
	 * @param array<int, WP_Post> $existing_posts Pool.
	 * @return array<int, int>
	 */
	private function linked_ids_from_content( string $html, array $existing_posts ): array {
		$ids = array();
		foreach ( $existing_posts as $p ) {
			$link = get_permalink( $p->ID );
			if ( $link && str_contains( $html, $link ) ) {
				$ids[] = (int) $p->ID;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function parse_suggestions( string $raw ): array {
		$raw = trim( $raw );
		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)```/i', $raw, $m ) ) {
			$raw = trim( $m[1] );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$out = array();
		foreach ( $data as $row ) {
			if ( ! is_array( $row ) || empty( $row['post_id'] ) ) {
				continue;
			}
			$out[] = array(
				'post_id'     => (int) $row['post_id'],
				'anchor_text' => sanitize_text_field( (string) ( $row['anchor_text'] ?? '' ) ),
			);
		}
		return $out;
	}

	/**
	 * @param array<int, WP_Post> $posts Pool.
	 * @param array<int, int>     $used_ids Used IDs.
	 */
	private function find_post_for_anchor( string $anchor, array $posts, array $used_ids ): int {
		foreach ( $posts as $p ) {
			if ( in_array( $p->ID, $used_ids, true ) ) {
				continue;
			}
			$title_plain = wp_strip_all_tags( $p->post_title );
			if ( stripos( $title_plain, $anchor ) !== false || stripos( $anchor, $title_plain ) !== false ) {
				return (int) $p->ID;
			}
		}
		return 0;
	}

	private function wrap_first_occurrence( string $html, string $anchor, int $post_id ): string {
		if ( stripos( wp_strip_all_tags( $html ), $anchor ) === false ) {
			return $html;
		}
		$escaped = preg_quote( $anchor, '/' );
		$pattern = '/(?![^<]*>)(' . $escaped . ')/iu';
		$title   = get_the_title( $post_id );
		$repl    = sprintf(
			'<a href="%s" title="%s">$1</a>',
			esc_url( get_permalink( $post_id ) ),
			esc_attr( $title )
		);
		return (string) preg_replace( $pattern, $repl, $html, 1 );
	}
}

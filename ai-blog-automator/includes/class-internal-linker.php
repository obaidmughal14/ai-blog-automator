<?php
/**
 * Internal link injection via Gemini suggestions.
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
	 * Replace internal link placeholders and optionally wrap natural mentions.
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
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'post__not_in'   => array( $current_post_id ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		if ( empty( $existing_posts ) ) {
			return (string) preg_replace( '/\[INTERNAL_LINK_PLACEHOLDER:\s*[^\]]+\]/', '', $content );
		}

		$lines = array();
		foreach ( $existing_posts as $p ) {
			$focus = '';
			if ( defined( 'WPSEO_VERSION' ) ) {
				$focus = (string) get_post_meta( $p->ID, '_yoast_wpseo_focuskw', true );
			} elseif ( defined( 'RANK_MATH_VERSION' ) ) {
				$focus = (string) get_post_meta( $p->ID, 'rank_math_focus_keyword', true );
			}
			$lines[] = sprintf( 'Post ID: %d | Title: %s | Keywords: %s', $p->ID, $p->post_title, $focus );
		}
		$posts_list = implode( "\n", $lines );

		$this->llm->reset_throttle_counter();
		$ctx = $topic !== '' ? $topic : $primary_keyword;
		$prompt = sprintf(
			'Given this new article about "%1$s" with keyword "%2$s",
which of these existing posts are most relevant for internal linking?
Return ONLY JSON array of top %3$d:
[{"post_id": 123, "anchor_text": "natural anchor text", "reason": "why relevant"}]

Existing posts:
%4$s',
			$ctx,
			$primary_keyword,
			$max,
			$posts_list
		);

		$suggestions = $this->llm->generate_text( $prompt );
		$queue       = array();
		if ( ! is_wp_error( $suggestions ) ) {
			$queue = $this->parse_suggestions( (string) $suggestions );
		}

		$used_ids = array();
		$linked   = array();

		if ( preg_match_all( '/\[INTERNAL_LINK_PLACEHOLDER:\s*([^\]]+)\]/', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$anchor_raw = sanitize_text_field( trim( $m[1] ) );
				$replacement = '';

				if ( count( $used_ids ) < $max && ! empty( $queue ) ) {
					$row = array_shift( $queue );
					$pid = (int) ( $row['post_id'] ?? 0 );
					if ( $pid && get_post( $pid ) && ! in_array( $pid, $used_ids, true ) ) {
						$link_text = $row['anchor_text'] !== '' ? $row['anchor_text'] : $anchor_raw;
						$replacement = sprintf(
							'<a href="%s" title="%s">%s</a>',
							esc_url( get_permalink( $pid ) ),
							esc_attr( get_the_title( $pid ) ),
							esc_html( $link_text )
						);
						$used_ids[] = $pid;
						$linked[]   = $pid;
					}
				}

				if ( '' === $replacement && $anchor_raw !== '' && count( $used_ids ) < $max ) {
					$pid = $this->find_post_for_anchor( $anchor_raw, $existing_posts, $used_ids );
					if ( $pid ) {
						$replacement = sprintf(
							'<a href="%s" title="%s">%s</a>',
							esc_url( get_permalink( $pid ) ),
							esc_attr( get_the_title( $pid ) ),
							esc_html( $anchor_raw )
						);
						$used_ids[] = $pid;
						$linked[]   = $pid;
					}
				}

				if ( '' === $replacement ) {
					$content = str_replace( $m[0], esc_html( $anchor_raw ), $content );
				} else {
					$content = str_replace( $m[0], $replacement, $content );
				}
			}
		}

		// Use remaining Gemini rows (up to max links total).
		foreach ( $queue as $row ) {
			if ( count( $used_ids ) >= $max ) {
				break;
			}
			$pid = (int) ( $row['post_id'] ?? 0 );
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

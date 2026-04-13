<?php
/**
 * Trending topics via Gemini + Search.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Trend fetcher.
 */
class AIBA_Trend_Fetcher {

	private AIBA_LLM_Client $llm;

	public function __construct( AIBA_LLM_Client $llm ) {
		$this->llm = $llm;
	}

	/**
	 * Slugify niche for transient key.
	 */
	private function niche_slug( string $niche ): string {
		return substr( preg_replace( '/[^a-z0-9]+/i', '_', strtolower( $niche ) ), 0, 80 );
	}

	/**
	 * Fetch trending topics for niche.
	 *
	 * @param string $niche Niche label.
	 * @param int    $count Number of topics.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public function get_trending_topics( string $niche, int $count = 10 ) {
		$niche = sanitize_text_field( $niche );
		if ( '' === $niche ) {
			return new WP_Error( 'aiba_empty_niche', __( 'Site niche is empty.', 'ai-blog-automator' ) );
		}

		$slug  = $this->niche_slug( $niche );
		$cache = get_transient( 'aiba_trends_' . $slug );
		if ( false !== $cache && is_array( $cache ) ) {
			return $cache;
		}

		$date = gmdate( 'Y-m-d' );
		$this->llm->reset_throttle_counter();
		$prompt = sprintf(
			'You are a content strategist. Using current Google Trends and latest news (today\'s date: %1$s), identify the top %2$d trending blog topics in the niche: "%3$s".

Return ONLY a valid JSON array. No markdown, no explanation. Format:
[
  {
    "topic": "Full topic title",
    "primary_keyword": "main SEO keyword",
    "secondary_keywords": ["kw1", "kw2", "kw3"],
    "search_intent": "informational|commercial|navigational",
    "estimated_difficulty": "low|medium|high",
    "trending_reason": "Brief reason why trending now"
  }
]',
			$date,
			$count,
			$niche
		);

		$raw = $this->llm->generate_with_search( $prompt );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$topics = $this->parse_topics_json( $raw );
		if ( is_wp_error( $topics ) ) {
			$raw2 = $this->llm->generate_with_search( $prompt . "\n\nYour previous output was invalid JSON. Fix it." );
			if ( is_wp_error( $raw2 ) ) {
				return $raw2;
			}
			$topics = $this->parse_topics_json( $raw2 );
		}

		if ( is_wp_error( $topics ) ) {
			return $topics;
		}

		set_transient( 'aiba_trends_' . $slug, $topics, 6 * HOUR_IN_SECONDS );
		return $topics;
	}

	/**
	 * Parse JSON array from model output (strip markdown fences if any).
	 *
	 * @param string $raw Raw text.
	 * @return array|WP_Error
	 */
	private function parse_topics_json( string $raw ) {
		$raw = trim( $raw );
		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)```/i', $raw, $m ) ) {
			$raw = trim( $m[1] );
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'aiba_invalid_json', __( 'Invalid JSON from trends response.', 'ai-blog-automator' ) );
		}
		$out = array();
		foreach ( $decoded as $row ) {
			if ( ! is_array( $row ) || empty( $row['topic'] ) ) {
				continue;
			}
			$out[] = array(
				'topic'                => sanitize_text_field( (string) $row['topic'] ),
				'primary_keyword'      => sanitize_text_field( (string) ( $row['primary_keyword'] ?? '' ) ),
				'secondary_keywords'   => isset( $row['secondary_keywords'] ) && is_array( $row['secondary_keywords'] ) ? array_map( 'sanitize_text_field', $row['secondary_keywords'] ) : array(),
				'search_intent'        => sanitize_text_field( (string) ( $row['search_intent'] ?? 'informational' ) ),
				'estimated_difficulty' => sanitize_text_field( (string) ( $row['estimated_difficulty'] ?? 'medium' ) ),
				'trending_reason'      => sanitize_textarea_field( (string) ( $row['trending_reason'] ?? '' ) ),
			);
		}
		if ( empty( $out ) ) {
			return new WP_Error( 'aiba_no_topics', __( 'No valid topics parsed.', 'ai-blog-automator' ) );
		}
		return $out;
	}
}

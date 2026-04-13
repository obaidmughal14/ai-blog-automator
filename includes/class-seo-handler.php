<?php
/**
 * SEO meta, schema, scoring.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * SEO handler.
 */
class AIBA_SEO_Handler {

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'wp_head', array( __CLASS__, 'output_head_meta' ), 2 );
		add_action( 'wp_head', array( __CLASS__, 'output_schema' ), 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_faq_styles' ) );
	}

	/**
	 * Optional FAQ block styling on the front end.
	 */
	public static function enqueue_faq_styles(): void {
		if ( ! is_singular() ) {
			return;
		}
		if ( '1' !== (string) get_option( 'aiba_faq_css', '1' ) ) {
			return;
		}
		$post = get_post();
		if ( ! $post || ! str_contains( (string) $post->post_content, 'aiba-faq' ) ) {
			return;
		}
		wp_register_style( 'aiba-faq', false, array(), AIBA_VERSION );
		wp_enqueue_style( 'aiba-faq' );
		$css = '.aiba-faq{margin:2em 0}.aiba-faq-item{border:1px solid #e5e5e5;border-radius:8px;padding:1em;margin-bottom:1em}.aiba-faq-question{margin:0 0 .5em;font-size:1.1em}.aiba-faq-answer p{margin:0}';
		wp_add_inline_style( 'aiba-faq', $css );
	}

	/**
	 * Detect active SEO integration mode.
	 */
	public static function detect_seo_plugin(): string {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return 'rankmath';
		}
		if ( defined( 'AIOSEO_VERSION' ) || defined( 'AIOSEO_PLUGIN_DIR' ) ) {
			return 'aioseo';
		}
		return 'native';
	}

	/**
	 * Apply SEO meta to post.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $seo_data Data keys: primary_keyword, secondary_keywords (string[]), meta_description, seo_title, content (optional).
	 * @param bool                 $force Overwrite existing plugin meta.
	 */
	public function apply_seo( int $post_id, array $seo_data, bool $force = false ): void {
		$primary = sanitize_text_field( (string) ( $seo_data['primary_keyword'] ?? '' ) );
		$desc    = sanitize_textarea_field( (string) ( $seo_data['meta_description'] ?? '' ) );
		$title   = sanitize_text_field( (string) ( $seo_data['seo_title'] ?? '' ) );

		$mode = get_option( 'aiba_seo_plugin', 'auto' );
		if ( 'auto' === $mode ) {
			$mode = self::detect_seo_plugin();
		}

		if ( 'yoast' === $mode ) {
			if ( $force || '' === (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_focuskw', $primary );
			}
			if ( $force || '' === (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', $desc );
			}
			if ( $force || '' === (string) get_post_meta( $post_id, '_yoast_wpseo_title', true ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_title', $title );
			}
		} elseif ( 'rankmath' === $mode ) {
			if ( $force || '' === (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true ) ) {
				update_post_meta( $post_id, 'rank_math_focus_keyword', $primary );
			}
			if ( $force || '' === (string) get_post_meta( $post_id, 'rank_math_description', true ) ) {
				update_post_meta( $post_id, 'rank_math_description', $desc );
			}
			if ( $force || '' === (string) get_post_meta( $post_id, 'rank_math_title', true ) ) {
				update_post_meta( $post_id, 'rank_math_title', $title );
			}
		} elseif ( 'aioseo' === $mode ) {
			if ( $force || '' === (string) get_post_meta( $post_id, '_aioseo_title', true ) ) {
				update_post_meta( $post_id, '_aioseo_title', $title );
			}
			if ( $force || '' === (string) get_post_meta( $post_id, '_aioseo_description', true ) ) {
				update_post_meta( $post_id, '_aioseo_description', $desc );
			}
			$secondary = self::sanitize_secondary_keywords_list( $seo_data, $primary );
			$kw_line   = $primary;
			if ( ! empty( $secondary ) ) {
				$kw_line = implode( ', ', array_unique( array_merge( array( $primary ), $secondary ) ) );
			}
			if ( $force || '' === (string) get_post_meta( $post_id, '_aioseo_keywords', true ) ) {
				update_post_meta( $post_id, '_aioseo_keywords', $kw_line );
			}
		} else {
			update_post_meta( $post_id, '_aiba_seo_title', $title );
			update_post_meta( $post_id, '_aiba_meta_description', $desc );
			update_post_meta( $post_id, '_aiba_focus_keyword', $primary );
		}

		$this->apply_secondary_seo_keywords( $post_id, $mode, $primary, $seo_data, $force );

		// Schema is injected once in the publisher after content, images, and thumbnail are final.
	}

	/**
	 * @param array<string, mixed> $seo_data Raw SEO payload.
	 * @return array<int, string>
	 */
	private static function sanitize_secondary_keywords_list( array $seo_data, string $primary ): array {
		$raw = $seo_data['secondary_keywords'] ?? array();
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$primary_lower = strtolower( $primary );
		$out           = array();
		foreach ( $raw as $item ) {
			$s = sanitize_text_field( (string) $item );
			if ( '' === $s || strtolower( $s ) === $primary_lower ) {
				continue;
			}
			$out[] = $s;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Yoast synonyms, Rank Math additional keywords, native secondary list.
	 *
	 * @param array<string, mixed> $seo_data Full payload including secondary_keywords.
	 */
	private function apply_secondary_seo_keywords( int $post_id, string $mode, string $primary, array $seo_data, bool $force ): void {
		$secondary = self::sanitize_secondary_keywords_list( $seo_data, $primary );
		if ( empty( $secondary ) ) {
			return;
		}
		$syn_line = implode( ', ', $secondary );

		if ( 'yoast' === $mode ) {
			if ( $force || '' === (string) get_post_meta( $post_id, '_yoast_wpseo_keywordsynonyms', true ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_keywordsynonyms', wp_json_encode( array( $syn_line ) ) );
			}
			if ( defined( 'WPSEO_PREMIUM_VERSION' ) ) {
				$rows = array();
				foreach ( $secondary as $kw ) {
					$rows[] = array(
						'keyword' => $kw,
						'score'   => 0,
					);
				}
				if ( $force || '' === (string) get_post_meta( $post_id, '_yoast_wpseo_focuskeywords', true ) ) {
					update_post_meta( $post_id, '_yoast_wpseo_focuskeywords', wp_json_encode( $rows ) );
				}
			}
		} elseif ( 'rankmath' === $mode ) {
			if ( $force || '' === (string) get_post_meta( $post_id, 'rank_math_additional_keywords', true ) ) {
				update_post_meta( $post_id, 'rank_math_additional_keywords', $syn_line );
			}
		} elseif ( 'native' === $mode ) {
			if ( $force || '' === (string) get_post_meta( $post_id, '_aiba_secondary_keywords', true ) ) {
				update_post_meta( $post_id, '_aiba_secondary_keywords', $syn_line );
			}
		}
	}

	/**
	 * Build and store JSON-LD.
	 *
	 * @param array<string, mixed> $seo_data SEO + article context.
	 */
	public function inject_schema( int $post_id, array $seo_data ): void {
		$add_article = '1' === (string) get_option( 'aiba_add_article_schema', '1' );
		$add_faq     = '1' === (string) get_option( 'aiba_add_faq_schema', '1' );

		$schemas = array();

		if ( $add_article ) {
			$schemas[] = $this->build_article_schema( $post_id, $seo_data );
		}

		if ( $add_faq && ! empty( $seo_data['content'] ) ) {
			$faq = $this->build_faq_schema_from_content( (string) $seo_data['content'] );
			if ( ! empty( $faq ) ) {
				$schemas[] = $faq;
			}
		}

		if ( empty( $schemas ) ) {
			delete_post_meta( $post_id, '_aiba_schema_json' );
			return;
		}

		update_post_meta( $post_id, '_aiba_schema_json', wp_json_encode( $schemas ) );
	}

	/**
	 * @param array<string, mixed> $seo_data Data.
	 * @return array<string, mixed>
	 */
	private function build_article_schema( int $post_id, array $seo_data ): array {
		$post = get_post( $post_id );
		$url  = get_permalink( $post_id );
		$img  = get_the_post_thumbnail_url( $post_id, 'full' ) ?: '';

		$author = get_the_author_meta( 'display_name', $post ? (int) $post->post_author : 0 );

		return array(
			'@context'         => 'https://schema.org',
			'@type'            => 'Article',
			'headline'         => sanitize_text_field( (string) ( $seo_data['seo_title'] ?? get_the_title( $post_id ) ) ),
			'description'      => sanitize_textarea_field( (string) ( $seo_data['meta_description'] ?? '' ) ),
			'author'           => array(
				'@type' => 'Person',
				'name'  => $author,
			),
			'datePublished'    => $post ? get_post_time( 'c', true, $post ) : gmdate( 'c' ),
			'dateModified'     => $post ? get_post_modified_time( 'c', true, $post ) : gmdate( 'c' ),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => $url,
			),
			'image'            => $img ? array( $img ) : array(),
		);
	}

	/**
	 * Parse FAQ block from HTML content.
	 *
	 * @return array<string, mixed>
	 */
	private function build_faq_schema_from_content( string $content ): array {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return array();
		}
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $content );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );
		$items = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' aiba-faq-item ')]" );
		if ( ! $items || 0 === $items->length ) {
			return array();
		}

		$main = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => array(),
		);

		foreach ( $items as $node ) {
			if ( ! $node instanceof DOMElement ) {
				continue;
			}
			$q = $xpath->query( ".//*[contains(concat(' ', normalize-space(@class), ' '), ' aiba-faq-question ')]", $node )->item( 0 );
			$a = $xpath->query( ".//*[contains(concat(' ', normalize-space(@class), ' '), ' aiba-faq-answer ')]", $node )->item( 0 );
			if ( ! $q || ! $a ) {
				continue;
			}
			$question = trim( $q->textContent );
			$answer   = '';
			foreach ( $a->childNodes as $child ) {
				$answer .= $dom->saveHTML( $child );
			}
			$answer = trim( wp_strip_all_tags( $answer ) );
			if ( '' === $question || '' === $answer ) {
				continue;
			}
			$main['mainEntity'][] = array(
				'@type'          => 'Question',
				'name'           => $question,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer,
				),
			);
		}

		if ( empty( $main['mainEntity'] ) ) {
			return array();
		}

		return $main;
	}

	/**
	 * Output native meta + canonical + OG when enabled.
	 */
	public static function output_head_meta(): void {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}
		if ( post_password_required( $post_id ) ) {
			return;
		}

		$disabled = get_option( 'aiba_disabled_types', array() );
		if ( is_array( $disabled ) && in_array( get_post_type( $post_id ), $disabled, true ) ) {
			return;
		}

		$mode = get_option( 'aiba_seo_plugin', 'auto' );
		if ( 'auto' === $mode ) {
			$mode = self::detect_seo_plugin();
		}

		if ( 'native' !== $mode ) {
			// Third-party SEO plugins handle primary tags.
			if ( '1' === (string) get_option( 'aiba_canonical', '1' ) ) {
				$link = get_permalink( $post_id );
				if ( $link ) {
					echo '<link rel="canonical" href="' . esc_url( $link ) . "\" />\n";
				}
			}
			if ( '1' === (string) get_option( 'aiba_og_tags', '1' ) ) {
				self::output_og_tags( $post_id );
			}
			return;
		}

		$title = (string) get_post_meta( $post_id, '_aiba_seo_title', true );
		$desc  = (string) get_post_meta( $post_id, '_aiba_meta_description', true );
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . "\" />\n";
		}
		if ( $title && '1' === (string) get_option( 'aiba_og_tags', '1' ) ) {
			echo '<meta property="og:title" content="' . esc_attr( $title ) . "\" />\n";
		}
		if ( $desc && '1' === (string) get_option( 'aiba_og_tags', '1' ) ) {
			echo '<meta property="og:description" content="' . esc_attr( $desc ) . "\" />\n";
		}
		if ( '1' === (string) get_option( 'aiba_canonical', '1' ) ) {
			$link = get_permalink( $post_id );
			if ( $link ) {
				echo '<link rel="canonical" href="' . esc_url( $link ) . "\" />\n";
			}
		}
		if ( '1' === (string) get_option( 'aiba_og_tags', '1' ) ) {
			self::output_og_tags( $post_id );
		}
	}

	private static function output_og_tags( int $post_id ): void {
		$url = get_permalink( $post_id );
		if ( $url ) {
			echo '<meta property="og:url" content="' . esc_url( $url ) . "\" />\n";
		}
		echo '<meta property="og:type" content="article" />' . "\n";
		$thumb = get_the_post_thumbnail_url( $post_id, 'large' );
		if ( $thumb ) {
			echo '<meta property="og:image" content="' . esc_url( $thumb ) . "\" />\n";
		}
	}

	/**
	 * Output stored schema JSON-LD.
	 */
	public static function output_schema(): void {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}
		$raw = (string) get_post_meta( $post_id, '_aiba_schema_json', true );
		if ( '' === $raw ) {
			return;
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return;
		}
		// Stored as list of graphs or single.
		$blocks = isset( $data[0] ) ? $data : array( $data );
		foreach ( $blocks as $schema ) {
			if ( ! is_array( $schema ) ) {
				continue;
			}
			echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
		}
	}

	/**
	 * Simple SEO score 0-100.
	 */
	public static function calculate_seo_score( string $content, string $keyword, string $title = '', string $meta_description = '' ): int {
		$keyword = strtolower( trim( $keyword ) );
		$plain   = strtolower( wp_strip_all_tags( $content ) );
		$score   = 0;

		if ( $keyword && $title && str_contains( strtolower( $title ), $keyword ) ) {
			$score += 20;
		}

		$first100 = substr( $plain, 0, 400 );
		if ( $keyword && str_contains( $first100, $keyword ) ) {
			$score += 15;
		}

		$words = str_word_count( $plain );
		if ( $words > 0 && $keyword ) {
			$occ   = substr_count( $plain, $keyword );
			$density = ( $occ / max( 1, $words ) ) * 100;
			if ( $density >= 0.5 && $density <= 1.5 ) {
				$score += 20;
			}
		}

		if ( $keyword && $meta_description && str_contains( strtolower( $meta_description ), $keyword ) ) {
			$score += 10;
		}

		if ( preg_match( '/<img[^>]+alt=["\'][^"\']+["\']/', $content ) ) {
			$score += 10;
		}

		if ( $words > 1000 ) {
			$score += 15;
		}

		if ( str_contains( $content, '<a ' ) ) {
			$score += 10;
		}

		return (int) min( 100, $score );
	}
}

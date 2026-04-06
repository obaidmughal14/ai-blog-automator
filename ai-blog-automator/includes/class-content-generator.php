<?php
/**
 * Article generation pipeline.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Content generator.
 */
class AIBA_Content_Generator {

	private AIBA_LLM_Client $llm;

	public function __construct( AIBA_LLM_Client $llm ) {
		$this->llm = $llm;
	}

	/**
	 * Generate full article from job definition.
	 *
	 * @param array<string, mixed> $job Job data.
	 * @return array<string, mixed>|WP_Error
	 */
	public function generate_article( array $job ) {
		$this->llm->reset_throttle_counter();

		$topic               = sanitize_text_field( (string) ( $job['topic'] ?? '' ) );
		$primary             = sanitize_text_field( (string) ( $job['primary_keyword'] ?? '' ) );
		$secondary           = isset( $job['secondary_keywords'] ) && is_array( $job['secondary_keywords'] ) ? array_map( 'sanitize_text_field', $job['secondary_keywords'] ) : array();
		$secondary_csv       = implode( ', ', $secondary );
		$word_count      = max( 300, (int) ( $job['word_count'] ?? (int) get_option( 'aiba_word_count', 1500 ) ) );
		$word_count      = AIBA_Premium::enhance_word_count( $word_count );
		$tone            = sanitize_text_field( (string) ( $job['tone'] ?? (string) get_option( 'aiba_tone', 'Professional' ) ) );
		$language        = sanitize_text_field( (string) ( $job['language'] ?? (string) get_option( 'aiba_language', 'English' ) ) );
		$images_per_post = max( 1, (int) get_option( 'aiba_images_per_post', 3 ) );
		$images_per_post = AIBA_Premium::enhance_images_per_post( $images_per_post );

		if ( '' === $topic || '' === $primary ) {
			return new WP_Error( 'aiba_job_invalid', __( 'Topic and primary keyword are required.', 'ai-blog-automator' ) );
		}

		$outline = $this->fetch_outline( $topic, $primary, $secondary_csv, $word_count, $tone, $images_per_post );
		if ( is_wp_error( $outline ) ) {
			return $outline;
		}

		$sections      = $outline['sections'] ?? array();
		$section_count = max( 1, count( $sections ) );
		$per_section   = (int) ceil( $word_count / $section_count );

		$image_suggestions = isset( $outline['image_suggestions'] ) && is_array( $outline['image_suggestions'] ) ? $outline['image_suggestions'] : array();
		$img_idx         = 0;

		$sections_html = '';
		foreach ( $sections as $sec ) {
			$heading      = sanitize_text_field( (string) ( $sec['heading'] ?? '' ) );
			$subheadings  = isset( $sec['subheadings'] ) && is_array( $sec['subheadings'] ) ? array_map( 'sanitize_text_field', $sec['subheadings'] ) : array();
			$notes        = sanitize_textarea_field( (string) ( $sec['notes'] ?? '' ) );
			$img_suggest  = isset( $image_suggestions[ $img_idx ] ) ? sanitize_text_field( (string) $image_suggestions[ $img_idx ] ) : $heading;
			++$img_idx;

			$section_body = $this->generate_section_html(
				$outline['title'],
				$heading,
				$primary,
				$secondary_csv,
				$subheadings,
				$notes,
				$per_section,
				$tone,
				$language,
				$img_suggest
			);
			if ( is_wp_error( $section_body ) ) {
				return $section_body;
			}
			$sections_html .= $section_body;
		}

		$faq_questions = isset( $outline['faq_questions'] ) && is_array( $outline['faq_questions'] ) ? array_map( 'sanitize_text_field', $outline['faq_questions'] ) : array();
		$faq_html      = '';
		if ( ! empty( $faq_questions ) ) {
			$faq_html = $this->generate_faq_html( $outline['title'], $primary, $faq_questions, $language );
			if ( is_wp_error( $faq_html ) ) {
				return $faq_html;
			}
		}

		$intro = $this->generate_intro( $outline['title'], $primary, $secondary_csv, $tone, $language, $sections );
		if ( is_wp_error( $intro ) ) {
			return $intro;
		}

		$conclusion = $this->generate_conclusion( $outline['title'], $primary, $tone, $language );
		if ( is_wp_error( $conclusion ) ) {
			return $conclusion;
		}

		$full_content = $intro . $sections_html . $faq_html . $conclusion;

		$tags = $this->build_tags( $primary, $secondary );

		return array(
			'title'             => sanitize_text_field( (string) ( $outline['title'] ?? $topic ) ),
			'slug'              => sanitize_title( (string) ( $outline['slug'] ?? $primary ) ),
			'content'           => $full_content,
			'meta_description'  => sanitize_textarea_field( (string) ( $outline['meta_description'] ?? '' ) ),
			'seo_title'         => sanitize_text_field( (string) ( $outline['title'] ?? $topic ) ),
			'primary_keyword'   => $primary,
			'secondary_keywords'=> $secondary,
			'image_suggestions' => array_values( array_filter( array_map( 'sanitize_text_field', $image_suggestions ) ) ),
			'word_count'        => str_word_count( wp_strip_all_tags( $full_content ) ),
			'tags'              => $tags,
		);
	}

	/**
	 * @param array<int, string> $secondary
	 * @param array<int, mixed>  $sections_for_intro
	 */
	private function generate_intro( string $title, string $primary, string $secondary_csv, string $tone, string $language, array $sections_for_intro ): string|WP_Error {
		$headings = array();
		foreach ( $sections_for_intro as $s ) {
			if ( is_array( $s ) && ! empty( $s['heading'] ) ) {
				$headings[] = sanitize_text_field( (string) $s['heading'] );
			}
		}
		$outline_hint = implode( '; ', $headings );
		$prompt       = sprintf(
			'Write an introduction (150-200 words) for an article titled "%1$s".
Primary keyword: %2$s. Secondary keywords: %3$s.
Tone: %4$s. Language: %5$s.
Hook the reader, include the primary keyword naturally, and briefly outline what the article covers: %6$s.
Output clean HTML only: <p>, <strong>, <em>. No H1.',
			$title,
			$primary,
			$secondary_csv,
			$tone,
			$language,
			$outline_hint
		);
		$out = $this->llm->generate_text( $prompt );
		return is_wp_error( $out ) ? $out : wp_kses_post( $out );
	}

	private function generate_conclusion( string $title, string $primary, string $tone, string $language ): string|WP_Error {
		$prompt = sprintf(
			'Write a conclusion (150-200 words) for an article titled "%1$s".
Primary keyword: %2$s. Tone: %3$s. Language: %4$s.
Summarize key takeaways and include a clear call-to-action.
Output clean HTML only: <p>, <strong>, <em>.',
			$title,
			$primary,
			$tone,
			$language
		);
		$out = $this->llm->generate_text( $prompt );
		return is_wp_error( $out ) ? $out : wp_kses_post( $out );
	}

	/**
	 * @param array<int, string> $faq_questions
	 */
	private function generate_faq_html( string $title, string $primary, array $faq_questions, string $language ): string|WP_Error {
		$list = wp_json_encode( $faq_questions );
		$prompt = sprintf(
			'Write an FAQ section in HTML for the article "%1$s".
Use FAQ schema-ready markup:
<div class="aiba-faq">
  <div class="aiba-faq-item">
    <h3 class="aiba-faq-question">Question?</h3>
    <div class="aiba-faq-answer"><p>Answer</p></div>
  </div>
</div>
Questions to answer (JSON array): %2$s
Language: %3$s
Keep each answer 50-100 words. Natural language. Include primary keyword "%4$s" in at least one answer.',
			$title,
			$list,
			$language,
			$primary
		);
		$out = $this->llm->generate_text( $prompt );
		return is_wp_error( $out ) ? $out : wp_kses_post( $out );
	}

	/**
	 * @param array<int, string> $subheadings
	 */
	private function generate_section_html(
		string $title,
		string $heading,
		string $primary,
		string $secondary_csv,
		array $subheadings,
		string $notes,
		int $section_word_count,
		string $tone,
		string $language,
		string $image_suggestion
	): string|WP_Error {
		$subs = wp_json_encode( $subheadings );
		$prompt = sprintf(
			'Write the section "%1$s" for an article titled "%2$s".
Primary keyword: %3$s — use naturally 1-2 times in this section.
Secondary keywords: use at least one of %4$s.
Subheadings to cover: %5$s
Section notes: %6$s
Requirements:
- Minimum %7$d words
- No filler, no keyword stuffing
- Include real, factual information
- Write in %8$s tone, language %9$s
- Output clean HTML only: use <h2>, <h3>, <p>, <ul>, <ol>, <strong>, <em>
- Do NOT include the main <h1> title
- Add a [IMAGE_PLACEHOLDER: %10$s] tag where an image would naturally fit
- Add [INTERNAL_LINK_PLACEHOLDER: relevant anchor phrase] where an internal link would help (use a short natural anchor)',
			$heading,
			$title,
			$primary,
			$secondary_csv,
			$subs,
			$notes,
			$section_word_count,
			$tone,
			$language,
			$image_suggestion
		);
		$out = $this->llm->generate_text( $prompt );
		return is_wp_error( $out ) ? $out : wp_kses_post( $out );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private function fetch_outline( string $topic, string $primary, string $secondary_csv, int $word_count, string $tone, int $image_count ) {
		$prompt = sprintf(
			'You are an expert SEO content strategist. Create a detailed article outline for:
Topic: %1$s
Primary Keyword: %2$s
Secondary Keywords: %3$s
Target Word Count: %4$d
Tone: %5$s
Suggest about %6$d in-content image descriptions in image_suggestions.

Return ONLY valid JSON:
{
  "title": "SEO-optimized H1 title (under 60 chars)",
  "slug": "url-friendly-slug",
  "meta_description": "Compelling meta description 150-160 chars containing primary keyword",
  "sections": [
    {
      "heading": "H2 heading",
      "subheadings": ["H3 subheading 1", "H3 subheading 2"],
      "notes": "What to cover in this section"
    }
  ],
  "faq_questions": ["Q1?", "Q2?", "Q3?"],
  "image_suggestions": ["description for image 1", "description for image 2"]
}',
			$topic,
			$primary,
			$secondary_csv,
			$word_count,
			$tone,
			$image_count
		);

		$raw = $this->llm->generate_text( $prompt );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		return $this->parse_outline( $raw );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private function parse_outline( string $raw ) {
		$raw = trim( $raw );
		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)```/i', $raw, $m ) ) {
			$raw = trim( $m[1] );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || empty( $data['title'] ) || empty( $data['sections'] ) ) {
			return new WP_Error( 'aiba_outline_invalid', __( 'Invalid outline JSON from Gemini.', 'ai-blog-automator' ) );
		}
		return $data;
	}

	/**
	 * @param array<int, string> $secondary
	 * @return array<int, string>
	 */
	private function build_tags( string $primary, array $secondary ): array {
		$tags = array_merge( array( $primary ), $secondary );
		$tags = array_unique( array_filter( array_map( 'sanitize_text_field', $tags ) ) );
		return array_slice( $tags, 0, 15 );
	}
}

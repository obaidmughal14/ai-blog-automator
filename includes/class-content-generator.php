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

		$topic         = sanitize_text_field( (string) ( $job['topic'] ?? '' ) );
		$primary       = sanitize_text_field( (string) ( $job['primary_keyword'] ?? '' ) );
		$secondary     = isset( $job['secondary_keywords'] ) && is_array( $job['secondary_keywords'] ) ? array_map( 'sanitize_text_field', $job['secondary_keywords'] ) : array();
		$secondary_csv = implode( ', ', $secondary );
		$word_count    = (int) ( $job['word_count'] ?? (int) get_option( 'aiba_word_count', 1500 ) );
		$word_count    = max( 300, min( 5000, $word_count ) );
		$word_count    = AIBA_Premium::enhance_word_count( $word_count );
		$tone          = sanitize_text_field( (string) ( $job['tone'] ?? (string) get_option( 'aiba_tone', 'Professional' ) ) );
		$language      = sanitize_text_field( (string) ( $job['language'] ?? (string) get_option( 'aiba_language', 'English' ) ) );
		$images_per_post = max( 1, (int) get_option( 'aiba_images_per_post', 3 ) );
		$images_per_post = AIBA_Premium::enhance_images_per_post( $images_per_post );

		$tpl_slug = isset( $job['article_template'] ) ? sanitize_key( (string) $job['article_template'] ) : '';
		if ( '' === $tpl_slug ) {
			$tpl_slug = AIBA_LLM_Templates::sanitize_article_template( (string) get_option( 'aiba_article_template', 'standard' ) );
		} else {
			$tpl_slug = AIBA_LLM_Templates::sanitize_article_template( $tpl_slug );
		}
		$format_instruction = AIBA_LLM_Templates::get_format_instruction( $tpl_slug );

		if ( '' === $topic || '' === $primary ) {
			return new WP_Error( 'aiba_job_invalid', __( 'Topic and primary keyword are required.', 'ai-blog-automator' ) );
		}

		$job_cat_ids = isset( $job['category_ids'] ) && is_array( $job['category_ids'] ) ? array_map( 'intval', $job['category_ids'] ) : array();
		$job_cat_ids = array_values( array_filter( array_unique( $job_cat_ids ) ) );

		$outline = $this->fetch_outline( $topic, $primary, $secondary_csv, $word_count, $tone, $images_per_post, $format_instruction );
		if ( is_wp_error( $outline ) ) {
			return $outline;
		}

		$sections      = $outline['sections'] ?? array();
		$section_count = max( 1, count( $sections ) );
		$per_section   = (int) ceil( $word_count / $section_count );

		$image_suggestions = isset( $outline['image_suggestions'] ) && is_array( $outline['image_suggestions'] ) ? $outline['image_suggestions'] : array();
		$img_idx           = 0;

		$sections_html = '';
		foreach ( $sections as $sec ) {
			$heading     = sanitize_text_field( (string) ( $sec['heading'] ?? '' ) );
			$subheadings = isset( $sec['subheadings'] ) && is_array( $sec['subheadings'] ) ? array_map( 'sanitize_text_field', $sec['subheadings'] ) : array();
			$notes       = sanitize_textarea_field( (string) ( $sec['notes'] ?? '' ) );
			$img_suggest = isset( $image_suggestions[ $img_idx ] ) ? sanitize_text_field( (string) $image_suggestions[ $img_idx ] ) : $heading;
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
				$img_suggest,
				$format_instruction
			);
			if ( is_wp_error( $section_body ) ) {
				return $section_body;
			}
			$sections_html .= $section_body;
		}

		$faq_raw       = isset( $outline['faq_questions'] ) && is_array( $outline['faq_questions'] ) ? $outline['faq_questions'] : array();
		$faq_questions = $this->normalize_faq_questions( $faq_raw, $topic, $primary, 6 );
		$faq_html      = $this->generate_faq_html( $outline['title'], $primary, $secondary_csv, $faq_questions, $language );
		if ( is_wp_error( $faq_html ) ) {
			return $faq_html;
		}

		$intro = $this->generate_intro( $outline['title'], $primary, $secondary_csv, $tone, $language, $sections );
		if ( is_wp_error( $intro ) ) {
			return $intro;
		}

		$closing = $this->generate_closing_paragraphs( $outline['title'], $primary, $secondary_csv, $tone, $language );
		if ( is_wp_error( $closing ) ) {
			return $closing;
		}

		$full_content = $this->polish_editorial_html( $intro . $sections_html . $closing . $faq_html );

		$tags = $this->build_tags( $primary, $secondary );
		if ( '1' === (string) get_option( 'aiba_ai_tag_expansion', '0' ) ) {
			$exp = $this->expand_tags_with_ai( (string) ( $outline['title'] ?? $topic ), $primary, $secondary, $tags );
			if ( ! is_wp_error( $exp ) ) {
				$tags = $exp;
			}
		}

		$suggested_cats = array();
		if ( '1' === (string) get_option( 'aiba_ai_suggest_categories', '0' ) ) {
			$suggested_cats = $this->suggest_wp_categories_ai( $topic, $primary, $job_cat_ids );
		}

		$raw_seo_title = sanitize_text_field( (string) ( $outline['title'] ?? $topic ) );
		$raw_meta      = sanitize_textarea_field( (string) ( $outline['meta_description'] ?? '' ) );
		$polished      = AIBA_SEO_Handler::polish_snippets( $primary, $raw_seo_title, $raw_meta );

		return array(
			'title'                    => sanitize_text_field( (string) ( $outline['title'] ?? $topic ) ),
			'slug'                     => sanitize_title( (string) ( $outline['slug'] ?? $primary ) ),
			'content'                  => $full_content,
			'meta_description'         => $polished['meta_description'],
			'seo_title'                => $polished['seo_title'],
			'primary_keyword'          => $primary,
			'secondary_keywords'       => $secondary,
			'image_suggestions'        => array_values( array_filter( array_map( 'sanitize_text_field', $image_suggestions ) ) ),
			'word_count'               => str_word_count( wp_strip_all_tags( $full_content ) ),
			'tags'                     => $tags,
			'suggested_category_ids'   => $suggested_cats,
		);
	}

	/**
	 * Shared SEO and style rules appended to model prompts.
	 */
	private function editorial_constraints_block( string $primary, string $secondary_csv ): string {
		$sec = $secondary_csv !== '' ? $secondary_csv : '(none beyond primary)';
		return sprintf(
			'Global editorial and SEO rules:
- Work the primary keyword "%1$s" naturally across intro, body, and closing paragraphs without stuffing.
- Use every secondary keyword phrase at least once in the article when secondary list is not empty: %2$s
- Do not use emojis or emoticons.
- Do not use en dashes or em dashes; use commas, periods, or the word "and" instead.
- Never start a paragraph with the phrases "In conclusion", "In summary", "Final remarks", or "Wrapping up".
- Put the primary keyword inside at least one H2 or H3 heading in each major section (natural wording).
- Add at least two outbound links to reputable third party sources (official docs, government, standards bodies, or major publishers) using real https URLs you trust. Use <a href="https://..." rel="noopener noreferrer">anchor text</a> (do follow is default; do not add nofollow on editorial sources unless required).
- Keep internal link placeholders where requested so the site can wire them to existing posts and pages.
- For Rank Math style scoring: SEO title and meta description must contain the primary keyword; first paragraph should include the primary keyword early; image alt ideas should include the primary or secondary keywords where relevant.',
			$primary,
			$sec
		);
	}

	/**
	 * Post-process assembled HTML: strip common emoji ranges, replace typographic dashes.
	 */
	private function polish_editorial_html( string $html ): string {
		$html = (string) preg_replace(
			'/[\x{1F300}-\x{1F9FF}\x{1F600}-\x{1F64F}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u',
			'',
			$html
		);
		$dash_map = array(
			"\u{2013}" => ', ',
			"\u{2014}" => ', ',
			"\u{2012}" => ', ',
		);
		$html       = strtr( $html, $dash_map );
		$html       = (string) preg_replace( '/,(\s*,)+/u', ',', $html );
		$html       = (string) preg_replace( '/(<p(?:\s[^>]*)?>)\s*In\s+conclusion,?\s+/iu', '$1', $html );
		$html       = (string) preg_replace( '/(<p(?:\s[^>]*)?>)\s*In\s+summary,?\s+/iu', '$1', $html );
		return $this->strip_banned_wrapup_headings( $html );
	}

	/**
	 * Remove stock wrap-up section titles if the model added them as headings.
	 */
	private function strip_banned_wrapup_headings( string $html ): string {
		$banned = 'conclusion|concluding|final\\s*remarks?|in\\s+summary|to\\s+sum\\s+up|wrapping\\s+up|closing\\s+thoughts|overall';
		return (string) preg_replace( '/<h[23][^>]*>\s*(?:' . $banned . ')\s*<\/h[23]>/iu', '', $html );
	}

	/**
	 * @param array<int, mixed> $from_outline Raw FAQ strings from outline JSON.
	 * @return array<int, string>
	 */
	private function normalize_faq_questions( array $from_outline, string $topic, string $primary, int $min = 6 ): array {
		$questions = array();
		foreach ( $from_outline as $q ) {
			$t = sanitize_text_field( (string) $q );
			if ( $t !== '' ) {
				$questions[] = $t;
			}
		}
		$questions = array_values( array_unique( $questions ) );
		if ( count( $questions ) >= $min ) {
			return $questions;
		}
		$stubs = array(
			sprintf( __( 'What is the most important thing to know about %s?', 'ai-blog-automator' ), $primary ),
			sprintf( __( 'Who should use this advice about %s?', 'ai-blog-automator' ), $topic ),
			sprintf( __( 'How does %s affect everyday choices?', 'ai-blog-automator' ), $primary ),
			sprintf( __( 'What are common misunderstandings about %s?', 'ai-blog-automator' ), $topic ),
			sprintf( __( 'What practical steps apply to %s?', 'ai-blog-automator' ), $primary ),
			sprintf( __( 'Where can readers learn more about %s?', 'ai-blog-automator' ), $topic ),
			sprintf( __( 'What risks or limits should someone know for %s?', 'ai-blog-automator' ), $primary ),
			sprintf( __( 'How long does it take to see results with %s?', 'ai-blog-automator' ), $primary ),
		);
		foreach ( $stubs as $stub ) {
			if ( count( $questions ) >= $min ) {
				break;
			}
			$slower = strtolower( $stub );
			$dup    = false;
			foreach ( $questions as $existing ) {
				if ( strtolower( $existing ) === $slower ) {
					$dup = true;
					break;
				}
			}
			if ( ! $dup ) {
				$questions[] = $stub;
			}
		}
		return $questions;
	}

	/**
	 * @param 'outline'|'section'|'general' $kind Prompt wrapper kind.
	 */
	private function llm_with_wrap( string $prompt, string $kind ) {
		$prompt = $this->apply_prompt_wrappers( $prompt, $kind );
		return $this->llm->generate_text( $prompt );
	}

	/**
	 * @param 'outline'|'section'|'general' $kind
	 */
	private function apply_prompt_wrappers( string $prompt, string $kind ): string {
		if ( 'outline' === $kind ) {
			$p = trim( (string) get_option( 'aiba_prompt_outline_prefix', '' ) );
			$s = trim( (string) get_option( 'aiba_prompt_outline_suffix', '' ) );
			if ( $p !== '' ) {
				$prompt = $p . "\n\n" . $prompt;
			}
			if ( $s !== '' ) {
				$prompt .= "\n\n" . $s;
			}
		} elseif ( 'section' === $kind ) {
			$p = trim( (string) get_option( 'aiba_prompt_section_prefix', '' ) );
			$s = trim( (string) get_option( 'aiba_prompt_section_suffix', '' ) );
			if ( $p !== '' ) {
				$prompt = $p . "\n\n" . $prompt;
			}
			if ( $s !== '' ) {
				$prompt .= "\n\n" . $s;
			}
		}
		$g = trim( (string) get_option( 'aiba_prompt_global_append', '' ) );
		if ( $g !== '' ) {
			$prompt .= "\n\n" . $g;
		}
		return $prompt;
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
Hook the reader, include the primary keyword in the first sentence, and briefly outline what the article covers: %6$s.
Include one outbound link to a reputable source when it fits (real https URL, rel="noopener noreferrer").
Do not begin with "In conclusion", "In summary", or similar wrap-up phrases.
Output clean HTML only: <p>, <strong>, <em>. No H1.',
			$title,
			$primary,
			$secondary_csv,
			$tone,
			$language,
			$outline_hint
		);
		$prompt .= "\n\n" . $this->editorial_constraints_block( $primary, $secondary_csv );
		$out = $this->llm_with_wrap( $prompt, 'general' );
		return is_wp_error( $out ) ? $out : wp_kses_post( $out );
	}

	private function generate_closing_paragraphs( string $title, string $primary, string $secondary_csv, string $tone, string $language ): string|WP_Error {
		$prompt = sprintf(
			'Write two or three short closing paragraphs (about 120 to 200 words total) for the article titled "%1$s".
Primary keyword: %2$s. Secondary keywords to weave in if natural: %3$s. Tone: %4$s. Language: %5$s.
Requirements:
- Output clean HTML only using <p>, <strong>, <em>. No headings of any level.
- Do not use these words or phrases anywhere: conclusion, concluding, final remarks, in summary, in conclusion, to sum up, wrapping up, closing thoughts, overall.
- Summarize one or two key ideas and end with one clear next step for the reader.',
			$title,
			$primary,
			$secondary_csv,
			$tone,
			$language
		);
		$prompt .= "\n\n" . $this->editorial_constraints_block( $primary, $secondary_csv );
		$out = $this->llm_with_wrap( $prompt, 'general' );
		return is_wp_error( $out ) ? $out : wp_kses_post( $out );
	}

	/**
	 * @param array<int, string> $faq_questions
	 */
	private function generate_faq_html( string $title, string $primary, string $secondary_csv, array $faq_questions, string $language ): string|WP_Error {
		$list   = wp_json_encode( $faq_questions );
		$prompt = sprintf(
			'Write a public FAQ block in HTML for the article "%1$s". These are real questions people type into search; answer each clearly and helpfully.
Use exactly this FAQ schema-ready structure (no extra H2 title above the block):
<div class="aiba-faq">
  <div class="aiba-faq-item">
    <h3 class="aiba-faq-question">Question?</h3>
    <div class="aiba-faq-answer"><p>Answer</p></div>
  </div>
</div>
You must answer every question in this JSON array with matching order: %2$s
Language: %3$s
Keep each answer 60 to 120 words. Natural language. Include primary keyword "%4$s" in at least one answer. Do not skip questions.',
			$title,
			$list,
			$language,
			$primary
		);
		$prompt .= "\n\n" . $this->editorial_constraints_block( $primary, $secondary_csv );
		$out = $this->llm_with_wrap( $prompt, 'general' );
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
		string $image_suggestion,
		string $format_instruction
	): string|WP_Error {
		$subs   = wp_json_encode( $subheadings );
		$prompt = sprintf(
			'Write the section "%1$s" for an article titled "%2$s".
Article format / style: %11$s
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
- Add a [IMAGE_PLACEHOLDER: %10$s] tag where an image would naturally fit; the placeholder description should relate to the section and include words from the primary keyword "%3$s" when natural (helps image alt SEO).
- Put the primary keyword "%3$s" into at least one <h3> subheading text in this section (you may add minor words for grammar).
- Where an internal link fits, output ONLY this exact token with a short anchor phrase after the colon (no other wording): [INTERNAL_LINK_PLACEHOLDER: anchor phrase]. Never write the words INTERNAL_LINK_PLACEHOLDER or "placeholder" as plain text, never use an empty token, and never write sentences like "check out our" before the token without a real anchor inside the brackets.
- Where you state a specific fact or statistic, prefer one outbound link to an authoritative primary source in that section.',
			$heading,
			$title,
			$primary,
			$secondary_csv,
			$subs,
			$notes,
			$section_word_count,
			$tone,
			$language,
			$image_suggestion,
			$format_instruction
		);
		$prompt .= "\n\n" . $this->editorial_constraints_block( $primary, $secondary_csv );
		$out = $this->llm_with_wrap( $prompt, 'section' );
		return is_wp_error( $out ) ? $out : wp_kses_post( $out );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private function fetch_outline( string $topic, string $primary, string $secondary_csv, int $word_count, string $tone, int $image_count, string $format_instruction ) {
		$prompt = sprintf(
			'You are an expert SEO content strategist. Create a detailed article outline for:
Topic: %1$s
Primary Keyword: %2$s
Secondary Keywords: %3$s
Target Word Count: %4$d
Tone: %5$s
Article format / style to respect: %7$s
Suggest about %6$d in-content image descriptions in image_suggestions.
Include at least 6 distinct, practical questions in faq_questions (phrases real readers would type into search).

Return ONLY valid JSON:
{
  "title": "SEO-optimized H1 title (under 60 chars)",
  "slug": "url-friendly-slug",
  "meta_description": "Must be 150-160 characters and START with the primary keyword phrase %2$s (then continue naturally)",
  "sections": [
    {
      "heading": "H2 heading that includes words from the primary keyword when possible",
      "subheadings": ["H3 subheading 1", "H3 subheading 2"],
      "notes": "What to cover in this section"
    }
  ],
  "faq_questions": ["Question 1 readers search?", "Question 2?", "Question 3?", "Question 4?", "Question 5?", "Question 6?"],
  "image_suggestions": ["description for image 1", "description for image 2"]
}',
			$topic,
			$primary,
			$secondary_csv,
			$word_count,
			$tone,
			$image_count,
			$format_instruction
		);
		$prompt .= "\n\n" . $this->editorial_constraints_block( $primary, $secondary_csv );
		$prompt .= "\nPlan section notes so claims can be supported with outbound links in the draft.";

		$raw = $this->llm_with_wrap( $prompt, 'outline' );
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
			return new WP_Error( 'aiba_outline_invalid', __( 'Invalid outline JSON from the model.', 'ai-blog-automator' ) );
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

	/**
	 * @param array<int, string> $secondary
	 * @param array<int, string> $base
	 * @return array<int, string>|WP_Error
	 */
	private function expand_tags_with_ai( string $title, string $primary, array $secondary, array $base ) {
		$prompt = sprintf(
			'Suggest 8–12 concise WordPress post tags (short phrases, no #) for an article titled "%1$s", primary keyword "%2$s", secondary: %3$s.
Return ONLY comma-separated tags on one line.',
			$title,
			$primary,
			implode( ', ', $secondary )
		);
		$raw = $this->llm_with_wrap( $prompt, 'general' );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		$parts  = array_map( 'trim', explode( ',', wp_strip_all_tags( (string) $raw ) ) );
		$merged = array_merge( $base, $parts );
		$merged = array_unique( array_filter( array_map( 'sanitize_text_field', $merged ) ) );
		return array_slice( $merged, 0, 20 );
	}

	/**
	 * @param array<int, int> $already_ids
	 * @return array<int, int>
	 */
	private function suggest_wp_categories_ai( string $topic, string $primary, array $already_ids ): array {
		$cats = get_categories(
			array(
				'hide_empty' => false,
				'number'     => 50,
			)
		);
		if ( empty( $cats ) ) {
			return array();
		}
		$payload = array();
		foreach ( $cats as $c ) {
			$payload[] = array(
				'id'   => (int) $c->term_id,
				'name' => $c->name,
			);
		}
		$valid = array_map(
			static function ( $c ) {
				return (int) $c->term_id;
			},
			$cats
		);

		$prompt = sprintf(
			'For WordPress blog topic "%1$s" and primary keyword "%2$s", pick 1–4 category IDs that best fit. Use ONLY ids from this JSON list. Return ONLY JSON: {"ids":[numbers]} .
Categories: %3$s
Always include these ids in your output if they are valid: %4$s',
			$topic,
			$primary,
			wp_json_encode( $payload ),
			wp_json_encode( array_values( array_map( 'intval', $already_ids ) ) )
		);

		$raw = $this->llm_with_wrap( $prompt, 'general' );
		if ( is_wp_error( $raw ) ) {
			return array();
		}
		$raw = trim( (string) $raw );
		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)```/i', $raw, $m ) ) {
			$raw = trim( $m[1] );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || ! isset( $data['ids'] ) || ! is_array( $data['ids'] ) ) {
			return array();
		}
		$pick = array_map( 'intval', $data['ids'] );
		$pick = array_values( array_intersect( $pick, $valid ) );
		$out  = array_values( array_unique( array_merge( array_map( 'intval', $already_ids ), $pick ) ) );
		return array_slice( $out, 0, 5 );
	}
}

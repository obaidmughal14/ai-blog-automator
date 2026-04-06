<?php
/**
 * Article format / prompt templates.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Built-in article templates (12+ formats).
 */
class AIBA_LLM_Templates {

	/**
	 * @return array<string, string> slug => label
	 */
	public static function get_article_formats(): array {
		return array(
			'standard'       => __( 'Standard article', 'ai-blog-automator' ),
			'how_to'         => __( 'How-To guide', 'ai-blog-automator' ),
			'listicle'       => __( 'Listicle', 'ai-blog-automator' ),
			'case_study'     => __( 'Case study', 'ai-blog-automator' ),
			'pillar'         => __( 'Pillar / ultimate guide', 'ai-blog-automator' ),
			'news'           => __( 'News & update', 'ai-blog-automator' ),
			'comparison'     => __( 'Comparison (X vs Y)', 'ai-blog-automator' ),
			'review'         => __( 'Product / service review', 'ai-blog-automator' ),
			'opinion'        => __( 'Opinion / editorial', 'ai-blog-automator' ),
			'beginners'      => __( 'Beginner’s explainer', 'ai-blog-automator' ),
			'interview'      => __( 'Interview / Q&A style', 'ai-blog-automator' ),
			'topical'        => __( 'Trend / timely take', 'ai-blog-automator' ),
			'troubleshooting'=> __( 'Troubleshooting / FAQ deep-dive', 'ai-blog-automator' ),
		);
	}

	/**
	 * Instruction block injected into outline and section prompts.
	 */
	public static function get_format_instruction( string $slug ): string {
		$slug = sanitize_key( $slug );
		$map  = array(
			'standard'        => 'Use a classic blog structure: clear sections, actionable takeaways, and a logical flow from problem to solution.',
			'how_to'          => 'Write as a step-by-step how-to: numbered steps where appropriate, prerequisites, tools needed, and a clear outcome. Use imperative verbs.',
			'listicle'        => 'Write as a listicle: engaging numbered list items in the H2/H3 structure, scannable bullets, and a punchy intro.',
			'case_study'      => 'Write as a case study: context, challenge, approach, measurable results, and lessons learned. Use a narrative arc.',
			'pillar'          => 'Write as a comprehensive pillar page: broad coverage, internal logic between sections, glossary-style clarity, and strong topical authority.',
			'news'            => 'Write in a news style: inverted pyramid (key facts first), dates and context, neutral tone, and what readers should do next.',
			'comparison'      => 'Write as a comparison: criteria matrix in prose, fair pros/cons, recommendation guidance, and clear section splits per option.',
			'review'          => 'Write as a review: summary, features, pros/cons, who it is for, and a verdict. Be specific and evidence-based.',
			'opinion'         => 'Write as an opinion piece: clear thesis, supporting arguments, counterpoints, and a memorable conclusion.',
			'beginners'       => 'Write for beginners: define jargon, short paragraphs, analogies, and avoid assumed prior knowledge.',
			'interview'       => 'Write in interview / Q&A style: natural questions as subheadings and conversational but informative answers.',
			'topical'         => 'Write a timely topical piece: why it matters now, context, implications, and forward-looking angle.',
			'troubleshooting' => 'Write as troubleshooting: symptoms, causes, ordered fixes, and prevention tips. Practical and diagnostic.',
		);
		$text = $map[ $slug ] ?? $map['standard'];
		return apply_filters( 'aiba_article_format_instruction', $text, $slug );
	}

	public static function sanitize_article_template( string $value ): string {
		$allowed = array_keys( self::get_article_formats() );
		$value   = sanitize_key( $value );
		return in_array( $value, $allowed, true ) ? $value : 'standard';
	}
}

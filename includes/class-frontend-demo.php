<?php
/**
 * Public marketing shortcode for product / demo pages.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers front-end shortcodes (e.g. for https://devigontech.com/ai-blog-automator).
 */
class AIBA_Frontend_Demo {

	public static function init(): void {
		add_shortcode( 'aiba_product_demo', array( __CLASS__, 'shortcode_product_demo' ) );
	}

	/**
	 * Marketing block: features, CTAs, shortcode hint for site owners.
	 *
	 * Attributes: title (string), show_shortcode_hint (yes|no, default yes).
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public static function shortcode_product_demo( $atts ): string {
		$atts = shortcode_atts(
			array(
				'title'                => __( 'AI Blog Automator for WordPress', 'ai-blog-automator' ),
				'show_shortcode_hint'  => 'yes',
			),
			is_array( $atts ) ? $atts : array(),
			'aiba_product_demo'
		);

		wp_enqueue_style(
			'aiba-public-demo',
			AIBA_PLUGIN_URL . 'assets/css/public-demo.css',
			array(),
			AIBA_VERSION
		);

		$product = esc_url( AIBA_Premium::product_url() );
		$title   = esc_html( sanitize_text_field( (string) $atts['title'] ) );
		$show    = strtolower( (string) $atts['show_shortcode_hint'] ) !== 'no';

		$free_rows  = AIBA_Premium::product_free_highlights();
		$premium_rows = AIBA_Premium::premium_marketing_points();

		ob_start();
		?>
		<div class="aiba-public-demo">
			<div class="aiba-public-demo__hero">
				<h2 class="aiba-public-demo__title"><?php echo $title; ?></h2>
				<p class="aiba-public-demo__lead">
					<?php esc_html_e( 'Generate long-form posts, SEO meta, stock images, internal links, FAQ schema, and optional Google Indexing from your WordPress dashboard.', 'ai-blog-automator' ); ?>
				</p>
				<div class="aiba-public-demo__actions">
					<a class="aiba-public-demo__btn aiba-public-demo__btn--primary" href="<?php echo $product; ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Get Premium / Buy', 'ai-blog-automator' ); ?>
					</a>
					<a class="aiba-public-demo__btn aiba-public-demo__btn--ghost" href="<?php echo $product; ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Learn more', 'ai-blog-automator' ); ?>
					</a>
				</div>
			</div>
			<h3 class="aiba-public-demo__subhead"><?php esc_html_e( 'Included with the plugin', 'ai-blog-automator' ); ?></h3>
			<ul class="aiba-public-demo__grid">
				<?php foreach ( $free_rows as $item ) : ?>
					<li class="aiba-public-demo__card">
						<span class="aiba-public-demo__check" aria-hidden="true">&#10003;</span>
						<span><?php echo esc_html( $item ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<h3 class="aiba-public-demo__subhead aiba-public-demo__subhead--premium"><?php esc_html_e( 'Premium unlocks higher limits', 'ai-blog-automator' ); ?></h3>
			<ul class="aiba-public-demo__grid aiba-public-demo__grid--premium">
				<?php foreach ( $premium_rows as $item ) : ?>
					<li class="aiba-public-demo__card">
						<span class="aiba-public-demo__check" aria-hidden="true">&#10003;</span>
						<span><?php echo esc_html( $item ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $show ) : ?>
				<p class="aiba-public-demo__hint">
					<?php esc_html_e( 'Site owner: add this shortcode to any page or post to show this block:', 'ai-blog-automator' ); ?>
					<code>[aiba_product_demo]</code>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

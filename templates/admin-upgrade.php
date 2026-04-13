<?php
/**
 * Upgrade / Get Premium screen.
 *
 * @package AI_Blog_Automator
 *
 * @var bool $aiba_premium Premium active.
 */

defined( 'ABSPATH' ) || exit;

$product_url = AIBA_Premium::product_url();
$settings_url = admin_url( 'admin.php?page=aiba-settings#aiba-premium-access' );

require AIBA_PLUGIN_DIR . 'templates/partials/shell-start.php';
?>

	<div class="aiba-card aiba-upgrade-intro">
		<h2 class="aiba-section-title"><?php esc_html_e( 'Get Premium', 'ai-blog-automator' ); ?></h2>
		<p class="aiba-upgrade-lead">
			<?php esc_html_e( 'The free plugin includes full generation, queue, SEO integrations, images, and schema. Premium is a paid add-on that raises safe defaults for power users who publish at higher volume.', 'ai-blog-automator' ); ?>
		</p>
		<div class="aiba-upgrade-cta-row">
			<a class="button button-primary button-hero" href="<?php echo esc_url( $product_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Buy Premium on Devigon Tech', 'ai-blog-automator' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( $settings_url ); ?>">
				<?php esc_html_e( 'Enter unlock code (Settings)', 'ai-blog-automator' ); ?>
			</a>
		</div>
		<p class="description aiba-upgrade-after-cta">
			<?php
			printf(
				wp_kses_post(
					/* translators: %s: Feedback admin screen URL */
					__( 'After purchase you receive an access code. Open <strong>Settings</strong>, paste the code under <strong>Unlock premium</strong>, and save. Questions? Use the <a href="%s">Feedback</a> screen.', 'ai-blog-automator' )
				),
				esc_url( admin_url( 'admin.php?page=aiba-feedback' ) )
			);
			?>
		</p>
	</div>

	<div class="aiba-upgrade-columns">
		<div class="aiba-card aiba-upgrade-col">
			<h3 class="aiba-card-title"><?php esc_html_e( 'Free plugin (everyone)', 'ai-blog-automator' ); ?></h3>
			<ul class="aiba-upgrade-list">
				<?php foreach ( AIBA_Premium::product_free_highlights() as $line ) : ?>
					<li><?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<div class="aiba-card aiba-upgrade-col aiba-upgrade-col--premium">
			<h3 class="aiba-card-title"><?php esc_html_e( 'Premium (after unlock)', 'ai-blog-automator' ); ?></h3>
			<ul class="aiba-upgrade-list">
				<?php foreach ( AIBA_Premium::premium_marketing_points() as $line ) : ?>
					<li><?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>

	<div class="aiba-card aiba-upgrade-envato-pack">
		<h3 class="aiba-card-title"><?php esc_html_e( 'CodeCanyon / Envato author pack', 'ai-blog-automator' ); ?></h3>
		<p>
			<?php esc_html_e( 'Inside the plugin zip, open the folder:', 'ai-blog-automator' ); ?>
			<code>packaging/envato/</code>
		</p>
		<ul class="aiba-upgrade-list">
			<li><code>README-ENVATO-AUTHOR.md</code> — <?php esc_html_e( 'start here', 'ai-blog-automator' ); ?></li>
			<li><code>ITEM-DESCRIPTION.html</code> — <?php esc_html_e( 'paste into the marketplace listing', 'ai-blog-automator' ); ?></li>
			<li><code>INSTALLATION.md</code>, <code>CREDITS-THIRD-PARTY.md</code>, <code>PRIVACY-DATA.md</code>, <code>SUPPORT-POLICY.md</code></li>
			<li><code>SCREENSHOTS-CHECKLIST.md</code>, <code>SUBMISSION-CHECKLIST.md</code>, <code>BUILD-ZIP.md</code></li>
		</ul>
		<p class="description"><?php esc_html_e( 'Also see docs/COMPLIANCE-SELF-AUDIT.md before each release.', 'ai-blog-automator' ); ?></p>
	</div>

	<div class="aiba-card aiba-upgrade-demo">
		<h3 class="aiba-card-title"><?php esc_html_e( 'Demo on your marketing site', 'ai-blog-automator' ); ?></h3>
		<p>
			<?php esc_html_e( 'On any WordPress page (for example your product URL), add this shortcode to render a ready-made feature block for visitors:', 'ai-blog-automator' ); ?>
		</p>
		<p><code class="aiba-code-block">[aiba_product_demo]</code></p>
		<p class="description">
			<?php
			printf(
				/* translators: %s: shortcode attribute example */
				esc_html__( 'Optional: title="Custom headline" show_shortcode_hint="no" hides the yellow hint line.', 'ai-blog-automator' )
			);
			?>
		</p>
	</div>

<?php
require AIBA_PLUGIN_DIR . 'templates/partials/shell-end.php';

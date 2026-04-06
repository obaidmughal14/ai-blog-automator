<?php
/**
 * Open admin page shell (hero + panel).
 *
 * @package AI_Blog_Automator
 *
 * @var bool        $aiba_premium   Premium active.
 * @var string      $aiba_page_title Page title.
 * @var string|null $aiba_page_sub Optional subtitle.
 */

defined( 'ABSPATH' ) || exit;

$aiba_premium   = ! empty( $aiba_premium );
$aiba_page_sub  = isset( $aiba_page_sub ) ? (string) $aiba_page_sub : '';
$aiba_page_title = isset( $aiba_page_title ) ? (string) $aiba_page_title : '';
?>
<div class="wrap aiba-wrap<?php echo $aiba_premium ? ' aiba-is-premium' : ''; ?>">
	<div class="aiba-hero">
		<div class="aiba-hero-inner">
			<div class="aiba-hero-brand">
				<span class="aiba-hero-icon dashicons dashicons-edit-large" aria-hidden="true"></span>
				<div>
					<h1 class="aiba-hero-title"><?php echo esc_html( $aiba_page_title ); ?></h1>
					<?php if ( $aiba_page_sub !== '' ) : ?>
						<p class="aiba-hero-sub"><?php echo esc_html( $aiba_page_sub ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( $aiba_premium ) : ?>
				<div class="aiba-premium-pill" title="<?php esc_attr_e( 'Premium features are active', 'ai-blog-automator' ); ?>">
					<span class="dashicons dashicons-awards" aria-hidden="true"></span>
					<?php esc_html_e( 'Premium', 'ai-blog-automator' ); ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<div class="aiba-panel">

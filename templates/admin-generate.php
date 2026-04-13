<?php
/**
 * Manual generation template.
 *
 * @package AI_Blog_Automator
 *
 * @var array<int, array<string, mixed>> $trends
 */

defined( 'ABSPATH' ) || exit;

$categories      = get_categories( array( 'hide_empty' => false ) );
$article_formats = AIBA_LLM_Templates::get_article_formats();
$def_cats        = AIBA_Core::get_default_category_ids();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Prefill only; generation uses AJAX + nonce.
$aiba_gen_prefill = array(
	'topic'              => isset( $_GET['topic'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['topic'] ) ) : '',
	'primary_keyword'    => isset( $_GET['primary_keyword'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['primary_keyword'] ) ) : '',
	'secondary_keywords' => isset( $_GET['secondary_keywords'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['secondary_keywords'] ) ) : '',
	'word_count'         => isset( $_GET['word_count'] ) ? max( 300, min( 5000, (int) $_GET['word_count'] ) ) : 0,
	'tone'               => isset( $_GET['tone'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['tone'] ) ) : '',
	'article_template'   => isset( $_GET['article_template'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['article_template'] ) ) : '',
	'publish_now'        => isset( $_GET['publish_now'] ) && '1' === (string) wp_unslash( $_GET['publish_now'] ),
);
$get_cat_ids = array();
if ( ! empty( $_GET['category_ids'] ) && is_array( $_GET['category_ids'] ) ) {
	foreach ( wp_unslash( $_GET['category_ids'] ) as $cid ) {
		$get_cat_ids[] = (int) $cid;
	}
} elseif ( isset( $_GET['category_ids'] ) && is_scalar( $_GET['category_ids'] ) ) {
	$get_cat_ids[] = (int) wp_unslash( $_GET['category_ids'] );
}
$get_cat_ids = array_values( array_filter( array_unique( $get_cat_ids ) ) );
// phpcs:enable WordPress.Security.NonceVerification.Recommended

require AIBA_PLUGIN_DIR . 'templates/partials/shell-start.php';

$aiba_generate_alerts = isset( $aiba_generate_alerts ) && is_array( $aiba_generate_alerts ) ? $aiba_generate_alerts : array();
$aiba_logs_url        = admin_url( 'admin.php?page=aiba-logs' );
$aiba_has_err         = false;
foreach ( $aiba_generate_alerts as $aiba_alert_row ) {
	if ( isset( $aiba_alert_row->status ) && 'error' === $aiba_alert_row->status ) {
		$aiba_has_err = true;
		break;
	}
}
?>

	<div
		id="aiba-generate-alerts-mount"
		class="aiba-generate-alerts-mount"
		data-logs-url="<?php echo esc_url( $aiba_logs_url ); ?>"
		role="region"
		aria-label="<?php esc_attr_e( 'Generation and API issues from the activity log', 'ai-blog-automator' ); ?>"
	>
		<?php if ( ! empty( $aiba_generate_alerts ) ) : ?>
			<div class="notice <?php echo $aiba_has_err ? 'notice-error' : 'notice-warning'; ?> aiba-generate-alerts">
				<p class="aiba-generate-alerts-title">
					<strong><?php esc_html_e( 'Recent issues that may block or affect generation', 'ai-blog-automator' ); ?></strong>
					<?php
					echo ' ';
					echo '<a href="' . esc_url( $aiba_logs_url ) . '">' . esc_html__( 'View activity logs', 'ai-blog-automator' ) . '</a>';
					?>
				</p>
				<ul class="aiba-generate-alerts-list">
					<?php foreach ( $aiba_generate_alerts as $aiba_alert_row ) : ?>
						<?php
						$aiba_st = isset( $aiba_alert_row->status ) ? (string) $aiba_alert_row->status : '';
						$aiba_ac = isset( $aiba_alert_row->action ) ? (string) $aiba_alert_row->action : '';
						$aiba_ms = isset( $aiba_alert_row->message ) ? (string) $aiba_alert_row->message : '';
						$aiba_ts = isset( $aiba_alert_row->created_at ) ? (string) $aiba_alert_row->created_at : '';
						?>
						<li class="aiba-gen-alert aiba-gen-alert--<?php echo esc_attr( 'error' === $aiba_st ? 'error' : 'warning' ); ?>">
							<span class="aiba-gen-alert-meta"><?php echo esc_html( $aiba_ts . ' · ' . $aiba_ac . ' · ' . $aiba_st ); ?></span>
							<span class="aiba-gen-alert-msg"><?php echo esc_html( $aiba_ms ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>

	<form id="aiba-generate-form" class="aiba-form aiba-form-card" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<?php /* Required so a native GET submit still opens this screen (not a blank admin.php). */ ?>
		<input type="hidden" name="page" value="aiba-generate" />
		<table class="form-table">
			<tr>
				<th scope="row"><label for="aiba_gen_topic"><?php esc_html_e( 'Topic', 'ai-blog-automator' ); ?></label></th>
				<td>
					<input type="text" class="large-text" id="aiba_gen_topic" name="topic" required value="<?php echo esc_attr( $aiba_gen_prefill['topic'] ); ?>" />
					<?php if ( ! empty( $trends ) ) : ?>
						<p class="description"><?php esc_html_e( 'Pick from cached trends:', 'ai-blog-automator' ); ?></p>
						<select id="aiba_trend_pick" class="aiba-select-trend">
							<option value=""><?php esc_html_e( '— Select —', 'ai-blog-automator' ); ?></option>
							<?php foreach ( $trends as $t ) : ?>
								<option
									value="<?php echo esc_attr( (string) ( $t['topic'] ?? '' ) ); ?>"
									data-kw="<?php echo esc_attr( (string) ( $t['primary_keyword'] ?? '' ) ); ?>"
									data-sec="<?php echo esc_attr( implode( ', ', $t['secondary_keywords'] ?? array() ) ); ?>">
									<?php echo esc_html( (string) ( $t['topic'] ?? '' ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aiba_gen_primary"><?php esc_html_e( 'Primary keyword', 'ai-blog-automator' ); ?></label></th>
				<td><input type="text" class="large-text" id="aiba_gen_primary" name="primary_keyword" required value="<?php echo esc_attr( $aiba_gen_prefill['primary_keyword'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="aiba_gen_secondary"><?php esc_html_e( 'Secondary keywords', 'ai-blog-automator' ); ?></label></th>
				<td><input type="text" class="large-text" id="aiba_gen_secondary" name="secondary_keywords" placeholder="kw1, kw2, kw3" value="<?php echo esc_attr( $aiba_gen_prefill['secondary_keywords'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="aiba_gen_cats"><?php esc_html_e( 'Categories', 'ai-blog-automator' ); ?></label></th>
				<td>
					<select id="aiba_gen_cats" name="category_ids[]" multiple size="6" style="min-width:260px">
						<?php
						$cat_selected = ! empty( $get_cat_ids ) ? $get_cat_ids : $def_cats;
						foreach ( $categories as $cat ) :
							?>
							<option value="<?php echo (int) $cat->term_id; ?>" <?php selected( in_array( (int) $cat->term_id, $cat_selected, true ), true ); ?>>
								<?php echo esc_html( $cat->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aiba_gen_format"><?php esc_html_e( 'Article format', 'ai-blog-automator' ); ?></label></th>
				<td>
					<select id="aiba_gen_format" name="article_template">
						<?php
						$cur_f = (string) get_option( 'aiba_article_template', 'standard' );
						if ( $aiba_gen_prefill['article_template'] !== '' && isset( $article_formats[ $aiba_gen_prefill['article_template'] ] ) ) {
							$cur_f = $aiba_gen_prefill['article_template'];
						}
						foreach ( $article_formats as $slug => $label ) {
							printf( '<option %s value="%s">%s</option>', selected( $cur_f, $slug, false ), esc_attr( $slug ), esc_html( $label ) );
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aiba_gen_wc"><?php esc_html_e( 'Word count', 'ai-blog-automator' ); ?></label></th>
				<td>
					<?php
					$gwc_default = max( 300, min( 5000, (int) get_option( 'aiba_word_count', 1500 ) ) );
					$gwc         = $aiba_gen_prefill['word_count'] > 0 ? $aiba_gen_prefill['word_count'] : $gwc_default;
					$gwc         = max( 300, min( 5000, $gwc ) );
					?>
					<input type="range" id="aiba_gen_wc_slider" min="300" max="5000" step="50" value="<?php echo esc_attr( (string) $gwc ); ?>" />
					<input type="number" id="aiba_gen_wc" name="word_count" min="300" max="5000" value="<?php echo esc_attr( (string) $gwc ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aiba_gen_tone"><?php esc_html_e( 'Tone', 'ai-blog-automator' ); ?></label></th>
				<td>
					<select id="aiba_gen_tone" name="tone">
						<?php
						$tones = array( 'Professional', 'Friendly', 'Authoritative', 'Conversational', 'Witty' );
						$cur   = $aiba_gen_prefill['tone'] !== '' ? $aiba_gen_prefill['tone'] : (string) get_option( 'aiba_tone', 'Professional' );
						if ( ! in_array( $cur, $tones, true ) ) {
							$cur = 'Professional';
						}
						foreach ( $tones as $tone ) :
							?>
							<option value="<?php echo esc_attr( $tone ); ?>" <?php selected( $cur, $tone ); ?>><?php echo esc_html( $tone ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Publish', 'ai-blog-automator' ); ?></th>
				<td>
					<label><input type="checkbox" id="aiba_gen_publish" name="publish_now" value="1" <?php checked( $aiba_gen_prefill['publish_now'] ); ?> /> <?php esc_html_e( 'Publish immediately (otherwise uses automation settings / draft)', 'ai-blog-automator' ); ?></label>
				</td>
			</tr>
		</table>
		<p>
			<button type="button" class="button button-primary button-large" id="aiba_gen_submit"><?php esc_html_e( 'Generate & Publish', 'ai-blog-automator' ); ?></button>
		</p>
	</form>
	<script>
	(function () {
		var f = document.getElementById('aiba-generate-form');
		if (!f) return;
		f.addEventListener('submit', function (e) { e.preventDefault(); });
	})();
	</script>

	<div id="aiba-gen-progress" class="aiba-progress aiba-form-card" hidden>
		<ol>
			<li class="aiba-step" data-step="outline"><?php esc_html_e( 'Outline & sections', 'ai-blog-automator' ); ?></li>
			<li class="aiba-step" data-step="assemble"><?php esc_html_e( 'Assemble & save post', 'ai-blog-automator' ); ?></li>
			<li class="aiba-step" data-step="done"><?php esc_html_e( 'Done', 'ai-blog-automator' ); ?></li>
		</ol>
		<p id="aiba-gen-result"></p>
	</div>
<?php require AIBA_PLUGIN_DIR . 'templates/partials/shell-end.php'; ?>

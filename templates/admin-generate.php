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
$aiba_gen_nonce       = wp_create_nonce( 'aiba_generate' );
$aiba_alerts_payload  = array();
foreach ( $aiba_generate_alerts as $aiba_alert_row ) {
	$aiba_alerts_payload[] = array(
		'status'     => isset( $aiba_alert_row->status ) ? (string) $aiba_alert_row->status : '',
		'action'     => isset( $aiba_alert_row->action ) ? (string) $aiba_alert_row->action : '',
		'message'    => isset( $aiba_alert_row->message ) ? (string) $aiba_alert_row->message : '',
		'created_at' => isset( $aiba_alert_row->created_at ) ? (string) $aiba_alert_row->created_at : '',
	);
}
$aiba_alerts_json = wp_json_encode(
	$aiba_alerts_payload,
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
);
if ( ! is_string( $aiba_alerts_json ) ) {
	$aiba_alerts_json = '[]';
}
?>

	<p class="aiba-gen-intro"><?php esc_html_e( 'Fill in topic and keyword, then run the pipeline. Progress and any API errors appear below the button.', 'ai-blog-automator' ); ?></p>

	<div
		id="aiba-generate-alerts-mount"
		class="aiba-generate-alerts-mount"
		data-logs-url="<?php echo esc_url( $aiba_logs_url ); ?>"
		role="region"
		aria-label="<?php esc_attr_e( 'Generation and API issues from the activity log', 'ai-blog-automator' ); ?>"
	>
		<div
			id="aiba-generate-alert-slot"
			class="aiba-generate-alert-slot"
			data-empty-label="<?php echo esc_attr( __( 'No logged issues yet. If a provider blocks generation, the latest message will show here after you try.', 'ai-blog-automator' ) ); ?>"
		></div>
		<div class="aiba-generate-alert-nav" id="aiba-generate-alert-nav" hidden>
			<button type="button" class="button" id="aiba-gen-alert-prev" aria-label="<?php esc_attr_e( 'Previous issue', 'ai-blog-automator' ); ?>"><?php esc_html_e( 'Previous', 'ai-blog-automator' ); ?></button>
			<span class="aiba-generate-alert-pos" id="aiba-gen-alert-pos" aria-live="polite"></span>
			<button type="button" class="button" id="aiba-gen-alert-next" aria-label="<?php esc_attr_e( 'Next issue', 'ai-blog-automator' ); ?>"><?php esc_html_e( 'Next', 'ai-blog-automator' ); ?></button>
		</div>
	</div>
	<script type="application/json" id="aiba-generate-alert-data"><?php echo $aiba_alerts_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD style payload, hex-escaped. ?></script>
	<script>
	(function () {
		var dataEl = document.getElementById('aiba-generate-alert-data');
		var slot = document.getElementById('aiba-generate-alert-slot');
		var nav = document.getElementById('aiba-generate-alert-nav');
		var posEl = document.getElementById('aiba-gen-alert-pos');
		var btnP = document.getElementById('aiba-gen-alert-prev');
		var btnN = document.getElementById('aiba-gen-alert-next');
		var mount = document.getElementById('aiba-generate-alerts-mount');
		var logsUrl = mount ? mount.getAttribute('data-logs-url') : '';
		var L = {
			title: <?php echo wp_json_encode( __( 'Latest from activity log', 'ai-blog-automator' ) ); ?>,
			logs: <?php echo wp_json_encode( __( 'Activity logs', 'ai-blog-automator' ) ); ?>,
			last: <?php echo wp_json_encode( __( 'Last attempt', 'ai-blog-automator' ) ); ?>
		};
		if (!dataEl || !slot) {
			return;
		}
		var items = [];
		try {
			items = JSON.parse(dataEl.textContent || '[]') || [];
		} catch (e) {
			items = [];
		}
		var idx = 0;
		function esc(s) {
			var d = document.createElement('div');
			d.textContent = s == null ? '' : String(s);
			return d.innerHTML;
		}
		function render() {
			if (!items.length) {
				slot.innerHTML = '<div class="aiba-gen-ready-card"><span class="dashicons dashicons-yes-alt aiba-gen-ready-icon" aria-hidden="true"></span><p class="aiba-gen-ready-text">' + esc(slot.getAttribute('data-empty-label') || '') + '</p></div>';
				if (nav) {
					nav.hidden = true;
				}
				return;
			}
			var it = items[idx];
			var isErr = it.status === 'error';
			var noticeClass = isErr ? 'notice-error' : 'notice-warning';
			var meta = esc((it.created_at || '') + ' · ' + (it.action || '') + ' · ' + (it.status || ''));
			var msg = esc(it.message || '');
			var logs = logsUrl ? ' <a href="' + esc(logsUrl) + '">' + esc(L.logs) + '</a>' : '';
			slot.innerHTML = '<div class="notice ' + noticeClass + ' aiba-generate-alerts"><p class="aiba-generate-alerts-title"><strong>' + esc(L.title) + '</strong>' + logs + '</p><p class="aiba-gen-alert-meta">' + meta + '</p><p class="aiba-gen-alert-msg">' + msg + '</p></div>';
			if (nav) {
				nav.hidden = items.length < 2;
				if (posEl) {
					posEl.textContent = String(idx + 1) + ' / ' + String(items.length);
				}
			}
		}
		render();
		if (btnP) {
			btnP.addEventListener('click', function () {
				idx = (idx - 1 + items.length) % items.length;
				render();
			});
		}
		if (btnN) {
			btnN.addEventListener('click', function () {
				idx = (idx + 1) % items.length;
				render();
			});
		}
		window.aibaGenerateAlertLabels = L;
		window.aibaGenerateAlertPushLive = function (message, isWarning) {
			if (!slot || !message) {
				return;
			}
			var noticeClass = isWarning ? 'notice-warning' : 'notice-error';
			var logs = logsUrl ? ' <a href="' + esc(logsUrl) + '">' + esc(L.logs) + '</a>' : '';
			slot.innerHTML = '<div class="notice ' + noticeClass + ' aiba-generate-alerts aiba-generate-alerts--live"><p class="aiba-generate-alerts-title"><strong>' + esc(L.last) + '</strong>' + logs + '</p><p class="aiba-gen-alert-msg">' + esc(message) + '</p></div>';
			if (nav) {
				nav.hidden = true;
			}
		};
	})();
	</script>

	<form id="aiba-generate-form" class="aiba-form aiba-form-card aiba-gen-form" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" data-gen-nonce="<?php echo esc_attr( $aiba_gen_nonce ); ?>">
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
		<div class="aiba-gen-actions">
			<button type="button" class="button button-primary button-hero" id="aiba_gen_submit">
				<span class="dashicons dashicons-admin-post aiba-gen-btn-icon" aria-hidden="true"></span>
				<span class="aiba-gen-btn-label"><?php esc_html_e( 'Generate post', 'ai-blog-automator' ); ?></span>
			</button>
			<span id="aiba-gen-spinner" class="aiba-gen-spinner" hidden aria-hidden="true"></span>
		</div>
		<div id="aiba-gen-progress" class="aiba-progress aiba-gen-progress" hidden>
			<p class="aiba-gen-progress-title"><?php esc_html_e( 'Progress', 'ai-blog-automator' ); ?></p>
			<ol>
				<li class="aiba-step" data-step="outline"><?php esc_html_e( 'Outline & sections', 'ai-blog-automator' ); ?></li>
				<li class="aiba-step" data-step="assemble"><?php esc_html_e( 'Assemble & save post', 'ai-blog-automator' ); ?></li>
				<li class="aiba-step" data-step="done"><?php esc_html_e( 'Done', 'ai-blog-automator' ); ?></li>
			</ol>
			<p id="aiba-gen-result" class="aiba-gen-result" role="status" aria-live="polite"></p>
		</div>
	</form>
	<script>
	(function () {
		var f = document.getElementById('aiba-generate-form');
		if (f) {
			f.addEventListener('submit', function (e) {
				e.preventDefault();
			});
		}
	})();
	</script>
<?php require AIBA_PLUGIN_DIR . 'templates/partials/shell-end.php'; ?>

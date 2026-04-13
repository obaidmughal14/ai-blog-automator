<?php
/**
 * Queue management template.
 *
 * @package AI_Blog_Automator
 *
 * @var array<int, object> $rows
 */

defined( 'ABSPATH' ) || exit;

$status_filter   = isset( $_GET['aiba_status'] ) ? sanitize_text_field( wp_unslash( $_GET['aiba_status'] ) ) : '';
$categories      = get_categories( array( 'hide_empty' => false ) );
$article_formats = AIBA_LLM_Templates::get_article_formats();
require AIBA_PLUGIN_DIR . 'templates/partials/shell-start.php';
?>

	<?php if ( ! empty( $_GET['added'] ) ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Job added to queue.', 'ai-blog-automator' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['bulk_added'] ) && is_numeric( $_GET['bulk_added'] ) ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( sprintf( /* translators: %d: number of jobs */ __( 'Added %d job(s) from keyword list.', 'ai-blog-automator' ), (int) $_GET['bulk_added'] ) ); ?></p></div>
	<?php endif; ?>

	<h2 class="aiba-section-title"><?php esc_html_e( 'Add to queue', 'ai-blog-automator' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aiba-form aiba-form-card">
		<input type="hidden" name="action" value="aiba_add_queue" />
		<?php wp_nonce_field( 'aiba_add_queue' ); ?>
		<table class="form-table">
			<tr>
				<th><label for="aiba_q_topic"><?php esc_html_e( 'Topic', 'ai-blog-automator' ); ?></label></th>
				<td><input name="topic" id="aiba_q_topic" class="large-text" required /></td>
			</tr>
			<tr>
				<th><label for="aiba_q_kw"><?php esc_html_e( 'Primary keyword', 'ai-blog-automator' ); ?></label></th>
				<td><input name="keyword" id="aiba_q_kw" class="large-text" required /></td>
			</tr>
			<tr>
				<th><label for="aiba_q_cat"><?php esc_html_e( 'Categories', 'ai-blog-automator' ); ?></label></th>
				<td>
					<?php
					$def_q = AIBA_Core::get_default_category_ids();
					?>
					<select name="category_ids[]" id="aiba_q_cat" multiple size="6" style="min-width:260px">
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo (int) $cat->term_id; ?>" <?php selected( in_array( (int) $cat->term_id, $def_q, true ), true ); ?>><?php echo esc_html( $cat->name ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Ctrl/Cmd+click for multiple. Matches Settings → Content defaults when pre-selected.', 'ai-blog-automator' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="aiba_q_secondary"><?php esc_html_e( 'Other keywords', 'ai-blog-automator' ); ?></label></th>
				<td>
					<textarea name="secondary_keywords" id="aiba_q_secondary" class="large-text" rows="3" placeholder="<?php esc_attr_e( 'comma or line separated', 'ai-blog-automator' ); ?>"></textarea>
					<p class="description"><?php esc_html_e( 'Optional. Included in prompts for broader coverage.', 'ai-blog-automator' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="aiba_q_tpl"><?php esc_html_e( 'Article format', 'ai-blog-automator' ); ?></label></th>
				<td>
					<select name="article_template" id="aiba_q_tpl">
						<?php
						$cur_tpl = (string) get_option( 'aiba_article_template', 'standard' );
						foreach ( $article_formats as $slug => $label ) {
							printf( '<option %s value="%s">%s</option>', selected( $cur_tpl, $slug, false ), esc_attr( $slug ), esc_html( $label ) );
						}
						?>
					</select>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Add to queue', 'ai-blog-automator' ) ); ?>
	</form>

	<h2 class="aiba-section-title"><?php esc_html_e( 'Bulk queue (month of content)', 'ai-blog-automator' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Each line is one queued article. Use tabs (paste from a spreadsheet) or pipes. Fields: title, focus keyphrase, other keywords (comma or semicolon), category term IDs (comma, optional), article format slug (optional). Default categories and format below apply when a column is empty. Legacy two-field lines still work: title|focus or a single title. Featured and in-article images use the Pexels API (if configured) or Unsplash; automated Google Image scraping is not supported.', 'ai-blog-automator' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aiba-form aiba-form-card">
		<input type="hidden" name="action" value="aiba_bulk_queue_keywords" />
		<?php wp_nonce_field( 'aiba_bulk_queue' ); ?>
		<table class="form-table">
			<tr>
				<th><label for="aiba_bulk_kw"><?php esc_html_e( 'Lines (TSV or pipe)', 'ai-blog-automator' ); ?></label></th>
				<td>
					<textarea class="large-text" rows="16" id="aiba_bulk_kw" name="bulk_keywords" placeholder="<?php esc_attr_e( "Example (tabs)\nBest oil change tips\toil change\tfilter, synthetic\t12,5\thow-to\n\nExample (pipes)\nFrame repair guide|collision repair|aluminum, ADAS|12|standard", 'ai-blog-automator' ); ?>"></textarea>
				</td>
			</tr>
			<tr>
				<th><label for="aiba_bulk_cat"><?php esc_html_e( 'Default categories', 'ai-blog-automator' ); ?></label></th>
				<td>
					<select name="bulk_category_ids[]" id="aiba_bulk_cat" multiple size="6" style="min-width:260px">
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo (int) $cat->term_id; ?>" <?php selected( in_array( (int) $cat->term_id, $def_q, true ), true ); ?>><?php echo esc_html( $cat->name ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Used when a line does not include category IDs.', 'ai-blog-automator' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="aiba_bulk_default_tpl"><?php esc_html_e( 'Default article format', 'ai-blog-automator' ); ?></label></th>
				<td>
					<select name="bulk_default_article_template" id="aiba_bulk_default_tpl">
						<?php
						$bulk_def_tpl = (string) get_option( 'aiba_article_template', 'standard' );
						foreach ( $article_formats as $slug => $label ) {
							printf( '<option %s value="%s">%s</option>', selected( $bulk_def_tpl, $slug, false ), esc_attr( $slug ), esc_html( $label ) );
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Stagger publishing', 'ai-blog-automator' ); ?></th>
				<td>
					<select name="bulk_schedule_mode" id="aiba_bulk_schedule_mode">
						<option value="none"><?php esc_html_e( 'No schedule (process as soon as cron runs)', 'ai-blog-automator' ); ?></option>
						<option value="daily"><?php esc_html_e( 'One job per day (spread across a month)', 'ai-blog-automator' ); ?></option>
						<option value="weekly"><?php esc_html_e( 'One job per week', 'ai-blog-automator' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'First row uses the start date; each following row adds a day or week. Time matches Settings → Automation “Publish time”.', 'ai-blog-automator' ); ?></p>
					<p><label for="aiba_bulk_schedule_start"><?php esc_html_e( 'Start date', 'ai-blog-automator' ); ?></label>
						<input type="date" name="bulk_schedule_start" id="aiba_bulk_schedule_start" value="<?php echo esc_attr( gmdate( 'Y-m-d', current_time( 'timestamp' ) ) ); ?>" /></p>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Add all to queue', 'ai-blog-automator' ) ); ?>
	</form>

	<h2 class="aiba-section-title"><?php esc_html_e( 'Filter', 'ai-blog-automator' ); ?></h2>
	<ul class="subsubsub">
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aiba-queue' ) ); ?>" class="<?php echo $status_filter === '' ? 'current' : ''; ?>"><?php esc_html_e( 'All', 'ai-blog-automator' ); ?></a> |</li>
		<?php foreach ( array( 'pending', 'processing', 'completed', 'failed' ) as $st ) : ?>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aiba-queue&aiba_status=' . $st ) ); ?>" class="<?php echo $status_filter === $st ? 'current' : ''; ?>"><?php echo esc_html( ucfirst( $st ) ); ?></a> |</li>
		<?php endforeach; ?>
	</ul>

	<form id="aiba-queue-bulk-form">
		<div class="tablenav top">
			<select name="bulk" id="aiba-bulk-action">
				<option value=""><?php esc_html_e( 'Bulk actions', 'ai-blog-automator' ); ?></option>
				<option value="delete"><?php esc_html_e( 'Delete', 'ai-blog-automator' ); ?></option>
				<option value="requeue"><?php esc_html_e( 'Re-queue failed', 'ai-blog-automator' ); ?></option>
			</select>
			<button type="button" class="button" id="aiba-bulk-apply"><?php esc_html_e( 'Apply', 'ai-blog-automator' ); ?></button>
		</div>

		<table class="widefat striped aiba-table aiba-table-modern">
			<thead>
				<tr>
					<td class="manage-column column-cb"><input type="checkbox" id="aiba-cb-all" /></td>
					<th><?php esc_html_e( 'Topic', 'ai-blog-automator' ); ?></th>
					<th><?php esc_html_e( 'Keyword', 'ai-blog-automator' ); ?></th>
					<th><?php esc_html_e( 'Other KW', 'ai-blog-automator' ); ?></th>
					<th><?php esc_html_e( 'Format', 'ai-blog-automator' ); ?></th>
					<th><?php esc_html_e( 'Categories', 'ai-blog-automator' ); ?></th>
					<th><?php esc_html_e( 'Scheduled', 'ai-blog-automator' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ai-blog-automator' ); ?></th>
					<th><?php esc_html_e( 'Post', 'ai-blog-automator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="9"><?php esc_html_e( 'No queue items.', 'ai-blog-automator' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<th scope="row" class="check-column"><input type="checkbox" class="aiba-row-cb" value="<?php echo (int) $r->id; ?>" /></th>
							<td><?php echo esc_html( (string) $r->topic ); ?></td>
							<td><?php echo esc_html( (string) $r->keyword ); ?></td>
							<td>
								<?php
								$secq = isset( $r->secondary_keywords ) ? (string) $r->secondary_keywords : '';
								echo $secq !== '' ? esc_html( mb_strlen( $secq ) > 48 ? mb_substr( $secq, 0, 48 ) . '…' : $secq ) : '—';
								?>
							</td>
							<td>
								<?php
								$ft = isset( $r->article_template ) ? (string) $r->article_template : '';
								if ( $ft !== '' ) {
									echo esc_html( $article_formats[ $ft ] ?? $ft );
								} else {
									echo '—';
								}
								?>
							</td>
							<td>
								<?php
								$ids = isset( $r->category_ids ) ? AIBA_Core::decode_queue_category_ids( (string) $r->category_ids ) : array();
								if ( empty( $ids ) && ! empty( $r->category_id ) ) {
									$ids = array( (int) $r->category_id );
								}
								$names = array();
								foreach ( $ids as $cid ) {
									$cat = get_category( $cid );
									if ( $cat instanceof WP_Term ) {
										$names[] = $cat->name;
									}
								}
								echo $names ? esc_html( implode( ', ', $names ) ) : '—';
								?>
							</td>
							<td><?php echo esc_html( $r->scheduled_at ? (string) $r->scheduled_at : '—' ); ?></td>
							<td><span class="aiba-badge aiba-badge-<?php echo esc_attr( (string) $r->status ); ?>"><?php echo esc_html( (string) $r->status ); ?></span></td>
							<td>
								<?php
								if ( ! empty( $r->post_id ) ) {
									echo '<a href="' . esc_url( get_edit_post_link( (int) $r->post_id, 'raw' ) ) . '">' . esc_html__( 'Edit', 'ai-blog-automator' ) . '</a>';
								} else {
									echo '—';
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</form>
<?php require AIBA_PLUGIN_DIR . 'templates/partials/shell-end.php'; ?>

<?php
/**
 * Queue management template.
 *
 * @package AI_Blog_Automator
 *
 * @var array<int, object> $rows
 */

defined( 'ABSPATH' ) || exit;

$status_filter = isset( $_GET['aiba_status'] ) ? sanitize_text_field( wp_unslash( $_GET['aiba_status'] ) ) : '';
$categories    = get_categories( array( 'hide_empty' => false ) );
?>
<div class="wrap aiba-wrap">
	<h1><?php esc_html_e( 'Content queue', 'ai-blog-automator' ); ?></h1>

	<?php if ( ! empty( $_GET['added'] ) ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Job added to queue.', 'ai-blog-automator' ); ?></p></div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Add to queue', 'ai-blog-automator' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aiba-form">
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
				<th><label for="aiba_q_cat"><?php esc_html_e( 'Category', 'ai-blog-automator' ); ?></label></th>
				<td>
					<select name="category_id" id="aiba_q_cat">
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo (int) $cat->term_id; ?>"><?php echo esc_html( $cat->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Add to queue', 'ai-blog-automator' ) ); ?>
	</form>

	<h2><?php esc_html_e( 'Filter', 'ai-blog-automator' ); ?></h2>
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

		<table class="widefat striped aiba-table">
			<thead>
				<tr>
					<td class="manage-column column-cb"><input type="checkbox" id="aiba-cb-all" /></td>
					<th><?php esc_html_e( 'Topic', 'ai-blog-automator' ); ?></th>
					<th><?php esc_html_e( 'Keyword', 'ai-blog-automator' ); ?></th>
					<th><?php esc_html_e( 'Category', 'ai-blog-automator' ); ?></th>
					<th><?php esc_html_e( 'Scheduled', 'ai-blog-automator' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ai-blog-automator' ); ?></th>
					<th><?php esc_html_e( 'Post', 'ai-blog-automator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No queue items.', 'ai-blog-automator' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<th scope="row" class="check-column"><input type="checkbox" class="aiba-row-cb" value="<?php echo (int) $r->id; ?>" /></th>
							<td><?php echo esc_html( (string) $r->topic ); ?></td>
							<td><?php echo esc_html( (string) $r->keyword ); ?></td>
							<td>
								<?php
								$cid = (int) $r->category_id;
								$cat = $cid ? get_category( $cid ) : false;
								echo ( $cat instanceof WP_Term ) ? esc_html( $cat->name ) : '—';
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
</div>

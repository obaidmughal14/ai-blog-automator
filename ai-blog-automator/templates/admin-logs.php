<?php
/**
 * Logs template.
 *
 * @package AI_Blog_Automator
 *
 * @var array<int, object> $rows
 */

defined( 'ABSPATH' ) || exit;

require AIBA_PLUGIN_DIR . 'templates/partials/shell-start.php';
?>

	<form method="get" class="aiba-form aiba-filters aiba-form-card">
		<input type="hidden" name="page" value="aiba-logs" />
		<label>
			<?php esc_html_e( 'Status', 'ai-blog-automator' ); ?>
			<select name="aiba_log_status">
				<option value=""><?php esc_html_e( 'Any', 'ai-blog-automator' ); ?></option>
				<option value="success" <?php selected( isset( $_GET['aiba_log_status'] ) && 'success' === $_GET['aiba_log_status'] ); ?>><?php esc_html_e( 'Success', 'ai-blog-automator' ); ?></option>
				<option value="error" <?php selected( isset( $_GET['aiba_log_status'] ) && 'error' === $_GET['aiba_log_status'] ); ?>><?php esc_html_e( 'Error', 'ai-blog-automator' ); ?></option>
				<option value="warning" <?php selected( isset( $_GET['aiba_log_status'] ) && 'warning' === $_GET['aiba_log_status'] ); ?>><?php esc_html_e( 'Warning', 'ai-blog-automator' ); ?></option>
			</select>
		</label>
		<label>
			<?php esc_html_e( 'Action', 'ai-blog-automator' ); ?>
			<input type="text" name="aiba_log_action" value="<?php echo isset( $_GET['aiba_log_action'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['aiba_log_action'] ) ) ) : ''; ?>" />
		</label>
		<label>
			<?php esc_html_e( 'From', 'ai-blog-automator' ); ?>
			<input type="date" name="aiba_from" value="<?php echo isset( $_GET['aiba_from'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['aiba_from'] ) ) ) : ''; ?>" />
		</label>
		<label>
			<?php esc_html_e( 'To', 'ai-blog-automator' ); ?>
			<input type="date" name="aiba_to" value="<?php echo isset( $_GET['aiba_to'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['aiba_to'] ) ) ) : ''; ?>" />
		</label>
		<?php submit_button( __( 'Filter', 'ai-blog-automator' ), 'secondary', 'submit', false ); ?>
	</form>

	<p>
		<button type="button" class="button" id="aiba-clear-logs"><?php esc_html_e( 'Clear logs', 'ai-blog-automator' ); ?></button>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=aiba_export_logs' ), 'aiba_export_logs' ) ); ?>"><?php esc_html_e( 'Export CSV', 'ai-blog-automator' ); ?></a>
	</p>

	<h2 class="aiba-section-title"><?php esc_html_e( 'Log entries', 'ai-blog-automator' ); ?></h2>
	<table class="widefat striped aiba-table aiba-table-modern">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'ai-blog-automator' ); ?></th>
				<th><?php esc_html_e( 'Post', 'ai-blog-automator' ); ?></th>
				<th><?php esc_html_e( 'Action', 'ai-blog-automator' ); ?></th>
				<th><?php esc_html_e( 'Status', 'ai-blog-automator' ); ?></th>
				<th><?php esc_html_e( 'Message', 'ai-blog-automator' ); ?></th>
				<th><?php esc_html_e( 'Time', 'ai-blog-automator' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No entries.', 'ai-blog-automator' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo (int) $row->id; ?></td>
						<td><?php echo (int) $row->post_id; ?></td>
						<td><?php echo esc_html( (string) $row->action ); ?></td>
						<td><span class="aiba-badge aiba-badge-<?php echo esc_attr( (string) $row->status ); ?>"><?php echo esc_html( (string) $row->status ); ?></span></td>
						<td><?php echo esc_html( wp_trim_words( (string) $row->message, 40 ) ); ?></td>
						<td><?php echo esc_html( (string) $row->created_at ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
<?php require AIBA_PLUGIN_DIR . 'templates/partials/shell-end.php'; ?>

<?php
/**
 * Dashboard template.
 *
 * @package AI_Blog_Automator
 *
 * @var array<string, int|float> $stats
 * @var array<int, object>       $logs
 * @var array<int, object>       $queue
 */

defined( 'ABSPATH' ) || exit;

require AIBA_PLUGIN_DIR . 'templates/partials/shell-start.php';
?>

	<div class="aiba-stat-grid">
		<div class="aiba-stat-tile">
			<span class="aiba-stat-icon dashicons dashicons-admin-post" aria-hidden="true"></span>
			<div class="aiba-stat-body">
				<span class="aiba-stat-label"><?php esc_html_e( 'Posts generated', 'ai-blog-automator' ); ?></span>
				<span class="aiba-stat-value"><?php echo esc_html( (string) ( $stats['total_generated'] ?? 0 ) ); ?></span>
				<span class="aiba-stat-hint"><?php esc_html_e( 'All time', 'ai-blog-automator' ); ?></span>
			</div>
		</div>
		<div class="aiba-stat-tile">
			<span class="aiba-stat-icon dashicons dashicons-calendar-alt" aria-hidden="true"></span>
			<div class="aiba-stat-body">
				<span class="aiba-stat-label"><?php esc_html_e( 'This month', 'ai-blog-automator' ); ?></span>
				<span class="aiba-stat-value"><?php echo esc_html( (string) ( $stats['this_month'] ?? 0 ) ); ?></span>
				<span class="aiba-stat-hint"><?php esc_html_e( 'Successful publishes', 'ai-blog-automator' ); ?></span>
			</div>
		</div>
		<div class="aiba-stat-tile">
			<span class="aiba-stat-icon dashicons dashicons-list-view" aria-hidden="true"></span>
			<div class="aiba-stat-body">
				<span class="aiba-stat-label"><?php esc_html_e( 'Queue pending', 'ai-blog-automator' ); ?></span>
				<span class="aiba-stat-value"><?php echo esc_html( (string) ( $stats['pending'] ?? 0 ) ); ?></span>
				<span class="aiba-stat-hint"><?php esc_html_e( 'Waiting to run', 'ai-blog-automator' ); ?></span>
			</div>
		</div>
		<div class="aiba-stat-tile">
			<span class="aiba-stat-icon dashicons dashicons-warning" aria-hidden="true"></span>
			<div class="aiba-stat-body">
				<span class="aiba-stat-label"><?php esc_html_e( 'Failed jobs', 'ai-blog-automator' ); ?></span>
				<span class="aiba-stat-value"><?php echo esc_html( (string) ( $stats['failed'] ?? 0 ) ); ?></span>
				<span class="aiba-stat-hint"><?php esc_html_e( 'Needs attention', 'ai-blog-automator' ); ?></span>
			</div>
		</div>
		<div class="aiba-stat-tile">
			<span class="aiba-stat-icon dashicons dashicons-chart-line" aria-hidden="true"></span>
			<div class="aiba-stat-body">
				<span class="aiba-stat-label"><?php esc_html_e( 'Avg. SEO score', 'ai-blog-automator' ); ?></span>
				<span class="aiba-stat-value"><?php echo esc_html( (string) ( $stats['avg_seo'] ?? 0 ) ); ?></span>
				<span class="aiba-stat-hint"><?php esc_html_e( 'Estimate (recent posts)', 'ai-blog-automator' ); ?></span>
			</div>
		</div>
	</div>

	<div class="aiba-toolbar">
		<button type="button" class="button button-primary button-hero" id="aiba-dash-generate"><?php esc_html_e( 'Generate post now', 'ai-blog-automator' ); ?></button>
		<button type="button" class="button" id="aiba-dash-trends"><?php esc_html_e( 'Fetch trends', 'ai-blog-automator' ); ?></button>
		<button type="button" class="button" id="aiba-dash-queue"><?php esc_html_e( 'Process queue', 'ai-blog-automator' ); ?></button>
	</div>
	<p class="aiba-inline-msg" id="aiba-dash-msg" aria-live="polite"></p>

	<div class="aiba-split">
		<div class="aiba-split-col">
			<h2 class="aiba-section-title"><?php esc_html_e( 'Recent activity', 'ai-blog-automator' ); ?></h2>
			<table class="widefat striped aiba-table aiba-table-modern">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'ai-blog-automator' ); ?></th>
						<th><?php esc_html_e( 'Action', 'ai-blog-automator' ); ?></th>
						<th><?php esc_html_e( 'Status', 'ai-blog-automator' ); ?></th>
						<th><?php esc_html_e( 'Message', 'ai-blog-automator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr><td colspan="4" class="aiba-empty"><?php esc_html_e( 'No log entries yet.', 'ai-blog-automator' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $logs as $row ) : ?>
							<tr>
								<td><code class="aiba-mono"><?php echo esc_html( (string) $row->created_at ); ?></code></td>
								<td><?php echo esc_html( (string) $row->action ); ?></td>
								<td><span class="aiba-badge aiba-badge-<?php echo esc_attr( (string) $row->status ); ?>"><?php echo esc_html( (string) $row->status ); ?></span></td>
								<td><?php echo esc_html( wp_trim_words( (string) $row->message, 24 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<div class="aiba-split-col">
			<h2 class="aiba-section-title"><?php esc_html_e( 'Queue preview', 'ai-blog-automator' ); ?></h2>
			<table class="widefat striped aiba-table aiba-table-modern">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Topic', 'ai-blog-automator' ); ?></th>
						<th><?php esc_html_e( 'Keyword', 'ai-blog-automator' ); ?></th>
						<th><?php esc_html_e( 'Status', 'ai-blog-automator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $queue ) ) : ?>
						<tr><td colspan="3" class="aiba-empty"><?php esc_html_e( 'Queue is empty.', 'ai-blog-automator' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $queue as $row ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $row->topic ); ?></td>
								<td><span class="aiba-muted"><?php echo esc_html( (string) $row->keyword ); ?></span></td>
								<td><span class="aiba-badge aiba-badge-<?php echo esc_attr( (string) $row->status ); ?>"><?php echo esc_html( (string) $row->status ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

<?php require AIBA_PLUGIN_DIR . 'templates/partials/shell-end.php'; ?>

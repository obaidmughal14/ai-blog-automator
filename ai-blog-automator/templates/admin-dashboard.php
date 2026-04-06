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
?>
<div class="wrap aiba-wrap">
	<h1><?php esc_html_e( 'AI Blog Automator', 'ai-blog-automator' ); ?></h1>

	<div class="aiba-cards">
		<div class="aiba-card">
			<h3><?php esc_html_e( 'Posts generated (all time)', 'ai-blog-automator' ); ?></h3>
			<p class="aiba-stat"><?php echo esc_html( (string) ( $stats['total_generated'] ?? 0 ) ); ?></p>
		</div>
		<div class="aiba-card">
			<h3><?php esc_html_e( 'This month', 'ai-blog-automator' ); ?></h3>
			<p class="aiba-stat"><?php echo esc_html( (string) ( $stats['this_month'] ?? 0 ) ); ?></p>
		</div>
		<div class="aiba-card">
			<h3><?php esc_html_e( 'Pending in queue', 'ai-blog-automator' ); ?></h3>
			<p class="aiba-stat"><?php echo esc_html( (string) ( $stats['pending'] ?? 0 ) ); ?></p>
		</div>
		<div class="aiba-card">
			<h3><?php esc_html_e( 'Failed jobs', 'ai-blog-automator' ); ?></h3>
			<p class="aiba-stat"><?php echo esc_html( (string) ( $stats['failed'] ?? 0 ) ); ?></p>
		</div>
		<div class="aiba-card">
			<h3><?php esc_html_e( 'Avg. SEO score (est.)', 'ai-blog-automator' ); ?></h3>
			<p class="aiba-stat"><?php echo esc_html( (string) ( $stats['avg_seo'] ?? 0 ) ); ?></p>
		</div>
	</div>

	<p class="aiba-actions">
		<button type="button" class="button button-primary" id="aiba-dash-generate"><?php esc_html_e( 'Generate Post Now', 'ai-blog-automator' ); ?></button>
		<button type="button" class="button" id="aiba-dash-trends"><?php esc_html_e( 'Fetch Trends Now', 'ai-blog-automator' ); ?></button>
		<button type="button" class="button" id="aiba-dash-queue"><?php esc_html_e( 'Process Queue Now', 'ai-blog-automator' ); ?></button>
	</p>
	<p class="aiba-inline-msg" id="aiba-dash-msg" aria-live="polite"></p>

	<h2><?php esc_html_e( 'Recent activity', 'ai-blog-automator' ); ?></h2>
	<table class="widefat striped aiba-table">
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
				<tr><td colspan="4"><?php esc_html_e( 'No log entries yet.', 'ai-blog-automator' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $logs as $row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $row->created_at ); ?></td>
						<td><?php echo esc_html( (string) $row->action ); ?></td>
						<td><span class="aiba-badge aiba-badge-<?php echo esc_attr( (string) $row->status ); ?>"><?php echo esc_html( (string) $row->status ); ?></span></td>
						<td><?php echo esc_html( wp_trim_words( (string) $row->message, 24 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Queue preview', 'ai-blog-automator' ); ?></h2>
	<table class="widefat striped aiba-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Topic', 'ai-blog-automator' ); ?></th>
				<th><?php esc_html_e( 'Keyword', 'ai-blog-automator' ); ?></th>
				<th><?php esc_html_e( 'Status', 'ai-blog-automator' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $queue ) ) : ?>
				<tr><td colspan="3"><?php esc_html_e( 'Queue is empty.', 'ai-blog-automator' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $queue as $row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $row->topic ); ?></td>
						<td><?php echo esc_html( (string) $row->keyword ); ?></td>
						<td><?php echo esc_html( (string) $row->status ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<?php
/**
 * Feedback form for administrators.
 *
 * @package AI_Blog_Automator
 *
 * @var array<int, array<string, mixed>> $aiba_feedback_inbox Recent entries (newest first).
 */

defined( 'ABSPATH' ) || exit;

require AIBA_PLUGIN_DIR . 'templates/partials/shell-start.php';
?>

	<?php if ( isset( $_GET['aiba_feedback_sent'] ) && '1' === (string) wp_unslash( $_GET['aiba_feedback_sent'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Thank you. Your feedback was saved and a copy was emailed to the site administrator.', 'ai-blog-automator' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['aiba_feedback_err'] ) && '1' === (string) wp_unslash( $_GET['aiba_feedback_err'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Please enter a longer message (at least 10 characters) and try again.', 'ai-blog-automator' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['aiba_feedback_rl'] ) && '1' === (string) wp_unslash( $_GET['aiba_feedback_rl'] ) ) : ?>
		<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'You have reached the hourly feedback submission limit for your account. Please try again later.', 'ai-blog-automator' ); ?></p></div>
	<?php endif; ?>

	<div class="aiba-card">
		<h2 class="aiba-section-title"><?php esc_html_e( 'Send feedback', 'ai-blog-automator' ); ?></h2>
		<p class="aiba-feedback-intro">
			<?php esc_html_e( 'Bug reports, feature ideas, and UX notes go directly to the product team. Submissions are stored on this site and emailed to the admin address.', 'ai-blog-automator' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aiba-feedback-form">
			<?php wp_nonce_field( 'aiba_feedback_submit' ); ?>
			<input type="hidden" name="action" value="aiba_feedback_submit" />
			<p>
				<label for="aiba_feedback_topic"><strong><?php esc_html_e( 'Topic', 'ai-blog-automator' ); ?></strong></label><br />
				<select name="aiba_feedback_topic" id="aiba_feedback_topic" class="regular-text">
					<option value="bug"><?php esc_html_e( 'Bug / something broken', 'ai-blog-automator' ); ?></option>
					<option value="feature"><?php esc_html_e( 'Feature request', 'ai-blog-automator' ); ?></option>
					<option value="ux"><?php esc_html_e( 'Admin UX or copy', 'ai-blog-automator' ); ?></option>
					<option value="seo"><?php esc_html_e( 'SEO / content quality', 'ai-blog-automator' ); ?></option>
					<option value="other"><?php esc_html_e( 'Other', 'ai-blog-automator' ); ?></option>
				</select>
			</p>
			<p>
				<label for="aiba_feedback_name"><strong><?php esc_html_e( 'Your name (optional)', 'ai-blog-automator' ); ?></strong></label><br />
				<input type="text" name="aiba_feedback_name" id="aiba_feedback_name" class="regular-text" maxlength="120" autocomplete="name" />
			</p>
			<p>
				<label for="aiba_feedback_email"><strong><?php esc_html_e( 'Reply-to email (optional)', 'ai-blog-automator' ); ?></strong></label><br />
				<input type="email" name="aiba_feedback_email" id="aiba_feedback_email" class="regular-text" maxlength="190" autocomplete="email" />
			</p>
			<p>
				<label for="aiba_feedback_message"><strong><?php esc_html_e( 'Message', 'ai-blog-automator' ); ?></strong></label><br />
				<textarea name="aiba_feedback_message" id="aiba_feedback_message" rows="8" class="large-text" required minlength="10" maxlength="8000" placeholder="<?php esc_attr_e( 'Describe steps to reproduce, what you expected, and your WordPress / PHP version if relevant.', 'ai-blog-automator' ); ?>"></textarea>
			</p>
			<p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Submit feedback', 'ai-blog-automator' ); ?></button>
			</p>
		</form>
	</div>

	<?php if ( ! empty( $aiba_feedback_inbox ) && is_array( $aiba_feedback_inbox ) ) : ?>
		<div class="aiba-card aiba-feedback-history">
			<h3 class="aiba-card-title"><?php esc_html_e( 'Recent submissions (this site)', 'ai-blog-automator' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Only administrators can see this list.', 'ai-blog-automator' ); ?></p>
			<ul class="aiba-feedback-log">
				<?php foreach ( $aiba_feedback_inbox as $entry ) : ?>
					<?php
					if ( ! is_array( $entry ) ) {
						continue;
					}
					$t      = isset( $entry['t'] ) ? (int) $entry['t'] : 0;
					$topic  = isset( $entry['topic'] ) ? sanitize_key( (string) $entry['topic'] ) : '';
					$msg    = isset( $entry['message'] ) ? (string) $entry['message'] : '';
					$who    = isset( $entry['name'] ) ? (string) $entry['name'] : '';
					$when   = $t > 0 ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $t ) : '';
					$preview = wp_trim_words( $msg, 42, '…' );
					?>
					<li class="aiba-feedback-log__item">
						<span class="aiba-feedback-log__meta">
							<strong><?php echo esc_html( $when ); ?></strong>
							<?php if ( $topic !== '' ) : ?>
								<span class="aiba-feedback-log__topic"><?php echo esc_html( $topic ); ?></span>
							<?php endif; ?>
							<?php if ( $who !== '' ) : ?>
								<span class="aiba-feedback-log__who"><?php echo esc_html( $who ); ?></span>
							<?php endif; ?>
						</span>
						<div class="aiba-feedback-log__msg"><?php echo esc_html( $preview ); ?></div>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

<?php
require AIBA_PLUGIN_DIR . 'templates/partials/shell-end.php';

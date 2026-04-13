<?php
/**
 * Public marketing shortcode for product / demo pages.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers front-end shortcodes (e.g. for https://devigontech.com/ai-blog-automator).
 */
class AIBA_Frontend_Demo {

	public static function init(): void {
		add_shortcode( 'aiba_product_demo', array( __CLASS__, 'shortcode_product_demo' ) );
	}

	/**
	 * Marketing block: features, practical walkthrough, checklist, optional wp-admin links.
	 *
	 * Attributes:
	 * - title (string)
	 * - show_shortcode_hint (yes|no, default yes)
	 * - show_walkthrough (yes|no, default yes) — step-by-step how you manage the plugin
	 * - show_checklist (yes|no, default yes) — persistent checklist (localStorage in browser)
	 * - show_admin_links (yes|no, default yes) — real Dashboard links for logged-in administrators
	 * - show_sandbox (yes|no, default yes) — fake “generate” animation (no API, no post; browser only)
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public static function shortcode_product_demo( $atts ): string {
		$atts = shortcode_atts(
			array(
				'title'               => __( 'AI Blog Automator for WordPress', 'ai-blog-automator' ),
				'show_shortcode_hint' => 'yes',
				'show_walkthrough'    => 'yes',
				'show_checklist'      => 'yes',
				'show_admin_links'    => 'yes',
				'show_sandbox'        => 'yes',
			),
			is_array( $atts ) ? $atts : array(),
			'aiba_product_demo'
		);

		wp_enqueue_style(
			'aiba-public-demo',
			AIBA_PLUGIN_URL . 'assets/css/public-demo.css',
			array(),
			AIBA_VERSION
		);

		$walk    = strtolower( (string) $atts['show_walkthrough'] ) !== 'no';
		$list    = strtolower( (string) $atts['show_checklist'] ) !== 'no';
		$sandbox = strtolower( (string) $atts['show_sandbox'] ) !== 'no';
		if ( $list || $sandbox ) {
			wp_enqueue_script(
				'aiba-public-demo',
				AIBA_PLUGIN_URL . 'assets/js/public-demo.js',
				array(),
				AIBA_VERSION,
				true
			);
			wp_localize_script(
				'aiba-public-demo',
				'aibaPublicDemo',
				array(
					'generateUrl' => ( is_user_logged_in() && current_user_can( 'manage_options' ) )
						? admin_url( 'admin.php?page=aiba-generate' )
						: '',
					'i18n'        => array(
						'sandboxErrRequired' => __( 'Enter a topic and a primary keyword to run the demo.', 'ai-blog-automator' ),
						'sandboxErrGeneric'  => __( 'Something went wrong. Please try again.', 'ai-blog-automator' ),
						'sandboxRunning'     => __( 'Running simulation…', 'ai-blog-automator' ),
						'sandboxDoneTitle'   => __( 'Demo run complete', 'ai-blog-automator' ),
						'sandboxDoneBody'    => __( 'No API was called and no post was created. This page is only showing what the timing feels like after you click Generate in wp-admin.', 'ai-blog-automator' ),
						'sandboxOpenReal'    => __( 'Open real Generate now', 'ai-blog-automator' ),
						'sandboxRunAgain'    => __( 'Run demo again', 'ai-blog-automator' ),
					),
					'sandboxSteps' => array(
						__( 'Preparing job…', 'ai-blog-automator' ),
						__( 'Building outline & sections…', 'ai-blog-automator' ),
						__( 'Adding SEO, images & internal links…', 'ai-blog-automator' ),
						__( 'Publishing to your site (simulated)…', 'ai-blog-automator' ),
					),
				)
			);
		}

		$product = esc_url( AIBA_Premium::product_url() );
		$title   = esc_html( sanitize_text_field( (string) $atts['title'] ) );
		$show    = strtolower( (string) $atts['show_shortcode_hint'] ) !== 'no';
		$adm     = strtolower( (string) $atts['show_admin_links'] ) !== 'no';

		$free_rows    = AIBA_Premium::product_free_highlights();
		$premium_rows = AIBA_Premium::premium_marketing_points();

		$site_key    = substr( md5( (string) home_url( '/' ) ), 0, 16 );
		$chk_prefix  = wp_unique_id( 'aibachk_' );
		$demo_inst   = wp_unique_id( 'aibademo_' );
		$sb_prefix   = wp_unique_id( 'aibasb_' );

		ob_start();
		?>
		<div class="aiba-public-demo" data-aiba-demo="1" data-site="<?php echo esc_attr( $site_key ); ?>" data-instance="<?php echo esc_attr( $demo_inst ); ?>">
			<div class="aiba-public-demo__hero">
				<h2 class="aiba-public-demo__title"><?php echo $title; ?></h2>
				<p class="aiba-public-demo__lead">
					<?php esc_html_e( 'Generate long-form posts, SEO meta, stock images, internal links, FAQ schema, and optional Google Indexing from your WordPress dashboard.', 'ai-blog-automator' ); ?>
				</p>
				<div class="aiba-public-demo__actions">
					<a class="aiba-public-demo__btn aiba-public-demo__btn--primary" href="<?php echo $product; ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Get Premium / Buy', 'ai-blog-automator' ); ?>
					</a>
					<a class="aiba-public-demo__btn aiba-public-demo__btn--ghost" href="<?php echo $product; ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Learn more', 'ai-blog-automator' ); ?>
					</a>
				</div>
			</div>

			<?php if ( $adm && is_user_logged_in() && current_user_can( 'manage_options' ) ) : ?>
				<?php echo self::render_admin_shortcut_strip(); ?>
			<?php endif; ?>

			<?php if ( $list ) : ?>
				<?php echo self::render_practice_checklist( $chk_prefix ); ?>
			<?php endif; ?>

			<?php if ( $sandbox ) : ?>
				<?php echo self::render_sandbox_simulator( $sb_prefix ); ?>
			<?php endif; ?>

			<?php if ( $walk ) : ?>
				<?php echo self::render_walkthrough_accordion(); ?>
			<?php endif; ?>

			<h3 class="aiba-public-demo__subhead"><?php esc_html_e( 'Included with the plugin', 'ai-blog-automator' ); ?></h3>
			<ul class="aiba-public-demo__grid">
				<?php foreach ( $free_rows as $item ) : ?>
					<li class="aiba-public-demo__card">
						<span class="aiba-public-demo__check" aria-hidden="true">&#10003;</span>
						<span><?php echo esc_html( $item ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<h3 class="aiba-public-demo__subhead aiba-public-demo__subhead--premium"><?php esc_html_e( 'Premium unlocks higher limits', 'ai-blog-automator' ); ?></h3>
			<ul class="aiba-public-demo__grid aiba-public-demo__grid--premium">
				<?php foreach ( $premium_rows as $item ) : ?>
					<li class="aiba-public-demo__card">
						<span class="aiba-public-demo__check" aria-hidden="true">&#10003;</span>
						<span><?php echo esc_html( $item ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $show ) : ?>
				<p class="aiba-public-demo__hint">
					<?php esc_html_e( 'Site owner: add this shortcode to any page or post to show this block:', 'ai-blog-automator' ); ?>
					<code>[aiba_product_demo]</code>
					<?php esc_html_e( 'Optional:', 'ai-blog-automator' ); ?>
					<code>show_walkthrough="no"</code>,
					<code>show_checklist="no"</code>,
					<code>show_admin_links="no"</code>,
					<code>show_sandbox="no"</code>.
				</p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Fake generate UI: timing animation only (no HTTP, no post).
	 *
	 * @param string $field_prefix Unique HTML id prefix for this shortcode instance.
	 * @return string HTML
	 */
	private static function render_sandbox_simulator( string $field_prefix ): string {
		$tid = $field_prefix . 'topic';
		$kid = $field_prefix . 'keyword';

		ob_start();
		?>
		<div class="aiba-demo-sandbox" data-aiba-sandbox="1">
			<h3 class="aiba-demo-sandbox__title"><?php esc_html_e( 'Try the flow (sandbox)', 'ai-blog-automator' ); ?></h3>
			<p class="aiba-demo-sandbox__disclaimer">
				<?php esc_html_e( 'Demonstration only: your text stays in the browser. Nothing is sent to an AI provider and no WordPress post is created.', 'ai-blog-automator' ); ?>
			</p>
			<div class="aiba-demo-sandbox__fields">
				<p class="aiba-demo-sandbox__field">
					<label class="aiba-demo-sandbox__label" for="<?php echo esc_attr( $tid ); ?>"><?php esc_html_e( 'Topic', 'ai-blog-automator' ); ?></label>
					<input type="text" class="aiba-demo-sandbox__input" id="<?php echo esc_attr( $tid ); ?>" name="aiba_demo_topic" autocomplete="off" maxlength="200" placeholder="<?php esc_attr_e( 'e.g. Sustainable packaging for e-commerce', 'ai-blog-automator' ); ?>" />
				</p>
				<p class="aiba-demo-sandbox__field">
					<label class="aiba-demo-sandbox__label" for="<?php echo esc_attr( $kid ); ?>"><?php esc_html_e( 'Primary keyword', 'ai-blog-automator' ); ?></label>
					<input type="text" class="aiba-demo-sandbox__input" id="<?php echo esc_attr( $kid ); ?>" name="aiba_demo_keyword" autocomplete="off" maxlength="120" placeholder="<?php esc_attr_e( 'e.g. eco-friendly shipping boxes', 'ai-blog-automator' ); ?>" />
				</p>
				<p class="aiba-demo-sandbox__field aiba-demo-sandbox__field--inline">
					<label class="aiba-demo-sandbox__fakecheck">
						<input type="checkbox" class="aiba-demo-sandbox__checkbox" disabled />
						<span><?php esc_html_e( 'Publish immediately (visual only in this demo)', 'ai-blog-automator' ); ?></span>
					</label>
				</p>
			</div>
			<p class="aiba-demo-sandbox__actions">
				<button type="button" class="aiba-demo-sandbox__run aiba-public-demo__btn aiba-public-demo__btn--primary">
					<?php esc_html_e( 'Run demo (no API calls)', 'ai-blog-automator' ); ?>
				</button>
			</p>
			<p class="aiba-demo-sandbox__err" hidden role="alert"></p>
			<div class="aiba-demo-sandbox__progress" hidden>
				<div class="aiba-demo-sandbox__spinner" aria-hidden="true"></div>
				<p class="aiba-demo-sandbox__status" aria-live="polite"></p>
				<ol class="aiba-demo-sandbox__steps"></ol>
			</div>
			<div class="aiba-demo-sandbox__result" hidden></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @return string HTML
	 */
	private static function render_admin_shortcut_strip(): string {
		$links = array(
			array(
				'url'   => admin_url( 'admin.php?page=ai-blog-automator' ),
				'label' => __( 'Dashboard', 'ai-blog-automator' ),
			),
			array(
				'url'   => admin_url( 'admin.php?page=aiba-settings' ),
				'label' => __( 'Settings', 'ai-blog-automator' ),
			),
			array(
				'url'   => admin_url( 'admin.php?page=aiba-generate' ),
				'label' => __( 'Generate now', 'ai-blog-automator' ),
			),
			array(
				'url'   => admin_url( 'admin.php?page=aiba-queue' ),
				'label' => __( 'Queue', 'ai-blog-automator' ),
			),
			array(
				'url'   => admin_url( 'admin.php?page=aiba-logs' ),
				'label' => __( 'Activity logs', 'ai-blog-automator' ),
			),
		);

		ob_start();
		?>
		<div class="aiba-public-demo__admin-strip">
			<p class="aiba-public-demo__admin-strip-title"><?php esc_html_e( 'You are logged in as an administrator — open the real screens:', 'ai-blog-automator' ); ?></p>
			<div class="aiba-public-demo__admin-links">
				<?php foreach ( $links as $row ) : ?>
					<a href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['label'] ); ?></a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param string $id_prefix Unique prefix for checkbox ids (multiple shortcodes per page).
	 * @return string HTML
	 */
	private static function render_practice_checklist( string $id_prefix ): string {
		$items = array(
			array(
				'id'    => 'api',
				'label' => __( 'Enter at least one LLM API key under Settings → API and click Save.', 'ai-blog-automator' ),
			),
			array(
				'id'    => 'test',
				'label' => __( 'Run “Test API connections” and confirm providers you use show as working.', 'ai-blog-automator' ),
			),
			array(
				'id'    => 'defaults',
				'label' => __( 'Set defaults: author, categories, tone, word count, and SEO mode on the Settings tabs.', 'ai-blog-automator' ),
			),
			array(
				'id'    => 'gen',
				'label' => __( 'Use Generate now with a real topic and primary keyword; review the draft in the editor.', 'ai-blog-automator' ),
			),
			array(
				'id'    => 'queue',
				'label' => __( 'Add one queue row or a bulk paste batch, then confirm jobs appear on the Queue screen.', 'ai-blog-automator' ),
			),
			array(
				'id'    => 'logs',
				'label' => __( 'Open Activity logs after a run to see provider messages and fix any warnings.', 'ai-blog-automator' ),
			),
		);

		ob_start();
		?>
		<div class="aiba-demo-checklist">
			<h3 class="aiba-demo-checklist__title"><?php esc_html_e( 'Practice checklist', 'ai-blog-automator' ); ?></h3>
			<p class="aiba-demo-checklist__intro">
				<?php esc_html_e( 'Tick steps as you complete them on your site. Progress is saved in this browser only (not on the server).', 'ai-blog-automator' ); ?>
			</p>
			<ul class="aiba-demo-checklist__list">
				<?php foreach ( $items as $row ) : ?>
					<?php
					$cid = $id_prefix . $row['id'];
					?>
					<li class="aiba-demo-checklist__item">
						<input type="checkbox" class="aiba-demo-checklist__input" data-check-id="<?php echo esc_attr( $row['id'] ); ?>" id="<?php echo esc_attr( $cid ); ?>" />
						<label for="<?php echo esc_attr( $cid ); ?>"><?php echo esc_html( $row['label'] ); ?></label>
					</li>
				<?php endforeach; ?>
			</ul>
			<button type="button" class="aiba-demo-checklist__reset"><?php esc_html_e( 'Clear checklist', 'ai-blog-automator' ); ?></button>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @return string HTML
	 */
	private static function render_walkthrough_accordion(): string {
		ob_start();
		?>
		<div class="aiba-demo-flow">
			<h3 class="aiba-demo-flow__head"><?php esc_html_e( 'How you manage it (same as production)', 'ai-blog-automator' ); ?></h3>
			<p class="aiba-demo-flow__sub">
				<?php esc_html_e( 'Expand each section for the exact workflow inside wp-admin. Full detail lives in docs/USER-GUIDE.html in the plugin folder.', 'ai-blog-automator' ); ?>
			</p>

			<details class="aiba-demo-flow__step" open>
				<summary><?php esc_html_e( '1. Connect APIs & test', 'ai-blog-automator' ); ?></summary>
				<div class="aiba-demo-flow__inner">
					<ol>
						<li><?php esc_html_e( 'In the sidebar open AI Automator → Settings.', 'ai-blog-automator' ); ?></li>
						<li><?php esc_html_e( 'Open the API tab. Paste your Gemini key (and optional OpenAI, Anthropic, custom base URL, Pexels, Unsplash, or Indexing JSON).', 'ai-blog-automator' ); ?></li>
						<li><?php esc_html_e( 'Click Save changes, then Test API connections and read any error text.', 'ai-blog-automator' ); ?></li>
					</ol>
					<p class="aiba-demo-flow__note"><?php esc_html_e( 'Tip: use separate keys per site so you can revoke one key without affecting other properties.', 'ai-blog-automator' ); ?></p>
				</div>
			</details>

			<details class="aiba-demo-flow__step">
				<summary><?php esc_html_e( '2. Defaults (content, automation, SEO)', 'ai-blog-automator' ); ?></summary>
				<div class="aiba-demo-flow__inner">
					<ol>
						<li><?php esc_html_e( 'Still under Settings, set default author, categories, language, tone, and target word count.', 'ai-blog-automator' ); ?></li>
						<li><?php esc_html_e( 'Automation tab: publish status, stagger options, and queue timing match how you want posts to go live.', 'ai-blog-automator' ); ?></li>
						<li><?php esc_html_e( 'SEO tab: pick Yoast, Rank Math, AIOSEO, Native, or Auto so meta is written where your theme expects it.', 'ai-blog-automator' ); ?></li>
					</ol>
				</div>
			</details>

			<details class="aiba-demo-flow__step">
				<summary><?php esc_html_e( '3. Generate your first article', 'ai-blog-automator' ); ?></summary>
				<div class="aiba-demo-flow__inner">
					<ol>
						<li><?php esc_html_e( 'Go to AI Automator → Generate now.', 'ai-blog-automator' ); ?></li>
						<li><?php esc_html_e( 'Enter topic, primary keyword, optional secondary keywords, categories, and article template.', 'ai-blog-automator' ); ?></li>
						<li><?php esc_html_e( 'Choose draft or publish now, submit, wait for the spinner, then use the link to edit the post.', 'ai-blog-automator' ); ?></li>
					</ol>
					<p class="aiba-demo-flow__note"><?php esc_html_e( 'If generation fails, the same screen shows recent log lines; Activity logs has the full history.', 'ai-blog-automator' ); ?></p>
				</div>
			</details>

			<details class="aiba-demo-flow__step">
				<summary><?php esc_html_e( '4. Queue many posts (bulk & schedule)', 'ai-blog-automator' ); ?></summary>
				<div class="aiba-demo-flow__inner">
					<ol>
						<li><?php esc_html_e( 'Open AI Automator → Queue.', 'ai-blog-automator' ); ?></li>
						<li><?php esc_html_e( 'Add single jobs, or use bulk paste: one line per article with columns for title, focus keyphrase, extras, category IDs, and template (see USER-GUIDE for exact formats).', 'ai-blog-automator' ); ?></li>
						<li><?php esc_html_e( 'Use stagger / schedule options so WordPress cron processes jobs over days instead of all at once.', 'ai-blog-automator' ); ?></li>
					</ol>
				</div>
			</details>

			<details class="aiba-demo-flow__step">
				<summary><?php esc_html_e( '5. Monitor, polish, get help', 'ai-blog-automator' ); ?></summary>
				<div class="aiba-demo-flow__inner">
					<ol>
						<li><?php esc_html_e( 'Dashboard summarizes queue health; Activity logs lists every generate/publish/image step.', 'ai-blog-automator' ); ?></li>
						<li><?php esc_html_e( 'Edit generated posts like any other post: headings, links, and SEO plugins remain under your control.', 'ai-blog-automator' ); ?></li>
						<li><?php esc_html_e( 'Upgrade explains premium limits; Feedback stores notes for the author team.', 'ai-blog-automator' ); ?></li>
					</ol>
				</div>
			</details>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

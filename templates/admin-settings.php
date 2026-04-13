<?php
/**
 * Settings tabs template.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$users      = get_users( array( 'who' => 'authors', 'orderby' => 'display_name' ) );
$categories = get_categories( array( 'hide_empty' => false ) );
$types      = get_post_types( array( 'public' => true ), 'objects' );
$detected = AIBA_SEO_Handler::detect_seo_plugin();
require AIBA_PLUGIN_DIR . 'templates/partials/shell-start.php';
?>

	<div class="aiba-card aiba-card-premium-access">
		<?php if ( AIBA_Premium::is_active() ) : ?>
			<div class="aiba-premium-active-banner">
				<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<div>
					<strong><?php esc_html_e( 'Premium is active', 'ai-blog-automator' ); ?></strong>
					<p class="description"><?php esc_html_e( 'Higher target word counts, more internal links and images, and extra queue retries are applied automatically.', 'ai-blog-automator' ); ?></p>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aiba-inline-form">
					<?php wp_nonce_field( 'aiba_premium_unlock' ); ?>
					<input type="hidden" name="action" value="aiba_premium_unlock" />
					<input type="hidden" name="aiba_premium_action" value="revoke" />
					<button type="submit" class="button"><?php esc_html_e( 'Turn off premium', 'ai-blog-automator' ); ?></button>
				</form>
			</div>
		<?php else : ?>
			<h3 class="aiba-card-title"><?php esc_html_e( 'Unlock premium (one-time code)', 'ai-blog-automator' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Enter your access code to enable boosted limits across the plugin.', 'ai-blog-automator' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aiba-premium-unlock-form">
				<?php wp_nonce_field( 'aiba_premium_unlock' ); ?>
				<input type="hidden" name="action" value="aiba_premium_unlock" />
				<input type="hidden" name="aiba_premium_action" value="unlock" />
				<input type="password" name="aiba_premium_code" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'Access code', 'ai-blog-automator' ); ?>" />
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Unlock premium', 'ai-blog-automator' ); ?></button>
			</form>
		<?php endif; ?>
	</div>

	<div class="nav-tab-wrapper aiba-settings-nav" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'ai-blog-automator' ); ?>">
		<a href="#aiba-tab-api" id="aiba-tab-link-api" class="nav-tab nav-tab-active" role="tab" aria-selected="true" aria-controls="aiba-tab-api"><?php esc_html_e( 'API', 'ai-blog-automator' ); ?></a>
		<a href="#aiba-tab-content" id="aiba-tab-link-content" class="nav-tab" role="tab" aria-selected="false" aria-controls="aiba-tab-content"><?php esc_html_e( 'Content', 'ai-blog-automator' ); ?></a>
		<a href="#aiba-tab-prompts" id="aiba-tab-link-prompts" class="nav-tab" role="tab" aria-selected="false" aria-controls="aiba-tab-prompts"><?php esc_html_e( 'Prompts & formats', 'ai-blog-automator' ); ?></a>
		<a href="#aiba-tab-auto" id="aiba-tab-link-auto" class="nav-tab" role="tab" aria-selected="false" aria-controls="aiba-tab-auto"><?php esc_html_e( 'Automation', 'ai-blog-automator' ); ?></a>
		<a href="#aiba-tab-seo" id="aiba-tab-link-seo" class="nav-tab" role="tab" aria-selected="false" aria-controls="aiba-tab-seo"><?php esc_html_e( 'SEO', 'ai-blog-automator' ); ?></a>
		<a href="#aiba-tab-adv" id="aiba-tab-link-adv" class="nav-tab" role="tab" aria-selected="false" aria-controls="aiba-tab-adv"><?php esc_html_e( 'Advanced', 'ai-blog-automator' ); ?></a>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( AIBA_Admin_UI::option_group_name() ); ?>

		<div id="aiba-tab-api" class="aiba-tab-panel" role="tabpanel" aria-labelledby="aiba-tab-link-api">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="aiba_gemini_api_key"><?php esc_html_e( 'Gemini API key', 'ai-blog-automator' ); ?></label></th>
					<td><input type="password" class="large-text" id="aiba_gemini_api_key" name="aiba_gemini_api_key" value="<?php echo esc_attr( (string) get_option( 'aiba_gemini_api_key', '' ) ); ?>" autocomplete="off" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'LLM provider', 'ai-blog-automator' ); ?></th>
					<td>
						<select name="aiba_llm_provider" id="aiba_llm_provider">
							<?php
							$lp = (string) get_option( 'aiba_llm_provider', 'auto' );
							$choices = array(
								'auto'   => __( 'Auto — Gemini → OpenAI → Claude → custom URL (by configured keys)', 'ai-blog-automator' ),
								'gemini' => __( 'Gemini only', 'ai-blog-automator' ),
								'openai' => __( 'OpenAI only (GPT-4 class models)', 'ai-blog-automator' ),
								'claude' => __( 'Claude (Anthropic) only', 'ai-blog-automator' ),
								'custom' => __( 'Custom OpenAI-compatible endpoint only', 'ai-blog-automator' ),
							);
							foreach ( $choices as $k => $lab ) {
								printf( '<option %s value="%s">%s</option>', selected( $lp, $k, false ), esc_attr( $k ), esc_html( $lab ) );
							}
							?>
						</select>
						<p class="description"><?php esc_html_e( 'Auto tries each provider in order when the previous hits a rate limit. Configure optional keys below for fallbacks.', 'ai-blog-automator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_openai_api_key"><?php esc_html_e( 'OpenAI API key (optional fallback)', 'ai-blog-automator' ); ?></label></th>
					<td>
						<input type="password" class="large-text" id="aiba_openai_api_key" name="aiba_openai_api_key" value="<?php echo esc_attr( (string) get_option( 'aiba_openai_api_key', '' ) ); ?>" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'Create a secret key at platform.openai.com. Never commit keys to version control.', 'ai-blog-automator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_openai_model"><?php esc_html_e( 'OpenAI model', 'ai-blog-automator' ); ?></label></th>
					<td>
						<select name="aiba_openai_model" id="aiba_openai_model">
							<?php
							$om  = (string) get_option( 'aiba_openai_model', 'gpt-4o-mini' );
							$oms = array( 'gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo' );
							foreach ( $oms as $m ) {
								printf( '<option %s value="%s">%s</option>', selected( $om, $m, false ), esc_attr( $m ), esc_html( $m ) );
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_anthropic_api_key"><?php esc_html_e( 'Anthropic API key (Claude)', 'ai-blog-automator' ); ?></label></th>
					<td>
						<input type="password" class="large-text" id="aiba_anthropic_api_key" name="aiba_anthropic_api_key" value="<?php echo esc_attr( (string) get_option( 'aiba_anthropic_api_key', '' ) ); ?>" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'Optional. Used when provider is Claude or as an Auto fallback.', 'ai-blog-automator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_anthropic_model"><?php esc_html_e( 'Claude model id', 'ai-blog-automator' ); ?></label></th>
					<td><input type="text" class="large-text" id="aiba_anthropic_model" name="aiba_anthropic_model" value="<?php echo esc_attr( (string) get_option( 'aiba_anthropic_model', 'claude-sonnet-4-20250514' ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_custom_llm_url"><?php esc_html_e( 'Custom LLM URL', 'ai-blog-automator' ); ?></label></th>
					<td>
						<input type="url" class="large-text" id="aiba_custom_llm_url" name="aiba_custom_llm_url" value="<?php echo esc_attr( (string) get_option( 'aiba_custom_llm_url', '' ) ); ?>" placeholder="https://api.example.com/v1/chat/completions" />
						<p class="description"><?php esc_html_e( 'OpenAI-style POST JSON: model, messages, max_tokens. Optional API key below.', 'ai-blog-automator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_custom_llm_model"><?php esc_html_e( 'Custom LLM model name', 'ai-blog-automator' ); ?></label></th>
					<td><input type="text" class="large-text" id="aiba_custom_llm_model" name="aiba_custom_llm_model" value="<?php echo esc_attr( (string) get_option( 'aiba_custom_llm_model', 'default' ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_custom_llm_api_key"><?php esc_html_e( 'Custom LLM API key', 'ai-blog-automator' ); ?></label></th>
					<td><input type="password" class="large-text" id="aiba_custom_llm_api_key" name="aiba_custom_llm_api_key" value="<?php echo esc_attr( (string) get_option( 'aiba_custom_llm_api_key', '' ) ); ?>" autocomplete="off" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_custom_llm_auth_header"><?php esc_html_e( 'Custom auth header name', 'ai-blog-automator' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="aiba_custom_llm_auth_header" name="aiba_custom_llm_auth_header" value="<?php echo esc_attr( (string) get_option( 'aiba_custom_llm_auth_header', 'Authorization' ) ); ?>" />
						<p class="description"><?php esc_html_e( 'Default Authorization sends “Bearer {key}” when the key has no spaces; otherwise the raw key is sent in this header.', 'ai-blog-automator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_pexels_api_key"><?php esc_html_e( 'Pexels API key (optional)', 'ai-blog-automator' ); ?></label></th>
					<td><input type="password" class="large-text" id="aiba_pexels_api_key" name="aiba_pexels_api_key" value="<?php echo esc_attr( (string) get_option( 'aiba_pexels_api_key', '' ) ); ?>" autocomplete="off" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_google_credentials"><?php esc_html_e( 'Google service account JSON', 'ai-blog-automator' ); ?></label></th>
					<td><textarea class="large-text code" rows="6" id="aiba_google_credentials" name="aiba_google_credentials"><?php echo esc_textarea( (string) get_option( 'aiba_google_credentials', '' ) ); ?></textarea></td>
				</tr>
			</table>
			<p><button type="button" class="button" id="aiba-test-apis"><?php esc_html_e( 'Test API connections', 'ai-blog-automator' ); ?></button> <span id="aiba-test-result"></span></p>
		</div>

		<div id="aiba-tab-content" class="aiba-tab-panel" role="tabpanel" aria-labelledby="aiba-tab-link-content" hidden>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="aiba_site_niche"><?php esc_html_e( 'Site niche / topic', 'ai-blog-automator' ); ?></label></th>
					<td><input type="text" class="large-text" id="aiba_site_niche" name="aiba_site_niche" value="<?php echo esc_attr( (string) get_option( 'aiba_site_niche', '' ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_word_count"><?php esc_html_e( 'Default word count', 'ai-blog-automator' ); ?></label></th>
					<td>
						<?php $wc = (int) get_option( 'aiba_word_count', 1500 ); ?>
						<input type="range" id="aiba_word_count_slider" min="300" max="5000" step="50" value="<?php echo esc_attr( (string) max( 300, min( 5000, $wc ) ) ); ?>" aria-hidden="true" />
						<input type="number" min="300" max="5000" id="aiba_word_count" name="aiba_word_count" value="<?php echo esc_attr( (string) max( 300, min( 5000, $wc ) ) ); ?>" />
						<p class="description"><?php esc_html_e( 'Target length for queue and defaults (300–5000). Premium adds extra headroom.', 'ai-blog-automator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Writing tone', 'ai-blog-automator' ); ?></th>
					<td>
						<select name="aiba_tone">
							<?php
							$tones = array( 'Professional', 'Friendly', 'Authoritative', 'Conversational', 'Witty' );
							$cur   = (string) get_option( 'aiba_tone', 'Professional' );
							foreach ( $tones as $t ) {
								printf( '<option %s value="%s">%s</option>', selected( $cur, $t, false ), esc_attr( $t ), esc_html( $t ) );
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Language', 'ai-blog-automator' ); ?></th>
					<td>
						<select name="aiba_language">
							<?php
							$langs = array( 'English', 'Spanish', 'French', 'German', 'Italian', 'Portuguese' );
							$curl  = (string) get_option( 'aiba_language', 'English' );
							foreach ( $langs as $l ) {
								printf( '<option %s value="%s">%s</option>', selected( $curl, $l, false ), esc_attr( $l ), esc_html( $l ) );
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default author', 'ai-blog-automator' ); ?></th>
					<td>
						<select name="aiba_author_id">
							<?php foreach ( $users as $u ) : ?>
								<option value="<?php echo (int) $u->ID; ?>" <?php selected( (int) get_option( 'aiba_author_id' ), (int) $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default categories', 'ai-blog-automator' ); ?></th>
					<td>
						<?php
						$sel = get_option( 'aiba_category_ids', array() );
						$sel = is_array( $sel ) ? array_map( 'intval', $sel ) : array();
						if ( empty( $sel ) ) {
							$one = (int) get_option( 'aiba_category_id', 0 );
							if ( $one ) {
								$sel = array( $one );
							}
						}
						?>
						<select name="aiba_category_ids[]" multiple size="8" style="min-width:280px">
							<?php foreach ( $categories as $c ) : ?>
								<option value="<?php echo (int) $c->term_id; ?>" <?php selected( in_array( (int) $c->term_id, $sel, true ), true ); ?>><?php echo esc_html( $c->name ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Posts can be assigned to multiple categories. Hold Ctrl/Cmd to select more than one.', 'ai-blog-automator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-generate tags', 'ai-blog-automator' ); ?></th>
					<td>
						<input type="hidden" name="aiba_auto_tags" value="0" />
						<label><input type="checkbox" name="aiba_auto_tags" value="1" <?php checked( '1', (string) get_option( 'aiba_auto_tags', '1' ) ); ?> /> <?php esc_html_e( 'Enabled', 'ai-blog-automator' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'AI tag expansion', 'ai-blog-automator' ); ?></th>
					<td>
						<input type="hidden" name="aiba_ai_tag_expansion" value="0" />
						<label><input type="checkbox" name="aiba_ai_tag_expansion" value="1" <?php checked( '1', (string) get_option( 'aiba_ai_tag_expansion', '0' ) ); ?> /> <?php esc_html_e( 'Ask the model for extra relevant tags (more API calls)', 'ai-blog-automator' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'AI category suggestions', 'ai-blog-automator' ); ?></th>
					<td>
						<input type="hidden" name="aiba_ai_suggest_categories" value="0" />
						<label><input type="checkbox" name="aiba_ai_suggest_categories" value="1" <?php checked( '1', (string) get_option( 'aiba_ai_suggest_categories', '0' ) ); ?> /> <?php esc_html_e( 'Suggest additional categories from your existing list (merged with defaults)', 'ai-blog-automator' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_max_internal_links"><?php esc_html_e( 'Max internal links', 'ai-blog-automator' ); ?></label></th>
					<td><input type="number" min="1" max="20" id="aiba_max_internal_links" name="aiba_max_internal_links" value="<?php echo esc_attr( (string) get_option( 'aiba_max_internal_links', 5 ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_images_per_post"><?php esc_html_e( 'In-content images (suggested)', 'ai-blog-automator' ); ?></label></th>
					<td><input type="number" min="0" max="10" id="aiba_images_per_post" name="aiba_images_per_post" value="<?php echo esc_attr( (string) get_option( 'aiba_images_per_post', 3 ) ); ?>" /></td>
				</tr>
			</table>
		</div>

		<div id="aiba-tab-prompts" class="aiba-tab-panel" role="tabpanel" aria-labelledby="aiba-tab-link-prompts" hidden>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Default article format', 'ai-blog-automator' ); ?></th>
					<td>
						<select name="aiba_article_template">
							<?php
							$cur_tpl = (string) get_option( 'aiba_article_template', 'standard' );
							foreach ( AIBA_LLM_Templates::get_article_formats() as $slug => $label ) {
								printf( '<option %s value="%s">%s</option>', selected( $cur_tpl, $slug, false ), esc_attr( $slug ), esc_html( $label ) );
							}
							?>
						</select>
						<p class="description"><?php esc_html_e( 'How-To, Listicle, Case Study, and 10+ more — shapes outlines and section instructions.', 'ai-blog-automator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_prompt_outline_prefix"><?php esc_html_e( 'Outline prompt — prefix', 'ai-blog-automator' ); ?></label></th>
					<td><textarea class="large-text" rows="3" id="aiba_prompt_outline_prefix" name="aiba_prompt_outline_prefix"><?php echo esc_textarea( (string) get_option( 'aiba_prompt_outline_prefix', '' ) ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_prompt_outline_suffix"><?php esc_html_e( 'Outline prompt — suffix', 'ai-blog-automator' ); ?></label></th>
					<td><textarea class="large-text" rows="3" id="aiba_prompt_outline_suffix" name="aiba_prompt_outline_suffix"><?php echo esc_textarea( (string) get_option( 'aiba_prompt_outline_suffix', '' ) ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_prompt_section_prefix"><?php esc_html_e( 'Section prompt — prefix', 'ai-blog-automator' ); ?></label></th>
					<td><textarea class="large-text" rows="3" id="aiba_prompt_section_prefix" name="aiba_prompt_section_prefix"><?php echo esc_textarea( (string) get_option( 'aiba_prompt_section_prefix', '' ) ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_prompt_section_suffix"><?php esc_html_e( 'Section prompt — suffix', 'ai-blog-automator' ); ?></label></th>
					<td><textarea class="large-text" rows="3" id="aiba_prompt_section_suffix" name="aiba_prompt_section_suffix"><?php echo esc_textarea( (string) get_option( 'aiba_prompt_section_suffix', '' ) ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_prompt_global_append"><?php esc_html_e( 'Append to every LLM request', 'ai-blog-automator' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="4" id="aiba_prompt_global_append" name="aiba_prompt_global_append"><?php echo esc_textarea( (string) get_option( 'aiba_prompt_global_append', '' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Added after outline/section wrappers (e.g. brand voice, banned topics, locale).', 'ai-blog-automator' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div id="aiba-tab-auto" class="aiba-tab-panel" role="tabpanel" aria-labelledby="aiba-tab-link-auto" hidden>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-fetch trends', 'ai-blog-automator' ); ?></th>
					<td>
						<input type="hidden" name="aiba_auto_trends" value="0" />
						<label><input type="checkbox" name="aiba_auto_trends" value="1" <?php checked( '1', (string) get_option( 'aiba_auto_trends', '1' ) ); ?> /> <?php esc_html_e( 'Enabled', 'ai-blog-automator' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-publish posts', 'ai-blog-automator' ); ?></th>
					<td>
						<input type="hidden" name="aiba_auto_publish" value="0" />
						<label><input type="checkbox" name="aiba_auto_publish" value="1" <?php checked( '1', (string) get_option( 'aiba_auto_publish', '0' ) ); ?> /> <?php esc_html_e( 'Enabled (queue / cron)', 'ai-blog-automator' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Publish status', 'ai-blog-automator' ); ?></th>
					<td>
						<select name="aiba_publish_status">
							<?php
							$ps = array( 'draft' => __( 'Draft', 'ai-blog-automator' ), 'publish' => __( 'Published', 'ai-blog-automator' ), 'scheduled' => __( 'Scheduled', 'ai-blog-automator' ) );
							$pv = (string) get_option( 'aiba_publish_status', 'draft' );
							foreach ( $ps as $k => $lab ) {
								printf( '<option %s value="%s">%s</option>', selected( $pv, $k, false ), esc_attr( $k ), esc_html( $lab ) );
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_posts_per_day"><?php esc_html_e( 'Posts per day (trends)', 'ai-blog-automator' ); ?></label></th>
					<td><input type="number" min="1" id="aiba_posts_per_day" name="aiba_posts_per_day" value="<?php echo esc_attr( (string) get_option( 'aiba_posts_per_day', 1 ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_publish_time"><?php esc_html_e( 'Publishing schedule time', 'ai-blog-automator' ); ?></label></th>
					<td><input type="time" id="aiba_publish_time" name="aiba_publish_time" value="<?php echo esc_attr( (string) get_option( 'aiba_publish_time', '09:00' ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto Google indexing', 'ai-blog-automator' ); ?></th>
					<td>
						<input type="hidden" name="aiba_auto_index" value="0" />
						<label><input type="checkbox" name="aiba_auto_index" value="1" <?php checked( '1', (string) get_option( 'aiba_auto_index', '1' ) ); ?> /> <?php esc_html_e( 'Enabled', 'ai-blog-automator' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Queue frequency', 'ai-blog-automator' ); ?></th>
					<td>
						<select name="aiba_queue_frequency" id="aiba_queue_frequency">
							<?php
							$qf = (string) get_option( 'aiba_queue_frequency', 'daily' );
							$freq_labels = array(
								'daily'  => __( 'Daily', 'ai-blog-automator' ),
								'12hr'   => __( 'Every 12 hours', 'ai-blog-automator' ),
								'6hr'    => __( 'Every 6 hours', 'ai-blog-automator' ),
								'3hr'    => __( 'Every 3 hours', 'ai-blog-automator' ),
								'2hr'    => __( 'Every 2 hours', 'ai-blog-automator' ),
								'custom' => __( 'Custom interval (minutes)', 'ai-blog-automator' ),
							);
							foreach ( $freq_labels as $k => $lab ) {
								printf( '<option %s value="%s">%s</option>', selected( $qf, $k, false ), esc_attr( $k ), esc_html( $lab ) );
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_queue_custom_minutes"><?php esc_html_e( 'Custom interval (minutes)', 'ai-blog-automator' ); ?></label></th>
					<td>
						<input type="number" min="30" max="1440" id="aiba_queue_custom_minutes" name="aiba_queue_custom_minutes" value="<?php echo esc_attr( (string) get_option( 'aiba_queue_custom_minutes', 120 ) ); ?>" />
						<p class="description"><?php esc_html_e( 'Used when queue frequency is “Custom”. Minimum 30, maximum 1440 (24 hours).', 'ai-blog-automator' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div id="aiba-tab-seo" class="aiba-tab-panel" role="tabpanel" aria-labelledby="aiba-tab-link-seo" hidden>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Detected SEO plugin', 'ai-blog-automator' ); ?></th>
					<td><code><?php echo esc_html( $detected ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'SEO plugin integration', 'ai-blog-automator' ); ?></th>
					<td>
						<select name="aiba_seo_plugin">
							<?php
							$sp = (string) get_option( 'aiba_seo_plugin', 'auto' );
							$opts = array(
								'auto'     => __( 'Auto-detect', 'ai-blog-automator' ),
								'yoast'    => 'Yoast SEO',
								'rankmath' => 'Rank Math',
								'aioseo'   => 'AIOSEO',
								'native'   => __( 'Native (plugin meta)', 'ai-blog-automator' ),
							);
							foreach ( $opts as $k => $lab ) {
								printf( '<option %s value="%s">%s</option>', selected( $sp, $k, false ), esc_attr( $k ), esc_html( $lab ) );
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'FAQ schema', 'ai-blog-automator' ); ?></th>
					<td>
						<input type="hidden" name="aiba_add_faq_schema" value="0" />
						<label><input type="checkbox" name="aiba_add_faq_schema" value="1" <?php checked( '1', (string) get_option( 'aiba_add_faq_schema', '1' ) ); ?> /> <?php esc_html_e( 'Add FAQ schema', 'ai-blog-automator' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Article schema', 'ai-blog-automator' ); ?></th>
					<td>
						<input type="hidden" name="aiba_add_article_schema" value="0" />
						<label><input type="checkbox" name="aiba_add_article_schema" value="1" <?php checked( '1', (string) get_option( 'aiba_add_article_schema', '1' ) ); ?> /> <?php esc_html_e( 'Add Article schema', 'ai-blog-automator' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Canonical URL', 'ai-blog-automator' ); ?></th>
					<td>
						<input type="hidden" name="aiba_canonical" value="0" />
						<label><input type="checkbox" name="aiba_canonical" value="1" <?php checked( '1', (string) get_option( 'aiba_canonical', '1' ) ); ?> /> <?php esc_html_e( 'Output canonical link', 'ai-blog-automator' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Open Graph tags', 'ai-blog-automator' ); ?></th>
					<td>
						<input type="hidden" name="aiba_og_tags" value="0" />
						<label><input type="checkbox" name="aiba_og_tags" value="1" <?php checked( '1', (string) get_option( 'aiba_og_tags', '1' ) ); ?> /> <?php esc_html_e( 'Output OG tags', 'ai-blog-automator' ); ?></label>
					</td>
				</tr>
			</table>
		</div>

		<div id="aiba-tab-adv" class="aiba-tab-panel" role="tabpanel" aria-labelledby="aiba-tab-link-adv" hidden>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Delete data on uninstall', 'ai-blog-automator' ); ?></th>
					<td>
						<input type="hidden" name="aiba_delete_on_uninstall" value="0" />
						<label><input type="checkbox" name="aiba_delete_on_uninstall" value="1" <?php checked( '1', (string) get_option( 'aiba_delete_on_uninstall', '0' ) ); ?> /> <?php esc_html_e( 'Remove tables, options, and plugin post meta', 'ai-blog-automator' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_max_retries"><?php esc_html_e( 'Max retries on failure', 'ai-blog-automator' ); ?></label></th>
					<td><input type="number" min="0" max="10" id="aiba_max_retries" name="aiba_max_retries" value="<?php echo esc_attr( (string) get_option( 'aiba_max_retries', 3 ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="aiba_log_retention"><?php esc_html_e( 'Log retention (days)', 'ai-blog-automator' ); ?></label></th>
					<td><input type="number" min="1" id="aiba_log_retention" name="aiba_log_retention" value="<?php echo esc_attr( (string) get_option( 'aiba_log_retention', 30 ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Disable on post types', 'ai-blog-automator' ); ?></th>
					<td>
						<select name="aiba_disabled_types[]" multiple size="6" style="min-width:220px">
							<?php
							$dis = get_option( 'aiba_disabled_types', array() );
							$dis = is_array( $dis ) ? $dis : array();
							foreach ( $types as $obj ) {
								echo '<option value="' . esc_attr( $obj->name ) . '"' . selected( in_array( $obj->name, $dis, true ), true, false ) . '>' . esc_html( $obj->label ) . '</option>';
							}
							?>
						</select>
						<p class="description"><?php esc_html_e( 'Holds native SEO meta output on these singular types.', 'ai-blog-automator' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'FAQ block CSS', 'ai-blog-automator' ); ?></th>
					<td>
						<input type="hidden" name="aiba_faq_css" value="0" />
						<label><input type="checkbox" name="aiba_faq_css" value="1" <?php checked( '1', (string) get_option( 'aiba_faq_css', '1' ) ); ?> /> <?php esc_html_e( 'Load minimal styles for .aiba-faq on the front end', 'ai-blog-automator' ); ?></label>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button(); ?>
	</form>
<?php require AIBA_PLUGIN_DIR . 'templates/partials/shell-end.php'; ?>

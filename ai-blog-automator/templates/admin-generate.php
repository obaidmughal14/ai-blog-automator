<?php
/**
 * Manual generation template.
 *
 * @package AI_Blog_Automator
 *
 * @var array<int, array<string, mixed>> $trends
 */

defined( 'ABSPATH' ) || exit;

$categories     = get_categories( array( 'hide_empty' => false ) );
$article_formats = AIBA_LLM_Templates::get_article_formats();
$def_cats       = AIBA_Core::get_default_category_ids();
require AIBA_PLUGIN_DIR . 'templates/partials/shell-start.php';
?>

	<form id="aiba-generate-form" class="aiba-form aiba-form-card">
		<table class="form-table">
			<tr>
				<th scope="row"><label for="aiba_gen_topic"><?php esc_html_e( 'Topic', 'ai-blog-automator' ); ?></label></th>
				<td>
					<input type="text" class="large-text" id="aiba_gen_topic" name="topic" required />
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
				<td><input type="text" class="large-text" id="aiba_gen_primary" name="primary_keyword" required /></td>
			</tr>
			<tr>
				<th scope="row"><label for="aiba_gen_secondary"><?php esc_html_e( 'Secondary keywords', 'ai-blog-automator' ); ?></label></th>
				<td><input type="text" class="large-text" id="aiba_gen_secondary" name="secondary_keywords" placeholder="kw1, kw2, kw3" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="aiba_gen_cats"><?php esc_html_e( 'Categories', 'ai-blog-automator' ); ?></label></th>
				<td>
					<select id="aiba_gen_cats" name="category_ids[]" multiple size="6" style="min-width:260px">
						<?php foreach ( $categories as $cat ) : ?>
							<option value="<?php echo (int) $cat->term_id; ?>" <?php selected( in_array( (int) $cat->term_id, $def_cats, true ), true ); ?>>
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
					<?php $gwc = max( 300, min( 5000, (int) get_option( 'aiba_word_count', 1500 ) ) ); ?>
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
						$cur   = (string) get_option( 'aiba_tone', 'Professional' );
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
					<label><input type="checkbox" id="aiba_gen_publish" name="publish_now" value="1" /> <?php esc_html_e( 'Publish immediately (otherwise uses automation settings / draft)', 'ai-blog-automator' ); ?></label>
				</td>
			</tr>
		</table>
		<p>
			<button type="submit" class="button button-primary button-large" id="aiba_gen_submit"><?php esc_html_e( 'Generate & Publish', 'ai-blog-automator' ); ?></button>
		</p>
	</form>

	<div id="aiba-gen-progress" class="aiba-progress aiba-form-card" hidden>
		<ol>
			<li class="aiba-step" data-step="outline"><?php esc_html_e( 'Outline & sections', 'ai-blog-automator' ); ?></li>
			<li class="aiba-step" data-step="assemble"><?php esc_html_e( 'Assemble & save post', 'ai-blog-automator' ); ?></li>
			<li class="aiba-step" data-step="done"><?php esc_html_e( 'Done', 'ai-blog-automator' ); ?></li>
		</ol>
		<p id="aiba-gen-result"></p>
	</div>
<?php require AIBA_PLUGIN_DIR . 'templates/partials/shell-end.php'; ?>

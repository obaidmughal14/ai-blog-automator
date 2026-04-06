<?php
/**
 * Creates and updates WordPress posts.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post publisher.
 */
class AIBA_Post_Publisher {

	private AIBA_SEO_Handler $seo_handler;
	private AIBA_Image_Handler $image_handler;
	private AIBA_Internal_Linker $internal_linker;
	private AIBA_Google_Indexing $google_indexing;

	public function __construct(
		AIBA_SEO_Handler $seo_handler,
		AIBA_Image_Handler $image_handler,
		AIBA_Internal_Linker $internal_linker,
		AIBA_Google_Indexing $google_indexing
	) {
		$this->seo_handler    = $seo_handler;
		$this->image_handler  = $image_handler;
		$this->internal_linker = $internal_linker;
		$this->google_indexing = $google_indexing;
	}

	/**
	 * Full publish pipeline.
	 *
	 * @param array<string, mixed> $article_data Generated article.
	 * @param array<string, mixed> $settings Overrides for author, category, publish flags.
	 * @return int|WP_Error Post ID.
	 */
	public function publish_post( array $article_data, array $settings = array() ) {
		$author_id   = (int) ( $settings['author_id'] ?? get_option( 'aiba_author_id', get_current_user_id() ) );
		$category_id = (int) ( $settings['category_id'] ?? get_option( 'aiba_category_id', 0 ) );
		$auto_tags   = '1' === (string) get_option( 'aiba_auto_tags', '1' );
		$tags        = ( $auto_tags && ! empty( $article_data['tags'] ) ) ? $article_data['tags'] : array();

		$post_id = wp_insert_post(
			array(
				'post_title'   => wp_strip_all_tags( (string) ( $article_data['title'] ?? '' ) ),
				'post_content' => $article_data['content'],
				'post_status'  => 'draft',
				'post_author'  => $author_id,
				'post_category'=> $category_id ? array( $category_id ) : array(),
				'post_name'    => sanitize_title( (string) ( $article_data['slug'] ?? '' ) ),
				'tags_input'   => $tags,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$topic = sanitize_text_field( (string) ( $settings['topic'] ?? $article_data['title'] ?? '' ) );

		$seo_payload = array(
			'primary_keyword'  => (string) ( $article_data['primary_keyword'] ?? '' ),
			'meta_description' => (string) ( $article_data['meta_description'] ?? '' ),
			'seo_title'        => (string) ( $article_data['seo_title'] ?? $article_data['title'] ?? '' ),
			'content'          => (string) ( $article_data['content'] ?? '' ),
		);

		$this->seo_handler->apply_seo( $post_id, $seo_payload, ! empty( $settings['force_seo'] ) );

		$feature_kw = (string) ( $article_data['primary_keyword'] ?? '' );
		if ( $feature_kw ) {
			$thumb_id = $this->image_handler->get_feature_image( (string) $article_data['title'], $feature_kw );
			if ( $thumb_id ) {
				set_post_thumbnail( $post_id, $thumb_id );
			}
		}

		$suggestions = isset( $article_data['image_suggestions'] ) && is_array( $article_data['image_suggestions'] ) ? $article_data['image_suggestions'] : array();
		$content     = $this->image_handler->replace_image_placeholders( (string) $article_data['content'], $suggestions );

		$content = $this->internal_linker->inject_internal_links(
			$content,
			$post_id,
			(string) ( $article_data['primary_keyword'] ?? '' ),
			$topic
		);

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $content,
			)
		);

		$seo_payload['content'] = $content;
		$this->seo_handler->inject_schema( $post_id, $seo_payload );

		$publish_status = (string) ( $settings['publish_status'] ?? get_option( 'aiba_publish_status', 'draft' ) );
		$auto_publish   = ! empty( $settings['auto_publish'] ) || '1' === (string) get_option( 'aiba_auto_publish', '0' );
		if ( isset( $settings['auto_publish'] ) ) {
			$auto_publish = (bool) $settings['auto_publish'];
		}

		$scheduled = $settings['scheduled_time'] ?? null;

		if ( $auto_publish || 'publish' === $publish_status || ( 'scheduled' === $publish_status && $scheduled ) ) {
			if ( $scheduled ) {
				wp_update_post(
					array(
						'ID'            => $post_id,
						'post_status'   => 'future',
						'post_date'     => $scheduled,
						'post_date_gmt' => get_gmt_from_date( $scheduled ),
					)
				);
			} else {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'publish',
					)
				);
			}
		}

		$post = get_post( $post_id );
		if ( $post && 'publish' === $post->post_status && '1' === (string) get_option( 'aiba_auto_index', '1' ) ) {
			$this->google_indexing->submit_url( get_permalink( $post_id ) );
		}

		AIBA_Core::log( $post_id, 'publish', 'success', 'Post saved: ' . $article_data['title'] );

		return $post_id;
	}
}

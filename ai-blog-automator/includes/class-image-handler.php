<?php
/**
 * Featured and in-post images.
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Image handler.
 */
class AIBA_Image_Handler {

	/**
	 * Download featured image from Unsplash Source or Pexels.
	 *
	 * @param string $topic Topic title.
	 * @param string $keyword Search keyword.
	 * @return int|false Attachment ID.
	 */
	public function get_feature_image( string $topic, string $keyword ) {
		$pexels = (string) get_option( 'aiba_pexels_api_key', '' );
		if ( '' !== $pexels ) {
			$id = $this->get_pexels_image( $keyword, $topic );
			if ( $id ) {
				return $id;
			}
		}

		$query = rawurlencode( sanitize_text_field( $keyword ) );
		$url   = 'https://source.unsplash.com/1200x630/?' . $query;

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			AIBA_Core::log( 0, 'feature_image', 'error', $tmp->get_error_message() );
			return false;
		}

		$file = array(
			'name'     => sanitize_file_name( $topic . '.jpg' ),
			'tmp_name' => $tmp,
		);

		$id = media_handle_sideload( $file, 0, sanitize_text_field( $topic ) );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			AIBA_Core::log( 0, 'feature_image', 'error', $id->get_error_message() );
			return false;
		}

		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $keyword ) );
		return (int) $id;
	}

	/**
	 * Pexels search single landscape image.
	 */
	private function get_pexels_image( string $keyword, string $title ): int|false {
		$key = (string) get_option( 'aiba_pexels_api_key', '' );
		$q   = rawurlencode( sanitize_text_field( $keyword ) );
		$url = 'https://api.pexels.com/v1/search?query=' . $q . '&per_page=1&orientation=landscape';

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => $key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			AIBA_Core::log( 0, 'pexels', 'error', $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$src  = $body['photos'][0]['src']['large2x'] ?? $body['photos'][0]['src']['large'] ?? '';
		if ( ! is_string( $src ) || '' === $src ) {
			return false;
		}

		return $this->sideload_image( $src, $title, $keyword );
	}

	/**
	 * Replace [IMAGE_PLACEHOLDER: ...] tags.
	 *
	 * @param array<int, string> $suggestions Fallback descriptions.
	 */
	public function replace_image_placeholders( string $content, array $suggestions ): string {
		if ( ! preg_match_all( '/\[IMAGE_PLACEHOLDER:\s*([^\]]+)\]/', $content, $matches, PREG_SET_ORDER ) ) {
			return $content;
		}

		$i = 0;
		foreach ( $matches as $m ) {
			$desc = sanitize_text_field( trim( $m[1] ) );
			if ( '' === $desc && isset( $suggestions[ $i ] ) ) {
				$desc = sanitize_text_field( $suggestions[ $i ] );
			}
			++$i;

			$attachment_id = $this->get_relevant_image( $desc );
			if ( ! $attachment_id ) {
				$content = str_replace( $m[0], '', $content );
				continue;
			}

			$url = wp_get_attachment_image_url( $attachment_id, 'large' );
			if ( ! $url ) {
				$content = str_replace( $m[0], '', $content );
				continue;
			}

			$alt  = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			$alt  = $alt ? sanitize_text_field( $alt ) : $desc;
			$cap  = esc_html( $desc );
			$html = sprintf(
				'<figure class="aiba-figure"><img src="%1$s" alt="%2$s" loading="lazy" decoding="async" />%3$s</figure>',
				esc_url( $url ),
				esc_attr( $alt ),
				$cap ? '<figcaption>' . $cap . '</figcaption>' : ''
			);

			$content = str_replace( $m[0], $html, $content );
		}

		return $content;
	}

	/**
	 * Fetch image for description (Unsplash source).
	 */
	public function get_relevant_image( string $description ): int|false {
		$description = sanitize_text_field( $description );
		if ( '' === $description ) {
			return false;
		}

		$pexels = (string) get_option( 'aiba_pexels_api_key', '' );
		if ( '' !== $pexels ) {
			$id = $this->get_pexels_image( $description, $description );
			if ( $id ) {
				return $id;
			}
		}

		$query = rawurlencode( $description );
		$url   = 'https://source.unsplash.com/800x600/?' . $query;
		return $this->sideload_image( $url, $description, $description );
	}

	/**
	 * Sideload remote image into media library.
	 */
	public function sideload_image( string $url, string $title, string $alt ): int|false {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			AIBA_Core::log( 0, 'sideload', 'error', $tmp->get_error_message() );
			return false;
		}

		$file = array(
			'name'     => sanitize_file_name( $title . '.jpg' ),
			'tmp_name' => $tmp,
		);

		$id = media_handle_sideload( $file, 0, sanitize_text_field( $title ) );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			AIBA_Core::log( 0, 'sideload', 'error', $id->get_error_message() );
			return false;
		}

		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		return (int) $id;
	}
}

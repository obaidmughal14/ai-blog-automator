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
	 * Download featured image (Pexels, then Unsplash API). Unsplash Source URLs are retired; use a key from unsplash.com/oauth/applications.
	 *
	 * @param string $topic Topic title.
	 * @param string $keyword Search keyword.
	 * @param int    $parent_post_id Attach to this post (0 = unattached).
	 * @return int|false Attachment ID.
	 */
	public function get_feature_image( string $topic, string $keyword, int $parent_post_id = 0 ) {
		$pexels = (string) get_option( 'aiba_pexels_api_key', '' );
		if ( '' !== $pexels ) {
			$id = $this->get_pexels_image( $keyword, $topic, $parent_post_id );
			if ( $id ) {
				return $id;
			}
		}

		$id = $this->unsplash_attach_photo( $keyword, $topic, $keyword, $parent_post_id, 'landscape' );
		if ( $id ) {
			return $id;
		}

		AIBA_Core::log(
			0,
			'feature_image',
			'error',
			__( 'No image provider succeeded. Add a Pexels API key and/or an Unsplash Access Key under Settings → API.', 'ai-blog-automator' )
		);
		return false;
	}

	/**
	 * Search Unsplash and sideload first result (or random fallback).
	 *
	 * @return int|false Attachment ID.
	 */
	private function unsplash_attach_photo( string $query, string $file_title, string $alt, int $parent_post_id, string $orientation = 'landscape' ): int|false {
		$key = trim( (string) get_option( 'aiba_unsplash_access_key', '' ) );
		if ( '' === $key ) {
			return false;
		}
		$q = sanitize_text_field( $query );
		if ( '' === $q ) {
			return false;
		}
		$orientation = in_array( $orientation, array( 'landscape', 'portrait', 'squarish' ), true ) ? $orientation : 'landscape';
		$headers     = array(
			'Authorization' => 'Client-ID ' . $key,
		);

		$search_url = 'https://api.unsplash.com/search/photos?query=' . rawurlencode( $q ) . '&per_page=1&orientation=' . rawurlencode( $orientation );
		$photo      = $this->unsplash_parse_search_first( $search_url, $headers );

		if ( null === $photo ) {
			$random_url = 'https://api.unsplash.com/photos/random?query=' . rawurlencode( $q ) . '&orientation=' . rawurlencode( $orientation );
			$photo      = $this->unsplash_parse_random( $random_url, $headers );
		}

		if ( null === $photo || empty( $photo['urls'] ) || ! is_array( $photo['urls'] ) ) {
			return false;
		}

		$src = $photo['urls']['regular'] ?? $photo['urls']['full'] ?? $photo['urls']['small'] ?? '';
		if ( ! is_string( $src ) || '' === $src ) {
			return false;
		}

		$id = $this->sideload_image( $src, $file_title, $alt, $parent_post_id );
		if ( ! $id ) {
			return false;
		}

		$uname = sanitize_text_field( (string) ( $photo['user']['name'] ?? '' ) );
		$plink = isset( $photo['links']['html'] ) ? esc_url_raw( (string) $photo['links']['html'] ) : '';

		$credit = $uname !== ''
			? sprintf(
				/* translators: 1: photographer name, 2: photo page URL */
				__( 'Photo by %1$s on Unsplash. %2$s', 'ai-blog-automator' ),
				$uname,
				$plink
			)
			: sprintf(
				/* translators: %s: photo page URL */
				__( 'Photo on Unsplash. %s', 'ai-blog-automator' ),
				$plink
			);

		update_post_meta( $id, '_aiba_image_source', 'unsplash' );
		update_post_meta( $id, '_aiba_image_credit', trim( $credit ) );
		update_post_meta( $id, '_aiba_photographer_name', $uname );
		$user_page = isset( $photo['user']['links']['html'] ) ? esc_url_raw( (string) $photo['user']['links']['html'] ) : '';
		update_post_meta( $id, '_aiba_photographer_url', $user_page );
		update_post_meta( $id, '_aiba_unsplash_photo_page', $plink );
		if ( ! empty( $photo['id'] ) ) {
			update_post_meta( $id, '_aiba_unsplash_photo_id', sanitize_text_field( (string) $photo['id'] ) );
		}
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );

		return (int) $id;
	}

	/**
	 * @param array<string, string> $headers Authorization header.
	 * @return array<string, mixed>|null Photo object.
	 */
	private function unsplash_parse_search_first( string $url, array $headers ): ?array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => $headers,
			)
		);
		if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return null;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['results'][0] ) || ! is_array( $body['results'][0] ) ) {
			return null;
		}
		return $body['results'][0];
	}

	/**
	 * @param array<string, string> $headers Authorization header.
	 * @return array<string, mixed>|null Photo object.
	 */
	private function unsplash_parse_random( string $url, array $headers ): ?array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => $headers,
			)
		);
		if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return null;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : null;
	}

	/**
	 * Pexels search single landscape image.
	 */
	private function get_pexels_image( string $keyword, string $title, int $parent_post_id = 0 ): int|false {
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

		$body  = json_decode( wp_remote_retrieve_body( $response ), true );
		$photo = ( is_array( $body ) && isset( $body['photos'][0] ) && is_array( $body['photos'][0] ) ) ? $body['photos'][0] : null;
		if ( null === $photo ) {
			return false;
		}
		$src = $photo['src']['large2x'] ?? $photo['src']['large'] ?? '';
		if ( ! is_string( $src ) || '' === $src ) {
			return false;
		}

		$id = $this->sideload_image( $src, $title, $keyword, $parent_post_id );
		if ( ! $id ) {
			return false;
		}

		$photographer = sanitize_text_field( (string) ( $photo['photographer'] ?? '' ) );
		$pexels_url   = isset( $photo['url'] ) ? esc_url_raw( (string) $photo['url'] ) : '';
		if ( $photographer !== '' ) {
			$credit = $pexels_url !== ''
				? sprintf(
					/* translators: 1: photographer name, 2: Pexels photo URL */
					__( 'Photo by %1$s on Pexels (%2$s).', 'ai-blog-automator' ),
					$photographer,
					$pexels_url
				)
				: sprintf(
					/* translators: %s: photographer name */
					__( 'Photo by %s on Pexels.', 'ai-blog-automator' ),
					$photographer
				);
		} else {
			$credit = __( 'Photo on Pexels.', 'ai-blog-automator' );
		}
		update_post_meta( $id, '_aiba_image_credit', $credit );
		update_post_meta( $id, '_aiba_image_source', 'pexels' );
		update_post_meta( $id, '_aiba_photographer_name', $photographer );
		$photog_page = isset( $photo['photographer_url'] ) ? esc_url_raw( (string) $photo['photographer_url'] ) : '';
		update_post_meta( $id, '_aiba_photographer_url', $photog_page );
		update_post_meta( $id, '_aiba_pexels_page_url', $pexels_url );
		if ( ! empty( $photo['id'] ) ) {
			update_post_meta( $id, '_aiba_pexels_photo_id', absint( $photo['id'] ) );
		}
		return (int) $id;
	}

	/**
	 * Replace [IMAGE_PLACEHOLDER: ...] tags.
	 *
	 * @param array<int, string> $suggestions Fallback descriptions.
	 * @param string             $primary_keyword Appended to alt text for Rank Math image checks when missing.
	 */
	public function replace_image_placeholders( string $content, array $suggestions, int $parent_post_id = 0, string $primary_keyword = '' ): string {
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

			$attachment_id = $this->get_relevant_image( $desc, $parent_post_id );
			if ( ! $attachment_id ) {
				$content = str_replace( $m[0], '', $content );
				continue;
			}

			$url = wp_get_attachment_image_url( $attachment_id, 'large' );
			if ( ! $url ) {
				$content = str_replace( $m[0], '', $content );
				continue;
			}

			$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			$alt = $alt ? sanitize_text_field( $alt ) : $desc;
			if ( $primary_keyword !== '' && stripos( $alt, $primary_keyword ) === false ) {
				$alt = trim( $alt . ' — ' . $primary_keyword );
				if ( strlen( $alt ) > 125 ) {
					$alt = substr( $alt, 0, 122 ) . '…';
				}
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
			}
			$cap_html = self::build_figcaption_html( $attachment_id );
			if ( '' === $cap_html ) {
				$credit = get_post_meta( $attachment_id, '_aiba_image_credit', true );
				$cap    = ( is_string( $credit ) && $credit !== '' ) ? $credit : $desc;
				$cap_html = $cap !== '' ? wp_kses_post( self::linkify_plain_urls( $cap ) ) : '';
			}
			$cap = '' !== $cap_html ? '<figcaption>' . $cap_html . '</figcaption>' : '';
			$html   = sprintf(
				'<figure class="aiba-figure"><img src="%1$s" alt="%2$s" loading="lazy" decoding="async" />%3$s</figure>',
				esc_url( $url ),
				esc_attr( $alt ),
				$cap
			);

			$content = str_replace( $m[0], $html, $content );
		}

		return $content;
	}

	/**
	 * Fetch image for description (Pexels then Unsplash API).
	 */
	public function get_relevant_image( string $description, int $parent_post_id = 0 ): int|false {
		$description = sanitize_text_field( $description );
		if ( '' === $description ) {
			return false;
		}

		$pexels = (string) get_option( 'aiba_pexels_api_key', '' );
		if ( '' !== $pexels ) {
			$id = $this->get_pexels_image( $description, $description, $parent_post_id );
			if ( $id ) {
				return $id;
			}
		}

		return $this->unsplash_attach_photo( $description, $description, $description, $parent_post_id, 'landscape' );
	}

	/**
	 * Sideload remote image into media library.
	 */
	public function sideload_image( string $url, string $title, string $alt, int $parent_post_id = 0 ): int|false {
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

		$id = media_handle_sideload( $file, $parent_post_id, sanitize_text_field( $title ) );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			AIBA_Core::log( 0, 'sideload', 'error', $id->get_error_message() );
			return false;
		}

		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		return (int) $id;
	}

	/**
	 * Figcaption HTML with photographer and stock site as clickable links.
	 */
	public static function build_figcaption_html( int $attachment_id ): string {
		$source = (string) get_post_meta( $attachment_id, '_aiba_image_source', true );
		$name   = (string) get_post_meta( $attachment_id, '_aiba_photographer_name', true );
		$purl   = (string) get_post_meta( $attachment_id, '_aiba_photographer_url', true );
		if ( 'pexels' === $source ) {
			$page = (string) get_post_meta( $attachment_id, '_aiba_pexels_page_url', true );
			$bits = array();
			if ( $name !== '' && $purl !== '' ) {
				$bits[] = sprintf(
					'Photo by <a href="%s" rel="noopener noreferrer" target="_blank">%s</a>',
					esc_url( $purl ),
					esc_html( $name )
				);
			} elseif ( $name !== '' ) {
				$bits[] = sprintf( 'Photo by %s', esc_html( $name ) );
			}
			if ( $page !== '' ) {
				$bits[] = sprintf(
					'on <a href="%s" rel="noopener noreferrer" target="_blank">Pexels</a>',
					esc_url( $page )
				);
			}
			return implode( ' ', array_filter( $bits ) );
		}
		if ( 'unsplash' === $source ) {
			$page = (string) get_post_meta( $attachment_id, '_aiba_unsplash_photo_page', true );
			$bits = array();
			if ( $name !== '' && $purl !== '' ) {
				$bits[] = sprintf(
					'Photo by <a href="%s" rel="noopener noreferrer" target="_blank">%s</a>',
					esc_url( $purl ),
					esc_html( $name )
				);
			} elseif ( $name !== '' ) {
				$bits[] = sprintf( 'Photo by %s', esc_html( $name ) );
			}
			if ( $page !== '' ) {
				$bits[] = sprintf(
					'on <a href="%s" rel="noopener noreferrer" target="_blank">Unsplash</a>',
					esc_url( $page )
				);
			}
			return implode( ' ', array_filter( $bits ) );
		}
		return '';
	}

	/**
	 * Legacy plain-text credits: turn http(s) URLs into anchor tags.
	 */
	private static function linkify_plain_urls( string $text ): string {
		$parts = preg_split( '#(https?://[^\s<]+)#i', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out   = '';
		foreach ( $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( preg_match( '#^https?://#i', $part ) ) {
				$out .= '<a href="' . esc_url( $part ) . '" rel="noopener noreferrer" target="_blank">' . esc_html( $part ) . '</a>';
			} else {
				$out .= esc_html( $part );
			}
		}
		return $out;
	}
}

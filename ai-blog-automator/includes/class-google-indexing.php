<?php
/**
 * Google Indexing API client (service account JWT).
 *
 * @package AI_Blog_Automator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Google Indexing API (singleton — single transition hook).
 */
class AIBA_Google_Indexing {

	private static ?self $instance = null;

	/**
	 * Shared instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			add_action( 'transition_post_status', array( self::$instance, 'maybe_index_on_publish' ), 10, 3 );
		}
		return self::$instance;
	}

	private function __construct() {
	}

	/**
	 * Submit URL when a post becomes published.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param WP_Post  $post Post object.
	 */
	public function maybe_index_on_publish( string $new_status, string $old_status, $post ): void {
		if ( 'publish' !== $new_status || ! ( $post instanceof WP_Post ) ) {
			return;
		}
		if ( 'post' !== $post->post_type ) {
			return;
		}
		if ( '1' !== (string) get_option( 'aiba_auto_index', '1' ) ) {
			return;
		}
		if ( 'publish' === $old_status ) {
			return;
		}
		$this->submit_url( get_permalink( $post ) );
	}

	/**
	 * Submit URL to Indexing API.
	 *
	 * @param string $url Full URL.
	 * @param string $type URL_UPDATED or URL_DELETED.
	 */
	public function submit_url( string $url, string $type = 'URL_UPDATED' ): bool {
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return false;
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			AIBA_Core::log( 0, 'google_index', 'error', $token->get_error_message() );
			return false;
		}

		$endpoint = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'url'  => $url,
						'type' => $type,
					)
				),
				'timeout' => 30,
			)
		);

		$code = wp_remote_retrieve_response_code( $response );
		$ok   = ( 200 === $code );
		AIBA_Core::log( 0, 'google_index', $ok ? 'success' : 'error', $url . ' — HTTP ' . $code );
		return $ok;
	}

	/**
	 * Verify service account by fetching a fresh access token.
	 */
	public function verify_credentials_fresh(): bool {
		delete_transient( 'aiba_google_token' );
		$t = $this->get_access_token();
		return ! is_wp_error( $t );
	}

	/**
	 * Obtain OAuth access token using service account JWT.
	 *
	 * @return string|WP_Error
	 */
	private function get_access_token() {
		$cached = get_transient( 'aiba_google_token' );
		if ( is_string( $cached ) && $cached !== '' ) {
			return $cached;
		}

		$creds_raw = (string) get_option( 'aiba_google_credentials', '' );
		if ( '' === $creds_raw ) {
			return new WP_Error( 'aiba_no_google_creds', __( 'Google service account JSON not configured.', 'ai-blog-automator' ) );
		}

		$credentials = json_decode( $creds_raw, true );
		if ( ! is_array( $credentials ) || empty( $credentials['client_email'] ) || empty( $credentials['private_key'] ) ) {
			return new WP_Error( 'aiba_google_creds_invalid', __( 'Invalid Google credentials JSON.', 'ai-blog-automator' ) );
		}

		$jwt = $this->create_signed_jwt( $credentials );
		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 30,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$tok  = is_array( $body ) && ! empty( $body['access_token'] ) ? (string) $body['access_token'] : '';
		if ( '' === $tok ) {
			$err = is_array( $body ) && ! empty( $body['error_description'] ) ? $body['error_description'] : wp_remote_retrieve_body( $response );
			return new WP_Error( 'aiba_google_token', $err );
		}

		set_transient( 'aiba_google_token', $tok, 3500 );
		return $tok;
	}

	/**
	 * @param array<string, string> $credentials Decoded service account JSON.
	 * @return string|WP_Error
	 */
	private function create_signed_jwt( array $credentials ) {
		$header  = $this->base64url_encode( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$now     = time();
		$payload = $this->base64url_encode(
			wp_json_encode(
				array(
					'iss'   => $credentials['client_email'],
					'scope' => 'https://www.googleapis.com/auth/indexing',
					'aud'   => 'https://oauth2.googleapis.com/token',
					'exp'   => $now + 3600,
					'iat'   => $now,
				)
			)
		);

		$signing_input = $header . '.' . $payload;
		$key           = openssl_pkey_get_private( $credentials['private_key'] );
		if ( false === $key ) {
			return new WP_Error( 'aiba_openssl', __( 'Could not read private key.', 'ai-blog-automator' ) );
		}

		$signature = '';
		$ok        = openssl_sign( $signing_input, $signature, $key, OPENSSL_ALGO_SHA256 );
		if ( ! $ok ) {
			return new WP_Error( 'aiba_openssl_sign', __( 'Could not sign JWT.', 'ai-blog-automator' ) );
		}

		return $signing_input . '.' . $this->base64url_encode( $signature );
	}

	private function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}

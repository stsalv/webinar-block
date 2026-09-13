<?php
/**
 * HLS segment signing.
 *
 * Signature is computed from two user-editable templates:
 *  - hash template: string passed to MD5 (e.g. "%expires%%path% %key%")
 *  - sign template: query string appended to the URL (e.g. "?md5=%md5%&expires=%expires%")
 *
 * Supported variables:
 *  %expires% - Unix timestamp when the signature expires
 *  %t%       - current Unix timestamp (anti-cache)
 *  %path%    - URL path (no query string)
 *  %key%     - secret key from webinar settings
 *  %md5%     - URL-safe base64 of the MD5 (only in sign template)
 *
 * @package Webinar_Block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HLS segment signing and manifest serving.
 *
 * @package Webinar_Block
 */
class Webinar_HLS {

	const DEFAULT_TTL       = 7200;
	const DEFAULT_HASH_TMPL = '%expires%%path% %key%';
	const DEFAULT_SIGN_TMPL = '?md5=%md5%&expires=%expires%';

	/**
	 * Register AJAX handlers for signing and manifest serving.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_webinar_sign_segment', array( __CLASS__, 'sign_segment' ) );
		add_action( 'wp_ajax_nopriv_webinar_sign_segment', array( __CLASS__, 'sign_segment' ) );

		add_action( 'wp_ajax_webinar_manifest', array( __CLASS__, 'serve_manifest' ) );
		add_action( 'wp_ajax_nopriv_webinar_manifest', array( __CLASS__, 'serve_manifest' ) );
	}


	/**
	 * Public URL that serves the rewritten master manifest.
	 *
	 * @param int $post_id Webinar post ID.
	 * @return string
	 */
	public static function manifest_url( $post_id ) {
		return add_query_arg(
			array(
				'action' => 'webinar_manifest',
				'id'     => $post_id,
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Serve the master manifest with relative paths rewritten
	 * to the webinar's segment base URL (nginx).
	 */
	public static function serve_manifest() {
		$post_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only manifest endpoint.

		if ( ! $post_id || 'webinar' !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			status_header( 404 );
			exit;
		}

		$manifest_id = (int) get_post_meta( $post_id, '_webinar_video_hls_manifest', true );
		$file        = $manifest_id ? get_attached_file( $manifest_id ) : '';

		if ( ! $file || ! is_readable( $file ) ) {
			status_header( 404 );
			exit;
		}

		$content = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file read, not a remote URL.
		if ( false === $content ) {
			status_header( 404 );
			exit;
		}

		$base    = rtrim( (string) get_post_meta( $post_id, '_webinar_video_hls_base_url', true ), '/' );
		$content = self::rewrite_manifest( $content, $base );

		status_header( 200 );
		header( 'Content-Type: application/vnd.apple.mpegurl' );
		header( 'Cache-Control: no-cache' );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Rewrite relative URIs in a manifest to absolute base URL.
	 *
	 * Handles plain URI lines (child playlists / segments) and
	 * URI="..." attributes (EXT-X-KEY, EXT-X-MAP, renditions).
	 *
	 * @param string $content Manifest text.
	 * @param string $base    Base URL (no trailing slash).
	 * @return string
	 */
	public static function rewrite_manifest( $content, $base ) {
		if ( '' === $base ) {
			return $content;
		}

		$lines = explode( "\n", $content );

		foreach ( $lines as &$line ) {
			$trim = trim( $line );

			if ( '' === $trim ) {
				continue;
			}

			// Plain URI line (not a #TAG).
			if ( 0 !== strpos( $trim, '#' ) ) {
				if ( ! preg_match( '#^(https?:)?//#i', $trim ) ) {
					$line = $base . '/' . ltrim( $trim, '/' );
				}
				continue;
			}

			// URI="..." attributes inside tags.
			$line = preg_replace_callback(
				'/(URI=")([^"]+)(")/',
				function ( $m ) use ( $base ) {
					if ( preg_match( '#^(https?:)?//#i', $m[2] ) ) {
						return $m[0];
					}
					return $m[1] . $base . '/' . ltrim( $m[2], '/' ) . $m[3];
				},
				$line
			);
		}
		unset( $line );

		return implode( "\n", $lines );
	}


	/**
	 * AJAX handler: sign an HLS segment URL using webinar's signing config.
	 *
	 * Validates the request, checks that the URL is under the webinar's
	 * configured base URL, computes MD5 hash from the hash template,
	 * and returns the signed URL built from the sign template.
	 *
	 * Supported template variables:
	 *  - %expires%  Unix timestamp when signature expires
	 *  - %t%        Current Unix timestamp (anti-cache)
	 *  - %path%     URL path (no query string)
	 *  - %key%      Secret key from webinar settings
	 *  - %md5%      URL-safe base64 of MD5 (sign template only)
	 *
	 * @return void Sends JSON response via wp_send_json_success/error.
	 */
	public static function sign_segment() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'webinar_nonce' ) ) {
			wp_send_json_error( 'invalid_nonce', 403 );
		}

		$webinar_id = isset( $_POST['webinar_id'] ) ? absint( $_POST['webinar_id'] ) : 0;
		$url        = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

		if ( ! $webinar_id || ! $url ) {
			wp_send_json_error( 'bad_request' );
		}

		$base = get_post_meta( $webinar_id, '_webinar_video_hls_base_url', true );
		$key  = get_post_meta( $webinar_id, '_webinar_video_hls_key', true );
		$ttl  = (int) get_post_meta( $webinar_id, '_webinar_video_hls_ttl', true );
		if ( $ttl < 60 ) {
			$ttl = self::DEFAULT_TTL;
		}

		$hash_tmpl = (string) get_post_meta( $webinar_id, '_webinar_video_hls_hash_template', true ) ?: self::DEFAULT_HASH_TMPL;
		$sign_tmpl = (string) get_post_meta( $webinar_id, '_webinar_video_hls_sign_template', true ) ?: self::DEFAULT_SIGN_TMPL;

		if ( '' === trim( $hash_tmpl ) || '' === trim( $sign_tmpl ) ) {
			wp_send_json_error( 'no_template' );
		}

		if ( ! $key ) {
			wp_send_json_error( 'no_key' );
		}

		$base_prefix = $base ? rtrim( $base, '/' ) . '/' : '';
		if ( $base_prefix && 0 !== strpos( $url, $base_prefix ) ) {
			wp_send_json_error( 'not_allowed' );
		}

		$parts = wp_parse_url( $url );
		$path  = isset( $parts['path'] ) ? $parts['path'] : '';
		if ( '' === $path ) {
			wp_send_json_error( 'bad_url' );
		}

		$expires = time() + $ttl;
		$now     = time();

		// 1) Compute hash from hash template.
		$hash_input = strtr(
			$hash_tmpl,
			array(
				'%expires%' => (string) $expires,
				'%t%'       => (string) $now,
				'%path%'    => $path,
				'%key%'     => $key,
			)
		);
		$md5        = strtr( base64_encode( md5( $hash_input, true ) ), '+/', '-_' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe base64 of a signature hash.
		$md5        = rtrim( $md5, '=' );

		// 2) Build signed URL from sign template.
		$query = strtr(
			$sign_tmpl,
			array(
				'%md5%'     => $md5,
				'%expires%' => (string) $expires,
				'%t%'       => (string) $now,
				'%path%'    => $path,
			)
		);

		// Smart join: if URL already has query, append with & (unless template already starts with ?/&).
		if ( '' !== $query ) {
			$first = substr( $query, 0, 1 );
			if ( '?' === $first || '&' === $first ) {
				$sep    = ( false === strpos( $url, '?' ) && '?' === $first ) ? '' : ( false !== strpos( $url, '?' ) && '&' !== $first ? '&' : '' );
				$signed = $url . $sep . $query;
			} else {
				$signed = $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $query;
			}
		} else {
			$signed = $url;
		}

		wp_send_json_success(
			array(
				'url'     => $signed,
				'expires' => $expires,
			)
		);
	}
}

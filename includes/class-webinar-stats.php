<?php
/**
 * View / timeline click counters with rate limiting.
 *
 * Views are counted on the first actual "play" per visitor per 30 minutes;
 * timeline clicks are counted once per chapter per visitor per minute.
 * Rate limits are IP-based transients (REMOTE_ADDR only, no spoofable headers).
 *
 * @package Webinar_Block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * View counter and click tracking.
 *
 * @package Webinar_Block
 */
class Webinar_Stats {

	/**
	 * View rate limit, seconds.
	 */
	const VIEW_TTL = 1800;

	/**
	 * Timeline click rate limit, seconds.
	 */
	const CLICK_TTL = 60;

	/**
	 * Hook into WordPress (guests included).
	 */
	public static function init() {
		add_action( 'wp_ajax_webinar_track_view', array( __CLASS__, 'track_view' ) );
		add_action( 'wp_ajax_nopriv_webinar_track_view', array( __CLASS__, 'track_view' ) );
		add_action( 'wp_ajax_webinar_track_click', array( __CLASS__, 'track_click' ) );
		add_action( 'wp_ajax_nopriv_webinar_track_click', array( __CLASS__, 'track_click' ) );
	}

	/**
	 * Increment the views counter (once per visitor per VIEW_TTL).
	 */
	public static function track_view() {
		check_ajax_referer( 'webinar_nonce', 'nonce' );

		$post_id = isset( $_POST['webinar_id'] ) ? absint( $_POST['webinar_id'] ) : 0;
		if ( ! $post_id || 'webinar' !== get_post_type( $post_id ) ) {
			wp_send_json_error( 'bad_request' );
		}

		$lock = 'webinar_view_' . $post_id . '_' . md5( self::client_ip() );
		if ( false !== get_transient( $lock ) ) {
			wp_send_json_success(
				array(
					'counted' => false,
					'count'   => (int) get_post_meta( $post_id, '_webinar_views', true ),
				)
			);
		}

		$views = (int) get_post_meta( $post_id, '_webinar_views', true );
		++$views;
		update_post_meta( $post_id, '_webinar_views', $views );
		set_transient( $lock, 1, self::VIEW_TTL );

		wp_send_json_success(
			array(
				'counted' => true,
				'count'   => $views,
			)
		);
	}

	/**
	 * Increment a timeline chapter click counter (rate limited).
	 */
	public static function track_click() {
		check_ajax_referer( 'webinar_nonce', 'nonce' );

		$post_id = isset( $_POST['webinar_id'] ) ? absint( $_POST['webinar_id'] ) : 0;
		$index   = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : -1;

		if ( ! $post_id || 'webinar' !== get_post_type( $post_id ) || $index < 0 ) {
			wp_send_json_error( 'bad_request' );
		}

		$timeline = get_post_meta( $post_id, '_webinar_timeline', true );
		$timeline = is_array( $timeline ) ? $timeline : array();
		if ( ! isset( $timeline[ $index ] ) ) {
			wp_send_json_error( 'bad_index' );
		}

		$lock = 'webinar_click_' . $post_id . '_' . $index . '_' . md5( self::client_ip() );
		if ( false !== get_transient( $lock ) ) {
			wp_send_json_success( array( 'counted' => false ) );
		}

		$timeline[ $index ]['clicks'] = isset( $timeline[ $index ]['clicks'] ) ? (int) $timeline[ $index ]['clicks'] + 1 : 1;
		update_post_meta( $post_id, '_webinar_timeline', $timeline );
		set_transient( $lock, 1, self::CLICK_TTL );

		wp_send_json_success(
			array(
				'counted' => true,
				'clicks'  => $timeline[ $index ]['clicks'],
			)
		);
	}

	/**
	 * Visitor IP. REMOTE_ADDR only — headers like X-Forwarded-For are spoofable.
	 *
	 * @return string
	 */
	private static function client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}
}

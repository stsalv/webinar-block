<?php
/**
 * Webinar meta fields registration and data helpers.
 *
 * @package Webinar_Block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers all webinar meta fields and provides a data getter.
 */
class Webinar_Meta {

	/**
	 * Register meta fields for the webinar post type.
	 * Called directly from the main plugin file on the 'init' hook.
	 */
	public static function register() {
		$fields = array(
			// ... (все поля остаются без изменений) ...
		);

		foreach ( $fields as $key => $args ) {
			register_post_meta(
				'webinar',
				$key,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => $args['type'],
					'default'       => isset( $args['default'] ) ? $args['default'] : null,
					'description'   => $args['description'],
					'auth_callback' => function () {
						return current_user_can( 'edit_webinars' );
					},
				)
			);
		}
	}

	/**
	 * Register meta fields for the webinar post type.
	 *
	 * Descriptions are developer-facing (REST schema) and stay in English.
	 */
	public static function register_meta_fields() {
		$fields = array(
			// ----- General -----
			'_webinar_views'                   => array(
				'type'        => 'integer',
				'default'     => 0,
				'description' => 'View counter.',
			),
			'_webinar_short_desc'              => array(
				'type'        => 'string',
				'description' => 'Short description shown in the header.',
			),
			'_webinar_full_desc'               => array(
				'type'        => 'string',
				'description' => 'Full description shown in the header.',
			),
			'_webinar_key_message'             => array(
				'type'        => 'string',
				'description' => 'Key message block below the tabs.',
			),

			// ----- Video -----
			'_webinar_video_type'              => array(
				'type'        => 'string',
				'default'     => 'mp4',
				'description' => 'Video source type: mp4 or hls.',
			),
			'_webinar_video_mp4'               => array(
				'type'        => 'integer',
				'description' => 'Attachment ID of the MP4 file.',
			),
			'_webinar_video_hls_manifest'      => array(
				'type'        => 'integer',
				'description' => 'Attachment ID of the HLS master manifest (.m3u8).',
			),
			'_webinar_video_hls_base_url'      => array(
				'type'        => 'string',
				'description' => 'Base URL used to resolve HLS segments.',
			),
			'_webinar_video_hls_key'           => array(
				'type'        => 'string',
				'description' => 'Secret key used to sign HLS segment names.',
			),
			'_webinar_video_hls_ttl'           => array(
				'type'        => 'integer',
				'default'     => 7200,
				'description' => 'Signature time-to-live, seconds.',
			),
			'_webinar_video_hls_hash_template' => array(
				'type'        => 'string',
				'default'     => '%expires%%path% %key%',
				'description' => 'Template for the string passed to MD5. Variables: %expires%, %path%, %key%, %t%.',
			),
			'_webinar_video_hls_sign_template' => array(
				'type'        => 'string',
				'default'     => '?md5=%md5%&expires=%expires%',
				'description' => 'Query string appended to the URL. Variables: %md5%, %expires%, %t%, %path%.',
			),
			'_webinar_video_hls_preset'        => array(
				'type'        => 'string',
				'default'     => 'nginx',
				'description' => 'Signing scheme preset: nginx|cloudfront|fastly|custom.',
			),

			'_webinar_video_poster'            => array(
				'type'        => 'integer',
				'description' => 'Attachment ID of the player poster image.',
			),
			'_webinar_player_type'             => array(
				'type'        => 'string',
				'default'     => 'custom',
				'description' => 'Player interface: "custom" (branded controls) or "native" (browser defaults).',
			),
			'_webinar_show_share'              => array(
				'type'        => 'string',
				'default'     => '1',
				'description' => 'Show the share button ("1" or "0").',
			),
			'_webinar_preview_std'             => array(
				'type'        => 'string',
				'default'     => '',
				'description' => 'URL of the standard-quality WebVTT storyboard for hover previews.',
			),
			'_webinar_preview_small'           => array(
				'type'        => 'string',
				'default'     => '',
				'description' => 'URL of the small-quality WebVTT storyboard for hover previews.',
			),
			'_webinar_timeline_mode'           => array(
				'type'        => 'string',
				'default'     => 'block',
				'description' => 'Timeline presentation: "block" (side column) or "bar" (progress bar only).',
			),

			// ----- Presentation -----
			'_webinar_pdf'                     => array(
				'type'        => 'integer',
				'description' => 'Attachment ID of the presentation file.',
			),
			'_webinar_pdf_button_text'         => array(
				'type'        => 'string',
				'default'     => 'Download PDF',
				'description' => 'Download button label.',
			),
			'_webinar_pdf_icon'                => array(
				'type'        => 'integer',
				'description' => 'Attachment ID of the button SVG icon.',
			),

			'_webinar_pdf_icon_size'           => array(
				'type'        => 'string',
				'default'     => 'normal',
				'description' => 'Icon size for the presentation download button: "normal" (16 px) or "large" (28 px).',
			),

			// ----- Theme -----
			'_webinar_theme'                   => array(
				'type'        => 'string',
				'default'     => 'standard',
				'description' => 'Color theme: standard, night, emerald, graphite, sunset or custom.',
			),
			'_webinar_theme_custom'            => array(
				'type'        => 'object',
				'description' => 'Custom CSS variables for the custom theme.',
			),

			// ----- Materials -----
			'_webinar_materials'               => array(
				'type'        => 'array',
				'description' => 'List of downloadable materials.',
			),

			// ----- Takeaways -----
			'_webinar_key_takeaway'            => array(
				'type'        => 'string',
				'description' => 'Key takeaway paragraph.',
			),
			'_webinar_takeaways'               => array(
				'type'        => 'array',
				'description' => 'List of takeaway tiles.',
			),
			'_webinar_takeaways_layout'        => array(
				'type'        => 'string',
				'default'     => 'default',
				'description' => 'Layout scheme for the takeaways block.',
			),

			'_webinar_materials_layout'        => array(
				'type'        => 'string',
				'default'     => 'classic',
				'description' => 'Layout scheme for the materials block.',
			),

			// ----- Timeline -----
			'_webinar_timeline'                => array(
				'type'        => 'array',
				'description' => 'List of timeline chapters.',
			),
			'_webinar_timeline_layout'         => array(
				'type'        => 'string',
				'default'     => 'default',
				'description' => 'Layout scheme for the timeline block.',
			),
		);

		foreach ( $fields as $key => $args ) {
			register_post_meta(
				'webinar',
				$key,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => $args['type'],
					'default'       => isset( $args['default'] ) ? $args['default'] : null,
					'description'   => $args['description'],
					'auth_callback' => function () {
						return current_user_can( 'edit_webinars' );
					},
				)
			);
		}
	}

	/**
	 * Collect all webinar data into a single array for rendering.
	 *
	 * @param int $post_id Webinar post ID.
	 * @return array
	 */
	public static function get_webinar_data( $post_id ) {
		return array(
			'id'                      => $post_id,
			'title'                   => get_the_title( $post_id ),
			'views'                   => get_post_meta( $post_id, '_webinar_views', true ) ?: 0,
			'short_desc'              => get_post_meta( $post_id, '_webinar_short_desc', true ),
			'full_desc'               => get_post_meta( $post_id, '_webinar_full_desc', true ),
			'key_message'             => get_post_meta( $post_id, '_webinar_key_message', true ),
			'key_takeaway'            => get_post_meta( $post_id, '_webinar_key_takeaway', true ),

			// Video.
			'video_type'              => get_post_meta( $post_id, '_webinar_video_type', true ) ?: 'mp4',
			'video_mp4'               => get_post_meta( $post_id, '_webinar_video_mp4', true ),
			'video_hls_manifest'      => get_post_meta( $post_id, '_webinar_video_hls_manifest', true ),
			'video_hls_base_url'      => get_post_meta( $post_id, '_webinar_video_hls_base_url', true ),
			'video_hls_key'           => get_post_meta( $post_id, '_webinar_video_hls_key', true ),
			'video_hls_ttl'           => (int) get_post_meta( $post_id, '_webinar_video_hls_ttl', true ) ?: 7200,
			'video_hls_hash_template' => get_post_meta( $post_id, '_webinar_video_hls_hash_template', true ) ?: '%expires%%path% %key%',
			'video_hls_sign_template' => get_post_meta( $post_id, '_webinar_video_hls_sign_template', true ) ?: '?md5=%md5%&expires=%expires%',
			'video_hls_preset'        => get_post_meta( $post_id, '_webinar_video_hls_preset', true ) ?: 'nginx',
			'video_poster'            => get_post_meta( $post_id, '_webinar_video_poster', true ),
			'player_type'             => get_post_meta( $post_id, '_webinar_player_type', true ) ?: 'custom',
			'show_share'              => get_post_meta( $post_id, '_webinar_show_share', true ) ?: '1',
			'preview_std'             => get_post_meta( $post_id, '_webinar_preview_std', true ),
			'preview_small'           => get_post_meta( $post_id, '_webinar_preview_small', true ),
			'timeline_mode'           => get_post_meta( $post_id, '_webinar_timeline_mode', true ) ?: 'block',

			// Presentation.
			'pdf'                     => get_post_meta( $post_id, '_webinar_pdf', true ),
			'pdf_button_text'         => get_post_meta( $post_id, '_webinar_pdf_button_text', true ) ?: __( 'Download PDF', 'webinar-block' ),
			'pdf_icon'                => get_post_meta( $post_id, '_webinar_pdf_icon', true ),
			'pdf_icon_size'           => get_post_meta( $post_id, '_webinar_pdf_icon_size', true ) ?: 'normal',

			// Repeaters.
			'timeline'                => get_post_meta( $post_id, '_webinar_timeline', true ) ?: array(),
			'takeaways'               => get_post_meta( $post_id, '_webinar_takeaways', true ) ?: array(),
			'materials'               => get_post_meta( $post_id, '_webinar_materials', true ) ?: array(),

			// Theme.
			'theme'                   => get_post_meta( $post_id, '_webinar_theme', true ) ?: 'standard',
			'theme_custom'            => get_post_meta( $post_id, '_webinar_theme_custom', true ),

			// Layout Schemes.
			'timeline_layout'         => get_post_meta( $post_id, '_webinar_timeline_layout', true ) ?: 'classic',
			'timeline_layout_css'     => get_post_meta( $post_id, '_webinar_timeline_layout_css', true ),

			'takeaways_layout'        => get_post_meta( $post_id, '_webinar_takeaways_layout', true ) ?: 'classic',
			'takeaways_layout_css'    => get_post_meta( $post_id, '_webinar_takeaways_layout_css', true ),

			'materials_layout'        => get_post_meta( $post_id, '_webinar_materials_layout', true ) ?: 'classic',
			'materials_layout_css'    => get_post_meta( $post_id, '_webinar_materials_layout_css', true ),

			'details_layout'          => get_post_meta( $post_id, '_webinar_details_layout', true ) ?: 'classic',
			'details_layout_css'      => get_post_meta( $post_id, '_webinar_details_layout_css', true ),
		);
	}
}

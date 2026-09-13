<?php
/**
 * Gutenberg block: dynamic "Webinar" block.
 *
 * Editor UI: pick a published webinar from a dropdown.
 * Front-end: rendered by Webinar_Render (same as the shortcode).
 *
 * @package Webinar_Block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gutenberg block registration and editor assets.
 *
 * @package Webinar_Block
 */
class Webinar_Gutenberg {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		if ( did_action( 'init' ) ) {
			// init has already finished — we register it right away.
			self::register_block();
		} else {
			// init is still running — we’re setting the priority LATER than the current one,
			// otherwise WP won’t execute the callback in this request.
			add_action( 'init', array( __CLASS__, 'register_block' ), 20 );
		}
	}

	/**
	 * Register the dynamic block + editor script.
	 */
	public static function register_block() {
		$file = WEBINAR_BLOCK_PLUGIN_DIR . 'build/blocks/webinar.js';
		if ( ! file_exists( $file ) ) {
			return;
		}

		wp_register_script(
			'webinar-block-editor',
			WEBINAR_BLOCK_PLUGIN_URL . 'build/blocks/webinar.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-api-fetch', 'wp-server-side-render' ),
			WEBINAR_BLOCK_VERSION,
			true // Load in footer.
		);

		wp_register_style(
			'webinar-block-editor-style',
			WEBINAR_BLOCK_PLUGIN_URL . 'build/public/webinar-style.css',
			array(),
			WEBINAR_BLOCK_VERSION
		);

		register_block_type(
			'webinar-block/webinar',
			array(
				'editor_script'   => 'webinar-block-editor',
				'editor_style'    => 'webinar-block-editor-style',
				'render_callback' => array( __CLASS__, 'render' ),
				'attributes'      => array(
					'webinarId' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);

		// Editor assets + localization object.
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor' ) );
	}

	/**
	 * Dynamic render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render( $attributes ) {
		$id = isset( $attributes['webinarId'] ) ? absint( $attributes['webinarId'] ) : 0;
		if ( ! $id || 'webinar' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return '';
		}
		return Webinar_Render::render( $id );
	}

	/**
	 * Explicitly enqueue the editor script and styles in the block editor.
	 */
	public static function enqueue_editor() {
		wp_enqueue_script( 'webinar-block-editor' );
		wp_enqueue_style( 'webinar-block', WEBINAR_BLOCK_PLUGIN_URL . 'build/public/webinar-style.css', array(), WEBINAR_BLOCK_VERSION );

		wp_localize_script(
			'webinar-block-editor',
			'webinarBlockEditorL10n',
			array(
				'title'        => __( 'Webinar', 'webinar-block' ),
				'settings'     => __( 'Webinar settings', 'webinar-block' ),
				'select'       => __( '— Select a webinar —', 'webinar-block' ),
				'selected'     => __( 'Selected:', 'webinar-block' ),
				'instructions' => __( 'Choose a published webinar. The full block renders on the site.', 'webinar-block' ),
			)
		);
	}
}

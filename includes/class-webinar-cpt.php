<?php
/**
 * Webinar custom post type registration.
 *
 * @package Webinar_Block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the "webinar" custom post type.
 */
class Webinar_CPT {

	const POST_TYPE = 'webinar';

	/**
	 * Register the post type.
	 * Called directly from the main plugin file on the 'init' hook.
	 */
	public static function register() {
		$labels = array(
			'name'                     => __( 'Webinars', 'webinar-block' ),
			'singular_name'            => __( 'Webinar', 'webinar-block' ),
			'add_new'                  => __( 'Add New', 'webinar-block' ),
			'add_new_item'             => __( 'Add New Webinar', 'webinar-block' ),
			'edit_item'                => __( 'Edit Webinar', 'webinar-block' ),
			'new_item'                 => __( 'New Webinar', 'webinar-block' ),
			'view_item'                => __( 'View Webinar', 'webinar-block' ),
			'view_items'               => __( 'View Webinars', 'webinar-block' ),
			'search_items'             => __( 'Search Webinars', 'webinar-block' ),
			'not_found'                => __( 'No webinars found', 'webinar-block' ),
			'not_found_in_trash'       => __( 'No webinars found in Trash', 'webinar-block' ),
			'all_items'                => __( 'All Webinars', 'webinar-block' ),
			'menu_name'                => __( 'Webinars', 'webinar-block' ),
			'archives'                 => __( 'Webinar Archives', 'webinar-block' ),
			'attributes'               => __( 'Webinar Attributes', 'webinar-block' ),
			'parent_item_colon'        => __( 'Parent Webinar:', 'webinar-block' ),
			'featured_image'           => __( 'Featured image', 'webinar-block' ),
			'set_featured_image'       => __( 'Set featured image', 'webinar-block' ),
			'remove_featured_image'    => __( 'Remove featured image', 'webinar-block' ),
			'use_featured_image'       => __( 'Use as featured image', 'webinar-block' ),
			'insert_into_item'         => __( 'Insert into webinar', 'webinar-block' ),
			'uploaded_to_this_item'    => __( 'Uploaded to this webinar', 'webinar-block' ),
			'filter_items_list'        => __( 'Filter webinars list', 'webinar-block' ),
			'items_list_navigation'    => __( 'Webinars list navigation', 'webinar-block' ),
			'items_list'               => __( 'Webinars list', 'webinar-block' ),
			'item_published'           => __( 'Webinar published.', 'webinar-block' ),
			'item_published_privately' => __( 'Webinar published privately.', 'webinar-block' ),
			'item_scheduled'           => __( 'Webinar scheduled.', 'webinar-block' ),
			'item_updated'             => __( 'Webinar updated.', 'webinar-block' ),
		);

		$args = array(
			'labels'              => $labels,
			// A webinar is an embeddable block, not a standalone page.
			// Non-public CPTs are ignored by Yoast and similar SEO plugins
			// and never appear in search results.
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => self::menu_icon(),
			'supports'            => array( 'title' ),
			'capability_type'     => array( 'webinar', 'webinars' ),
			'map_meta_cap'        => true,
			'show_in_rest'        => true, // Required for the Gutenberg block.
			'rest_base'           => 'webinars',
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Custom admin menu icon: narrow play badge + three stripes
	 * (matches the Gutenberg block icon). WP tints it via opacity:
	 * grey at rest, white on hover / current item.
	 *
	 * @return string Data-URI SVG.
	 */
	private static function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 34">'
			. '<path fill="#ffffff" fill-rule="evenodd" d="M3.5 3h9q2.5 0 2.5 2.5v11q0 2.5-2.5 2.5h-9Q1 19 1 16.5v-11Q1 3 3.5 3zM5.5 8.5v7l6-3.5z"/>'
			. '<path fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" d="M16 15h3.5M16 19.5h3.5M16 24h3.5"/>'
			. '</svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Data URI encoding, not obfuscation.
	}
}

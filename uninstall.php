<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes plugin-added capabilities. Post type 'webinar' posts and their
 * meta are intentionally preserved as user content, per WordPress.org policy.
 *
 * @package Webinar_Block
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-webinar-capabilities.php';

Webinar_Capabilities::remove_capabilities();

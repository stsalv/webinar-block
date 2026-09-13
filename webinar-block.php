<?php
/**
 * Plugin Name: Webinar Block
 * Plugin URI: https://github.com/stsalv/webinar-block
 * Description: Webinar block with video, timeline, takeaways and detailed content.
 * Version: 1.0.0
 * Author: stsalv
 * Author URI: https://github.com/stsalv
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: webinar-block
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 7.0
 * Requires PHP: 7.4
 *
 * @package Webinar_Block
 */

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Hook callbacks live alongside the bootstrap class.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Plugin constants.
define( 'WEBINAR_BLOCK_VERSION', '1.0.0' );
define( 'WEBINAR_BLOCK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WEBINAR_BLOCK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load plugin classes.
require_once WEBINAR_BLOCK_PLUGIN_DIR . 'includes/class-webinar-cpt.php';
require_once WEBINAR_BLOCK_PLUGIN_DIR . 'includes/class-webinar-meta.php';
require_once WEBINAR_BLOCK_PLUGIN_DIR . 'includes/class-webinar-capabilities.php';
require_once WEBINAR_BLOCK_PLUGIN_DIR . 'includes/class-webinar-layouts.php';
require_once WEBINAR_BLOCK_PLUGIN_DIR . 'includes/class-webinar-themes.php';
require_once WEBINAR_BLOCK_PLUGIN_DIR . 'includes/class-webinar-render.php';
require_once WEBINAR_BLOCK_PLUGIN_DIR . 'includes/class-webinar-hls.php';
require_once WEBINAR_BLOCK_PLUGIN_DIR . 'includes/class-webinar-stats.php';
require_once WEBINAR_BLOCK_PLUGIN_DIR . 'includes/class-webinar-gutenberg.php';

// Admin-only components (meta boxes, list columns).
if ( is_admin() ) {
	require_once WEBINAR_BLOCK_PLUGIN_DIR . 'includes/admin/class-webinar-admin.php';
	Webinar_Admin::init();
}

/**
 * Main plugin bootstrap class (singleton).
 */
class Webinar_Block {

	/**
	 * Singleton instance.
	 *
	 * @var Webinar_Block|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Webinar_Block
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor: wire up hooks.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Load plugin text domain for translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'webinar-block',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);
	}

	/**
	 * Initialize plugin components on the 'init' hook.
	 */
	public function init() {
		Webinar_CPT::register();
		Webinar_Meta::register();
		Webinar_Render::init();
		Webinar_HLS::init();
		Webinar_Stats::init();
		Webinar_Gutenberg::init();
	}
}

// Bootstrap the plugin.
Webinar_Block::get_instance();

// Activation hook.
register_activation_hook( __FILE__, 'webinar_block_activate' );

/**
 * Plugin activation: flush rewrite rules so the webinar CPT becomes queryable.
 *
 * @return void
 */
function webinar_block_activate() {
	Webinar_CPT::register();
	Webinar_Capabilities::add_capabilities();
	flush_rewrite_rules();
}

// Deactivation hook.
register_deactivation_hook( __FILE__, 'webinar_block_deactivate' );

/**
 * Plugin deactivation: clean up scheduled events if any.
 *
 * @return void
 */
function webinar_block_deactivate() {
	flush_rewrite_rules();
}

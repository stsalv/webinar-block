<?php
/**
 * Role capabilities management for webinars.
 *
 * @package Webinar_Block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds/removes webinar capabilities for administrator and editor roles.
 */
class Webinar_Capabilities {

	/**
	 * Roles that should receive webinar capabilities.
	 *
	 * @var string[]
	 */
	private static $roles = array( 'administrator', 'editor' );

	/**
	 * Webinar capability list (matches capability_type ['webinar', 'webinars']).
	 *
	 * @var string[]
	 */
	private static $caps = array(
		'edit_webinars',
		'edit_others_webinars',
		'edit_private_webinars',
		'edit_published_webinars',
		'publish_webinars',
		'read_private_webinars',
		'delete_webinars',
		'delete_others_webinars',
		'delete_private_webinars',
		'delete_published_webinars',
	);


	/**
	 * Grant webinar capabilities (called on plugin activation).
	 */
	public static function add_capabilities() {
		foreach ( self::$roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( self::$caps as $cap ) {
					$role->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * Revoke webinar capabilities (called on uninstall).
	 */
	public static function remove_capabilities() {
		foreach ( self::$roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( self::$caps as $cap ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}
}

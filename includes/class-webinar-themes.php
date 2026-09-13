<?php
/**
 * Color themes for webinar blocks.
 *
 * @package Webinar_Block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme presets and custom palette helpers.
 */
class Webinar_Themes {

	/**
	 * Theme list for selects.
	 *
	 * @return array
	 */
	public static function get_themes() {
		return array(
			'standard' => __( 'Standard (site colors)', 'webinar-block' ),
			'night'    => __( 'Dark night', 'webinar-block' ),
			'emerald'  => __( 'Emerald', 'webinar-block' ),
			'graphite' => __( 'Graphite', 'webinar-block' ),
			'sunset'   => __( 'Sunset', 'webinar-block' ),
			'custom'   => __( 'Custom palette', 'webinar-block' ),
		);
	}

	/**
	 * Custom palette field definitions grouped by category.
	 *
	 * @return array
	 */
	public static function get_color_fields() {
		return array(
			'brand'      => array(
				'primary'       => __( 'Primary color', 'webinar-block' ),
				'primary_hover' => __( 'Primary hover', 'webinar-block' ),
				'secondary'     => __( 'Secondary color', 'webinar-block' ),
				'accent'        => __( 'Accent color', 'webinar-block' ),
			),
			'surfaces'   => array(
				'bg_main'    => __( 'Main background', 'webinar-block' ),
				'bg_tile'    => __( 'Tile background', 'webinar-block' ),
				'bg_content' => __( 'Tab content background', 'webinar-block' ),
				'bg_button'  => __( 'Button background', 'webinar-block' ),
				'border'     => __( 'Borders', 'webinar-block' ),
			),
			'text'       => array(
				'text_primary'    => __( 'Primary text', 'webinar-block' ),
				'text_secondary'  => __( 'Secondary text', 'webinar-block' ),
				'text_on_primary' => __( 'Text on primary', 'webinar-block' ),
				'text_muted'      => __( 'Muted text', 'webinar-block' ),
				'text_inverse'    => __( 'Inverse text', 'webinar-block' ),
			),
			'components' => array(
				'tab_active'      => __( 'Active tab', 'webinar-block' ),
				'timeline_active' => __( 'Active timeline', 'webinar-block' ),
				'eye_counter'     => __( 'Eye & counter', 'webinar-block' ),
				'badge_bg'        => __( 'Badge background', 'webinar-block' ),
			),
		);
	}

	/**
	 * Hex colors for each preset (16 colors per theme).
	 *
	 * @return array
	 */
	public static function preset_colors() {
		return array(
			'standard' => array(
				'primary'         => '#014993',
				'primary_hover'   => '#003a75',
				'secondary'       => '#3a7bc8',
				'accent'          => '#002d5c',
				'bg_main'         => '#ffffff',
				'bg_tile'         => '#ffffff',
				'bg_content'      => '#f5f5f5',
				'bg_button'       => '#014993',
				'border'          => '#e5e7eb',
				'text_primary'    => '#1e3a5f',
				'text_secondary'  => '#4b5563',
				'text_on_primary' => '#ffffff',
				'text_muted'      => '#94a3b8',
				'text_inverse'    => '#ffffff',
				'tab_active'      => '#002d5c',
				'timeline_active' => '#e6f0fa',
				'eye_counter'     => '#667085',
				'badge_bg'        => '#014993',
			),
			'night'    => array(
				'primary'         => '#9ec9ff',
				'primary_hover'   => '#c7e2fc',
				'secondary'       => '#3a7bc8',
				'accent'          => '#dce7f5',
				'bg_main'         => '#0a1929',
				'bg_tile'         => '#112240',
				'bg_content'      => '#0d1f35',
				'bg_button'       => '#9ec9ff',
				'border'          => '#233554',
				'text_primary'    => '#dce7f5',
				'text_secondary'  => '#8892b0',
				'text_on_primary' => '#0a1929',
				'text_muted'      => '#8892b0',
				'text_inverse'    => '#0a1929',
				'tab_active'      => '#dce7f5',
				'timeline_active' => '#183362',
				'eye_counter'     => '#8892b0',
				'badge_bg'        => '#9ec9ff',
			),
			'emerald'  => array(
				'primary'         => '#065f46',
				'primary_hover'   => '#047857',
				'secondary'       => '#10b981',
				'accent'          => '#047857',
				'bg_main'         => '#ecfdf5',
				'bg_tile'         => '#ffffff',
				'bg_content'      => '#ffffff',
				'bg_button'       => '#065f46',
				'border'          => '#a7f3d0',
				'text_primary'    => '#064e3b',
				'text_secondary'  => '#047857',
				'text_on_primary' => '#ffffff',
				'text_muted'      => '#6b7280',
				'text_inverse'    => '#ffffff',
				'tab_active'      => '#047857',
				'timeline_active' => '#d1fae5',
				'eye_counter'     => '#6b7280',
				'badge_bg'        => '#065f46',
			),
			'graphite' => array(
				'primary'         => '#1f2937',
				'primary_hover'   => '#111827',
				'secondary'       => '#6b7280',
				'accent'          => '#111827',
				'bg_main'         => '#f3f4f6',
				'bg_tile'         => '#ffffff',
				'bg_content'      => '#ffffff',
				'bg_button'       => '#1f2937',
				'border'          => '#d1d5db',
				'text_primary'    => '#1f2937',
				'text_secondary'  => '#4b5563',
				'text_on_primary' => '#ffffff',
				'text_muted'      => '#6b7280',
				'text_inverse'    => '#ffffff',
				'tab_active'      => '#111827',
				'timeline_active' => '#e5e7eb',
				'eye_counter'     => '#6b7280',
				'badge_bg'        => '#1f2937',
			),
			'sunset'   => array(
				'primary'         => '#7c2d12',
				'primary_hover'   => '#9a3412',
				'secondary'       => '#f97316',
				'accent'          => '#b45309',
				'bg_main'         => '#fff7ed',
				'bg_tile'         => '#ffffff',
				'bg_content'      => '#ffffff',
				'bg_button'       => '#7c2d12',
				'border'          => '#fed7aa',
				'text_primary'    => '#7c2d12',
				'text_secondary'  => '#9a3412',
				'text_on_primary' => '#ffffff',
				'text_muted'      => '#9ca3af',
				'text_inverse'    => '#ffffff',
				'tab_active'      => '#b45309',
				'timeline_active' => '#ffedd5',
				'eye_counter'     => '#9ca3af',
				'badge_bg'        => '#7c2d12',
			),
		);
	}
}

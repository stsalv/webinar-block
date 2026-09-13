<?php
/**
 * Front-end rendering for webinars.
 *
 * @package Webinar_Block
 */

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Template tags live alongside the render class.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a webinar from its meta data and exposes shortcode / template API.
 */
class Webinar_Render {

	const DEFAULT_ICON_SVG = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>';
	const EYE_SVG          = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
	const CHEVRON_SVG      = '<svg class="webinar-chevron" width="18" height="18" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>';

	// Custom player controls SVG icons (optimized for 24x24 viewBox, currentColor).
	const SVG_PLAY            = '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>';
	const SVG_PAUSE           = '<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>';
	const SVG_VOLUME          = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>';
	const SVG_MUTE            = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line></svg>';
	const SVG_FULLSCREEN      = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg>';
	const SVG_EXIT_FULLSCREEN = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"></path></svg>';
	const SVG_SKIP_BACK       = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 17l-5-5 5-5M18 17l-5-5 5-5"/></svg>';
	const SVG_SKIP_FORWARD    = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 17l5-5-5-5M6 17l5-5-5-5"/></svg>';
	const SVG_SPEED           = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2a10 10 0 0 1 0 20" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path><path d="M12 22a10 10 0 0 1 0-20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="3.5 4"></path><path d="M10 8.5v7l6-3.5z" fill="currentColor"></path></svg>';
	const SVG_RETRY           = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 4v6h-6"></path><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>';
	const SVG_SHARE           = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>';
	const SVG_PIP             = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"></path><path d="M14 3h7v7"></path><path d="M21 3L11 13"></path></svg>';
	/**
	 * Icon HTML: inline sanitized SVG (recolorable via currentColor),
	 * <img> for raster, default icon when nothing chosen.
	 *
	 * @param int $icon_id Attachment ID.
	 * @return string
	 */
	private static function icon_html( $icon_id ) {
		if ( $icon_id ) {
			$file = get_attached_file( $icon_id );
			if ( $file && 'svg' === strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
				$svg = self::get_safe_svg( $file );
				if ( '' !== $svg ) {
					return $svg;
				}
			}
			$url = wp_get_attachment_url( $icon_id );
			if ( $url ) {
				return '<img src="' . esc_url( $url ) . '" alt="" />';
			}
		}
		return self::DEFAULT_ICON_SVG;
	}

	/**
	 * Sanitize an SVG file and recolor hard-coded fills/strokes
	 * to currentColor so the icon follows the button text color.
	 *
	 * @param string $file Absolute path.
	 * @return string
	 */
	private static function get_safe_svg( $file ) {
		if ( ! file_exists( $file ) ) {
			return '';
		}
		$svg = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local SVG file, not remote URL.
		if ( ! $svg || false === strpos( $svg, '<svg' ) ) {
			return '';
		}

		$allowed = array(
			'svg'      => array(
				'xmlns'           => true,
				'viewbox'         => true,
				'width'           => true,
				'height'          => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'aria-hidden'     => true,
				'focusable'       => true,
			),
			'path'     => array(
				'd'            => true,
				'fill'         => true,
				'fill-rule'    => true,
				'clip-rule'    => true,
				'stroke'       => true,
				'stroke-width' => true,
				'opacity'      => true,
			),
			'circle'   => array(
				'cx'           => true,
				'cy'           => true,
				'r'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'opacity'      => true,
			),
			'rect'     => array(
				'x'            => true,
				'y'            => true,
				'width'        => true,
				'height'       => true,
				'rx'           => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'opacity'      => true,
			),
			'line'     => array(
				'x1'           => true,
				'y1'           => true,
				'x2'           => true,
				'y2'           => true,
				'stroke'       => true,
				'stroke-width' => true,
				'opacity'      => true,
			),
			'polyline' => array(
				'points'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'opacity'      => true,
			),
			'polygon'  => array(
				'points'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'opacity'      => true,
			),
			'ellipse'  => array(
				'cx'           => true,
				'cy'           => true,
				'rx'           => true,
				'ry'           => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'opacity'      => true,
			),
			'g'        => array(
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
				'opacity'      => true,
			),
		);

		$clean = wp_kses( $svg, $allowed );

		// Any hard-coded color becomes currentColor ("none" stays).
		$clean = preg_replace( '/(fill|stroke)="(?!none|currentColor)[^"]*"/i', '$1="currentColor"', $clean );

		return $clean;
	}

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_shortcode( 'webinar', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue front-end assets only on pages that contain a webinar.
	 */
	public static function enqueue_assets() {
		$need = false;

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post && ! empty( $post->post_content ) ) {
				$need = has_shortcode( $post->post_content, 'webinar' ) || has_block( 'webinar-block/webinar', $post );
			}
		}

		/**
		 * Force front-end assets (for template-based rendering).
		 *
		 * @param bool $need Current decision.
		 */
		$need = apply_filters( 'webinar_block_force_assets', $need );

		if ( ! $need ) {
			return;
		}

		wp_enqueue_style( 'webinar-block', WEBINAR_BLOCK_PLUGIN_URL . 'build/public/webinar-style.css', array(), WEBINAR_BLOCK_VERSION );

		foreach ( array( 'webinar', 'webinar-tabs', 'webinar-seek', 'webinar-details', 'webinar-slider', 'webinar-controls' ) as $asset ) {
			wp_enqueue_script( 'webinar-block-' . $asset, WEBINAR_BLOCK_PLUGIN_URL . 'build/public/' . $asset . '.js', array(), WEBINAR_BLOCK_VERSION, true );
		}

		self::localize_front();
	}

	/**
	 * Front-end localization payload (shared with the editor canvas).
	 */
	private static function localize_front() {
		wp_localize_script(
			'webinar-block-webinar',
			'webinarBlockFront',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'webinar_nonce' ),
				'autoLabel'      => __( 'Auto', 'webinar-block' ),
				'prevSlide'      => __( 'Previous slide', 'webinar-block' ),
				'nextSlide'      => __( 'Next slide', 'webinar-block' ),
				// Custom player controls i18n.
				'play'           => __( 'Play', 'webinar-block' ),
				'pause'          => __( 'Pause', 'webinar-block' ),
				'mute'           => __( 'Mute', 'webinar-block' ),
				'unmute'         => __( 'Unmute', 'webinar-block' ),
				'fullscreen'     => __( 'Fullscreen', 'webinar-block' ),
				'exitFullscreen' => __( 'Exit Fullscreen', 'webinar-block' ),
				'skipBackward'   => __( 'Skip backward 10 seconds', 'webinar-block' ),
				'skipForward'    => __( 'Skip forward 10 seconds', 'webinar-block' ),
				'playbackSpeed'  => __( 'Playback speed', 'webinar-block' ),
				'progress'       => __( 'Progress', 'webinar-block' ),
				'volume'         => __( 'Volume', 'webinar-block' ),
				// Buffering / error states.
				'buffering'      => __( 'Buffering', 'webinar-block' ),
				'playbackError'  => __( 'Playback error', 'webinar-block' ),
				'retry'          => __( 'Retry', 'webinar-block' ),
				'quality'        => __( 'Quality', 'webinar-block' ),
				'share'          => __( 'Share', 'webinar-block' ),
			)
		);
	}

	/**
	 * Shortcode handler: [webinar id="123"] or [webinar slug="my-webinar"].
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'   => 0,
				'slug' => '',
			),
			$atts
		);

		$id = absint( $atts['id'] );
		if ( ! $id && $atts['slug'] ) {
			$found = get_page_by_path( $atts['slug'], OBJECT, 'webinar' );
			$id    = $found ? $found->ID : 0;
		}

		if ( ! $id || 'webinar' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return '';
		}

		return self::render( $id );
	}

	/**
	 * Convert "mm:ss" (or "h:mm:ss") to seconds.
	 *
	 * @param string $str Time string.
	 * @return int
	 */
	public static function time_to_seconds( $str ) {
		$str = trim( (string) $str );
		if ( '' === $str ) {
			return 0;
		}
		$parts = array_reverse( explode( ':', $str ) );
		$mult  = array( 1, 60, 3600 );
		$secs  = 0;
		foreach ( $parts as $i => $part ) {
			$secs += absint( $part ) * $mult[ $i ];
		}
		return $secs;
	}

	/**
	 * Compact view counter: 0-999 as is, then K/M/B with up to two
	 * decimals and trimmed trailing zeros (1 K, 1.5 K, 1.55 K, 10 K).
	 *
	 * @param int $count View count.
	 * @return string
	 */
	public static function format_views( $count ) {
		$count = max( 0, absint( $count ) );
		if ( $count < 1000 ) {
			return (string) $count;
		}
		$units = array(
			1000000000 => 'B',
			1000000    => 'M',
			1000       => 'K',
		);
		foreach ( $units as $value => $suffix ) {
			if ( $count >= $value ) {
				$scaled = round( $count / $value, 2 );
				$text   = rtrim( rtrim( number_format( $scaled, 2, '.', '' ), '0' ), '.' );
				return $text . ' ' . $suffix;
			}
		}
		return (string) $count;
	}

	/**
	 * Per-instance styles: theme accent remap + layout schemes.
	 *
	 * @param array $data Webinar data.
	 * @return string
	 */
	private static function instance_styles( $data ) {
		$id  = $data['id'];
		$css = '';

		// Theme: generate CSS variables for this instance.
		$presets = Webinar_Themes::preset_colors();
		$colors  = 'custom' === $data['theme']
			? wp_parse_args( (array) $data['theme_custom'], $presets['standard'] )
			: ( $presets[ $data['theme'] ] ?? $presets['standard'] );

		$selector = '.webinar-widget[data-webinar-id="' . $id . '"]';
		$css     .= $selector . '{';
		$css     .= '--webinar-primary:' . $colors['primary'] . ';';
		$css     .= '--webinar-primary-hover:' . $colors['primary_hover'] . ';';
		$css     .= '--webinar-secondary:' . $colors['secondary'] . ';';
		$css     .= '--webinar-accent:' . $colors['accent'] . ';';
		$css     .= '--webinar-bg-main:' . $colors['bg_main'] . ';';
		$css     .= '--webinar-bg-tile:' . $colors['bg_tile'] . ';';
		$css     .= '--webinar-bg-content:' . $colors['bg_content'] . ';';
		$css     .= '--webinar-bg-button:' . $colors['bg_button'] . ';';
		$css     .= '--webinar-border:' . $colors['border'] . ';';
		$css     .= '--webinar-text-primary:' . $colors['text_primary'] . ';';
		$css     .= '--webinar-text-secondary:' . $colors['text_secondary'] . ';';
		$css     .= '--webinar-text-on-primary:' . $colors['text_on_primary'] . ';';
		$css     .= '--webinar-text-muted:' . $colors['text_muted'] . ';';
		$css     .= '--webinar-text-inverse:' . $colors['text_inverse'] . ';';
		$css     .= '--webinar-tab-active:' . $colors['tab_active'] . ';';
		$css     .= '--webinar-timeline-active:' . $colors['timeline_active'] . ';';
		$css     .= '--webinar-eye-counter:' . $colors['eye_counter'] . ';';
		$css     .= '--webinar-badge-bg:' . $colors['badge_bg'] . ';';
		$css     .= '}';

		// Layout schemes (preset or custom CSS), scoped to this instance.
		foreach ( Webinar_Layouts::get_blocks() as $block ) {
			$scheme = ( $data[ $block . '_layout' ] ?? '' ) ?: 'classic';
			$layout = 'custom' === $scheme
				? (string) get_post_meta( $id, '_webinar_' . $block . '_layout_css', true )
				: Webinar_Layouts::get_css( $block, $scheme );
			if ( $layout ) {
				if ( 'custom' === $scheme ) {
					// Custom CSS: user writes relative selectors, we wrap them.
					$css .= self::wrap_custom_css( $layout, $id );
				} else {
					// Preset CSS: already starts with .webinar-widget, just replace.
					$css .= str_replace( '.webinar-widget', $selector, $layout );
				}
			}
		}

		if ( '' === $css ) {
			return '';
		}
		return '<style>' . $css . '</style>';
	}

	/**
	 * Wrap custom CSS selectors in widget scope.
	 *
	 * User writes relative selectors like:
	 *   .webinar-takeaways-grid li { ... }
	 * We wrap them to:
	 *   .webinar-widget[data-webinar-id="123"] .webinar-takeaways-grid li { ... }
	 *
	 * @param string $css   Custom CSS code.
	 * @param int    $id    Webinar ID.
	 * @return string
	 */
	private static function wrap_custom_css( $css, $id ) {
		$scope = '.webinar-widget[data-webinar-id="' . $id . '"]';

		$wrap_list = function ( $list ) use ( $scope ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.listFound -- $list is a common variable name, not using PHP list() construct.
			$out = array();
			foreach ( explode( ',', $list ) as $sel ) {
				$sel = trim( $sel );
				if ( '' === $sel ) {
					continue;
				}
				// If the user wrote .webinar-widget — replace the prefix with scope.
				if ( 0 === strpos( $sel, '.webinar-widget' ) ) {
					$out[] = $scope . substr( $sel, strlen( '.webinar-widget' ) );
				} else {
					// Relative selector — add scope from the top.
					$out[] = $scope . ' ' . $sel;
				}
			}
			return implode( ', ', $out );
		};

		// We wrap selectors in regular rules and inside @media blocks.
		$css = preg_replace_callback(
			'/((?:@media[^{]+\{)?)([^{}@]+)(\{[^{}]*\})/',
			function ( $m ) use ( $wrap_list ) {
				return $m[1] . $wrap_list( $m[2] ) . $m[3];
			},
			$css
		);

		return $css;
	}

	/**
	 * Render the full webinar markup.
	 *
	 * @param int $post_id Webinar post ID.
	 * @return string
	 */
	public static function render( $post_id ) {

		$data = Webinar_Meta::get_webinar_data( $post_id );

		$has_materials = ! empty( $data['materials'] );
		$has_takeaways = ! empty( $data['takeaways'] ) || '' !== (string) $data['key_takeaway'] || '' !== (string) $data['key_message'];
		$has_details   = false;
		foreach ( $data['timeline'] as $chapter ) {
			if ( '' !== (string) $chapter['long_desc'] ) {
				$has_details = true;
				break;
			}
		}

		$video_url = '';
		if ( 'mp4' === $data['video_type'] && $data['video_mp4'] ) {
			$video_url = wp_get_attachment_url( $data['video_mp4'] );
		}
		$manifest_url = '';
		if ( 'hls' === $data['video_type'] && $data['video_hls_manifest'] ) {
			$manifest_url = Webinar_HLS::manifest_url( $data['id'] );
		}
		$poster_url = $data['video_poster'] ? wp_get_attachment_url( $data['video_poster'] ) : '';

		// Chapters payload for the hover preview: rendered as JSON inside
		// the player wrapper so JS never depends on presentation markup.
		$chapters_json = array();
		foreach ( $data['timeline'] as $chapter ) {
			$chapters_json[] = array(
				't'     => self::time_to_seconds( $chapter['time'] ),
				'title' => $chapter['title'],
			);
		}

		ob_start();
		?>
<div class="webinar-widget webinar-theme-<?php echo esc_attr( $data['theme'] ); ?> webinar-timeline-<?php echo esc_attr( $data['timeline_mode'] ?? 'block' ); ?><?php echo ( $has_takeaways || $has_materials ) ? '' : ' webinar-no-tabs'; ?>" data-webinar-id="<?php echo esc_attr( $data['id'] ); ?>" id="webinar-<?php echo esc_attr( $data['id'] ); ?>">
		<?php echo self::instance_styles( $data ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
<div class="webinar-tabs">

	<div class="webinar-header">
		<div class="webinar-header-text">
			<h2 class="webinar-title"><?php echo esc_html( $data['title'] ); ?></h2>
			<?php if ( $data['short_desc'] ) : ?>
				<p class="webinar-subtitle"><?php echo esc_html( $data['short_desc'] ); ?></p>
			<?php endif; ?>
			<div class="webinar-divider"></div>
			<?php if ( $data['full_desc'] ) : ?>
				<p class="webinar-desc"><?php echo esc_html( $data['full_desc'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( $data['pdf'] ) : ?>
			<div class="webinar-header-actions">
				<div class="webinar-materials">
					<a href="<?php echo esc_url( wp_get_attachment_url( $data['pdf'] ) ); ?>" target="_blank" rel="noopener"<?php echo 'large' === ( $data['pdf_icon_size'] ?? '' ) ? ' class="webinar-btn-icon-large"' : ''; ?>>						<?php echo self::icon_html( $data['pdf_icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php echo esc_html( $data['pdf_button_text'] ); ?>
					</a>
				</div>
			</div>
		<?php endif; ?>
	</div>

		<?php if ( $has_takeaways || $has_materials ) : ?>
	<div class="webinar-tabbar">
		<button class="webinar-tab-btn active" data-tab="video"><?php esc_html_e( 'Video', 'webinar-block' ); ?></button>
			<?php if ( $has_takeaways ) : ?>
			<button class="webinar-tab-btn" data-tab="takeaways"><?php esc_html_e( 'Takeaways', 'webinar-block' ); ?></button>
		<?php endif; ?>
			<?php if ( $has_materials ) : ?>
			<button class="webinar-tab-btn" data-tab="materials"><?php esc_html_e( 'Materials', 'webinar-block' ); ?></button>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="webinar-tab-content active" data-tab="video" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--20)">
		<div class="webinar-video-cols">
			<div class="webinar-col-video">
				<div class="webinar-player-wrapper" data-player-type="<?php echo esc_attr( $data['player_type'] ?? 'custom' ); ?>" data-webinar-id="<?php echo esc_attr( $data['id'] ); ?>" data-preview-std="<?php echo esc_url( $data['preview_std'] ?? '' ); ?>" data-preview-small="<?php echo esc_url( $data['preview_small'] ?? '' ); ?>">
					<script type="application/json" class="webinar-chapters-data"><?php echo wp_json_encode( $chapters_json, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE ); ?></script>
					<video
						controls
						data-video-type="<?php echo esc_attr( $data['video_type'] ); ?>"
						<?php if ( $poster_url ) : ?>
							poster="<?php echo esc_url( $poster_url ); ?>"
						<?php endif; ?>
						<?php if ( 'mp4' === $data['video_type'] && $video_url ) : ?>
							src="<?php echo esc_url( $video_url ); ?>"
						<?php endif; ?>
						<?php if ( 'hls' === $data['video_type'] && $manifest_url ) : ?>
							data-manifest="<?php echo esc_url( $manifest_url ); ?>"
							data-base-url="<?php echo esc_url( $data['video_hls_base_url'] ); ?>"
						<?php endif; ?>
					></video>
					<?php if ( 'custom' === ( $data['player_type'] ?? 'custom' ) ) : ?>
					<button type="button" class="webinar-big-play" aria-label="<?php esc_attr_e( 'Play', 'webinar-block' ); ?>">
						<?php echo self::SVG_PLAY; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</button>
					<div class="webinar-play-flash" aria-hidden="true" hidden>
						<?php echo self::SVG_PLAY; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
					<div class="webinar-pause-flash" aria-hidden="true" hidden>
						<?php echo self::SVG_PAUSE; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
					<button type="button" class="webinar-center-play-pause" aria-label="<?php esc_attr_e( 'Play', 'webinar-block' ); ?>">
						<?php echo self::SVG_PLAY; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</button>
					<button type="button" class="webinar-btn webinar-btn-pip" aria-label="<?php esc_attr_e( 'Picture-in-Picture', 'webinar-block' ); ?>" hidden>
						<?php echo self::SVG_PIP; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</button>
					<div class="webinar-custom-controls">
						<div class="webinar-controls-top">
							<div class="webinar-views webinar-views--overlay">
								<?php echo self::EYE_SVG; // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<span class="webinar-views-count"><?php echo esc_html( self::format_views( $data['views'] ) ); ?></span>
							</div>
							<button type="button" class="webinar-btn webinar-btn-skip-back" aria-label="<?php esc_attr_e( 'Skip backward 10 seconds', 'webinar-block' ); ?>" data-skip="-10">
								<?php echo self::SVG_SKIP_BACK; // phpcs:ignore WordPress.Security.EscapeOutput ?>
							</button>
							<div class="webinar-time-display">
								<span class="webinar-time-current">0:00</span>
								<span class="webinar-time-separator">/</span>
								<span class="webinar-time-duration">0:00</span>
							</div>
							<button type="button" class="webinar-btn webinar-btn-skip-forward" aria-label="<?php esc_attr_e( 'Skip forward 10 seconds', 'webinar-block' ); ?>" data-skip="10">
								<?php echo self::SVG_SKIP_FORWARD; // phpcs:ignore WordPress.Security.EscapeOutput ?>
							</button>
						</div>
						<div class="webinar-progress-bar" role="slider" aria-label="<?php esc_attr_e( 'Progress', 'webinar-block' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" tabindex="0">
							<div class="webinar-progress-segments"></div>
							<div class="webinar-progress-thumb"></div>
						</div>
						<div class="webinar-controls-bottom">
							<div class="webinar-controls-left">
								<button type="button" class="webinar-btn webinar-btn-play-pause" aria-label="<?php esc_attr_e( 'Play', 'webinar-block' ); ?>">
									<?php echo self::SVG_PLAY; // phpcs:ignore WordPress.Security.EscapeOutput ?>
								</button>
								<button type="button" class="webinar-btn webinar-btn-volume" aria-label="<?php esc_attr_e( 'Mute', 'webinar-block' ); ?>">
									<?php echo self::SVG_VOLUME; // phpcs:ignore WordPress.Security.EscapeOutput ?>
								</button>
								<div class="webinar-volume-slider">
									<input type="range" class="webinar-volume-input" min="0" max="1" step="0.05" value="1" aria-label="<?php esc_attr_e( 'Volume', 'webinar-block' ); ?>" />
								</div>
							</div>
							<div class="webinar-controls-right">
								<span class="webinar-speed-holder">
									<button type="button" class="webinar-btn webinar-btn-speed" aria-label="<?php esc_attr_e( 'Playback speed', 'webinar-block' ); ?>">
										<?php echo self::SVG_SPEED; // phpcs:ignore WordPress.Security.EscapeOutput ?>
										<span class="webinar-speed-label">1x</span>
									</button>
									<div class="webinar-speed-menu" role="menu" aria-label="<?php esc_attr_e( 'Playback speed', 'webinar-block' ); ?>">
										<button type="button" class="webinar-speed-option" role="menuitem" data-speed="1">1x</button>
										<button type="button" class="webinar-speed-option" role="menuitem" data-speed="1.25">1.25x</button>
										<button type="button" class="webinar-speed-option" role="menuitem" data-speed="1.5">1.5x</button>
										<button type="button" class="webinar-speed-option" role="menuitem" data-speed="2">2x</button>
									</div>
								</span>
								<?php /* The quality button is injected by JS right after the speed holder. */ ?>
								<?php if ( ! empty( $data['show_share'] ) ) : ?>
								<span class="webinar-share-holder">
									<button type="button" class="webinar-btn webinar-btn-share" aria-label="<?php esc_attr_e( 'Share', 'webinar-block' ); ?>">
										<?php echo self::SVG_SHARE; // phpcs:ignore WordPress.Security.EscapeOutput ?>
									</button>
									<div class="webinar-share-menu" role="menu" aria-label="<?php esc_attr_e( 'Share', 'webinar-block' ); ?>">
										<button type="button" class="webinar-share-option" role="menuitem" data-share="video"><?php esc_html_e( 'Link to video', 'webinar-block' ); ?></button>
										<button type="button" class="webinar-share-option" role="menuitem" data-share="moment"><?php esc_html_e( 'Link to moment', 'webinar-block' ); ?></button>
									</div>
									<div class="webinar-share-toast" role="status" hidden><?php esc_html_e( 'Link copied to clipboard', 'webinar-block' ); ?></div>
								</span>
								<?php endif; ?>
								<button type="button" class="webinar-btn webinar-btn-fullscreen" aria-label="<?php esc_attr_e( 'Fullscreen', 'webinar-block' ); ?>">
									<?php echo self::SVG_FULLSCREEN; // phpcs:ignore WordPress.Security.EscapeOutput ?>
								</button>
							</div>
						</div>
					</div>
					<?php endif; ?>
					<div class="webinar-spinner" role="status" aria-label="<?php esc_attr_e( 'Buffering', 'webinar-block' ); ?>" aria-live="polite" hidden>
						<span class="webinar-spinner-dot"></span>
						<span class="webinar-spinner-dot"></span>
						<span class="webinar-spinner-dot"></span>
					</div>
					<div class="webinar-error-overlay" role="alert" hidden>
						<div class="webinar-error-box">
							<p class="webinar-error-text"><?php esc_html_e( 'Playback error', 'webinar-block' ); ?></p>
							<button type="button" class="webinar-btn webinar-btn-retry" aria-label="<?php esc_attr_e( 'Retry', 'webinar-block' ); ?>">
								<?php echo self::SVG_RETRY; // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<span><?php esc_html_e( 'Retry', 'webinar-block' ); ?></span>
							</button>
						</div>
					</div>
				</div>
				<?php if ( 'custom' !== ( $data['player_type'] ?? 'custom' ) ) : ?>
				<div class="webinar-views">
					<?php echo self::EYE_SVG; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="webinar-views-count"><?php echo esc_html( self::format_views( $data['views'] ) ); ?></span>
				</div>
				<?php endif; ?>
			</div>			<?php if ( ! empty( $data['timeline'] ) ) : ?>
			<div class="webinar-col-timeline">
				<ul class="webinar-timeline-list">
					<?php foreach ( $data['timeline'] as $index => $chapter ) : ?>
						<li class="webinar-timeline-item" data-time="<?php echo esc_attr( self::time_to_seconds( $chapter['time'] ) ); ?>" data-index="<?php echo esc_attr( $index ); ?>">
							<a href="#" class="webinar-timeline-link">
								<span class="webinar-timeline-time"><?php echo esc_html( $chapter['time'] ); ?></span>
								<span class="webinar-timeline-title"><?php echo esc_html( $chapter['title'] ); ?></span>
							</a>
							<?php if ( $chapter['short_desc'] ) : ?>
								<p class="webinar-timeline-desc"><?php echo esc_html( $chapter['short_desc'] ); ?></p>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>
		</div>

		<?php if ( $has_details ) : ?>
		<div class="webinar-content">
			<details class="webinar-details-wrap">
				<summary class="webinar-details-summary">
					<span><?php esc_html_e( 'Detailed content', 'webinar-block' ); ?></span>
					<?php echo self::CHEVRON_SVG; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</summary>
				<div class="webinar-details-body">
					<div class="webinar-details-columns">
						<?php foreach ( $data['timeline'] as $index => $chapter ) : ?>
							<?php
							if ( '' === (string) $chapter['long_desc'] ) {
								continue; }
							?>
							<div class="webinar-detail-block" data-time="<?php echo esc_attr( self::time_to_seconds( $chapter['time'] ) ); ?>" data-index="<?php echo esc_attr( $index ); ?>">
								<h4>
									<a href="#" class="webinar-detail-time-link" data-time="<?php echo esc_attr( self::time_to_seconds( $chapter['time'] ) ); ?>" data-index="<?php echo esc_attr( $index ); ?>">
										<span class="webinar-detail-time"><?php echo esc_html( $chapter['time'] ); ?></span>
									</a>
									<?php echo esc_html( $chapter['title'] ); ?>
								</h4>

								<p><?php echo esc_html( $chapter['long_desc'] ); ?></p>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="webinar-details-summary webinar-details-close">
						<span><?php esc_html_e( 'Detailed content', 'webinar-block' ); ?></span>
						<?php echo self::CHEVRON_SVG; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</button>
				</div>
			</details>
		</div>
		<?php endif; ?>
	</div>

		<?php if ( $has_materials ) : ?>
	<div class="webinar-tab-content" data-tab="materials">
		<div class="webinar-materials-list">
			<?php foreach ( $data['materials'] as $material ) : ?>
				<div class="webinar-material-item">
					<div class="webinar-materials">
						<a href="<?php echo esc_url( wp_get_attachment_url( $material['file'] ) ); ?>" target="_blank" rel="noopener"<?php echo 'large' === ( $material['icon_size'] ?? '' ) ? ' class="webinar-btn-icon-large"' : ''; ?>>
							<?php echo self::icon_html( $material['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<?php
							$mat_label = isset( $material['button_text'] ) && '' !== (string) $material['button_text']
								? $material['button_text']
								: __( 'Download PDF', 'webinar-block' );
							echo esc_html( $mat_label );
							?>
						</a>
					</div>
					<?php if ( ! empty( $material['description'] ) ) : ?>
						<p class="webinar-material-desc"><?php echo esc_html( $material['description'] ); ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>


		<?php if ( $has_takeaways ) : ?>
	<div class="webinar-tab-content" data-tab="takeaways" style="margin-top:0;margin-bottom:0;padding-top:0;padding-right:var(--wp--preset--spacing--20);padding-bottom:0;padding-left:var(--wp--preset--spacing--20)">
			<?php if ( $data['key_takeaway'] ) : ?>
			<p class="webinar-key-takeaway"><?php echo esc_html( $data['key_takeaway'] ); ?></p>
		<?php endif; ?>
			<?php if ( ! empty( $data['takeaways'] ) ) : ?>
			<ul class="webinar-takeaways-grid">
				<?php foreach ( $data['takeaways'] as $takeaway ) : ?>
					<li class="has-medium-font-size"><?php echo esc_html( $takeaway ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php endif; ?>

</div>
</div>
		<?php
		return ob_get_clean();
	}
}



/**
 * Template tag: echo a webinar by ID.
 *
 * @param int $post_id Webinar post ID.
 */
function render_webinar( $post_id ) {
	echo Webinar_Render::render( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput
}

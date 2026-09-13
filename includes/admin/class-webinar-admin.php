<?php
/**
 * Webinar admin UI: list columns, meta boxes and save handler.
 *
 * Phase 2A: general fields, video sources, presentation.
 * Repeaters (timeline / takeaways / materials), themes and
 * import/export arrive in Phase 2B.
 *
 * @package Webinar_Block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything that renders in wp-admin for the webinar post type.
 */
class Webinar_Admin {



	/**
	 * Default download icon SVG (used in admin preview and front-end
	 * when no custom icon is selected).
	 *
	 * @return string
	 */
	public static function default_icon_svg() {
		return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>';
	}


	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_webinar', array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'manage_webinar_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_webinar_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_webinar_export_settings', array( __CLASS__, 'export_settings' ) );
		add_action( 'admin_post_webinar_export_scheme', array( __CLASS__, 'export_scheme' ) );
		add_action( 'wp_ajax_webinar_import_scheme', array( __CLASS__, 'import_scheme' ) );
		add_action( 'admin_post_webinar_export_theme', array( __CLASS__, 'export_theme' ) );
		add_action( 'wp_ajax_webinar_import_settings', array( __CLASS__, 'import_settings' ) );
		add_filter( 'upload_mimes', array( __CLASS__, 'allow_hls_mimes' ) );
		add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'fix_playlist_filetype' ), 10, 5 );
	}

	/*
	------------------------------------------------------------------
	* Assets
	* ----------------------------------------------------------------
	*/

	/**
	 * Enqueue admin assets on webinar screens only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		unset( $hook );
		$screen = get_current_screen();

		if ( ! $screen || 'webinar' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'webinar-block-admin',
			WEBINAR_BLOCK_PLUGIN_URL . 'build/admin/admin-style.css',
			array(),
			WEBINAR_BLOCK_VERSION
		);

		wp_enqueue_style(
			'webinar-block-preview',
			WEBINAR_BLOCK_PLUGIN_URL . 'build/admin/admin-webinar-preview.css',
			array(),
			WEBINAR_BLOCK_VERSION
		);

		wp_enqueue_script(
			'webinar-block-admin',
			WEBINAR_BLOCK_PLUGIN_URL . 'build/admin/admin.js',
			array(),
			WEBINAR_BLOCK_VERSION,
			true
		);

		wp_localize_script(
			'webinar-block-admin',
			'webinarBlockAdmin',
			array(
				'chooseVideo'          => __( 'Choose video', 'webinar-block' ),
				'choosePoster'         => __( 'Choose poster', 'webinar-block' ),
				'chooseManifest'       => __( 'Choose manifest', 'webinar-block' ),
				'chooseFile'           => __( 'Choose file', 'webinar-block' ),
				'chooseIcon'           => __( 'Choose icon', 'webinar-block' ),
				'defaultIcon'          => self::default_icon_svg(),
				'defaultLabel'         => __( '(default)', 'webinar-block' ),
				'confirmRemove'        => __( 'Remove this row?', 'webinar-block' ),
				'nonce'                => wp_create_nonce( 'webinar_nonce' ),
				'postId'               => isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only for localization payload.
				'postEditBase'         => admin_url( 'post.php' ),
				'imported'             => __( 'Imported. Reloading…', 'webinar-block' ),
				'importError'          => __( 'Import failed.', 'webinar-block' ),
				'importModeQuestion'   => __( 'What should happen to the current rows?', 'webinar-block' ),
				'importModeAppend'     => __( 'Append to current rows', 'webinar-block' ),
				'importModeReplace'    => __( 'Replace current rows', 'webinar-block' ),
				'importModeCancel'     => __( 'Cancel', 'webinar-block' ),
				'importFormatError'    => __( 'File format does not match this block.', 'webinar-block' ),
				'updateKeyQuestion'    => __( 'Replace the key takeaway?', 'webinar-block' ),
				'yesLabel'             => __( 'Yes', 'webinar-block' ),
				'noLabel'              => __( 'No', 'webinar-block' ),
				'hlsTemplatesRequired' => __( 'Hash template and signature template are required for HLS.', 'webinar-block' ),
				'presetColors'         => Webinar_Themes::preset_colors(),
				'layoutPresets'        => self::get_layout_presets_for_js(),

				'previewData'          => array(
					'timeline'  => array(
						array(
							'time'       => '00:00',
							'title'      => __( 'Introduction', 'webinar-block' ),
							'short_desc' => __( 'Welcome and overview', 'webinar-block' ),
						),
						array(
							'time'       => '05:30',
							'title'      => __( 'Main Topic', 'webinar-block' ),
							'short_desc' => __( 'Key concepts explained', 'webinar-block' ),
						),
						array(
							'time'       => '12:45',
							'title'      => __( 'Case Study', 'webinar-block' ),
							'short_desc' => __( 'Real-world example', 'webinar-block' ),
						),
					),
					'takeaways' => array(
						__( 'First important takeaway from the webinar', 'webinar-block' ),
						__( 'Second key point to remember', 'webinar-block' ),
						__( 'Third actionable insight', 'webinar-block' ),
						__( 'Fourth conclusion for practice', 'webinar-block' ),
					),
					'materials' => array(
						array(
							'button_text' => __( 'Download PDF', 'webinar-block' ),
							'description' => __( 'Main presentation slides', 'webinar-block' ),
						),
						array(
							'button_text' => __( 'Get Checklist', 'webinar-block' ),
							'description' => __( 'Step-by-step guide', 'webinar-block' ),
						),
					),
					'details'   => array(
						array(
							'time'      => '00:00',
							'title'     => __( 'Introduction', 'webinar-block' ),
							'long_desc' => __( 'Detailed explanation of the introduction section with key points and context.', 'webinar-block' ),
						),
						array(
							'time'      => '05:30',
							'title'     => __( 'Main Topic', 'webinar-block' ),
							'long_desc' => __( 'In-depth analysis of the main topic with examples and practical applications.', 'webinar-block' ),
						),
					),
				),
				'i18n'                 => array(
					'previewNotAvailable'    => __( 'Preview not available', 'webinar-block' ),
					'previewUpdated'         => __( 'Preview updated', 'webinar-block' ),
					'previewDetailedContent' => __( 'Detailed content', 'webinar-block' ),
					'cssDangerousPattern'    => __( 'Dangerous pattern detected', 'webinar-block' ),
					'cssSyntaxError'         => __( 'Syntax error: mismatched braces', 'webinar-block' ),
					'cssUnknownTag'          => __( 'Unknown element in selector', 'webinar-block' ),
					'cssBadSelector'         => __( 'Invalid selector', 'webinar-block' ),
				),
			)
		);
	}

	/*
	------------------------------------------------------------------
	* Meta boxes
	* ----------------------------------------------------------------
	*/

	/**
	 * Register meta boxes.
	 */
	public static function add_meta_boxes() {
		add_meta_box( 'webinar_general', __( 'Webinar details', 'webinar-block' ), array( __CLASS__, 'render_general' ), 'webinar', 'normal', 'high' );
		add_meta_box( 'webinar_video', __( 'Video', 'webinar-block' ), array( __CLASS__, 'render_video' ), 'webinar', 'normal', 'high' );
		add_meta_box( 'webinar_presentation', __( 'Presentation', 'webinar-block' ), array( __CLASS__, 'render_presentation' ), 'webinar', 'normal', 'default' );

		add_meta_box( 'webinar_timeline', __( 'Timeline', 'webinar-block' ), array( __CLASS__, 'render_timeline' ), 'webinar', 'normal', 'default' );
		add_meta_box( 'webinar_takeaways', __( 'Takeaways', 'webinar-block' ), array( __CLASS__, 'render_takeaways' ), 'webinar', 'normal', 'default' );
		add_meta_box( 'webinar_materials', __( 'Materials', 'webinar-block' ), array( __CLASS__, 'render_materials' ), 'webinar', 'normal', 'default' );

		add_meta_box( 'webinar_theme', __( 'Theme', 'webinar-block' ), array( __CLASS__, 'render_theme' ), 'webinar', 'side', 'default' );
		add_meta_box( 'webinar_schemes', __( 'Layout schemes', 'webinar-block' ), array( __CLASS__, 'render_schemes' ), 'webinar', 'normal', 'default' );
		add_meta_box( 'webinar_io', __( 'Import / Export', 'webinar-block' ), array( __CLASS__, 'render_io' ), 'webinar', 'side', 'low' );
	}

	/**
	 * General meta box: counters and descriptions.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_general( $post ) {
		wp_nonce_field( 'webinar_save', 'webinar_nonce' );

		$views       = get_post_meta( $post->ID, '_webinar_views', true );
		$short_desc  = get_post_meta( $post->ID, '_webinar_short_desc', true );
		$full_desc   = get_post_meta( $post->ID, '_webinar_full_desc', true );
		$key_message = get_post_meta( $post->ID, '_webinar_key_message', true );
		?>
		<p>
			<label for="_webinar_views"><?php esc_html_e( 'View counter (editable)', 'webinar-block' ); ?></label><br>
			<input type="number" min="0" id="_webinar_views" name="_webinar_views" value="<?php echo esc_attr( $views ); ?>" class="webinar-input-small" />
		</p>
		<p>
			<label for="_webinar_short_desc"><?php esc_html_e( 'Short description', 'webinar-block' ); ?></label><br>
			<input type="text" id="_webinar_short_desc" name="_webinar_short_desc" value="<?php echo esc_attr( $short_desc ); ?>" class="large-text" />
		</p>
		<p>
			<label for="_webinar_full_desc"><?php esc_html_e( 'Full description', 'webinar-block' ); ?></label><br>
			<textarea id="_webinar_full_desc" name="_webinar_full_desc" rows="4" class="large-text"><?php echo esc_textarea( $full_desc ); ?></textarea>
		</p>
		<p>
			<label for="_webinar_key_message"><?php esc_html_e( 'Key message', 'webinar-block' ); ?></label><br>
			<textarea id="_webinar_key_message" name="_webinar_key_message" rows="4" class="large-text"><?php echo esc_textarea( $key_message ); ?></textarea>
		</p>
		<?php
	}

	/**
	 * Video meta box: MP4 / HLS sources and poster.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_video( $post ) {
		$player_type   = get_post_meta( $post->ID, '_webinar_player_type', true ) ?: 'custom';
		$show_share    = get_post_meta( $post->ID, '_webinar_show_share', true ) ?: '1';
		$preview_std   = get_post_meta( $post->ID, '_webinar_preview_std', true );
		$preview_small = get_post_meta( $post->ID, '_webinar_preview_small', true );
		$timeline_mode = get_post_meta( $post->ID, '_webinar_timeline_mode', true ) ?: 'block';
		$type          = get_post_meta( $post->ID, '_webinar_video_type', true ) ?: 'mp4';
		$mp4           = get_post_meta( $post->ID, '_webinar_video_mp4', true );
		$manifest      = get_post_meta( $post->ID, '_webinar_video_hls_manifest', true );
		$base_url      = get_post_meta( $post->ID, '_webinar_video_hls_base_url', true );
		$hls_key       = get_post_meta( $post->ID, '_webinar_video_hls_key', true );
		$hls_ttl       = get_post_meta( $post->ID, '_webinar_video_hls_ttl', true ) ?: 7200;
		$hash_tmpl     = get_post_meta( $post->ID, '_webinar_video_hls_hash_template', true ) ?: '%expires%%path% %key%';
		$sign_tmpl     = get_post_meta( $post->ID, '_webinar_video_hls_sign_template', true ) ?: '?md5=%md5%&expires=%expires%';
		$poster        = get_post_meta( $post->ID, '_webinar_video_poster', true );
		?>
		<p>
			<label for="_webinar_player_type"><?php esc_html_e( 'Player interface', 'webinar-block' ); ?></label><br>
			<label><input type="radio" name="_webinar_player_type" value="native" <?php checked( $player_type, 'native' ); ?> /> <?php esc_html_e( 'Native browser controls', 'webinar-block' ); ?></label>
			&nbsp;
			<label><input type="radio" name="_webinar_player_type" value="custom" <?php checked( $player_type, 'custom' ); ?> /> <?php esc_html_e( 'Custom branded player', 'webinar-block' ); ?></label>
			<br><span class="description"><?php esc_html_e( 'Custom player uses branded controls matching your theme. Native uses browser default controls.', 'webinar-block' ); ?></span>
		</p>
		<div class="webinar-admin-row" data-player-type="custom" <?php echo 'custom' !== $player_type ? 'style="display:none"' : ''; ?>>
			<p>
				<label><input type="checkbox" name="_webinar_show_share" value="1" <?php checked( $show_share, '1' ); ?> /> <?php esc_html_e( 'Show share links', 'webinar-block' ); ?></label>
				<br><span class="description"><?php esc_html_e( 'Adds a share button with "Link to video" and "Link to moment" actions. Custom player only.', 'webinar-block' ); ?></span>
			</p>
			<p>
				<label for="_webinar_preview_std"><?php esc_html_e( 'Preview storyboard URL (standard), recommended frame size 284 x 160', 'webinar-block' ); ?></label><br>
				<input type="url" class="widefat" id="_webinar_preview_std" name="_webinar_preview_std" value="<?php echo esc_attr( $preview_std ); ?>" placeholder="https://your_website/.../preview_hd.vtt" />
			</p>
			<p>
				<label for="_webinar_preview_small"><?php esc_html_e( 'Preview storyboard URL (small), recommended frame size 192 x 108', 'webinar-block' ); ?></label><br>
				<input type="url" class="widefat" id="_webinar_preview_small" name="_webinar_preview_small" value="<?php echo esc_attr( $preview_small ); ?>" placeholder="https://your_website/.../preview_sd.vtt" />
				<br><span class="description"><?php esc_html_e( 'WebVTT storyboard files with #xywh tiles. Standard is used on wide screens with a fast connection; small on narrow screens or slow networks. Custom player only.', 'webinar-block' ); ?></span>
			</p>
		</div>
		<p>
			<label><input type="radio" name="_webinar_video_type" value="mp4" <?php checked( $type, 'mp4' ); ?> /> <?php esc_html_e( 'MP4 file', 'webinar-block' ); ?></label>
			&nbsp;
			<label><input type="radio" name="_webinar_video_type" value="hls" <?php checked( $type, 'hls' ); ?> /> <?php esc_html_e( 'HLS stream', 'webinar-block' ); ?></label>
		</p>

		<div class="webinar-admin-row" data-video-type="mp4" <?php echo 'hls' === $type ? 'style="display:none"' : ''; ?>>
			<?php self::media_field( '_webinar_video_mp4', __( 'MP4 video', 'webinar-block' ), $mp4, 'video' ); ?>
		</div>

		<div class="webinar-admin-row" data-video-type="hls" <?php echo 'hls' === $type ? '' : 'style="display:none"'; ?>>
			<?php self::media_field( '_webinar_video_hls_manifest', __( 'Master manifest (.m3u8)', 'webinar-block' ), $manifest, 'manifest' ); ?>
			<p>
				<label for="_webinar_video_hls_base_url"><?php esc_html_e( 'Segment base URL', 'webinar-block' ); ?></label><br>
				<input type="url" id="_webinar_video_hls_base_url" name="_webinar_video_hls_base_url" value="<?php echo esc_attr( $base_url ); ?>" class="large-text" placeholder="https://cdn.example.com/webinars/sorm/" />
				<br><span class="description"><?php esc_html_e( 'Base URL used to resolve HLS segments and child manifests.', 'webinar-block' ); ?></span>
			</p>
			<p>
				<label for="_webinar_video_hls_key"><?php esc_html_e( 'Segment signing key', 'webinar-block' ); ?></label><br>
				<input type="text" id="_webinar_video_hls_key" name="_webinar_video_hls_key" value="<?php echo esc_attr( $hls_key ); ?>" class="regular-text" autocomplete="off" />
				<br><span class="description"><?php esc_html_e( 'Secret key used by WordPress to sign HLS segment names (validated by nginx).', 'webinar-block' ); ?></span>
			</p>
			<p>
				<label for="_webinar_video_hls_ttl"><?php esc_html_e( 'Signature TTL (seconds)', 'webinar-block' ); ?></label><br>
				<input type="number" min="60" step="60" id="_webinar_video_hls_ttl" name="_webinar_video_hls_ttl" value="<?php echo esc_attr( $hls_ttl ); ?>" class="webinar-input-small" />
				<br><span class="description"><?php esc_html_e( 'How long a signed URL stays valid. Match your nginx secure_link config.', 'webinar-block' ); ?></span>
			</p>
			<div class="webinar-hls-signing">
				<div class="webinar-hls-presets">
					<?php
					$preset  = get_post_meta( $post->ID, '_webinar_video_hls_preset', true ) ?: 'nginx';
					$presets = array(
						'nginx'      => __( 'Nginx secure link', 'webinar-block' ),
						'cloudfront' => __( 'AWS CloudFront style', 'webinar-block' ),
						'fastly'     => __( 'Fastly Token Auth style', 'webinar-block' ),
						'custom'     => __( 'Custom scheme', 'webinar-block' ),
					);
					foreach ( $presets as $id => $label ) :
						?>
						<label><input type="radio" name="_webinar_video_hls_preset" value="<?php echo esc_attr( $id ); ?>" <?php checked( $preset, $id ); ?> /> <?php echo esc_html( $label ); ?></label><br>
					<?php endforeach; ?>
					<span class="description"><?php esc_html_e( 'Hash engine: MD5 (URL-safe base64). The CDN must verify the same string.', 'webinar-block' ); ?></span>
				</div>
				<div class="webinar-hls-templates">
					<p>
						<label for="_webinar_video_hls_hash_template"><?php esc_html_e( 'Hash template', 'webinar-block' ); ?> *<br>
							<input type="text" id="_webinar_video_hls_hash_template" name="_webinar_video_hls_hash_template" value="<?php echo esc_attr( $hash_tmpl ); ?>" class="large-text" />
						</label>
					</p>
					<p>
						<label for="_webinar_video_hls_sign_template"><?php esc_html_e( 'Signature query template', 'webinar-block' ); ?> *<br>
							<input type="text" id="_webinar_video_hls_sign_template" name="_webinar_video_hls_sign_template" value="<?php echo esc_attr( $sign_tmpl ); ?>" class="large-text" />
						</label>
					</p>

					<span class="description">
					<?php
						// translators: 1: %expires% in hash template, 2: %expires% in query template.
						esc_html_e( 'Variables: %1$expires%, %path%, %key%, %t% (hash); %md5%, %2$expires%, %t%, %path% (query).', 'webinar-block' );
					?>
					</span>
				</div>
			</div>
		</div>

		<?php self::media_field( '_webinar_video_poster', __( 'Poster image', 'webinar-block' ), $poster, 'poster' ); ?>
		<p>
			<label><?php esc_html_e( 'Timeline presentation', 'webinar-block' ); ?></label><br>
			<label><input type="radio" name="_webinar_timeline_mode" value="block" <?php checked( $timeline_mode, 'block' ); ?> /> <?php esc_html_e( 'Show separate timeline block', 'webinar-block' ); ?></label>
			&nbsp;
			<label><input type="radio" name="_webinar_timeline_mode" value="bar" <?php checked( $timeline_mode, 'bar' ); ?> /> <?php esc_html_e( 'Timeline only in progress bar', 'webinar-block' ); ?></label>
			<br><span class="description"><?php esc_html_e( 'Default: separate timeline block beside the video. "Only in progress bar" hides the side block and lets the video take the full width.', 'webinar-block' ); ?></span>
		</p>
		<?php
	}

	/**
	 * Presentation meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_presentation( $post ) {
		$pdf      = get_post_meta( $post->ID, '_webinar_pdf', true );
		$btn_text = get_post_meta( $post->ID, '_webinar_pdf_button_text', true ) ?: 'Download PDF';
		$icon     = get_post_meta( $post->ID, '_webinar_pdf_icon', true );
		?>
		<?php self::media_field( '_webinar_pdf', __( 'Presentation file', 'webinar-block' ), $pdf, 'file' ); ?>
		<p>
			<label for="_webinar_pdf_button_text"><?php esc_html_e( 'Button label', 'webinar-block' ); ?></label><br>
			<input type="text" id="_webinar_pdf_button_text" name="_webinar_pdf_button_text" value="<?php echo esc_attr( $btn_text ); ?>" class="regular-text" />
		</p>
		<?php
		$icon_size = get_post_meta( $post->ID, '_webinar_pdf_icon_size', true ) ?: 'normal';
		self::media_field( '_webinar_pdf_icon', __( 'Button icon (SVG)', 'webinar-block' ), $icon, 'icon', null, '_webinar_pdf_icon_size', $icon_size );
		?>
		<?php
	}

	/**
	 * Render a hidden input + media library buttons + live preview.
	 *
	 * Preview depends on the field kind: small box for icons,
	 * medium image for posters, embedded player for videos,
	 * file name for plain files.
	 *
	 * For icon fields you can additionally render a pair of size radios
	 * ("Normal" 16 px / "Large" 28 px). The size is per-button — each
	 * presenter/material row may pick its own. When passed, the radios
	 * are rendered right under the preview and share the same line as
	 * the preview block. The preview container gets the modifier class
	 * `webinar-preview--large` when the stored value is "large";
	 * switching the radios toggles that class live (admin.js).
	 *
	 * @param string      $id         Input id (and preview target).
	 * @param string      $label      Field label.
	 * @param int         $value      Stored attachment ID.
	 * @param string      $kind       Media frame kind: video|poster|manifest|file|icon.
	 * @param string|null $name       Optional input name (defaults to $id).
	 * @param string|null $size_name  Optional name attribute for the size radios.
	 *                                Pass only for icon fields where a size choice
	 *                                makes sense; omit for poster/video/file.
	 * @param string      $size_value Current stored size ("normal" or "large").
	 *                                Only used when $size_name is provided.
	 */
	private static function media_field( $id, $label, $value, $kind, $name = null, $size_name = null, $size_value = 'normal' ) {
		if ( null === $name ) {
			$name = $id;
		}
		$url           = $value ? wp_get_attachment_url( (int) $value ) : '';
		$preview_class = ( 'icon' === $kind && 'large' === $size_value ) ? ' webinar-preview--large' : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label><br>
			<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
			<button type="button" class="button webinar-media-select" data-kind="<?php echo esc_attr( $kind ); ?>" data-target="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Choose from library', 'webinar-block' ); ?></button>
			<button type="button" class="button-link webinar-media-clear" data-target="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Clear', 'webinar-block' ); ?></button>
		</p>
		<div class="webinar-media-preview<?php echo esc_attr( $preview_class ); ?>" data-target="<?php echo esc_attr( $id ); ?>" data-kind="<?php echo esc_attr( $kind ); ?>">
			<?php echo self::media_preview_html( $url, $kind ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php if ( 'icon' === $kind && $size_name ) : ?>
			<div class="webinar-icon-size" data-preview-target="<?php echo esc_attr( $id ); ?>">
				<label><input type="radio" name="<?php echo esc_attr( $size_name ); ?>" value="normal" <?php checked( $size_value, 'normal' ); ?> /> <?php esc_html_e( 'Normal (16 px)', 'webinar-block' ); ?></label>
				<label><input type="radio" name="<?php echo esc_attr( $size_name ); ?>" value="large" <?php checked( $size_value, 'large' ); ?> /> <?php esc_html_e( 'Large (28 px)', 'webinar-block' ); ?></label>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Build the preview markup for a selected attachment.
	 *
	 * @param string $url  Attachment URL.
	 * @param string $kind Field kind.
	 * @return string
	 */
	private static function media_preview_html( $url, $kind ) {
		// Icon field always shows something: a custom icon or the default one.
		if ( 'icon' === $kind && ! $url ) {
			return '<div class="webinar-preview-default">'
				. '<span class="webinar-preview-icon">' . self::default_icon_svg() . '</span>'
				. '<span class="description">' . esc_html__( '(default)', 'webinar-block' ) . '</span>'
				. '</div>';
		}

		if ( ! $url ) {
			return '';
		}

		if ( 'icon' === $kind ) {
			return '<img src="' . esc_url( $url ) . '" alt="" class="webinar-preview-icon" />';
		}

		if ( 'poster' === $kind ) {
			return '<img src="' . esc_url( $url ) . '" alt="" class="webinar-preview-image" />';
		}

		if ( 'video' === $kind ) {
			return '<video src="' . esc_url( $url ) . '" class="webinar-preview-video" controls muted preload="metadata"></video>';
		}

		// manifest / file: show the file name only.
		return '<span class="description">' . esc_html( basename( $url ) ) . '</span>';
	}


	/*
	 * ----------------------------------------------------------------
	 * Timeline meta box (repeater).
	 * ----------------------------------------------------------------
	 */

	/**
	 * Timeline chapters repeater.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_timeline( $post ) {
		$timeline = get_post_meta( $post->ID, '_webinar_timeline', true );
		$timeline = is_array( $timeline ) ? $timeline : array();
		?>
		<div class="webinar-repeater" data-repeater="timeline">
			<div class="webinar-repeater-rows">
				<?php
				foreach ( $timeline as $i => $row ) {
					self::timeline_row( $i, $row );
				}
				?>
			</div>
			<p class="webinar-repeater-actions">
				<button type="button" class="button webinar-repeater-add"><?php esc_html_e( 'Add chapter', 'webinar-block' ); ?></button>
				<button type="button" class="button webinar-block-export" data-block="timeline"><?php esc_html_e( 'Export block', 'webinar-block' ); ?></button>
				<button type="button" class="button webinar-block-import-btn" data-block="timeline"><?php esc_html_e( 'Import block', 'webinar-block' ); ?></button>
				<input type="file" accept=".json,application/json" class="webinar-block-import-file" style="display:none" />
			</p>
		</div>
		<template id="webinar-tpl-timeline"><?php self::timeline_row( '__INDEX__', array() ); ?></template>
		<?php
	}

	/**
	 * One timeline chapter row.
	 *
	 * @param int|string $i   Row index (or __INDEX__ placeholder).
	 * @param array      $row Row data.
	 */
	private static function timeline_row( $i, $row ) {
		$row        = is_array( $row ) ? $row : array();
		$time       = isset( $row['time'] ) ? $row['time'] : '';
		$title      = isset( $row['title'] ) ? $row['title'] : '';
		$short_desc = isset( $row['short_desc'] ) ? $row['short_desc'] : '';
		$long_desc  = isset( $row['long_desc'] ) ? $row['long_desc'] : '';
		$clicks     = isset( $row['clicks'] ) ? (int) $row['clicks'] : 0;
		$n          = '_webinar_timeline[' . $i . ']';
		?>
		<div class="webinar-repeater-row">
			<div class="webinar-row-line">
				<label><?php esc_html_e( 'Start time', 'webinar-block' ); ?> *<br>
					<input type="text" name="<?php echo esc_attr( $n . '[time]' ); ?>" value="<?php echo esc_attr( $time ); ?>" placeholder="00:00" class="webinar-input-small" />
				</label>
				<label class="webinar-row-grow"><?php esc_html_e( 'Title', 'webinar-block' ); ?> *<br>
					<input type="text" name="<?php echo esc_attr( $n . '[title]' ); ?>" value="<?php echo esc_attr( $title ); ?>" class="regular-text webinar-input-wide" />
				</label>
				<button type="button" class="button webinar-repeater-remove"><?php esc_html_e( 'Remove', 'webinar-block' ); ?></button>
			</div>
			<p><label><?php esc_html_e( 'Short description', 'webinar-block' ); ?><br>
				<input type="text" name="<?php echo esc_attr( $n . '[short_desc]' ); ?>" value="<?php echo esc_attr( $short_desc ); ?>" class="large-text" />
			</label></p>
			<p><label><?php esc_html_e( 'Detailed description', 'webinar-block' ); ?><br>
				<textarea name="<?php echo esc_attr( $n . '[long_desc]' ); ?>" rows="3" class="large-text"><?php echo esc_textarea( $long_desc ); ?></textarea>
			</label></p>
			<p><label><?php esc_html_e( 'Clicks (stats)', 'webinar-block' ); ?><br>
				<input type="number" min="0" name="<?php echo esc_attr( $n . '[clicks]' ); ?>" value="<?php echo esc_attr( $clicks ); ?>" class="webinar-input-small" />
			</label></p>
		</div>
		<?php
	}

	/*
	 * ------------------------------------------------------------------
	 * Takeaways meta box (key takeaway + repeater).
	 * ----------------------------------------------------------------
	 */

	/**
	 * Takeaways editor.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_takeaways( $post ) {
		$key       = get_post_meta( $post->ID, '_webinar_key_takeaway', true );
		$takeaways = get_post_meta( $post->ID, '_webinar_takeaways', true );
		$takeaways = is_array( $takeaways ) ? $takeaways : array();
		?>
		<p><label for="_webinar_key_takeaway"><?php esc_html_e( 'Key takeaway', 'webinar-block' ); ?></label><br>
			<textarea id="_webinar_key_takeaway" name="_webinar_key_takeaway" rows="4" class="large-text"><?php echo esc_textarea( $key ); ?></textarea>
		</p>
		<div class="webinar-repeater" data-repeater="takeaways">
			<div class="webinar-repeater-rows">
				<?php
				foreach ( $takeaways as $i => $text ) {
					self::takeaway_row( $i, $text );
				}
				?>
			</div>
			<p class="webinar-repeater-actions">
				<button type="button" class="button webinar-repeater-add"><?php esc_html_e( 'Add takeaway', 'webinar-block' ); ?></button>
				<button type="button" class="button webinar-block-export" data-block="takeaways"><?php esc_html_e( 'Export block', 'webinar-block' ); ?></button>
				<button type="button" class="button webinar-block-import-btn" data-block="takeaways"><?php esc_html_e( 'Import block', 'webinar-block' ); ?></button>
				<input type="file" accept=".json,application/json" class="webinar-block-import-file" style="display:none" />
			</p>
		</div>
		<template id="webinar-tpl-takeaway"><?php self::takeaway_row( '__INDEX__', '' ); ?></template>
		<?php
	}

	/**
	 * One takeaway tile row.
	 *
	 * @param int|string $i    Row index (or __INDEX__ placeholder).
	 * @param string     $text Takeaway text.
	 */
	private static function takeaway_row( $i, $text ) {
		?>
		<div class="webinar-repeater-row webinar-repeater-row-inline">
			<textarea name="_webinar_takeaways[<?php echo esc_attr( $i ); ?>]" rows="2" class="large-text"><?php echo esc_textarea( is_string( $text ) ? $text : '' ); ?></textarea>
			<button type="button" class="button webinar-repeater-remove"><?php esc_html_e( 'Remove', 'webinar-block' ); ?></button>
		</div>
		<?php
	}

	/*
	 * ------------------------------------------------------------------
	 * Materials meta box (repeater).
	 * ----------------------------------------------------------------
	 */

	/**
	 * Downloadable materials repeater.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_materials( $post ) {
		$materials = get_post_meta( $post->ID, '_webinar_materials', true );
		$materials = is_array( $materials ) ? $materials : array();
		?>
		<div class="webinar-repeater" data-repeater="materials">
			<div class="webinar-repeater-rows">
				<?php
				foreach ( $materials as $i => $row ) {
					self::material_row( $i, $row );
				}
				?>
			</div>
			<p><button type="button" class="button webinar-repeater-add"><?php esc_html_e( 'Add material', 'webinar-block' ); ?></button></p>
		</div>
		<template id="webinar-tpl-material"><?php self::material_row( '__INDEX__', array() ); ?></template>
		<?php
	}

	/**
	 * One materials repeater row.
	 *
	 * @param string $index    Row index (or __INDEX__ placeholder).
	 * @param array  $material Stored material data.
	 */
	private static function material_row( $index, $material = array() ) {
		$icon        = isset( $material['icon'] ) ? (int) $material['icon'] : 0;
		$button_text = isset( $material['button_text'] ) ? $material['button_text'] : '';
		$desc        = isset( $material['description'] ) ? $material['description'] : '';
		$file        = isset( $material['file'] ) ? (int) $material['file'] : 0;
		$icon_size   = isset( $material['icon_size'] ) ? $material['icon_size'] : 'normal';
		$n           = '_webinar_materials[' . $index . ']';
		?>
		<div class="webinar-repeater-row">
			<div class="webinar-row-line">
				<label class="webinar-row-grow"><?php esc_html_e( 'Button text', 'webinar-block' ); ?><br>
					<input type="text" name="<?php echo esc_attr( $n . '[button_text]' ); ?>" value="<?php echo esc_attr( $button_text ); ?>" placeholder="Download PDF" class="regular-text" />
				</label>
				<button type="button" class="button webinar-repeater-remove"><?php esc_html_e( 'Remove', 'webinar-block' ); ?></button>
			</div>
			<?php self::media_field( $n . '[icon]', __( 'Icon (SVG)', 'webinar-block' ), $icon, 'icon', null, $n . '[icon_size]', $icon_size ); ?>			<p><label><?php esc_html_e( 'Description', 'webinar-block' ); ?><br>
				<textarea name="<?php echo esc_attr( $n . '[description]' ); ?>" rows="3" class="large-text"><?php echo esc_textarea( $desc ); ?></textarea>
			</label></p>
			<?php self::media_field( $n . '[file]', __( 'File', 'webinar-block' ), $file, 'file' ); ?>
		</div>
		<?php
	}




	/*
	 * ------------------------------------------------------------------
	 * Layout scheme meta box.
	 * ----------------------------------------------------------------
	 */


	/**
	 * Layout schemes meta box with tabs for each block.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_schemes( $post ) {
		$blocks = Webinar_Layouts::get_blocks();
		?>
		<div class="webinar-schemes-wrapper">
			<div class="webinar-schemes-tabs">
				<?php foreach ( $blocks as $i => $block ) : ?>
					<button type="button" class="webinar-scheme-tab<?php echo 0 === $i ? ' active' : ''; ?>" data-block="<?php echo esc_attr( $block ); ?>">
						<?php echo esc_html( self::get_block_label( $block ) ); ?>
					</button>
				<?php endforeach; ?>
			</div>

			<?php
			foreach ( $blocks as $i => $block ) :
				$scheme  = get_post_meta( $post->ID, '_webinar_' . $block . '_layout', true ) ?: 'classic';
				$css     = get_post_meta( $post->ID, '_webinar_' . $block . '_layout_css', true );
				$schemes = Webinar_Layouts::get_schemes( $block );
				?>
				<div class="webinar-scheme-panel<?php echo 0 === $i ? ' active' : ''; ?>" data-block="<?php echo esc_attr( $block ); ?>">
					<div class="webinar-scheme-selector">
						<?php foreach ( $schemes as $key => $label ) : ?>
							<label class="webinar-scheme-option">
								<input type="radio" name="_webinar_<?php echo esc_attr( $block ); ?>_layout" value="<?php echo esc_attr( $key ); ?>" <?php checked( $scheme, $key ); ?> class="webinar-scheme-radio" />
								<span class="webinar-scheme-label"><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>

					<div class="webinar-scheme-preview" data-block="<?php echo esc_attr( $block ); ?>">
						<!-- Preview will be rendered by JS -->
						<div class="webinar-scheme-preview-placeholder">
							<?php esc_html_e( 'Preview loads here', 'webinar-block' ); ?>
						</div>
					</div>

					<div class="webinar-scheme-custom" <?php echo 'custom' === $scheme ? '' : 'style="display:none"'; ?>>
						<label for="webinar_<?php echo esc_attr( $block ); ?>_layout_css">
							<?php esc_html_e( 'Custom CSS', 'webinar-block' ); ?>
						</label>
						<textarea id="webinar_<?php echo esc_attr( $block ); ?>_layout_css" name="_webinar_<?php echo esc_attr( $block ); ?>_layout_css" rows="12" class="large-text webinar-scheme-css" placeholder="<?php esc_attr_e( 'Write CSS here. Use relative selectors like .webinar-takeaways-grid li { ... }', 'webinar-block' ); ?>"><?php echo esc_textarea( $css ); ?></textarea>
						<div class="webinar-scheme-css-actions">
							<button type="button" class="button webinar-scheme-apply" data-block="<?php echo esc_attr( $block ); ?>" <?php echo 'custom' !== $scheme ? 'disabled' : ''; ?>>
								<?php esc_html_e( 'Apply', 'webinar-block' ); ?>
							</button>
							<span class="webinar-scheme-css-status"></span>
						</div>
					</div>

					<div class="webinar-scheme-actions">
						<a class="button" href="<?php echo esc_url( self::get_scheme_export_url( $post->ID, $block ) ); ?>">
							<?php esc_html_e( 'Export scheme', 'webinar-block' ); ?>
						</a>
						<button type="button" class="button webinar-scheme-import-btn" data-block="<?php echo esc_attr( $block ); ?>" <?php echo 'custom' !== $scheme ? 'disabled' : ''; ?>>
							<?php esc_html_e( 'Import scheme', 'webinar-block' ); ?>
						</button>
						<input type="file" accept=".json,application/json" class="webinar-scheme-import-file" style="display:none" data-block="<?php echo esc_attr( $block ); ?>" />
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Get human-readable label for a block.
	 *
	 * @param string $block Block key.
	 * @return string
	 */
	private static function get_block_label( $block ) {
		$labels = array(
			'timeline'  => __( 'Timeline', 'webinar-block' ),
			'takeaways' => __( 'Takeaways', 'webinar-block' ),
			'materials' => __( 'Materials', 'webinar-block' ),
			'details'   => __( 'Details', 'webinar-block' ),
		);
		return isset( $labels[ $block ] ) ? $labels[ $block ] : ucfirst( $block );
	}

	/**
	 * Get export URL for a layout scheme.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $block   Block key.
	 * @return string
	 */
	private static function get_scheme_export_url( $post_id, $block ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'webinar_export_scheme',
					'post'   => $post_id,
					'block'  => $block,
				),
				admin_url( 'admin-post.php' )
			),
			'webinar_scheme_export_' . $post_id
		);
	}



	/**
	 * ------------------------------------------------------------------
	 * Theme meta box
	 * ----------------------------------------------------------------
	 *
	 * Render the theme color picker meta box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public static function render_theme( $post ) {
		$theme  = get_post_meta( $post->ID, '_webinar_theme', true ) ?: 'standard';
		$custom = get_post_meta( $post->ID, '_webinar_theme_custom', true );
		$custom = is_array( $custom ) ? $custom : array();

		$presets = Webinar_Themes::preset_colors();
		$fields  = Webinar_Themes::get_color_fields();

		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'webinar_export_theme',
					'post'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			'webinar_theme_export_' . $post->ID
		);
		?>
		<p>
			<label for="_webinar_theme"><?php esc_html_e( 'Color theme', 'webinar-block' ); ?></label><br>
			<div class="webinar-theme-buttons">
				<?php
				foreach ( Webinar_Themes::get_themes() as $id => $label ) :
					$colors    = $presets[ $id ] ?? array();
					$primary   = $colors['primary'] ?? '#000000';
					$secondary = $colors['secondary'] ?? '#666666';
					$accent    = $colors['accent'] ?? '#999999';
					?>
					<label class="webinar-theme-button<?php selected( $theme, $id ); ?>">
						<input 
							type="radio" 
							name="_webinar_theme" 
							value="<?php echo esc_attr( $id ); ?>" 
							<?php checked( $theme, $id ); ?>
							class="webinar-theme-radio"
						/>
						<span class="webinar-theme-preview">
							<span class="webinar-theme-dot" style="background-color: <?php echo esc_attr( $primary ); ?>;"></span>
							<span class="webinar-theme-dot" style="background-color: <?php echo esc_attr( $secondary ); ?>;"></span>
							<span class="webinar-theme-dot" style="background-color: <?php echo esc_attr( $accent ); ?>;"></span>
						</span>
						<span class="webinar-theme-label"><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</p>

		<div class="webinar-theme-palette" data-theme="<?php echo esc_attr( $theme ); ?>">
			<?php foreach ( $fields as $group_key => $group_fields ) : ?>
				<div class="webinar-theme-group" data-group="<?php echo esc_attr( $group_key ); ?>">
					<h4 class="webinar-theme-group-title"><?php echo esc_html( self::get_group_label( $group_key ) ); ?></h4>
					<div class="webinar-theme-colors">
						<?php
						foreach ( $group_fields as $color_key => $color_label ) :
							// We determine the value: custom or from the preset.
							if ( 'custom' === $theme && isset( $custom[ $color_key ] ) ) {
								$value = $custom[ $color_key ];
							} elseif ( isset( $presets[ $theme ][ $color_key ] ) ) {
								$value = $presets[ $theme ][ $color_key ];
							} else {
								$value = '#000000';
							}
							$is_custom = 'custom' === $theme;
							?>
							<div class="webinar-color-field" data-color-key="<?php echo esc_attr( $color_key ); ?>">
								<label for="webinar_color_<?php echo esc_attr( $color_key ); ?>">
									<?php echo esc_html( $color_label ); ?>
								</label>
								<div class="webinar-color-input-wrap">
									<input
										type="color"
										id="webinar_color_<?php echo esc_attr( $color_key ); ?>"
										name="_webinar_theme_custom[<?php echo esc_attr( $color_key ); ?>]"
										value="<?php echo esc_attr( $value ); ?>"
										<?php echo $is_custom ? '' : 'disabled'; ?>
										class="webinar-color-picker"
									/>
									<span class="webinar-color-value"><?php echo esc_html( strtoupper( $value ) ); ?></span>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<p class="webinar-repeater-actions">
			<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export color scheme', 'webinar-block' ); ?></a>
			<button type="button" class="button webinar-theme-import-btn" <?php echo 'custom' !== $theme ? 'disabled' : ''; ?>><?php esc_html_e( 'Import color scheme', 'webinar-block' ); ?></button>
			<input type="file" accept=".json,application/json" class="webinar-theme-import-file" style="display:none" />
		</p>
		<?php
	}

	/**
	 * Get human-readable label for color group.
	 *
	 * @param string $key Group key.
	 * @return string
	 */
	private static function get_group_label( $key ) {
		$labels = array(
			'brand'      => __( 'Brand', 'webinar-block' ),
			'surfaces'   => __( 'Surfaces', 'webinar-block' ),
			'text'       => __( 'Text', 'webinar-block' ),
			'components' => __( 'Components', 'webinar-block' ),
		);
		return isset( $labels[ $key ] ) ? $labels[ $key ] : ucfirst( $key );
	}


	/*
	 * ------------------------------------------------------------------
	 * Import / Export meta box.
	 * ----------------------------------------------------------------
	 */

	/**
	 * Webinar settings import/export controls.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_io( $post ) {
		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'webinar_export_settings',
					'post'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			'webinar_export_' . $post->ID
		);
		?>
		<p class="webinar-repeater-actions">
			<a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export webinar settings (JSON)', 'webinar-block' ); ?></a>
		</p>
		<p class="webinar-repeater-actions">
			<button type="button" class="button webinar-settings-import-btn"><?php esc_html_e( 'Import webinar settings (JSON)', 'webinar-block' ); ?></button>
			<input type="file" accept=".json,application/json" class="webinar-settings-import-file" style="display:none" />
			<span class="description webinar-settings-import-status"></span>
		</p>
		<?php
	}

	/*
	 * ------------------------------------------------------------------
	 * List table columns.
	 * ----------------------------------------------------------------
	 */

	/**
	 * Add custom columns to the webinar list table.
	 *
	 * @param array $columns Default columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $title ) {
			if ( 'title' === $key ) {
				$new[ $key ]              = $title;
				$new['webinar_views']     = __( 'Views', 'webinar-block' );
				$new['webinar_video']     = __( 'Video', 'webinar-block' );
				$new['webinar_shortcode'] = __( 'Shortcode', 'webinar-block' );
			} else {
				$new[ $key ] = $title;
			}
		}

		return $new;
	}

	/**
	 * Fill custom columns.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function column_content( $column, $post_id ) {
		if ( 'webinar_views' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_webinar_views', true ) ?: 0 );
		}

		if ( 'webinar_video' === $column ) {
			$type = get_post_meta( $post_id, '_webinar_video_type', true ) ?: 'mp4';
			echo esc_html( strtoupper( $type ) );
		}
		if ( 'webinar_shortcode' === $column ) {
			$code = '[webinar id="' . $post_id . '"]';
			?>
			<span class="webinar-shortcode-wrap">
				<input type="text" readonly class="webinar-shortcode-input" value="<?php echo esc_attr( $code ); ?>" onclick="this.select();" />
				<button type="button" class="button webinar-copy-btn"><?php esc_html_e( 'Copy', 'webinar-block' ); ?></button>
			</span>
			<?php
		}
	}

	/*
	 * ------------------------------------------------------------------
	 * Save handler.
	 * ----------------------------------------------------------------
	 */

	/**
	 * Save webinar meta fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save( $post_id, $post ) {
		unset( $post );
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['webinar_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['webinar_nonce'] ), 'webinar_save' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// General.
		if ( isset( $_POST['_webinar_views'] ) ) {
			update_post_meta( $post_id, '_webinar_views', max( 0, absint( $_POST['_webinar_views'] ) ) );
		}
		if ( isset( $_POST['_webinar_short_desc'] ) ) {
			update_post_meta( $post_id, '_webinar_short_desc', sanitize_text_field( wp_unslash( $_POST['_webinar_short_desc'] ) ) );
		}
		if ( isset( $_POST['_webinar_full_desc'] ) ) {
			update_post_meta( $post_id, '_webinar_full_desc', sanitize_textarea_field( wp_unslash( $_POST['_webinar_full_desc'] ) ) );
		}
		if ( isset( $_POST['_webinar_key_message'] ) ) {
			update_post_meta( $post_id, '_webinar_key_message', sanitize_textarea_field( wp_unslash( $_POST['_webinar_key_message'] ) ) );
		}

		// Video.
		if ( isset( $_POST['_webinar_player_type'] ) ) {
			$player_type_raw = sanitize_text_field( wp_unslash( $_POST['_webinar_player_type'] ) );
			$player_type     = in_array( $player_type_raw, array( 'native', 'custom' ), true ) ? $player_type_raw : 'custom';

			update_post_meta( $post_id, '_webinar_player_type', $player_type );
		}
		update_post_meta( $post_id, '_webinar_show_share', isset( $_POST['_webinar_show_share'] ) ? '1' : '0' );
		if ( isset( $_POST['_webinar_preview_std'] ) ) {
			update_post_meta( $post_id, '_webinar_preview_std', esc_url_raw( wp_unslash( $_POST['_webinar_preview_std'] ) ) );
		}
		if ( isset( $_POST['_webinar_preview_small'] ) ) {
			update_post_meta( $post_id, '_webinar_preview_small', esc_url_raw( wp_unslash( $_POST['_webinar_preview_small'] ) ) );
		}
		if ( isset( $_POST['_webinar_timeline_mode'] ) ) {
			$raw           = sanitize_text_field( wp_unslash( $_POST['_webinar_timeline_mode'] ) );
			$timeline_mode = in_array( $raw, array( 'block', 'bar' ), true ) ? $raw : 'block';
			update_post_meta( $post_id, '_webinar_timeline_mode', $timeline_mode );
		}
		if ( isset( $_POST['_webinar_video_type'] ) ) {
			$raw  = sanitize_text_field( wp_unslash( $_POST['_webinar_video_type'] ) );
			$type = in_array( $raw, array( 'mp4', 'hls' ), true ) ? $raw : 'mp4';
			update_post_meta( $post_id, '_webinar_video_type', $type );
		}
		if ( isset( $_POST['_webinar_video_mp4'] ) ) {
			update_post_meta( $post_id, '_webinar_video_mp4', absint( $_POST['_webinar_video_mp4'] ) );
		}
		if ( isset( $_POST['_webinar_video_hls_manifest'] ) ) {
			update_post_meta( $post_id, '_webinar_video_hls_manifest', absint( $_POST['_webinar_video_hls_manifest'] ) );
		}
		if ( isset( $_POST['_webinar_video_hls_base_url'] ) ) {
			update_post_meta( $post_id, '_webinar_video_hls_base_url', esc_url_raw( wp_unslash( $_POST['_webinar_video_hls_base_url'] ) ) );
		}
		if ( isset( $_POST['_webinar_video_poster'] ) ) {
			update_post_meta( $post_id, '_webinar_video_poster', absint( $_POST['_webinar_video_poster'] ) );
		}
		if ( isset( $_POST['_webinar_video_hls_key'] ) ) {
			update_post_meta( $post_id, '_webinar_video_hls_key', sanitize_text_field( wp_unslash( $_POST['_webinar_video_hls_key'] ) ) );
		}
		if ( isset( $_POST['_webinar_video_hls_ttl'] ) ) {
			update_post_meta( $post_id, '_webinar_video_hls_ttl', max( 60, absint( $_POST['_webinar_video_hls_ttl'] ) ) );
		}
		if ( isset( $_POST['_webinar_video_hls_hash_template'] ) ) {
			update_post_meta( $post_id, '_webinar_video_hls_hash_template', sanitize_text_field( wp_unslash( $_POST['_webinar_video_hls_hash_template'] ) ) );
		}
		if ( isset( $_POST['_webinar_video_hls_sign_template'] ) ) {
			update_post_meta( $post_id, '_webinar_video_hls_sign_template', sanitize_text_field( wp_unslash( $_POST['_webinar_video_hls_sign_template'] ) ) );
		}
		if ( isset( $_POST['_webinar_video_hls_preset'] ) ) {
			$preset = sanitize_key( $_POST['_webinar_video_hls_preset'] );
			if ( ! in_array( $preset, array( 'nginx', 'cloudfront', 'fastly', 'custom' ), true ) ) {
				$preset = 'nginx';
			}
			update_post_meta( $post_id, '_webinar_video_hls_preset', $preset );
		}

		// Presentation.
		if ( isset( $_POST['_webinar_pdf'] ) ) {
			update_post_meta( $post_id, '_webinar_pdf', absint( $_POST['_webinar_pdf'] ) );
		}
		if ( isset( $_POST['_webinar_pdf_button_text'] ) ) {
			update_post_meta( $post_id, '_webinar_pdf_button_text', sanitize_text_field( wp_unslash( $_POST['_webinar_pdf_button_text'] ) ) );
		}
		if ( isset( $_POST['_webinar_pdf_icon'] ) ) {
			update_post_meta( $post_id, '_webinar_pdf_icon', absint( $_POST['_webinar_pdf_icon'] ) );
		}
		if ( isset( $_POST['_webinar_pdf_icon_size'] ) ) {
			update_post_meta( $post_id, '_webinar_pdf_icon_size', 'large' === $_POST['_webinar_pdf_icon_size'] ? 'large' : 'normal' );
		}

		// Timeline repeater: always update (empty array when all removed).
		$clean          = array();
		$timeline_input = isset( $_POST['_webinar_timeline'] ) ? wp_unslash( $_POST['_webinar_timeline'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per item below.
		if ( is_array( $timeline_input ) ) {
			foreach ( $timeline_input as $raw ) {
				if ( ! is_array( $raw ) ) {
					continue;
				}
				$time  = isset( $raw['time'] ) ? sanitize_text_field( $raw['time'] ) : '';
				$title = isset( $raw['title'] ) ? sanitize_text_field( $raw['title'] ) : '';
				if ( '' === $time || '' === $title ) {
					continue;
				}
				$clean[] = array(
					'time'       => $time,
					'title'      => $title,
					'short_desc' => isset( $raw['short_desc'] ) ? sanitize_text_field( $raw['short_desc'] ) : '',
					'long_desc'  => isset( $raw['long_desc'] ) ? sanitize_textarea_field( $raw['long_desc'] ) : '',
					'clicks'     => isset( $raw['clicks'] ) ? absint( $raw['clicks'] ) : 0,
				);
			}
		}
		update_post_meta( $post_id, '_webinar_timeline', $clean );

		// Takeaways.
		if ( isset( $_POST['_webinar_key_takeaway'] ) ) {
			update_post_meta( $post_id, '_webinar_key_takeaway', sanitize_textarea_field( wp_unslash( $_POST['_webinar_key_takeaway'] ) ) );
		}
		// Always update (empty array when all removed).
		$clean           = array();
		$takeaways_input = isset( $_POST['_webinar_takeaways'] ) ? wp_unslash( $_POST['_webinar_takeaways'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per item below.
		if ( is_array( $takeaways_input ) ) {
			foreach ( $takeaways_input as $text ) {
				$text = sanitize_textarea_field( $text );
				if ( '' !== $text ) {
					$clean[] = $text;
				}
			}
		}
		update_post_meta( $post_id, '_webinar_takeaways', $clean );

		// Materials repeater: always update (empty array when all removed).
		$clean           = array();
		$materials_input = isset( $_POST['_webinar_materials'] ) ? wp_unslash( $_POST['_webinar_materials'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per item below.
		if ( is_array( $materials_input ) ) {
			foreach ( $materials_input as $raw ) {
				if ( ! is_array( $raw ) ) {
					continue;
				}
				$button_text = isset( $raw['button_text'] ) ? sanitize_text_field( $raw['button_text'] ) : '';
				$desc        = isset( $raw['description'] ) ? sanitize_textarea_field( $raw['description'] ) : '';
				$file        = isset( $raw['file'] ) ? absint( $raw['file'] ) : 0;
				if ( ! $file ) {
					continue;
				}
				$clean[] = array(
					'icon'        => isset( $raw['icon'] ) ? absint( $raw['icon'] ) : 0,
					'icon_size'   => isset( $raw['icon_size'] ) && 'large' === $raw['icon_size'] ? 'large' : 'normal',
					'button_text' => $button_text,
					'description' => $desc,
					'file'        => $file,
				);
			}
		}
		update_post_meta( $post_id, '_webinar_materials', $clean );

		// Theme.
		if ( isset( $_POST['_webinar_theme'] ) ) {
			$theme = sanitize_key( $_POST['_webinar_theme'] );
			if ( ! array_key_exists( $theme, Webinar_Themes::get_themes() ) ) {
				$theme = 'standard';
			}
			update_post_meta( $post_id, '_webinar_theme', $theme );
		}

		$theme_custom_input = isset( $_POST['_webinar_theme_custom'] ) ? wp_unslash( $_POST['_webinar_theme_custom'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per item below.
		if ( is_array( $theme_custom_input ) ) {
			$clean      = array();
			$all_fields = array();
			foreach ( Webinar_Themes::get_color_fields() as $group_fields ) {
				$all_fields = array_merge( $all_fields, array_keys( $group_fields ) );
			}
			foreach ( $theme_custom_input as $key => $hex ) {
				if ( in_array( $key, $all_fields, true ) ) {
					$clean[ $key ] = sanitize_hex_color( $hex ) ?: '#000000';
				}
			}
			update_post_meta( $post_id, '_webinar_theme_custom', $clean );
		}

		// Layout schemes + custom CSS.
		foreach ( Webinar_Layouts::get_blocks() as $block ) {
			$meta  = '_webinar_' . $block . '_layout';
			$css_m = '_webinar_' . $block . '_layout_css';
			if ( isset( $_POST[ $meta ] ) ) {
				$scheme = sanitize_key( $_POST[ $meta ] );
				if ( ! array_key_exists( $scheme, Webinar_Layouts::get_schemes( $block ) ) ) {
					$scheme = 'classic';
				}
				update_post_meta( $post_id, $meta, $scheme );
			}
			if ( isset( $_POST[ $css_m ] ) ) {
				update_post_meta( $post_id, $css_m, wp_strip_all_tags( wp_unslash( $_POST[ $css_m ] ) ) );
			}
		}
	}


	/*
	 * ------------------------------------------------------------------
	 * Media library: allow HLS playlists
	 * ----------------------------------------------------------------
	 */

	/**
	 * Whitelist .m3u8 / .m3u uploads.
	 *
	 * @param array $mimes Allowed MIME types.
	 * @return array
	 */
	public static function allow_hls_mimes( $mimes ) {
		$mimes['m3u8'] = 'application/vnd.apple.mpegurl';
		$mimes['m3u']  = 'application/x-mpegurl';
		return $mimes;
	}

	/**
	 * WordPress' finfo sees playlists as text/plain, which fails the
	 * "real MIME" check. Force the playlist MIME for these extensions.
	 *
	 * @param array  $data     Detected file data (ext/type/proper_filename).
	 * @param string $file     Full path to the file.
	 * @param string $filename Original file name.
	 * @param array  $mimes    Allowed MIME types.
	 * @param string $real_mime Real MIME detected by finfo.
	 * @return array
	 */
	public static function fix_playlist_filetype( $data, $file, $filename, $mimes, $real_mime = '' ) {
		unset( $mimes, $real_mime );
		$ext = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( 'm3u8' === $ext || 'm3u' === $ext ) {
			$data['ext']  = $ext;
			$data['type'] = 'm3u8' === $ext ? 'application/vnd.apple.mpegurl' : 'application/x-mpegurl';
		}

		return $data;
	}

	/*
	 * ------------------------------------------------------------------
	 * Import / Export handlers
	 * ----------------------------------------------------------------
	 */

	/**
	 * Stream webinar settings as a JSON download.
	 */
	public static function export_settings() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! $post_id || 'webinar' !== get_post_type( $post_id ) ) {
			wp_die( 'Invalid webinar.' );
		}
		check_admin_referer( 'webinar_export_' . $post_id );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( 'Forbidden.' );
		}

		$data = Webinar_Meta::get_webinar_data( $post_id );
		unset( $data['id'] ); // Title is only used when import creates a new webinar.

		// Ensure player_type is included in export.
		if ( ! isset( $data['player_type'] ) ) {
			$data['player_type'] = get_post_meta( $post_id, '_webinar_player_type', true ) ?: 'custom';
		}

		$data['layout_css'] = array(
			'timeline'  => get_post_meta( $post_id, '_webinar_timeline_layout_css', true ),
			'takeaways' => get_post_meta( $post_id, '_webinar_takeaways_layout_css', true ),
			'materials' => get_post_meta( $post_id, '_webinar_materials_layout_css', true ),
		);

		// Attachment id -> url map for portability between sites.
		$ids = array( $data['video_mp4'], $data['video_hls_manifest'], $data['video_poster'], $data['pdf'], $data['pdf_icon'] );
		foreach ( $data['materials'] as $material ) {
			$ids[] = isset( $material['icon'] ) ? $material['icon'] : 0;
			$ids[] = isset( $material['file'] ) ? $material['file'] : 0;
		}
		$data['attachments'] = array();
		foreach ( array_unique( array_map( 'absint', $ids ) ) as $aid ) {
			if ( $aid ) {
				$url = wp_get_attachment_url( $aid );
				if ( $url ) {
					$data['attachments'][ $aid ] = $url;
				}
			}
		}

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="webinar-' . $post_id . '-settings.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Stream the current layout scheme CSS as a download.
	 */
	public static function export_layout() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$block   = isset( $_GET['block'] ) ? sanitize_key( $_GET['block'] ) : '';
		if ( ! $post_id || 'webinar' !== get_post_type( $post_id ) || ! in_array( $block, array( 'timeline', 'takeaways', 'materials' ), true ) ) {
			wp_die( 'Invalid request.' );
		}
		check_admin_referer( 'webinar_layout_export_' . $post_id );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( 'Forbidden.' );
		}

		$scheme = get_post_meta( $post_id, '_webinar_' . $block . '_layout', true ) ?: 'classic';
		$css    = 'custom' === $scheme
			? (string) get_post_meta( $post_id, '_webinar_' . $block . '_layout_css', true )
			: Webinar_Layouts::get_css( $block, $scheme );

		header( 'Content-Type: text/css; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="webinar-' . $block . '-layout-' . $scheme . '.css"' );

		// Preset CSS is ours; custom CSS is stripped on save.
		echo wp_strip_all_tags( $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS output, already sanitized.
		exit;
	}

	/**
	 * Stream the current theme colors as a JSON download.
	 */
	public static function export_theme() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! $post_id || 'webinar' !== get_post_type( $post_id ) ) {
			wp_die( 'Invalid webinar.' );
		}
		check_admin_referer( 'webinar_theme_export_' . $post_id );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( 'Forbidden.' );
		}

		$theme   = get_post_meta( $post_id, '_webinar_theme', true ) ?: 'standard';
		$presets = Webinar_Themes::preset_colors();

		// We collect all the color keys.
		$all_keys = array();
		foreach ( Webinar_Themes::get_color_fields() as $group_fields ) {
			$all_keys = array_merge( $all_keys, array_keys( $group_fields ) );
		}

		if ( 'custom' === $theme ) {
			$custom_colors = (array) get_post_meta( $post_id, '_webinar_theme_custom', true );
			$colors        = array();
			foreach ( $all_keys as $key ) {
				$colors[ $key ] = isset( $custom_colors[ $key ] ) ? $custom_colors[ $key ] : ( $presets['standard'][ $key ] ?? '#000000' );
			}
		} else {
			$colors = $presets[ $theme ];
		}

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="webinar-' . $post_id . '-theme.json"' );
		echo wp_json_encode(
			array(
				'theme'  => $theme,
				'colors' => $colors,
			),
			JSON_PRETTY_PRINT
		);
		exit;
	}


	/**
	 * Export a layout scheme as JSON.
	 */
	public static function export_scheme() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$block   = isset( $_GET['block'] ) ? sanitize_key( $_GET['block'] ) : '';

		if ( ! $post_id || 'webinar' !== get_post_type( $post_id ) || ! in_array( $block, Webinar_Layouts::get_blocks(), true ) ) {
			wp_die( 'Invalid request.' );
		}
		check_admin_referer( 'webinar_scheme_export_' . $post_id );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( 'Forbidden.' );
		}

		$scheme = get_post_meta( $post_id, '_webinar_' . $block . '_layout', true ) ?: 'classic';
		$css    = 'custom' === $scheme
			? (string) get_post_meta( $post_id, '_webinar_' . $block . '_layout_css', true )
			: Webinar_Layouts::get_css( $block, $scheme );

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="webinar-' . $block . '-scheme-' . $scheme . '.json"' );
		echo wp_json_encode(
			array(
				'block'  => $block,
				'scheme' => $scheme,
				'css'    => $css,
			),
			JSON_PRETTY_PRINT
		);
		exit;
	}

	/**
	 * Import a layout scheme (AJAX).
	 */
	public static function import_scheme() {
		check_ajax_referer( 'webinar_nonce', 'nonce' );

		$payload_raw = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : '';  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string, decoded and validated below.
		$payload     = $payload_raw ? json_decode( $payload_raw, true ) : null;
		if ( ! is_array( $payload ) ) {
			wp_send_json_error( 'bad_json' );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$block   = isset( $payload['block'] ) ? sanitize_key( $payload['block'] ) : '';

		if ( ! $post_id || 'webinar' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'forbidden' );
		}

		if ( ! in_array( $block, Webinar_Layouts::get_blocks(), true ) ) {
			wp_send_json_error( 'invalid_block' );
		}

		$scheme = isset( $payload['scheme'] ) ? sanitize_key( $payload['scheme'] ) : 'custom';
		if ( ! array_key_exists( $scheme, Webinar_Layouts::get_schemes( $block ) ) ) {
			$scheme = 'custom';
		}

		update_post_meta( $post_id, '_webinar_' . $block . '_layout', $scheme );

		if ( isset( $payload['css'] ) && is_string( $payload['css'] ) ) {
			update_post_meta( $post_id, '_webinar_' . $block . '_layout_css', wp_strip_all_tags( $payload['css'] ) );
		}

		wp_send_json_success(
			array(
				'post_id' => $post_id,
				'block'   => $block,
			)
		);
	}

	/**
	 * Apply imported webinar settings (AJAX).
	 */
	public static function import_settings() {
		check_ajax_referer( 'webinar_nonce', 'nonce' );

		$payload_raw = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : '';   // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string, decoded and validated below.
		$payload     = $payload_raw ? json_decode( $payload_raw, true ) : null;
		if ( ! is_array( $payload ) ) {
			wp_send_json_error( 'bad_json' );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( $post_id ) {
			// Import into an existing webinar.
			if ( 'webinar' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( 'forbidden' );
			}
		} else {
			// Import from "Add New": create a fresh draft and fill it.
			if ( ! current_user_can( 'edit_webinars' ) ) {
				wp_send_json_error( 'forbidden' );
			}
			$title   = isset( $payload['title'] ) && is_string( $payload['title'] ) && '' !== trim( $payload['title'] )
				? sanitize_text_field( $payload['title'] )
				: __( 'Imported webinar', 'webinar-block' );
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'webinar',
					'post_status' => 'draft',
					'post_title'  => $title,
				)
			);
			if ( ! $post_id || is_wp_error( $post_id ) ) {
				wp_send_json_error( 'create_failed' );
			}
		}

		// Text fields.
		$text_fields = array(
			'short_desc'              => array( '_webinar_short_desc', 'text' ),
			'full_desc'               => array( '_webinar_full_desc', 'textarea' ),
			'key_message'             => array( '_webinar_key_message', 'textarea' ),
			'key_takeaway'            => array( '_webinar_key_takeaway', 'textarea' ),
			'pdf_button_text'         => array( '_webinar_pdf_button_text', 'text' ),
			'video_hls_base_url'      => array( '_webinar_video_hls_base_url', 'url' ),
			'video_hls_ttl'           => array( '_webinar_video_hls_ttl', 'absint' ),
			'video_hls_hash_template' => array( '_webinar_video_hls_hash_template', 'text' ),
			'video_hls_sign_template' => array( '_webinar_video_hls_sign_template', 'text' ),
			'video_hls_key'           => array( '_webinar_video_hls_key', 'text' ),
		);

		foreach ( $text_fields as $key => $target ) {
			if ( isset( $payload[ $key ] ) ) {
				$value = (string) $payload[ $key ];
				if ( 'absint' === $target[1] ) {
					$value = max( 60, absint( $value ) );
				} elseif ( 'text' === $target[1] ) {
					$value = sanitize_text_field( $value );
				} elseif ( 'textarea' === $target[1] ) {
					$value = sanitize_textarea_field( $value );
				} elseif ( 'url' === $target[1] ) {
					$value = esc_url_raw( $value );
				}
				update_post_meta( $post_id, $target[0], $value );
			}
		}

				// Signing preset (enum, not a text field).
		if ( isset( $payload['video_hls_preset'] ) ) {
			$preset = sanitize_key( $payload['video_hls_preset'] );
			if ( in_array( $preset, array( 'nginx', 'cloudfront', 'fastly', 'custom' ), true ) ) {
				update_post_meta( $post_id, '_webinar_video_hls_preset', $preset );
			}
		}

		// Numbers and enums.
		if ( isset( $payload['views'] ) ) {
			update_post_meta( $post_id, '_webinar_views', max( 0, absint( $payload['views'] ) ) );
		}
		if ( isset( $payload['player_type'] ) ) {
			update_post_meta( $post_id, '_webinar_player_type', in_array( $payload['player_type'], array( 'native', 'custom' ), true ) ? $payload['player_type'] : 'custom' );
		}
		if ( isset( $payload['show_share'] ) ) {
			update_post_meta( $post_id, '_webinar_show_share', in_array( $payload['show_share'], array( '1', '0' ), true ) ? $payload['show_share'] : '1' );
		}
		if ( isset( $payload['preview_std'] ) ) {
			update_post_meta( $post_id, '_webinar_preview_std', esc_url_raw( $payload['preview_std'] ) );
		}
		if ( isset( $payload['preview_small'] ) ) {
			update_post_meta( $post_id, '_webinar_preview_small', esc_url_raw( $payload['preview_small'] ) );
		}
		if ( isset( $payload['timeline_mode'] ) ) {
			update_post_meta( $post_id, '_webinar_timeline_mode', in_array( $payload['timeline_mode'], array( 'block', 'bar' ), true ) ? $payload['timeline_mode'] : 'block' );
		}
		if ( isset( $payload['video_type'] ) ) {
			update_post_meta( $post_id, '_webinar_video_type', in_array( $payload['video_type'], array( 'mp4', 'hls' ), true ) ? $payload['video_type'] : 'mp4' );
		}

		// Attachments (remapped by URL when possible).
		$att_fields = array(
			'video_mp4'          => '_webinar_video_mp4',
			'video_hls_manifest' => '_webinar_video_hls_manifest',
			'video_poster'       => '_webinar_video_poster',
			'pdf'                => '_webinar_pdf',
			'pdf_icon'           => '_webinar_pdf_icon',
		);

		if ( isset( $payload['pdf_icon_size'] ) ) {
			update_post_meta( $post_id, '_webinar_pdf_icon_size', 'large' === $payload['pdf_icon_size'] ? 'large' : 'normal' );
		}

		foreach ( $att_fields as $key => $meta ) {
			if ( isset( $payload[ $key ] ) ) {
				update_post_meta( $post_id, $meta, self::remap_attachment( $payload[ $key ], $payload ) );
			}
		}

		// Theme.
		if ( isset( $payload['theme'] ) ) {
			$theme = sanitize_key( $payload['theme'] );
			if ( array_key_exists( $theme, Webinar_Themes::get_themes() ) ) {
				update_post_meta( $post_id, '_webinar_theme', $theme );
			}
		}
		if ( isset( $payload['theme_custom'] ) && is_array( $payload['theme_custom'] ) ) {
			$clean      = array();
			$all_fields = array();
			foreach ( Webinar_Themes::get_color_fields() as $group_fields ) {
				$all_fields = array_merge( $all_fields, array_keys( $group_fields ) );
			}
			foreach ( $payload['theme_custom'] as $key => $hex ) {
				if ( in_array( $key, $all_fields, true ) ) {
					$clean[ $key ] = sanitize_hex_color( $hex ) ?: '#000000';
				}
			}
			update_post_meta( $post_id, '_webinar_theme_custom', $clean );
		}

		// Layouts.
		foreach ( Webinar_Layouts::get_blocks() as $block ) {
			if ( isset( $payload[ $block . '_layout' ] ) ) {
				$scheme = sanitize_key( $payload[ $block . '_layout' ] );
				if ( array_key_exists( $scheme, Webinar_Layouts::get_schemes( $block ) ) ) {
					update_post_meta( $post_id, '_webinar_' . $block . '_layout', $scheme );
				}
			}
		}
		if ( isset( $payload['layout_css'] ) && is_array( $payload['layout_css'] ) ) {
			foreach ( Webinar_Layouts::get_blocks() as $block ) {
				if ( isset( $payload['layout_css'][ $block ] ) ) {
					update_post_meta( $post_id, '_webinar_' . $block . '_layout_css', wp_strip_all_tags( (string) $payload['layout_css'][ $block ] ) );
				}
			}
		}

		// Takeaways list.
		if ( isset( $payload['takeaways'] ) && is_array( $payload['takeaways'] ) ) {
			$clean = array();
			foreach ( $payload['takeaways'] as $text ) {
				$text = sanitize_textarea_field( (string) $text );
				if ( '' !== $text ) {
					$clean[] = $text;
				}
			}
			update_post_meta( $post_id, '_webinar_takeaways', $clean );
		}

		// Timeline.
		if ( isset( $payload['timeline'] ) && is_array( $payload['timeline'] ) ) {
			$clean = array();
			foreach ( $payload['timeline'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$time  = isset( $row['time'] ) ? sanitize_text_field( $row['time'] ) : '';
				$title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
				if ( '' === $time || '' === $title ) {
					continue;
				}
				$clean[] = array(
					'time'       => $time,
					'title'      => $title,
					'short_desc' => isset( $row['short_desc'] ) ? sanitize_text_field( $row['short_desc'] ) : '',
					'long_desc'  => isset( $row['long_desc'] ) ? sanitize_textarea_field( $row['long_desc'] ) : '',
					'clicks'     => isset( $row['clicks'] ) ? absint( $row['clicks'] ) : 0,
				);
			}
			update_post_meta( $post_id, '_webinar_timeline', $clean );
		}

		// Materials.
		if ( isset( $payload['materials'] ) && is_array( $payload['materials'] ) ) {
			$clean = array();
			foreach ( $payload['materials'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$desc = isset( $row['description'] ) ? sanitize_text_field( $row['description'] ) : '';
				$file = isset( $row['file'] ) ? self::remap_attachment( $row['file'], $payload ) : 0;
				if ( '' === $desc || ! $file ) {
					continue;
				}
				$clean[] = array(
					'icon'        => isset( $row['icon'] ) ? self::remap_attachment( $row['icon'], $payload ) : 0,
					'icon_size'   => isset( $row['icon_size'] ) && 'large' === $row['icon_size'] ? 'large' : 'normal',
					'button_text' => isset( $row['button_text'] ) ? sanitize_text_field( $row['button_text'] ) : '',
					'description' => isset( $row['description'] ) ? sanitize_textarea_field( $row['description'] ) : '',
					'file'        => $file,
				);
			}
			update_post_meta( $post_id, '_webinar_materials', $clean );
		}

		wp_send_json_success( array( 'post_id' => $post_id ) );
	}

	/**
	 * Resolve an attachment ID from an imported payload.
	 *
	 * Keeps the ID when the attachment exists locally, otherwise tries
	 * to find a local attachment by the exported URL.
	 *
	 * @param int   $id      Original attachment ID.
	 * @param array $payload Imported payload (with attachments map).
	 * @return int
	 */
	private static function remap_attachment( $id, $payload ) {
		$id = absint( $id );
		if ( $id && get_post( $id ) && 'attachment' === get_post_type( $id ) ) {
			return $id;
		}
		if ( isset( $payload['attachments'][ $id ] ) && $payload['attachments'][ $id ] ) {
			$found = attachment_url_to_postid( $payload['attachments'][ $id ] );
			if ( $found ) {
				return $found;
			}
		}
		return 0;
	}

	/**
	 * Get layout presets for JS.
	 *
	 * @return array
	 */
	private static function get_layout_presets_for_js() {
		$presets = array();
		foreach ( Webinar_Layouts::get_blocks() as $block ) {
			$presets[ $block ] = array();
			foreach ( Webinar_Layouts::get_schemes( $block ) as $key => $label ) {
				if ( 'custom' !== $key ) {
					$presets[ $block ][ $key ] = Webinar_Layouts::get_css( $block, $key );
				}
			}
		}
		return $presets;
	}
}

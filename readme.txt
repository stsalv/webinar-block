=== Webinar Block ===
Contributors: stsalv
Tags: webinar, video, hls, player, timeline
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn any page into a webinar landing: branded player, protected HLS, chapter timeline, takeaways and materials — one block, zero shortcodes required.

== Description ==

Webinar Block renders a complete webinar experience from a single Gutenberg block (with a `[webinar id="…"]` shortcode fallback):

* **Branded custom player** — controls in your theme colors: playback speed, HLS quality levels, ±10 s skip, fullscreen, Picture-in-Picture, full keyboard navigation and sprite hover-previews with time and chapter title.
* **Protected HLS** — segment URL signing for nginx secure_link, CloudFront-style, Fastly-style or custom templates; automatic re-signing on expired signatures; prime-loading keeps pages with many videos light (one manifest + one segment per video until playback).
* **Chapter ecosystem** — side timeline block (or timeline only in the progress bar), chapter segments on the progress bar, "Detailed content" accordion; everything is click-to-seek and highlights the active chapter during playback.
* **Takeaways & materials** — key takeaway, checklist tiles, downloadable files with per-item icons and sizes.
* **Position memory & deep links** — viewers resume where they left off; share "link to video" or "link to moment".
* **Themes & layout schemes** — color presets exposed as per-instance CSS variables, layout schemes per block, custom CSS escape hatch with live preview and validation.
* **Container-aware layouts** — place widgets side by side in tables or columns: inner grids collapse by the widget's own width, not the viewport.
* **Editor friendly** — live ServerSideRender preview with theme palettes; block-level and full-settings import/export as JSON.
* **View & click counters** — rate-limited per visitor, editable in admin, compact K/M/B formatting.

= Requirements =

* WordPress 6.0+, PHP 7.4+
* For protected HLS: nginx with `secure_link` (or a CDN verifying the same hash string)

= Privacy =

Counters use IP-based transients (REMOTE_ADDR only, no spoofable headers, no cookies). Preview sprites and videos are loaded from your own uploads/CDN.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via Plugins → Add New.
2. Activate Webinar Block.
3. Create a **Webinar** post and fill the meta boxes: video (MP4 or HLS), timeline chapters, takeaways, materials, theme.
4. Insert the **Webinar** block on any page (or use `[webinar id="123"]`) and pick the webinar.

== Frequently Asked Questions ==

= Does the player work with plain MP4? =
Yes. HLS is optional; MP4 plays with the same branded controls (quality menu appears only for HLS with multiple levels).

= How are HLS segments protected? =
WordPress signs segment names with your key and templates; your CDN must verify the same string. The nginx secure_link preset is included and documented in the meta box.

= Can I place two webinars side by side? =
Yes. All inner layouts use CSS container queries, so each widget adapts to its own column width.

= Does the block work in the site editor? =
Yes, the editor shows a themed static preview; the full player renders on the front end.

== Screenshots ==

1. Webinar landing: branded player, chapter timeline and takeaways tabs.
2. Hover preview: sprite frame with time and chapter title over the progress bar.
3. Admin: video meta box with MP4/HLS sources and signing templates.
4. Admin: color themes and per-block layout schemes.

== Changelog ==

= 1.0.0 =
* First public release.
* Gutenberg block + shortcode, webinar post type with meta boxes (video, presentation, timeline, takeaways, materials, theme, layouts, import/export).
* Custom branded player: speed, quality, ±10 s, fullscreen, PiP, keyboard, sprite hover-previews, position memory, share links.
* HLS with signed segments (nginx/CloudFront/Fastly/custom), re-signing on 403/410, prime-loading.
* Color themes as per-instance CSS variables; layout schemes with custom CSS and live preview.
* Container-query layouts for side-by-side widgets; rate-limited view/click counters; ru_RU translation included.
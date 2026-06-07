=== weRgoing Gallery for HiDrive ===
Contributors: JoDa, weRgoing
Tags: gallery, photos, albums, hidrive, strato
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display STRATO HiDrive photo folders as albums in WordPress without importing files into the Media Library.

== Description ==

weRgoing Gallery for HiDrive allows you to display photo folders from your STRATO HiDrive account directly on your WordPress site as structured albums.

Images are not imported into the WordPress Media Library.
All files remain stored in HiDrive and are retrieved on demand.

This plugin is intended for users who:
* Manage large photo collections outside WordPress
* Want to avoid Media Library duplication
* Prefer folder-based album management

This plugin follows standard WordPress practices and uses the WordPress REST API for media delivery.

== Features ==

* Display albums using shortcodes
* Display albums using a Gutenberg block
* Folder-based album structure (including sub-albums)
* Dynamic album loading via shortcode
* Secure public REST proxy for image delivery
* Configurable album cover images
* Lazy loading for images
* No Media Library imports

== Proxy Endpoint and Security Model ==

This plugin exposes a public REST proxy endpoint to allow visitors to load images and album data from HiDrive without client-side authentication.

This endpoint is intentionally public.

=== Security Assumptions ===
- The proxy endpoint does not provide access to the entire HiDrive account.
- All requests are restricted to the configured HiGallery root folder.
- Content outside the configured root folder is not accessible.
- WordPress authentication is not required for read-only access within this scope.

=== Important Considerations ===
- Anyone who can access the site can request files that exist within the configured root folder.
- File paths are validated to prevent traversal outside this scope.
- The plugin does not attempt to hide or obfuscate file paths within the allowed folder.

=== Recommended Configuration ===
- Use a dedicated HiDrive folder for HiGallery content.
- Do not configure a broad or sensitive directory as the root folder.
- Choose the narrowest possible folder scope containing only public content.

This design prioritizes simplicity and performance. Stricter access control requires additional hardening outside the scope of this plugin.

== Usage ==

This plugin supports two rendering methods:
* Shortcodes
* Gutenberg block

These methods differ in behavior.

== Shortcodes ==

Example:

[higallery_all_albums]

Behavior:
- Albums are loaded dynamically at runtime.
- The current HiDrive folder structure is queried when the page is viewed.
- Newly added albums become visible automatically (subject to caching).

Recommended when:
- You want newly added albums to appear automatically.
- Fully dynamic behavior is required.

== Gutenberg Block ==

This plugin provides a Gutenberg block to display albums.

Behavior:
- Album selection is stored as static block attributes.
- When “Select all albums” is chosen, the current album list is saved at block configuration time.
- Newly added albums will NOT automatically appear in existing blocks.
- To include new albums, the block must be edited and saved again.

Design note:
This behavior is inherent to how Gutenberg blocks serialize configuration data into post content.
The block is intentionally static.

Recommended when:
- A fixed album selection is desired.
- Content editors prefer block-based configuration.

== Dynamic Albums and Caching ==

Dynamic loading applies only to shortcodes.

=== Cache behavior ===
- This plugin uses a short internal cache (approximately 5 minutes) to reduce API load.
- HiDrive remains the source of truth.

=== Page caching ===
If your site uses page caching (plugin, hosting, or CDN), updates may appear delayed.

To minimize delays:
- Exclude pages containing `[higallery_all_albums]` from page caching, or
- Disable caching for gallery pages.

== Installation ==

1. Upload the `wergoing-gallery` folder to `/wp-content/plugins/`
2. Activate the plugin via the WordPress Plugins menu
3. Configure HiDrive access in the weRgoing Gallery settings

== Quick Start ==

1. Configure your STRATO HiDrive connection in the weRgoing Gallery settings.
2. Organize albums using folders in HiDrive.
3. Create a page and insert either:
   - the `[higallery_all_albums]` shortcode, or
   - the weRgoing Gallery Gutenberg block
4. Publish the page.

== Frequently Asked Questions ==

= Does this plugin import images into the Media Library? =
No. Images remain stored in HiDrive.

= Why do new albums appear in shortcodes but not in blocks? =
Shortcodes are rendered dynamically at runtime.
Gutenberg blocks store a static snapshot of album selection.

= Is authentication required to view images? =
No. Images are served via a public read-only proxy limited to the configured root folder.

== Changelog ==

= 1.2.0 =
* Dynamic album loading for the “All albums” shortcode
* Automatic visibility of newly added albums (shortcode)
* Short-term caching introduced for performance
* Documentation expanded to clarify dynamic vs static behavior
* Security model explicitly documented

= 1.1.0 =
* Improved album handling and cover image configuration
* REST and proxy endpoint refinements
* General security and stability improvements

== Upgrade Notice ==

= 1.2.0 =
Introduces dynamic album loading for shortcodes.
Existing Gutenberg blocks remain static by design.

== Third-party Libraries ==

PhotoSwipe (MIT License)
© Dmitry Semenov

== External Services ==

This plugin connects to STRATO HiDrive.

Endpoints used:
* https://my.hidrive.com/oauth2/authorize
* https://my.hidrive.com/oauth2/token
* https://api.hidrive.strato.com/2.1/dir
* https://api.hidrive.strato.com/2.1/file

Data exchanged:
* OAuth tokens
* Folder and file paths required for album listing and image delivery

# HiGallery

**Version: 1.3.0**

HiGallery displays photo folders from your STRATO HiDrive account as albums on a WordPress website.

Images are **not imported** into the WordPress Media Library.  
All files remain stored in HiDrive and are retrieved on demand via a WordPress REST proxy.

This plugin is designed for users who manage photo collections outside WordPress and want to avoid Media Library duplication.

---

## Key Characteristics

- Folder-based albums sourced directly from HiDrive
- No Media Library imports
- WordPress-native REST integration
- Optimized for performance and large collections
- Explicit, documented security model

---

## Features

- Display albums using shortcodes
- Display albums using a Gutenberg block
- Automatic detection of sub-albums and images
- Dynamic “All albums” view via shortcode
- Public REST proxy for image delivery
- Configurable album cover images
- Lazy loading for images
- Short-term caching to reduce API load

---

## Dynamic vs Static Behavior

HiGallery supports two rendering methods with different behavior.

### Shortcodes

Example:
```
[higallery_all_albums]
```

- Albums are loaded dynamically at runtime.
- The current HiDrive folder structure is queried when the page is viewed.
- Newly added albums become visible automatically (subject to cache TTL).

Recommended when fully dynamic behavior is required.

### Gutenberg Block

- Album selection is stored as static block attributes.
- When “Select all albums” is chosen, the album list is saved at block configuration time.
- Newly added albums do **not** automatically appear in existing blocks.
- To include new albums, the block must be edited and saved again.

This behavior is inherent to how Gutenberg blocks serialize configuration data.

---

## Caching Behavior

- HiGallery uses a short internal cache (approximately 5 minutes).
- HiDrive remains the source of truth.
- New albums usually appear within a few minutes.

If page caching or a CDN is used, additional delay may occur.
Exclude gallery pages from page caching if immediate visibility is required.

---

## Proxy Endpoint and Security Model

HiGallery exposes a public REST proxy endpoint to retrieve images and album data from HiDrive.

Important properties:

- The endpoint is intentionally public.
- No WordPress login is required for read-only access.
- Requests are strictly limited to the configured HiGallery root folder.
- Content outside this root folder is not accessible.

This model is suitable for public galleries.
It is **not** intended for members-only or private content.

Users should configure a dedicated HiDrive folder containing only content intended for public display.

---

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- STRATO HiDrive account with API access

---

## Installation

1. Upload the `higallery` folder to `/wp-content/plugins/`
2. Activate the plugin via the WordPress admin panel
3. Configure HiDrive access in the HiGallery settings
4. Add a shortcode or Gutenberg block to a page

---

## Development

This repository is intended for development and issue tracking.

End users should install HiGallery via WordPress.org when published.

---

## License

GPLv2 or later

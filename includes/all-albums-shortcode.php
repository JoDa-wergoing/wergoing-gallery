<?php
/**
 * HiGallery - All Albums Shortcode
 *
 * Visitor-facing dynamic album listing.
 *
 * Usage:
 *   [higallery_all_albums]
 *
 * - Reads folders live from HiDrive (optionally cached via transients)
 * - Does NOT require any admin action to sync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fetch albums (directories) from HiDrive for a given path.
 * Uses a short transient cache to avoid excessive API calls.
 *
 * @param string $path
 * @param string $token
 * @return array<int, array{name:string,path:string,type:string}>
 */
function higallery_get_albums_cached(string $path, string $token): array {
    $path = (string) $path;
    $token = (string) $token;
    if ($token === '') {
        return [];
    }

    // Cache key depends on path only (single site connection).
    $cache_key = 'higallery_all_albums_' . md5($path);

    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $api = higallery_api_get_folders($path, $token);
    $albums = [];
    if (!empty($api['albums']) && is_array($api['albums'])) {
        $albums = $api['albums'];
    }

    // Natural sort by name (case-insensitive)
    usort($albums, static function ($a, $b) {
        $an = isset($a['name']) ? (string) $a['name'] : '';
        $bn = isset($b['name']) ? (string) $b['name'] : '';
        return strnatcasecmp($an, $bn);
    });

    /**
     * Filter: cache TTL for the visitor-side album list.
     * Default: 5 minutes.
     */
    $ttl = (int) apply_filters('higallery_all_albums_cache_ttl', 5 * MINUTE_IN_SECONDS);
    if ($ttl < 0) {
        $ttl = 0;
    }

    set_transient($cache_key, $albums, $ttl);
    return $albums;
}

/**
 * Shortcode handler for [higallery_all_albums]
 */
function higallery_all_albums_shortcode($atts = []): string {
    // Ensure we're always visitor-facing and robust.
    $token = higallery_get_valid_access_token();
    if (!$token) {
        return '<p>' . esc_html__( 'No HiDrive connection. Please connect HiDrive first.', 'higallery' ) . '</p>';
    }

    $default_root = get_option('higallery_root_folder', '/');

    // Optional override: [higallery_all_albums path="/Photos"]
    $atts = shortcode_atts([
        'path' => '',
    ], $atts, 'higallery_all_albums');

    $path = trim((string) $atts['path']);
    if ($path === '') {
        $path = (string) $default_root;
    }
    // If the visitor clicked an album, reuse the existing [higallery] browsing behavior.
    $nonce        = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    $clicked_path = isset( $_GET['higallery_path'] ) ? sanitize_textarea_field( wp_unslash( $_GET['higallery_path'] ) ) : '';

    if ( $nonce && $clicked_path && wp_verify_nonce( $nonce, 'higallery_browse' ) ) {
        // Render the regular gallery view for the clicked folder.
        if ( function_exists( 'higallery_gallery_shortcode' ) ) {
            return higallery_gallery_shortcode( [ 'path' => $clicked_path ] );
        }
    }
$albums = higallery_get_albums_cached($path, (string) $token);

    if (empty($albums)) {
        return '<p>' . esc_html__( 'No albums found.', 'higallery' ) . '</p>';
    }

    $out = '<div class="higallery-wrapper">';

    $rendered = 0;
    foreach ($albums as $album) {
        if (empty($album['name']) || empty($album['path'])) {
            continue;
        }

        $album_name = (string) $album['name'];
        $album_path = (string) $album['path'];

        // Link back into the existing [higallery] browse mechanism.
        $link = add_query_arg([
            'higallery_path' => $album_path,
            '_wpnonce'       => wp_create_nonce('higallery_browse'),
        ], get_permalink());

        $out .= '<a href="' . esc_url($link) . '" class="higallery-album-link">';
        $out .= '<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>';
        $out .= '<div class="higallery-album-name">' . esc_html($album_name) . '</div>';
        $out .= '</a>';
        $rendered++;
    }

    if ($rendered === 0) {
        $out .= '<p>' . esc_html__( 'No albums found.', 'higallery' ) . '</p>';
    }

    $out .= '</div>';
    return $out;
}

add_shortcode('higallery_all_albums', 'higallery_all_albums_shortcode');

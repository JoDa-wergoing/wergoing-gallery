<?php
/**
 * HiGallery
 *
 * @package HiGallery
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Check whether a given HiDrive path is within the configured root folder.
 *
 * Prevents path traversal attacks on the public proxy endpoint.
 * Blocks any path containing '..' or that does not start with the root folder.
 *
 * @param string $path The HiDrive path to validate.
 * @return bool True if the path is allowed, false otherwise.
 */
function higallery_is_path_allowed( string $path ): bool {
    // Always block directory traversal sequences.
    if ( str_contains( $path, '..' ) ) {
        return false;
    }

    $root = (string) get_option( 'higallery_root_folder', '/' );

    // Normalise: ensure root ends without a trailing slash (except bare '/').
    if ( $root !== '/' ) {
        $root = rtrim( $root, '/' );
    }

    // A bare root of '/' allows everything under the HiDrive account.
    // This is valid — the admin chose it deliberately.
    if ( $root === '/' ) {
        return true;
    }

    // Path must start exactly with the root folder, followed by '/' or end of string.
    // This prevents '/photos-secret' from matching a root of '/photos'.
    return (
        $path === $root ||
        str_starts_with( $path, $root . '/' )
    );
}

/**
 * Fetch a single file from HiDrive and return the raw WP HTTP API response.
 *
 * @param string $path  The HiDrive path of the file.
 * @param string $token A valid OAuth2 access token.
 * @return array|WP_Error WP HTTP API response array, or WP_Error on failure.
 */
function higallery_hidrive_get_file( string $path, string $token ) {
    if ( empty( $path ) || empty( $token ) ) {
        return new WP_Error(
            'higallery_invalid_args',
            __( 'Path and token are required.', 'higallery' )
        );
    }

    $url = add_query_arg(
        [ 'path' => rawurlencode( $path ) ],
        'https://api.hidrive.strato.com/2.1/file'
    );

    $response = wp_remote_get( $url, [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
        ],
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    if ( $code !== 200 ) {
        return new WP_Error(
            'higallery_hidrive_error',
            sprintf(
                /* translators: %d: HTTP status code returned by HiDrive */
                __( 'HiDrive returned status %d.', 'higallery' ),
                $code
            ),
            [ 'status' => $code ]
        );
    }

    return $response;
}

function higallery_api_get_folders($path, $token) {
    if (empty($token)) {
        return [];
    }

    $path = (string) $path;
    $encoded_path = rawurlencode($path);

    // Ask HiDrive for image metadata (width/height) + exif (for orientation)
    $fields = implode(',', [
        'path',
        'name',
        'type',
        'members.path',
        'members.name',
        'members.type',
        'members.mime_type',
        'members.image.width',
        'members.image.height',
        'members.image.exif',
    ]);

    $url = 'https://api.hidrive.strato.com/2.1/dir?path=' . $encoded_path . '&fields=' . rawurlencode($fields);

    $response = wp_remote_get($url, [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        return [];
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        return [];
    }

    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    if (!is_array($json)) {
        return [];
    }

    $members = $json['members'] ?? [];
    if (!is_array($members)) {
        $members = [];
    }

    $albums = [];
    $images = [];

    foreach ($members as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        // IMPORTANT: keys inside each member are name/path/type (not "members.name")
        $type = isset($entry['type']) ? (string) $entry['type'] : '';
        $name = isset($entry['name']) ? (string) $entry['name'] : '';
        $p    = isset($entry['path']) ? (string) $entry['path'] : '';

        // Skip malformed entries
        if ($type === '' || $name === '' || $p === '') {
            continue;
        }

        $entry_info = [
            'name' => urldecode($name),
            'type' => $type,
            'path' => urldecode($p),
        ];

        if (!empty($entry['mime_type'])) {
            $entry_info['mime_type'] = (string) $entry['mime_type'];
        }

        // Optional image metadata (for PhotoSwipe aspect ratio)
        if (!empty($entry['image']) && is_array($entry['image'])) {
            $w = !empty($entry['image']['width'])  ? (int) $entry['image']['width']  : 0;
            $h = !empty($entry['image']['height']) ? (int) $entry['image']['height'] : 0;

            // Try to read EXIF orientation if provided by HiDrive
            $orientation = 1;
            if (!empty($entry['image']['exif']) && is_array($entry['image']['exif'])) {
                $exif = $entry['image']['exif'];

                // Handle common possible keys
                if (isset($exif['Orientation'])) {
                    $orientation = (int) $exif['Orientation'];
                } elseif (isset($exif['orientation'])) {
                    $orientation = (int) $exif['orientation'];
                } elseif (isset($exif['EXIF:Orientation'])) {
                    $orientation = (int) $exif['EXIF:Orientation'];
                }
            }

            // EXIF orientations 5-8 imply 90/270 rotation => swap dimensions
            if ($w > 0 && $h > 0 && in_array($orientation, [5, 6, 7, 8], true)) {
                $tmp = $w; $w = $h; $h = $tmp;
            }

            if ($w > 0) $entry_info['width']  = $w;
            if ($h > 0) $entry_info['height'] = $h;
        }

        // Images
        if ($type === 'file' && preg_match('/\.(jpe?g|png|gif)$/i', $name)) {
            $images[] = $entry_info;
            continue;
        }

        // Albums (folders)
        if ($type === 'dir') {
            $albums[] = $entry_info;
            continue;
        }
    }

    return [
        'albums' => $albums,
        'images' => $images,
    ];
}

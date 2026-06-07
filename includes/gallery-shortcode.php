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

function higallery_gallery_shortcode($atts) {
    $atts = shortcode_atts([
        'path'   => '',
        'albums' => '',
    ], $atts, 'wergoing-gallery');

    $token = higallery_get_valid_access_token();
    if (!$token) {
        return '<p>' . esc_html__( 'No HiDrive connection. Please connect HiDrive first.', 'wergoing-gallery' ) . '</p>';
    }

    $default_root = get_option( 'higallery_root_folder', '/' );

    // Shortcode attribute takes precedence if provided.
    $path = isset( $atts['path'] ) ? sanitize_textarea_field( (string) $atts['path'] ) : '';

    // If the visitor clicked an album, reuse the browse behavior (nonce-protected).
    if ( $path === '' && isset( $_GET['higallery_path'], $_GET['_wpnonce'] ) ) {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately.
        $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

        if ( wp_verify_nonce( $nonce, 'higallery_browse' ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately.
            $path = sanitize_textarea_field( wp_unslash( $_GET['higallery_path'] ) );
        }
    }

    if ( $path === '' ) {
        $path = $default_root;
    }

    $selected_albums = [];
    if (isset($atts['albums']) && $atts['albums'] !== '' && $atts['albums'] !== null) {
        if (is_array($atts['albums'])) {
            $selected_albums = array_filter(array_map('trim', $atts['albums']));
        } else {
            $raw = (string) $atts['albums'];
            $raw = str_replace(['“', '”', '’'], ['"', '"', "'"], $raw);
            $delimiter = (strpos($raw, ';') !== false) ? ';' : ',';
            $selected_albums = array_filter(array_map('trim', explode($delimiter, $raw)));
        }
    }

    $selected_norm = [];
    foreach ($selected_albums as $n) {
        $n = strtolower(trim((string) $n));
        if ($n !== '') {
            $selected_norm[] = $n;
        }
    }

    $api_response = higallery_api_get_folders($path, $token);
    if (empty($api_response) || !is_array($api_response)) {
        return '<p>' . esc_html__( 'Cannot load gallery.', 'wergoing-gallery' ) . '</p>';
    }

    $output = '<div class="higallery-wrapper">';

    if (!empty($api_response['images']) && is_array($api_response['images'])) {

        $rendered = 0;
        $output .= '<div class="pswp-gallery higallery">';

        foreach ($api_response['images'] as $image) {
            if (empty($image['path']) || empty($image['name'])) {
                continue;
            }

            $file_url = add_query_arg('path', $image['path'], rest_url('higallery/v1/file'));

            $thumb_w  = 150;
            $medium_w = 1600;
            $large_w  = 2000;

            $thumb_url  = add_query_arg(['path' => $image['path'], 'width' => $thumb_w],  rest_url('higallery/v1/thumb'));
            $medium_url = add_query_arg(['path' => $image['path'], 'width' => $medium_w], rest_url('higallery/v1/thumb'));
            $large_url  = add_query_arg(['path' => $image['path'], 'width' => $large_w],  rest_url('higallery/v1/thumb'));

            $pswp_w = !empty($image['width'])  ? (int) $image['width']  : 1600;
            $pswp_h = !empty($image['height']) ? (int) $image['height'] : 1067;

            $output .= '<a class="higallery-item" href="' . esc_url($file_url) . '"'
                . ' data-pswp-width="' . esc_attr($pswp_w) . '"'
                . ' data-pswp-height="' . esc_attr($pswp_h) . '"'
                . ' data-hg-medium="' . esc_url($medium_url) . '" data-hg-medium-w="' . esc_attr($medium_w) . '"'
                . ' data-hg-large="' . esc_url($large_url) . '" data-hg-large-w="' . esc_attr($large_w) . '"'
                . ' data-hg-orig="' . esc_url($file_url) . '" data-hg-orig-w="' . esc_attr($pswp_w) . '">';

            $output .= '<img decoding="async" src="' . esc_url($thumb_url) . '" alt="' . esc_attr($image['name']) . '" loading="lazy" />';
            $output .= '</a>';

            $rendered++;
        }

        $output .= '</div>';

        if ($rendered === 0) {
            $output .= '<p>' . esc_html__( 'No images found in this album.', 'wergoing-gallery' ) . '</p>';
        }

    } elseif (!empty($api_response['albums']) && is_array($api_response['albums'])) {

        $rendered = 0;

        foreach ($api_response['albums'] as $album) {
            if (empty($album['name']) || empty($album['path'])) {
                continue;
            }

            $album_name = (string) $album['name'];
            $album_path = (string) $album['path'];

            if (!empty($selected_norm)) {
                $needle = strtolower(trim($album_name));
                if (!in_array($needle, $selected_norm, true)) {
                    continue;
                }
            }

            $link = add_query_arg([
                'higallery_path' => $album_path,
                '_wpnonce'       => wp_create_nonce('higallery_browse'),
            ], get_permalink());



            
            $output .= '<a href="' . esc_url($link) . '" class="higallery-album-link">';
            $output .= '<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>';
            $output .= '<div class="higallery-album-name">' . esc_html($album_name) . '</div>';
            $output .= '</a>';

            $rendered++;
        }

        if ($rendered === 0) {
            if (!empty($selected_norm)) {
                $output .= '<p>' . esc_html__( 'No albums matched the filter.', 'wergoing-gallery' ) . '</p>';
            } else {
                $output .= '<p>' . esc_html__( 'No albums found.', 'wergoing-gallery' ) . '</p>';
            }
        }

    } else {
        $output .= '<p>' . esc_html__( 'No photos found in this album.', 'wergoing-gallery' ) . '</p>';
    }

    $output .= '</div>';
    return $output;
}

add_shortcode('higallery', 'higallery_gallery_shortcode');

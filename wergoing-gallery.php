<?php
/**
 * Plugin Name: weRgoing Gallery for HiDrive
 * Plugin URI:  https://wergoing.com/foto-album
 * Description: Description: Show your STRATO HiDrive photo folders as secure WordPress photo albums without importing images.
 * Version:     1.2.0
 * Author:      weRgoing JoDa
 * Author URI:  https://wergoing.com
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: wergoing-gallery
 * Domain Path: /languages
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Definieer een constante voor de menu-slug
if ( ! defined('HIGALLERY_MENU_SLUG') ) {
    define('HIGALLERY_MENU_SLUG', 'wergoing-gallery-settings');
}


add_action('plugins_loaded', function() {
});

add_shortcode('higallery','higallery_shortcode');

define('HIGALLERY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HIGALLERY_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once HIGALLERY_PLUGIN_DIR . 'includes/oauth.php';
require_once HIGALLERY_PLUGIN_DIR . 'includes/oauth-callback.php';
require_once HIGALLERY_PLUGIN_DIR . 'includes/api-client.php';
require_once HIGALLERY_PLUGIN_DIR . 'includes/gallery-shortcode.php';
require_once HIGALLERY_PLUGIN_DIR . 'includes/all-albums-shortcode.php';
require_once HIGALLERY_PLUGIN_DIR . 'admin/settings-page.php';
require_once HIGALLERY_PLUGIN_DIR . 'includes/proxy-endpoint.php';
require_once HIGALLERY_PLUGIN_DIR . 'includes/gutenberg-block.php';

/**
 * Determine whether the current post/page contains a HiGallery shortcode or block.
 *
 * Called during 'wp' action (after the query is set) so that $post is available.
 *
 * @return bool
 */
function higallery_is_active_on_page(): bool {
    global $post;

    if ( ! ( $post instanceof WP_Post ) ) {
        return false;
    }

    // Gutenberg block.
    if ( has_block( 'wergoing-gallery/block', $post ) ) {
        return true;
    }

    // Either shortcode variant.
    if (
        has_shortcode( $post->post_content, 'wergoing-gallery' ) ||
        has_shortcode( $post->post_content, 'higallery_all_albums' )
    ) {
        return true;
    }

    return false;
}

/**
 * Register (but do not enqueue) all frontend assets.
 *
 * Actual enqueueing happens in higallery_enqueue_frontend_assets(),
 * which only runs on pages that contain a HiGallery shortcode or block.
 */
add_action( 'wp_enqueue_scripts', function () {
    // 'dashicons' is already registered by WordPress core — no need to register it here.
    // We only need to enqueue it on the frontend when our gallery is active.

    wp_register_style(
        'higallery-css',
        HIGALLERY_PLUGIN_URL . 'assets/css/gallery-style.css',
        [ 'dashicons' ],
        '1.2.0'
    );

    wp_register_style(
        'photoswipe-css',
        HIGALLERY_PLUGIN_URL . 'assets/photoswipe/photoswipe.css',
        [],
        '5.4.4'
    );

    wp_register_script(
        'photoswipe-core',
        HIGALLERY_PLUGIN_URL . 'assets/photoswipe/photoswipe.umd.min.js',
        [],
        '5.4.4',
        true
    );

    wp_register_script(
        'photoswipe-js',
        HIGALLERY_PLUGIN_URL . 'assets/photoswipe/photoswipe-lightbox.umd.min.js',
        [ 'photoswipe-core' ],
        '5.4.4',
        true
    );

    wp_register_script(
        'photoswipe-init',
        HIGALLERY_PLUGIN_URL . 'assets/js/photoswipe-init.js',
        [ 'photoswipe-js' ],
        '2.0.0',
        true
    );

    wp_register_script(
        'higallery-photoswipe',
        HIGALLERY_PLUGIN_URL . 'assets/js/higallery-photoswipe.js',
        [ 'photoswipe-js' ],
        '1.2.0',
        true
    );

    wp_register_script(
        'higallery-lazyload',
        HIGALLERY_PLUGIN_URL . 'assets/js/higallery-lazyload.js',
        [],
        '1.2.0',
        true
    );
} );

/**
 * Enqueue all registered HiGallery assets — but only on pages that need them.
 *
 * Runs on 'wp' (after the main query) so has_block() / has_shortcode()
 * can inspect the current post object.
 */
add_action( 'wp', function () {
    if ( ! higallery_is_active_on_page() ) {
        return;
    }

    wp_enqueue_style( 'dashicons' );
    wp_enqueue_style( 'higallery-css' );
    wp_enqueue_style( 'photoswipe-css' );

    wp_enqueue_script( 'photoswipe-core' );
    wp_enqueue_script( 'photoswipe-js' );
    wp_enqueue_script( 'photoswipe-init' );
    wp_enqueue_script( 'higallery-photoswipe' );
    wp_enqueue_script( 'higallery-lazyload' );
} );

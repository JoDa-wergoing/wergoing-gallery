<?php
/**
 * HiGallery uninstall cleanup.
 *
 * Runs automatically when the plugin is deleted via the WordPress admin.
 * Removes all options and transients created by HiGallery.
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

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// 1. Named options — all keys stored via update_option() / register_setting()
// ---------------------------------------------------------------------------
$higallery_option_keys = array(
	'higallery_client_id',
	'higallery_client_secret',
	'higallery_access_token',
	'higallery_refresh_token',
	'higallery_token_expires',
	'higallery_root_folder',
	'higallery_scope',
	'higallery_album_covers',
	'higallery_thumbnail_size',
	'higallery_test_mode',
);

foreach ( $higallery_option_keys as $higallery_key ) {
	delete_option( $higallery_key );
	delete_site_option( $higallery_key ); // Multisite cleanup.
}

// ---------------------------------------------------------------------------
// 2. Transients — album cache and OAuth state tokens
//
//    Three transient patterns are used:
//      - higallery_all_albums_{md5(path)}  — album listing cache (5 min TTL)
//      - higallery_oauth_state_{state}     — OAuth2 CSRF state token (15 min TTL)
//      - higallery_rl_{ip_hash}            — rate limit counter (1 min TTL)
//
//    WordPress stores transients as options prefixed with '_transient_'.
//    We use a direct database query with LIKE to catch all of them at once,
//    which is the standard WordPress approach for wildcard transient cleanup.
// ---------------------------------------------------------------------------
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_higallery_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_higallery_' ) . '%'
	)
);

// Multisite: also clean network transients if applicable.
if ( is_multisite() ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
			$wpdb->esc_like( '_site_transient_higallery_' ) . '%',
			$wpdb->esc_like( '_site_transient_timeout_higallery_' ) . '%'
		)
	);
}

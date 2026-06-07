<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
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

add_action('rest_api_init', function () {
    register_rest_route('wergoing-gallery', '/oauth/callback', [
        'methods'  => 'GET',
        'callback' => 'higallery_handle_oauth_callback',
        // Open callback is oké, maar we vertrouwen op state:
        'permission_callback' => '__return_true',
    ]);
});

function higallery_handle_oauth_callback($request) {
    $code  = sanitize_text_field($request->get_param('code'));
    $state = sanitize_text_field($request->get_param('state'));

    if (empty($code) || empty($state)) {
        return higallery_callback_redirect(__('OAuth Callback: ontbrekende code of state.', 'wergoing-gallery'), 'error');
    }

    $ok = get_transient('higallery_oauth_state_' . $state);
    delete_transient('higallery_oauth_state_' . $state);
    if (!$ok) {
        return higallery_callback_redirect(__('Ongeldige of verlopen state. Probeer opnieuw.', 'wergoing-gallery'), 'error');
    }

    $tokens = higallery_exchange_code_for_token($code);
    if (is_wp_error($tokens)) {
        return higallery_callback_redirect(__('Token exchange mislukt. Controleer client-id/-secret, scope en redirect URI.', 'wergoing-gallery'), 'error');
    }

    $access  = isset($tokens['access_token'])  ? $tokens['access_token']  : '';
    $refresh = isset($tokens['refresh_token']) ? $tokens['refresh_token'] : '';
    $exp     = isset($tokens['expires_in'])    ? (int)$tokens['expires_in'] : 0;

    if ($access === '' || $refresh === '') {
        return higallery_callback_redirect(__('Provider gaf geen volledige tokens terug (missende refresh_token?). Controleer scope (bijv. user,ro) en app-config.', 'wergoing-gallery'), 'error');
    }

    update_option('higallery_access_token',  $access);
    update_option('higallery_refresh_token', $refresh);
    if ($exp > 0) {
        update_option('higallery_token_expires', time() + $exp - 30);
    }

    return higallery_callback_redirect(__('HiDrive verbinding geslaagd!', 'wergoing-gallery'), 'success');
}

function higallery_callback_redirect($message, $type = 'success') {

    $url = admin_url('admin.php?page=higallery-settings');
    $url = add_query_arg([
        'higallery_msg'  => rawurlencode($message),
        'higallery_type' => $type,
    ], $url);

    return new WP_REST_Response(['redirect' => $url], 302, ['Location' => $url]);
}
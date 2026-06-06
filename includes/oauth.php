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

if ( ! defined('HIGALLERY_HIDRIVE_AUTHORIZE') ) {
    define('HIGALLERY_HIDRIVE_AUTHORIZE', 'https://my.hidrive.com/oauth2/authorize');
}
if ( ! defined('HIGALLERY_HIDRIVE_TOKEN') ) {
    define('HIGALLERY_HIDRIVE_TOKEN', 'https://my.hidrive.com/oauth2/token');
}


function higallery_get_oauth_authorize_url() {
    $client_id = get_option('higallery_client_id');
    if (empty($client_id)) {
        return '#';
    }

    $redirect_uri = higallery_get_redirect_uri();

    $scopes = trim(get_option('higallery_scope', 'user,ro'));
    if ($scopes === '') {
        return '#';
    }

    $state = wp_generate_password(12, false);
    set_transient('higallery_oauth_state_' . $state, time(), 900);

    $query = http_build_query([
        'response_type' => 'code',
        'client_id'     => $client_id,
        'redirect_uri'  => $redirect_uri,
        'scope'         => $scopes,
        'state'         => $state,
    ], '', '&', PHP_QUERY_RFC3986);

    return HIGALLERY_HIDRIVE_AUTHORIZE . '?' . $query;
}

function higallery_get_redirect_uri() {
    return rest_url('higallery/oauth/callback');
}

function higallery_exchange_code_for_token($code) {
    $client_id     = get_option('higallery_client_id');
    $client_secret = get_option('higallery_client_secret');
    $redirect_uri  = higallery_get_redirect_uri();

    if (empty($client_id) || empty($client_secret)) {
        return new WP_Error('oauth_config', __( 'Client ID or secret is missing.', 'higallery' ));
    }

    $body = [
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => $redirect_uri,
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
    ];

    $resp = wp_remote_post(HIGALLERY_HIDRIVE_TOKEN, [
        'timeout' => 20,
        'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        'body'    => $body,
    ]);
    if (is_wp_error($resp)) {
        return $resp;
    }

    $code = wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code !== 200 || !is_array($json)) {
        return new WP_Error('oauth_bad_response', __( 'Invalid token response from HiDrive.', 'higallery' ));
    }

    return $json;
}

function higallery_refresh_token() {
    $client_id     = get_option('higallery_client_id');
    $client_secret = get_option('higallery_client_secret');
    $refresh_token = get_option('higallery_refresh_token');

    if (empty($client_id) || empty($client_secret) || empty($refresh_token)) {
        return false;
    }

    $resp = wp_remote_post(HIGALLERY_HIDRIVE_TOKEN, [
        'timeout' => 20,
        'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        'body'    => [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refresh_token,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
        ],
    ]);
    if (is_wp_error($resp)) {
        return false;
    }

    $code = wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code !== 200 || !is_array($json) || empty($json['access_token'])) {
        return false;
    }

    update_option('higallery_access_token', $json['access_token']);
    if (!empty($json['refresh_token'])) { // sommige providers geven een nieuwe
        update_option('higallery_refresh_token', $json['refresh_token']);
    }
    if (!empty($json['expires_in'])) {
        update_option('higallery_token_expires', time() + (int)$json['expires_in'] - 30);
    }
    return $json['access_token'];
}

function higallery_get_valid_access_token() {
    $access_token = get_option('higallery_access_token');
    $expires = (int) get_option('higallery_token_expires', 0);

    if ($access_token && time() < $expires) {
        return $access_token;
    }
    return higallery_refresh_token();
}

?>
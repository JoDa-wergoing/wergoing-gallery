<?php
/**
 * HiGallery Proxy Endpoint
 *
 * Streams files from HiDrive via a WordPress REST endpoint.
 *
 * Rate limiting: max 60 requests per IP per minute (configurable via filter).
 * Uses WordPress transients — no external dependencies required.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', function () {
    register_rest_route(
        'higallery/v1',
        '/file',
        [
            'methods'             => 'GET',
            'callback'            => 'higallery_proxy_file',
            'permission_callback' => '__return_true', // Public by design (documented).
        ]
    );
} );

/**
 * Retrieve the request IP address as a stable, hashed key for transients.
 *
 * We hash the IP so it is never stored in plain text in the database.
 * REMOTE_ADDR is used directly; proxies behind a trusted reverse proxy
 * may set HTTP_X_FORWARDED_FOR, but trusting that header unconditionally
 * is a security risk (it can be spoofed), so we deliberately ignore it here.
 *
 * @return string An 8-character hex hash of the remote IP, or 'unknown'.
 */
function higallery_get_rate_limit_key(): string {
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REMOTE_ADDR contains no slashable characters; wp_unslash() is a no-op but included for PHPCS compliance.
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';

    if ( '' === $ip ) {
        return 'unknown';
    }

    // Short hash — just needs to be consistent, not cryptographically secure.
    return substr( md5( $ip ), 0, 12 );
}

/**
 * Check whether the current request is within the allowed rate limit.
 *
 * Increments a per-IP counter stored as a transient. The transient expires
 * after one minute (the rate-limit window). Once the limit is reached,
 * further requests within that window return false.
 *
 * Defaults:
 *   - 60 requests per minute per IP
 *
 * Developers can adjust via the filters:
 *   - higallery_rate_limit_max    (int)  maximum requests per window
 *   - higallery_rate_limit_window (int)  window size in seconds
 *
 * @return bool True if the request is allowed, false if rate-limited.
 */
function higallery_check_rate_limit(): bool {
    /**
     * Maximum number of proxy requests allowed per IP per window.
     *
     * @param int $max Default 60.
     */
    $max = (int) apply_filters( 'higallery_rate_limit_max', 60 );

    /**
     * Rate-limit window in seconds.
     *
     * @param int $window Default 60 (one minute).
     */
    $window = (int) apply_filters( 'higallery_rate_limit_window', 60 );

    // Clamp to sensible bounds so filter mistakes don't break the site.
    $max    = max( 1, min( 1000, $max ) );
    $window = max( 10, min( 3600, $window ) );

    $key            = 'higallery_rl_' . higallery_get_rate_limit_key();
    $current_count  = (int) get_transient( $key );

    if ( $current_count >= $max ) {
        return false; // Rate limit exceeded.
    }

    if ( 0 === $current_count ) {
        // First request in this window — create the transient with the TTL.
        set_transient( $key, 1, $window );
    } else {
        // Subsequent requests — increment the counter.
        // set_transient() resets the TTL, so we update the value only.
        // We use the options table directly to avoid resetting the expiry.
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->options}
                 SET option_value = option_value + 1
                 WHERE option_name = %s",
                '_transient_' . $key
            )
        );
    }

    return true;
}

/**
 * REST callback to proxy a file from HiDrive.
 *
 * @param WP_REST_Request $request
 * @return void|WP_Error
 */
function higallery_proxy_file( WP_REST_Request $request ) {

    // --- Rate limiting ---------------------------------------------------
    if ( ! higallery_check_rate_limit() ) {
        // Send standard HTTP 429 with Retry-After header.
        // WP_Error does not support arbitrary headers, so we set it manually.
        if ( ! headers_sent() ) {
            header( 'Retry-After: 60' );
        }
        return new WP_Error(
            'higallery_rate_limited',
            __( 'Too many requests. Please try again later.', 'higallery' ),
            [ 'status' => 429 ]
        );
    }

    // --- Input validation ------------------------------------------------
    $raw_path = $request->get_param( 'path' );
    $path     = sanitize_textarea_field( (string) wp_unslash( $raw_path ) );

    if ( empty( $path ) ) {
        return new WP_Error(
            'higallery_invalid_path',
            __( 'Invalid or missing path parameter.', 'higallery' ),
            [ 'status' => 400 ]
        );
    }

    // --- Path validation -------------------------------------------------
    if ( ! higallery_is_path_allowed( $path ) ) {
        return new WP_Error(
            'higallery_forbidden_path',
            __( 'Access to this path is not allowed.', 'higallery' ),
            [ 'status' => 403 ]
        );
    }

    // --- Token -----------------------------------------------------------
    $token = higallery_get_valid_access_token();
    if ( ! $token ) {
        return new WP_Error(
            'higallery_no_token',
            __( 'No valid HiDrive access token available.', 'higallery' ),
            [ 'status' => 401 ]
        );
    }

    // --- Fetch from HiDrive ----------------------------------------------
    $response = higallery_hidrive_get_file( $path, $token );
    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = wp_remote_retrieve_body( $response );
    $mime = wp_remote_retrieve_header( $response, 'content-type' );

    if ( empty( $body ) ) {
        return new WP_Error(
            'higallery_empty_response',
            __( 'Empty response from HiDrive.', 'higallery' ),
            [ 'status' => 502 ]
        );
    }

    if ( ! $mime ) {
        $mime = 'application/octet-stream';
    }

    // --- Output ----------------------------------------------------------
    header( 'Content-Type: ' . $mime );
    header( 'Content-Length: ' . strlen( $body ) );
    header( 'Cache-Control: public, max-age=300' );

    // Binary output must not be escaped.
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary file stream (image bytes)
    echo $body;
    exit;
}

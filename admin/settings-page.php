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

// ---------------------------------------------------------------------------
// Admin notices
// ---------------------------------------------------------------------------

/**
 * Determine the current HiDrive connection status.
 *
 * Returns one of three states:
 *   'unconfigured' — Client ID or secret is missing.
 *   'disconnected' — Credentials exist but no valid token is stored.
 *   'connected'    — A valid (or refreshable) token is present.
 *
 * @return string
 */
function higallery_connection_status(): string {
	$client_id     = get_option( 'higallery_client_id', '' );
	$client_secret = get_option( 'higallery_client_secret', '' );

	if ( empty( $client_id ) || empty( $client_secret ) ) {
		return 'unconfigured';
	}

	$access_token = get_option( 'higallery_access_token', '' );
	$expires      = (int) get_option( 'higallery_token_expires', 0 );

	// Token present and not yet expired.
	if ( $access_token && time() < $expires ) {
		return 'connected';
	}

	// No token at all, or token expired and no refresh possible.
	$refresh_token = get_option( 'higallery_refresh_token', '' );
	if ( $refresh_token ) {
		// Refresh token exists — assume refreshable (connected).
		return 'connected';
	}

	return 'disconnected';
}

/**
 * Show a global admin notice when HiDrive is not connected.
 *
 * Only shown to users who can manage options, and only outside the
 * HiGallery settings page itself (where the inline status is shown instead).
 */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Don't show on the HiGallery settings page — inline status is shown there.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$current_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
	if ( $current_page === HIGALLERY_MENU_SLUG ) {
		return;
	}

	$status = higallery_connection_status();

	if ( 'unconfigured' === $status ) {
		$settings_url = admin_url( 'options-general.php?page=' . HIGALLERY_MENU_SLUG );
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'HiGallery: Client ID and secret are not configured.', 'higallery' ),
			esc_url( $settings_url ),
			esc_html__( 'Go to settings', 'higallery' )
		);
		return;
	}

	if ( 'disconnected' === $status ) {
		$settings_url = admin_url( 'options-general.php?page=' . HIGALLERY_MENU_SLUG );
		printf(
			'<div class="notice notice-error"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'HiGallery: Not connected to HiDrive. Galleries will not load.', 'higallery' ),
			esc_url( $settings_url ),
			esc_html__( 'Reconnect', 'higallery' )
		);
	}
} );

add_action( 'admin_menu', function () {
	add_options_page(
		__( 'HiGallery', 'higallery' ),          
		__( 'HiGallery', 'higallery' ),          
		'manage_options',                        
		'higallery-settings',                    
		'higallery_render_settings_page'         
	);
} );

function higallery_render_settings_page() {
	$status = higallery_connection_status();
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'HiGallery settings', 'higallery' ); ?></h1>

		<?php if ( 'connected' === $status ) : ?>
		<div class="notice notice-success inline">
			<p>
				<strong><?php esc_html_e( 'HiDrive: connected', 'higallery' ); ?></strong>
				&mdash; <?php esc_html_e( 'Your HiDrive account is linked and galleries will load normally.', 'higallery' ); ?>
			</p>
		</div>
		<?php elseif ( 'disconnected' === $status ) : ?>
		<div class="notice notice-error inline">
			<p>
				<strong><?php esc_html_e( 'HiDrive: not connected', 'higallery' ); ?></strong>
				&mdash; <?php esc_html_e( 'Credentials are saved but no valid token exists. Use the button below to reconnect.', 'higallery' ); ?>
			</p>
		</div>
		<?php elseif ( 'unconfigured' === $status ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( 'HiDrive: not configured', 'higallery' ); ?></strong>
				&mdash; <?php esc_html_e( 'Enter your Client ID and Client Secret below, then connect to HiDrive.', 'higallery' ); ?>
			</p>
		</div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php
			settings_fields( 'higallery_settings' );
			do_settings_sections( 'higallery-settings' );
			submit_button();
			?>
		</form>

		<hr>

		<h2><?php echo esc_html__( 'OAuth2 connection', 'higallery' ); ?></h2>
		<p><?php echo esc_html__( 'Connect HiGallery to your HiDrive account:', 'higallery' ); ?></p>
		<?php
		$auth_url = higallery_get_oauth_authorize_url();
		printf(
			'<a href="%s" class="button button-primary">%s</a>',
			esc_url( $auth_url ),
			esc_html__( 'Connect to HiDrive', 'higallery' )
		);
		?>
	</div>
	<?php
}

function higallery_sanitize_root_folder( $value ) {
	$value = is_string( $value ) ? sanitize_text_field( $value ) : '';

	if ( '' === $value ) {
		return '/';
	}

	if ( '/' !== $value[0] ) {
		$value = '/' . $value;
	}

	if ( false !== strpos( $value, '..' ) ) {
		return '/';
	}

	return rtrim( $value, '/' );
}

function higallery_sanitize_checkbox( $value ) {
	return empty( $value ) ? 0 : 1;
}

function higallery_sanitize_thumbnail_size( $value ) {
	$value = absint( $value );
	return max( 50, min( 1000, $value ) );
}

function higallery_sanitize_client_secret( $input ) {
	$input  = is_string( $input ) ? trim( $input ) : '';
	$stored = (string) get_option( 'higallery_client_secret', '' );

	if ( '' === $input ) {
		return $stored;
	}

	if (
		$stored &&
		strlen( $input ) === strlen( $stored ) &&
		preg_match( '/^\*+$/', $input )
	) {
		return $stored;
	}

	return sanitize_text_field( $input );
}

add_action( 'admin_init', function () {

	register_setting(
		'higallery_settings',
		'higallery_client_id',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);

	register_setting(
		'higallery_settings',
		'higallery_client_secret',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'higallery_sanitize_client_secret',
		)
	);

	register_setting(
		'higallery_settings',
		'higallery_root_folder',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'higallery_sanitize_root_folder',
		)
	);

	register_setting(
		'higallery_settings',
		'higallery_thumbnail_size',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'higallery_sanitize_thumbnail_size',
		)
	);

	register_setting(
		'higallery_settings',
		'higallery_test_mode',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'higallery_sanitize_checkbox',
		)
	);

	add_settings_section(
		'higallery_main',
		esc_html__( 'Configuration', 'higallery' ),
		null,
		'higallery-settings'
	);

	add_settings_field(
		'higallery_client_id',
		esc_html__( 'Client ID', 'higallery' ),
		function () {
			$value = get_option( 'higallery_client_id', '' );
			printf(
				'<input type="text" name="higallery_client_id" value="%s" class="regular-text" />',
				esc_attr( $value )
			);
		},
		'higallery-settings',
		'higallery_main'
	);

	add_settings_field(
		'higallery_client_secret',
		esc_html__( 'Client Secret', 'higallery' ),
		function () {
			$stored  = (string) get_option( 'higallery_client_secret', '' );
			$display = $stored ? str_repeat( '*', strlen( $stored ) ) : '';

			printf(
				'<input type="password" name="higallery_client_secret" value="%s" class="regular-text" autocomplete="new-password" placeholder="%s" />',
				esc_attr( $display ),
				esc_attr__( 'Enter new secret', 'higallery' )
			);

			if ( $stored ) {
				echo '<p class="description">' .
					esc_html__( 'The client secret is stored securely. Enter a new value to replace it.', 'higallery' ) .
				'</p>';
			}
		},
		'higallery-settings',
		'higallery_main'
	);

	add_settings_field(
		'higallery_root_folder',
		esc_html__( 'HiGallery root folder', 'higallery' ),
		function () {
			$value = get_option( 'higallery_root_folder', '/' );
			printf(
				'<input type="text" name="higallery_root_folder" value="%s" class="regular-text" />',
				esc_attr( $value )
			);
		},
		'higallery-settings',
		'higallery_main'
	);

	add_settings_field(
		'higallery_thumbnail_size',
		esc_html__( 'Thumbnail width (px)', 'higallery' ),
		function () {
			$value = get_option( 'higallery_thumbnail_size', 150 );
			printf(
				'<input type="number" name="higallery_thumbnail_size" value="%s" min="50" max="1000" class="small-text" /> px',
				esc_attr( $value )
			);
		},
		'higallery-settings',
		'higallery_main'
	);

	add_settings_field(
		'higallery_test_mode',
		esc_html__( 'Test mode', 'higallery' ),
		function () {
			$value = (int) get_option( 'higallery_test_mode', 0 );
			printf(
				'<label><input type="checkbox" name="higallery_test_mode" value="1" %s /> %s</label>',
				checked( 1, $value, false ),
				esc_html__( 'Use demo albums without HiDrive connection', 'higallery' )
			);
		},
		'higallery-settings',
		'higallery_main'
	);

} );

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
			esc_html__( 'HiGallery: Client ID and secret are not configured.', 'wergoing-gallery' ),
			esc_url( $settings_url ),
			esc_html__( 'Go to settings', 'wergoing-gallery' )
		);
		return;
	}

	if ( 'disconnected' === $status ) {
		$settings_url = admin_url( 'options-general.php?page=' . HIGALLERY_MENU_SLUG );
		printf(
			'<div class="notice notice-error"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'HiGallery: Not connected to HiDrive. Galleries will not load.', 'wergoing-gallery' ),
			esc_url( $settings_url ),
			esc_html__( 'Reconnect', 'wergoing-gallery' )
		);
	}
} );

add_action( 'admin_menu', function () {
	add_options_page(
		__( 'weRgoing Gallery', 'wergoing-gallery' ),
		__( 'weRgoing Gallery', 'wergoing-gallery' ),
		'manage_options',
		'wergoing-gallery-settings',
		'higallery_render_settings_page'
	);
} );

function higallery_render_settings_page() {
	$status = higallery_connection_status();
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'HiGallery settings', 'wergoing-gallery' ); ?></h1>

		<?php if ( 'connected' === $status ) : ?>
		<div class="notice notice-success inline">
			<p>
				<strong><?php esc_html_e( 'HiDrive: connected', 'wergoing-gallery' ); ?></strong>
				&mdash; <?php esc_html_e( 'Your HiDrive account is linked and galleries will load normally.', 'wergoing-gallery' ); ?>
			</p>
		</div>
		<?php elseif ( 'disconnected' === $status ) : ?>
		<div class="notice notice-error inline">
			<p>
				<strong><?php esc_html_e( 'HiDrive: not connected', 'wergoing-gallery' ); ?></strong>
				&mdash; <?php esc_html_e( 'Credentials are saved but no valid token exists. Use the button below to reconnect.', 'wergoing-gallery' ); ?>
			</p>
		</div>
		<?php elseif ( 'unconfigured' === $status ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( 'HiDrive: not configured', 'wergoing-gallery' ); ?></strong>
				&mdash; <?php esc_html_e( 'Enter your Client ID and Client Secret below, then connect to HiDrive.', 'wergoing-gallery' ); ?>
			</p>
		</div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php
			settings_fields( 'wergoing_gallery_settings' );
			do_settings_sections( 'wergoing-gallery-settings' );
			submit_button();
			?>
		</form>

		<hr>

		<h2><?php echo esc_html__( 'OAuth2 connection', 'wergoing-gallery' ); ?></h2>
		<p><?php echo esc_html__( 'Connect this plugin to your HiDrive account:', 'wergoing-gallery' ); ?></p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<?php echo esc_html__( 'Redirect URI', 'wergoing-gallery' ); ?>
				</th>
				<td>
					<?php
					$callback_url = rest_url( 'wergoing-gallery/oauth/callback' );
					?>
					<code style="user-select:all; font-size:13px;"><?php echo esc_url( $callback_url ); ?></code>
					<button
						type="button"
						class="button button-secondary"
						style="margin-left:8px;"
						onclick="navigator.clipboard.writeText('<?php echo esc_js( $callback_url ); ?>').then(function(){ this.textContent='<?php echo esc_js( __( 'Copied!', 'wergoing-gallery' ) ); ?>'; }.bind(this));"
					>
						<?php echo esc_html__( 'Copy', 'wergoing-gallery' ); ?>
					</button>
					<p class="description">
						<?php echo esc_html__( 'Use this exact URL when registering your app at developer.hidrive.com/contact.', 'wergoing-gallery' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php
		$auth_url = higallery_get_oauth_authorize_url();
		printf(
			'<a href="%s" class="button button-primary">%s</a>',
			esc_url( $auth_url ),
			esc_html__( 'Connect to HiDrive', 'wergoing-gallery' )
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
	// OAuth2 client secrets may contain characters that sanitize_text_field()
	// would strip or modify (e.g. <, >, &, special UTF-8). Per WordPress.org
	// review feedback, we intentionally avoid sanitize_text_field() here and
	// only unslash + trim, which is safe for password-like values.
	$input  = is_string( $input ) ? trim( wp_unslash( $input ) ) : '';
	$stored = (string) get_option( 'higallery_client_secret', '' );

	// Empty input = keep existing stored value (field was left blank).
	if ( '' === $input ) {
		return $stored;
	}

	// Masked placeholder (all asterisks, same length) = keep existing stored value.
	if (
		$stored &&
		strlen( $input ) === strlen( $stored ) &&
		preg_match( '/^\*+$/', $input )
	) {
		return $stored;
	}

	// Store the raw (unslashed, trimmed) secret value.
	return $input;
}

add_action( 'admin_init', function () {

	register_setting(
		'wergoing_gallery_settings',
		'higallery_client_id',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);

	register_setting(
		'wergoing_gallery_settings',
		'higallery_client_secret',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'higallery_sanitize_client_secret',
		)
	);

	register_setting(
		'wergoing_gallery_settings',
		'higallery_root_folder',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'higallery_sanitize_root_folder',
		)
	);

	register_setting(
		'wergoing_gallery_settings',
		'higallery_thumbnail_size',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'higallery_sanitize_thumbnail_size',
		)
	);

	register_setting(
		'wergoing_gallery_settings',
		'higallery_test_mode',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'higallery_sanitize_checkbox',
		)
	);

	add_settings_section(
		'higallery_main',
		esc_html__( 'Configuration', 'wergoing-gallery' ),
		null,
		'wergoing-gallery-settings'
	);

	add_settings_field(
		'higallery_client_id',
		esc_html__( 'Client ID', 'wergoing-gallery' ),
		function () {
			$value = get_option( 'higallery_client_id', '' );
			printf(
				'<input type="text" name="higallery_client_id" value="%s" class="regular-text" />',
				esc_attr( $value )
			);
		},
		'wergoing-gallery-settings',
		'higallery_main'
	);

	add_settings_field(
		'higallery_client_secret',
		esc_html__( 'Client Secret', 'wergoing-gallery' ),
		function () {
			$stored  = (string) get_option( 'higallery_client_secret', '' );
			$display = $stored ? str_repeat( '*', strlen( $stored ) ) : '';

			printf(
				'<input type="password" name="higallery_client_secret" value="%s" class="regular-text" autocomplete="new-password" placeholder="%s" />',
				esc_attr( $display ),
				esc_attr__( 'Enter new secret', 'wergoing-gallery' )
			);

			if ( $stored ) {
				echo '<p class="description">' .
					esc_html__( 'The client secret is stored securely. Enter a new value to replace it.', 'wergoing-gallery' ) .
				'</p>';
			}
		},
		'wergoing-gallery-settings',
		'higallery_main'
	);

	add_settings_field(
		'higallery_root_folder',
		esc_html__( 'HiGallery root folder', 'wergoing-gallery' ),
		function () {
			$value = get_option( 'higallery_root_folder', '/' );
			printf(
				'<input type="text" name="higallery_root_folder" value="%s" class="regular-text" />',
				esc_attr( $value )
			);
		},
		'wergoing-gallery-settings',
		'higallery_main'
	);

	add_settings_field(
		'higallery_thumbnail_size',
		esc_html__( 'Thumbnail width (px)', 'wergoing-gallery' ),
		function () {
			$value = get_option( 'higallery_thumbnail_size', 150 );
			printf(
				'<input type="number" name="higallery_thumbnail_size" value="%s" min="50" max="1000" class="small-text" /> px',
				esc_attr( $value )
			);
		},
		'wergoing-gallery-settings',
		'higallery_main'
	);

	add_settings_field(
		'higallery_test_mode',
		esc_html__( 'Test mode', 'wergoing-gallery' ),
		function () {
			$value = (int) get_option( 'higallery_test_mode', 0 );
			printf(
				'<label><input type="checkbox" name="higallery_test_mode" value="1" %s /> %s</label>',
				checked( 1, $value, false ),
				esc_html__( 'Use demo albums without HiDrive connection', 'wergoing-gallery' )
			);
		},
		'wergoing-gallery-settings',
		'higallery_main'
	);

} );

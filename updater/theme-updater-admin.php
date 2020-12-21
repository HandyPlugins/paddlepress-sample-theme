<?php
/**
 * Theme updater admin page and functions.
 *
 * @package PaddlePress
 */

namespace PaddlePress\Sample;

// phpcs:disable WordPress.Security.NonceVerification.Recommended
// phpcs:disable WordPress.DateTime.CurrentTimeTimestamp.Requested
// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralText

/**
 * Class Theme_Updater_Admin
 */
class Theme_Updater_Admin {

	/**
	 * License endpoint
	 *
	 * @var string
	 */
	protected $license_api_url = null;
	/**
	 * Update endpoint
	 *
	 * @var string
	 */
	protected $update_api_url = null;

	/**
	 * The slug of the theme
	 *
	 * @var string|null
	 */
	protected $theme_slug = null;

	/**
	 * Download tag (slug) of the download item
	 *
	 * @var string
	 */
	protected $download_tag = null;

	/**
	 * Current version of theme
	 *
	 * @var string
	 */
	protected $version = null;

	/**
	 * Author
	 *
	 * @var |null
	 */
	protected $author = null;

	/**
	 * User feedback messages
	 *
	 * @var array
	 */
	protected $strings = null;

	/**
	 * Initialize the class.
	 *
	 * @param array $config  config parameters
	 * @param array $strings feedback messages
	 *
	 * @since 1.0.0
	 */
	public function __construct( $config = array(), $strings = array() ) {
		$config = wp_parse_args(
			$config,
			array(
				'license_api_url' => 'http://example.org/wp-json/paddlepress-api/v1/license',
				'update_api_url'  => 'http://example.org/wp-json/paddlepress-api/v1/update',
				'theme_slug'      => get_template(),
				'license_key'     => '',
				'license_url'     => '',
				'version'         => '',
				'author'          => '',
				'download_tag'    => '',
				'beta'            => false,
			)
		);

		/**
		 * Fires after the theme $config is setup.
		 *
		 * @param array $config Array of theme data.
		 *
		 * @since 1.0.0
		 */
		do_action( 'post_paddlepress_theme_updater_setup', $config );

		// Set config arguments
		$this->license_api_url = $config['license_api_url'];
		$this->update_api_url  = $config['update_api_url'];
		$this->download_tag    = $config['download_tag'];
		$this->theme_slug      = sanitize_key( $config['theme_slug'] );
		$this->version         = $config['version'];
		$this->author          = $config['author'];
		$this->beta            = $config['beta'];

		// Populate version fallback
		if ( '' === $config['version'] ) {
			$theme         = wp_get_theme( $this->theme_slug );
			$this->version = $theme->get( 'Version' );
		}

		// Strings passed in from the updater config
		$this->strings = $strings;

		add_action( 'init', array( $this, 'updater' ) );
		add_action( 'admin_init', array( $this, 'register_option' ) );
		add_action( 'admin_init', array( $this, 'license_action' ) );
		add_action( 'admin_menu', array( $this, 'license_menu' ) );
		add_action( 'update_option_' . $this->theme_slug . '_license_key', array( $this, 'activate_license' ), 10, 2 );
		add_filter( 'http_request_args', array( $this, 'disable_wporg_request' ), 5, 2 );
		add_action( 'admin_notices', array( $this, 'theme_admin_notices' ) );
	}

	/**
	 * Creates the updater class.
	 * since 1.0.0
	 */
	public function updater() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/* If there is no valid license key status, don't allow updates. */
		if ( get_option( $this->theme_slug . '_license_key_status', false ) !== 'valid' ) {
			return;
		}

		if ( ! class_exists( 'PaddlePress\Theme_Updater' ) ) {
			// Load our custom theme updater
			include dirname( __FILE__ ) . '/theme-updater-class.php';
		}

		new \PaddlePress\Sample\Theme_Updater(
			array(
				'update_api_url' => $this->update_api_url,
				'version'        => $this->version,
				'download_tag'   => $this->download_tag,
				'license_key'    => trim( get_option( $this->theme_slug . '_license_key' ) ),
				'license_url'    => home_url(),
				'author'         => $this->author,
				'beta'           => $this->beta,
			),
			$this->strings
		);
	}

	/**
	 * Adds a menu item for the theme license under the appearance menu.
	 * since 1.0.0
	 */
	public function license_menu() {
		$strings = $this->strings;
		add_theme_page(
			$strings['theme-license'],
			$strings['theme-license'],
			'manage_options',
			$this->theme_slug . '-license',
			array( $this, 'license_page' )
		);
	}

	/**
	 * Outputs the markup used on the theme license page.
	 * since 1.0.0
	 */
	public function license_page() {
		$strings = $this->strings;

		$license = trim( get_option( $this->theme_slug . '_license_key' ) );
		$status  = get_option( $this->theme_slug . '_license_key_status', false );

		// Checks license status to display under license key
		if ( ! $license ) {
			$message = $strings['enter-key'];
		} else {
			if ( ! get_transient( $this->theme_slug . '_license_message' ) ) {
				set_transient( $this->theme_slug . '_license_message', $this->check_license(), ( 60 * 60 * 24 ) );
			}
			$message = get_transient( $this->theme_slug . '_license_message' );
		}
		?>
		<div class="wrap">
		<h2><?php echo esc_html( $strings['theme-license'] ); ?></h2>
		<form method="post" action="options.php">
			<?php settings_fields( $this->theme_slug . '-license' ); ?>
			<table class="form-table">
				<tbody>
				<tr valign="top">
					<th scope="row" valign="top">
						<?php echo esc_html( $strings['license-key'] ); ?>
					</th>
					<td>
						<input id="<?php echo esc_attr( $this->theme_slug ); ?>_license_key" name="<?php echo esc_attr( $this->theme_slug ); ?>_license_key" type="text" class="regular-text" value="<?php echo esc_attr( $license ); ?>" />
						<p class="description">
							<?php echo esc_html( $message ); ?>
						</p>
					</td>
				</tr>

				<?php if ( $license ) { ?>
					<tr valign="top">
						<th scope="row" valign="top">
							<?php echo esc_html( $strings['license-action'] ); ?>
						</th>
						<td>
							<?php
							wp_nonce_field( $this->theme_slug . '_nonce', $this->theme_slug . '_nonce' );
							if ( 'valid' === $status ) {
								?>
								<input type="submit" class="button-secondary" name="<?php echo esc_attr( $this->theme_slug ); ?>_license_deactivate" value="<?php esc_attr_e( $strings['deactivate-license'] ); ?>" />
							<?php } else { ?>
								<input type="submit" class="button-secondary" name="<?php echo esc_attr( $this->theme_slug ); ?>_license_activate" value="<?php esc_attr_e( $strings['activate-license'] ); ?>" />
								<?php
							}
							?>
						</td>
					</tr>
				<?php } ?>

				</tbody>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Registers the option used to store the license key in the options table.
	 * since 1.0.0
	 */
	public function register_option() {
		register_setting(
			$this->theme_slug . '-license',
			$this->theme_slug . '_license_key',
			array( $this, 'sanitize_license' )
		);
	}

	/**
	 * Sanitizes the license key.
	 * since 1.0.0
	 *
	 * @param string $new License key that was submitted.
	 *
	 * @return string $new Sanitized license key.
	 */
	public function sanitize_license( $new ) {
		$old = get_option( $this->theme_slug . '_license_key' );

		if ( $old && $old !== $new ) {
			// New license has been entered, so must reactivate
			delete_option( $this->theme_slug . '_license_key_status' );
			delete_transient( $this->theme_slug . '_license_message' );
		}

		return $new;
	}

	/**
	 * Makes a call to the API.
	 *
	 * @param array $api_params to be used for wp_remote_get.
	 *
	 * @return array $response decoded JSON response.
	 * @since 1.0.0
	 */
	public function get_api_response( $api_params ) {
		// Call the custom API.
		$verify_ssl = (bool) apply_filters( 'paddlepress_api_request_verify_ssl', true );
		$response   = wp_remote_post(
			$this->license_api_url,
			array(
				'timeout'   => 15,
				'sslverify' => $verify_ssl,
				'body'      => $api_params,
			)
		);

		return $response;
	}

	/**
	 * Activates the license key.
	 *
	 * @since 1.0.0
	 */
	public function activate_license() {
		$license = trim( get_option( $this->theme_slug . '_license_key' ) );

		// Data to send in our API request.
		$api_params = array(
			'action'       => 'activate',
			'license_key'  => $license,
			'license_url'  => home_url(),
			'download_tag' => $this->download_tag,
		);

		$response = $this->get_api_response( $api_params );

		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
			} else {
				$message = __( 'An error occurred, please try again.' );
			}

			$base_url = admin_url( 'themes.php?page=' . $this->theme_slug . '-license' );
			$redirect = add_query_arg(
				array(
					'pp_theme_activation' => 'false',
					'message'             => rawurlencode( $message ),
				),
				$base_url
			);

			wp_safe_redirect( $redirect );
			exit();

		} else {
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( false === $license_data->success ) {
				$message = $this->handle_license_err_code( $license_data );

				if ( ! empty( $message ) ) {
					$base_url = admin_url( 'themes.php?page=' . $this->theme_slug . '-license' );
					$redirect = add_query_arg(
						array(
							'pp_theme_activation' => 'false',
							'message'             => rawurlencode( $message ),
						),
						$base_url
					);

					wp_safe_redirect( $redirect );
					exit();
				}
			}
		}

		if ( $license_data && isset( $license_data->success ) && $license_data->success ) {
			update_option( $this->theme_slug . '_license_key_status', $license_data->license_status );
			delete_transient( $this->theme_slug . '_license_message' );
		}

		wp_safe_redirect( admin_url( 'themes.php?page=' . $this->theme_slug . '-license' ) );
		exit();

	}

	/**
	 * Get first err message from API response
	 *
	 * @param object $license_data API response
	 *
	 * @return mixed|string|void
	 */
	public function handle_license_err_code( $license_data ) {
		// first err code
		$error_keys = array_keys( (array) $license_data->errors );
		$err_code   = isset( $error_keys[0] ) ? $error_keys[0] : 'unkdown';

		switch ( $err_code ) {
			case 'missing_license_key':
				$message = esc_html__( 'License key does not exist', 'paddlepress' );
				break;

			case 'expired_license_key':
				$message = sprintf(
					__( 'Your license key expired on %s.' ),
					date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
				);
				break;
			case 'unregistered_license_domain':
				$message = esc_html__( 'Unregistered domain address', 'paddlepress' );
				break;
			case 'invalid_license_or_domain':
				$message = esc_html__( 'Invalid license or url', 'paddlepress' );
				break;
			case 'can_not_add_new_domain':
				$message = esc_html__( 'Can not add a new domain.', 'paddlepress' );
				break;

			default:
				$message = __( 'An error occurred, please try again.' );
				break;
		}

		/**
		 * modify api err message?
		 *
		 * @since 1.0.0
		 */
		$message = apply_filters( 'paddlepress_theme_updater_api_error_message', $message, $err_code );

		return $message;
	}


	/**
	 * Deactivates the license key.
	 *
	 * @since 1.0.0
	 */
	public function deactivate_license() {
		// Retrieve the license from the database.
		$license = trim( get_option( $this->theme_slug . '_license_key' ) );

		// Data to send in our API request.
		$api_params = array(
			'action'       => 'deactivate',
			'license_key'  => $license,
			'license_url'  => home_url(),
			'download_tag' => $this->download_tag,
		);

		$response = $this->get_api_response( $api_params );

		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
			} else {
				$message = __( 'An error occurred, please try again.' );
			}

			$base_url = admin_url( 'themes.php?page=' . $this->theme_slug . '-license' );
			$redirect = add_query_arg(
				array(
					'pp_theme_activation' => 'false',
					'message'             => rawurlencode( $message ),
				),
				$base_url
			);

			wp_safe_redirect( $redirect );
			exit();

		} else {

			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( $license_data && isset( $license_data->success ) && $license_data->success ) {
				delete_option( $this->theme_slug . '_license_key_status' );
				delete_transient( $this->theme_slug . '_license_message' );
			}
		}

		if ( ! empty( $message ) ) {
			$base_url = admin_url( 'themes.php?page=' . $this->theme_slug . '-license' );
			$redirect = add_query_arg(
				array(
					'pp_theme_activation' => 'false',
					'message'             => rawurlencode( $message ),
				),
				$base_url
			);

			wp_safe_redirect( $redirect );
			exit();
		}

		wp_safe_redirect( admin_url( 'themes.php?page=' . $this->theme_slug . '-license' ) );
		exit();
	}


	/**
	 * Checks if a license action was submitted.
	 *
	 * @since 1.0.0
	 */
	public function license_action() {

		if ( isset( $_POST[ $this->theme_slug . '_license_activate' ] ) ) {
			if ( check_admin_referer( $this->theme_slug . '_nonce', $this->theme_slug . '_nonce' ) ) {
				$this->activate_license();
			}
		}

		if ( isset( $_POST[ $this->theme_slug . '_license_deactivate' ] ) ) {
			if ( check_admin_referer( $this->theme_slug . '_nonce', $this->theme_slug . '_nonce' ) ) {
				$this->deactivate_license();
			}
		}

	}

	/**
	 * Checks if license is valid and gets expire date.
	 *
	 * @return string $message License status message.
	 * @since 1.0.0
	 */
	public function check_license() {
		$license = trim( get_option( $this->theme_slug . '_license_key' ) );
		$strings = $this->strings;

		$api_params = array(
			'action'       => 'info',
			'license_key'  => $license,
			'license_url'  => home_url(),
			'download_tag' => $this->download_tag,
		);

		$response = $this->get_api_response( $api_params );

		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
			} else {
				$message = $strings['license-status-unknown'];
			}

			$base_url = admin_url( 'themes.php?page=' . $this->theme_slug . '-license' );
			$redirect = add_query_arg(
				array(
					'pp_theme_activation' => 'false',
					'message'             => rawurlencode( $message ),
				),
				$base_url
			);

			wp_safe_redirect( $redirect );
			exit();

		} else {

			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			// If response doesn't include license data, return
			if ( ! isset( $license_data->license_status ) ) {
				$message = $strings['license-status-unknown'];

				return $message;
			}

			// We need to update the license status at the same time the message is updated
			if ( $license_data && isset( $license_data->license_status ) && $license_data->license_status ) {
				update_option( $this->theme_slug . '_license_key_status', $license_data->license_status );
			}

			// Get expire date
			$expires = false;
			if ( isset( $license_data->expires ) && 'lifetime' !== $license_data->expires ) {
				$expires = date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) );
			} elseif ( isset( $license_data->expires ) && 'lifetime' === $license_data->expires ) {
				$expires = 'lifetime';
			}

			// Get site counts
			$site_count    = $license_data->site_count;
			$license_limit = absint( $license_data->license_limit );

			// If unlimited
			if ( 0 === $license_limit ) {
				$license_limit = $strings['unlimited'];
			}

			if ( 'valid' === $license_data->license_status ) {
				$message = $strings['license-key-is-active'] . ' ';
				if ( isset( $expires ) && 'lifetime' !== $expires ) {
					$message .= sprintf( $strings['expires%s'], $expires ) . ' ';
				}
				if ( isset( $expires ) && 'lifetime' === $expires ) {
					$message .= $strings['expires-never'];
				}
				if ( $site_count && $license_limit ) {
					$message .= sprintf( $strings['%1$s/%2$-sites'], $site_count, $license_limit );
				}
			} elseif ( 'invalid' === $license_data->license_status ) {
				$message = $this->handle_license_err_code( $license_data );
			} else {
				$message = $strings['license-status-unknown'];
			}
		}

		return $message;
	}

	/**
	 * Disable requests to wp.org repository for this theme.
	 *
	 * @param array  $r   response
	 * @param string $url update check API
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public function disable_wporg_request( $r, $url ) {

		// If it's not a theme update request, bail.
		if ( 0 !== strpos( $url, 'https://api.wordpress.org/themes/update-check/1.1/' ) ) {
			return $r;
		}

		// Decode the JSON response
		$themes = json_decode( $r['body']['themes'] );

		// Remove the active parent and child themes from the check
		$parent = get_option( 'template' );
		$child  = get_option( 'stylesheet' );
		unset( $themes->themes->$parent );
		unset( $themes->themes->$child );

		// Encode the updated JSON response
		$r['body']['themes'] = wp_json_encode( $themes );

		return $r;
	}

	/**
	 * This is a means of catching errors from the activation method above and displaying it to the customer
	 */
	public function theme_admin_notices() {
		if ( isset( $_GET['pp_theme_activation'] ) && ! empty( $_GET['message'] ) ) {
			switch ( $_GET['pp_theme_activation'] ) {
				case 'false':
					$message = urldecode( $_GET['message'] );
					printf( '<div class="error"><p>%s<p></div>', esc_html( $message ) );
					break;
				case 'true':
				default:
					break;
			}
		}
	}

}


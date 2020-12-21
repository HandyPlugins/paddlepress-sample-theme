<?php
/**
 * Theme updater class.
 * Adopted from EDD's sample theme
 *
 * @package PaddlePress
 * @version 1.0
 */

namespace PaddlePress\Sample;

/**
 * Class Theme_Updater
 *
 * @package PaddlePress
 */
class Theme_Updater {
	/**
	 * Update server endpoint
	 *
	 * @var string
	 */
	private $update_api_url;

	/**
	 * License key
	 *
	 * @var string
	 */
	private $license_key;

	/**
	 * Licensed domain
	 *
	 * @var string
	 */
	private $license_url;

	/**
	 * Download tag
	 *
	 * @var string
	 */
	private $download_tag;
	/**
	 * request param
	 *
	 * @var array
	 */
	private $request_data;
	/**
	 * response key
	 *
	 * @var string
	 */
	private $response_key;

	/**
	 * The slug of theme
	 *
	 * @var string
	 */
	private $theme_slug;

	/**
	 * Current version
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Author of the theme
	 *
	 * @var string
	 */
	private $author;

	/**
	 * feedback messages
	 *
	 * @var array|null
	 */
	protected $strings = null;


	/**
	 * Initiate the Theme updater
	 *
	 * @param array $args    Array of arguments from the theme requesting an update check
	 * @param array $strings Strings for the update process
	 */
	public function __construct( $args = array(), $strings = array() ) {

		$defaults = array(
			'update_api_url' => 'http://example.org/wp-json/paddlepress-api/v1/update',
			'request_data'   => array(),
			'theme_slug'     => get_template(), // use get_stylesheet() for child theme updates
			'download_tag'   => '',
			'license_key'    => '',
			'license_url'    => '',
			'version'        => '',
			'author'         => '',
			'beta'           => false,
		);

		$args = wp_parse_args( $args, $defaults );

		$this->license_key    = $args['license_key'];
		$this->license_url    = $args['license_url'];
		$this->update_api_url = $args['update_api_url'];
		$this->version        = $args['version'];
		$this->theme_slug     = sanitize_key( $args['theme_slug'] );
		$this->author         = $args['author'];
		$this->beta           = $args['beta'];
		$this->download_tag   = $args['download_tag'];
		$this->response_key   = $this->theme_slug . '-' . $this->beta . '-update-response';
		$this->strings        = $strings;

		add_filter( 'site_transient_update_themes', array( $this, 'theme_update_transient' ) );
		add_filter( 'delete_site_transient_update_themes', array( $this, 'delete_theme_update_transient' ) );
		add_action( 'load-update-core.php', array( $this, 'delete_theme_update_transient' ) );
		add_action( 'load-themes.php', array( $this, 'delete_theme_update_transient' ) );
		add_action( 'load-themes.php', array( $this, 'load_themes_screen' ) );
	}

	/**
	 * Show the update notification when neecessary
	 *
	 * @return void
	 */
	public function load_themes_screen() {
		add_thickbox();
		add_action( 'admin_notices', array( $this, 'update_nag' ) );
	}

	/**
	 * Display the update notifications
	 *
	 * @return void
	 */
	public function update_nag() {
		$strings      = $this->strings;
		$theme        = wp_get_theme( $this->theme_slug );
		$api_response = get_transient( $this->response_key );

		if ( false === $api_response ) {
			return;
		}

		$update_url     = wp_nonce_url( 'update.php?action=upgrade-theme&amp;theme=' . rawurldecode( $this->theme_slug ), 'upgrade-theme_' . $this->theme_slug );
		$update_onclick = ' onclick="if ( confirm(\'' . esc_js( $strings['update-notice'] ) . '\') ) {return true;}return false;"';

		if ( version_compare( $this->version, $api_response->new_version, '<' ) ) {

			echo '<div id="update-nag">';
			printf(
				$strings['update-available'], // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html( $theme->get( 'Name' ) ),
				esc_html( $api_response->new_version ),
				'#TB_inline?width=640&amp;inlineId=' . $this->theme_slug . '_changelog', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$theme->get( 'Name' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$update_url, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$update_onclick // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
			echo '</div>';
			echo '<div id="' . esc_attr( $this->theme_slug ) . '_changelog" style="display:none;">';
			if ( isset( $api_response->sections->changelog ) ) {
				echo wpautop( $api_response->sections->changelog ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				esc_html_e( 'No changelog has been found.', 'paddlepress' );
			}
			echo '</div>';
		}
	}

	/**
	 * Update the theme update transient with the response from the version check
	 *
	 * @param array $value The default update values.
	 *
	 * @return array|boolean  If an update is available, returns the update parameters, if no update is needed returns false, if
	 *                        the request fails returns false.
	 */
	public function theme_update_transient( $value ) {
		$update_data = $this->check_for_update();

		if ( $update_data ) {

			// Make sure the theme property is set. See issue 1463 on Github in the Software Licensing Repo.
			$update_data['theme'] = $this->theme_slug;

			$value->response[ $this->theme_slug ] = $update_data;
		}

		return $value;
	}

	/**
	 * Remove the update data for the theme
	 *
	 * @return void
	 */
	public function delete_theme_update_transient() {
		delete_transient( $this->response_key );
	}

	/**
	 * Call the PaddlePress update API (using the URL in the construct) to get the latest version information
	 *
	 * @return array|boolean  If an update is available, returns the update parameters, if no update is needed returns false, if
	 *                        the request fails returns false.
	 */
	public function check_for_update() {
		$update_data = get_transient( $this->response_key );

		if ( false === $update_data ) {
			$failed = false;

			$api_params = array(
				'action'       => 'get_version',
				'license_key'  => $this->license_key,
				'license_url'  => $this->license_url,
				'download_tag' => $this->download_tag,
				'slug'         => $this->theme_slug,
				'version'      => $this->version,
				'author'       => $this->author,
				'beta'         => $this->beta,
			);

			$response = wp_remote_post(
				$this->update_api_url,
				array(
					'timeout' => 15,
					'body'    => $api_params,
				)
			);

			// Make sure the response was successful
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				$failed = true;
			}

			$update_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( ! is_object( $update_data ) ) {
				$failed = true;
			}

			// If the response failed, try again in 30 minutes
			if ( $failed ) {
				$data              = new \stdClass();
				$data->new_version = $this->version;
				set_transient( $this->response_key, $data, strtotime( '+30 minutes', time() ) );

				return false;
			}

			// If the status is 'ok', return the update arguments
			if ( ! $failed ) {
				$update_data->sections = maybe_unserialize( $update_data->sections );
				set_transient( $this->response_key, $update_data, strtotime( '+12 hours', time() ) );
			}
		}

		if ( version_compare( $this->version, $update_data->new_version, '>=' ) ) {
			return false;
		}

		return (array) $update_data;
	}

}

<?php
/**
 * PaddlePress Theme Updater
 * Adopted from EDD Sample Theme updater
 *
 * @package PaddlePress
 */

namespace PaddlePress\Sample;

// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
// phpcs:disable WordPress.WP.I18n.MixedOrderedPlaceholdersText

$config = array(
	'license_api_url' => 'http://example.org/wp-json/paddlepress-api/v1/license', // License endpoint
	'update_api_url'  => 'http://example.org/wp-json/paddlepress-api/v1/update', // Update endpoint
	'theme_slug'      => 'paddlepress-sample-theme', // Theme slug
	'download_tag'    => 'paddlepress-sample', // download tag
	'version'         => '1.0.0', // The current version of this theme
	'author'          => 'HandyPlugins', // The author of this theme
	'beta'            => false, // Optional, set to true to opt into beta versions
);
// Strings
$strings = array(
	'theme-license'             => __( 'Theme License', 'paddlepress' ),
	'enter-key'                 => __( 'Enter your theme license key.', 'paddlepress' ),
	'license-key'               => __( 'License Key', 'paddlepress' ),
	'license-action'            => __( 'License Action', 'paddlepress' ),
	'deactivate-license'        => __( 'Deactivate License', 'paddlepress' ),
	'activate-license'          => __( 'Activate License', 'paddlepress' ),
	'status-unknown'            => __( 'License status is unknown.', 'paddlepress' ),
	'renew'                     => __( 'Renew?', 'paddlepress' ),
	'unlimited'                 => __( 'unlimited', 'paddlepress' ),
	'license-key-is-active'     => __( 'License key is active.', 'paddlepress' ),
	'expires%s'                 => __( 'Expires %s.', 'paddlepress' ),
	'expires-never'             => __( 'Lifetime License.', 'paddlepress' ),
	'%1$s/%2$-sites'            => __( 'You have %1$s / %2$s sites activated.', 'paddlepress' ),
	'license-key-expired-%s'    => __( 'License key expired %s.', 'paddlepress' ),
	'license-key-expired'       => __( 'License key has expired.', 'paddlepress' ),
	'license-keys-do-not-match' => __( 'License keys do not match.', 'paddlepress' ),
	'license-is-inactive'       => __( 'License is inactive.', 'paddlepress' ),
	'license-key-is-disabled'   => __( 'License key is disabled.', 'paddlepress' ),
	'site-is-inactive'          => __( 'Site is inactive.', 'paddlepress' ),
	'license-status-unknown'    => __( 'License status is unknown.', 'paddlepress' ),
	'update-notice'             => __( "Updating this theme will lose any customizations you have made. 'Cancel' to stop, 'OK' to update.", 'paddlepress' ),
	'update-available'          => __( '<strong>%1$s %2$s</strong> is available. <a href="%3$s" class="thickbox" title="%4s">Check out what\'s new</a> or <a href="%5$s"%6$s>update now</a>.', 'paddlepress' ),
);

// Includes the files needed for the theme updater
if ( ! class_exists( 'PaddlePress\Sample\Theme_Updater_Admin' ) ) {
	include dirname( __FILE__ ) . '/theme-updater-admin.php';
}

// Loads the updater classes
$updater = new \PaddlePress\Sample\Theme_Updater_Admin( $config, $strings );

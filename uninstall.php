<?php
/**
 * Uninstall script
 *
 * @package corvuspay-woocommerce-integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'woocommerce_corvuspay_settings' );
delete_option( 'corvuspay_settings_version' );

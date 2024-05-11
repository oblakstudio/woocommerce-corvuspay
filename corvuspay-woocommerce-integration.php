<?php
/**
 * Plugin Name:          CorvusPay - WooCommerce Integration
 * Description:          Extends WooCommerce with CorvusPay Credit Card payments.
 * Version:              2.5.7
 * Plugin URI:           https://www.corvuspay.com/
 * Author:               Corvus Pay d.o.o.
 * Author URI:           https://www.corvuspay.com/
 * License:              GPLv3
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.html
 * Requires PHP:         8.0
 * Requires at least:    6.5
 * Tested up to:         6.5
 * WC requires at least: 8.5
 * WC tested up to:      8.8
 * Requires Plugins:     woocommerce
 * Text Domain:          corvuspay-woocommerce-integration
 * Domain Path:          /languages
 *
 * @package CorvusPay Payment Gateway
 */

defined( 'ABSPATH' ) || exit;

defined( 'WC_CORVUSPAY_SETTINGS_VERSION' ) || define( 'WC_CORVUSPAY_SETTINGS_VERSION', 4 );
defined( 'WC_CORVUSPAY_FILE' ) || define( 'WC_CORVUSPAY_FILE', __FILE__ );
defined( 'WC_CORVUSPAY_PATH' ) || define( 'WC_CORVUSPAY_PATH', __DIR__ );

add_action( 'plugins_loaded', 'woocommerce_corvuspay_init', 0 );

/**
 * Init CorvusPay WooCommerce gateway plugin.
 */
function woocommerce_corvuspay_init() {
    $domain = 'corvuspay-woocommerce-integration';
    $locale = apply_filters( 'plugin_locale', determine_locale(), $domain );
    $mofile = WP_PLUGIN_DIR . '/' . dirname(
        plugin_basename( __FILE__ ),
    ) . '/languages/' . $domain . '-' . $locale . '.mo';
    unload_textdomain( $domain );
    load_textdomain( $domain, $mofile );
    load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	require_once WC_CORVUSPAY_PATH . '/includes/class-wc-gateway-corvuspay.php';

	/**
	 * Add Settings link to plugin page.
	 *
	 * @param array $links List of existing plugin action links.
	 *
	 * @return array List of modified plugin action links.
	 */
	function corvuspay_action_links( $links ) {
		$settings = array(
			'<a href="' . esc_url(
                admin_url( 'admin.php?page=wc-settings&tab=checkout&section=corvuspay' ),
            ) . '">' . esc_html__( 'Settings', 'corvuspay-woocommerce-integration' ) . '</a>',
		);

		return array_merge( $settings, $links );
	}

	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'corvuspay_action_links' );

	/**
	 * Add CorvusPay Gateway.
	 *
	 * @param array $methods Methods.
	 *
	 * @return array
	 */
	function woocommerce_add_corvuspay_gateway( $methods ) {
		$methods[] = 'WC_Gateway_CorvusPay';

		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_corvuspay_gateway' );
	WC_Gateway_CorvusPay::get_instance()->init_hooks();
}

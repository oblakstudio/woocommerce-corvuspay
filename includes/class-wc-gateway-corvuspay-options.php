<?php
/**
 * Class WC_Gateway_CorvusPay_Options
 *
 * @package corvuspay-woocommerce-integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CorvusPay Gateway Options Class.
 */
class WC_Gateway_CorvusPay_Options {

	/**
	 * Check whether WooCommerce Subscriptions plugin v2.0 is installed and active.
	 *
	 * @var bool Check whether WooCommerce Subscriptions plugin v2.0 is installed and active.
	 */
	public $subscriptions;

	/**
	 * Gateway ID.
	 *
	 * @var string Gateway ID.
	 */
	public $id;

	/**
	 * Is currency based routing enabled.
	 *
	 * @var string Is currency based routing enabled.
	 */
	public $currency_routing;

	/**
	 * Payment environment. One of 'test'|'prod'.
	 *
	 * @var string Payment environment. One of 'test'|'prod'.
	 */
	public $environment;

	/**
	 * Is current environment test environment.
	 *
	 * @var bool Is current environment test environment.
	 */
	public $test;

	/**
	 * Is current environment production environment.
	 *
	 * @var bool Is current environment production environment.
	 */
	public $production;

	/**
	 * Array of Store IDs.
	 *
	 * @var array Array of Store IDs. In form of array('test' => ..., 'prod' => ...).
	 */
	public $store_id = array();

	/**
	 * Array of Secret Keys.
	 *
	 * @var array Array of Secret Keys. In form of array('test' => ..., 'prod' => ...).
	 */
	public $secret_key = array();

	/**
	 * Array of Stores Settings.
	 *
	 * @var array Array of Stores Settings. In form of array('test' => ..., 'prod' => ...).
	 */
	public $stores_settings = array();

	/**
	 * Array of Certificates.
	 *
	 * @var array Array of Certificates. In form of array('test' => ..., 'prod' => ...).
	 */
	public $certificate = array();

	/**
	 * Array of Order number formats.
	 *
	 * @var array Array of Order number formats. In form of array('test' => ..., 'prod' => ...).
	 */
	public $order_number_format = array();

	/**
	 * Type of transaction.
	 *
	 * @var bool Type of transaction. Is preauth.
	 */
	public $preauth;

	/**
	 * Should user be automatically redirected to payment form.
	 *
	 * @var bool Should user be automatically redirected to payment form.
	 */
	public $auto_redirect;

	/**
	 * Payment form language.
	 *
	 * @var string Payment form language. Two letter language code (similar to ISO 639-1).
	 */
	public $language;

	/**
	 * Which cardholder information should be sent to payment form.
	 *
	 * @var string Which cardholder information should be sent to payment form. One of 'none'|'mandatory'|'all'.
	 */
	public $cardholder_fields;

	/**
	 * Payment form time limit in seconds or null.
	 *
	 * @var int|null Payment form time limit in seconds or null.
	 */
	public $time_limit;

	/**
	 * Type of installments configuration.
	 *
	 * @var string Type of installments configuration. One of 'none'|'all'|'map'.
	 */
	public $installments;

	/**
	 * Installments map or null.
	 *
	 * @var array|null Installments map or null. In form of array(('card_brand' => ..., 'min_installments' => ..., 'max_installments' => ..., 'general_percentage' => ..., 'specific_percentage' => ...)).
	 */
	public $installments_map;

	/**
	 * Creditor reference or null.
	 *
	 * @var string|null Payee model and reference number for PIS payments or null.
	 */
	public $creditor_reference;

	/**
	 * Array of tabs to hide during checkout.
	 *
	 * @var array Array of tabs to hide during checkout.
	 */
	public $hide_tabs;

	/**
	 * WC_Gateway_CorvusPay_Options constructor.
	 *
	 * @param WC_Gateway_CorvusPay $gateway Gatweay.
	 */
	public function __construct( $gateway ) {
		$this->subscriptions = class_exists( 'WC_Subscriptions_Order' );
		$this->id            = $gateway->id;

		$this->currency_routing            = 'yes' === $gateway->get_option( 'currency_routing' );
		$this->environment                 = $gateway->get_option( 'environment' );
		$this->test                        = 'test' === $this->environment;
		$this->production                  = 'prod' === $this->environment;
		$this->store_id['test']            = (int) $gateway->get_option( 'test_store_id' );
		$this->secret_key['test']          = $gateway->get_option( 'test_secret_key' );
		$this->stores_settings['test']     = json_decode( $gateway->get_option( 'test_stores_settings' ), true );
		$this->certificate['test']         = $gateway->get_option( 'test_certificate' );
        $this->certificate['test_crt']     = $gateway->get_option( 'test_certificate_crt_pem' );
        $this->certificate['test_key']     = $gateway->get_option( 'test_certificate_key_pem' );
		$this->order_number_format['test'] = $gateway->get_option( 'test_order_number_format' );
		$this->store_id['prod']            = (int) $gateway->get_option( 'prod_store_id' );
		$this->secret_key['prod']          = $gateway->get_option( 'prod_secret_key' );
		$this->stores_settings['prod']     = json_decode( $gateway->get_option( 'prod_stores_settings' ), true );
		$this->certificate['prod']         = $gateway->get_option( 'prod_certificate' );
		$this->certificate['prod_crt']     = $gateway->get_option( 'prod_certificate_crt_pem' );
		$this->certificate['prod_key']     = $gateway->get_option( 'prod_certificate_key_pem' );
		$this->order_number_format['prod'] = $gateway->get_option( 'prod_order_number_format' );
		$this->preauth                     = 'auth' === $gateway->get_option( 'payment_action' );
		$this->auto_redirect               = 'yes' === $gateway->get_option( 'form_auto_redirect' );
		$this->language                    = $gateway->get_option( 'payment_form_language' );
		$this->cardholder_fields           = $gateway->get_option( 'cardholder_fields' );
		$this->time_limit                  = 'yes' === $gateway->get_option( 'form_time_limit_enabled' ) ? (int) $gateway->get_option( 'form_time_limit_seconds' ) : null;
		$this->installments                = $gateway->get_option( 'form_installments' );
		$this->installments_map            = json_decode( $gateway->get_option( 'form_installments_map' ), true );
		$this->creditor_reference          = 'yes' === $gateway->get_option( 'form_pis_enabled' ) ? $gateway->get_option( 'form_pis_creditor_reference' ) : null;
		$this->hide_tabs                   = is_array( $gateway->get_option( 'hide_tabs' ) ) ? $gateway->get_option( 'hide_tabs' ) : null;
	}
}

<?php
/**
 * CorvusPay WooCommerce Payment Gateway Settings
 *
 * @package corvuspay-woocommerce-integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once WP_PLUGIN_DIR . '/corvuspay-woocommerce-integration/vendor/autoload.php';

//Get supported languages.
$supported_languages = ['auto' => __( 'Autodetect', 'corvuspay-woocommerce-integration' )];
foreach ( \CorvusPay\Service\CheckoutService::SUPPORTED_LANGUAGES as $code => $name ) {
    $supported_languages[$code] = __( $name, 'corvuspay-woocommerce-integration' );
}

//Get tabs to hide.
$hide_tabs = [];
foreach ( \CorvusPay\Service\CheckoutService::TABS as $code => $name ) {
    $hide_tabs[$code] = __( $name, 'corvuspay-woocommerce-integration' );
}

return array(
	'enabled'                     => array(
		'title'   => __( 'Enable/Disable', 'corvuspay-woocommerce-integration' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable CorvusPay Gateway', 'corvuspay-woocommerce-integration' ),
		'default' => 'no',
	),
	'title'                       => array(
		'title'       => __( 'Title', 'corvuspay-woocommerce-integration' ),
		'type'        => 'text',
		'description' => __( 'This controls the title which the user sees during checkout.', 'corvuspay-woocommerce-integration' ),
		'default'     => __( 'Credit Card (CorvusPay)', 'corvuspay-woocommerce-integration' ),
		'desc_tip'    => true,
	),
	'description'                 => array(
		'title'       => __( 'Description', 'corvuspay-woocommerce-integration' ),
		'type'        => 'textarea',
		'description' => __( 'This controls the description which the user sees during checkout. You can add the following symbols: :amex: :dina: :diners: :discover: :jcb: :maestro: :master: :visa: :card: :iban: :paysafecard: :wallet:', 'corvuspay-woocommerce-integration' ),
		'default'     => __( 'Online transaction processing.', 'corvuspay-woocommerce-integration' ) . ' 
:amex: :dina: :diners: :discover: :maestro: :master: :visa: :iban: :paysafecard:',
		'desc_tip'    => true,
	),
	'tokenization'                => array(
		'title'   => __( 'Enable/Disable Tokenization', 'corvuspay-woocommerce-integration' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable Tokenization (required for Subscriptions)', 'corvuspay-woocommerce-integration' ),
		'default' => 'no',
	),
	'currency_routing'            => array(
		'title'   => __( 'Enable/Disable Currency based order routing', 'corvuspay-woocommerce-integration' ),
		'type'    => 'checkbox',
		'label'   => __( 'Route orders by currency', 'corvuspay-woocommerce-integration' ),
		'default' => 'no',
	),
	'account_settings'            => array(
		'title'       => __( 'Account Settings', 'corvuspay-woocommerce-integration' ),
		'type'        => 'title',
		'description' => '',
	),
	'environment'                 => array(
		'title'       => __( 'Environment', 'corvuspay-woocommerce-integration' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select',
		'description' => __( 'This setting specifies whether you will process live transactions, or whether you will process simulated transactions.', 'corvuspay-woocommerce-integration' ),
		'options'     => array(
			'prod' => __( 'Production', 'corvuspay-woocommerce-integration' ),
			'test' => __( 'Test', 'corvuspay-woocommerce-integration' ),
		),
		'default'     => 'test',
		'desc_tip'    => true,
	),
	'test_store_id'               => array(
		'title'       => __( 'Test Store ID', 'corvuspay-woocommerce-integration' ),
		'type'        => 'text',
		'description' => __( 'Copy Test Store ID from the CorvusPay Merchant Center.', 'corvuspay-woocommerce-integration' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'test_secret_key'             => array(
		'title'       => __( 'Test Secret Key', 'corvuspay-woocommerce-integration' ),
		'type'        => 'password',
		'description' => __( 'Copy Test Secret Key from the CorvusPay Merchant Center.', 'corvuspay-woocommerce-integration' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'test_stores_settings'        => array(
		'type' => 'test_stores_settings',
	),
	'test_certificate'            => array(
		'title'       => __( 'Test API Certificate', 'corvuspay-woocommerce-integration' ),
		'type'        => 'file',
		'description' => $this->certificate_info( 'test' ),
		'default'     => '',
	),
	'test_certificate_crt_pem' => array(
		'title'   => __( 'Test API Certificate Pem', 'corvuspay-woocommerce-integration' ),
		'type'    => 'file',
		'default' => '',
		'class'   => 'hidden-setting',
	),
	'test_certificate_key_pem' => array(
		'title'   => __( 'Test API Certificate Key Pem', 'corvuspay-woocommerce-integration' ),
		'type'    => 'file',
		'default' => '',
		'class'   => 'hidden-setting',
	),
	'test_certificate_password'   => array(
		'title'       => __( 'Test API Certificate Password', 'corvuspay-woocommerce-integration' ),
		'type'        => 'password',
		'description' => __( 'Password is used only once, to decrypt the certificate. It will not be stored.', 'corvuspay-woocommerce-integration' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'test_order_number_format'    => array(
		'title'       => __( 'Test Order Number Format', 'corvuspay-woocommerce-integration' ),
		'type'        => 'text',
		'description' => __( 'Order number format. You can add the following keywords: {site_title}, {order_number} and {post_id}.', 'corvuspay-woocommerce-integration' ),
		'default'     => '{site_title} - #{order_number}',
		'desc_tip'    => true,
	),
	'prod_store_id'               => array(
		'title'       => __( 'Production Store ID', 'corvuspay-woocommerce-integration' ),
		'type'        => 'text',
		'description' => __( 'Copy Production Store ID from the CorvusPay Merchant Center.', 'corvuspay-woocommerce-integration' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'prod_secret_key'             => array(
		'title'       => __( 'Production Secret Key', 'corvuspay-woocommerce-integration' ),
		'type'        => 'password',
		'description' => __( 'Copy Production Secret Key from the CorvusPay Merchant Center.', 'corvuspay-woocommerce-integration' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'prod_stores_settings'        => array(
		'type' => 'prod_stores_settings',
	),
	'prod_certificate'            => array(
		'title'       => __( 'Production API Certificate', 'corvuspay-woocommerce-integration' ),
		'type'        => 'file',
		'description' => $this->certificate_info( 'prod' ),
		'default'     => '',
	),
    'prod_certificate_crt_pem'            => array(
        'title'       => __( 'Production API Certificate Pem', 'corvuspay-woocommerce-integration' ),
        'type'        => 'file',
        'default'     => '',
        'class'       => 'hidden-setting',
    ),
    'prod_certificate_key_pem'            => array(
        'title'       => __( 'Production API Certificate Key Pem', 'corvuspay-woocommerce-integration' ),
        'type'        => 'file',
        'default'     => '',
        'class'        => 'hidden-setting',
    ),
	'prod_certificate_password'   => array(
		'title'       => __( 'Production API Certificate Password', 'corvuspay-woocommerce-integration' ),
		'type'        => 'password',
		'description' => __( 'Password is used only once, to decrypt the certificate. It will not be stored.', 'corvuspay-woocommerce-integration' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'prod_order_number_format'    => array(
		'title'       => __( 'Production Order Number Format', 'corvuspay-woocommerce-integration' ),
		'type'        => 'text',
		'description' => __( 'Order number format. You can add the following keywords: {site_title}, {order_number} and {post_id}.', 'corvuspay-woocommerce-integration' ),
		'default'     => '#{order_number}',
		'desc_tip'    => true,
	),
	'payment_action'              => array(
		'title'       => __( 'Payment Action', 'corvuspay-woocommerce-integration' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select',
		'description' => __( 'Choose whether you wish to capture funds immediately or authorize payment only.', 'corvuspay-woocommerce-integration' ),
		'options'     => array(
			'sale' => __( 'Sale', 'corvuspay-woocommerce-integration' ),
			'auth' => __( 'Authorize', 'corvuspay-woocommerce-integration' ),
		),
		'default'     => 'sale',
		'desc_tip'    => true,
	),
	'success_url'                 => array(
		'title'       => __( 'Success URL', 'corvuspay-woocommerce-integration' ),
		'type'        => 'title',
		'description' => __( 'Copy Success URL to the CorvusPay Merchant Center', 'corvuspay-woocommerce-integration' ) . ': <strong>' . WC_Gateway_CorvusPay::get_url( 'success' ) . '</strong>',
	),
	'cancel_url'                  => array(
		'title'       => __( 'Cancel URL', 'corvuspay-woocommerce-integration' ),
		'type'        => 'title',
		'description' => __( 'Copy Cancel URL to the CorvusPay Merchant Center', 'corvuspay-woocommerce-integration' ) . ': <strong>' . WC_Gateway_CorvusPay::get_url( 'cancel' ) . '</strong>',
	),
	'form_options'                => array(
		'title'       => __( 'Form Options', 'corvuspay-woocommerce-integration' ),
		'type'        => 'title',
		'description' => '',
	),
	'form_auto_redirect'          => array(
		'title'   => __( 'Enable/Disable Auto-redirect', 'corvuspay-woocommerce-integration' ),
		'type'    => 'checkbox',
		'label'   => __( 'Automatically redirect user to CorvusPay payment form.', 'corvuspay-woocommerce-integration' ),
		'default' => 'yes',
	),
	'payment_form_language'       => array(
		'title'       => __( 'Payment Form Language', 'corvuspay-woocommerce-integration' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select',
		'description' => __( 'This setting specifies the payment form language.', 'corvuspay-woocommerce-integration' ),
		'options'     => $supported_languages,
		'default'     => 'auto',
		'desc_tip'    => true,
	),
	'cardholder_fields'           => array(
		'title'       => __( 'Send Cardholder Information', 'corvuspay-woocommerce-integration' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select',
		'description' => __( 'Send customer information to CorvusPay to speed up payment process. Can include name, address and contact details.', 'corvuspay-woocommerce-integration' ),
		'options'     => array(
			'none'      => __( 'None', 'corvuspay-woocommerce-integration' ),
			'mandatory' => __( 'Mandatory', 'corvuspay-woocommerce-integration' ),
			'all'       => __( 'Both mandatory and optional', 'corvuspay-woocommerce-integration' ),
		),
		'default'     => 'all',
		'desc_tip'    => true,
	),
	'form_time_limit_enabled'     => array(
		'title'   => __( 'Enable/Disable Time Limit', 'corvuspay-woocommerce-integration' ),
		'type'    => 'checkbox',
		'label'   => __( 'Limit payment time. Make sure WordPress keeps accurate time.', 'corvuspay-woocommerce-integration' ),
		'default' => 'no',
	),
	'form_time_limit_seconds'     => array(
		'title'       => __( 'Time Limit in seconds', 'corvuspay-woocommerce-integration' ),
		'type'        => 'text',
		'description' => __( 'Payment time limit in seconds. Maximum value is "900" (15 minutes).', 'corvuspay-woocommerce-integration' ),
		'default'     => '900',
		'desc_tip'    => true,
	),
	'form_installments'           => array(
		'title'       => __( 'Installments', 'corvuspay-woocommerce-integration' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select',
		'description' => __( 'Allow installment payments.', 'corvuspay-woocommerce-integration' ),
		'options'     => array(
			'none' => __( 'Disabled', 'corvuspay-woocommerce-integration' ),
			'all'  => __( 'Simple', 'corvuspay-woocommerce-integration' ),
			'map'  => __( 'Advanced', 'corvuspay-woocommerce-integration' ),
		),
		'default'     => 'none',
		'desc_tip'    => true,
	),
	'form_installments_map'       => array(
		'type' => 'installments_map',
	),
	'form_pis_enabled'            => array(
		'title'   => __( 'Enable/Disable PIS Payments', 'corvuspay-woocommerce-integration' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable Pay by IBAN functionality.', 'corvuspay-woocommerce-integration' ),
		'default' => 'no',
	),
	'form_pis_creditor_reference' => array(
		'title'       => __( 'Creditor Reference', 'corvuspay-woocommerce-integration' ),
		'type'        => 'text',
		'placeholder' => 'HR00{post_id}',
		'description' => __( 'Payee model and reference number for PIS payments. Sequence "{post_id}" is replaced with WooCommerce Post ID. Sequence "{order_number}" is replaced with WooCommerce Order Number.', 'corvuspay-woocommerce-integration' ),
		'default'     => 'HR00{post_id}',
		'desc_tip'    => true,
	),
	'hide_tabs'                   => array(
		'title'       => __( 'Hide payment methods', 'corvuspay-woocommerce-integration' ),
		'type'        => 'multiselect',
		'class'       => 'wc-enhanced-select',
		'description' => __( 'Hide payment methods during checkout.', 'corvuspay-woocommerce-integration' ),
		'options'     => $hide_tabs,
		'default'     => 'none',
		'desc_tip'    => true,
	),
	'troubleshooting'             => array(
		'title'       => __( 'Troubleshooting', 'corvuspay-woocommerce-integration' ),
		'type'        => 'title',
		'description' => '',
	),
	'log_level'                   => array(
		'title'       => __( 'Log Level', 'corvuspay-woocommerce-integration' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select',
		'description' => __( 'This setting specifies the log level.', 'corvuspay-woocommerce-integration' ),
		'options'     => array(
			'emergency' => __( 'Emergency', 'corvuspay-woocommerce-integration' ),
			'alert'     => __( 'Alert', 'corvuspay-woocommerce-integration' ),
			'critical'  => __( 'Critical', 'corvuspay-woocommerce-integration' ),
			'error'     => __( 'Error', 'corvuspay-woocommerce-integration' ),
			'warning'   => __( 'Warning', 'corvuspay-woocommerce-integration' ),
			'notice'    => __( 'Notice', 'corvuspay-woocommerce-integration' ),
			'info'      => __( 'Informational', 'corvuspay-woocommerce-integration' ),
			'debug'     => __( 'Debug', 'corvuspay-woocommerce-integration' ),
		),
		'default'     => 'error',
		'desc_tip'    => true,
	),
);

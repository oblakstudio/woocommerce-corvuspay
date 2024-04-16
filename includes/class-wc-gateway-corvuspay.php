<?php
/**
 * WC_Gateway_CorvusPay
 *
 * @package corvuspay-woocommerce-integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once WP_PLUGIN_DIR . '/corvuspay-woocommerce-integration/vendor/autoload.php';
/**
 * CorvusPay Payment Gateway Class.
 */
class WC_Gateway_CorvusPay extends WC_Payment_Gateway_CC {

	/**
	 * Singleton instance
	 * @var WC_Gateway_CorvusPay
	 */
	private static $_instance;

	/**
	 * CorvusPay Checkout URLs for test and production.
	 */
	const CHECKOUT_URL = \CorvusPay\Service\CheckoutService::CHECKOUT_URL;

	/**
	 * CorvusPay Gateway Logger.
	 *
	 * @var WC_Logger_CorvusPay CorvusPay Gateway Logger.
	 */
	public $log;

	/**
	 * CorvusPay Gateway Options.
	 *
	 * @var WC_Gateway_CorvusPay_Options CorvusPay Gateway Options.
	 */
	public $options;

	/**
	 * Get instance.
	 *
	 * Returns a new instance of self, if it does not already exist.
	 *
	 * @access public
	 * @static
	 * @return WC_Gateway_CorvusPay
	 */
	public static function get_instance() {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
	/**
	 * WC_Gateway_CorvusPay constructor.
	 */
	public function __construct() {
		$this->id   = 'corvuspay';
		$this->icon = plugins_url( 'assets/img/CorvusPay.svg', WC_CORVUSPAY_FILE );

		$this->has_fields = true;

		$this->method_title       = __( 'CorvusPay', 'corvuspay-woocommerce-integration' );
		$this->method_description = __( 'For entering of card data users will be redirected to CorvusPay payment form.', 'corvuspay-woocommerce-integration' );

		if ( 'yes' === $this->get_option( 'tokenization' ) ) {
			$this->supports = array(
				'products',
				'refunds',
				'tokenization',
				'subscriptions',
				'multiple_subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
				'subscription_payment_method_change',
				'subscription_payment_method_change_admin',
				'subscription_payment_method_change_customer',
			);
		} else {
			$this->supports = array(
				'products',
				'refunds',
			);
		}

		require_once WC_CORVUSPAY_PATH . '/includes/class-wc-logger-corvuspay.php';

		$this->log = new WC_Logger_CorvusPay( $this->get_option( 'log_level', 'error' ), $this->id );

		require_once WC_CORVUSPAY_PATH . '/includes/class-wc-gateway-corvuspay-options.php';
		require_once WC_CORVUSPAY_PATH . '/includes/class-wc-order-corvuspay.php';
		require_once WC_CORVUSPAY_PATH . '/includes/class-corvuspay-api.php';
		require_once WC_CORVUSPAY_PATH . '/includes/class-wc-payment-token-cc-corvuspay.php';

		$settings_version = (int) get_option( 'corvuspay_settings_version', 0 );
		if ( $settings_version < WC_CORVUSPAY_SETTINGS_VERSION ) {
			$this->update_settings( $settings_version );
			update_option( 'corvuspay_settings_version', WC_CORVUSPAY_SETTINGS_VERSION );
		}

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );
		$this->options     = new WC_Gateway_CorvusPay_Options( $this );

	}

	/**
	 * Initialize module hooks.
	 */
	public function init_hooks() {
		add_action( "woocommerce_update_options_payment_gateways_{$this->id}", array(
			$this,
			'process_admin_options'
		) );
		add_action( "woocommerce_receipt_{$this->id}", array( $this, 'process_receipt' ) );
		add_action( 'woocommerce_api_corvuspay-success', array( $this, 'corvuspay_success_handler' ) );
		add_action( 'woocommerce_api_corvuspay-cancel', array( $this, 'corvuspay_cancel_handler' ) );
		add_filter( 'woocommerce_order_number', array( $this, 'change_woocommerce_order_number' ), 10, 2 );
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'recalculate_order_total_in_email'), 10, 3 );
		add_action( 'woocommerce_thankyou_corvuspay', array( $this, 'corvuspay_content_thankyou' ), 10, 1 );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'corvuspay_email_after_order_table' ), 10, 4 );
		add_action( 'woocommerce_order_status_processing', array(
			$this,
			'remove_order_note_when_status_change_to_processing'
		), 10, 2 );
		add_action( 'woocommerce_order_status_cancelled', array(
			$this,
			'remove_order_note_when_status_change_to_void'
		), 10, 2 );
		add_action( 'woocommerce_before_order_object_save', array( $this, 'corvuspay_handle_order_status_change' ), 10, 2 );
		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'add_capture_button' ) );
		add_action( 'wp_ajax_corvuspay_complete_order', array( $this, 'complete_order' ) );
		add_filter( 'woocommerce_order_fully_refunded_status', array( $this, 'corvuspay_cancel_voided_order' ), 10, 2 );

		if ( $this->options->subscriptions ) {
			add_action( "woocommerce_scheduled_subscription_payment_{$this->id}", array(
				$this,
				'process_scheduled_subscription_payment'
			), 10, 2 );
			add_action( 'woocommerce_subscription_validate_payment_meta', array(
				$this,
				'validate_subscription_payment_meta'
			), 10, 2 );
			add_filter( 'woocommerce_subscription_payment_meta', array(
				$this,
				'add_subscription_payment_meta'
			), 10, 2 );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'add_theme_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 10, 1 );
	}

	/**
	 * Updates plugin settings from older versions.
	 *
	 * @param int $version Old settings version to update from.
	 */
	public function update_settings( $version ) {
		if ( $version <= 0 ) { /* First installation */
			$old_settings = get_option( 'cpsi_gateway', null );
			if ( is_null( $old_settings ) ) {
				return;
			}
		}
		if ( $version <= 1 ) { /* Versions 1.0.0 - 1.1.1 */
			$old_settings = get_option( 'woocommerce_cpsi_settings', null );
			$new_settings = array(
				'enabled'               => $old_settings['enabled'],
				'payment_form_language' => $old_settings['payment_form_language'],
			);
			if ( 'yes' === $old_settings['cps_debug'] ) {
				$new_settings['environment']     = 'test';
				$new_settings['test_store_id']   = $old_settings['corvus_store_id'];
				$new_settings['test_secret_key'] = $old_settings['corvus_key'];
			} else {
				$new_settings['environment']     = 'prod';
				$new_settings['prod_store_id']   = $old_settings['corvus_store_id'];
				$new_settings['prod_secret_key'] = $old_settings['corvus_key'];
			}
			if ( 'yes' === $old_settings['enable_installments'] ) {
				$new_settings['form_installments'] = 'all';
			}
			if ( 'yes' === $old_settings['require_complete'] ) {
				$new_settings['payment_action'] = 'auth';
			}
			add_option( 'woocommerce_corvuspay_settings', $new_settings );
			WC_Admin_Notices::add_custom_notice(
				"{$this->id}_update_urls",
				esc_html__( 'CorvusPay WooCommerce Payment Gateway was updated successfully. CorvusPay Success URL and CorvusPay Cancel URL have been changed and have to be updated in CorvusPay Merchant Center. Go to the CorvusPay WooCommerce Payment Gateway plugin settings page to find out more.', 'corvuspay-woocommerce-integration' )
			);
			$this->log->debug( 'Updating settings from: ' . wp_json_encode( $old_settings ) . ' to ' . wp_json_encode( $new_settings ) );

			// Delete pages and settings from older versions.
			if ( ! empty( $old_settings['checkout_page_id'] ) ) {
				wp_delete_post( $old_settings['checkout_page_id'], true );
				$this->log->debug( 'Deleting old pages Checkout page.' );
			}
			if ( ! empty( $old_settings['cps_success_page_id'] ) ) {
				wp_delete_post( $old_settings['cps_success_page_id'], true );
				$this->log->debug( 'Deleting old pages Success page.' );
			}
			if ( ! empty( $old_settings['cps_cancel_page_id'] ) ) {
				wp_delete_post( $old_settings['cps_cancel_page_id'], true );
				$this->log->debug( 'Deleting old pages Cancel page.' );
			}
			if ( delete_option( 'cpsi_gateway' ) ) {
				$this->log->debug( 'Deleting old cpsi_gateway setting.' );
			}
			if ( delete_option( 'woocommerce_cpsi_settings' ) ) {
				$this->log->debug( 'Deleting old woocommerce_cpsi_settings setting.' );
			}
			$this->log->debug( 'Deleting old pages and settings.' );
		}
		if ( $version <= 2 ) { /* Versions 2.0.0 - 2.3.7 */
			$settings    = get_option( 'woocommerce_corvuspay_settings', null );
			$old_setting = $settings['form_pis_creditor_reference'];

			$settings['form_pis_creditor_reference'] =
				str_replace( '${orderId}', '#ORDER_ID', $settings['form_pis_creditor_reference'] );

			update_option( 'woocommerce_corvuspay_settings', $settings );
			$this->log->debug( 'Updating Creditor Reference from: ' . wp_json_encode( $old_setting ) . ' to ' . wp_json_encode( $settings['form_pis_creditor_reference'] ) );
		}
		if ( $version <= 3 ) { /* Versions 2.4.0 - x.x.x */
			$settings    = get_option( 'woocommerce_corvuspay_settings', null );
			$old_setting = $settings['form_pis_creditor_reference'];

			if ( strpos( $old_setting, '#ORDER_ID' ) !== false ) {
				$settings['form_pis_creditor_reference'] = str_replace( '#ORDER_ID', '{post_id}', $old_setting );
				$this->log->debug( 'Updating Creditor Reference from: ' . wp_json_encode( $old_setting ) .
				                   ' to ' . wp_json_encode( $settings['form_pis_creditor_reference'] ) );
			}
			$settings['test_order_number_format'] = "{site_title} - #{order_number}";
			$settings['prod_order_number_format'] = "#{order_number}";

			update_option( 'woocommerce_corvuspay_settings', $settings );
			$this->log->debug( 'Adding Test Order Number Format: ' . $settings['test_order_number_format'] );
			$this->log->debug( 'Adding Production Order Number Format: ' . $settings['prod_order_number_format'] );
		}
	}

	/**
	 * Initialize settings form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = require WC_CORVUSPAY_PATH . '/includes/settings.php';
	}

	/**
	 * Add CSS.
	 */
	public function add_theme_scripts() {
		wp_enqueue_style( 'corvuspay', plugins_url( 'assets/css/corvuspay.css', WC_CORVUSPAY_FILE ), array(), '1.0.0' );
	}

	/**
	 * Add JavaScript to settings page.
     *
     * @param string $hook
	 */
	public function admin_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' === get_current_screen()->id ) {
			wp_enqueue_script( 'woocommerce_corvuspay_admin', plugins_url( 'assets/js/corvuspay-admin.js', WC_CORVUSPAY_FILE ), array(), '1.2.0', true );
			wp_enqueue_style( 'woocommerce_corvuspay_admin', plugins_url( 'assets/css/corvuspay-admin.css', WC_CORVUSPAY_FILE ), array(), '1.0.0' );
		}

		if ( 'shop_order' === get_current_screen()->id && 'post.php' == $hook && isset($_GET['post']) && $_GET['action'] == 'edit' ) {
            $post_id = $_GET['post'];
            $order = wc_get_order($post_id);

            // Targeting only Orders which payment method is CorvusPay.
            if( $order->get_payment_method() != 'corvuspay')
                return;
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'corvuspay-capture', plugins_url( 'assets/js/corvuspay-capture.js', WC_CORVUSPAY_FILE ), array( 'jquery' ), time(), true );
			$corvuspay_vars = array(
				'ajax_url'            => admin_url( 'admin-ajax.php' ),
				'confirm_description' => __( 'Are you sure you wish to process this capture? This action cannot be undone.', 'corvuspay-woocommerce-integration' )
			);
			wp_localize_script( 'corvuspay-capture', 'corvuspay_vars', $corvuspay_vars );
		}
	}

	/**
	 * Handle payment and process the order.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array|null Redirect
	 */
	public function process_payment( $order_id ) {
		$this->log->info( 'Creating Order with post_id: ' . $order_id . '.' );
		$order = new WC_Order( $order_id );

		$this->log->debug( '$_POST: ' . wp_json_encode( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$this->log->debug( '$_GET: ' . wp_json_encode( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->log->debug( '$order->get_id(): ' . wp_json_encode( $order->get_id() ) );

		$token_id = 'none';
		if ( $this->options->subscriptions &&
			( wcs_order_contains_subscription( $order ) ||
			wcs_order_contains_resubscribe( $order ) ||
			wcs_order_contains_renewal( $order ) ||
			wcs_order_contains_early_renewal( $order ) ||
			wcs_order_contains_switch( $order ) ) ) {
			$token_id = 'new';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ "wc-{$this->id}-new-payment-method" ] ) && 'true' === $_POST[ "wc-{$this->id}-new-payment-method" ] ) {
			$token_id = 'new';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST[ "wc-{$this->id}-payment-token" ] ) && 'new' !== $_POST[ "wc-{$this->id}-payment-token" ] ) {
			$token_id = wc_clean( $_POST[ "wc-{$this->id}-payment-token" ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		if ( 'none' !== $token_id && 'new' !== $token_id ) {
			try {
				$order = new WC_Order_CorvusPay( $this->log, $this->options, 'api-renew', $order );
                $api   = new CorvusPay_API( $this->log, $this->options, $order->get_client() );
				$token = WC_Payment_Tokens::get( $token_id );
				$token = new WC_Payment_Token_CC_CorvusPay( $token );

				if ( ! is_null( $token ) ) {
					$order->update_meta_data( '_corvuspay_token_id', $token->get_id() );
					$order->save_meta_data();
				} else {
					$this->log->notice( "Payment with token #{$token_id} for Order #{$order_id} failed." );

					wc_add_notice( __( 'CorvusPay payment failed.', 'corvuspay-woocommerce-integration' ), 'error' );

					return null;
				}

				$result = $api->renew( $order, $order->get_total() );

				if ( true === $result ) {
					$order->payment_complete();
					$this->update_subscriptions_payment_token( $order, $token->get_id() );

					return array(
						'result'   => 'success',
						'redirect' => $order->get_checkout_order_received_url(),
					);
				} else {
					$order->update_status( 'failed', __( 'CorvusPay payment failed.', 'corvuspay-woocommerce-integration' ) );
					$this->log->notice( "Payment with token #{$token_id} for Order #{$order_id} failed. Result is: " . wp_json_encode( $result ) );

					wc_add_notice( __( 'CorvusPay payment failed.', 'corvuspay-woocommerce-integration' ), 'error' );

					return null;
				}
			} catch ( Exception $e ) {
				$this->log->error( $e->getMessage() . ' $order_id: ' . wp_json_encode( $order_id ) );
				wc_add_notice( __( 'CorvusPay payment failed.', 'corvuspay-woocommerce-integration' ), 'error' );

				return null;
			}
		} else {
			$order->update_meta_data( '_corvuspay_token', $token_id );
			$order->save_meta_data();
		}

		$this->log->debug( 'return success for Order: ' . wp_json_encode( $order->get_id() ) );
		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Process refund for the order.
	 *
	 * @param int    $order_id Order ID.
	 * @param null   $amount Amount to refund.
	 * @param string $reason Optional reason for refund.
	 *
	 * @return bool|WP_Error Returns true on success, false on failure or WP_Error on error.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		try {
			$order = new WC_Order_CorvusPay( $this->log, $this->options, 'api', $order_id );
			$this->log->info( "Refunding Order #{$order_id} with amount {$amount} {$order->get_currency()} for reason \"{$reason}\"" );

			$api    = new CorvusPay_API( $this->log, $this->options, $order->get_client() );
			$result = $api->refund( $order, $amount );

			$token = (int) $order->get_meta( '_corvuspay_token_id' );

			$this->log->debug( '$result: ' . wp_json_encode( $result ) . ' $token: ' . wp_json_encode( $token ) );

			return $result;
		} catch ( Exception $e ) {
			$this->log->error( $e->getMessage() . ' $order_id: ' . wp_json_encode( $order_id ) );

			return false;
		}
	}

	/**
	 * Add a payment method. Redirects to CorvusPay payment form.
	 *
	 * @return array Redirect to payment form or error.
	 */
	public function add_payment_method() {
		try {
			$order = wc_create_order();

			$customer = new WC_Customer( get_current_user_id() );

			$order->set_customer_id( $customer->get_id() );
			$order->set_address( $customer->get_billing(), 'billing' );
			$order->set_total( 1 );
			$order->set_payment_method( $this );
			$order->save();

			$order->update_meta_data( '_corvuspay_token', 'add' );
			$order->save_meta_data();

			return array(
				'redirect' => $order->get_checkout_payment_url( true ),
			);
		} catch ( Exception $e ) {
			$this->log->error( $e->getMessage() );

			return array(
				'result'   => 'failure',
				'messages' => __( 'Something went wrong.', 'corvuspay-woocommerce-integration' ),
			);
		}
	}

	/**
	 * Process scheduled payments for subscriptions. Completes order on success or changes order status to 'failed' on failure.
	 *
	 * @param float    $amount Amount to charge.
	 * @param WC_Order $order Order to process.
	 */
	public function process_scheduled_subscription_payment( $amount, $order ) {
		$this->log->info( 'Entered process_scheduled_subscription_payment. Processing subscription Order with post_id: ' . $order->get_id() . '.' );

		try {
			$order = new WC_Order_CorvusPay( $this->log, $this->options, 'api-renew', $order );

			$api = new CorvusPay_API( $this->log, $this->options, $order->get_client() );

			$result = $api->renew( $order, $amount );

			if ( true === $result ) {
				$order->payment_complete();
			} else {
				$order->update_status( 'failed', __( 'CorvusPay payment failed.', 'corvuspay-woocommerce-integration' ) );
			}
		} catch ( Exception $e ) {
			$this->log->error( $e->getMessage() . ' $order_id: ' . $order->get_id() );

			$order->update_status( 'failed', __( 'CorvusPay payment failed.', 'corvuspay-woocommerce-integration' ) );
		}
	}

	/**
	 * Generates receipt for order which contains payment form fields for CorvusPay and a Submit button.
	 *
	 * @param int $order_id Order ID.
	 */
	public function process_receipt( $order_id ) {
		try {
			$this->log->info( 'Entered process_receipt. Processing Order with post_id: ' . $order_id . '.' );
			$order = new WC_Order_CorvusPay( $this->log, $this->options, 'cardholder', $order_id );
			$button = 'auto';
			if ( ! $this->options->auto_redirect ) {
				$button = __( 'Continue to payment', 'corvuspay-woocommerce-integration' );
			}
			$this->log->debug( 'get_meta(): ' . wp_json_encode( $order->get_meta( '_corvuspay_token' ) ) );

			ob_start();
			$order->get_client()->checkout->create( $order->get_parameters(), $button );
			?>
			<?php

			ob_end_flush();

		} catch ( Exception $e ) {
			$this->log->error( $e->getMessage() . ' $order_id: ' . wp_json_encode( $order_id ) );
			wp_die( esc_html( $e->getMessage() ), esc_html__( 'CorvusPay Process Receipt Failure', 'corvuspay-woocommerce-integration' ) );
		}
	}

	/**
	 * Handle Success URL. Completes order on success or changes order status to 'on-hold' for authorizations. Stores payment tokens.
	 */
	public function corvuspay_success_handler() {
		try {
			$this->log->debug( 'Enter corvuspay_success_handler $_POST: ' . wp_json_encode( $_POST ) );

			if ( empty( $_POST ) ) {
				throw new Exception( esc_html__( 'Empty POST data.', 'corvuspay-woocommerce-integration' ) );
			}

			$order = new WC_Order_CorvusPay( $this->log, $this->options, 'callback-signed' );

			$this->log->info( 'Success URL for Order #' . $order->get_id() . '.' );

			if ( $this->options->installments === 'map' ) {
				$status_order = new WC_Order_CorvusPay( $this->log, $this->options, 'api-status', $order );
				$api          = new CorvusPay_API( $this->log, $this->options, $status_order->get_client() );

				$server_output = $api->status( $status_order );
				$this->log->debug( '$server_output for status:' . PHP_EOL . $server_output );

				if ( false === $server_output ) {
					$this->log->error( 'Status query failed. Query: ' . wp_json_encode( $order->get_parameters() ) );
					$failed = true;
				}
				$xml = simplexml_load_string( $server_output );

				if ( $xml !== false && 'errors' === $xml->getName() ) {
					$this->log->error( 'Status error. CorvusPay returned:' . PHP_EOL . $server_output );
					$failed = true;
				}

				if ( $failed ) {
					throw new Exception( esc_html__( 'An error occurred.', 'corvuspay-woocommerce-integration' ) );
				} else {
					$amount_after_discount = (float) $xml->{'transaction-amount'} / 100;

					if ( (float) $order->get_total() !== $amount_after_discount ) {
						$discount_amount = (float) $order->get_total() - $amount_after_discount;

						$this->wc_order_add_discount( $order->get_id(), $discount_amount );

						$order->update_meta_data( '_corvuspay_discount_used', true );
						$order->save_meta_data();
					}
				}
			}

			$action = $order->get_meta( '_corvuspay_action' );
			if ( 'auth' === $action ) {
				$order->update_status( 'on-hold', __( 'CorvusPay payment authorized. Awaiting capture.', 'corvuspay-woocommerce-integration' ) );
			} elseif ( 'sale' === $action ) {
				$order->payment_complete();
			} else {
				throw new Exception( esc_html__( 'Unsupported transaction action.', 'corvuspay-woocommerce-integration' ) );
			}

			$parameters = $order->get_parameters();

			if ( array_key_exists( 'account_id', $parameters ) && array_key_exists( 'subscription_exp_date', $parameters ) ) {
				$token = new WC_Payment_Token_CC_CorvusPay();
                $status_order  = new WC_Order_CorvusPay( $this->log, $this->options, 'api-status', $order );
				$api   = new CorvusPay_API( $this->log, $this->options, $status_order->get_client() );

				$server_output = $api->status( $status_order );

				$this->log->debug( 'New subscription $server_output:' . PHP_EOL . $server_output );

				// TODO check output

				$xml = simplexml_load_string( $server_output );

				$date = date_parse( (string) $xml->{'subscription-exp-date'} );

				// check if the card is already saved
				$duplicate = false;
				foreach ( $this->get_tokens() as $saved_token ) {
					$last4     = $saved_token->get_last4();
					$exp_year  = $saved_token->get_expiry_year();
					$exp_month = $saved_token->get_expiry_month();
					$cc_type   = $saved_token->get_card_type();
					$user_id   = $saved_token->get_user_id();

					if ( $last4 === substr( (string) $xml->{'card-details'}, - 4 ) &&
						 $exp_year === (string) $date['year'] &&
						 $cc_type === (string) $xml->{'cc-type'} &&
						 $exp_month === str_pad( (string) $date['month'], 2, '0', STR_PAD_LEFT ) &&
						 $user_id === get_current_user_id()
					) {
						$duplicate = true;
						break;
					}
				}
				if ( ! $duplicate || wcs_order_contains_subscription( $order ) || wcs_order_contains_renewal( $order ) ) {
					if ( $token->new_corvuspay_token(
							$this->id,
							$parameters['account_id'],
							(string) $xml->{'cc-type'},
							substr( (string) $xml->{'card-details'}, - 4 ),
							$date['month'],
							$date['year']
					) ) {
						$order->update_meta_data( '_corvuspay_token_id', $token->get_id() );
						$order->save_meta_data();
					} else {
						$this->log->error( "Failed to save token for Order #{$order->get_id()}." );
					}

					$this->update_subscriptions_payment_token( $order, $token->get_id() );
				}
				else {
					wc_add_notice( __( 'Payment method already exists.', 'corvuspay-woocommerce-integration' ), 'error' );
				}
				if ( 'add' === $order->get_meta( '_corvuspay_token' ) &&
				     'auth' === $order->get_meta( '_corvuspay_action' ) &&
				     1 == $order->get_total() ) {
					wc_create_refund(
						array(
							'order_id'       => $order->get_id(),
							'amount'         => 1,
							'reason'         => __( 'Refunding card storage transaction', 'corvuspay-woocommerce-integration' ),
							'refund_payment' => true,
						)
					);
				}
			}

			if ( 'add' === $order->get_meta( '_corvuspay_token' ) ) {
				wp_safe_redirect( wc_get_endpoint_url( 'payment-methods', '', wc_get_page_permalink( 'myaccount' ) ) );
			} else {
				wp_safe_redirect( $order->get_checkout_order_received_url() );
			}
		} catch ( Exception $e ) {
			$this->log->error( $e->getMessage() . ' $_POST: ' . wp_json_encode( $_POST ) );
			wp_die( esc_html( $e->getMessage() ), esc_html__( 'CorvusPay Success URL Failure', 'corvuspay-woocommerce-integration' ) );
		}
	}

	/**
	 * Add a discount to an Order.
	 * (Using the FEE API - A negative fee)
	 *
	 * @param  int     $order_id  The order ID. Required.
	 * @param  mixed   $amount  Fixed amount (float) based on the subtotal. Required.
	 */
	function wc_order_add_discount( $order_id, $amount ) {
		$order = wc_get_order( $order_id );
		$item  = new WC_Order_Item_Fee();

		$item->set_tax_class( '' );
		if ($amount > 0) {
			$item->set_name( esc_html__( 'Discount', 'corvuspay-woocommerce-integration' ) );
		} else {
			$item->set_name( esc_html__( 'Compensation', 'corvuspay-woocommerce-integration' ) );
		}
		$item->set_amount( - $amount );
		$item->set_total( - $amount );
		$item->set_tax_status( 'none' );
		$item->save();

		$order->add_item( $item );
		$order->calculate_totals(false);
		$order->save();
	}

	/**
	 * Handle Cancel URL. Redirects to Order Cancellation URL or changes order status to 'cancelled' for failed attempts to add a payment method.
	 */
	public function corvuspay_cancel_handler() {
		try {
			$this->log->debug( 'Enter corvuspay_cancel_handler $_POST: ' . wp_json_encode( $_POST ) );

			$order = new WC_Order_CorvusPay( $this->log, $this->options, 'callback' );

			$this->log->info( 'Cancel URL for Order #' . $order->get_id() . '.' );

			if ( 'add' === $order->get_meta( '_corvuspay_token' ) ) {
				$order->update_status( 'cancelled' );
				wp_safe_redirect( wc_get_endpoint_url( 'payment-methods', '', wc_get_page_permalink( 'myaccount' ) ) );
			} else {
				wp_safe_redirect( $order->get_cancel_order_url_raw() );
			}
		} catch ( Exception $e ) {
			$this->log->error( $e->getMessage() . ' $_POST: ' . wp_json_encode( $_POST ) );
			wp_die( esc_html( $e->getMessage() ), esc_html__( 'CorvusPay Cancel URL Failure', 'corvuspay-woocommerce-integration' ) );
		}
	}

	/**
	 * Updates subscriptions payment token.
	 *
	 * @param WC_Order $order Order.
	 * @param int      $token_id Token ID.
	 */
	private function update_subscriptions_payment_token( $order, $token_id ) {
		if ( $this->options->subscriptions ) {
			if ( wcs_order_contains_subscription( $order ) ) {
				$subscriptions = wcs_get_subscriptions_for_order( $order );
			} elseif ( wcs_order_contains_renewal( $order ) ) {
				$subscriptions = wcs_get_subscriptions_for_renewal_order( $order );
			} else {
				$subscriptions = array();
			}

			$this->log->debug( '$subscriptions: ' . wp_json_encode( $subscriptions ) );

			foreach ( $subscriptions as $subscription ) {
				$subscription->update_meta_data( '_corvuspay_token_id', $token_id );
				$subscription->save_meta_data();
			}
		}
	}

	/**
	 * Gets an endpoint URL.
	 *
	 * @param string $endpoint Endpoint for URL.
	 *
	 * @return string URL for endpoint.
	 */
	public static function get_url( $endpoint ) {
		return add_query_arg( 'wc-api', "corvuspay-{$endpoint}", trailingslashit( get_home_url() ) );
	}

	/**
	 * Gets the Checkout URI. Depends on environment.
	 *
	 * @return string Checkout URI
	 */
	private function get_checkout_uri() {
		return self::CHECKOUT_URL[ $this->options->environment ];
	}

	/**
	 * Generate Payment Fields HTML. Removes Credit Card input and adds description.
	 */
	public function payment_fields() {
		$search_array  = [];
		$replace_array = [];
		$subject       = $this->get_description();

		foreach ( WC_Order_CorvusPay::CARD_BRANDS as $key => $value ) {
			$search_array[]  = ":" . $key . ":";
			$replace_array[] = '<img style="width:10%" src=' . plugins_url( "assets/img/cards/{$key}.svg", WC_CORVUSPAY_FILE ) . ' alt="' . $key . '">';
		}

		$search_array[]  = ":iban:";
		$replace_array[] = '<img style="width:10%" src=' . plugins_url( "assets/img/outline/iban.svg", WC_CORVUSPAY_FILE ) . ' alt="iban">';

		$search_array[]  = ":paysafecard:";
		$replace_array[] = '<img style="width:10%" src=' . plugins_url( "assets/img/outline/paysafecard.svg", WC_CORVUSPAY_FILE ) . ' alt="paysafecard">';

		$search_array[]  = ":card:";
		$replace_array[] = '<img style="width:10%" src=' . plugins_url( "assets/img/outline/card.svg", WC_CORVUSPAY_FILE ) . ' alt="card">';

		$search_array[]  = ":wallet:";
		$replace_array[] = '<img style="width:10%" src=' . plugins_url( "assets/img/outline/wallet.svg", WC_CORVUSPAY_FILE ) . ' alt="wallet">';

		$new_string = str_replace( $search_array, $replace_array, $subject );
		echo wpautop( wptexturize( $new_string ) );
		if ( $this->supports( 'tokenization' ) && is_checkout() ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
			$this->save_payment_method_checkbox();
		}
	}

	/**
	 * Returns HTML to display a payment token. Adds Credit Card icons.
	 *
	 * @param WC_Payment_Token_CC $token Payment Token.
	 *
	 * @return mixed|string HTML.
	 */
	public function get_saved_payment_method_option_html( $token ) {
		$html = sprintf(
			'<li class="woocommerce-SavedPaymentMethods-token">
				<input id="wc-%1$s-payment-token-%2$s" type="radio" name="wc-%1$s-payment-token" value="%2$s" style="width:auto;" class="woocommerce-SavedPaymentMethods-tokenInput" %4$s />
				<label for="wc-%1$s-payment-token-%2$s"><img src="%5$s" />%3$s</label>
			</li>',
			esc_attr( $this->id ),
			esc_attr( $token->get_id() ),
			esc_html( $token->get_display_name() ),
			checked( $token->is_default(), true, false ),
			plugins_url( "assets/img/cards/{$token->get_card_type()}.svg", WC_CORVUSPAY_FILE )
		);

		return apply_filters( 'woocommerce_payment_gateway_get_saved_payment_method_option_html', $html, $token, $this );
	}
	// TODO show card icons on user pages

	/**
	 * Prints HTML for "save payment method" checkbox. Checks and disables checkbox if cart contains subscriptions.
	 */
	public function save_payment_method_checkbox() {
		$contains_subscription = false;
		if ( $this->options->subscriptions ) {
			$contains_subscription = WC_Subscriptions_Cart::cart_contains_subscription() || wcs_cart_contains_renewal();
			$this->log->debug( '$contains_subscription: ' . wp_json_encode( $contains_subscription ) );
		}

		$checked  = $contains_subscription;
		$disabled = $contains_subscription;
		printf(
			'<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
				<input id="wc-%1$s-new-payment-method" name="wc-%1$s-new-payment-method" type="checkbox" value="true" style="width:auto;" %3$s %4$s />
				<label for="wc-%1$s-new-payment-method" style="display:inline;">%2$s</label>
			</p>',
			esc_attr( $this->id ),
			esc_html__( 'Save to account', 'corvuspay-woocommerce-integration' ),
			checked( $checked, true, false ),
			disabled( $disabled, true, false )
		);
	}

	/**
	 * Generate Test Stores Settings HTML.
	 *
	 * @return string Test Stores Settings HTML.
	 */
	public function generate_test_stores_settings_html() {
		return self::generate_stores_settings_html( 'test' );
	}

	/**
	 * Generate Production Stores Settings HTML.
	 *
	 * @return string Production Stores Settings HTML.
	 */
	public function generate_prod_stores_settings_html() {
		return self::generate_stores_settings_html( 'prod' );
	}

	/**
	 * Generate Stores Settings HTML.
	 *
	 * @param string $prefix Stores Settings prefix.
	 *
	 * @return string Stores Settings HTML.
	 */
	public function generate_stores_settings_html( $prefix ) {
		ob_start();
		?>
		<tr valign="top">
			<th scope="row"
				class="titledesc">
		<?php
		if ( 'test' === $prefix ) {
			esc_html_e( 'Test Stores settings:', 'corvuspay-woocommerce-integration' );
		} elseif ( 'prod' === $prefix ) {
			esc_html_e( 'Production Stores settings:', 'corvuspay-woocommerce-integration' );
		}
		?>
			</th>
			<td class="forminp" id="<?php echo esc_attr( "woocommerce_corvuspay_{$prefix}_stores_settings" ); ?>">
				<div class="wc_input_table_wrapper">
					<table class="widefat wc_input_table sortable corvuspay_stores_settings" cellspacing="0">
						<thead>
						<tr>
							<th class="sort">&nbsp;</th>
							<th><?php esc_html_e( 'Currency', 'corvuspay-woocommerce-integration' ); ?></th>
							<th>
		<?php
		if ( 'test' === $prefix ) {
			esc_html_e( 'Test Store ID', 'corvuspay-woocommerce-integration' );
		} elseif ( 'prod' === $prefix ) {
			esc_html_e( 'Production Store ID', 'corvuspay-woocommerce-integration' );
		}
		?>
							</th>
							<th>
		<?php
		if ( 'test' === $prefix ) {
			esc_html_e( 'Test Secret Key', 'corvuspay-woocommerce-integration' );
		} elseif ( 'prod' === $prefix ) {
			esc_html_e( 'Production Secret Key', 'corvuspay-woocommerce-integration' );
		}
		?>
							</th>
						</tr>
						</thead>
						<tbody>
						<?php
						if ( $this->options->stores_settings[ $prefix ] ) {
							foreach ( $this->options->stores_settings[ $prefix ] as $i => $stores_setting ) {
								?>
								<tr>
									<td class="sort"></td>
									<td><select title="<?php esc_html_e( 'Currency', 'corvuspay-woocommerce-integration' ); ?>"
												name="<?php echo esc_attr( "{$prefix}_stores_settings_currency[{$i}][]" ); ?>"
												multiple="multiple">
											<?php
											foreach ( WC_Order_CorvusPay::CURRENCY_CODES as $currency => $code ) {
												?>
											<option value="<?php echo esc_attr( $currency ); ?>"
												<?php if ( in_array( $currency, $stores_setting['currency'], true ) ) { ?>
													selected="selected"<?php } ?>><?php echo esc_html( $currency ); ?></option>
												<?php
											}
											?>
											<option value="*"
												<?php if ( in_array( '*', $stores_setting['currency'], true ) ) { ?>
													selected="selected"<?php } ?>><?php esc_html_e( 'Other currencies', 'corvuspay-woocommerce-integration' ); ?></option>
										</select>
									</td>
									<td><input type="text"
												title="
								<?php
								if ( 'test' === $prefix ) {
									esc_html_e( 'Test Store ID', 'corvuspay-woocommerce-integration' );
								} elseif ( 'prod' === $prefix ) {
									esc_html_e( 'Production Store ID', 'corvuspay-woocommerce-integration' );
								}
								?>
												"
												value="<?php echo esc_attr( $this->options->stores_settings[ $prefix ][ $i ]['store_id'] ); ?>"
												name="<?php echo esc_attr( "{$prefix}_stores_settings_store_id[{$i}]" ); ?>"/>
									</td>
									<td><input type="password"
												title="
								<?php
								if ( 'test' === $prefix ) {
									esc_html_e( 'Test Secret Key', 'corvuspay-woocommerce-integration' );
								} elseif ( 'prod' === $prefix ) {
									esc_html_e( 'Production Secret Key', 'corvuspay-woocommerce-integration' );
								}
								?>
												"
												value="<?php echo esc_attr( $this->options->stores_settings[ $prefix ][ $i ]['secret_key'] ); ?>"
												name="<?php echo esc_attr( "{$prefix}_stores_settings_secret_key[{$i}]" ); ?>"/>
									</td>
								</tr>
								<?php
							}
						}
						?>
						</tbody>
						<tfoot>
						<tr>
							<th colspan="6">
								<a href="#" class="add button"><?php esc_html_e( '+ Add stores settings entry', 'corvuspay-woocommerce-integration' ); ?></a>
								<a href="#" class="remove_rows button"><?php esc_html_e( 'Remove selected stores settings entry', 'corvuspay-woocommerce-integration' ); ?></a>
							</th>
						</tr>
						</tfoot>
					</table>
					<p><?php esc_html_e( '"Other currencies" option has low precedence.', 'corvuspay-woocommerce-integration' ); ?></p>
					<p><?php esc_html_e( 'Example row: "EUR, USD; 123; abc".', 'corvuspay-woocommerce-integration' ); ?></p>
					<p><?php esc_html_e( 'Explanation: All orders in EUR or USD will be routed to Store ID 123 using Secret Key "abc".', 'corvuspay-woocommerce-integration' ); ?></p>
				</div>
				<script type="text/javascript">
					jQuery(function () {
						jQuery('.corvuspay_stores_settings select').selectWoo({width: '100%', theme: 'corvuspay'});
						jQuery('<?php echo esc_attr( "#woocommerce_corvuspay_{$prefix}_stores_settings" ); ?>').on('click', 'a.add', function () {
							var size = jQuery('<?php echo esc_attr( "#woocommerce_corvuspay_{$prefix}_stores_settings" ); ?>').find('tbody tr').length;
							jQuery('<tr>\
									<td class="sort"></td>\
									<td><select name="<?php echo esc_attr( "{$prefix}_stores_settings_currency" ); ?>[' + size + '][]" multiple="multiple">\<?php foreach ( WC_Order_CorvusPay::CURRENCY_CODES as $currency => $code ) { ?>
										<option value="<?php echo esc_attr( $currency ); ?>"><?php echo esc_html( $currency ); ?></option>\<?php echo PHP_EOL; } ?>
										<option value="*"><?php esc_html_e( 'Other currencies', 'corvuspay-woocommerce-integration' ); ?></option>\
									</select></td>\
									<td><input type="text" name="<?php echo esc_attr( "{$prefix}_stores_settings_store_id" ); ?>[' + size + ']" /></td>\
									<td><input type="password" name="<?php echo esc_attr( "{$prefix}_stores_settings_secret_key" ); ?>[' + size + ']" /></td>\
								</tr>').appendTo('<?php echo esc_attr( "#woocommerce_corvuspay_{$prefix}_stores_settings" ); ?> table tbody');
							jQuery('.corvuspay_stores_settings select').selectWoo({width: '100%', theme: 'corvuspay'});
							return false;
						});
					});
				</script>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate Installments Map HTML.
	 *
	 * @return string Installments Map HTML.
	 */
	public function generate_installments_map_html() {
		ob_start();
		?>
		<tr valign="top">
			<th scope="row"
					class="titledesc"><?php esc_html_e( 'Installments plan:', 'corvuspay-woocommerce-integration' ); ?></th>
			<td class="forminp" id="woocommerce_corvuspay_form_installments_map">
				<div class="wc_input_table_wrapper">
					<table class="widefat wc_input_table sortable" cellspacing="0">
						<thead>
						<tr>
							<th class="sort">&nbsp;</th>
							<th><?php esc_html_e( 'Card brand', 'corvuspay-woocommerce-integration' ); ?></th>
							<th><?php esc_html_e( 'Minimum installments', 'corvuspay-woocommerce-integration' ); ?></th>
							<th><?php esc_html_e( 'Maximum installments', 'corvuspay-woocommerce-integration' ); ?></th>
							<th><?php esc_html_e( 'General discount', 'corvuspay-woocommerce-integration' ); ?></th>
							<th><?php esc_html_e( 'Specific discount', 'corvuspay-woocommerce-integration' ); ?></th>
						</tr>
						</thead>
						<tbody>
						<?php
						if ( $this->options->installments_map ) {
							$count = count( $this->options->installments_map );
							for ( $i = 0; $i < $count; $i ++ ) {
								?>
								<tr>
									<td class="sort"></td>
                                    <td>
                                        <select title="<?php esc_attr_e('Card brand', 'corvuspay-woocommerce-integration'); ?>"
                                                name="installments_map_card_brand[<?php echo esc_attr($i); ?>]"><?php foreach (WC_Order_CorvusPay::CARD_BRANDS as $code => $brand) { ?>
                                                <option value="<?php echo esc_attr($code); ?>"
                                                    <?php if (strcmp($code, $this->options->installments_map[ $i ]['card_brand']) == 0) { ?>
                                                        selected="selected"<?php } ?>><?php echo esc_html($brand); ?></option>
                                                <?php
                                            }
                                            ?>
                                        </select></td>
                                    <td><input type="text"
												title="<?php esc_attr_e( 'Minimum installments', 'corvuspay-woocommerce-integration' ); ?>"
												value="<?php echo esc_attr( $this->options->installments_map[ $i ]['min_installments'] ); ?>"
												name="installments_map_min_installments[<?php echo esc_attr( $i ); ?>]"/>
									</td>
									<td><input type="text"
												title="<?php esc_attr_e( 'Maximum installments', 'corvuspay-woocommerce-integration' ); ?>"
												value="<?php echo esc_attr( $this->options->installments_map[ $i ]['max_installments'] ); ?>"
												name="installments_map_max_installments[<?php echo esc_attr( $i ); ?>]"/>
									</td>
									<td><input type="text"
												title="<?php esc_attr_e( 'General discount', 'corvuspay-woocommerce-integration' ); ?>"
												value="<?php echo esc_attr( $this->options->installments_map[ $i ]['general_percentage'] ); ?>"
												name="installments_map_general_percentage[<?php echo esc_attr( $i ); ?>]"/>
									</td>
									<td><input type="text"
												title="<?php esc_attr_e( 'Specific discount', 'corvuspay-woocommerce-integration' ); ?>"
												value="<?php echo esc_attr( $this->options->installments_map[ $i ]['specific_percentage'] ); ?>"
												name="installments_map_specific_percentage[<?php echo esc_attr( $i ); ?>]"/>
									</td>
								</tr>
								<?php
							}
						}
						?>
						</tbody>
						<tfoot>
						<tr>
							<th colspan="6">
								<a href="#" class="add button"><?php esc_html_e( '+ Add installment entry', 'corvuspay-woocommerce-integration' ); ?></a>
								<a href="#" class="remove_rows button"><?php esc_html_e( 'Remove selected installment entry', 'corvuspay-woocommerce-integration' ); ?></a>
							</th>
						</tr>
						</tfoot>
					</table>
					<p><?php esc_html_e( 'Example row: "Visa; 1; 2; 10; 15".', 'corvuspay-woocommerce-integration' ); ?></p>
					<p><?php esc_html_e( 'Explanation: All Visa cards get a 10% discount if customer pays in one payment or in two installments. Some Visa cards, issued by a specific issuer, get a 15% discount under the same conditions. To setup specific discounts, contact CorvusPay.', 'corvuspay-woocommerce-integration' ); ?></p>
				</div>
				<script type="text/javascript">
					jQuery(function () {
						jQuery('#woocommerce_corvuspay_form_installments_map select').selectWoo({width: '100%', theme: 'corvuspay'});
						jQuery('#woocommerce_corvuspay_form_installments_map').on('click', 'a.add', function () {
							var size = jQuery('#woocommerce_corvuspay_form_installments_map').find('tbody tr').length;
							jQuery('<tr>\
									<td class="sort"></td>\
									<td><select name="installments_map_card_brand[' + size + ']">\<?php foreach ( WC_Order_CorvusPay::CARD_BRANDS as $code => $brand ) { ?>
										<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $brand ); ?></option>\<?php echo PHP_EOL; } ?>
									</select></td>\
									<td><input type="text" name="installments_map_min_installments[' + size + ']" /></td>\
									<td><input type="text" name="installments_map_max_installments[' + size + ']" /></td>\
									<td><input type="text" name="installments_map_general_percentage[' + size + ']" value="0" /></td>\
									<td><input type="text" name="installments_map_specific_percentage[' + size + ']" value="0" /></td>\
								</tr>').appendTo('#woocommerce_corvuspay_form_installments_map table tbody');
							jQuery('#woocommerce_corvuspay_form_installments_map select').selectWoo({width: '100%', theme: 'corvuspay'});
							return false;
						});
					});
				</script>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Returns human readable certificate description with validity.
	 *
	 * @param string $environment Payment environment. One of 'test'|'prod'.
	 *
	 * @return string Certificate description.
	 */
	private function certificate_info( $environment ) {
		$certificate = $this->get_option( "{$environment}_certificate" );
		$certificate_crt_pem = $this->get_option( "{$environment}_certificate_crt_pem" );
		$certificate_key_pem = $this->get_option( "{$environment}_certificate_key_pem" );


		if ( strpos( curl_version()['ssl_version'], 'NSS/' ) !== false || strpos( curl_version()['ssl_version'], 'GnuTLS' ) !== false ) {
			if ( empty( $certificate_crt_pem ) || empty( $certificate_key_pem ) ) {
				return __( 'No certificate stored. Only certificates in PKCS#12 (*.p12) format are supported.', 'corvuspay-woocommerce-integration' );
			}

			$certificate = openssl_x509_parse( base64_decode( $certificate_crt_pem ) );
		} else {
			if ( empty( $certificate ) ) {
				return __( 'No certificate stored. Only certificates in PKCS#12 (*.p12) format are supported.', 'corvuspay-woocommerce-integration' );
			}

			$certificate = base64_decode( $certificate );
			$pem_array   = array();
			if ( ! openssl_pkcs12_read( $certificate, $pem_array, '' ) ) {
				return __( 'Invalid certificate.', 'corvuspay-woocommerce-integration' );
			}
			$certificate = openssl_x509_parse( $pem_array['cert'] );
		}

		if ( ! $certificate ) {
			return __( 'Invalid certificate.', 'corvuspay-woocommerce-integration' );
		}

		$expiration = date( 'Y-m-d', $certificate['validTo_time_t'] );
		if ( $certificate['validTo_time_t'] < time() ) { // Already expired.
			/* translators: %1$s: Certificate Common Name, %2$s: Expiration date */
			return '<strong>' . esc_html( sprintf( __( 'Certificate "%1$s" expired on %2$s.', 'corvuspay-woocommerce-integration' ), $certificate['subject']['CN'], $expiration ) ) . '</strong>';
		} elseif ( $certificate['validTo_time_t'] < ( time() - 30 * 24 * 60 * 60 ) ) { // Expires soon.
			/* translators: %1$s: Certificate Common Name, %2$s: Expiration date */
			return '<strong>' . esc_html( sprintf( __( 'Certificate "%1$s" will expire on %2$s.', 'corvuspay-woocommerce-integration' ), $certificate['subject']['CN'], $expiration ) ) . '</strong>';
		} else { // Valid.
			/* translators: %1$s: Certificate Common Name, %2$s: Expiration date */
			return esc_html( sprintf( __( 'Certificate "%1$s" is valid until %2$s.', 'corvuspay-woocommerce-integration' ), $certificate['subject']['CN'], $expiration ) );
		}
	}

	/**
	 * Display notification for unable storing the certificate.
	 */
	function unable_to_store_certificate_error() {
		$class   = 'notice notice-error';
		$message = __( 'Unable to store certificate.', 'corvuspay-woocommerce-integration' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Display notification for unable reading the certificate.
	 */
	function unable_to_read_certificate_error() {
		$class   = 'notice notice-error';
		$message = __( 'Unable to read certificate. Please check password.', 'corvuspay-woocommerce-integration' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Display notification for unsupported time limit value.
	 */
	function unsupported_time_limit_error() {
		$class   = 'notice notice-error';
		$message = esc_html__( 'Unsupported time limit value. Valid range is from 1 to 900.', 'corvuspay-woocommerce-integration' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Display notification for unsupported order number format value.
	 */
	function unsupported_order_number_format_error() {
		$class   = 'notice notice-error';
		$message = esc_html__( 'The "Order Number Format" field is required and must contain {post_id} or {order_number}.', 'corvuspay-woocommerce-integration' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Display notification for certificate required when tokenization is enabled.
	 */
	function certificate_required_when_tokenization_enabled_error() {
		$class   = 'notice notice-error';
		$message = esc_html__( 'Certificate is required when tokenization is enabled.', 'corvuspay-woocommerce-integration' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Display notification for certificate required when installments plan is set.
	 */
	function certificate_required_when_installments_map_set_error() {
		$class   = 'notice notice-error';
		$message = esc_html__( 'Certificate is required when Advanced installments plan is set.', 'corvuspay-woocommerce-integration' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Display notification for invalid advanced installments.
	 */
	function invalid_installments_map_error() {
		$class   = 'notice notice-error';
		$message = esc_html__( 'Invalid advanced installments.', 'corvuspay-woocommerce-integration' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	/**
	 * Process advanced admin options. Includes certificates and installments_map.
	 *
	 * @return bool from parent::process_admin_options();
	 */
	public function process_admin_options() {
	    // WordPress adds slashes to $_POST/$_GET/$_REQUEST/$_COOKIE regardless of what get_magic_quotes_gpc() returns.
		$_POST = stripslashes_deep( $_POST );

		// If a certificate has been uploaded, read the contents and save Base64 encoded data instead.
		foreach ( array( 'test', 'prod' ) as $environment ) {
			$parameter = "woocommerce_corvuspay_{$environment}_certificate";
			if ( array_key_exists( $parameter, $_FILES )
				&& array_key_exists( 'tmp_name', $_FILES[ $parameter ] )
				&& array_key_exists( 'size', $_FILES[ $parameter ] )
				&& $_FILES[ $parameter ]['size'] ) {

				$_POST[ $parameter ] = '';

				$pem_array = array();
				if ( openssl_pkcs12_read( file_get_contents( $_FILES[ $parameter ]['tmp_name'] ), $pem_array, $_POST[ "{$parameter}_password" ] ) ) {
					$this->log->debug( 'openssl_pkcs12_read: ' . wp_json_encode( $pem_array ) );
				} else {
					/* translators: %s: Certificate file name */
					add_action( 'admin_notices', array( $this, 'unable_to_read_certificate_error' ) );
					$this->log->notice( 'Unable to read certificate "' . $_FILES[ $parameter ]['name'] . '".' );
					continue;
				}
				$pem_array['friendlyname'] = $_FILES[ $parameter ]['name'];

				if ( strpos( curl_version()['ssl_version'], 'NSS/' ) !== false || strpos( curl_version()['ssl_version'], 'GnuTLS' ) !== false ) {
					// Extract the certificate and convert to PEM
					$_POST[ $parameter . '_crt_pem' ] = base64_encode( $pem_array['cert'] );
					$_POST[ $parameter . '_key_pem' ] = base64_encode( $pem_array['pkey'] );
					$this->log->notice( 'Curl with NSS detected, saved certificate and private key.' );
				} else {
					$pkcs12_string = '';
					if ( openssl_pkcs12_export( $pem_array['cert'], $pkcs12_string, $pem_array['pkey'], '', $pem_array ) ) {
						$this->log->debug( 'Certificate "' . $_FILES[ $parameter ]['name'] . '" uploaded: ' . wp_json_encode( $_FILES[ $parameter ] ) );
						$_POST[ $parameter ] = base64_encode( $pkcs12_string );
					} else {
						/* translators: %s: Certificate file name */
						add_action( 'admin_notices', array( $this, 'unable_to_store_certificate_error' ) );
						$this->log->notice( 'Unable to store certificate "' . $_FILES[ $parameter ]['name'] . '".' );
						continue;
					}
				}
				unlink( $_FILES[ $parameter ]['tmp_name'] );
				unset( $_FILES[ $parameter ] );
			} else {
				$_POST[ $parameter ] = $this->get_option( $environment . '_certificate' );
				$_POST[ $parameter . '_crt_pem' ] = $this->get_option( $environment . '_certificate_crt_pem' );
				$_POST[ $parameter . '_key_pem' ] = $this->get_option( $environment . '_certificate_key_pem' );
			}
			$_POST[ "{$parameter}_password" ] = '';
		}

		foreach ( array( 'test', 'prod' ) as $environment ) {
			${"{$environment}_stores_settings"} = array();
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST[ "{$environment}_stores_settings_currency" ] ) &&
				isset( $_POST[ "{$environment}_stores_settings_store_id" ] ) &&
				isset( $_POST[ "{$environment}_stores_settings_secret_key" ] ) ) {
				${"{$environment}_currency"}   = wc_clean( wp_unslash( $_POST[ "{$environment}_stores_settings_currency" ] ) );
				${"{$environment}_store_id"}   = wc_clean( wp_unslash( $_POST[ "{$environment}_stores_settings_store_id" ] ) );
				${"{$environment}_secret_key"} = wc_clean( wp_unslash( $_POST[ "{$environment}_stores_settings_secret_key" ] ) );
				// phpcs:enable
				foreach ( ${"{$environment}_currency"} as $i => $currency ) {
					if ( ! isset( ${"{$environment}_currency"}[ $i ] ) ) {
						continue;
					}
					${"{$environment}_stores_settings"}[] = array(
						'currency'   => ${"{$environment}_currency"}[ $i ],
						'store_id'   => ${"{$environment}_store_id"}[ $i ],
						'secret_key' => ${"{$environment}_secret_key"}[ $i ],
					);
				}
			}

			$_POST[ "woocommerce_corvuspay_{$environment}_stores_settings" ] = wp_json_encode( ${"{$environment}_stores_settings"} );
		}

		$installments_map = array();
        $is_valid_installments_map = true;
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['installments_map_card_brand'] ) &&
			isset( $_POST['installments_map_min_installments'] ) &&
			isset( $_POST['installments_map_max_installments'] ) &&
			isset( $_POST['installments_map_general_percentage'] ) &&
			isset( $_POST['installments_map_specific_percentage'] ) ) {
			$card_brand          = wc_clean( wp_unslash( $_POST['installments_map_card_brand'] ) );
			$min_installments    = wc_clean( wp_unslash( $_POST['installments_map_min_installments'] ) );
			$max_installments    = wc_clean( wp_unslash( $_POST['installments_map_max_installments'] ) );
			$general_percentage  = wc_clean( wp_unslash( $_POST['installments_map_general_percentage'] ) );
			$specific_percentage = wc_clean( wp_unslash( $_POST['installments_map_specific_percentage'] ) );
			// phpcs:enable
			foreach ( $card_brand as $i => $brand ) {
				if ( ! isset( $card_brand[ $i ] ) ) {
					continue;
				}
                if(is_numeric($min_installments[ $i ]) && is_numeric($max_installments[ $i ]) && is_numeric($general_percentage[ $i ]) && is_numeric($specific_percentage[ $i ])) {
	                $installments_map[] = array(
		                'card_brand'          => $card_brand[ $i ],
		                'min_installments'    => $min_installments[ $i ],
		                'max_installments'    => $max_installments[ $i ],
		                'general_percentage'  => $general_percentage[ $i ],
		                'specific_percentage' => $specific_percentage[ $i ],
	                );
                }
                else {
	                $is_valid_installments_map = false;
                }
			}
		}

		$_POST['woocommerce_corvuspay_form_installments_map'] = wp_json_encode( $installments_map );

        if ( $_POST["woocommerce_corvuspay_form_time_limit_enabled"] === "1" &&
             ( ! ctype_digit( $_POST["woocommerce_corvuspay_form_time_limit_seconds"] ) ||
               (int) $_POST["woocommerce_corvuspay_form_time_limit_seconds"] > 900 ||
               (int) $_POST["woocommerce_corvuspay_form_time_limit_seconds"] < 1 ) ) {
			add_action( 'admin_notices', array( $this, 'unsupported_time_limit_error' ) );
			$this->log->notice( 'Unsupported time limit value. Valid range is from 1 to 900.', 'corvuspay-woocommerce-integration'  );
		}

		$environment         = $_POST["woocommerce_corvuspay_environment"];
		$order_number_format = $_POST["woocommerce_corvuspay_{$environment}_order_number_format"];
		if ( ! ( isset( $order_number_format ) &&
		         ( strpos( $order_number_format, '{post_id}' ) !== false ||
		           strpos( $order_number_format, '{order_number}' ) !== false ) ) ) {
			$_POST["woocommerce_corvuspay_{$environment}_order_number_format"] = $this->get_option( $environment . '_order_number_format' );
			add_action( 'admin_notices', array( $this, 'unsupported_order_number_format_error' ) );
			$this->log->notice( 'The "Order Number Format" field is required and must contain {post_id} or {order_number}.', 'corvuspay-woocommerce-integration' );
		}

		$parameter_p12     = "woocommerce_corvuspay_{$environment}_certificate";
		$parameter_crt_pem = "woocommerce_corvuspay_{$environment}_certificate_crt_pem";
		$parameter_key_pem = "woocommerce_corvuspay_{$environment}_certificate_key_pem";
		$isset_cert        = ( isset( $_POST[ $parameter_p12 ] ) && $_POST[ $parameter_p12 ] !== "" ) ||
		                     ( ( isset( $_POST[ $parameter_crt_pem ] ) && $_POST[ $parameter_crt_pem ] !== "" ) &&
		                       ( isset( $_POST[ $parameter_key_pem ] ) && $_POST[ $parameter_key_pem ] !== "" ) );

		if ( isset( $_POST["woocommerce_corvuspay_tokenization"] ) && !$isset_cert) {
			unset( $_POST['woocommerce_corvuspay_tokenization'] );
			add_action( 'admin_notices', array( $this, 'certificate_required_when_tokenization_enabled_error' ) );
			$this->log->notice( 'Certificate is required when tokenization is enabled.', 'corvuspay-woocommerce-integration' );
		}

		if ( $_POST['woocommerce_corvuspay_form_installments'] === "map" && (count( $installments_map ) === 0 || !$is_valid_installments_map)) {
            // Set old values for woocommerce_corvuspay_form_installments and woocommerce_corvuspay_form_installments_map and show error.
			$_POST['woocommerce_corvuspay_form_installments'] = $this->options->installments;
			if ( ! isset( $this->options->installments_map ) || count( $this->options->installments_map ) === 0 ) {
				unset( $_POST['woocommerce_corvuspay_form_installments_map'] );
			} else {
				$_POST['woocommerce_corvuspay_form_installments_map'] = wp_json_encode( $this->options->installments_map );
			}

			add_action( 'admin_notices', array( $this, 'invalid_installments_map_error' ) );
			$this->log->notice( 'Invalid advanced installments', 'corvuspay-woocommerce-integration' );
		}

		if ( count( $installments_map ) > 0 && !$isset_cert ) {
			if ( ! isset( $this->options->installments_map ) || count( $this->options->installments_map ) === 0 ) {
				unset( $_POST['woocommerce_corvuspay_form_installments_map'] );
			} else {
				$_POST['woocommerce_corvuspay_form_installments_map'] = wp_json_encode( $this->options->installments_map );
			}

			add_action( 'admin_notices', array( $this, 'certificate_required_when_installments_map_set_error' ) );
			$this->log->notice( 'Certificate is required when installments map is set.', 'corvuspay-woocommerce-integration' );
		}

		return parent::process_admin_options();
	}

	/**
	 * Adds subscription payment metadata. Inserts CorvusPay metadata. Metadata key '_corvuspay_token_id' contains Token ID.
	 *
	 * @param array           $payment_meta Array containing metadata.
	 * @param WC_Subscription $subscription Subscription for metadata.
	 *
	 * @return array Array containing metadata.
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				'_corvuspay_token_id' => array(
					'value' => $subscription->get_meta( '_corvuspay_token_id' ),
					'label' => __( 'CorvusPay Token ID', 'corvuspay-woocommerce-integration' ),
				),
			),
		);

		return $payment_meta;
	}

	/**
	 * Change order number depending on the defined order number format.
	 *
	 * @param WC_Order $order Order to check.
	 * @param string $order_number Current order number.
	 *
	 * @return string New order number.
	 */
	function change_woocommerce_order_number( $order_number, $order ) {
		global $wp;

		if ( isset($wp->query_vars['order-pay']) && absint($wp->query_vars['order-pay']) > 0 && $order->get_payment_method() === "corvuspay") {
			if ( get_post_meta( $order->get_id(), '_corvuspay_order_number', true ) ) {
				return get_post_meta( $order->get_id(), '_corvuspay_order_number', true );
			} else {
				$order_number_base = get_post_meta( $order->get_id(), '_order_number', true );
				if ( ! $order_number_base ) {
					$order_number_base = $order->get_id();
				}
				$corvus_order_number = strtr(
					$this->options->order_number_format[ $this->options->environment ],
					array(
						'{site_title}'   => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
						'{post_id}'      => $order->get_id(),
						'{order_number}' => $order_number_base
					)
				);
				update_post_meta( $order->get_id(), '_corvuspay_order_number', $corvus_order_number );
				update_post_meta( $order->get_id(), '_order_number', $corvus_order_number );
				return $corvus_order_number;
			}
        } else {
            if ( $order->get_payment_method() === "corvuspay" && get_post_meta( $order->get_id(), '_corvuspay_order_number', true ) ) {
                return get_post_meta( $order->get_id(), '_corvuspay_order_number', true );
            }

            return $order_number;
        }
	}

	/**
	 * Validates subscription payment metadata.
	 *
	 * @param string $payment_method_id Gateway ID.
	 * @param array  $payment_meta Array containing metadata.
	 *
	 * @throws Exception Error if metadata failed check.
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {
		if ( $this->id === $payment_method_id ) {
			if ( ! isset( $payment_meta['post_meta']['_corvuspay_token_id']['value'] ) || empty( $payment_meta['post_meta']['_corvuspay_token_id']['value'] ) ) {
				throw new Exception( __( 'CorvusPay Token ID is required.', 'corvuspay-woocommerce-integration' ) );
			} elseif ( (int) $payment_meta['post_meta']['_corvuspay_token_id']['value'] != $payment_meta['post_meta']['_corvuspay_token_id']['value'] ) {
				throw new Exception( 'CorvusPay Token ID should be a number.' );
			}
		}
	}

	/**
	 * Checks whether Order can be refunded.
	 *
	 * @param WC_Order $order Order to check.
	 *
	 * @return bool Can Order be refunded.
	 */
	public function can_refund_order( $order ) {
		return $order->get_remaining_refund_amount() > 0;
	}

	/**
	 * Added row in emails for total amount when fee used.
	 *
	 * @param  $total_rows
	 * @param  $that
	 * @param  $tax_display
	 *
	 * @return
	 */
	public function recalculate_order_total_in_email( $total_rows, $order, $tax_display ) {
		// Only on emails notifications when creating orders with fee(positive or negative) for corvuspay
		if ( is_wc_endpoint_url() || $order->get_payment_method() != 'corvuspay' || ! $order->get_fees() || !isset($_POST['order_number'])) {
			return $total_rows;
		}

		// Recalculate totals
		$order->calculate_totals();

		$this->log->info( 'Order with discount. Recalculating totals.' );

		if ( $total_rows['order_total'] ) {
			$total_rows['order_total']['value'] = wc_price( $order->get_total() );
		} else {
			// Add new row
			$total_rows['corvuspay_total_amount']['label'] = __( 'Total to pay', 'woocommerce' );
			$total_rows['corvuspay_total_amount']['value'] = wc_price( $order->get_total() );
		}

		return $total_rows;
	}

	/**
	 * Add content to thank you page.
	 *
	 * @param int $order_id Order id.
	 */
	function corvuspay_content_thankyou( $order_id ) {
		$this->echo_additional_order_details( $order_id );
	}

	/**
	 * Add content to e-mail.
	 *
	 * @param WC_Order $order Order to check.
	 * @param bool $sent_to_admin Is sent.
	 * @param bool $plain_text Is plain text.
	 * @param WC_Email $email Email.
	 */
	function corvuspay_email_after_order_table( $order, $sent_to_admin, $plain_text, $email ) {
		if ( ( $email->id === "new_order" || $email->id === "customer_processing_order" ) && $order->get_payment_method() === "corvuspay" ) {
			$this->echo_additional_order_details( $order->get_id() );
		}
	}

	/**
	 * Echo additional order details.
	 *
	 * @param int $order_id Order id.
	 */
	function echo_additional_order_details( $order_id ) {
		$approval_code    = get_post_meta( $order_id, '_corvuspay_approval_code', true );
		$transaction_date = get_post_meta( $order_id, '_corvuspay_transaction_date', true );

		if ( isset( $approval_code ) && isset( $transaction_date ) ) {
			echo '<p>' . __( 'Payment successful - payment card account debited.', 'corvuspay-woocommerce-integration' ) . '</p>
                  <ul>
                    <li>' . __( 'Approval code: ', 'corvuspay-woocommerce-integration' ) . $approval_code . ' </li>
                    <li>' . __( 'Transaction date and time: ', 'corvuspay-woocommerce-integration' ) . $transaction_date . '</li>
                  </ul>';
		}
	}

	/**
	 * Adds the capture charge button to the order UI.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function add_capture_button( $order ) {
		$payment_method   = $order->get_payment_method();
		$payment_gateways = WC()->payment_gateways()->payment_gateways();
		$gateway          = isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ] : null;

		// only display the button for corvuspay orders
		if ( 'shop_order' !== get_post_type( $order->get_id() ) || 'auth' !== $order->get_meta( '_corvuspay_action' ) || ! $gateway || ! ( $gateway instanceof WC_Gateway_CorvusPay ) ) {
			return;
		}
		?>
        <button type="button" class="button capture"
                id="partial_complete"><?php echo __( 'Capture Charge', 'corvuspay-woocommerce-integration' ); ?></button>
		<?php

		// add the partial capture UI HTML
		$this->output_partial_capture_html( $order );
	}

	/**
	 * Outputs the partial capture UI HTML.
	 *
	 * @param WC_Order $order Order object.
	 */
	protected function output_partial_capture_html( \WC_Order $order ) {
		$authorization_total = $order->get_total();

		include WC_CORVUSPAY_PATH . '/assets/views/html-order-capture.php';
	}

	/**
	 * Ajax callback for complete order.
	 */
	function complete_order() {
		$order_id     = intval( $_POST['order_id'] );
		$order_amount = floatval( $_POST['order_amount'] );

		//getting order Object
		$order = wc_get_order( $order_id );

		$back_data = array( 'error' => 0 );
		if ( $order !== false ) {
			if ( 'auth' === $order->get_meta( '_corvuspay_action' ) ) {
				$payment_method   = $order->get_payment_method();
				$payment_gateways = WC()->payment_gateways()->payment_gateways();
				$gateway          = isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ] : null;
				if ( $gateway && ( $gateway instanceof WC_Gateway_CorvusPay ) ) {
					$result = $gateway->capture_charge( $order_amount, $order );
					if ( $result !== true ) {
						$back_data['error'] = 1;
						/* translators: %s: Error description */
						$back_data['message'] = $result->errors["corvuspay_api_error"];

					}
				}
			}
		} else {
			$back_data['error'] = 1;
		}
		wp_send_json( $back_data );
	}

	/**
	 * Capture the provided amount.
	 *
	 * @param float $amount
	 * @param WC_Order $order
	 *
	 * @return bool|WP_Error
	 */
	public function capture_charge( $amount, $order ) {
		$id       = $order->get_transaction_id();
		$order_id = $order->get_id();
		try {
			$order = new WC_Order_CorvusPay( $this->log, $this->options, 'api', $order_id );
			$this->log->info( "Completing Order #{$order_id} with amount {$amount} {$order->get_currency()}" );

			$api    = new CorvusPay_API( $this->log, $this->options, $order->get_client() );
			$result = $api->complete( $order, $amount );

			$this->log->debug( '$result: ' . wp_json_encode( $result ) );

			if ( $result === true ) {
				$order->update_meta_data( '_corvuspay_action', 'api-completed' );
				$order->save_meta_data();
				$order->payment_complete( $id );
				if ( $amount !== $order->get_total() ) {
					$order->set_total( $amount );
				}
				$order->save();
			}

			return $result;
		} catch ( Exception $e ) {
			$this->log->error( $e->getMessage() . ' $order_id: ' . wp_json_encode( $order_id ) );

			return false;
		}
	}

	/**
	 * Function that is called when order status is changed to processing.
	 *
	 * @param int $order_id
	 * @param WC_Order $order
	 *
	 */
	public static function remove_order_note_when_status_change_to_processing( $order_id, $order ) {
		if ( 'api-completed' === $order->get_meta( '_corvuspay_action' ) ) {
			$payment_method   = $order->get_payment_method();
			$payment_gateways = WC()->payment_gateways()->payment_gateways();
			$gateway          = isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ] : null;
			if ( $gateway && ( $gateway instanceof WC_Gateway_CorvusPay ) ) {
				add_filter( 'woocommerce_new_order_note_data', function ( $args ) use ( $order ) {
					if ( 'api-completed' === $order->get_meta( '_corvuspay_action' ) ) {
						$order->update_meta_data( '_corvuspay_action', 'after-api-completed' );
						$order->save_meta_data();

						return $args;

					} elseif ( 'after-api-completed' === $order->get_meta( '_corvuspay_action' ) ) {
						$order->update_meta_data( '_corvuspay_action', 'completed' );
						$order->save_meta_data();

						return [];
					} else {
						return $args;
					}
				} );
			}
		}
	}

	/**
	 * Function that is called when order status is changed to cancelled.
	 *
	 * @param int $order_id
	 * @param WC_Order $order
	 *
	 */
	public static function remove_order_note_when_status_change_to_void( $order_id, $order ) {
		if ( 'refunded_to_be_voided' === $order->get_meta( '_corvuspay_action' ) || 'after_refund' === $order->get_meta( '_corvuspay_action' ) ) {
			$payment_method   = $order->get_payment_method();
			$payment_gateways = WC()->payment_gateways()->payment_gateways();
			$gateway          = isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ] : null;
			if ( $gateway && ( $gateway instanceof WC_Gateway_CorvusPay ) ) {
				add_filter( 'woocommerce_new_order_note_data', function ( $args ) use ( $order ) {
					if ( 'refunded_to_be_voided' === $order->get_meta( '_corvuspay_action' ) ) {
						$order->update_meta_data( '_corvuspay_action', 'after_refund' );
						$order->save_meta_data();

						return $args;

					} elseif ( 'after_refund' === $order->get_meta( '_corvuspay_action' ) ) {
						$order->update_meta_data( '_corvuspay_action', 'voided' );
						$order->save_meta_data();

						return [];
					} else {
						return $args;
					}
				} );
			}
		}
	}

	/**
	 * Remove order note when status is changed to the same status.
	 *
	 * @param int $order_id
	 * @param int $old_status
	 * @param int $new_status
	 * @param WC_Order $order
	 *
	 */
	public static function remove_order_note_when_status_change_to_same_status( $order_id, $old_status, $new_status, $order ) {
		$payment_method   = $order->get_payment_method();
		$payment_gateways = WC()->payment_gateways()->payment_gateways();
		$gateway          = isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ] : null;
		if ( $gateway && ( $gateway instanceof WC_Gateway_CorvusPay ) && $old_status === $new_status ) {
			add_filter( 'woocommerce_new_order_note_data', function ( $args ) use ( $order ) {
				if ( $old_status === $new_status ) {
					return [];
				} else {
					return $args;
				}
			} );
		}
	}

	/**
	 * Function that is called when order is changed. Handle void and capture order.
	 *
	 * @param WC_Data_Store $data_store the data to be stored through WC_Data_Store class
	 * @param WC_Order $order WC_Order object
	 *
	 */
	public static function corvuspay_handle_order_status_change( $order, $data_store ) {
		$changes = $order->get_changes();
		if ( isset( $changes['status'] ) ) {
			$data        = $order->get_data();
			$from_status = $data['status'];
			$to_status   = $changes['status'];
			if ( $from_status === 'on-hold' && $to_status === 'cancelled' ) {
				if ( 'auth' === get_post_meta( $order->get_id(), '_corvuspay_action', true ) ) {
					$payment_method   = $order->get_payment_method();
					$payment_gateways = WC()->payment_gateways()->payment_gateways();
					$gateway          = isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ] : null;
					if ( $gateway && ( $gateway instanceof WC_Gateway_CorvusPay ) ) {
						$res = $gateway->void( $order->get_total(), $order );
						if ( ! $res ) {
							$order->set_status( $from_status );
							$order->update_meta_data( '_corvuspay_remove_duplicate_note', 'false' );
							add_filter( 'woocommerce_new_order_note_data', function ( $args ) use ( $order ) {
								if ( 'false' === $order->get_meta( '_corvuspay_remove_duplicate_note' ) ) {
									$order->update_meta_data( '_corvuspay_remove_duplicate_note', 'true' );
									$order->save_meta_data();

									return $args;

								} elseif ( 'true' === $order->get_meta( '_corvuspay_remove_duplicate_note' ) ) {
									$order->update_meta_data( '_corvuspay_remove_duplicate_note', 'removed' );
									$order->save_meta_data();

									return [];
								} else {
									return $args;
								}
							} );
							/* translators: %1$s: From status, %2$s: To status */
							throw new Exception( sprintf( __( "You are not allowed to change order from %1s to %2s.", "corvuspay-woocommerce-integration" ),
                                ucfirst( wc_get_order_status_name( $from_status ) ), ucfirst( wc_get_order_status_name( $to_status ) ) ) );

							return false;
						}
					}
				}
			} elseif ( $from_status === 'on-hold' && $to_status === 'processing' ) {
				if ( 'auth' === get_post_meta( $order->get_id(), '_corvuspay_action', true ) ) {
					$payment_method   = $order->get_payment_method();
					$payment_gateways = WC()->payment_gateways()->payment_gateways();
					$gateway          = isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ] : null;
					if ( $gateway && ( $gateway instanceof WC_Gateway_CorvusPay ) ) {
						$res = $gateway->capture_charge( $order->get_total(), $order );
						if ( $res !== true ) {
							$order->set_status( $from_status );
							$order->update_meta_data( '_corvuspay_remove_duplicate_note', 'false' );
							add_filter( 'woocommerce_new_order_note_data', function ( $args ) use ( $order ) {
								if ( 'false' === $order->get_meta( '_corvuspay_remove_duplicate_note' ) ) {
									$order->update_meta_data( '_corvuspay_remove_duplicate_note', 'true' );
									$order->save_meta_data();

									return $args;

								} elseif ( 'true' === $order->get_meta( '_corvuspay_remove_duplicate_note' ) ) {
									$order->update_meta_data( '_corvuspay_remove_duplicate_note', 'removed' );
									$order->save_meta_data();

									return [];
								} else {
									return $args;
								}
							} );
							/* translators: %1$s: From status, %2$s: To status */
							throw new Exception( sprintf( __( "You are not allowed to change order from %1s to %2s.", "corvuspay-woocommerce-integration" ),
                                ucfirst( wc_get_order_status_name( $from_status ) ), ucfirst( wc_get_order_status_name( $to_status ) ) ) );

							return false;
						}
					}
				}
			}
		}

		return $order;
	}

	/**
	 * Void the amount.
	 *
	 * @param float $amount
	 * @param WC_Order $order
	 *
	 * @return bool|WP_Error
	 */
	public function void( $amount, $order ) {
		$result = wc_create_refund(
			array(
				'order_id'       => $order->get_id(),
				'amount'         => $amount,
				'refund_payment' => true,
			)
		);

		if ( ! is_a( $order, 'WC_Order' ) || is_a( $result, 'WP_Error' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Change the order status for a voided order to cancelled when voiding.
	 *
	 * @param string $order_status default order status for fully refunded orders
	 * @param int $order_id order ID
	 *
	 * @return string order status
	 */
	public function corvuspay_cancel_voided_order( $order_status, $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'refunded_to_be_voided' === $order->get_meta( '_corvuspay_action' ) ) {
			$payment_method   = $order->get_payment_method();
			$payment_gateways = WC()->payment_gateways()->payment_gateways();
			$gateway          = isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ] : null;
			if ( $gateway && ( $gateway instanceof WC_Gateway_CorvusPay ) ) {
				$order->update_meta_data( '_corvuspay_refunded', 'true' );
				$order->save_meta_data();

				return 'cancelled';
			}
		} else {
			return $order_status;
		}
	}

/**
	 * Returns the payment gateway id.
	 *
	 * @see WC_Payment_Gateway::$id
	 * @return string Payment gateway id.
	 */
	public function get_id() {
		return $this->id;
	}
}

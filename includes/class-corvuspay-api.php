<?php
/**
 * Class CorvusPay_API
 *
 * @package corvuspay-woocommerce-integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once WP_PLUGIN_DIR . '/corvuspay-woocommerce-integration/vendor/autoload.php';
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_error
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_exec
// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_close

/**
 * CorvusPay API Class.
 */
class CorvusPay_API {

	/**
	 * CorvusPay API endpoints for test and production.
	 */
	const API_ENDPOINTS = \CorvusPay\ApiRequestor::API_ENDPOINTS;

	/**
	 * CorvusPay Gateway Logger.
	 *
	 * @var WC_Logger_CorvusPay CorvusPay Gateway Logger.
	 */
	private $log;

	/**
	 * CorvusPay Gateway Options.
	 *
	 * @var WC_Gateway_CorvusPay_Options CorvusPay Gateway Options.
	 */
	private $options;

	/**
	 * CorvusPay Client.
	 *
	 * @var CorvusPay\CorvusPayClient CorvusPay Client.
	 */
	private $client;

	/**
	 * CorvusPay_API constructor.
	 *
	 * @param WC_Logger_CorvusPay          $log CorvusPay log.
	 * @param WC_Gateway_CorvusPay_Options $options CorvusPay Gateway options.
	 * @param \CorvusPay\CorvusPayClient $corvuspay_client CorvusPay client.
	 *
	 */
	public function __construct( $log, $options, $corvuspay_client = null ) {
		$this->log     = $log;
		$this->options = $options;
		$this->client  = $corvuspay_client;

		try {
			if ( strpos( curl_version()['ssl_version'], 'NSS/' ) !== false || strpos( curl_version()['ssl_version'], 'GnuTLS' ) !== false ) {
				$this->client->setCertificateCrtAndKey( $this->options->certificate[ $this->options->environment . '_crt' ], $this->options->certificate[ $this->options->environment . '_key' ] );
			} else {
				$this->client->setCertificate( $this->options->certificate[ $this->options->environment ] );
			}
		} catch ( Exception $e ) {
			$this->log->error( $e->getMessage() );

			return;
		}
	}

	/**
	 * Queries CorvusPay API about order status.
	 *
	 * @param WC_Order_CorvusPay $order Order for status checking. Use 'api-status' type.
	 *
	 * @return mixed|null Server output.
	 */
	public function status( $order ) {
		$this->log->info( "Processing status query for Order with post_id: {$order->get_id()}, and order_number: {$order->get_order_number()}." );
		$server_output = $this->client->transaction->status( $order->get_parameters() );

		if ( false === $server_output ) {
			$this->log->warning( 'Status query failed. Query: ' . wp_json_encode( $order->get_parameters() ) );
		}
		$this->log->debug( 'Query: ' . wp_json_encode( $order->get_parameters() ) . PHP_EOL . 'CorvusPay returned:' . PHP_EOL . $server_output );

		return $server_output;
	}

	/**
	 * Refunds or voids order using CorvusPay API.
	 *
	 * @param WC_Order_CorvusPay $order Order to refund/void. Use 'api' type.
	 * @param null|float         $amount Amount to refund. Must be full amount for voids.
	 *
	 * @return bool|WP_Error Returns true on success, false on failure or WP_Error on error.
	 * @throws Exception
	 */
	public function refund( $order, $amount = null ) {
		$this->log->info( "Processing refund for Order #{$order->get_order_number()}." );

		$partial_refund = ! ( is_null( $amount ) || ( $order->get_remaining_refund_amount() == 0 ) );

		if ( 'auth' === $order->get_meta( '_corvuspay_action' ) ) {
			if ( $partial_refund ) {
				return new WP_Error( 'corvuspay_partial_cancel', __( 'CorvusPay doesn\'t support partial voids. Order can be partially captured or fully captured and then partially refunded.', 'corvuspay-woocommerce-integration' ) );
			} else {
				$server_output = $this->client->transaction->cancel( $order->get_parameters(), true );
				$cancel_request = true;
			}
		} else {
			if ( $partial_refund ) {
				$order->set_parameter_new_amount( $order->get_remaining_refund_amount() );
				$order->set_parameter_currency();
				$server_output = $this->client->transaction->partiallyRefund( $order->get_parameters(), true );
			} else {
				$server_output = $this->client->transaction->refund( $order->get_parameters(), true );
			}
		}

		$this->log->debug( 'Query: ' . wp_json_encode( $order->get_parameters() ) . PHP_EOL . 'CorvusPay returned:' . PHP_EOL . $server_output );

		if ( false === $server_output ) {
			$this->log->warning( 'Refund query failed. Query: ' . wp_json_encode( $order->get_parameters() ) );

			return new WP_Error( 'corvuspay_unexpected_response', __( 'An error occurred.', 'corvuspay-woocommerce-integration' ) );
		}

		$xml = simplexml_load_string( $server_output );
		if ( 'errors' === $xml->getName() ) {
			$this->log->warning( 'Refund error. CorvusPay returned:' . PHP_EOL . $server_output );

			/* translators: %s: Error description */
			return new WP_Error( 'corvuspay_api_error', sprintf( __( 'Encountered an error with description: "%s".', 'corvuspay-woocommerce-integration' ), $xml->{'description'} ) );
		}

		if ( 'voided' == $xml->{'status'} && $cancel_request ) {
			$order->update_meta_data( '_corvuspay_action', 'refunded_to_be_voided' );
			$order->save_meta_data();
		} else {
			$order->update_meta_data( '_corvuspay_action', 'refunded' );
			$order->save_meta_data();
		}
	
		if ( $partial_refund ) {
			return 'partially_refunded' == $xml->{'status'};
		} else {
			return 'refunded' == $xml->{'status'} || 'voided' == $xml->{'status'};
		}
	}

	/**
	 * Renews a subscription or uses a token to pay for an order using CorvusPay API.
     *
	 * @param WC_Order_CorvusPay $order Order to renew. Use 'api' type.
	 * @param float              $amount Amount to charge.
	 *
	 * @return bool|WP_Error Returns true on success, false on failure or WP_Error on error.
	 */
	public function renew( $order, $amount ) {
		$this->log->info( "Processing subscription for Order with order_number: {$order->get_order_number()}." );

		$token = (int) $order->get_meta( '_corvuspay_token_id' );
		$this->log->debug( 'Payment token #' . wp_json_encode( $token ) );

		$token = WC_Payment_Tokens::get( $token );
		if ( is_null( $token ) ) {
			$this->log->error( "Failed to get token for Order #{$order->get_order_number()}." );

			return new WP_Error( 'order_token_error', __( 'Failed to get token for order.', 'corvuspay-woocommerce-integration' ) );
		}

		$token = new WC_Payment_Token_CC_CorvusPay( $token );

		$order->set_tokenization( $token );
		$order->set_parameter_new_amount( $amount );
		$order->set_parameter_currency();
		$order->set_parameter_cart();

		$server_output = $this->client->subscription->pay( $order->get_parameters(), true );
		$this->log->debug( 'Query: ' . wp_json_encode( $order->get_parameters() ) . PHP_EOL . 'CorvusPay returned:' . PHP_EOL . $server_output );

		if ( false === $server_output ) {
			$this->log->warning( 'Renew query failed. Query: ' . wp_json_encode( $order->get_parameters() ) );

			return false;
		}

		$xml = simplexml_load_string( $server_output );
		if ( 'errors' === $xml->getName() ) {
			$this->log->warning( 'Renew error. CorvusPay returned:' . PHP_EOL . $server_output );

			/* translators: %s: Error description */
			return new WP_Error( 'corvuspay_api_error', sprintf( __( 'Encountered an error with description: "%s".', 'corvuspay-woocommerce-integration' ), $xml->{'description'} ) );
		}

		$is_authorized = 'authorized' == $xml->{'status'};
		if ( $is_authorized && isset( $xml->{'approval-code'} ) && $xml->{'approval-code'} != null ) {
			update_post_meta( $order->get_id(), '_corvuspay_approval_code', (string) $xml->{'approval-code'} );
			update_post_meta( $order->get_id(), '_corvuspay_transaction_date', current_time( "d.m.Y H:i:s" ) );
		}

		return $is_authorized;
	}

	/**
	 * Complete order using CorvusPay API.
	 *
	 * @param WC_Order_CorvusPay $order Order to complete. Use 'api' type.
	 * @param null|float $amount Amount to complete.
	 *
	 * @return bool|WP_Error Returns true on success, false on failure or WP_Error on error.
	 */
	public function complete( $order, $amount = null ) {
		$this->log->info( "Processing complete for Order #{$order->get_id()}." );

		$partial_complete = ! ( is_null( $amount ) || ( $order->get_total() == $amount ) );

		if ( 'auth' === $order->get_meta( '_corvuspay_action' ) ) {
			if ( $partial_complete ) {
				$order->set_parameter_new_amount( $amount );
				$order->set_parameter_currency();
				$server_output = $this->client->transaction->partiallyComplete( $order->get_parameters(), true );
			} else {
				$server_output = $this->client->transaction->complete( $order->get_parameters(), true );
			}
		} else {
			return new WP_Error( 'corvuspay_complete', __( 'Cannot complete order which is not authorized. ', 'corvuspay-woocommerce-integration' ) );
		}

		$this->log->debug( 'Query: ' . wp_json_encode( $order->get_parameters() ) . PHP_EOL . 'CorvusPay returned:' . PHP_EOL . $server_output );

		if ( false === $server_output ) {
			$this->log->warning( 'Capture query failed. Query: ' . wp_json_encode( $order->get_parameters() ) );

			return new WP_Error( 'corvuspay_unexpected_response', __( 'An error occurred.', 'corvuspay-woocommerce-integration' ) );
		}

		$xml = simplexml_load_string( $server_output );
		if ( 'errors' === $xml->getName() ) {
			$this->log->warning( 'Capture error. CorvusPay returned:' . PHP_EOL . $server_output );

			/* translators: %s: Error description */

			return new WP_Error( 'corvuspay_api_error', sprintf( __( 'Encountered an error with description: "%s".', 'corvuspay-woocommerce-integration' ), $xml->{'description'} ) );
		}

		if ( $partial_complete ) {
			return 'partially_completed' == $xml->{'status'};
		} else {
			return 'completed' == $xml->{'status'};
		}
	}
}

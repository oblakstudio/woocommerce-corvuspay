<?php
/**
 * Class WC_Payment_Token_CC_CorvusPay
 *
 * @package corvuspay-woocommerce-integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CorvusPay Credit Card Payment Token Class.
 */
class WC_Payment_Token_CC_CorvusPay extends WC_Payment_Token_CC {

	/**
	 * Fake constructor for CorvusPay Token.
	 *
	 * @param int    $gateway_id CorvusPay Gateway ID.
	 * @param int    $account_id Parameter account_id.
	 * @param string $card_type Card brand. One of 'amex'|'dina'|'diners'|'discover'|'maestro'|'master'|'visa'.
	 * @param string $last4 Last 4 PAN digits.
	 * @param string $expiry_month Expiration month.
	 * @param string $expiry_year Expiration year.
	 *
	 * @return int Token ID. Returns 0 on failure.
	 */
	public function new_corvuspay_token( $gateway_id, $account_id, $card_type, $last4, $expiry_month, $expiry_year ) {
		$token_array = array(
			'account_id'     => $account_id,
		);
		$this->set_token( wp_json_encode( $token_array ) );
		$this->set_gateway_id( $gateway_id );
		$this->set_user_id( get_current_user_id() );

		$this->set_card_type( $card_type );
		$this->set_last4( $last4 );
		$this->set_expiry_month( $expiry_month );
		$this->set_expiry_year( $expiry_year );

		return $this->save();
	}

	/**
	 * Parameter account_id getter.
	 *
	 * @return int Parameter account_id.
	 */
	public function get_account_id() {
		$token_array = json_decode( $this->get_token(), true );

		return (int) $token_array['account_id'];
	}
}

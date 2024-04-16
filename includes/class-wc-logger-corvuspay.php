<?php
/**
 * Class WC_Logger_CorvusPay
 *
 * @package corvuspay-woocommerce-integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CorvusPay WC_Logger Class.
 */
class WC_Logger_CorvusPay extends WC_Logger {

	/**
	 * Log context.
	 *
	 * @var array Log context.
	 */
	private $context;

	/**
	 * WC_Gateway_CorvusPay_Logger constructor.
	 *
	 * @param string $log_level One of 'emergency'|'alert'|'critical'|'error'|'warning'|'notice'|'info'|'debug'.
	 * @param string $id Context ID.
	 */
	public function __construct( $log_level, $id ) {
		parent::__construct( null, $log_level );
		$this->context = array( 'source' => $id );
	}

	/**
	 * Adds an emergency level message.
	 *
	 * @param string $message Message to log.
	 * @param null   $context Log context.
	 */
	public function emergency( $message, $context = null ) {
		parent::emergency( $message, is_null( $context ) ? $this->context : $context );
	}

	/**
	 * Adds an emergency level message.
	 *
	 * @param string $message Message to log.
	 * @param null   $context Log context.
	 */
	public function alert( $message, $context = null ) {
		parent::alert( $message, is_null( $context ) ? $this->context : $context );
	}

	/**
	 * Adds a critical level message.
	 *
	 * @param string $message Message to log.
	 * @param null   $context Log context.
	 */
	public function critical( $message, $context = null ) {
		parent::critical( $message, is_null( $context ) ? $this->context : $context );
	}

	/**
	 * Adds an error level message.
	 *
	 * @param string $message Message to log.
	 * @param null   $context Log context.
	 */
	public function error( $message, $context = null ) {
		parent::error( $message, is_null( $context ) ? $this->context : $context );
	}

	/**
	 * Adds a warning level message.
	 *
	 * @param string $message Message to log.
	 * @param null   $context Log context.
	 */
	public function warning( $message, $context = null ) {
		parent::warning( $message, is_null( $context ) ? $this->context : $context );
	}

	/**
	 * Adds a notice level message.
	 *
	 * @param string $message Message to log.
	 * @param null   $context Log context.
	 */
	public function notice( $message, $context = null ) {
		parent::notice( $message, is_null( $context ) ? $this->context : $context );
	}

	/**
	 * Adds an info level message.
	 *
	 * @param string $message Message to log.
	 * @param null   $context Log context.
	 */
	public function info( $message, $context = null ) {
		parent::info( $message, is_null( $context ) ? $this->context : $context );
	}

	/**
	 * Adds a debug level message.
	 *
	 * @param string $message Message to log.
	 * @param null   $context Log context.
	 */
	public function debug( $message, $context = null ) {
		parent::debug( $message, is_null( $context ) ? $this->context : $context );
	}
}

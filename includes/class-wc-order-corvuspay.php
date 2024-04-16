<?php
/**
 * WC_Order_CorvusPay
 *
 * @package corvuspay-woocommerce-integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once WP_PLUGIN_DIR . '/corvuspay-woocommerce-integration/vendor/autoload.php';
/**
 * Class WC_Order_CorvusPay
 */
class WC_Order_CorvusPay extends WC_Order {
	/**
	 * CorvusPay API version.
	 */
	const API_VERSION = \CorvusPay\BaseCorvusPayClient::API_VERSION;

	/**
	 * Delimiter for CorvusPay order_number. CorvusPay requires all test orders to have a unique order_number. Test
	 * orders have a prefix to make them unique. Delimiter is used to join and split prefix and Order ID.
	 */
	const ORDER_NUMBER_DELIMITER = ' - ';

	/**
	 * Maximum length for cart description. CorvusPay limits cart description length to 255 characters.
	 */
	const CART_MAX_LENGTH = 250;

    /**
     * List of languages supported by CorvusPay. ISO 639-1 codes.
     */
    const SUPPORTED_LANGUAGES = \CorvusPay\Service\CheckoutService::SUPPORTED_LANGUAGES;

    /**
     * Currency codes conversion. ISO 4217 codes.
     */
    const CURRENCY_CODES = \CorvusPay\Service\CheckoutService::CURRENCY_CODES;

    /**
     * Card brands.
     */
    const CARD_BRANDS = \CorvusPay\Service\CheckoutService::CARD_BRANDS;
	/**
	 * CorvusPay log.
	 *
	 * @var WC_Logger_CorvusPay CorvusPay log.
	 */
	private $log;

	/**
	 * CorvusPay options.
	 *
	 * @var WC_Gateway_CorvusPay_Options CorvusPay options.
	 */
	private $options;

	/**
	 * Array of CorvusPay parameters for order.
	 *
	 * @var array Array of CorvusPay parameters for order.
	 */
	private $parameters;

    /**
     * CorvusPay Client.
     *
     * @var CorvusPay\CorvusPayClient CorvusPay Client.
     */
    private $client;

	/**
	 * WC_Order_CorvusPay constructor. Constructs new order form Order ID or POST
	 *
	 * @param WC_Logger_CorvusPay          $log CorvusPay log.
	 * @param WC_Gateway_CorvusPay_Options $options CorvusPay Gateway options.
	 * @param string                       $type Type of order. One of 'cardholder'|'callback'|'callback-signed'|'api'|'api-status'.
	 * @param null|int|WC_Order            $order Order ID if known.
	 *
	 * @throws Exception Errors in constructor.
	 */
	public function __construct( $log, $options, $type, $order = null ) {
		$this->log     = $log;
		$this->options = $options;

        if ( 'cardholder' === $type ) {
			/* New Order from cardholder */
			parent::__construct( $order );

	        /* Create CorvusPay Client */
	        $config_params = ['store_id' => $this->get_store_id(), 'secret_key' => $this->get_secret_key(), 'environment' => $this->options->environment, 'logger' => $this->log];
	        $this->client = new CorvusPay\CorvusPayClient( $config_params );

	        /* Mandatory fields */
	        $this->set_parameter_version();
	        $this->generate_and_set_parameter_order_number();
	        $this->set_parameter_language();
	        $this->set_parameter_currency();
			$this->set_parameter_amount();
			$this->set_parameter_cart();
			$this->set_parameter_require_complete();

			/* Optional fields */
			if ( 'mandatory' === $this->options->cardholder_fields ) {
				$this->set_parameter_cardholder_name();
				$this->set_parameter_cardholder_surname();
				$this->set_parameter_cardholder_email();
			} elseif ( 'all' === $this->options->cardholder_fields ) {
				$this->set_parameter_cardholder_name();
				$this->set_parameter_cardholder_surname();
				$this->set_parameter_cardholder_email();
				$this->set_parameter_cardholder_address();
				$this->set_parameter_cardholder_city();
				$this->set_parameter_cardholder_zip_code();
				$this->set_parameter_cardholder_country();
				$this->set_parameter_cardholder_phone();
			}

			if ( ! is_null( $this->options->time_limit ) ) {
				$this->set_parameter_best_before();
			}

            if ( ! is_null( $this->options->hide_tabs ) ) {
                $this->set_parameter_hide_tabs();
            }

			$token = $this->get_meta( '_corvuspay_token' );
			if ( 'new' === $token ) {
				/* Subscriptions */
				$this->set_parameter_subscription();
                /* Installments */
                if ( 'all' === $this->options->installments ) {
                    $this->set_parameter_payment_all();
                } elseif ( 'map' === $this->options->installments ) {
                    $this->set_parameter_installments_map();
                }
			} elseif ( 'add' === $token ) {
                /* Subscriptions */
                $this->set_parameter_subscription();
			} else {
                /* Installments */
                if ( 'all' === $this->options->installments ) {
                    $this->set_parameter_payment_all();
                } elseif ( 'map' === $this->options->installments ) {
                    $this->set_parameter_installments_map();
                }
            }

			if ( ! is_null( $this->options->creditor_reference ) ) {
				$this->set_parameter_creditor_reference();
			}

	        $this->parameters = apply_filters( 'corvuspay_modify_order_parameters', $this->parameters, $this );

        } elseif ( 'callback' === $type || 'callback-signed' === $type ) {
			/* Existing order from POST */
			if ( ! is_null( $order ) ) {
				throw new Exception( 'WC_Order_CorvusPay expects $order to be null when $type is set to \'cardholder\'.' );
			}

	        /* Create CorvusPay Client */
	        $config_params = ['store_id' => $this->get_store_id(), 'secret_key' => $this->get_secret_key(),
	                          'environment' => $this->options->environment, 'logger' => $this->log, 'version' => '1.4'];
	        $this->client = new CorvusPay\CorvusPayClient( $config_params );

	        // TODO add filter
	        $this->parameters = filter_input_array( INPUT_POST );

	        $post_id = $this->get_post_by_corvuspay_order_number( $this->parameters['order_number'] );

	        if ( null === $post_id ) {
		        $this->log->error( 'The parameter $post_id is null. There is no post with 
		        _corvuspay_order_number equals ' . $this->parameters['order_number'] );
	        }

	        parent::__construct( $post_id );
			
			if ( 'callback-signed' === $type ) {
				if ( isset( $this->parameters["approval_code"] ) && $this->parameters["approval_code"] != null ) {
					update_post_meta( $this->get_id(), '_corvuspay_approval_code', $this->parameters["approval_code"] );
					update_post_meta( $this->get_id(), '_corvuspay_transaction_date', current_time( "d.m.Y H:i:s" ) );
				}
                $res = $this->client->validate->signature( $this->parameters );
                $this->log->debug( 'Result from signature validation: ' . $res );
			}
		} elseif ( 'api' === $type || 'api-status' === $type || 'api-renew' === $type ) {
	        /* Existing order for API */
	        parent::__construct( $order );

	        /* Create CorvusPay Client */
	        $config_params = ['store_id' => $this->get_store_id(), 'secret_key' => $this->get_secret_key(), 'environment' => $this->options->environment, 'logger' => $this->log];
	        $this->client = new CorvusPay\CorvusPayClient( $config_params );

	        /* Mandatory fields */
	        if ( 'api-renew' === $type ) {
		        $this->generate_and_set_parameter_order_number();
	        } else {
		        $this->set_parameter_order_number();
	        }

	        if ( 'api-status' === $type ) {
		        $this->set_parameter_currency_code();
		        $this->set_parameter_timestamp();
		        $this->set_parameter_version();
	        }
        } else {
	        throw new Exception( 'Unsupported order type.' );
        }
	}

	/**
	 * Returns array of parameter => value for CorvusPay payment form.
	 *
	 * @return array Parameters
	 */
	public function get_parameters() {
		return $this->parameters;
	}

    /**
     * Returns CorvusPay client.
     *
     * @return \CorvusPay\CorvusPayClient CorvusPay client.
     */
    public function get_client() {
        return $this->client;
    }

	/**
	 * Determines Store ID and Secret Key based on Order and Options.
	 *
	 * @return array Store ID ('store_id') and Secret Key ('secret_key').
	 * @throws Exception Unsupported currency.
	 */
	private function get_settings() {
		foreach ( $this->options->stores_settings[ $this->options->environment ] as $settings ) {
			if ( in_array( $this->get_currency(), $settings['currency'], true ) ) {
				return array(
					'store_id'   => $settings['store_id'],
					'secret_key' => $settings['secret_key'],
				);
			}
		}
		foreach ( $this->options->stores_settings[ $this->options->environment ] as $settings ) {
			if ( in_array( '*', $settings['currency'], true ) ) {
				return array(
					'store_id'   => $settings['store_id'],
					'secret_key' => $settings['secret_key'],
				);
			}
		}
		throw new Exception( esc_html__( 'Unsupported currency.', 'corvuspay-woocommerce-integration' ) );
	}

	/**
	 * Determines Store ID and Secret Key based on Order and Options.
	 *
	 * @return array Store ID ('store_id') and Secret Key ('secret_key').
	 * @throws Exception Unsupported currency.
	 */
	private function get_settings_before_order_is_created() {
		foreach ( $this->options->stores_settings[ $this->options->environment ] as $settings ) {
			if ( in_array( get_woocommerce_currency(), $settings['currency'], true ) ) {
				return array(
					'store_id'   => $settings['store_id'],
					'secret_key' => $settings['secret_key'],
				);
			}
		}
		foreach ( $this->options->stores_settings[ $this->options->environment ] as $settings ) {
			if ( in_array( '*', $settings['currency'], true ) ) {
				return array(
					'store_id'   => $settings['store_id'],
					'secret_key' => $settings['secret_key'],
				);
			}
		}
		throw new Exception( esc_html__( 'Unsupported currency.', 'corvuspay-woocommerce-integration' ) );
	}

	/**
	 * Determines Store ID based on Order and Options.
	 *
	 * @return string store_id
	 * @throws Exception Unsupported currency.
	 */
	private function get_store_id() {
		if ( $this->options->currency_routing && $this->get_currency() !== "") {
			return $this->get_settings()['store_id'];
		} elseif ($this->options->currency_routing && $this->get_currency() === "") {
			return $this->get_settings_before_order_is_created()['store_id'];
		} else {
			return $this->options->store_id[ $this->options->environment ];
		}
	}

	/**
	 * Determines Secret Key based on Order and Options.
	 *
	 * @return string secret_key
	 * @throws Exception Unsupported currency.
	 */
	private function get_secret_key() {
		if ( $this->options->currency_routing && $this->get_currency() !== "" ) {
			return $this->get_settings()['secret_key'];
		} elseif ($this->options->currency_routing && $this->get_currency() === "") {
			return $this->get_settings_before_order_is_created()['secret_key'];
		} else {
			return $this->options->secret_key[ $this->options->environment ];
		}
	}

    /**
     * Get Order number format.
     *
     * @return string order_number_format
     */
    private function get_order_number_format() {
        return $this->options->order_number_format[ $this->options->environment ];
    }

	/**
	 * Converts CorvusPay order_number to WooCommerce post_id.
	 *
	 * @return int WooCommerce post_id
	 */
	private function parse_parameter_order_number() {
		$exploded = explode( self::ORDER_NUMBER_DELIMITER, $this->parameters['order_number'] );

		return (int) end( $exploded );
	}

	/**
	 * Sets 'version' parameter. Fixed value.
	 */
	private function set_parameter_version() {
		$this->parameters['version'] = self::API_VERSION;
	}

	/**
	 * Generate Order number from Order number format and set to parameters.
	 *
	 * @return string order_number.
	 */
	private function generate_and_set_parameter_order_number() {
		/* We here flush cache because of the plugin "Sequential Order Number for WooCommerce" */
		wp_cache_flush();
		$corvuspay_order_number = get_post_meta( $this->get_id(), '_corvuspay_order_number', true );
		$order_number_base = get_post_meta( $this->get_id(), '_order_number', true );
		$this->log->debug( "Entered generate_and_set_parameter_order_number, _corvuspay_order_number is {$corvuspay_order_number} and _order_number is {$order_number_base}" );

		if ( $corvuspay_order_number ) {
			/* If somehow the value of _corvuspay_order_number is duplicate, then regenerate the order_number.
			Cannot use get_posts() or WP_Query because 'post_type' => 'shop_order' is not public.*/

			global $wpdb;
			$posts = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}postmeta WHERE meta_key = '_corvuspay_order_number' AND meta_value = '{$corvuspay_order_number}'");//

			if ( count( $posts ) === 0 || count( $posts ) === 1 && (int)$posts[0]->post_id === $this->get_id()) {
				$this->parameters['order_number'] = $corvuspay_order_number;
			} else {
				$this->log->debug("Found duplicate _corvuspay_order_number!");
				$this->parameters['order_number'] = $this->generate_order_number($order_number_base);
			}

		} else {
			$this->parameters['order_number'] = $this->generate_order_number($order_number_base);
		}
	}

	/**
	 * Sets 'order_number' parameter.
	 *
	 * @param string $order_number
	 */
	private function set_parameter_order_number() {
		/* We here flush cache because of the plugin "Sequential Order Number for WooCommerce" */
		wp_cache_flush();

		$corvuspay_order_number = get_post_meta( $this->get_id(), '_corvuspay_order_number', true );
		$this->log->debug( "Entered set_parameter_order_number, _corvuspay_order_number is {$corvuspay_order_number}." );

		if ( $corvuspay_order_number ) {
			$this->parameters['order_number'] = $corvuspay_order_number;
		} else {
			$this->parameters['order_number'] =
				$this->options->production ? $this->get_id() : html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . self::ORDER_NUMBER_DELIMITER . $this->get_id();
		}
	}

	/**
	 * Generate 'order_number' parameter.
	 *
	 * @param $order_number_base
	 *
	 * @return string $order_number
	 */
	private function generate_order_number($order_number_base) {
		if ( ! $order_number_base ) {
			$order_number_base = $this->get_id();
			$this->log->debug("The order_number_base is blank so use post_id.");
		}
		$order_number = strtr(
			$this->get_order_number_format(),
			array(
				'{site_title}'   => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'{post_id}'      => $this->get_id(),
				'{order_number}' => $order_number_base
			)
		);
		update_post_meta( $this->get_id(), '_corvuspay_order_number', $order_number );
		update_post_meta( $this->get_id(), '_order_number', $order_number );
		return $order_number;
	}
	/**
	 * Sets 'language' parameter. Tries to guess language if set to 'Auto'. Does a sanity check. Falls back to 'en'.
	 */
	private function set_parameter_language() {
		$language = $this->options->language;
		if ( 'auto' === $language ) {
			$language = explode( '-', get_bloginfo( 'language' ), 2 )[0];
		}

		$this->parameters['language'] = array_key_exists( $language, self::SUPPORTED_LANGUAGES ) ? $language : 'en';
	}

	/**
	 * Sets 'currency' parameter.
	 *
	 * @throws Exception Unsupported currency.
	 */
	public function set_parameter_currency() {
		if ( ! array_key_exists( $this->get_currency(), self::CURRENCY_CODES ) ) {
			throw new Exception( esc_html__( 'Unsupported currency.', 'corvuspay-woocommerce-integration' ) );
		}
		$this->parameters['currency'] = $this->get_currency();
	}

	/**
	 * Sets 'currency_code' parameter.
	 *
	 * @throws Exception Unsupported currency.
	 */
	private function set_parameter_currency_code() {
		if ( ! array_key_exists( $this->get_currency(), self::CURRENCY_CODES ) ||
			is_null( self::CURRENCY_CODES[ $this->get_currency() ] ) ) {
			$this->log->error( "Currency {$this->get_currency()} is not supported." );

			throw new Exception( esc_html__( 'Unsupported currency.', 'corvuspay-woocommerce-integration' ) );
		}
		$this->parameters['currency_code'] = $this->get_currency();
	}

	/**
	 * Sets 'timestamp' parameter.
	 */
	private function set_parameter_timestamp() {
		$this->parameters['timestamp'] = date( 'YmdHis' );
	}

	/**
	 * Sets 'additional_order_number' parameter.
	 */
	private function set_additional_order_number() {
		$this->parameters['additional_order_number'] = $this->get_id();
	}

	/**
	 * Sets 'new_amount' parameter.
	 *
	 * @param float $amount Amount.
	 */
	public function set_parameter_new_amount( $amount ) {
		$this->parameters['new_amount'] = $amount;
	}

	/**
	 * Sets parameters for tokenization/subscriptions.
	 *
	 * @param WC_Payment_Token_CC_CorvusPay $token Token.
	 */
	public function set_tokenization( $token ) {
        $this->set_parameter_subscription();
        $this->set_parameter_account_id( $token->get_account_id() );
        $this->set_parameter_version();
	}

	/**
	 * Sets 'amount' parameter.
	 *
	 * @throws Exception Invalid amount.
	 */
	private function set_parameter_amount() {
		$total = (float) $this->get_total();
		if ( $total <= 0 || round( $total, get_option( 'woocommerce_price_num_decimals', 2 ) ) !== $total ) {
			throw new Exception( esc_html__( 'Invalid amount.', 'corvuspay-woocommerce-integration' ) );
		}
		if ( 'add' === $this->get_meta( '_corvuspay_token' ) && $this->get_currency() === 'EUR') {
			$total = 0.1;
		}
		$this->parameters['amount'] = $this->round_amount( $total );
	}

	/**
	 * Rounds amounts.
	 *
	 * @param float $amount Amount to round.
	 *
	 * @return string Rounded amount.
	 */
	private function round_amount( $amount ) {
		return number_format( $amount, get_option( 'woocommerce_price_num_decimals', 2 ), '.', '' );
	}

	/**
	 * Sets 'cart' parameter. Doesn't do a sanity check.
	 */
	public function set_parameter_cart() {
		if ( 'add' === $this->get_meta( '_corvuspay_token' ) ) {
			$cart = __( 'Card storage', 'corvuspay-woocommerce-integration' );
		} else {
			$items = array();

			foreach ( $this->get_items() as $item ) {
				$items[] = $item['name'] . ' × ' . $item['qty'];
			}

			$cart = implode( ', ', $items );
		}
		$ellipsis = '...';

		if ( mb_strlen( $cart ) > self::CART_MAX_LENGTH ) {
			if ( function_exists( 'mb_strimwidth' ) ) {
				$this->parameters['cart'] = mb_strimwidth( $cart, 0, self::CART_MAX_LENGTH, $ellipsis );
			} else {
				$this->parameters['cart'] = mb_substr( $cart, 0, self::CART_MAX_LENGTH - strlen( $ellipsis ) ) . $ellipsis;
			}
		} else {
			$this->parameters['cart'] = $cart;
		}
	}

	/**
	 * Sets 'require_complete' parameter.
	 */
	private function set_parameter_require_complete() {
		if ( 'add' === $this->get_meta( '_corvuspay_token' ) ) {
			$this->parameters['require_complete'] = 'true';
		} else {
			$this->parameters['require_complete'] = $this->options->preauth ? 'true' : 'false';
		}
		$this->update_meta_data( '_corvuspay_action', 'true' === $this->parameters['require_complete'] ? 'auth' : 'sale' );
		$this->save_meta_data();
		$this->log->debug( '_corvuspay_action: ' . $this->get_meta( '_corvuspay_action' ) );
	}

	/**
	 * Sets 'best_before' parameter.
	 *
	 * @throws Exception Unsupported time limit value. Valid range is from 1 to 900.
	 */
	private function set_parameter_best_before() {
		if ( $this->options->time_limit > 900 ) {
			throw new Exception( esc_html__( 'Unsupported time limit value. Valid range is from 1 to 900.', 'corvuspay-woocommerce-integration' ) );
		}
		$this->parameters['best_before'] = time() + $this->options->time_limit;
	}

	/**
	 * Sets 'subscription' parameter to 'true'.
	 */
	private function set_parameter_subscription() {
		$this->parameters['subscription'] = 'true';
	}

	/**
	 * Sets 'account_id' parameter.
	 *
	 * @param int $account_id Account ID.
	 */
	private function set_parameter_account_id( $account_id ) {
		$this->parameters['account_id'] = $account_id;
	}

	/**
	 * Sets the 'creditor_reference' parameter.
	 */
	private function set_parameter_creditor_reference() {
		$order_number_base = get_post_meta( $this->get_id(), '_order_number', true );
		if ( ! $order_number_base ) {
			$order_number_base = $this->get_id();
		}
		$this->parameters['creditor_reference'] = strtr(
			$this->options->creditor_reference,
			array(
				'{post_id}'      => $this->get_id(),
				'{order_number}' => $order_number_base
			)
		);
	}

	/**
	 * Sets 'payment_all' parameter.
	 */
	private function set_parameter_payment_all() {
		$this->parameters['payment_all'] = 'Y0299';
	}

	/**
	 * Sets 'installments_map' parameter. Doesn't do a sanity check.
	 */
	private function set_parameter_installments_map() {
		$amount           = (float) $this->get_total();
		$installments_map = array();

		foreach ( $this->options->installments_map as $installment ) {
			for ( $installments = (int) $installment['min_installments']; $installments <= (int) $installment['max_installments']; $installments ++ ) {
				if ( '' !== $installment['general_percentage'] ) {
					$installments_map[ $installment['card_brand'] ][ $installments ]['amount'] =
						$this->round_amount( $amount * ( 100 - (float) $installment['general_percentage'] ) / 100 );
				}

				if ( '' !== $installment['specific_percentage'] ) {
					$installments_map[ $installment['card_brand'] ][ $installments ]['discounted_amount'] =
						$this->round_amount( $amount * ( 100 - (float) $installment['specific_percentage'] ) / 100 );
				}
			}
		}

		$this->log->debug( 'installments_map: ' . wp_json_encode( $installments_map, JSON_FORCE_OBJECT ) );
		$this->parameters['installments_map'] = wp_json_encode( $installments_map, JSON_FORCE_OBJECT );
	}

	/**
	 * Sets 'cardholder_name' parameter.
	 */
	private function set_parameter_cardholder_name() {
		$this->parameters['cardholder_name'] = $this->get_billing_first_name();
	}

	/**
	 * Sets 'cardholder_surname' parameter.
	 */
	private function set_parameter_cardholder_surname() {
		$this->parameters['cardholder_surname'] = $this->get_billing_last_name();
	}

	/**
	 * Sets 'cardholder_email' parameter.
	 */
	private function set_parameter_cardholder_email() {
		$this->parameters['cardholder_email'] = $this->get_billing_email();
	}

	/**
	 * Sets 'cardholder_address' parameter.
	 */
	private function set_parameter_cardholder_address() {
		$this->parameters['cardholder_address'] = $this->get_billing_address_1();
	}

	/**
	 * Sets 'cardholder_city' parameter.
	 */
	private function set_parameter_cardholder_city() {
		$this->parameters['cardholder_city'] = $this->get_billing_city();
	}

	/**
	 * Sets 'cardholder_zip_code' parameter.
	 */
	private function set_parameter_cardholder_zip_code() {
		$this->parameters['cardholder_zip_code'] = $this->get_billing_postcode();
	}

	/**
	 * Sets 'cardholder_country' parameter.
	 */
	private function set_parameter_cardholder_country() {
		$this->parameters['cardholder_country'] = $this->get_billing_country();
	}

	/**
	 * Sets 'cardholder_phone' parameter.
	 */
	private function set_parameter_cardholder_phone() {
		$this->parameters['cardholder_phone'] = $this->get_billing_phone();
	}

    /**
     * Sets 'hide_tabs' parameter.
     */
    private function set_parameter_hide_tabs() {
        $this->parameters['hide_tabs'] = implode(",", $this->options->hide_tabs);
    }

    /**
     * Get post by meta value when meta key is "_corvuspay_order_number".
     *
     * @param string $corvuspay_order_number
     * 
     * @return int|null id of last post with '_corvuspay_order_number' == $corvuspay_order_number or null if not exists.
     */
	private function get_post_by_corvuspay_order_number( $corvuspay_order_number ) {
		$cc_args  = array(
			'post_type'   => 'shop_order',
			'post_status' => array_values( get_post_stati( [ 'exclude_from_search' => false ] ) ),
			'meta_key'    => '_corvuspay_order_number',
			'meta_value'  => $corvuspay_order_number
		);
		$cc_query = get_posts( $cc_args );

		$last_post_id = count( $cc_query ) ? $cc_query[ count( $cc_query ) - 1 ]->ID : null;

		return $last_post_id;
	}
}

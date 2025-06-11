<?php

/**
 * Handling Novalnet validation / process functions
 *
 * @class    WC_Novalnet_Helper
 * @package  woocommerce-novalnet-gateway/includes/
 * @category Class
 * @author   Novalnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Automattic\WooCommerce\Utilities\OrderUtil;
use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;

/**
 * WC_Novalnet_Helper Class.
 */
class WC_Novalnet_Helper {


	/**
	 * Payport Endpoint URL.
	 *
	 * @var string
	 */
	private $endpoint = 'https://payport.novalnet.de/v2/';

	/**
	 * Status mapper.
	 *
	 * @var array
	 */
	public $statuses = array(
		'ON_HOLD'     => array( '85', '91', '98', '99', '84' ),
		'CONFIRMED'   => array( '100' ),
		'PENDING'     => array( '90', '80', '86', '83', '75' ),
		'DEACTIVATED' => array( '103' ),
	);

	/**
	 * List of BIC allowed countries.
	 *
	 * @var array $bic_allowed_countries The single instance of the class.
	 * @since 12.5.6
	 */
	private $bic_allowed_countries = array( 'CH', 'MC', 'SM', 'GB', 'GI' );

	/**
	 * The single instance of the class.
	 *
	 * @var   Novalnet_Helper The single instance of the class.
	 * @since 12.0.0
	 */
	protected static $instance = null;

	/**
	 * Main Novalnet_Helper Instance.
	 *
	 * Ensures only one instance of Novalnet_Helper is loaded or can be loaded.
	 *
	 * @since  12.0.0
	 * @static
	 * @return Novalnet_Api_Callback Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Submit the given request and convert the
	 * query string to array.
	 *
	 * @since 12.0.0
	 *
	 * @param array  $request The request data.
	 * @param string $url     The request url.
	 * @param array  $args    Arguments.
	 *
	 * @return array
	 */
	public function submit_request( $request, $url, $args = array() ) {

		$args = wp_parse_args(
			$args,
			array(
				'access_key' => '',
				'post_id'    => '',
			)
		);

		$override_log_setting = ( ! empty( $args['is_scheduled_payment'] ) && true === $args['is_scheduled_payment'] ) ? true : false;

		// Perform server call and format the response.
		if ( empty( $args['access_key'] ) ) {
			$args['access_key'] = WC_Novalnet_Configuration::get_global_settings( 'key_password' );
		}

		if ( ! empty( $args['access_key'] ) ) {

			// Form headers.
			$headers = array(
				'Content-Type'    => 'application/json',
				'charset'         => 'utf-8',
				'Accept'          => 'application/json',
				'X-NN-Access-Key' => base64_encode($args['access_key']), // phpcs:ignore.
			);

			$json_request = wc_novalnet_serialize_data( $request );

			if ( false === strpos( $url, 'merchant/details' ) ) {
				if ( isset( $request['transaction']['payment_data'] ) ) {
					unset( $request['transaction']['payment_data'] );
				}
				$request_log = wc_novalnet_serialize_data( $request );

				$this->debug( "REQUEST: {$url} - $request_log", $args['post_id'], $override_log_setting );
			}
			// Post the values to the paygate URL.
			$response = wp_remote_post(
				$url,
				array(
					'method'  => 'POST',
					'headers' => $headers,
					'timeout' => 240,
					'body'    => $json_request,
				)
			);

			// Log and return error.
			if ( is_wp_error( $response ) ) {

				// Log error.
				$this->log_error( "API call ($url) failed due to the connection error: " . $response->get_error_message(), $args['post_id'] );

				return array(
					'result' => array(
						'status'      => 'FAILURE',
						'status_code' => '106',
						'status_text' => $response->get_error_message(),
					),
				);
			} elseif ( ! empty( $response['body'] ) ) {

				if ( false === strpos( $url, 'merchant/details' ) ) {
					$res_data = wc_novalnet_unserialize_data( $response['body'] );
					if ( isset( $res_data['transaction']['payment_data'] ) ) {
						unset( $res_data['transaction']['payment_data'] );
					}
					$response_log = wc_novalnet_serialize_data( $res_data );

					$this->debug( "RESPONSE: {$url} - " . $response_log, $args['post_id'], $override_log_setting );
				}
				return wc_novalnet_unserialize_data( $response['body'] );
			}
		}
		return array(
			'result' => array(
				'status_code' => '106',
				'status'      => 'FAILURE',
				'status_text' => __( 'Please enter the required fields under Novalnet API Configuration', 'woocommerce-novalnet-gateway' ),
			),
		);
	}

	/**
	 * Prepare the Novalnet transaction comments.
	 *
	 * @since 12.0.0
	 * @param array $data The data.
	 * @param array $wc_order The order object.
	 * @return array
	 */
	public function prepare_payment_comments( $data, $wc_order = false ) {

		// Forming basic comments.
		$comments = $this->form_comments( $data );
		if ( 'PENDING' === $data['transaction']['status'] && in_array( $data['transaction']['payment_type'], array( 'GUARANTEED_INVOICE', 'INSTALMENT_INVOICE' ), true ) ) {
			$comments .= __( '\n\nYour order is being verified. Once confirmed, we will send you our bank details to which the order amount should be transferred. Please note that this may take up to 24 hours', 'woocommerce-novalnet-gateway' );
		} elseif ( 'PENDING' === $data['transaction']['status'] && in_array( $data['transaction']['payment_type'], array( 'GUARANTEED_DIRECT_DEBIT_SEPA', 'INSTALMENT_DIRECT_DEBIT_SEPA' ), true ) ) {
			$comments .= __( '\n\nYour order is under verification and we will soon update you with the order status. Please note that this may take upto 24 hours.', 'woocommerce-novalnet-gateway' );
		} elseif ( ! empty( $data['transaction']['bank_details'] ) && ! empty( $data['transaction']['amount'] ) && empty( $data['instalment']['prepaid'] ) ) {
			$comments .= $this->form_amount_transfer_comments( $data, $wc_order );
		} elseif ( ! empty( $data['transaction']['nearest_stores'] ) ) {

			$comments .= $this->form_nearest_store_comments( $data );
		} elseif ( ! empty( $data['transaction']['partner_payment_reference'] ) ) {

			/* translators: %s: amount */
			$comments .= sprintf( __( '\nPlease use the following payment reference details to pay the amount of %s at a Multibanco ATM or through your internet banking.', 'woocommerce-novalnet-gateway' ), wc_novalnet_shop_amount_format( $data['transaction']['amount'] ) );

			/* translators: %s: partner_payment_reference */
			$comments .= sprintf( __( '\nPayment Reference : %s', 'woocommerce-novalnet-gateway' ), $data['transaction']['partner_payment_reference'] );

			if ( ! empty( $data['transaction']['service_supplier_id'] ) ) {
				/* translators: %s: service_supplier_id */
				$comments .= sprintf( __( '\nEntity : %s \n', 'woocommerce-novalnet-gateway' ), $data['transaction']['service_supplier_id'] );
			}
		}

		$comments = str_replace( '\n', PHP_EOL, sanitize_textarea_field( $comments ) );

		return $comments;
	}

	/**
	 * Form payment comments.
	 *
	 * @since 12.0.0
	 * @param array   $data The comment data.
	 * @param boolean $is_error The error.
	 *
	 * @return string
	 */
	public function form_comments( $data, $is_error = false ) {

		$comments = '';

		if ( WC_Novalnet_Validation::is_success_status( $data ) && in_array( $data['transaction']['payment_type'], array( 'GOOGLEPAY', 'APPLEPAY' ), true ) ) {
			$payment = ( 'GOOGLEPAY' === $data['transaction']['payment_type'] ) ? 'Google Pay' : 'Apple Pay';
			/* translators: %1$s: brand, %2$s: last four */
			$card_mask = ( isset( $data['transaction']['payment_data']['card_brand'] ) ) ? sprintf(
				'(%1$s ****%2$s)',
				strtolower(
					$data['transaction']['payment_data']['card_brand']
				),
				$data['transaction']['payment_data']['last_four']
			) : '';
			/* translators: %1$s: payment, %2$s: brand, %2$s: last four */
			$comments .= sprintf( __( 'Your order was successfully processed using %1$s %2$s', 'woocommerce-novalnet-gateway\n' ), $payment, $card_mask );
		}

		if ( ! empty( $data['transaction']['tid'] ) ) {

			/* translators: %s: TID */
			$comments .= sprintf( __( 'Novalnet transaction ID: %s ', 'woocommerce-novalnet-gateway' ), $data['transaction']['tid'] );
			if ( ! empty( $data['transaction']['test_mode'] ) ) {
				$comments .= __( ' Test order ', 'woocommerce-novalnet-gateway' );
			}
		}
		if ( $is_error ) {
			$comments .= wc_novalnet_response_text( $data );
		}
		return $comments;
	}

	/**
	 * Form payment comments.
	 *
	 * @since 12.0.0
	 * @param array $data The comment data.
	 *
	 * @return string
	 */
	public function format_querystring_response( $data ) {
		foreach (
			array(
				'tid'          => 'transaction',
				'payment_type' => 'transaction',
				'status'       => 'result',
				'status_text'  => 'result',
			) as $parameter => $category
		) {
			if ( ! empty( $data[ $parameter ] ) ) {
				$data[ $category ][ $parameter ] = $data[ $parameter ];
			}
		}
		return $data;
	}

	/**
	 * Form payment comments.
	 *
	 * @since 12.0.0
	 * @param array $data The comment data.
	 *
	 * @return string
	 */
	public function form_nearest_store_comments( $data ) {

		$nearest_stores = $data['transaction']['nearest_stores'];
		$comments       = '';

		if ( ! empty( $data['transaction']['due_date'] ) ) {
			/* translators: %s: due_date */
			$comments .= sprintf( __( '\nSlip expiry date : %s', 'woocommerce-novalnet-gateway' ), wc_novalnet_formatted_date( $data['transaction']['due_date'] ) );
		}
		$comments .= __( '\n\nStore(s) near to you: \n\n', 'woocommerce-novalnet-gateway' );

		foreach ( $nearest_stores as $nearest_store ) {
			$address = array();
			foreach (
				array(
					'store_name'   => 'company',
					'street'       => 'address_1',
					'city'         => 'city',
					'zip'          => 'postcode',
					'country_code' => 'country',
				) as $nn_key => $wc_key
			) {
				if ( ! empty( $nearest_store[ $nn_key ] ) ) {
					$address[ $wc_key ] = $nearest_store[ $nn_key ];
				}
			}
			$comments .= WC()->countries->get_formatted_address( $address, PHP_EOL );
			$comments .= '\n\n';
		}
		return $comments;
	}

	/**
	 * Form Bank details comments.
	 *
	 * @since 12.0.0
	 * @param array $input     The input data.
	 * @param int   $wc_order_id The order id.
	 *
	 * @return string
	 */
	public function form_amount_transfer_comments( $input, $wc_order_id = false ) {

		$order_amount = $input['transaction']['amount'];
		if ( ! empty( $input['instalment']['cycle_amount'] ) ) {
			$order_amount = $input['instalment']['cycle_amount'];
		}
		if ( in_array( $input['transaction']['status'], array( 'CONFIRMED', 'PENDING' ), true ) && ! empty( $input['transaction']['due_date'] ) ) {
			/* translators: %1$s: amount, %2$s: due date */
			$comments = sprintf( __( '\n \n Please transfer the amount of %1$s to the following account on or before %2$s \n\n', 'woocommerce-novalnet-gateway' ), wc_novalnet_shop_amount_format( $order_amount ), wc_novalnet_formatted_date( $input['transaction']['due_date'] ) );

			if ( ! empty( $input['instalment']['cycle_amount'] ) ) {
				/* translators: %1$s: amount, %2$s: due date */
				$comments = sprintf( __( '\n \n Please transfer the instalment cycle amount of %1$s to the following account on or before %2$s \n \n', 'woocommerce-novalnet-gateway' ), wc_novalnet_shop_amount_format( $order_amount ), wc_novalnet_formatted_date( $input['transaction']['due_date'] ) );
			}
		} else {
			/* translators: %s: amount*/
			$comments = sprintf( __( ' \n \n Please transfer the amount of %1$s to the following account. \n\n', 'woocommerce-novalnet-gateway' ), wc_novalnet_shop_amount_format( $order_amount ) );

			if ( ! empty( $input['instalment']['cycle_amount'] ) ) {
				/* translators: %s: amount*/
				$comments = sprintf( __( ' \n \n Please transfer the instalment cycle amount of %1$s to the following account. \n \n', 'woocommerce-novalnet-gateway' ), wc_novalnet_shop_amount_format( $order_amount ) );
			}
		}

		foreach (
			array(
				/* translators: %s: account_holder */
				'account_holder' => __( 'Account holder: %s \n', 'woocommerce-novalnet-gateway' ),

				/* translators: %s: bank_name */
				'bank_name'      => __( 'Bank: %s \n', 'woocommerce-novalnet-gateway' ),

				/* translators: %s: bank_place */
				'bank_place'     => __( 'Place: %s \n', 'woocommerce-novalnet-gateway' ),

				/* translators: %s: iban */
				'iban'           => __( 'IBAN: %s \n', 'woocommerce-novalnet-gateway' ),

				/* translators: %s: bic */
				'bic'            => __( 'BIC: %s \n', 'woocommerce-novalnet-gateway' ),
			) as $key => $text
		) {
			if ( ! empty( $input['transaction']['bank_details'][ $key ] ) ) {
				$comments .= sprintf( $text, $input['transaction']['bank_details'][ $key ] );
			}
		}

		$comments .= __( '\n Please use any of the following payment references when transferring the amount. This is necessary to match it with your corresponding order', 'woocommerce-novalnet-gateway' );

		/* translators: %s:  TID */
		$comments .= sprintf( __( '\n Payment Reference 1: TID %s', 'woocommerce-novalnet-gateway' ), $input['transaction']['tid'] );

		// Form reference comments.
		if ( ! empty( $input['transaction']['invoice_ref'] ) ) {
			/* translators: %s: invoice_ref */
			$comments .= sprintf( __( '\n Payment Reference 2: %s', 'woocommerce-novalnet-gateway' ), $input['transaction']['invoice_ref'] );
		} elseif ( ! empty( $wc_order_id ) && ! empty( $input['merchant']['project'] ) ) {
			/* translators: %s: project name */
			$comments .= sprintf( __( '\n Payment Reference 2: %s', 'woocommerce-novalnet-gateway' ), 'BNR-' . $input['merchant']['project'] . '-' . $wc_order_id );
		}
		return wc_novalnet_format_text( $comments );
	}

	/**
	 * Update transaction order comments in
	 * order and customer note.
	 *
	 * @since 12.0.0
	 * @param WC_Order $wc_order             The order object.
	 * @param string   $transaction_comments The transaction comments.
	 * @param string   $type                 The comment type.
	 * @param boolean  $notify_customer      Notify to the customer.
	 * @param boolean  $set_customer_note    Customer note falg.
	 * @param string   $customer_note        The customer given note.
	 */
	public function update_comments( $wc_order, $transaction_comments, $type = 'note', $notify_customer = true, $set_customer_note = false, $customer_note = '' ) {

		$wc_order->add_order_note( wc_novalnet_format_text( $transaction_comments ), $notify_customer );
		if ( 'transaction_info' === $type && $set_customer_note ) {
			$customer_note = ( ! empty( $customer_note ) ) ? wc_novalnet_format_text( $customer_note . PHP_EOL . $transaction_comments ) : wc_novalnet_format_text( $transaction_comments );
			$wc_order->set_customer_note( $customer_note );
			$wc_order->save();
		}
	}

	/**
	 * Forms the customer payment parameters.
	 *
	 * @since 12.0.0
	 * @param WC_Order $order The order object.
	 *
	 * @return array
	 */
	public function get_customer_data( $order ) {

		$customer = array();

		// Get billing address.
		list($billing_customer, $billing_address) = $this->get_address( $order, 'billing' );

		// Add customer details.
		if ( ! empty( $billing_customer ) ) {
			$customer = $billing_customer;
		}
		$customer['customer_ip'] = wc_novalnet_get_ip_address();
		$customer['customer_no'] = $order->get_user_id();

		// Add billing address.
		if ( ! empty( $billing_address ) ) {
			$customer['billing'] = $billing_address;
		}

		// Get shipping details.
		list($shipping_customer, $shipping_address) = $this->get_address( $order, 'shipping' );

		// Add shipping details.
		if ( ! empty( $shipping_address['street'] ) && ! empty( $shipping_address['city'] ) && ! empty( $shipping_address['zip'] ) && ! empty( $shipping_address['country_code'] ) ) {
			if ( $billing_address === $shipping_address ) {
				$customer['shipping']['same_as_billing'] = 1;
			} else {
				$customer['shipping'] = $shipping_address;
				if ( ! empty( $shipping_customer ) ) {
					$customer['shipping'] = array_merge( $customer['shipping'], $shipping_customer );
				}
			}
		}

		return $customer;
	}

	/**
	 * Get Address data.
	 *
	 * @since 12.0.0
	 * @param WC_Order $order The order object.
	 * @param string   $type billing / shipping.
	 *
	 * @return array
	 */
	public function get_address( $order, $type = 'billing' ) {

		$address  = array();
		$customer = array();
		if ( ! empty( $order ) ) {
			if ( is_array( $order ) ) {
				$prefix = '';
				if ( 'shipping' === $type ) {
					$prefix = 'shipping_';
				}
				$address = array(
					'street'       => $order[ $prefix . 'address_1' ],
					'city'         => $order[ $prefix . 'city' ],
					'zip'          => $order[ $prefix . 'postcode' ],
					'country_code' => $order[ $prefix . 'country' ],
				);
				if ( ! empty( $order[ $prefix . 'address_2' ] ) ) {
					$address['street'] .= ' ' . $order[ $prefix . 'address_2' ];
				}
				return array( $customer, $address );
			}
			$wc_address = $order->get_address( $type );
			list($customer['first_name'], $customer['last_name']) = wc_novalnet_retrieve_name(
				array(
					$wc_address['first_name'],
					$wc_address['last_name'],
				)
			);

			if ( 'billing' === $type ) {
				if ( ! empty( $wc_address['gender'] ) ) {
					$customer['gender'] = strtoupper( substr( $wc_address['gender'], 0, 1 ) );
				}
				if ( ! empty( $wc_address['email'] ) ) {
					$customer['email'] = $wc_address['email'];
				}
			}
			$address['street']       = $wc_address['address_1'] . ' ' . $wc_address['address_2'];
			$address['city']         = $wc_address['city'];
			$address['zip']          = $wc_address['postcode'];
			$address['country_code'] = $wc_address['country'];
			if ( ! empty( $wc_address['state'] ) && isset( WC()->countries->get_states( $wc_address['country'] )[ $wc_address['state'] ] ) ) {
				$address['state'] = WC()->countries->get_states( $wc_address['country'] )[ $wc_address['state'] ];
			}
			if ( ! empty( $wc_address['company'] ) ) {
				$address['company'] = $wc_address['company'];
			}
			if ( ! empty( $wc_address['phone'] ) ) {
				$customer['tel'] = $wc_address['phone'];
			}
		}
		return array( $customer, $address );
	}

	/**
	 * Assign post values in session.
	 *
	 * @since 12.0.0
	 *
	 * @param string $payment_type    The payment ID.
	 * @param array  $post_array The post data.
	 */
	public function set_post_value_session( $payment_type, $post_array ) {

		$session = WC()->session->get( $payment_type );

		// Set post values in session.
		foreach ( $post_array as $value ) {
			$session_value = '';
			if ( ! empty( $session[ $value ] ) ) {
				$session_value = sanitize_text_field( trim( $session[ $value ] ) );
			}

			$session[ $value ] = $session_value;
			if ( isset( novalnet()->request[ $value ] ) && '' !== novalnet()->request[ $value ] ) {
				$session[ $value ] = sanitize_text_field( trim( novalnet()->request[ $value ] ) );
			}
		}

		// Storing the values in session.
		WC()->session->set( $payment_type, $session );
		return WC()->session->get( $payment_type );
	}

	/**
	 * Get post parent id
	 *
	 * @since 12.0.0
	 * @param WC_Order $wc_order The subscription order object.
	 *
	 * @return int
	 */
	public function get_order_post_id( $wc_order ) {
		$parent_id = $wc_order->get_parent_id();
		if ( ! empty( $parent_id ) ) {
			return $parent_id;
		}
		return $wc_order->get_id();
	}

	/**
	 * Check and maintain debug log if enabled
	 *
	 * @param string $message     Message to be logged.
	 * @param int    $wc_order_id The post ID value.
	 * @param bool   $override_log Flag to override the debug log.
	 * @param string $debug_method Flag to identifie the debug method.
	 *
	 * @since 12.0.0
	 */
	public function debug( $message, $wc_order_id = '', $override_log = false, $debug_method = 'debug' ) {
		global $current_user;

		if ( 'yes' === WC_Novalnet_Configuration::get_global_settings( 'debug_log' ) || true === $override_log ) {
			if ( ! empty( $wc_order_id ) ) {
				$message = "###$wc_order_id### $message";
			}
			if ( ! empty( $current_user->user_login ) ) {
				$message .= " - $current_user->user_login";
			}
			$debug_level = constant( 'WC_Log_Levels::' . strtoupper( $debug_method ) );
			wc_novalnet_logger()->add( 'woocommerce-novalnet-gateway', $message, $debug_level );
		}
	}

	/**
	 * Log error
	 *
	 * @param string $message  Message to be logged.
	 *
	 * @since 12.0.0
	 */
	public function log_error( $message ) {
		global $current_user;

		if ( ! empty( $current_user->user_login ) ) {
			$message .= " - $current_user->user_login";
		}
		wc_novalnet_logger()->add( 'woocommerce-novalnet-gateway-error', $message, WC_Log_Levels::CRITICAL );
	}

	/**
	 * Get action URL
	 *
	 * @param string $action the action.
	 *
	 * @since 12.0.0
	 */
	public function get_action_endpoint( $action = '' ) {
		return $this->endpoint . str_replace( '_', '/', $action );
	}

	/**
	 * Status mapper
	 *
	 * @param string $status_code  The status code.
	 *
	 * @since 12.0.0
	 */
	public function status_mapper( &$status_code ) {

		if ( WC_Novalnet_Validation::is_valid_digit( $status_code ) ) {
			foreach ( $this->statuses as $status => $status_codes ) {
				if ( in_array( $status_code, $status_codes, true ) ) {
					$status_code = $status;
					break;
				}
			}
		}
	}

	/**
	 * Load the template
	 *
	 * @since 12.0.0
	 * @param string $file_name The file name.
	 * @param array  $contents The contents.
	 * @param array  $payment_type The payment type.
	 * @param string $type The name of the contents array.
	 */
	public function load_template( $file_name, $contents, $payment_type = '', $type = 'checkout' ) {

		wc_get_template(
			$file_name,
			array(
				'contents'     => $contents,
				'payment_type' => $payment_type,
			),
			'',
			dirname( dirname( __FILE__ ) ) . "/templates/$type/"
		);
	}

	/**
	 * Remove unsupported feature
	 *
	 * @since 12.0.0
	 * @param array  $supports The supported feature.
	 * @param string $feature The feature need to be unset.
	 */
	public function unset_supports( &$supports, $feature = '' ) {
		foreach ( $supports as $key => $support ) {
			if ( $support === $feature ) {
				unset( $supports[ $key ] );
			}
		}
		$supports = array_values( $supports );
	}

	/**
	 * Check current page is pay for order.
	 *
	 * @since 12.5.5
	 */
	public function get_pay_order() {
		global $wp;
		// If paying from order, we need to get total from order not cart.
		if (isset($_GET['pay_for_order']) && ! empty($_GET['key']) && ! empty($wp->query_vars)) { // @codingStandardsIgnoreLine.
			return wc_get_order( wc_clean( $wp->query_vars['order-pay'] ) );
		}
		return false;
	}
	/**
	 * Form system version details for Novalnet server parameter.
	 *
	 * @since 12.5.5
	 */
	public function get_system_version_string() {
		if ( class_exists( 'WC_Subscriptions' ) ) {
			return get_bloginfo( 'version' ) . '-' . WOOCOMMERCE_VERSION . '-' . WC_Subscriptions::$version . '-NN' . NOVALNET_VERSION;
		} else {
			return get_bloginfo( 'version' ) . '-' . WOOCOMMERCE_VERSION . '-NN' . NOVALNET_VERSION;
		}
	}

	/**
	 * Get BIC allowed countries
	 *
	 * @since 12.5.6
	 */
	public function get_bic_allowed_countries() {
		return $this->bic_allowed_countries;
	}

	/**
	 * Update the amount booked for an order that is processed as a zero amount transaction.
	 *
	 * @since 12.6.0
	 *
	 * @param int      $wc_order_id      The order id.
	 * @param WC_Order $wc_order         The order object.
	 * @param array    $booking_response The payment booking transaction response.
	 * @param array    $additional_info  The additional info.
	 */
	public function update_payment_booking( $wc_order_id, $wc_order, $booking_response, $additional_info ) {
		if ( is_object( $wc_order ) && ! empty( $booking_response ) ) {
			novalnet()->helper()->novalnet_delete_wc_order_meta( $wc_order, '_novalnet_booking_ref_order' );
			$additional_info['is_payment_booked'] = 'yes';
			novalnet()->db()->update(
				array(
					'tid'             => $booking_response['transaction']['tid'],
					'amount'          => $booking_response['transaction']['amount'],
					'callback_amount' => ( in_array( $booking_response['transaction']['status'], array( 'PENDING', 'ON_HOLD' ), true ) ) ? 0 : $booking_response['transaction']['amount'],
					'gateway_status'  => $booking_response['transaction']['status'],
					'additional_info' => wc_novalnet_serialize_data( $additional_info ),
				),
				array(
					'order_no' => $wc_order_id,
				)
			);
			$wc_order->set_transaction_id( $booking_response['transaction']['tid'] );
			$wc_order->save();
			/* translators: %1$s: amount, %2$s: TID*/
			$message = sprintf( __( 'Your order has been booked with the amount of %1$s. Your new TID for the booked amount: %2$s', 'woocommerce-novalnet-gateway' ), wc_novalnet_shop_amount_format( $booking_response['transaction']['amount'] ), $booking_response['transaction']['tid'] );
			novalnet()->helper()->update_comments( $wc_order, wc_novalnet_format_text( $message ) );
			return true;
		}
		return false;
	}

	/**
	 * Prepares the data to be inserted into the Novalnet Transaction Details table.
	 *
	 * @since 12.6.1
	 *
	 * @param WC_Order $wc_order        The order object.
	 * @param string   $payment_id      The order payment id.
	 * @param array    $server_response Payment gateway reponse.
	 *
	 * @return array $insert_data
	 */
	public function prepare_transaction_table_data( $wc_order, $payment_id, $server_response ) {

		$insert_data = array(
			'order_no'       => $wc_order->get_id(),
			'tid'            => $server_response['transaction']['tid'],
			'currency'       => get_woocommerce_currency(),
			'gateway_status' => $server_response['transaction']['status'],
			'payment_type'   => $payment_id,
			'amount'         => wc_novalnet_formatted_amount( $wc_order->get_total() ),
		);

		if ( ! empty( $server_response['subscription']['subs_id'] ) ) {
			$insert_data['subs_id'] = $server_response['subscription']['subs_id'];
		}

		$insert_data['callback_amount'] = $insert_data['amount'];

		if ( novalnet()->get_supports( 'instalment', $payment_id ) ) {
			$insert_data['additional_info'] = apply_filters( 'novalnet_store_instalment_data', $server_response );
		}
		if ( ( novalnet()->get_supports( 'pay_later', $payment_id ) || 'novalnet_guaranteed_invoice' === $payment_id || 'novalnet_instalment_invoice' === $payment_id ) ) {
			if ( ! empty( $insert_data['additional_info'] ) ) {
				$insert_data['additional_info'] = wc_novalnet_serialize_data( wc_novalnet_unserialize_data( $insert_data['additional_info'] ) + $server_response['transaction']['bank_details'] );
			} elseif ( ! empty( $server_response['transaction']['bank_details'] ) ) {
				$insert_data['additional_info'] = wc_novalnet_serialize_data( $server_response['transaction']['bank_details'] );
			} elseif ( ! empty( $server_response['transaction']['nearest_stores'] ) ) {
				$insert_data['additional_info'] = wc_novalnet_serialize_data( $server_response['transaction']['nearest_stores'] );
			}
		}

		if ( novalnet()->get_supports( 'zero_amount_booking', $payment_id ) && '1' === (string) $this->novalnet_get_wc_order_meta( $wc_order, '_novalnet_booking_ref_order' ) ) {
			$booking_data = array(
				'nn_booking_ref_token' => $server_response['transaction']['payment_data']['token'],
				'is_payment_booked'    => 'no',
			);
			if ( ! empty( $insert_data['additional_info'] ) ) {
				$insert_data['additional_info'] = wc_novalnet_serialize_data( wc_novalnet_unserialize_data( $insert_data['additional_info'] ) + $booking_data );
			} else {
				$insert_data['additional_info'] = wc_novalnet_serialize_data( $booking_data );
			}
			$this->update_comments( $wc_order, wc_novalnet_format_text( __( 'This order processed as a zero amount booking', 'woocommerce-novalnet-gateway' ) ) );
		}

		novalnet()->helper()->novalnet_update_wc_order_meta( $wc_order, '_novalnet_gateway_status', $server_response['transaction']['status'], true );

		if ( in_array( $insert_data['gateway_status'], array( 'PENDING', 'ON_HOLD' ), true ) || ! WC_Novalnet_Validation::is_success_status( $server_response ) ) {
			$insert_data['callback_amount'] = 0;
		}

		return $insert_data;
	}


	/**
	 * Inserts the change payment transaction details into the Novalnet transaction table.
	 *
	 * @since 12.6.1
	 *
	 * @param WC_Subscription $subscription    Subscription object.
	 * @param string          $payment_type    Changed payment id.
	 * @param array           $server_response Payment response.
	 */
	public function insert_change_payment_transaction_details( $subscription, $payment_type, $server_response ) {
		$is_renew_subscription = 0;
		if ( isset( $server_response['transaction']['order_no'] ) ) {
			// Check the payment method changed while renewing the subscription.
			$is_renew_subscription = $this->novalnet_get_wc_order_meta( $subscription, '_novalnet_renew_subscription' );
		}

		if ( empty( $is_renew_subscription ) && 1 !== (int) $is_renew_subscription ) { // Renewal order transaction details are stored by transaction success method.
			$insert_data = array(
				'order_no'     => $subscription->get_id(),
				'tid'          => $server_response['transaction']['tid'],
				'currency'     => get_woocommerce_currency(),
				'payment_type' => $payment_type,
				'amount'       => $server_response['transaction']['amount'],
				'subs_id'      => ! empty( $server_response['subscription']['subs_id'] ) ? $server_response['subscription']['subs_id'] : '',
			);

			$insert_data['callback_amount'] = $insert_data['amount'];

			if ( ! empty( $server_response['transaction']['status'] ) ) {
				novalnet()->helper()->novalnet_update_wc_order_meta( $subscription, '_novalnet_gateway_status', $server_response['transaction']['status'], true );
				$insert_data['gateway_status'] = $server_response['transaction']['status'];
				if ( 'PENDING' === $server_response['transaction']['status'] ) {
					$insert_data['callback_amount'] = '0';
				}
			}

			// Insert the transaction details.
			novalnet()->db()->insert( $insert_data, 'novalnet_transaction_detail' );
		}
	}

	/**
	 * Checks that the renewal subscription is active with collection.
	 *
	 * @since 12.6.1
	 *
	 * @param array $server_response Payment gateway reponse.
	 *
	 * @return boolean
	 */
	public function is_subs_renewal_active_with_collection( $server_response ) {
		if (
			( isset( $server_response['event']['type'] ) && 'RENEWAL' === $server_response['event']['type'] ) &&
			( isset( $server_response['subscription']['status'] ) && 'ACTIVE_WITH_COLLECTION' === $server_response['subscription']['status'] )
		) {
			return true;
		}
		return false;
	}

	/**
	 * Get Novalnet subscription TID for woocommerce subscription
	 *
	 * @since 12.6.1
	 *
	 * @param strint $parent_order_id Subscription parent order ID.
	 * @param strint $subscription_id Subscription order ID.
	 *
	 * @return string
	 */
	public function get_novalnet_subscription_tid( $parent_order_id, $subscription_id ) {
		$tid = novalnet()->db()->get_subs_data_by_order_id( $parent_order_id, $subscription_id, 'tid', false );
		if ( empty( $tid ) ) {
			$tid = novalnet()->db()->get_entry_by_order_id( $parent_order_id, 'tid' );
		}
		return $tid;
	}

	/**
	 * Checks that the Novalnet-based subscription entry exists in the table for the subscription.
	 *
	 * @since 12.6.1
	 *
	 * @param WC_Subscription $subscription The WooCommerce subscription object.
	 *
	 * @return boolean
	 */
	public function is_novalnet_based_subs_exist( $subscription ) {
		$is_subscription = apply_filters( 'novalnet_check_is_subscription', $subscription );
		$tid             = ( $is_subscription ) ? $this->get_novalnet_subscription_tid( $subscription->get_parent_id(), $subscription->get_id() ) : '';
		if ( ! empty( $tid ) ) {
			$is_shop_scheduled = novalnet()->db()->get_subs_data_by_order_id( $subscription->get_parent_id(), $subscription->get_id(), 'shop_based_subs' );
			$nn_subs_id        = novalnet()->db()->get_subs_data_by_order_id( $subscription->get_parent_id(), $subscription->get_id(), 'subs_id' );
			if ( empty( $is_shop_scheduled ) && ! empty( $nn_subs_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks that the Shop-based subscription entry exists in the table for the subscription.
	 *
	 * @since 12.6.1
	 *
	 * @param WC_Subscription $subscription The WooCommerce subscription object.
	 *
	 * @return boolean
	 */
	public function is_shop_based_subs_exist( $subscription ) {
		$is_subscription = apply_filters( 'novalnet_check_is_subscription', $subscription );
		$tid             = ( $is_subscription ) ? $this->get_novalnet_subscription_tid( $subscription->get_parent_id(), $subscription->get_id() ) : '';
		if ( ! empty( $tid ) ) {
			$is_shop_scheduled = novalnet()->db()->get_subs_data_by_order_id( $subscription->get_parent_id(), $subscription->get_id(), 'shop_based_subs' );
			$nn_subs_id        = novalnet()->db()->get_subs_data_by_order_id( $subscription->get_parent_id(), $subscription->get_id(), 'subs_id' );
			if ( ! empty( $is_shop_scheduled ) && empty( $nn_subs_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get post id.
	 *
	 * @param int $wc_order_id  The order id of the processing order.
	 * @since 12.0.0
	 * @since 12.6.2 Moved from webhook to helper.
	 *
	 * @return array
	 */
	public function get_post_id( $wc_order_id ) {

		$post_id = '';
		if ( ! empty( $wc_order_id ) ) {
			$post_id = novalnet()->db()->get_post_id_by_order_number( $wc_order_id );

			if ( empty( $post_id ) ) {
				$wc_order = wc_get_order( $wc_order_id );

				if ( is_object( $wc_order ) && method_exists( $wc_order, 'get_id' ) && (string) $wc_order_id === (string) $wc_order->get_id() ) {
					$post_id = $wc_order->get_id();
				}
			}

			if ( empty( $post_id ) ) {
				$post_id = $wc_order_id;
			}
		}
		return $post_id;
	}

	/**
	 * Update order meta data.
	 *
	 * @since 12.6.2
	 * @param WC_Order $wc_order   The WooCommerce order.
	 * @param string   $meta_key   Meta data key string.
	 * @param mixed    $meta_value Meta data value.
	 * @param boolean  $save_order To save the order.
	 */
	public function novalnet_update_wc_order_meta( $wc_order, $meta_key, $meta_value, $save_order = false ) {
		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$wc_order->update_meta_data( $meta_key, $meta_value ); // HPOS usage is enabled.
			if ( $save_order ) {
				$wc_order->save();
			}
		} else {
			update_post_meta( $wc_order->get_id(), $meta_key, $meta_value ); // Traditional CPT-based orders are in use.
		}
	}

	/**
	 * Delete order meta data.
	 *
	 * @since 12.6.2
	 * @param WC_Order $wc_order   The WooCommerce order.
	 * @param string   $meta_key   Meta data key string.
	 * @param boolean  $save_order To save the order.
	 */
	public function novalnet_delete_wc_order_meta( $wc_order, $meta_key, $save_order = false ) {
		if ( ! empty( $wc_order ) && method_exists( $wc_order, 'delete_meta_data' ) ) {
			$wc_order->delete_meta_data( $meta_key );
		}
		delete_post_meta( $wc_order->get_id(), $meta_key );
		if ( $save_order ) {
			$wc_order->save();
		}
	}

	/**
	 * Get order meta data.
	 *
	 * @since 12.6.2
	 * @param WC_Order $wc_order   The WooCommerce order.
	 * @param string   $meta_key   Meta data key string.
	 * @param mixed    $single     Return the single value.
	 */
	public function novalnet_get_wc_order_meta( $wc_order, $meta_key, $single = true ) {
		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$meta_value = $wc_order->get_meta( $meta_key, $single ); // HPOS usage is enabled.
			if ( ! empty( $meta_value ) ) {
				return $meta_value;
			}
		}
		return get_post_meta( $wc_order->get_id(), $meta_key, $single ); // Traditional CPT-based orders are in use.
	}

	/**
	 * Get order meta data.
	 *
	 * @since 12.6.2
	 * @param WC_Order|int $wc_order_or_id   The WooCommerce order.
	 */
	public function novalnet_get_wc_order_type( $wc_order_or_id ) {
		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return OrderUtil::get_order_type( $wc_order_or_id );
		}
		return get_post_type( $wc_order_or_id );
	}

	/**
	 * Returns the script date for payment method blocks.
	 *
	 * @since 12.6.2
	 * @param string $payment_id Payment Method ID.
	 *
	 * @return array
	 */
	public function get_payment_method_block_data( $payment_id ) {
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( isset( $available_gateways[ $payment_id ] ) ) {
			$icons   = array();
			$gateway = $available_gateways[ $payment_id ];

			if ( 'yes' === WC_Novalnet_Configuration::get_global_settings( 'payment_logo' ) ) {
				if ( 'novalnet_cc' === $payment_id ) {
					foreach ( $gateway->settings['accepted_card_logo'] as $logo ) {
						$icon_src = novalnet()->plugin_url . '/assets/images/novalnet_cc_' . $logo . '.png';
						$icons[]  = array(
							'id'  => 'novalnet_cc_' . $logo,
							'src' => $icon_src,
							'alt' => $gateway->get_title(),
						);
					}
				} else {
					$icon_url = novalnet()->plugin_url . '/assets/images/' . $payment_id . '.png';
					$icons[]  = array(
						'id'  => $payment_id,
						'src' => $icon_url,
						'alt' => $gateway->get_title(),
					);
				}
			}

			$payment_description = '';
			if ( ! empty( $gateway ) ) {
				$payment_description = method_exists( $gateway, 'get_payment_description_html' ) ? $gateway->get_payment_description_html() : ( method_exists( $gateway, 'get_description' ) ? $gateway->get_description() : null );
			}

			return array(
				'title'       => $gateway->get_title(),
				'description' => $payment_description,
				'supports'    => $gateway->supports,
				'icons'       => $icons,
			);
		}
		return array();
	}

	/**
	 * Updates the wallet payments data in block script date.
	 *
	 * @since 12.6.2
	 * @param string $payment_id Payment Method ID.
	 * @param array  $payment_method_data Payment method script data.
	 *
	 * @return array
	 */
	public function update_wallet_payment_block_data( $payment_id, $payment_method_data ) {
		$payment_wallet = str_replace( 'novalnet_', '', $payment_id );
		if ( is_checkout() && is_user_logged_in() ) {
			$source_page = 'checkout_page';
		} elseif ( ! is_user_logged_in() ) {
			$source_page = 'guest_checkout_page';
		} elseif ( is_cart() ) {
			$source_page = 'shopping_cart_page';
		} else {
			return array();
		}

		$available_wallets = get_available_wallets( $source_page );
		if ( ! in_array( $payment_wallet, $available_wallets, true ) ) {
			return array();
		}

		$wallet_sheet_detail = get_wallet_sheet_details( $payment_wallet );
		if ( ! ( $wallet_sheet_detail['cart_has_subs'] <= 1 || 'yes' === get_option( 'novalnet_enable_shop_subs' ) ) ) {
			return array();
		}

		$payment_method_data['paymentWallet']      = $payment_wallet;
		$payment_method_data['lang']               = wc_novalnet_shop_language();
		$payment_method_data['walletContainerId']  = $source_page . '_' . $payment_wallet . '_button';
		$payment_method_data['walletSheetDetails'] = array_merge(
			$wallet_sheet_detail,
			array(
				'store_lang' => wc_novalnet_shop_wallet_language(),
				'setpending' => ( 'billing' === get_option( 'woocommerce_tax_based_on' ) ) ? true : false,
			)
		);
		return $payment_method_data;
	}

	/**
	 * Returns the script handle for payment method block.
	 *
	 * @since 12.6.2
	 * @param string  $payment_id Payment Method ID.
	 * @param boolean $novalnet_utility Is payment requires Novalnet Utility script.
	 * @param boolean $novalnet_payment Is payment requires Novalnet Payment script.
	 * @return string
	 */
	public function register_payment_script( $payment_id, $novalnet_utility = false, $novalnet_payment = false ) {
		$script_payment_id = str_replace( '_', '-', $payment_id );
		$script_handle     = 'wc-' . $script_payment_id . '-blocks-integration';
		$deps              = array( 'jquery', 'react', 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-i18n', 'wp-polyfill', 'wp-components', 'wc-price-format' );

		if ( $novalnet_utility && ! is_admin() ) {

			if ( ! wp_script_is( 'woocommerce-novalnet-gateway-external-script', 'registered' ) ) {
				wp_register_script(
					'woocommerce-novalnet-gateway-external-script',
					'https://cdn.novalnet.de/js/v2/NovalnetUtility.js',
					array( 'jquery', 'jquery-payment' ),
					NOVALNET_VERSION,
					true
				);
			}
			$deps[] = 'woocommerce-novalnet-gateway-external-script';
		}

		wp_register_script(
			'wc-novalnet-block-inputs',
			novalnet()->plugin_url . '/includes/wc-blocks/assets/block-novalnet-elements.min.js',
			$deps,
			NOVALNET_VERSION,
			true
		);

		wp_set_script_translations(
			'wc-novalnet-block-inputs',
			'woocommerce-novalnet-gateway'
		);

		if ( $novalnet_payment && ! is_admin() ) {

			if ( ! wp_script_is( 'woocommerce-novalnet-gateway-external-script-payment', 'registered' ) ) {
				wp_register_script(
					'woocommerce-novalnet-gateway-external-script-payment',
					'https://cdn.novalnet.de/js/v3/payment.js',
					array( 'jquery', 'jquery-payment' ),
					NOVALNET_VERSION,
					true
				);
			}
			$deps[] = 'woocommerce-novalnet-gateway-external-script-payment';
		}

		wp_register_script(
			$script_handle,
			novalnet()->plugin_url . '/includes/wc-blocks/assets/block-' . $script_payment_id . '.min.js',
			array_merge( $deps, array( 'wc-novalnet-block-inputs' ) ),
			NOVALNET_VERSION,
			true
		);
		wp_set_script_translations(
			$script_handle,
			'woocommerce-novalnet-gateway'
		);
		return $script_handle;
	}

	/**
	 * Checks if the default checkout page is using the Checkout block.
	 *
	 * @since 12.6.2
	 * @return bool true if the WC checkout page is using the Checkout block.
	 */
	public function is_checkout_block_default() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils' ) && has_block( 'woocommerce/checkout' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Checks if the default cart page is using the Cart block.
	 *
	 * @since 12.6.2
	 * @return bool true if the WC cart page is using the Cart block.
	 */
	public function is_cart_block_default() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils' ) && has_block( 'woocommerce/cart' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Checks that the current page is a subscription list table or a subscription edit page.
	 *
	 * @since 12.6.2
	 * @return bool true if the current page in admin subscription page.
	 */
	public function is_wcs_subscription_page() {
		if ( ! WC_Novalnet_Validation::is_subscription_plugin_available() ) {
			return false;
		}

		if ( function_exists( 'wcs_is_custom_order_tables_usage_enabled' ) && wcs_is_custom_order_tables_usage_enabled() ) {
			$current_screen             = get_current_screen();
			$is_subscription_list_table = $current_screen && wcs_get_page_screen_id( 'shop_subscription' ) === $current_screen->id;
		} else {
			global $post_type;
			$is_subscription_list_table = ( 'shop_subscription' === $post_type ) ? true : false;
		}
		return $is_subscription_list_table;
	}

	/**
	 * Update the subscription related dates.
	 *
	 * @since 12.6.3
	 *
	 * @param WC_Subscription $subscription  Wcs order object.
	 * @param array           $dates         Next payment date.
	 * @param bool            $set_cancelled The flag to reset the cancelled date.
	 */
	public function update_subscription_dates( $subscription, $dates, $set_cancelled = false ) {

		$update_dates = array();

		if ( $set_cancelled ) {
			$update_dates['cancelled'] = 0;
		}
		if ( ! empty( $dates['next_payment'] ) ) {
			$next_cycle = $dates['next_payment'];
			$start_date = $subscription->get_date( 'start' );

			$items = $subscription->get_items();

			foreach ( $items as $item ) {
				$product_id           = $item->get_product_id();
				$product_variation_id = $item->get_variation_id();
			}

			if ( $product_variation_id ) {
				$end_date   = WC_Subscriptions_Product::get_expiration_date( $product_variation_id, $start_date );
				$trial_date = WC_Subscriptions_Product::get_trial_expiration_date( $product_variation_id, $start_date );
			} elseif ( ! $product_variation_id ) {
				$end_date   = WC_Subscriptions_Product::get_expiration_date( $product_id, $start_date );
				$trial_date = WC_Subscriptions_Product::get_trial_expiration_date( $product_id, $start_date );
			}
			if ( ! empty( $trial_date ) && $trial_date > gmdate( 'Y-m-d H:i:s' ) ) {
				$update_dates['trial_end'] = ( $next_cycle > $trial_date ) ? $trial_date : $next_cycle;
			}

			if ( 0 <= $end_date ) {
				$update_dates['end'] = ( $next_cycle < $end_date ) ? $end_date : 0;
			}

			$update_dates['next_payment'] = $next_cycle;
		}
		if ( ! empty( $update_dates ) ) {
			$subscription->update_dates( $update_dates );
		}
	}
}

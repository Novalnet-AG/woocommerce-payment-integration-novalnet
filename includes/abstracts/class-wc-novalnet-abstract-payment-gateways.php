<?php
/**
 * Novalnet Payment Gateway class.
 *
 * Extended by individual payment gateways to handle payments.
 *
 * @class    WC_Novalnet_Abstract_Payment_Gateways
 * @extends  WC_Payment_Gateway
 * @package  woocommerce-novalnet-gateway/includes/abstracts/
 * @category Abstract Class
 * @author   Novalnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Novalnet_Abstract_Payment_Gateways Abstract Class.
 */
abstract class WC_Novalnet_Abstract_Payment_Gateways extends WC_Payment_Gateway {


	/**
	 * Settings of the gateway.
	 *
	 * @var array
	 */
	public $settings = array();

	/**
	 * True if the gateway shows fields on the checkout.
	 *
	 * @var bool
	 */
	public $has_fields = true;


	/**
	 * Form gateway parameters to process in the Novalnet server.
	 *
	 * @param WC_Order $wc_order    The order object.
	 * @param int      $parameters  The parameters.
	 */
	abstract public function generate_payment_parameters( $wc_order, &$parameters );

	/**
	 * Perform the payment call to Novalnet server.
	 *
	 * @since 12.0.0
	 * @param int $wc_order_id The order id.
	 *
	 * @return array
	 */
	public function perform_payment_call( $wc_order_id ) {

		// The order object.
		$wc_order = new WC_Order( $wc_order_id );

		// Set current payment id in session.
		WC()->session->set( 'current_novalnet_payment', $this->id );

		// Generate basic parameters.
		$parameters = $this->generate_basic_parameters( $wc_order, WC_Novalnet_Validation::is_change_payment_method( $wc_order ) );

		// Generate payment related parameters.
		if ( wc_novalnet_check_session() && ( WC()->session->__isset( 'novalnet_guaranteed_sepa_switch_payment' ) || WC()->session->__isset( 'novalnet_guaranteed_invoice_switch_payment' ) ) && in_array( $this->id, array( 'novalnet_invoice', 'novalnet_sepa' ), true ) ) {
			if ( 'novalnet_sepa' === $this->id ) {
				( new WC_Gateway_Novalnet_Sepa() )->generate_payment_parameters( $wc_order, $parameters );
			} elseif ( 'novalnet_invoice' === $this->id ) {
				( new WC_Gateway_Novalnet_Guaranteed_Invoice() )->generate_payment_parameters( $wc_order, $parameters );
			}
		} else {
			$this->generate_payment_parameters( $wc_order, $parameters );
		}

		$is_subscription = apply_filters( 'novalnet_check_is_subscription', $wc_order );

		$is_shop_based_subs = true;
		if ( class_exists( 'WC_Subscriptions' ) && WC_Novalnet_Validation::is_change_payment_method( $wc_order ) && WC()->session->__isset( 'novalnet_change_payment_method' ) ) {
			$is_shop_based_subs = apply_filters( 'novalnet_check_is_shop_scheduled_subscription', $wc_order_id );
		}

		// Set endpoint.
		if ( ! $is_shop_based_subs && wc_novalnet_check_session() && WC()->session->__isset( 'novalnet_change_payment_method' ) ) {
			$endpoint                                  = novalnet()->helper()->get_action_endpoint( 'subscription_update' );
			$parameters['transaction']['payment_type'] = novalnet()->get_payment_types( $this->id );
			$parameters ['subscription']['tid']        = novalnet()->db()->get_entry_by_order_id( $wc_order->get_parent_id(), 'tid' );
		} else {
			if ( $this->supports( 'subscriptions' ) ) {

				// Set Subscription related parameters if available.
				do_action( 'novalnet_set_shopbased_subs_flag', $wc_order, WC_Novalnet_Validation::is_change_payment_method( $wc_order ) );
			}

			if ( is_admin() && wc_novalnet_check_session() && WC()->session->get( 'admin_add_shop_order' ) ) {
				$endpoint = $this->get_payment_endpoint_admin_orders( $wc_order->get_total() );
				if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0', '>' ) ) {
					add_filter( 'woocommerce_payment_complete_order_status', array( &$this, 'get_order_status' ), 10, 3 );
				} else {
					add_filter( 'woocommerce_payment_complete_order_status', array( &$this, 'get_order_status' ), 10, 2 );
				}
			} else {
				// Set payment token for subscription order.
				$is_shop_based_subs_enabled = apply_filters( 'novalnet_check_is_shop_scheduled_subscription_enabled', false );
				if ( ( $is_subscription || WC_Novalnet_Validation::is_renew_subscription( $wc_order ) ) && $is_shop_based_subs_enabled ) {
					$parameters ['custom']['input1']    = 'shop_subs';
					$parameters ['custom']['inputval1'] = 1;
					if ( empty( $parameters['transaction']['create_token'] ) && empty( $parameters['transaction']['payment_data']['token'] ) ) {
						// Send order number in input value.
						$parameters ['transaction']['create_token'] = '1';
					}
				}
				$endpoint = $this->get_payment_endpoint();
			}
		}

		// Update order number in post meta.
		if ( ! empty( $parameters ['transaction']['order_no'] ) ) {
			update_post_meta( $wc_order->get_id(), '_novalnet_order_number', $parameters ['transaction']['order_no'] );
		}

		$update_wc_order = false;
		if ( is_admin() && wc_novalnet_check_session() && WC()->session->get( 'admin_add_shop_order' ) && ! empty( novalnet()->request['_transaction_id'] ) ) {
			$parameters = array(
				'transaction' => array(
					'tid' => novalnet()->request['_transaction_id'],
				),
				'custom'      => array(
					'lang' => wc_novalnet_shop_language(),
				),
			);

			$endpoint        = novalnet()->helper()->get_action_endpoint( 'transaction_details' );
			$update_wc_order = true;
		}

		// For admin order check parameters are not empty.
		if ( is_admin() && ! in_array( $this->id, array( 'novalnet_googlepay', 'novalnet_applepay' ), true ) ) {
			$input_check = true;
			foreach ( $parameters as $sub_array ) {
				if ( in_array( '', $sub_array, true ) ) {
					$input_check = false;
				}
				foreach ( $sub_array as $details ) {
					if ( is_array( $details ) && in_array( '', $details, true ) ) {
						$input_check = false;
					}
				}
				if ( ! $input_check ) {
					$message = __( 'Customer data cannot be left blank. Please enter the customer details.', 'woocommerce-novalnet-gateway' );
					$wc_order->update_status( 'failed' );
					$wc_order->add_order_note( $message );
					WC_Admin_Meta_Boxes::add_error( $message );
					return array(
						'result'   => 'input_error',
						'response' => $message,
					);
				}
			}
		}

		// Submit the given request.
		$response = novalnet()->helper()->submit_request( $parameters, $endpoint, array( 'post_id' => $wc_order->get_id() ) );

		if ( $update_wc_order && WC_Novalnet_Validation::is_success_status( $response ) ) {
			$this->update_wc_order( $wc_order, $response );
		}

		// Handle redirection (if needed).
		if ( ! empty( $response ['result'] ['redirect_url'] ) && ! empty( $response['transaction']['txn_secret'] ) ) {
			WC()->session->set( 'novalnet_post_id', $wc_order_id );
			WC()->session->set( 'novalnet_txn_secret', $response['transaction']['txn_secret'] );
			novalnet()->helper()->debug( 'Going to redirect the end-user to the URL - ' . $response ['result'] ['redirect_url'] . ' to complete the payment', $wc_order_id );
			return array(
				'result'   => 'success',
				'redirect' => $response ['result'] ['redirect_url'],
			);
		}

		// Handle response.
		return $this->check_transaction_status( $response, $wc_order );
	}

	/**
	 * Built logo with link to display in front-end.
	 *
	 * @since 12.0.0
	 *
	 * @return string
	 */
	public function built_logo() {
		$icon = '';

		if ( 'yes' === WC_Novalnet_Configuration::get_global_settings( 'payment_logo' ) ) {
			$icon_url = novalnet()->plugin_url . '/assets/images/' . $this->id . '.png';
			$icon     = "<img src='$icon_url' alt='" . $this->title . "' title='" . $this->title . "' />";
		}
		return $icon;
	}


	/**
	 * Align order confirmation mail transaction comments.
	 *
	 * @since 12.0.0
	 *
	 * @param WC_Order $wc_order The order object.
	 * @param bool     $sent_to_admin Sent to admin.
	 */
	public function add_email_instructions( $wc_order, $sent_to_admin ) {
		$language = strtolower( wc_novalnet_shop_language() );

		if ( $wc_order->get_payment_method() === $this->id && ! $sent_to_admin && ! empty( $this->settings [ 'instructions_' . $language ] ) ) {

			// Set email notes.
			echo wp_kses_post( wpautop( wptexturize( $this->settings [ 'instructions_' . $language ] ) ) );

		}
	}

	/**
	 * Align order confirmation transaction comments in checkout page.
	 *
	 * @since 12.0.0
	 *
	 * @param WC_Order $wc_order The order object.
	 */
	public function align_transaction_details( $wc_order ) {

		// Check Novalnet payment.
		if ( WC_Novalnet_Validation::check_string( $wc_order->get_payment_method() ) ) {
			if ( version_compare( WOOCOMMERCE_VERSION, '3.6.5', '>' ) ) {
				$wc_order->set_customer_note( str_replace( PHP_EOL, '<\br>', $wc_order->get_customer_note() ) );
			}
		}
	}

	/**
	 * Forming basic params to process payment in Novalnet server.
	 *
	 * @since 12.0.0
	 * @param WC_Order $wc_order           The order object.
	 * @param string   $is_change_payment  The change payment.
	 *
	 * @throws Exception For error.
	 * @return array
	 */
	public function generate_basic_parameters( $wc_order, $is_change_payment ) {

		$parameters = array();

		// Switch payment before form the parameters if the payment process in force normal mode.
		if ( ! empty( WC()->session ) && in_array( $this->id, array( 'novalnet_guaranteed_invoice', 'novalnet_guaranteed_sepa' ), true ) ) {
			$this->handle_payment_switch( $wc_order, $parameters );
		}

		// Form vendor parameters.
		$parameters['merchant'] = array(
			'signature' => WC_Novalnet_Configuration::get_global_settings( 'public_key' ),
			'tariff'    => WC_Novalnet_Configuration::get_global_settings( 'tariff_id' ),
		);

		// Form order details parameters.
		$parameters['transaction'] = array(
			// Add payment type defined in Novalnet.
			'payment_type'   => novalnet()->get_payment_types( $this->id ),

			// Add test mode value as 1/ 0 based on configuration value.
			'test_mode'      => (int) ( 'yes' === $this->settings ['test_mode'] ),

			// Add Amount details.
			'amount'         => wc_novalnet_formatted_amount( $wc_order->get_total() ),
			'currency'       => get_woocommerce_currency(),

			// Add formated order number.
			'order_no'       => ltrim( $wc_order->get_order_number(), _x( '#', 'hash before order number', 'woocommerce' ) ),

			// Add System details.
			'system_name'    => 'wordpress-woocommerce',
			'system_version' => get_bloginfo( 'version' ) . '-' . WOOCOMMERCE_VERSION . '-NN' . NOVALNET_VERSION,
			'system_url'     => site_url(),
			'system_ip'      => wc_novalnet_get_ip_address( 'SERVER_ADDR' ),
		);

		if ( $is_change_payment ) {
			$parameters['transaction']['amount'] = 0;
		}

		$parameters['customer'] = novalnet()->helper()->get_customer_data( $wc_order );

		$parameters['custom'] ['lang'] = wc_novalnet_shop_language();

		if ( ! is_admin() && wc_novalnet_check_session() ) {

			if ( ! empty( $parameters['transaction'] ['order_no'] ) ) {
				WC()->session->set( 'formatted_order_no', $parameters['transaction'] ['order_no'] );
			}
			// Set current payment method in session.
			WC()->session->set( 'current_novalnet_payment', $this->id );

			$is_change_payment = (bool) WC()->session->__isset( 'novalnet_change_payment_method' );

			if ( ! WC_Novalnet_Validation::has_valid_customer_data( $parameters ) ) {
				throw new Exception( __( 'Please enter values in the required fields', 'woocommerce-novalnet-gateway' ) );
			}
		}

		if ( $this->supports( 'subscriptions' ) ) {

			// Set Subscription related parameters if available.
			$parameters = apply_filters( 'novalnet_generate_subscription_parameters', $parameters, $wc_order, $is_change_payment );
		}

		// Save customer note.
		$customer_given_note = $wc_order->get_customer_note();
		if ( ! empty( $customer_given_note ) ) {
			update_post_meta( $wc_order->get_id(), '_nn_customer_given_note', $customer_given_note );
		}

		return $parameters;
	}

	/**
	 * Form basic redirect payment parameters.
	 *
	 * @since 12.0.0
	 *
	 * @param array    $wc_order     The encode values.
	 * @param WC_Order $parameters   The parameters.
	 */
	public function redirect_payment_params( $wc_order, &$parameters ) {

		// Customize the shop return URL's based on payment process type.
		$parameters ['transaction']['return_url'] = esc_url( add_query_arg( 'wc-api', 'response_' . $this->id, apply_filters( 'novalnet_return_url', $this->get_return_url( $wc_order ) ) ) );

		// Send order number in input value.
		$parameters ['custom']['input1']    = 'nn_shopnr';
		$parameters ['custom']['inputval1'] = $wc_order->get_id();
	}

	/**
	 * Assigning the shop order process based on the
	 * Novalnet server response whether success / failure.
	 *
	 * @since 12.0.0
	 * @param string   $server_response The server response data.
	 * @param WC_Order $wc_order        The order object.
	 * @param bool     $is_webhook      The flag to notify the webhook action.
	 * @param bool     $is_scheduled_payment The flag to notify the scheduled_payment action.
	 *
	 * @return array|string
	 */
	public function check_transaction_status( $server_response, $wc_order, $is_webhook = false, $is_scheduled_payment = false ) {

		novalnet()->helper()->debug( 'Response successfully reached to shop for the order: ' . $wc_order->get_id(), $wc_order->get_id() );
		if ( WC_Novalnet_Validation::is_success_status( $server_response ) ) {
			return $this->transaction_success( $server_response, $wc_order, $is_webhook, $is_scheduled_payment );
		}
		return $this->transaction_failure( $server_response, $wc_order, $is_webhook, $is_scheduled_payment );
	}

	/**
	 * Transaction success process for completing the order.
	 *
	 * @since 12.0.0
	 * @param array    $server_response The server response data.
	 * @param WC_Order $wc_order        The order object.
	 * @param bool     $is_webhook      The flag to notify the webhook action.
	 * @param bool     $is_scheduled_payment The flag to notify the scheduled_payment action.
	 *
	 * @return array|string
	 */
	public function transaction_success( $server_response, $wc_order, $is_webhook, $is_scheduled_payment ) {

		// Store payment token (if applicable).
		$this->store_payment_token( $server_response ['transaction'], $wc_order );

		// Check order subscription renew order.
		$novalnet_renew_subscription = get_post_meta( $wc_order->get_id(), '_novalnet_renew_subscription', true );

		// Request sent to process change payment method in Novalnet server.
		if ( wc_novalnet_check_session() && WC()->session->__isset( 'novalnet_change_payment_method' ) ) {
			$wcs_order = new WC_Order( $wc_order->get_id() );

			// Update recurring payment process.
			if ( WC_Novalnet_Validation::check_string( $wc_order->get_payment_method() ) ) {

				do_action( 'novalnet_update_recurring_payment', $server_response, $wc_order->get_parent_id(), $this->id, $wcs_order );
			}

			$success_url = $this->get_return_url( $wcs_order );
			if ( ! empty( $server_response['transaction']['redirect_url'] ) ) {
				// Get success URL for change payment method.
				$data        = apply_filters( 'novalnet_subscription_change_payment_method_success_url', $wcs_order );
				$success_url = $data ['success_url'];
			}

			return $this->novalnet_redirect( $success_url );

			// Update comments with TID for normal payment.
		} elseif ( empty( novalnet()->request ['change_payment_method'] ) ) {
			// Form order comments.
			$transaction_comments = novalnet()->helper()->prepare_payment_comments( $server_response, $wc_order->get_id() );
			$customer_given_note  = '';
			if ( empty( $is_webhook ) && ! $is_scheduled_payment ) {
				$customer_given_note = $wc_order->get_customer_note();
			}

			if ( empty( $customer_given_note ) ) {
				$nn_customer_given_note = get_post_meta( $wc_order->get_id(), '_nn_customer_given_note', true );
				if ( ! empty( $nn_customer_given_note ) ) {
					$customer_given_note = $nn_customer_given_note;
				}
			}

			// Update order comments.
			novalnet()->helper()->update_comments( $wc_order, $transaction_comments, false, 'transaction_info', false );

			// Update comments for change payment method (initiated newly).
		} else {
			/* translators: %s: Message  */
			$message = wc_novalnet_format_text( sprintf( __( 'Successfully changed the payment method for next subscription on %s', 'woocommerce-novalnet-gateway' ), wc_novalnet_formatted_date() ) );

			// Update order comments.
			novalnet()->helper()->update_comments( $wc_order, $message, true, 'note', true );
		}

		if ( ! empty( $novalnet_renew_subscription ) && 1 === (int) $novalnet_renew_subscription ) {
			// Update recurring payment token when subscription renew.
			if ( WC_Novalnet_Validation::check_string( $wc_order->get_payment_method() ) && ! wcs_order_contains_early_renewal( $wc_order ) && wcs_order_contains_renewal( $wc_order ) ) {
				$subscriptions = wcs_get_subscriptions_for_renewal_order( $wc_order );
				foreach ( $subscriptions as $subscription ) {
					if ( WC_Novalnet_Validation::check_string( $subscription->get_payment_method() ) && ! $subscription->is_manual() ) {
						do_action( 'novalnet_update_recurring_payment', $server_response, $subscription->get_parent_id(), $this->id, $subscription );
					}
				}
			}
			delete_post_meta( $wc_order->get_id(), '_novalnet_renew_subscription', true );
		}

		$insert_data = array(
			'order_no'       => $wc_order->get_id(),
			'tid'            => $server_response['transaction']['tid'],
			'currency'       => get_woocommerce_currency(),
			'gateway_status' => $server_response['transaction']['status'],
			'payment_type'   => $this->id,
			'amount'         => wc_novalnet_formatted_amount( $wc_order->get_total() ),
		);

		$subs_id = '';
		if ( ! empty( $server_response ['subscription']['subs_id'] ) ) {
			$insert_data['subs_id'] = $server_response ['subscription']['subs_id'];
		}

		$insert_data['callback_amount'] = $insert_data ['amount'];

		if ( novalnet()->get_supports( 'instalment', $this->id ) ) {
			$insert_data ['additional_info'] = apply_filters( 'novalnet_store_instalment_data', $server_response );
		}
		if ( ( novalnet()->get_supports( 'pay_later', $this->id ) || 'novalnet_guaranteed_invoice' === $this->id || 'novalnet_instalment_invoice' === $this->id ) ) {
			if ( ! empty( $insert_data ['additional_info'] ) ) {
				$insert_data ['additional_info'] = wc_novalnet_serialize_data( wc_novalnet_unserialize_data( $insert_data ['additional_info'] ) + $server_response['transaction']['bank_details'] );
			} elseif ( ! empty( $server_response['transaction']['bank_details'] ) ) {
				$insert_data ['additional_info'] = wc_novalnet_serialize_data( $server_response['transaction']['bank_details'] );
			} elseif ( ! empty( $server_response['transaction']['nearest_stores'] ) ) {
				$insert_data ['additional_info'] = wc_novalnet_serialize_data( $server_response['transaction']['nearest_stores'] );
			}
		}

		update_post_meta( $wc_order->get_id(), '_novalnet_gateway_status', $server_response ['transaction'] ['status'] );

		if ( in_array( $insert_data['gateway_status'], array( 'PENDING', 'ON_HOLD' ), true ) ) {
			$insert_data ['callback_amount'] = 0;
		}

		if ( ! empty( $server_response['transaction']['checkout_js'] ) && ! empty( $server_response['transaction']['checkout_token'] ) ) {
			$overlay_details                    = array();
			$overlay_details ['checkout_js']    = $server_response['transaction']['checkout_js'];
			$overlay_details ['checkout_token'] = $server_response['transaction']['checkout_token'];
			update_post_meta( $wc_order->get_id(), '_nn_cp_checkout_token', wc_novalnet_serialize_data( $overlay_details ) );
		}

		if ( ! is_admin() && ! empty( WC()->session ) ) {
			// Unset the Novalnet sessions.
			wc_novalnet_unset_payment_session( $this->id );
		}
		// Insert the transaction details.
		novalnet()->db()->insert( $insert_data, 'novalnet_transaction_detail' );

		// Update Novalnet version while processing the current post id.
		update_post_meta( $wc_order->get_id(), '_nn_version', NOVALNET_VERSION );

		// Complete the payment process.
		$wc_order->payment_complete( $server_response['transaction']['tid'] );

		if ( ! empty( $transaction_comments ) ) {

			if ( empty( $customer_given_note ) ) {
				// Update customer note.
				$wc_order->set_customer_note( wc_novalnet_format_text( $transaction_comments ) );
			} else {
				// Update customer note.
				$wc_order->set_customer_note( $customer_given_note . PHP_EOL . wc_novalnet_format_text( $transaction_comments ) );
			}
			$wc_order->save();
		}

		// Handle subscription process.
		do_action( 'novalnet_handle_subscription_post_process', $wc_order->get_id(), $this->id, $server_response, $wc_order );

		// Log to notify order got success.
		novalnet()->helper()->debug( 'Transaction got completed successfully TID: ' . $server_response['transaction']['tid'], $wc_order->get_id(), $is_scheduled_payment );

		if ( ! empty( $is_webhook ) ) {
			return $transaction_comments;
		}

		return $this->novalnet_redirect( $this->get_return_url( $wc_order ) );
	}

	/**
	 * Transaction failure process which cancel the
	 * order and redirect to checkout page with error.
	 *
	 * @since 12.0.0
	 * @param array    $server_response The server response data.
	 * @param WC_Order $wc_order        The order object.
	 * @param bool     $is_webhook      The flag to notify the webhook action.
	 * @param bool     $is_scheduled_payment The flag to notify the scheduled_payment action.
	 *
	 * @return array
	 *
	 * @throws Exception For admin change payment method.
	 */
	public function transaction_failure( $server_response, $wc_order, $is_webhook = false, $is_scheduled_payment = false ) {

		// Get message.
		$message = wc_novalnet_response_text( $server_response );

		// Log to notify order got failed.
		novalnet()->helper()->debug( "Transaction got failed due to: $message", $wc_order->get_id(), $is_scheduled_payment );
		if ( wc_novalnet_check_session() && WC()->session->__isset( 'novalnet_change_payment_method' ) ) {

			// Update cancelled transaction payment method with old payment method.
			$old_payment_method = $wc_order->get_meta( '_old_payment_method' );
			$old_payment_title  = $wc_order->get_meta( '_old_payment_method_title' );
			update_post_meta( WC()->session->novalnet_change_payment_method, '_payment_method', $old_payment_method );
			update_post_meta( WC()->session->novalnet_change_payment_method, '_payment_method_title', $old_payment_title );

			// Update notice comments.
			/* translators: %s: Reason */
			$transaction_comments = sprintf( __( 'Your action to change payment method failed for the renewal order due to %s', 'woocommerce-novalnet-gateway' ), $message );

			// Update transaction comments.
			novalnet()->helper()->update_comments( $wc_order, $transaction_comments, true, 'note' );

		} else {
			// Form transaction comments.
			$transaction_comments = novalnet()->helper()->form_comments( $server_response, true );

			// Update transaction comments.
			novalnet()->helper()->update_comments( $wc_order, $transaction_comments, true, 'note', false );
			update_post_meta( $wc_order->get_id(), '_nn_version', NOVALNET_VERSION );

			if ( ! empty( $server_response ['transaction']['status'] ) ) {
				update_post_meta( $wc_order->get_id(), '_novalnet_gateway_status', $server_response ['transaction'] ['status'] );
			} elseif ( ! empty( $server_response ['status'] ) ) {
				update_post_meta( $wc_order->get_id(), '_novalnet_gateway_status', $server_response ['status'] );
			}

			// Cancel a order.
			$wc_order->update_status( 'failed' );
		}

		$url              = wc_get_checkout_url();
		$error_return_url = apply_filters( 'novalnet_error_return_url', $url );

		if ( ! empty( WC()->session ) ) {
			// Unset used sessions.
			WC()->session->__unset( 'novalnet_change_payment_method' );
			WC()->session->__unset( $this->id );
		}

		if ( ! is_admin() && ! empty( WC()->session ) ) {
			// Display message.
			$this->display_info( $message );
		}

		if ( ! empty( $is_webhook ) ) {
			return $transaction_comments;
		}

		// Reload the checkout when multicheckout failure payment.
		if ( class_exists( 'WooCommerce_Germanized_Pro' ) && get_option( 'woocommerce_gzdp_checkout_enable' ) === 'yes' ) {
			WC()->session->set( 'reload_checkout', 1 );
		}

		$do_redirect = WC()->session->get( 'googlepay_do_redirect' );
		if ( in_array( $this->id, array( 'novalnet_applepay', 'novalnet_googlepay' ), true ) && ( empty( $do_redirect ) || 'true' !== $do_redirect ) ) {
			return $this->novalnet_redirect( $message, 'error' );
		}

		// Redirecting to checkout page.
		return $this->novalnet_redirect( $error_return_url, 'error' );
	}


	/**
	 * Assigning initial reference.
	 * parameters in session to store in database.
	 *
	 * @since 12.0.0
	 *
	 * @param array $parameters The formed parameters.
	 */
	public function set_payment_token( &$parameters ) {

		$payment = $this->id;
		if ( ! empty( WC()->session->$payment [ 'wc-' . $payment . '-payment-token' ] ) && ! wc_novalnet_check_isset( WC()->session->$payment, 'wc-' . $payment . '-payment-token', 'new' ) ) {
			$token = WC_Payment_Tokens::get( WC()->session->$payment [ 'wc-' . $payment . '-payment-token' ] );
			$parameters ['transaction']['payment_data']['token'] = $token->get_reference_token();
			$parameters ['custom']['input2']                     = 'reference_tid';
			$parameters ['custom']['inputval2']                  = $token->get_reference_tid();
			$parameters ['custom']['input3']                     = 'reference_token';
			$parameters ['custom']['inputval3']                  = $token->get_reference_token();
		} elseif ( $this->supports( 'tokenization' ) && ! empty( WC()->session->$payment [ 'wc-' . $payment . '-new-payment-method' ] ) && wc_novalnet_check_isset( WC()->session->$payment, 'wc-' . $payment . '-new-payment-method', 'true' ) ) {
			$parameters ['transaction']['create_token'] = '1';
		}
	}

	/**
	 * Assigning initial reference.
	 * parameters in session to store in database.
	 *
	 * @since 12.0.0
	 *
	 * @param array $transaction The formed transaction.
	 * @param array $wc_order    The order object.
	 */
	public function store_payment_token( $transaction, $wc_order ) {
		if ( $this->supports( 'tokenization' ) ) {
			$payment_type = $this->id;

			if ( wc_novalnet_check_session() && WC()->session->novalnet_guaranteed_sepa_switch_payment ) {
				if ( 'novalnet_invoice' === $this->id ) {
					$payment_type = 'novalnet_guaranteed_invoice';
				} elseif ( 'novalnet_sepa' === $this->id ) {
					$payment_type = 'novalnet_guaranteed_sepa';
				}
			}

			if ( ! empty( $transaction ['payment_data'] ['token'] ) && ( ( empty( WC()->session->$payment_type [ 'wc-' . $payment_type . '-payment-token' ] ) || wc_novalnet_check_isset( WC()->session->$payment_type, 'wc-' . $payment_type . '-payment-token', 'new' ) ) && ! empty( WC()->session->$payment_type [ 'wc-' . $payment_type . '-new-payment-method' ] ) && wc_novalnet_check_isset( WC()->session->$payment_type, 'wc-' . $payment_type . '-new-payment-method', 'true' ) ) ) {
				$payment_data = $transaction['payment_data'];

				$token = new WC_Payment_Token_Novalnet();
				$token->delete_duplicate_tokens( $payment_data, $payment_type );

				$token->set_token( $payment_data ['token'] );
				$token->set_reference_token( $payment_data ['token'] );
				$token->set_reference_tid( $transaction['tid'] );
				$token->set_gateway_id( $payment_type );
				$token->store_token_data( $payment_type, $payment_data, $token );
				$token->set_user_id( get_current_user_id() );
				$token->save();

				$wc_order->add_payment_token( $token );
			}
		}
	}

	/**
	 * Grab and display our saved payment methods.
	 *
	 * @since 12.0.0
	 */
	public function saved_payment_methods() {

		$tokens = $this->get_tokens();

		// Merge both guaranteed & non-guaranteed SEPA tokens together.
		if ( 'novalnet_sepa' === $this->id ) {
			$tokens = array_merge( $tokens, WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'novalnet_guaranteed_sepa' ), WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'novalnet_instalment_sepa' ) );
		} elseif ( 'novalnet_guaranteed_sepa' === $this->id ) {
			$tokens = array_merge( $tokens, WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'novalnet_sepa' ), WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'novalnet_instalment_sepa' ) );
		} elseif ( 'novalnet_instalment_sepa' === $this->id ) {
			$tokens = array_merge( $tokens, WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'novalnet_sepa' ), WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), 'novalnet_guaranteed_sepa' ) );
		}

		$html            = '<ul class="woocommerce-SavedPaymentMethods wc-saved-payment-methods" data-count="' . esc_attr( count( $tokens ) ) . '">';
		$is_token_exists = array(
			'novalnet_paypal'          => false,
			'novalnet_sepa'            => false,
			'novalnet_cc'              => false,
			'novalnet_guaranteed_sepa' => false,
			'novalnet_instalment_sepa' => false,
		);
		foreach ( $tokens as $token ) {

			$gateway_status       = novalnet()->db()->get_entry_by_tid( $token->get_reference_tid() );
			$saved_payment_list   = '';
			$show_saved_card_data = true;
			if ( 'novalnet_paypal' === $this->id && 'novalnet_paypal' === $token->get_gateway_id() && empty( $token->get_paypal_account() ) ) {
				$show_saved_card_data = false;
			}

			// Restrict token display for PayPal on-hold token.
			if ( ! empty( $show_saved_card_data ) ) {

				$saved_payment_list = sprintf(
					'<li class="woocommerce-SavedPaymentMethods-token">
                        <input id="wc-%1$s-payment-token-%2$s" type="radio" name="wc-%1$s-payment-token" value="%2$s" style="width:auto;" class="woocommerce-SavedPaymentMethods-tokenInput" %4$s />
                        <label for="wc-%1$s-payment-token-%2$s">%3$s</label>
                    </li>',
					esc_attr( $this->id ),
					esc_attr( $token->get_id() ),
					$token->get_display_name(),
					checked( $token->is_default(), true, false )
				);

				$html .= apply_filters( 'woocommerce_payment_gateway_get_saved_payment_method_option_html', $saved_payment_list, $token, $this );

				$is_token_exists[ $this->id ] = true;
			}
		}

		if ( ! empty( $is_token_exists[ $this->id ] ) && true === $is_token_exists[ $this->id ] ) {
			$html .= $this->get_new_payment_method_option_html();
		}
		$html .= '</ul>';

        echo apply_filters( 'wc_payment_gateway_form_saved_payment_methods_html', $html, $this ); // @codingStandardsIgnoreLine
	}


	/**
	 * Processing redirect payment process.
	 *
	 * @since 12.0.0
	 *
	 * @return array|string
	 */
	public function process_redirect_payment_response() {
		$post_id  = WC()->session->get( 'novalnet_post_id' );
		$wc_order = new WC_Order( $post_id );
		if ( WC_Novalnet_Validation::is_success_status( novalnet()->request ) ) {
			$nn_status = get_post_meta( $post_id, '_novalnet_gateway_status', true );
			if ( ! empty( $nn_status ) && 'FAILURE' !== $nn_status ) {
				return $this->novalnet_redirect( $this->get_return_url( $wc_order ) );
			} elseif ( WC_Novalnet_Validation::is_valid_checksum( novalnet()->request, WC()->session->get( 'novalnet_txn_secret' ), WC_Novalnet_Configuration::get_global_settings( 'key_password' ) ) ) {
				$parameters      = array(
					'transaction' => array(
						'tid' => novalnet()->request ['tid'],
					),
					'custom'      => array(
						'lang' => wc_novalnet_shop_language(),
					),
				);
				$endpoint        = novalnet()->helper()->get_action_endpoint( 'transaction_details' );
				$server_response = novalnet()->helper()->submit_request( $parameters, $endpoint, array( 'post_id' => $post_id ) );

				if ( empty( $wc_order ) && ! empty( $server_response ['custom']['nn_shopnr'] ) ) {
					$post_id  = $server_response ['custom']['nn_shopnr'];
					$wc_order = new WC_Order( $post_id );
				}
				// Checks transaction status.
				return $this->check_transaction_status( $server_response, $wc_order );
			} else {
				$server_response                          = novalnet()->helper()->format_querystring_response( novalnet()->request );
				$server_response['result']['status_text'] = __( 'Please note some data has been changed while redirecting', 'woocommerce-novalnet-gateway' );
			}
		}

		return $this->transaction_failure( novalnet()->request, $wc_order, false, false );
	}

	/**
	 * Assigning basic details in gateway instance.
	 *
	 * @since 12.0.0
	 */
	public function assign_basic_payment_details() {

		// Get language.
		$language = strtolower( wc_novalnet_shop_language() );

		// Initiate payment settings.
		$this->init_settings();

		$payment_text = WC_Novalnet_Configuration::get_payment_text( $this->id );

		// Payment title in back-end.
		$this->method_title = wc_novalnet_get_payment_text( $this->settings, $payment_text, $language, $this->id, 'admin_title' );

		// Payment title in front-end.
		$this->title = wc_novalnet_get_payment_text( $this->settings, $payment_text, $language, $this->id );

		// Payment description.
		$this->description = wc_novalnet_get_payment_text( $this->settings, $payment_text, $language, $this->id, 'description' );

		// Gateway view transaction URL.
		$this->view_transaction_url = 'https://admin.novalnet.de';

		if ( ! empty( $payment_text['admin_desc'] ) ) {
			$this->method_description = $payment_text['admin_desc'];
		}

		// Basic payment supports.
		$this->supports = array(
			'refunds',
			'products',
		);

		// Display payment configuration fields.
		$this->init_form_fields();

		// Handle the payment selection & complete process.
		if ( ! is_admin() ) {
			$this->chosen = ( wc_novalnet_check_session() && WC()->session->__isset( 'chosen_payment_method' ) && WC()->session->chosen_payment_method === $this->id );
			if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0', '>' ) ) {
				add_filter( 'woocommerce_payment_complete_order_status', array( &$this, 'get_order_status' ), 10, 3 );
			} else {
				add_filter( 'woocommerce_payment_complete_order_status', array( &$this, 'get_order_status' ), 10, 2 );
			}

			// Display error message in checkout page.
			add_action( 'woocommerce_check_cart_items', array( $this, 'show_error_message_on_redirect' ) );
		}

		if ( in_array( $this->id, array( 'novalnet_applepay', 'novalnet_googlepay' ), true ) ) {
			if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0', '>' ) ) {
				add_filter( 'woocommerce_payment_complete_order_status', array( &$this, 'get_order_status' ), 10, 3 );
			} else {
				add_filter( 'woocommerce_payment_complete_order_status', array( &$this, 'get_order_status' ), 10, 2 );
			}
		}

		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );

		// Restrict Add payment method option.
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'restrict_add_payment_method' ) );

		// Customize E-mail.
		add_action( 'woocommerce_email_before_order_table', array( $this, 'add_email_instructions' ), 10, 2 );

		// Check extra Line break for Customer note in checkout.
		add_action( 'woocommerce_order_details_after_order_table_items', array( &$this, 'align_transaction_details' ), 10, 2 );

		// Do Payment related validation before save the settings.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Customize front-end my-account option.
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'filter_my_account_action' ), 10, 2 );

		// Customize thank you page.
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page_instructions' ) );
	}

	/**
	 * Check whether a given subscription is using reference transactions and if so process the payment.
	 *
	 * @param int    $amount        Order total.
	 * @param object $renewal_order The renewal order.
	 */
	public function scheduled_subscription_payment( $amount, $renewal_order ) {

		// Log to notify order got success.
		novalnet()->helper()->debug( 'Executing scheduled payment action', $renewal_order->get_id(), true );

		// Get subscription object.
		$subscription    = new WC_Subscription( $renewal_order->get_meta( '_subscription_renewal' ) );
		$subscription_id = $subscription->get_id();
		
		$is_shop_based_subs = apply_filters( 'novalnet_check_is_shop_scheduled_subscription', $subscription_id );
		
		if ( ! $is_shop_based_subs ) {
			// Log to notify order got success.
			novalnet()->helper()->debug( 'Scheduled payment action stopped for gateway scheduled subscriptions', $renewal_order->get_id(), true );
			return false;
		}
		
		// Get order object.
		$wc_order       = new WC_Order( $subscription->get_parent_id() );
		$tid            = novalnet()->db()->get_subs_data_by_order_id( $subscription->get_parent_id(), $subscription_id, 'tid' );
		$this->settings = WC_Novalnet_Configuration::get_payment_settings( $subscription->get_payment_method() );
		// Generate basic parameters.
		$parameters                              = $this->generate_basic_parameters( $renewal_order, false );
		$parameters ['transaction'] ['amount']   = wc_novalnet_formatted_amount( $amount );
		$parameters ['transaction'] ['order_no'] = $renewal_order->get_id();

		if ( in_array( $this->id, array( 'novalnet_sepa', 'novalnet_cc', 'novalnet_paypal', 'novalnet_guaranteed_sepa', 'novalnet_guaranteed_invoice', 'novalnet_applepay', 'novalnet_googlepay' ), true ) ) {

			$parameters ['transaction'] ['payment_data'] ['payment_ref'] = $tid;
			$is_shop_based_subs = apply_filters( 'novalnet_check_is_shop_scheduled_subscription', $subscription_id );
			$recurring_tid      = novalnet()->db()->get_subs_data_by_order_id( $subscription->get_parent_id(), $subscription_id, 'recurring_tid' );
			if ( $is_shop_based_subs && ! empty( $recurring_tid ) ) {
				$parameters ['transaction'] ['payment_data'] ['payment_ref'] = $recurring_tid;
			}
			$shop_subs_token = novalnet()->db()->get_subs_data_by_order_id( $subscription->get_parent_id(), $subscription_id, 'nn_txn_token' );
			if ( ! empty( $shop_subs_token ) ) {
				$parameters ['transaction'] ['payment_data'] = array(
					'token' => $shop_subs_token,
				);
			}
		}

		// Submit the given request.
		$response = novalnet()->helper()->submit_request(
			$parameters,
			'https://payport.novalnet.de/v2/payment',
			array(
				'post_id'              => $renewal_order->get_id(),
				'is_scheduled_payment' => true,
			)
		);

		update_post_meta( $renewal_order->get_id(), '_novalnet_gateway_status', $response ['transaction']['status'] );

		$payment_gateway = wc_get_payment_gateway_by_order( $subscription );

		$subs_customer_note = get_post_meta( $subscription->get_parent_id(), '_nn_customer_given_note', true );
		if ( ! empty( $subs_customer_note ) ) {
			update_post_meta( $renewal_order->get_id(), '_nn_customer_given_note', $subs_customer_note );
		}

		if ( method_exists( $payment_gateway, 'check_transaction_status' ) ) {
			$payment_gateway->check_transaction_status( $response, $renewal_order, 'subscription_renewals', true );
		} else {
			$data = array( 'message' => 'Payment not found in the order' );
			wp_send_json( $data, 200 );
		}
	}

	/**
	 * Handle validations before update the configuration into tables
	 *
	 * @since 12.0.0.
	 */
	public function process_admin_options() {

		// Do backend validation.
		WC_Novalnet_Validation::backend_validation();

		// Call core process_admin_options().
		parent::process_admin_options();

	}

	/**
	 * Restrict the add payment method option for tokenization
	 *
	 * @since 12.0.0
	 *
	 * @param array $gateways The supported gateways.
	 *
	 * @return array
	 */
	public function restrict_add_payment_method( $gateways ) {
		global $wp;
		$page_id = wc_get_page_id( 'myaccount' );

		if ( ( $page_id && is_page( $page_id ) && isset( $wp->query_vars['add-payment-method'] ) ) ) {
			foreach ( novalnet()->get_supports( 'tokenization' ) as $payment_type ) {
				if ( isset( $gateways [ $payment_type ] ) ) {
					unset( $gateways [ $payment_type ] );
				}
			}
		}
		return $gateways;
	}

		/**
		 * Customizing shop thankyou page.
		 *
		 * @since 12.0.0
		 *
		 * @param int $wc_order_id The order ID.
		 *
		 * @return void
		 */
	public function thankyou_page_instructions( $wc_order_id ) {

		$language = strtolower( wc_novalnet_shop_language() );

		// Check Novalnet payment.
		if ( ! empty( $this->settings [ 'instructions_' . $language ] ) ) {
			echo wp_kses_post( wpautop( wptexturize( $this->settings [ 'instructions_' . $language ] ) ) );
		}

		$checkout_token = get_post_meta( $wc_order_id, '_nn_cp_checkout_token', true );

		if ( ! empty( $checkout_token ) ) {
			$overlay_details = wc_novalnet_unserialize_data( $checkout_token );
			if ( ! empty( $overlay_details ['checkout_js'] ) && ! empty( $overlay_details ['checkout_token'] ) ) {
				wp_enqueue_script( 'woocommerce-novalnet-gateway-external-script-barzahlen', esc_url( $overlay_details ['checkout_js'] . '?token=' . esc_attr( $overlay_details ['checkout_token'] ) ), array(), NOVALNET_VERSION, false );
				echo wp_kses(
					"<button id='barzahlen_button' class='bz-checkout-btn'>" . __( 'Pay now with Barzahlen', 'woocommerce-novalnet-gateway' ) . '</button>',
					array(
						'button' => array(
							'id'    => true,
							'class' => true,
						),
					)
				);
			}
		}
	}

	/**
	 * Shows the TESTMODE notification.
	 *
	 * @since 12.0.0
	 *
	 * @return void
	 */
	public function test_mode_notification() {
		$html = '';
		if ( wc_novalnet_check_isset( $this->settings, 'test_mode', 'yes' ) ) {
			$html = '<p><div class="novalnet-test-mode">' . __( 'TESTMODE', 'woocommerce-novalnet-gateway' ) . '</div></p>';
		}

		echo wp_kses(
			wpautop( '<div id="' . $this->id . '_error" role="alert"></div><div class="clear"></div>' . $html . '<br/>' ),
			array(
				'div' => array(
					'class' => true,
					'id'    => true,
				),
				'br'  => array(),
			)
		);
	}

	/**
	 * Show description
	 *
	 * @since 12.0.0
	 *
	 * @param array $additional_info Additiona information to be displayed in payment checkout.
	 *
	 * @return void
	 */
	public function show_description( $additional_info = array() ) {

		// Unset payment session for ignored payments.
		if ( wc_novalnet_check_session() && WC()->session->__isset( 'chosen_payment_method' ) && WC()->session->chosen_payment_method !== $this->id ) {
			WC()->session->__unset( $this->id );
		}

		// Hide multiple payment fields.
		wc_novalnet_hide_multiple_payment();

		$contents = array();

		$contents[] = $this->description;
		if ( ! empty( $additional_info ) ) {
			$contents = array_merge( $contents, $additional_info );
		}

		if ( ! empty( $contents ) ) {
			if ( count( $contents ) > 1 ) {
				$text = '<ul>';
				foreach ( $contents as $content ) {
					if ( ! empty( $content ) ) {
						$text .= '<li>' . $content . '</li>';
					}
				}
				$text .= '</ul>';
			} elseif ( ! empty( $contents['0'] ) ) {
				$text = $contents['0'];
			}

			if ( ! empty( $text ) ) {
				echo wp_kses(
					wpautop( '<div class="novalnet-info-box">' . $text . '</div><br/>' ),
					array(
						'div'    => array(
							'class'   => true,
							'id'      => true,
							'style'   => true,
							'display' => true,
						),
						'a'      => array(
							'id'      => true,
							'onclick' => true,
							'style'   => true,
						),
						'p'      => array(),
						'strong' => array(),
						'ul'     => array(),
						'li'     => array(),
					)
				);
			}
		}
	}

	/**
	 * Redirects to the given URL.
	 *
	 * @since 12.0.0
	 *
	 * @param string $url      The url value.
	 * @param string $redirect The result type.
	 *
	 * @return array
	 */
	public function novalnet_redirect( $url = '', $redirect = 'success' ) {
		if ( '' === $url ) {
			$url = wc_get_checkout_url();
		}
		return array(
			'result'   => $redirect,
			'redirect' => $url,
		);
	}

	/**
	 * To display the success and failure
	 * messages.
	 *
	 * @since 12.0.0
	 *
	 * @param string $message      The message value.
	 * @param string $message_type The message type value.
	 */
	public function display_info( $message, $message_type = 'error' ) {
		if ( is_admin() ) {
			WC_Admin_Meta_Boxes::add_error( $message );
		} else {
			wc_add_notice( $message, $message_type );
		}
	}

	/**
	 * Checks and unset the other Novalnet sessions.
	 *
	 * @since 12.0.0
	 */
	public function unset_other_payment_session() {
		if ( wc_novalnet_check_session() ) {
			foreach ( array_keys( novalnet()->get_payment_types() ) as $payment ) {
				WC()->session->__unset( $payment . '_dob' );
				WC()->session->__unset( $this->id . '_dob' );
				WC()->session->__unset( $this->id . '_show_dob' );
				if ( $this->id !== $payment ) {
					WC()->session->__unset( $payment );
					WC()->session->__unset( 'current_novalnet_payment' );
					WC()->session->__unset( $payment . '_switch_payment' );
					WC()->session->__unset( 'novalnet_post_id' );
				}
			}
		}
	}

	/**
	 * Get endpoint url to send request.
	 *
	 * @since 12.0.0
	 *
	 * @return string
	 */
	public function get_payment_endpoint() {

		$action = 'payment';
		if ( WC_Novalnet_Validation::is_authorize( $this->id, WC()->cart->total, $this->settings ) ) {
			$action = 'authorize';
		}
		return novalnet()->helper()->get_action_endpoint( $action );
	}

	/**
	 * Get endpoint url to send request for admin orders.
	 *
	 * @since 12.0.0
	 *
	 * @param WC_Order $total   The order object.
	 *
	 * @return string
	 */
	public function get_payment_endpoint_admin_orders( $total ) {

		$action = 'payment';
		if ( WC_Novalnet_Validation::is_authorize( $this->id, $total, $this->settings ) ) {
			$action = 'authorize';
		}
		return novalnet()->helper()->get_action_endpoint( $action );
	}

	/**
	 * Outputs a checkbox for saving a new payment method to the database.
	 *
	 * @since 12.0.0
	 */
	public function save_payment_method_checkbox() {
		printf(
			'<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
                <input id="wc-%1$s-new-payment-method" name="wc-%1$s-new-payment-method" type="checkbox" value="true" checked style="width:auto;" />
                <label for="wc-%1$s-new-payment-method" style="display:inline;">%2$s</label>
            </p>',
			esc_attr( $this->id ),
			esc_html( __( 'Save for future purchase', 'woocommerce-novalnet-gateway' ) )
		);
	}

	/**
	 * Handle payment switch
	 *
	 * @since 12.0.0
	 *
	 * @param WC_Order $wc_order   The order object.
	 * @param array    $parameters The formed parameters.
	 */
	public function handle_payment_switch( $wc_order, &$parameters ) {
		if ( wc_novalnet_check_session() && WC()->session->__isset( $this->id . '_switch_payment' ) && wc_novalnet_check_isset( $this->settings, 'force_normal_payment', 'yes' ) ) {
			$previous_payment = $this->id;
			$this->id         = wc_novalnet_switch_payment( $this->id );
			$this->settings   = WC_Novalnet_Configuration::get_payment_settings( $this->id );
			if ( 'novalnet_guaranteed_sepa' === $previous_payment ) {
				$post_data_key = array(
					'novalnet_guaranteed_sepa' => array(
						'novalnet_guaranteed_sepa_iban',
						'novalnet_guaranteed_sepa_dob',
						'novalnet_guaranteed_sepa_bic',
						'wc-novalnet_guaranteed_sepa-new-payment-method',
						'wc-novalnet_guaranteed_sepa-payment-token',
					),
				);
				foreach ( $post_data_key[ $previous_payment ] as $value ) {
					if ( isset( novalnet()->request[ $value ] ) && strpos( $value, $previous_payment ) !== false ) {
						$new_index                        = str_replace( $previous_payment, $this->id, $value );
						novalnet()->request[ $new_index ] = novalnet()->request[ $value ];
					}
				}
			}
			$parameters ['transaction'] ['payment_type'] = novalnet()->get_payment_types( $this->id );
			WC()->session->set( 'current_novalnet_payment', $this->id );

			// Add due date parameter.
			if ( ! empty( $this->settings ['payment_duration'] ) ) {
				$parameters ['transaction']['due_date'] = wc_novalnet_format_due_date( $this->settings ['payment_duration'] );
			}

			$payment_text         = WC_Novalnet_Configuration::get_payment_text( $this->id );
			$payment_method_title = wc_novalnet_get_payment_text( $this->settings, $payment_text, wc_novalnet_shop_language(), $this->id, 'title' );
			$wc_order->set_payment_method_title( $payment_method_title );
			$wc_order->set_payment_method( $this->id );

			$is_subscription = apply_filters( 'novalnet_check_is_subscription', $wc_order );
			if ( $is_subscription ) {
				$subscriptions = wcs_get_subscriptions_for_order( $wc_order->get_id() );
				if ( ! empty( $subscriptions ) ) {
					foreach ( $subscriptions as $subscription ) {
						$subscription->set_payment_method_title( $payment_method_title );
						$subscription->set_payment_method( $this->id );
						$subscription->save();
					}
				}
			}
		}
	}

	/**
	 * Returns the order status.
	 *
	 * @param string   $wc_order_status The order status.
	 * @param int      $wc_order_id     The post ID.
	 * @param WC_Order $wc_order   The order object.
	 *
	 * @return string
	 */
	public function get_order_status( $wc_order_status, $wc_order_id, $wc_order = '' ) {

		if ( empty( $wc_order ) ) {
			$wc_order = new WC_Order( $wc_order_id );
		}

		if ( WC_Novalnet_Validation::check_string( $wc_order->get_payment_method() ) ) {
			$gateway_status = get_post_meta( $wc_order_id, '_novalnet_gateway_status', true );
			novalnet()->helper()->status_mapper( $gateway_status );

			if ( ! empty( $gateway_status ) ) {
				if ( 'PENDING' === $gateway_status && ! novalnet()->get_supports( 'pay_later', $wc_order->get_payment_method() ) ) {
					$wc_order_status = 'wc-pending';
				} elseif ( 'ON_HOLD' === $gateway_status ) {
					$wc_order_status = 'wc-on-hold';
				} else {
					$settings = WC_Novalnet_Configuration::get_payment_settings( $wc_order->get_payment_method() );
					if ( ! empty( $settings ['order_success_status'] ) ) {
						$wc_order_status = $settings ['order_success_status'];
					}
				}
			}
			if ( 'PENDING' === $gateway_status && ( novalnet()->get_supports( 'pay_later', $wc_order->get_payment_method() ) && 'novalnet_invoice' !== $wc_order->get_payment_method() ) ) {
				// get order items = each product in the order.
				$items = $wc_order->get_items();

				// Set variable.
				$found = false;

				foreach ( $items as $item ) {
					// Get product id.
					$product = wc_get_product( $item['product_id'] );

					// Is virtual.
					$is_virtual = $product->is_virtual();

					// Is_downloadable.
					$is_downloadable = $product->is_downloadable();

					if ( $is_virtual || $is_downloadable ) { // true and break loop.
						$found = true;
						break;
					}
				}

				// true.
				if ( $found ) {
					$wc_order_status = 'wc-on-hold';
				}
			}
		}
		return $wc_order_status;
	}

	/**
	 * Set error notice for payment failure to display in checkout page.
	 *
	 * @since 12.0.0
	 */
	public function show_error_message_on_redirect() {

		if ( is_checkout() && wc_notice_count( 'error' ) > 0 && wc_novalnet_check_session() && WC()->session->__isset( 'chosen_payment_method' ) && WC_Novalnet_Validation::check_string( WC()->session->chosen_payment_method ) ) {

			// Show non-cart errors.
			wc_print_notices();
		}
	}

	/**
	 * Restricting the Pay/Cancel option shop front-end
	 * if succesfull transaction has pending status.
	 *
	 * @since 12.0.0
	 *
	 * @param array    $actions The actions data.
	 * @param WC_Order $wc_order   The order object.
	 *
	 * @return array
	 */
	public function filter_my_account_action( $actions, $wc_order ) {

		if ( WC_Novalnet_Validation::check_string( $wc_order->get_payment_method() ) ) {

			if ( $wc_order->has_status( 'pending' ) ) {
				unset( $actions['pay'] );
			}

			// Unset user order cancel option.
			if ( ! empty( $actions['cancel'] ) ) {
				unset( $actions['cancel'] );
			}
		}
		return $actions;
	}

	/**
	 * Update payment method if transactions id given
	 *
	 * @since 12.0.0
	 *
	 * @param WC_Order $wc_order   The order object.
	 * @param Array    $novalnet_response Novalnet Server Response.
	 */
	public function update_payment_method_backend_order( $wc_order, $novalnet_response ) {
		$payment_types = array_flip( novalnet()->get_payment_types() );

		$payment_id = $payment_types[ $novalnet_response['transaction'] ['payment_type'] ];
		WC()->session->set( 'current_novalnet_payment', $payment_id );
		$payment_text         = WC_Novalnet_Configuration::get_payment_text( $payment_id );
		$payment_method_title = wc_novalnet_get_payment_text( array(), $payment_text, wc_novalnet_shop_language(), $payment_id, 'title' );
		$wc_order->set_payment_method_title( $payment_method_title );
		$wc_order->set_payment_method( $payment_id );
	}

	/**
	 * Update payment method if transactions id given
	 *
	 * @since 12.0.0
	 *
	 * @param WC_Order $wc_order   The order object.
	 * @param Array    $novalnet_response Novalnet Server Response.
	 */
	public function handle_order_id_update( $wc_order, &$novalnet_response ) {
		// Form API request.
		$parameters = array(
			'transaction' => array(
				'tid'      => $novalnet_response['transaction']['tid'],
				'order_no' => $wc_order->get_id(),
			),
			'custom'      => array(
				'lang'         => wc_novalnet_shop_language(),
				'shop_invoked' => 1,
			),
		);
		if ( isset( $novalnet_response['transaction']['invoice_ref'] ) ) {
			$parameters['transaction'] = array(
				'invoice_no'  => $wc_order->get_id(),
				'invoice_ref' => "BNR-{$novalnet_response['merchant']['project']}-{$wc_order->get_id()}",
			);
		}

		// Send API request call.
		$response = novalnet()->helper()->submit_request( $parameters, novalnet()->helper()->get_action_endpoint( 'transaction_update' ), array( 'post_id' => $wc_order->get_id() ) );
	}

	/**
	 * Update Order details if transactions id given
	 *
	 * @since 12.0.0
	 *
	 * @param WC_Order $wc_order   The order object.
	 * @param Array    $novalnet_response Novalnet Server Response.
	 *
	 * @return void
	 */
	public function update_wc_order( $wc_order, &$novalnet_response ) {
		$wc_order->set_billing_first_name( $novalnet_response['customer']['first_name'] );
		$wc_order->set_billing_last_name( $novalnet_response['customer']['last_name'] );
		$wc_order->set_billing_email( $novalnet_response['customer']['email'] );
		$wc_order->set_billing_phone( $novalnet_response['customer']['tel'] );
		$wc_order->set_billing_country( $novalnet_response['customer']['billing']['country_code'] );
		$wc_order->set_billing_postcode( $novalnet_response['customer']['billing']['zip'] );
		$wc_order->set_billing_city( $novalnet_response['customer']['billing']['city'] );
		if ( isset( $novalnet_response['customer']['billing']['house_no'] ) && ! empty( $novalnet_response['customer']['billing']['house_no'] ) ) {
			$wc_order->set_billing_address_1( $novalnet_response['customer']['billing']['street'] );
			$wc_order->set_billing_address_2( $novalnet_response['customer']['billing']['house_no'] );
		} else {
			$wc_order->set_billing_address_1( $novalnet_response['customer']['billing']['street'] );
		}

		$wc_order->set_shipping_first_name( $novalnet_response['customer']['last_name'] );
		$wc_order->set_shipping_last_name( $novalnet_response['customer']['last_name'] );
		$wc_order->set_shipping_phone( $novalnet_response['customer']['tel'] );

		if ( isset( $novalnet_response['customer']['shipping'] ) && ! empty( $novalnet_response['customer']['shipping'] ) ) {
			$wc_order->set_shipping_country( $novalnet_response['customer']['shipping']['country_code'] );
			$wc_order->set_shipping_postcode( $novalnet_response['customer']['shipping']['zip'] );
			$wc_order->set_shipping_city( $novalnet_response['customer']['shipping']['city'] );
			if ( isset( $novalnet_response['customer']['shipping']['house_no'] ) && ! empty( $novalnet_response['customer']['shipping']['house_no'] ) ) {
				$wc_order->set_shipping_address_1( $novalnet_response['customer']['shipping']['street'] );
				$wc_order->set_shipping_address_2( $novalnet_response['customer']['shipping']['house_no'] );
			} else {
				$wc_order->set_shipping_address_1( $novalnet_response['customer']['shipping']['street'] );
			}
		} else {
			$wc_order->set_shipping_country( $novalnet_response['customer']['billing']['country_code'] );
			$wc_order->set_shipping_postcode( $novalnet_response['customer']['billing']['zip'] );
			$wc_order->set_shipping_city( $novalnet_response['customer']['billing']['city'] );
			if ( isset( $novalnet_response['customer']['billing']['house_no'] ) && ! empty( $novalnet_response['customer']['billing']['house_no'] ) ) {
				$wc_order->set_shipping_address_1( $novalnet_response['customer']['billing']['street'] );
				$wc_order->set_shipping_address_2( $novalnet_response['customer']['billing']['house_no'] );
			} else {
				$wc_order->set_shipping_address_1( $novalnet_response['customer']['billing']['street'] );
			}
		}
		$wc_order->set_total( wc_novalnet_shop_amount_format( $novalnet_response['transaction']['amount'] ) );
		$this->update_payment_method_backend_order( $wc_order, $novalnet_response );
		$this->handle_order_id_update( $wc_order, $novalnet_response );
		$wc_order->save();
	}
}

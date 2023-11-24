<?php
/**
 * Zero amount booking meta box.
 *
 * @author   Novalnet AG
 * @category Class WC_Novalnet_Meta_Box_Amount_Book
 * @package  woocommerce-novalnet-gateway/includes/admin/meta-boxes/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Novalnet_Meta_Box_Amount_Book Class
 */
class WC_Novalnet_Meta_Box_Amount_Book {


	/**
	 * Output the metabox
	 *
	 * @param WP_Post|WC_Order $post_or_order_object The Order object.
	 */
	public static function output( $post_or_order_object ) {
		$wc_order            = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
		$transaction_details = novalnet()->db()->get_transaction_details( $wc_order->get_id() );
		if ( ! empty( $transaction_details ) ) {
			include_once dirname( ( __FILE__ ) ) . '/views/html-novalnet-amount-book.php';
		}
	}

	/**
	 * Save meta box data
	 *
	 * @param WP_Post $post_id Post ID of the order.
	 */
	public static function save( $post_id ) {
		$message_type = '1';
		if ( ! empty( novalnet()->request['novalnet_book_order_amount'] ) && 'yes' === novalnet()->request['novalnet_book_order_amount'] ) {
			$txn_booking_amount = sanitize_text_field( novalnet()->request ['novalnet_book_amount'] );
			$txn_booking_amount = wc_novalnet_formatted_amount( wc_format_decimal( $txn_booking_amount ) );
			$wc_order           = wc_get_order( $post_id );
			$payment_gateway    = wc_get_payment_gateway_by_order( $wc_order );
			if ( method_exists( $payment_gateway, 'process_payment' ) ) {
				$additional_info                       = novalnet()->db()->get_entry_by_order_id( $post_id, 'additional_info' );
				$parameters                            = $payment_gateway->generate_basic_parameters( $wc_order, false );
				$parameters ['transaction'] ['amount'] = $txn_booking_amount;
				if ( isset( $additional_info['nn_booking_ref_token'] ) && ! empty( $additional_info['nn_booking_ref_token'] ) ) {
					$parameters ['transaction'] ['payment_data']['token'] = $additional_info['nn_booking_ref_token'];
				} else {
					$parameters ['transaction'] ['payment_data'] ['payment_ref'] = $wc_order->get_transaction_id();
				}
				$parameters ['custom']['shop_invoked'] = 1;
				$parameters ['custom']['input1']       = 'payment_booking_type';
				$parameters ['custom']['inputval1']    = 'zero_ref_order';

				// Submit the given request.
				$response = novalnet()->helper()->submit_request(
					$parameters,
					novalnet()->helper()->get_action_endpoint( 'payment' ),
					array(
						'post_id' => $post_id,
					)
				);

				if ( ! empty( $response ['transaction']['status'] ) ) {
					novalnet()->helper()->novalnet_update_wc_order_meta( $wc_order, '_novalnet_gateway_status', $response ['transaction']['status'], true );
				}

				if ( WC_Novalnet_Validation::is_success_status( $response ) ) {
					novalnet()->helper()->update_payment_booking( $post_id, $wc_order, $response, $additional_info );
				} else {
					$message = wc_novalnet_response_text( $response );
					WC_Admin_Meta_Boxes::add_error( $message );
					novalnet()->helper()->debug( 'Payment Booking failed due to : ' . $message, $post_id, true );
				}
			} else {
				WC_Admin_Meta_Boxes::add_error( __( 'Payment method currently not available.', 'woocommerce-novalnet-gateway' ) );
				novalnet()->helper()->debug( 'Zero amount booking payment gateway not has required method.', $post_id, true );
			}
			// Redirect to order view page.
			wc_novalnet_safe_redirect(
				add_query_arg(
					array(
						'action'  => 'edit',
						'post'    => $post_id,
						'message' => $message_type,
					)
				)
			);
		}
	}
}

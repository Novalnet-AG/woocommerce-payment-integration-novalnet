<?php
/**
 * Amount refund
 *
 * Handling amount update process
 *
 * @author   Novalnet
 * @category Admin
 * @package  woocommerce-novalnet-gateway/includes/admin/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Novalnet_Amount_Refund Class
 */
class WC_Novalnet_Amount_Refund {

	/**
	 * Execute Refund action
	 *
	 * @since 12.0.0.
	 *
	 * @param int    $wc_order_id   The order number.
	 * @param double $refund_amount The total amount of refund.
	 * @param string $reason        The reason for refund.
	 * @param string $refund_tid    The refund tid.
	 * @param string $manual_refund The manual refund tid.
	 *
	 * @return boolean
	 */
	public static function execute( $wc_order_id, $refund_amount = null, $reason = '', $refund_tid = '', $manual_refund = false ) {

		$wc_order = wc_get_order( $wc_order_id );
		// Fetch transaction details.
		$transaction_details = novalnet()->db()->get_transaction_details( $wc_order_id );

		if ( ! $refund_amount || ! WC_Novalnet_Validation::is_valid_digit( $refund_amount ) ) {
			return new WP_Error( 'error', __( 'Invalid refund amount', 'woocommerce-novalnet-gateway' ) );
		} elseif ( (int) $transaction_details['amount'] <= (int) $transaction_details['refunded_amount'] ) {
			return __( 'Refund has been already initiated for the order', 'woocommerce-novalnet-gateway' );
		}

		// Override tid value (if received in argument).
		if ( '' !== $refund_tid ) {
			$transaction_details ['tid'] = $refund_tid;
		}

		$nn_credit_tid = novalnet()->helper()->novalnet_get_wc_order_meta( $wc_order, 'nn_credit_tid' );
		if ( ! empty( $nn_credit_tid ) ) {
			$transaction_details ['tid'] = $nn_credit_tid;
		}

		// Form API request.
		$parameters = array(
			'transaction' => array(
				'tid'    => $transaction_details['tid'],
				'amount' => $refund_amount,
				'reason' => $reason,
			),
			'custom'      => array(
				'lang'         => wc_novalnet_shop_language(),
				'shop_invoked' => 1,
			),
		);

		// Send API request call.
		$response = novalnet()->helper()->submit_request( $parameters, novalnet()->helper()->get_action_endpoint( 'transaction_refund' ), array( 'post_id' => $wc_order_id ) );

		// Handle success process.
		if ( WC_Novalnet_Validation::is_success_status( $response ) ) {

			/* translators: %s: tid,amount */
			$message = sprintf( __( 'Refund has been initiated for the TID: %1$s with the amount of %2$s.', 'woocommerce-novalnet-gateway' ), $response['transaction']['tid'], wc_novalnet_shop_amount_format( $refund_amount ) );

			// Get the new TID (if available).
			if ( ! empty( $response['transaction']['refund']['tid'] ) ) {
				/* translators: %s: response tid */
				$message .= sprintf( __( 'New TID:%s for the refunded amount', 'woocommerce-novalnet-gateway' ), $response ['transaction']['refund']['tid'] );
			}

			// Update transaction_detail table.
			novalnet()->db()->update(
				array(

					// Calculating refunded amount.
					'refunded_amount' => $transaction_details ['refunded_amount'] + $parameters ['transaction']['amount'],
					'gateway_status'  => $response ['transaction']['status'],
				),
				array(
					'order_no' => $wc_order_id,
				)
			);

			// Update order comments.
			novalnet()->helper()->update_comments( $wc_order, wc_novalnet_format_text( $message ) );

			// Update transaction status.
			if ( 'DEACTIVATED' === $response ['transaction']['status'] ) {
				novalnet()->helper()->novalnet_update_wc_order_meta( $wc_order, '_novalnet_gateway_status', $response ['transaction']['status'], true );
			}

			// Create the manual refund (if needed).
			if ( true === $manual_refund ) {
				$result = wc_create_refund(
					array(
						'order_id' => $wc_order_id,
						'amount'   => (float) number_format( ( $refund_amount / 100 ), 2, '.', '' ),
						'reason'   => $reason,
					)
				);
				if ( is_wp_error( $result ) ) {
					/* translators: %s: amount, message  */
					$message = sprintf( __( 'Payment refund failed for the order: %1$s due to: %2$s.' ), $wc_order_id, $result->get_error_message() );
					novalnet()->helper()->log_error( $message );
					novalnet()->helper()->update_comments( $wc_order, wc_novalnet_format_text( $message ) );
				}
			}
			return true;
		}

		// Handle failure process.
		$message = wc_novalnet_response_text( $response );

		novalnet()->helper()->debug( "Transaction Refund got failed due to: $message", $wc_order_id );
		return new WP_Error( 'error', $message );
	}
}

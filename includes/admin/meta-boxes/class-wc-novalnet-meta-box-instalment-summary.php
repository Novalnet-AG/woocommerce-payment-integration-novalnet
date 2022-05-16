<?php
/**
 * Show and handle instalment summary
 *
 * Handling Instalment Payments
 *
 * @author   Novalnet AG
 * @category Class WC_Novalnet_Meta_Box_Instalment_Summary
 * @package  woocommerce-novalnet-gateway/includes/admin/meta-boxes/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WC_Novalnet_Meta_Box_Instalment_Summary Class
 */
class WC_Novalnet_Meta_Box_Instalment_Summary {


	/**
	 * Output the metabox
	 *
	 * @param WP_Post $post The Order object.
	 */
	public static function output( $post ) {

		$instalments         = apply_filters( 'novalnet_get_stored_instalment_data', $post->ID );
		$transaction_details = novalnet()->db()->get_transaction_details( $post->ID );

		if ( ! empty( $instalments ) ) {
			include_once dirname( ( __FILE__ ) ) . '/views/html-instalment-summary.php';
		}
	}

	/**
	 * Save meta box data
	 *
	 * @param WP_Post $post_id Post ID of the order.
	 */
	public static function save( $post_id ) {

		$message_type = '1';
		if ( ! empty( novalnet()->request['novalnet_instalment_refund_tid'] ) && ! empty( novalnet()->request['novalnet_instalment_refund_amount'] ) ) {
			$refund_amount = sanitize_text_field( novalnet()->request ['novalnet_instalment_refund_amount'] );
			$refund_tid    = sanitize_text_field( novalnet()->request ['novalnet_instalment_refund_tid'] );
			$refund_reason = '';
			if ( ! empty( novalnet()->request ['novalnet_instalment_refund_reason'] ) ) {
				$refund_reason = sanitize_text_field( novalnet()->request ['novalnet_instalment_refund_reason'] );
			}
			$result = WC_Novalnet_Amount_Refund::execute( $post_id, wc_novalnet_formatted_amount( wc_format_decimal( $refund_amount ) ), $refund_reason, $refund_tid, true );

			if ( is_wp_error( $result ) ) {
				WC_Admin_Meta_Boxes::add_error( $result->get_error_message() );
				$message_type = '1';
			} else {
				$instalments = novalnet()->db()->get_entry_by_order_id( $post_id, 'additional_info' );
				foreach ( $instalments as $key => $data ) {
					if ( ! empty( $data ['tid'] ) && (int) $data ['tid'] === (int) $refund_tid ) {
						if ( strpos( $instalments [ $key ] ['amount'], '.' ) ) {
							$instalments [ $key ] ['amount'] *= 100;
						}
						$instalments [ $key ] ['amount'] -= wc_format_decimal( $refund_amount ) * 100;
					}
				}

				// Update transaction details.
				novalnet()->db()->update(
					array(
						// Calculating refunded amount.
						'additional_info' => wc_novalnet_serialize_data( $instalments ),
					),
					array(
						'order_no' => $post_id,
					)
				);
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

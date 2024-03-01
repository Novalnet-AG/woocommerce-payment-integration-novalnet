<?php
/**
 * Order amount booking
 *
 * @author  Novalnet AG
 * @package woocommerce-novalnet-gateway/includes/admin/meta-boxes/views/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="wc-novalnet-order-amount-booking">
	<table>
		<tbody>
			<tr>
				<td>
					<?php
					woocommerce_wp_text_input(
						array(
							'id'            => 'novalnet_book_amount',
							'wrapper_class' => 'novalnet_book_amount_wrapper_class',
							'data_type'     => 'price',
							'label'         => __( 'Transaction booking amount', 'woocommerce-novalnet-gateway' ),
							'value'         => number_format( $transaction_details['amount'] / 100, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() ),
						)
					);
					?>
				</td>
			</tr>
			<tr>
				<td class="meta-box-footer">
					<button type="submit" class="button save_order button-primary" id="novalnet_book_order_amount" name="novalnet_book_order_amount" value="yes"><?php echo esc_html__( 'Book transaction', 'woocommerce-novalnet-gateway' ); ?></button>
				</td>
			</tr>
		</tbody>
	</table>
</div>

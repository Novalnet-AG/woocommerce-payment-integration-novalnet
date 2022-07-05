<?php
/**
 * Display Instalment summary
 *
 * @author  Novalnet AG
 * @package woocommerce-novalnet-gateway/includes/admin/meta-boxes/views/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<div class="wc_novalnet_instalment_related_orders_admin">
	<table>
		<thead>
			<tr>
				<th><?php esc_attr_e( 'S.no', 'woocommerce-novalnet-gateway' ); ?></th>
				<th><?php esc_attr_e( 'Date', 'woocommerce-novalnet-gateway' ); ?></th>
				<th><?php esc_attr_e( 'Amount', 'woocommerce-novalnet-gateway' ); ?></th>
				<th><?php esc_attr_e( 'Novalnet transaction ID', 'woocommerce-novalnet-gateway' ); ?></th>
				<th><?php esc_attr_e( 'Status', 'woocommerce-novalnet-gateway' ); ?></th>
				<th style="text-align:center" ><?php esc_attr_e( 'Instalment refund', 'woocommerce-novalnet-gateway' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php
			woocommerce_wp_hidden_input(
				array(
					'id'   => 'novalnet_instalment_refund_amount',
					'name' => 'novalnet_instalment_refund_amount',
				)
			);
			woocommerce_wp_hidden_input(
				array(
					'id'   => 'novalnet_instalment_refund_tid',
					'name' => 'novalnet_instalment_refund_tid',
				)
			);
			woocommerce_wp_hidden_input(
				array(
					'id'   => 'novalnet_instalment_refund_reason',
					'name' => 'novalnet_instalment_refund_reason',
				)
			);
			foreach ( $instalments as $cycle => $instalment ) :
				if ( strpos( $instalment['amount'], '.' ) ) {
					$instalment['amount'] = $instalment['amount'] * 100;
				}
				?>
				<tr class="order">
					<td>
						<?php echo esc_attr( $cycle ); ?>
					</td>
					<td>
						<?php echo esc_attr( $instalment['date'] ); ?>
					</td>
					<td>
						<?php echo esc_attr( wc_novalnet_shop_amount_format( $instalment['amount'] ) ); ?>
					</td>
					<td>
						<?php
						if ( ! empty( $instalment['tid'] ) ) :
							echo esc_attr( $instalment['tid'] );
						endif;
						?>
					</td>
					<td class="order_status column-order_status">
						<?php
						if ( $transaction_details['amount'] === $transaction_details['refunded_amount'] || 0 === $instalment['amount'] ) {
							$instalment['status']      = 'refunded';
							$instalment['status_text'] = 'Refunded';
						}
						?>
						<mark class="order-status status-<?php echo esc_attr( $instalment['status'] ); ?>">
							<span><?php echo esc_attr( $instalment['status_text'] ); ?></span>
						</mark>
					</td>
					<td>
						<?php if ( ! empty( $instalment['tid'] ) && ! empty( $instalment['amount'] ) && $transaction_details['amount'] > $transaction_details['refunded_amount'] ) : ?>
							<div style="text-align:center"; class="wc-order-data-row novalnet-instalment-data-row-toggle refund_button_<?php echo esc_attr( $cycle ); ?>">
								<button type="button" class="button refund-items" id="refund_link_<?php echo esc_attr( $cycle ); ?>" style="cursor:pointer;" onclick="return wc_novalnet_admin.show_instalment_refund('<?php echo esc_attr( $cycle ); ?>');"><?php esc_attr_e( 'Refund', 'woocommerce' ); ?></button>
							</div>
							<div id="div_refund_link_<?php echo esc_attr( $cycle ); ?>" class="wc-order-data-row novalnet-instalment-data-row-toggle" style="display: none;">
								<table class="wc-order-totals">
									<tbody>
										<tr>
											<td class="label" style="float:right;"><label for="refund_amount"><?php esc_attr_e( 'Refund amount', 'woocommerce' ); ?>:</label></td>
											<td class="total" style="width:10px;">
												<input type="text" style="float:left;" id="novalnet_instalment_refund_amount_<?php echo esc_attr( $cycle ); ?>" name="novalnet_instalment_refund_amount_<?php echo esc_attr( $cycle ); ?>" class="wc_input_price" value="<?php echo number_format( $instalment['amount'] / 100, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() ); ?>"/>
												<input type="hidden" id="novalnet_instalment_tid_<?php echo esc_attr( $cycle ); ?>" name="novalnet_instalment_tid_<?php echo esc_attr( $cycle ); ?>" value="<?php echo esc_attr( $instalment['tid'] ); ?>"/>
												<div class="clear"></div>
											</td>
										</tr>
										<tr>
											<td class="label" style="float:right;" ><label for="refund_reason"><?php echo wc_help_tip( __( 'Note: Refund reason will be shown to the customer', 'woocommerce' ) ); // WPCS: XSS ok. ?> <?php esc_attr_e( 'Reason for refund (optional):', 'woocommerce' ); ?></label></td>
											<td class="total" style="width:10px;">
												<input type="text" style="float:left;" id="novalnet_instalment_refund_reason_<?php echo esc_attr( $cycle ); ?>" name="novalnet_instalment_refund_reason_<?php echo esc_attr( $cycle ); ?>" />
												<div class="clear"></div>
											</td>
										</tr>
									</tbody>
								</table>
								<div class="clear"></div>
								<div class="refund-actions">
									<button class="button button-primary do-api-refund align_right" onclick="return wc_novalnet_admin.instalment_amount_refund(<?php echo esc_attr( $cycle ); ?>)"><?php esc_attr_e( 'Confirm', 'woocommerce' ); ?></button>
									<button type="button" class="button cancel-action" id="refund_cancel_link_<?php echo esc_attr( $cycle ); ?>" id="refund_cancel_link_<?php echo esc_attr( $cycle ); ?>" onclick="return wc_novalnet_admin.hide_instalment_refund('<?php echo esc_attr( $cycle ); ?>');"><?php esc_attr_e( 'Cancel', 'woocommerce' ); ?></button>
								</div>
							</div>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

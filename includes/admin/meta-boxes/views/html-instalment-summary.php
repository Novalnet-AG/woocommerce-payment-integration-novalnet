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
				<th><?php esc_attr_e( 'S.No', 'woocommerce-novalnet-gateway' ); ?></th>
				<th><?php esc_attr_e( 'Novalnet Transaction ID', 'woocommerce-novalnet-gateway' ); ?></th>
				<th><?php esc_attr_e( 'Amount', 'woocommerce-novalnet-gateway' ); ?></th>
				<th><?php esc_attr_e( 'Next Instalment Date', 'woocommerce-novalnet-gateway' ); ?></th>
				<th><?php esc_attr_e( 'Status', 'woocommerce-novalnet-gateway' ); ?></th>
				<th style="text-align:center" ><?php esc_attr_e( 'Instalment Refund', 'woocommerce-novalnet-gateway' ); ?></th>
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

			$instalment_cancel_tid = '';
			$count                 = 0;
			?>
			<div id="instalment_cancel_additional_message"></div>
			<?php
			$is_instalment_cancelled = ( ( ! empty( $instalments['is_instalment_cancelled'] ) && 1 === (int) $instalments['is_instalment_cancelled'] ) || 'DEACTIVATED' === (string) $transaction_details['gateway_status'] ) ? true : false;
			foreach ( $instalments as $cycle => $instalment ) :
				if ( ! is_array( $instalment ) ) {
					continue;
				}
				if ( 0 === $count ) {
					$instalment_cancel_tid = $instalment['tid'];
				}
				if ( strpos( $instalment['amount'], '.' ) ) {
					$instalment['amount'] = $instalment['amount'] * 100;
				}
				$count++;
				?>
				<tr class="order">
					<td>
						<?php echo esc_attr( $cycle ); ?>
					</td>
					<td>
						<?php
						if ( ! empty( $instalment['tid'] ) ) :
							echo esc_attr( $instalment['tid'] );
						endif;
						?>
					</td>
					<td>
						<?php echo esc_attr( wc_novalnet_shop_amount_format( $instalment['amount'] ) ); ?>
					</td>
					<td>
						<?php echo esc_attr( $instalment['date'] ); ?>
					</td>
					<td class="order_status column-order_status">
						<?php
						if ( $transaction_details['amount'] === $transaction_details['refunded_amount'] || 0 === $instalment['amount'] ) {
							$instalment['status']      = 'refunded';
							$instalment['status_text'] = 'Refunded';
							if ( empty( $instalment['tid'] ) ) {
								$instalment['status']      = 'cancelled';
								$instalment['status_text'] = 'Cancelled';
							}
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
											<td class="label" style="float:right;" ><label for="refund_reason"><?php echo wc_help_tip( __( 'Note: Refund reason will be shown to the customer', 'woocommerce' ) ); // phpcs:ignore. ?> <?php esc_attr_e( 'Reason for refund (optional):', 'woocommerce' ); ?></label></td>
											<td class="total" style="width:10px;">
												<input type="text" style="float:left;" id="novalnet_instalment_refund_reason_<?php echo esc_attr( $cycle ); ?>" name="novalnet_instalment_refund_reason_<?php echo esc_attr( $cycle ); ?>" />
												<div class="clear"></div>
											</td>
										</tr>
									</tbody>
								</table>
								<div class="clear"></div>
								<div class="refund-actions" style="text-align:right">
									<button class="button button-primary do-api-refund align_right" onclick="return wc_novalnet_admin.instalment_amount_refund(<?php echo esc_attr( $cycle ); ?>)"><?php esc_attr_e( 'Confirm', 'woocommerce' ); ?></button>
									<button type="button" class="button cancel-action" id="refund_cancel_link_<?php echo esc_attr( $cycle ); ?>" id="refund_cancel_link_<?php echo esc_attr( $cycle ); ?>" onclick="return wc_novalnet_admin.hide_instalment_refund('<?php echo esc_attr( $cycle ); ?>');"><?php esc_attr_e( 'Cancel', 'woocommerce' ); ?></button>
								</div>
							</div>
						<?php endif; ?>
					</td>
				</tr>
				<?php
			endforeach;
			?>
			<tr>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td></td>
				<td>
					<div id="novalnet_instalment_cancel" style="text-align:right"; class="wc-order-data-row novalnet-instalment-data-row-toggle refund_button_<?php echo esc_attr( $cycle ); ?>">
						<input type="hidden" id="instalment_cancel_tid" hidden value=<?php echo esc_attr( $instalment_cancel_tid ); ?> >
						<input type="hidden" id="instalment_cancel_order_id" hidden value=<?php echo esc_attr( $wc_order->get_id() ); ?> >
						<input type="hidden" id="novalnet_key_password" hidden value=<?php echo esc_attr( get_option( 'novalnet_key_password' ) ); ?> >
						<?php
							wp_nonce_field( 'novalnet_instalment_cancel_event', 'instalment_security_nonce' );
						if ( ! $is_instalment_cancelled ) :
							?>
							<button type="button" class="button refund-items"
							id="instalment_cancel" style="cursor:pointer;background-color:#007cba;color:white" onclick="return wc_novalnet_admin.show_instalment_cancel_option();"><?php esc_attr_e( 'Instalment Cancel', 'woocommerce-novalnet-gateway' ); ?></button>
							<button type="button" class="button refund-items"
							id="entire_instalment_cancel" style="cursor:pointer;background-color:#007cba;color:white;display:none" onclick="return wc_novalnet_admin.entire_instalment_cancel();"><?php esc_attr_e( 'Cancel All Instalment', 'woocommerce-novalnet-gateway' ); ?></button>
							<?php if ( isset( $instalments['has_pending_cycle'] ) && true === $instalments['has_pending_cycle'] ) : ?>
								<button type="button" class="button refund-items"
								id="stop_upcoming_instalment" style="cursor:pointer;background-color:#007cba;color:white;display:none" onclick="return wc_novalnet_admin.stop_upcoming_instalment();"><?php esc_attr_e( 'Cancel All Remaining Instalments', 'woocommerce-novalnet-gateway' ); ?></button>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</td>
			</tr>
		</tbody>
	</table>
</div>

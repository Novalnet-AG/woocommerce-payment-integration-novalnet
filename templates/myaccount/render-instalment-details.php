<?php
/**
 * Display Instalment related transactions
 *
 * @author  Novalnet AG
 * @package woocommerce-novalnet-gateway/templates/myaccount
 * @version 11.3.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( 1 < count( $contents ) ) {
	?>
<div class="wc_novalnet_instalment_related_orders_myaccount">
<table class="shop_table my_account_orders order_details wc_novalnet_instalment_show_table">
<thead>
	<tr>
		<th><?php esc_attr_e( 'S.no', 'woocommerce-novalnet-gateway' ); ?></th>
		<th><?php esc_attr_e( 'Amount', 'woocommerce-novalnet-gateway' ); ?></th>
		<th><?php esc_attr_e( 'Date', 'woocommerce-novalnet-gateway' ); ?></th>
		<th><?php esc_attr_e( 'Novalnet transaction ID', 'woocommerce-novalnet-gateway' ); ?></th>
		<th><?php esc_attr_e( 'Status', 'woocommerce-novalnet-gateway' ); ?></th>
	</tr>
</thead>
<tbody>
	<?php
	foreach ( $contents['instalments'] as $cycle => $instalment ) {
		if ( ! is_array( $instalment ) ) {
			continue;
		}
		if ( $contents['transaction']['amount'] === $contents['transaction']['refunded_amount'] || 0 === $instalment['amount'] ) {
			$instalment['status']      = 'refunded';
			$instalment['status_text'] = 'Refunded';
			if ( empty( $instalment['tid'] ) ) {
				$instalment['status']      = 'cancelled';
				$instalment['status_text'] = 'Cancelled';
			}
		}
		?>
		<tr class="order">
			<td>
				<?php echo esc_attr( $cycle ); ?>
			</td>
			<td>
				<?php echo esc_html( wc_novalnet_shop_amount_format( $instalment['amount'] ) ); ?>
			</td>
			<td>
				<?php echo esc_attr( $instalment['date'] ); ?>
			</td>
			<td>
				<?php echo esc_attr( ! empty( $instalment['tid'] ) ? $instalment['tid'] : '-' ); ?>
			</td>
			<td>
				<?php echo esc_html( $instalment['status_text'] ); ?>
			</td>
		</tr>
			<?php
	}
}
?>
		</tbody>
	</table>
</div>

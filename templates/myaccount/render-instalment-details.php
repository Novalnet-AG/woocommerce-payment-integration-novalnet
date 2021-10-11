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
<table class="shop_table shop_table_responsive my_account_orders">
<thead>
	<tr>
		<th><?php esc_attr_e( 'S.no', 'woocommerce-novalnet-gateway' ); ?></th>
		<th><?php esc_attr_e( 'Date', 'woocommerce-novalnet-gateway' ); ?></th>
		<th><?php esc_attr_e( 'Novalnet transaction ID', 'woocommerce-novalnet-gateway' ); ?></th>
		<th><?php esc_attr_e( 'Amount', 'woocommerce-novalnet-gateway' ); ?></th>
	</tr>
</thead>
<tbody>
	<?php
	foreach ( $contents as $cycle => $instalment ) {
		?>
		<tr class="order">
			<td>
				<?php echo esc_attr( $cycle ); ?>
			</td>
			<td>
				<?php echo esc_attr( $instalment['date'] ); ?>
			</td>
			<td>
				<?php echo esc_attr( ! empty( $instalment['tid'] ) ? $instalment['tid'] : '-' ); ?>
			</td>
			<td>
				<?php echo esc_html( wc_novalnet_shop_amount_format( $instalment['amount'] ) ); ?>
			</td>
		</tr>
			<?php
	}
}
?>
		</tbody>
	</table>
</div>

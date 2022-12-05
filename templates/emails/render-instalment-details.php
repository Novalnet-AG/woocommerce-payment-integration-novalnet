<?php
/**
 * Order details table shown in emails.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/email-order-details.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package woocommerce-novalnet-gateway/includes/emails/
 * @version 3.3.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$text_align = is_rtl() ? 'right' : 'left';?>

<h2>
	<?php wp_kses_post( __( 'Instalment Summary', 'woocommerce-novalnet-gateway' ) ); ?>
</h2>

<div style="margin-bottom: 40px;">
	<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
		<thead>
			<tr>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_attr_e( 'S.no', 'woocommerce-novalnet-gateway' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_attr_e( 'Date', 'woocommerce-novalnet-gateway' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_attr_e( 'Novalnet transaction ID', 'woocommerce-novalnet-gateway' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_attr_e( 'Amount', 'woocommerce-novalnet-gateway' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $contents['instalments'] as $cycle => $instalment ) { ?>
				<tr class="order">
					<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; word-wrap:break-word;">
						<?php echo esc_html( $cycle ); ?>
					</td>
					<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; word-wrap:break-word;">
						<?php echo esc_html( $instalment['date'] ); ?>
					</td>
					<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; word-wrap:break-word;">
						<?php echo esc_html( ! empty( $instalment['tid'] ) ? $instalment['tid'] : '-' ); ?>
					</td>
					<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; word-wrap:break-word;">
						<?php echo esc_html( wc_novalnet_shop_amount_format( $instalment['amount'] ) ); ?>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
</div>

<?php
/**
 * Instalment/Guaranteed input Form.
 *
 * @author  Novalnet AG
 * @package woocommerce-novalnet-gateway/templates/checkout
 */

if ( ! defined( 'ABSPATH' ) ) :
	exit; // Exit if accessed directly.
endif;

$show_dob                = $payment_type . '_show_dob';
$change_payment_notifier = false;
if ( ! empty( novalnet()->request ['change_payment_method'] ) ) {
	$change_payment_notifier = true;
}

$show_dob_fields = false;

if ( ! is_admin() ) {
	WC()->session->set( $payment_type . '_dob_hided', true );
	if ( ( WC()->session->__isset( $show_dob ) && ! empty( WC()->session->$show_dob ) ) || $change_payment_notifier ) {
		WC()->session->__unset( $payment_type . '_dob_hided' );
		$show_dob_fields = true;
	}
} elseif ( is_admin() ) {
	$show_dob_fields = true;
}

if ( $show_dob_fields ) :
	woocommerce_form_field(
		$payment_type . '_dob',
		array(
			'required'          => true,
			'class'             => array(
				'form-row-wide',
			),
			'label'             => __( 'Your date of birth', 'woocommerce-novalnet-gateway' ),
			'placeholder'       => __( 'DD.MM.YYYY', 'woocommerce-novalnet-gateway' ),

			'custom_attributes' => array(
				'onkeydown'    => 'return NovalnetUtility.isNumericBirthdate( this, event )',
				'onblur'       => 'return wc_novalnet.validate_date_format( this, "' . $payment_type . '" )',
				'autocomplete' => 'OFF',
			),
		)
	);

endif;

if ( WC_Novalnet_Validation::check_string( $payment_type, 'instalment' ) ) :
	$order_total = isset( $contents['total'] ) ? $contents['total'] : WC()->cart->total;

	// Create instalment cycle field.
	$instalment_details = apply_filters( 'novalnet_get_instalment_cycles', $contents, $order_total );

	if ( ! empty( $instalment_details ['period'] ) ) :

		woocommerce_form_field(
			$payment_type . '_period',
			array(
				'type'              => 'select',
				'required'          => true,
				'input_class'       => array(
					'form-row',
					'select2-container',
				),
				'custom_attributes' => array(
					'onchange' => 'wc_novalnet.show_instalment_table("' . $payment_type . '")',
				),
				/* translators: %s: Amount */
				'label'             => sprintf( __( 'Choose your instalment plan <b>(Net loan amount: %s )</b>', 'woocommerce-novalnet-gateway' ), wc_novalnet_shop_amount_format( $order_total * 100 ) ),
				'id'                => $payment_type . '_period',
				'options'           => $instalment_details ['period'],
				'default'           => min( array_keys( $instalment_details ['period'] ) ),
			)
		);

		if ( ! empty( $instalment_details ['attributes'] ) ) :
			?>
			<div class="novalnet-info-box">
			<?php
			foreach ( $instalment_details ['attributes'] as $period => $attributes ) :

				$cycle_amount   = sprintf( '%0.2f', $order_total / $period );
				$splited_amount = $cycle_amount * ( $period - 1 );

				if ( $cycle_amount !== $splited_amount ) :
					$diff_amount = wc_novalnet_shop_amount_format( sprintf( '%0.2f', $order_total - $splited_amount ) * 100 );
				endif;
				?>
				<table id="<?php echo esc_html( $payment_type . '_table_' . $period ); ?>" class="shop_table"  style="display:none;">
					<tr>
						<th style="text-align:center"><?php esc_attr_e( 'Instalment cycles', 'woocommerce-novalnet-gateway' ); ?></th>
						<th style="text-align:center"><?php esc_attr_e( 'Instalment Amount', 'woocommerce-novalnet-gateway' ); ?></th>
					</tr>
					<tbody>
						<?php for ( $i = 1;$i <= $period;$i++ ) : ?>
							<tr>
								<td style="text-align:center"><?php echo esc_html( $i ); ?></td>
								<td style="text-align:center"><?php echo esc_html( ( $i !== $period && ! empty( $attributes ['amount'] ) ) ? esc_html( $attributes ['amount'] ) : esc_html( $diff_amount ) ); ?></td>
							</tr>
						<?php endfor; ?>
					</tbody>
				</table>
				<?php endforeach; ?>
			</div>
			<?php
		endif;
	endif;
endif;



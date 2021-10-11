<?php
/**
 * Instalment/Guaranteed input Form.
 *
 * @author  Novalnet AG
 * @package woocommerce-novalnet-gateway/Templates/Checkout
 */

if ( ! defined( 'ABSPATH' ) ) :
	exit; // Exit if accessed directly.
endif;
?>
<span style="color: #96588a;"><?php esc_attr_e( 'You can pay this article in instalments.', 'woocommerce-novalnet-gateway' ); ?></span>
<span id="novalnet-instalment-suggestions" style="text-decoration:underline;cursor:pointer;color:black;">
	<strong></strong><?php esc_attr_e( 'Learn more', 'woocommerce-novalnet-gateway' ); ?>
</span>
<div style = "display:none"; class="novalnet-popover-container">
	<div class="novalnet-popover-inner">
		<header class="novalnet-popover-header">
			<h4 class="novalnet-popover-header-content" id="a-novalnet-popover-header-7">Instalment Options</h4>
			<button data-action="novalnet-popover-close" class="novalnet-popover-button-close  a-declarative" aria-label="Close"><i class="fas fa-window-close"></i></button>
		</header>
		<div class="woocommerce-tabs wc-tabs-wrapper">
			<ul class="tabs wc-tabs" role="tablist">
				<?php foreach ( $contents ['payment_types'] as $payment_type => $data ) : ?>
				<li class="<?php echo esc_attr( $payment_type ); ?>_plan" id="tab-title-<?php echo esc_attr( $payment_type ); ?>_plan" role="tab" aria-controls="tab-<?php echo esc_attr( $payment_type ); ?>_plan">
					<a href="#tab-<?php echo esc_attr( $payment_type ); ?>_plan">
												<?php
												$payment_text = WC_Novalnet_Configuration::get_payment_text( $payment_type );
												echo esc_html( wc_novalnet_get_payment_text( $data ['settings'], $payment_text, wc_novalnet_shop_language(), $payment_type ) );
												?>
					</a>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php foreach ( $contents ['payment_types'] as $payment_type => $data ) : ?>
				<div class="woocommerce-Tabs-panel--<?php echo esc_attr( $payment_type ); ?>_plan panel entry-content wc-tab" id="tab-<?php echo esc_attr( $payment_type ); ?>_plan" role="tabpanel" aria-labelledby="tab-title-<?php echo esc_attr( $payment_type ); ?>_plan" style="display:block;">
					<table class="shop_attributes">
						<tbody>
							<?php
							if ( ! empty( $data['cycles']['attributes'] ) && ! empty( $data['cycles']['period'] ) ) :
								$plan = 1;
								foreach ( $data['cycles']['period'] as $key => $value ) :
									if ( $key ) :
										if ( 2 === count( $data['cycles']['period'] ) ) :
											$plan = '';
										endif;
										?>
										<tr>
											<th>
												<?php
												/* translators: %s: plan */
												echo sprintf( esc_html__( 'Plan %s: ', 'woocommerce-novbalnet-gateway' ), esc_html( $plan ) );
												$plan++;
												?>
											</th>
											<td>
												<span><?php echo esc_html( $value ); ?></span>
											</td>
										</tr>
										<?php
									endif;
								endforeach;
							endif;
							?>
						</tbody>
					</table>
					<div class="novalnet-instalment-footer">
						<p><?php esc_attr_e( 'Things to note:', 'woocommerce-novalnet-gateway' ); ?></p>
						<ul>
							<li><span><?php esc_attr_e( 'Available in Germany, Austria and Switzerland', 'woocommerce-novalnet-gateway' ); ?></span></li>
							<li><span><?php esc_attr_e( 'The shipping address must be the same as the billing address', 'woocommerce-novalnet-gateway' ); ?></span></li>
						</ul>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

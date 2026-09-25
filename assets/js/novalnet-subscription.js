/**
 * Novalnet subscription JS.
 *
 * @category  JS
 * @package   Novalnet
 */

/** Global wcs_novalnet_data */
(function($){

	wcs_novalnet = {

		/**
		 * Handle subscription cancel validation.
		 */
		process_subscription_cancel : function () {
			if ('0' === $( '#novalnet_subscription_cancel_reason' ).val() ) {
				alert( wcs_novalnet_data.error_message );
				return false;
			}
			wc_novalnet.load_block( 'novalnet_subscription_cancel', null );
		},

		/**
		 * Initialize event handlers and validation.
		 */
		init : function () {

			/**
			 * Assign values for admin change payment method in subscription.
			 */
			$( 'input[id*="novalnet_payments"]' ).attr( 'type', 'hidden' );
			$( '.edit_address' ).on(
				'click',
				function() {
					$( 'input[id*="novalnet_payment"]' ).val( '1' );
				}
			);

			if ('1' === wcs_novalnet_data.hide_unsupported_features ) {
				$( '#_billing_interval, #_billing_period, #end_hour, #end, #end_minute' ).attr( 'disabled', true );
				$( '#_billing_interval' ).css( 'disabled', true );
			}

			if ( undefined !== wcs_novalnet_data.customer ) {
				$( '.cancel' ).wrap( '<span class="cancelled"></span>' );
			}

			$( '.cancelled' ).on(
				'click',
				function( evt ) {
					var submit_url = $( this ).children( 'a' ).attr( 'href' );
					if (0 < submit_url.indexOf( "novalnet-api" ) ) {
						$( '#novalnet_subscription_cancel' ).remove();
						$( this ).closest( 'td' ).append( wcs_novalnet_data.reason_list );
						$( ' #novalnet_subscription_cancel_reason' ).css( 'position', 'absolute' );
						evt.preventDefault();
						evt.stopImmediatePropagation();
					}
					$( '#novalnet_subscription_cancel' ).attr( 'method', 'POST' );
					$( '#novalnet_subscription_cancel' ).attr( 'action', submit_url );
				}
			);
			$( document ).on(
				'submit',
				'#' + $( '#_payment_method' ).closest( 'form' ).attr( 'id' ),
				function( event ) {
					var payment_method = jQuery( '#_payment_method' ).val();
					if ( -1 != jQuery.inArray( payment_method, [ 'novalnet_guaranteed_sepa', 'novalnet_guaranteed_invoice' ] ) && 'yes' === jQuery( '#nn-subs-need-shipping-addr' ).val() ) {
						var address_fields = [
							'_first_name',
							'_last_name',
							'_company',
							'_address_1',
							'_address_2',
							'_city',
							'_postcode',
							'_country',
							'_state'
						];
						jQuery.each(address_fields, function (key, field) {
							if ( $( '#_billing' + field ).val() != $( '#_shipping' + field ).val() ) {
								event.preventDefault();
								event.stopImmediatePropagation();
								alert( wcs_novalnet_data.change_address_error_message );
								return false;
							}
						});
					}
				}
			);
		},
	};

	wcs_novalnet.init();
})( jQuery );

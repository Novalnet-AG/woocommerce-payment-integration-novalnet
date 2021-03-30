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
			$( 'input[id*="novalnet_payment"]' ).attr( 'type', 'hidden' );
			$( '.edit_address' ).on(
				'click',
				function() {
					$( 'input[id*="novalnet_payment"]' ).val( '1' );
				}
			);
			$( 'input[id*="novalnet_payment_change"]' ).replaceWith( '<p class="form-field form-field-wide"><input id="novalnet_payment_change" name="novalnet_payment_change" type="checkbox" value="1" style="width:5%" >' + wcs_novalnet_data.change_payment_text + '</p>' );
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
		},
	};

	wcs_novalnet.init();
})( jQuery );

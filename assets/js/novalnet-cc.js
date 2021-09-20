/**
 * Novalnet Creditcard Iframe JS.
 *
 * @category  JS
 * @package   Novalnet
 */

/** Global wc_novalnet_cc_data */

(function($){

	wc_novalnet_cc = {

		/** Initiate Credit card Iframe process */
		init : function() {

			wc_novalnet_cc.load_iframe();

			if ( undefined !== wc_novalnet_cc_data.admin ) {
				$( '.edit_address' ).on(
					'click',
					function() {
						var iframe = document.getElementById( 'novalnet_cc_iframe' ).contentWindow;

						/** Initiate post message to get Iframe height. */
						NovalnetUtility.setCreditCardFormHeight();
					}
				);
				$( document ).on(
					'submit',
					'#' + $( '#novalnet_cc_pan_hash' ).closest( 'form' ).attr( 'id' ),
					function( event ) {
						wc_novalnet.hide_error( '.novalnet-cc-error' );
						if ( $( '#novalnet_cc_iframe' ).is( ":visible" ) && '' == $( '#novalnet_cc_pan_hash' ).val() && 'novalnet_cc' === $( '#_payment_method' ).val() ) {
							event.preventDefault();
							event.stopImmediatePropagation();
							NovalnetUtility.getPanHash();
						}
					}
				);
			} else {

				/**Set height when payment selected. */
				if ( undefined !== $( '#payment_method_novalnet_cc' ).val() ) {
					$( '#payment_method_novalnet_cc' ).on(
						'click',
						function() {
							NovalnetUtility.setCreditCardFormHeight();
						}
					);
				}

				/**Process hash call. */
				$( '#' + wc_novalnet.form_id() ).on(
					'click',
					function( event ) {

						if ( '' == $( '#novalnet_cc_pan_hash' ).val() && wc_novalnet.check_payment( 'novalnet_cc' ) && ( undefined === $( '#wc-novalnet_cc-payment-token-new' ).val() || $( '#wc-novalnet_cc-payment-token-new' ).is( ":checked" ) ) ) {
							event.preventDefault();
							event.stopImmediatePropagation();
							wc_novalnet.load_block( 'main' );
							NovalnetUtility.getPanHash();
						}
					}
				);

				$( '#wc-novalnet_cc-payment-token-new' ).on(
					'click',
					function (event) {
						NovalnetUtility.setCreditCardFormHeight();
					}
				);
			}
		},

		/**Load Iframe */
		load_iframe : function () {
			var first_name   = (undefined !== $( 'input[id*=billing_first_name]' ).val()) ? $( 'input[id*=billing_first_name]' ).val() : wc_novalnet_cc_data.first_name,
				last_name    = (undefined !== $( 'input[id*=billing_last_name]' ).val()) ? $( 'input[id*=billing_last_name]' ).val() : wc_novalnet_cc_data.last_name,
				email        = (undefined !== $( 'input[id*=billing_email]' ).val()) ? $( 'input[id*=billing_email]' ).val() : wc_novalnet_cc_data.email,
				tel          = (undefined !== $( 'input[id*=billing_phone]' ).val()) ? $( 'input[id*=billing_phone]' ).val() : wc_novalnet_cc_data.tel,
				street       = (undefined !== $(
					'input[id*=billing_address_1]'
				).val()) ? $( 'input[id*=billing_address_1]' ).val() : wc_novalnet_cc_data.street,
				zip          = (undefined !== $( 'input[id*=billing_postcode]' ).val()) ? $( 'input[id*=billing_postcode]' ).val() : wc_novalnet_cc_data.zip,
				city         = (undefined !== $( 'input[id*=billing_city]' ).val()) ? $( 'input[id*=billing_city]' ).val() : wc_novalnet_cc_data.city,
				country_code = (undefined !== $( 'input[id*=billing_country]' ).val()) ? $( 'input[id*=billing_country]' ).val() : wc_novalnet_cc_data.country_code;

			var iframe = document.getElementById( 'novalnet_cc_iframe' ).contentWindow;
			NovalnetUtility.setClientKey( wc_novalnet_cc_data.client_key );
			var request_object = {

				callback: {
					on_success: function (data) {
						$( '.blockUI' ).remove();
						$( '#novalnet_authenticated_amount' ).val( $( "#novalnet_checkout_amount" ).val() );
						$( '#novalnet_cc_pan_hash' ).val( data ['hash'] );
						$( '#novalnet_cc_unique_id' ).val( data ['unique_id'] );
						if ( undefined === wc_novalnet_cc_data.admin ) {
							$( '#novalnet_cc_force_redirect' ).val( data ['do_redirect'] );
							$( '#' + wc_novalnet.form_id() ).click();
							return false;
						} else if ( undefined !== wc_novalnet_cc_data.admin && data ['do_redirect'] != 0 ) {
							alert( wc_novalnet_cc_data.error_message );
							return false;
						} else {
							$( '#' + $( '#novalnet_cc_pan_hash' ).closest( 'form' ).attr( 'id' ) ).submit();
							return false;
						}
					},
					on_error:  function (data) {
						$( '.blockUI' ).remove();
						if ( undefined !== data['error_message'] ) {
							var error_message = data['error_message'];

							// Scroll to top.
							return wc_novalnet.show_error( '#novalnet_cc_error', error_message );
						}
					},
					on_show_overlay:  function () {
						$( '.blockUI' ).remove();
						$( '#novalnet_cc_iframe' ).addClass( 'novalnet-challenge-window-overlay' );
					},
					on_hide_overlay:  function () {
						$( '.blockUI' ).remove();
						$( '#novalnet_cc_iframe' ).removeClass( 'novalnet-challenge-window-overlay' );
					},
					on_show_captcha:  function () {
						$( '.blockUI' ).remove();
						// Scroll to top.
						return wc_novalnet.show_error( '#novalnet_cc_error' );
					}
				},
				iframe: {
					id: 'novalnet_cc_iframe',
					inline: wc_novalnet_cc_data.inline_form,
					style: {
						container: wc_novalnet_cc_data.standard_css,
						input: wc_novalnet_cc_data.standard_input,
						label: wc_novalnet_cc_data.standard_label,
					}
				},
				customer: {
					first_name: first_name,
					last_name: last_name,
					email: email,
					billing: {
						street: street,
						city: city,
						zip: zip,
						country_code: country_code,
					},
				},
				transaction: {
					amount: $( "#novalnet_checkout_amount" ).val(),
					currency: wc_novalnet_cc_data.currency,
					test_mode: wc_novalnet_cc_data.test_mode,
					enforce_3d: wc_novalnet_cc_data.enforce_3d,
				},
				custom: {
					lang : wc_novalnet_cc_data.lang
				}
			};

			NovalnetUtility.createCreditCardForm( request_object );
		},
	};
	$( document ).ready(
		function () {
			wc_novalnet_cc.init();
			$( document ).ajaxComplete(
				function( event, xhr, settings ) {
					if ( undefined === wc_novalnet_cc_data.admin ) {
						try {
							var response = $.parseJSON( xhr.responseText );
						} catch (xhr) {
							var response = xhr.responseText;
						}
						if ( 0 < xhr.status && response !== undefined && ((response.result !== undefined && 'failure' === response.result) || (response.step !== undefined && 'payment' === response.step))) {
							$( '#novalnet_cc_pan_hash, #novalnet_cc_unique_id, #novalnet_cc_force_redirect' ).val( '' );
						}
						wc_novalnet_cc.init();
					}
				}
			);
		}
	);

})( jQuery );

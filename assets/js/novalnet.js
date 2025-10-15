/**
 * Novalnet Validation and event handler JS.
 *
 * @category  JS
 * @package   Novalnet
 */

/** Global wc_novalnet */
(function($){

	wc_novalnet = {

		/**
		 * List of payment method id that uses IBAN.
		 */
		iban_payments : {
			'novalnet_sepa':'novalnet_sepa_iban',
			'novalnet_guaranteed_sepa':'novalnet_guaranteed_sepa_iban',
			'novalnet_instalment_sepa':'novalnet_instalment_sepa_iban',
		},
		/**
		 * Initialize event handlers and validation.
		 */
		init : function () {
			
			if ( 'undefined' !== typeof wc_novalnet_data && 'yes' === wc_novalnet_data.is_pay_for_order_page ) {
				$( document.body ).trigger( 'wc-credit-card-form-init' );
			}

			$( "#novalnet-instalment-suggestions" ).mouseover(
				function() {
					$( '.novalnet-popover-container' ).show();
				}
			);
			$( '.novalnet-popover-button-close' ).on(
				'click',
				function(){
					$( '.novalnet-popover-container' ).hide();
				}
			);

			$( "#novalnet_guaranteed_sepa_bic_field, #novalnet_sepa_bic_field, #novalnet_instalment_sepa_bic_field" ).css( "display", "none" );
			wc_novalnet.check_iban();
			$( '#' + wc_novalnet.form_id() ).on(
				'click',
				function( event ) {

					var payment_type = $( 'input[name=payment_method]:checked' ).val();

					if ( -1 != $.inArray( payment_type, [ 'novalnet_sepa', 'novalnet_guaranteed_sepa', 'novalnet_instalment_sepa' ] )  && wc_novalnet.check_payment( payment_type ) ) {
						if ( undefined !== $( '#' + payment_type + '_iban' ) ) {
							$( "#novalnet_guaranteed_sepa_bic_field, #novalnet_sepa_bic_field, #novalnet_instalment_sepa_bic_field" ).css( "display", "none" );
							wc_novalnet.check_iban();
							var iban = NovalnetUtility.formatAlphaNumeric( $( '#' + payment_type + '_iban' ).val() );
							if ( '' === iban ) {
								return wc_novalnet.show_error( '#' + payment_type + '_error', wc_novalnet_data.sepa_account_error );
							}
						}
					}
					if ( -1 != $.inArray( payment_type, [ 'novalnet_guaranteed_invoice', 'novalnet_guaranteed_sepa', 'novalnet_instalment_invoice', 'novalnet_instalment_sepa' ] ) ) {
						if ( undefined !== $( '#' + payment_type + '_dob' ) && undefined !== $( '#' + payment_type + '_dob' ).val() ) {
							if ( '' === $( '#' + payment_type + '_dob' ).val() || ! NovalnetUtility.validateDateFormat( $( '#' + payment_type + '_dob' ).val() ) ) {
								return wc_novalnet.show_error( '#' + payment_type + '_error', wc_novalnet_data.dob_error );
							}
						}
					}
					return true;
				}
			);

			$( "#novalnet_instalment_invoice_period, #novalnet_instalment_sepa_period" ).trigger( "change" );

			$( '#billing_company, #ship-to-different-address-checkbox' ).on(
				'change',
				function() {

					if (undefined === $( '#novalnet_valid_company' ).val()) {
						$( "#billing_company:last" ).append( "<input type='hidden' name='novalnet_valid_company' id='novalnet_valid_company' value = '" + NovalnetUtility.isValidCompanyName( $( '#billing_company' ).val() ) + "'/>" );
					} else {
						$( '#novalnet_valid_company' ).val( NovalnetUtility.isValidCompanyName( $( '#billing_company' ).val() ) );
					}

					$( document.body ).trigger( 'update_checkout' );
				}
			).change();
		},

		/**
		 * Check valid name characters in ACH account holder fields.
		 */
		is_valid_name : function ( event ) {
			var charCode = (event.which) ? event.which : event.keyCode;
			if ( !( charCode >= 65 && charCode <= 90 ) && // A-Z
				!( charCode >= 97 && charCode <= 122 ) && // a-z
				charCode !== 38 && // &
				charCode !== 45 && // -
				charCode !== 46 && // .
				charCode !== 32 // space
			) {
				event.preventDefault();
				return false;
			}
			return true;
		},

		/**
		 * Check numeric in ACH Account and Routing fields.
		 */
		is_number : function (event) {
			var charCode = (event.which) ? event.which : event.keyCode;
            if (charCode > 31 && (charCode < 48 || charCode > 57)) {
				event.preventDefault();
                return false;
            }
            return true;
		},

		/**
		 * Check IBAN to show BIC fields.
		 */
		check_iban : function () {
			wc_novalnet.set_iban_placeholder();
			$.each( wc_novalnet.iban_payments, function( payment, iban_field_id ) {
				if ( '' !== $( "#" + iban_field_id ).val() && undefined != $( "#" + iban_field_id ).val() ) {
					var iban = $( "#" + iban_field_id ).val().substring( 0, 2 );
					if ( wc_novalnet_data.bic_allowed_countries.includes( iban.toUpperCase() ) ) {
						$( "#" + payment + "_bic_field" ).css( "display", "block" );
					}
				}
			});
		},

		/**
		 * Get payment form id dynamically.
		 *
		 * @return {Boolean}
		 */
		form_id : function  () {
			var form_id = ( undefined !== $( '#order_review button[type=submit]' ).attr( 'id' ) && '' !== $( '#order_review button[type=submit]' ).attr( 'id' ) ) ? $( '#order_review button[type=submit]' ).attr( 'id' ) : $( '#order_review input[type=submit]' ).attr( 'id' );
			return ( undefined === form_id || null === form_id ) ? 'place_order' : form_id;
		},

		/**
		 * Check for tokenization io fthe current payment form.
		 *
		 * @param  {String} payment The payment type.
		 * @return {Boolean}
		 */
		check_payment : function ( payment ) {
			return (payment === $( 'input[name=payment_method]:checked' ).val() && ( undefined === $( '#wc-' + payment + '-payment-token-new' ).val() || $( '#wc-' + payment + '-payment-token-new' ).is( ":checked" )));
		},

		/**
		 * Add loader block.
		 */
		load_block: function( id, message ) {
			$( '#' + id ).block(
				{
					message: '',
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				}
			);
		},

		/**
		 * Show error
		 *
		 * @param {String} element The id/class of the element.
		 * @return {Boolean}
		 */
		show_error : function ( element, error_message = '' ) {
			if ( undefined !== error_message && '' !== error_message ) {
				$( element ).closest( '.form-row' ).removeClass( 'woocommerce-validated' ).addClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
				$( element ).html( '<ul class="woocommerce-error"><li>' + error_message + '</li></ul>' );
			}
			$( element ).css( "display","block" );
			$( 'html, body' ).animate(
				{
					scrollTop: ( $( element ).offset().top - 100 )
				},
				1000
			);
			setTimeout(
				function() {
					$( element ).fadeOut( 'slow' );
				},
				5000
			);
			return false;
		},

		/**
		 * Hide error
		 */
		hide_error : function ( element ) {
			$( element ).html( '' );
		},

		/**
		 * Show instalment table
		 *
		 * @params {String} payment_type The payment type
		 */
		show_instalment_table : function( payment_type ) {

			if ( undefined !== $( "#" + payment_type + "_period option:selected" ) ) {
				if ( '' !== $( "#" + payment_type + "_period option:selected" ).val() ) {
					var novalnet_order_cycle_period = $( "#" + payment_type + "_period option:selected" ).val();
					$( "table[id^='" + payment_type + "_table_']" ).css( "display", "none" );
					$( "#" + payment_type + "_table_" + novalnet_order_cycle_period ).css( "display", "" );
				}
			}
		},

		/**
		 * Validate date format
		 *
		 * @params {Element} e The element
		 * @params {String} payment_type The Payment type
		 *
		 * @return {Boolean}
		 */
		validate_date_format : function( e, payment_type ) {
			if ( ! NovalnetUtility.validateDateFormat( e.value ) ) {
				return wc_novalnet.show_error( '#' + payment_type + '_error', wc_novalnet_data.dob_error );

			}
		},

		/**
		 * Set placeholder for IBN field
		 */
		set_iban_placeholder() {
			if ( undefined != $('#billing_country').val() && '' != $('#billing_country').val() ) {
				var sepa_placeholder = $('#billing_country').val() + 'XX XXXX XXXX XXXX XXXX XX';
				$.each( wc_novalnet.iban_payments, function( payment, iban_field_id ) {
					if ( undefined != $( "#" + iban_field_id ).val() && '' == $( "#" + iban_field_id ).val() ) {
						$( "#" + iban_field_id ).attr('placeholder', sepa_placeholder);
					}
				});
			}
		},

		/**
		 * Do the basic initialization on AJAX
		 */
		init_ajax : function() {
			$( "#novalnet_instalment_invoice_period, #novalnet_instalment_sepa_period" ).trigger( "change" );
			$( "#novalnet_guaranteed_sepa_bic_field, #novalnet_sepa_bic_field, #novalnet_instalment_sepa_bic_field" ).css( "display", "none" );
			wc_novalnet.check_iban();
		},

		/**
		 * Check selected payment method available on multistep checkout.
		 */
		check_multistep_chosen_payment : function() {
			if ( ! $( '#place_order' ).is( ":visible" ) ) {
				return true;
			}
			let current_check_payment = $( 'input[name=payment_method]:checked' ).val();
			$.ajax({
				url: wc_novalnet_data.ajax_url,
				type: 'POST',
				data: {'action':'check_mutlistep_payment'},
				success: function (response) {
					let chosen_payment = ( undefined !== response.data.wc_chosen_payment ) ? response.data.wc_chosen_payment : '';
					if ( '' !== chosen_payment && ( current_check_payment !== response.data.wc_chosen_payment ) ) {
						alert( wc_novalnet_data.gzdp_multistep_error );
					}
				},
				error: function(xhr){
					alert( xhr.responseText );
				}
			});
		},
	};
	$( document ).ready(
		function () {
			wc_novalnet.init();

			$( document.body ).on( 'updated_checkout', function( response ) {
				wc_novalnet.init_ajax();
				if ( 'undefined' !== typeof window.germanized ) {
					wc_novalnet.check_multistep_chosen_payment();
				}
			});

			$( document.body ).on( 'checkout_error', function( response ) {
				wc_novalnet.init_ajax();
			});
		}
	);
})( jQuery );

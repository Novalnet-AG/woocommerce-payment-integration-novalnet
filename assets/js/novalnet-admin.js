/**
 * Novalnet admin JS.
 *
 * @category  JS
 * @package   Novalnet
 */

/** Global wc_novalnet_admin_data */
(function ( $ ) {

	wc_novalnet_admin = {

		/**
		 * Initialize event handlers and validation.
		 */
		init : function () {

			$( document.body ).on( 'order-totals-recalculate-complete', function( response ) {
				var data = {
					'novalnet_check_order_id': $.trim( $( '#post_ID' ).val() ),
					'action': 'novalnet_wc_order_recalculate_success',
				};
				wc_novalnet_admin.ajax_call( data, 'novalnet_order_recalculate_success' );
			});
			
			$( '#novalnet_enable_subs' ).on(
				'change',
				function() {
					if ( $( '#novalnet_enable_subs' ).is( ':checked' ) ) {
						$( '#novalnet_subs_payments, #novalnet_subs_tariff_id, #novalnet_usr_subcl, #novalnet_enable_shop_subs' ).closest( 'tr' ).slideDown( "fast" );
					} else {
						$( '#novalnet_subs_payments, #novalnet_subs_tariff_id, #novalnet_usr_subcl, #novalnet_enable_shop_subs' ).closest( 'tr' ).slideUp( 'fast' );
					}
				}
			).change();
			
			$( '#novalnet_book_order_amount' ).on(
				'click',
				function(){
					var booking_amount = $( '#novalnet_book_amount' ).val().replace(/[^0-9\.]/g, '');
					if ( '' == booking_amount || booking_amount <= 0 ) {
						$( document.body ).triggerHandler( 'wc_add_error_tip', [ $( '#novalnet_book_amount' ), "i18n_decimal_error",] );
						return false;
					}
					if ( ! confirm( wc_novalnet_admin_data.amount_booking_confirmation ) ) {
						return false;
					}
				}
			);

			$( '#novalnet_enable_shop_subs' ).on(
				'click',
				function() {
					if ( $( '#novalnet_enable_shop_subs' ).is(':checked') ) {
						if ( ! confirm( wc_novalnet_admin_data.shopbased_subs_warning ) ) {
							return false;
						}
					} else {
						if ( ! confirm( wc_novalnet_admin_data.server_subs_warning ) ) {
							return false;
						}
					}
				}
			);
			
			$( '#webhook_configure' ).on(
				'click',
				function() {

					if ( undefined === $( '#novalnet_webhook_url' ) || '' === $( '#novalnet_webhook_url' ).val() ) {
						wc_novalnet_admin.handle_webhook_configure( {"data": {"error" : wc_novalnet_admin_data.webhook_url_error } } );
						return false;
					}
					var webhook_url = $.trim( $( '#novalnet_webhook_url' ).val() );
					var regex       = /(http|https):\/\/(\w+:{0,1}\w*)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%!\-\/]))?/;

					if ( ! regex .test( webhook_url )) {
						wc_novalnet_admin.handle_webhook_configure( {"data": {"error" : wc_novalnet_admin_data.webhook_url_error } } );
						return false;
					}
					if ( confirm( wc_novalnet_admin_data.webhook_notice ) ) {
						wc_novalnet.load_block( 'mainform' );
						var data = {
							'novalnet_api_key': $.trim( $( '#novalnet_public_key' ).val() ),
							'novalnet_key_password': $.trim( $( '#novalnet_key_password' ).val() ),
							'novalnet_webhook_url': webhook_url,
							'action': 'handle_webhook_configure',
						};

						wc_novalnet_admin.ajax_call( data, 'webhook_configure' );
						return false;
					}
				}
			).change();

			$( '#novalnet_test_mode_message' ).hide();
			$( '#novalnet_webhook_url_message' ).hide();
			if ( $( '#novalnet_public_key' ).length && $( '#novalnet_key_password' ).length ) {
				$( '#novalnet_tariff_id, #novalnet_subs_tariff_id' ).prop( 'readonly', true );
				if ( '' !== $.trim( $( '#novalnet_public_key' ).val() ) && '' !== $.trim( $( '#novalnet_key_password' ).val() ) ) {
					wc_novalnet_admin.fill_novalnet_details();
				}
				$( '#novalnet_public_key, #novalnet_key_password' ).on(
					'input change',
					function(e) {

						$( this ).next( "input[type=text]" ).focus();
						if ( '' !== $.trim( $( '#novalnet_public_key' ).val() ) && '' !== $.trim( $( '#novalnet_key_password' ).val() ) ) {
							if ( 'input' === e.type ) {
								if (e.originalEvent.inputType != undefined && 'insertFromPaste' === e.originalEvent.inputType ) {
									wc_novalnet_admin.fill_novalnet_details();
								}
							} else {
								wc_novalnet_admin.fill_novalnet_details();
							}
						}
					}
				).change();
				$( '#novalnet_public_key' ).closest( 'form' ).on(
					'submit',
					function( event ) {
						if ( 'false' === wc_novalnet_admin.ajax_complete ) {
							event.preventDefault();
							$( document ).ajaxComplete(
								function( event, xhr, settings ) {
									$( '#novalnet_public_key' ).closest( 'form' ).submit();
								}
							);
						}
					}
				);
			}
			if ( 'undefined' !== typeof wc_novalnet_order_data ) {
				if (  undefined !== wc_novalnet_order_data.disallow_refund_reversal && '1' === wc_novalnet_order_data.disallow_refund_reversal ) {
					$( ".delete_refund" ).hide();
				}
			}
			if ( 'undefined' !== typeof wc_novalnet_admin_supported_payment ) {
				jQuery( '#_payment_method >option' ).each(
					function() {
						if ( -1 == jQuery.inArray( this.value, wc_novalnet_admin_supported_payment ) ) {
							if ( -1 != this.value.indexOf( "novalnet" ) ) {
								jQuery( '#_payment_method option[value="' + this.value + '"]' ).hide();
							}
						}
						if ( -1 != this.value.indexOf( "novalnet_guaranteed" ) ) {
							var guarantee_id = this.value;
							var text         = wc_novalnet_admin_data.guarantee_text[guarantee_id];
							jQuery( '#_payment_method option[value="' + this.value + '"]' ).html( text );
						}
					}
				);
				$( '#_billing_country , #_billing_company' ).on(
					'change',
					function() {
						var billing_country = $( '#_billing_country' ).val();
						if ( NovalnetUtility.isValidCompanyName( $( '#_billing_company' ).val() ) ) {
							var check_list = wc_novalnet_allowed_countries.b2b;
						} else {
							var check_list = wc_novalnet_allowed_countries.b2c;
						}
						if ( -1 == jQuery.inArray( billing_country , check_list ) ) {
							jQuery( '#_payment_method >option' ).each(
								function() {
									if ( -1 != this.value.indexOf( "novalnet_guaranteed" ) ) {
										$( "#_payment_method" ).val( $( "#_payment_method option:first" ).val() );
										jQuery( '.wc_shop_admin_order_novalnet_method' ).hide();
										jQuery( '#_payment_method option[value="' + this.value + '"]' ).hide();
									}
								}
							);
						} else {
							jQuery( '#_payment_method >option' ).each(
								function() {
									if ( -1 != this.value.indexOf( "novalnet_guaranteed" ) ) {
										jQuery( '#_payment_method option[value="' + this.value + '"]' ).show();
										wc_novalnet_admin.validate_form_fields();
									}
								}
							);
						}
					}
				);
				$( '#_payment_method, #_billing_company, #_transaction_id' ).on(
					'change',
					wc_novalnet_admin.validate_form_fields
				);
				$( document ).on(
					'submit',
					'#' + $( '#wc_shop_order_novalnet_payment_method' ).closest( 'form' ).attr( 'id' ),
					function( event ) {
						var payment_method = jQuery( '#_payment_method' ).val();
						if ( '' == payment_method || 0 == payment_method.length ) {
							if ( $( "select[id='_payment_method'] option[value*='novalnet_']" ).length > 0 ) {
								wc_novalnet.show_error( '#novalnet_admin_order_error', wc_novalnet_admin_data.payment_method_empty );
								event.preventDefault();
								event.stopImmediatePropagation();
							}
						}
						if ( -1 != jQuery.inArray( payment_method, wc_novalnet_admin_supported_payment ) ) {
							if ( '0' == $( '#novalnet_valid_amount' ).val() ) {
								$( '#wc_shop_order_novalnet_payment_method' ).closest( 'form' ).find( ':submit' ).attr( 'disabled', true );
								var data = {
									'novalnet_check_post_id': $.trim( $( '#post_ID' ).val() ),
									'novalnet_admin_payment': payment_method,
									'action': 'check_amount_admin_order',
								};
								event.preventDefault();
								event.stopImmediatePropagation();
								wc_novalnet_admin.ajax_call( data, 'admin_shop_order_amount_check' );
							}
						}
					}
				);
			}
			if ( ( 'auto-draft' != $( '#original_post_status' ).val() ) && 'shop_subscription' != $( '#post_type' ).val() ) {
				var processed_payment = $( '#_payment_method' ).val();
				$( '#_payment_method' ).on(
					'change' ,
					function(event) {
						if ( -1 != $( this ).val().indexOf( 'novalnet' ) || -1 != processed_payment.indexOf( 'novalnet' ) ) {
							alert( wc_novalnet_admin_data.change_payment_error );
							$( this ).val( processed_payment );
						}
					}
				);

			}
		},

		/**
		 * Prepare & send AJAX call.
		 *
		 * @param  {Object} data The request data.
		 * @param  {String} type The request type.
		 */
		ajax_call : function ( data, type = 'merchant_data' ) {

			// Checking for cross domain request.
			if ('XDomainRequest' in window && null !== window.XDomainRequest ) {
				var request_data = $.param( data );
				var xdr          = new XDomainRequest();
				xdr.open( 'POST' , novalnet_server_url );
				xdr.onload = function () {
					if ( 'merchant_data' === type ) {
						wc_novalnet_admin.handle_merchant_data( this.responseText );
					} else if ( 'webhook_configure' === type ) {
						wc_novalnet_admin.handle_webhook_configure( this.responseText );
					} else if ( 'admin_shop_order_amount_check' === type ) {
						wc_novalnet_admin.admin_shop_order_amount_check( response );
					} else if ( 'instalment_cancel' === type ) {
						wc_novalnet_admin.handle_instalment_cancel( response );
					}
				};
				xdr.send( request_data );
			} else {
				$.ajax(
					{
						type: 'POST',
						url: ajaxurl,
						data: data,
						success: function( response ) {
							if ( 'merchant_data' === type ) {
								wc_novalnet_admin.handle_merchant_data( response );
							} else if ( 'webhook_configure' === type ) {
								wc_novalnet_admin.handle_webhook_configure( response );
							} else if ( 'admin_shop_order_amount_check' === type ) {
								wc_novalnet_admin.admin_shop_order_amount_check( response );
							} else if ( 'instalment_cancel' === type ) {
								wc_novalnet_admin.handle_instalment_cancel( response );
							}
						}
					}
				);
			}
		},

		/**
		 * Handle merchant data.
		 *
		 * @param  {Object}  data The response data.
		 * @return {Boolean}
		 */
		handle_merchant_data : function ( data ) {

			$( '.blockUI' ).remove();
			data = data.data;

			if ( undefined !== data.error && '' !== data.error ) {
				$( '#novalnet_additional_info-description' ).html( '<div class="error inline notice"><p>' + data.error + '</p></div>' );
				$( 'html, body' ).animate(
					{
						scrollTop: ( $( '#novalnet_additional_info-description .error' ).offset().top - 200 )
					},
					1000
				);
				$( '#novalnet_test_mode_message' ).hide();
				wc_novalnet_admin.null_basic_params();
				return false;
			} else {
				$( '#novalnet_additional_info-description .error' ).hide();
			}
			var saved_tariff_id      = $( '#novalnet_tariff_id' ).val();
			var saved_subs_tariff_id = $( '#novalnet_subs_tariff_id' ).val();

			if ( 'text' == $( '#novalnet_tariff_id' ).prop( 'type' ) ) {
				$( '#novalnet_tariff_id' ).replaceWith( '<select id="novalnet_tariff_id" style="width:25em;" name= "novalnet_tariff_id" ></select>' );
			}
			if ( 'text' == $( '#novalnet_subs_tariff_id' ).prop( 'type' ) ) {
				$( '#novalnet_subs_tariff_id' ).replaceWith( '<select id="novalnet_subs_tariff_id" style="width:25em;"  name= "novalnet_subs_tariff_id" ></select>' );
			}
			$( '#novalnet_tariff_id, #novalnet_subs_tariff_id' ).empty().append();

			for ( var tariff_id in data.merchant.tariff ) {
				var tariff_type  = data.merchant.tariff[ tariff_id ].type;
				var tariff_value = data.merchant.tariff[ tariff_id ].name;

				if ('4' !== $.trim( tariff_type ) ) {
					$( '#novalnet_tariff_id' ).select2().append(
						$(
							'<option>',
							{
								value: $.trim( tariff_id ),
								text : $.trim( tariff_value )
							}
						).attr( "tariff_type", $.trim( tariff_type ) )
					);
				}

				/** Assign subscription tariff id. */
				if ('4' === $.trim( tariff_type ) ) {
					$( '#novalnet_subs_tariff_id' ).select2().append(
						$(
							'<option>',
							{
								value: $.trim( tariff_id ),
								text : $.trim( tariff_value )
							}
						)
					);
					if (saved_subs_tariff_id === $.trim( tariff_id ) ) {
						$( '#novalnet_subs_tariff_id' ).val( $.trim( tariff_id ) );
					}
				}

				/** Assign tariff id. */
				if (saved_tariff_id === $.trim( tariff_id ) ) {
					$( '#novalnet_tariff_id' ).val( $.trim( tariff_id ) );
					$( '#novalnet_tariff_type' ).val( $.trim( tariff_type ) );
				}
			}

			/** Assign vendor details. */
			$( '#novalnet_client_key' ).val( data.merchant.client_key );
			wc_novalnet_admin.ajax_complete = 'true';

			if (1 === data.merchant.test_mode) {
				$( '#novalnet_test_mode_message' ).show();
			} else {
				$( '#novalnet_test_mode_message' ).hide();
			}
			if ( undefined !== data.merchant.hook_url && '' !== data.merchant.hook_url) {
				$( '#novalnet_webhook_url_message' ).hide();
			} else {
				$( '#novalnet_webhook_url_message' ).show();
			}
			
			$('select#novalnet_tariff_id').on(
				'change',
				function() {
					var selected_tariff_type = $("option:selected", this).attr("tariff_type");
					$( '#novalnet_tariff_type' ).val( $.trim( selected_tariff_type ) );
				}
			);
			return true;
		},

		/**
		 * Process to fill the vendor details
		 *
		 * @param  {none}
		 */
		fill_novalnet_details : function () {

			wc_novalnet.load_block( 'mainform' );
			var data = {
				'novalnet_api_key': $.trim( $( '#novalnet_public_key' ).val() ),
				'novalnet_key_password': $.trim( $( '#novalnet_key_password' ).val() ),
				'action': 'get_novalnet_vendor_details',
			};

			wc_novalnet_admin.ajax_call( data );
		},

		/**
		 * Process to fill the vendor details
		 *
		 * @param  {none}
		 */
		handle_webhook_configure : function ( data ) {

			$( '.blockUI' ).remove();
			data = data.data;
			if ( undefined !== data.error && '' !== data.error ) {
				$( '#novalnet_additional_info-description' ).html( '<div class="error inline notice"><p>' + data.error + '</p></div>' );
			} else {
				$( '#novalnet_additional_info-description' ).html( '<div class="updated inline notice"><p>' + data.result.	status_text + '</p></div>' );
			}
			$( 'html, body' ).animate(
				{
					scrollTop: ( $( '#novalnet_additional_info-description' ).offset().top - 100 )
				},
				1000
			);
			return false;
		},

		handle_instalment_cancel : function ( data ) {
			$( '.blockUI' ).remove();
			location.reload( true )
		},

		/**
		 * Process to check admin order amount
		 *
		 * @param  {Object}  data The response data.
		 * @return {Boolean}
		 */
		admin_shop_order_amount_check : function ( data ) {
			data = data.data;
			$( '#novalnet_valid_amount' ).val( data['is_amount_valid'] );
			if ( undefined !== data.error && '' !== data.error ) {
				$( '#wc_shop_order_novalnet_payment_method' ).closest( 'form' ).find( ':submit' ).attr( 'disabled',false );
				wc_novalnet.show_error( '#novalnet_admin_order_error', data.error );
			} else {
				$( '#' + $( '#_payment_method' ).closest( 'form' ).attr( 'id' ) ).submit();
			}

		},

		/**
		 * Null config values
		 *
		 * @param  {none}
		 */
		null_basic_params : function () {

			wc_novalnet_admin.ajax_complete = 'true';
			$( '#novalnet_client_key' ).val( '' );
			$( '#novalnet_tariff_id' ).find( 'option' ).remove();
			$( '#novalnet_tariff_id' ).append(
				$(
					'<option>',
					{
						value: '',
						text : wc_novalnet_admin.select_text,
					}
				)
			);

			if ( 'select' == $( '#novalnet_tariff_id' ).prop( 'type' ) ) {
				$( '#novalnet_tariff_id' ).replaceWith( '<input type = "text" id="novalnet_tariff_id" style="width:25em;" name= "novalnet_tariff_id" >' );
			}

			$( '#novalnet_subs_tariff_id' ).find( 'option' ).remove();
			$( '#novalnet_subs_tariff_id' ).append(
				$(
					'<option>',
					{
						value: '',
						text : wc_novalnet_admin.select_text,
					}
				)
			);
		},

		/**
		 * Toggle Instalment Refund Div
		 *
		 * @param  id
		 */
		show_instalment_refund : function (id) {
			$( '#div_refund_link_' + id ).toggle();
		},

		/**
		 * Instalment Amount refund validation
		 *
		 * @param  id
		 */
		instalment_amount_refund : function (id) {
			var refund_amount = $( '#novalnet_instalment_refund_amount_' + id ).val();
			var refund_reason = $( '#novalnet_instalment_refund_reason_' + id ).val();
			var refund_tid    = $( '#novalnet_instalment_tid_' + id ).val();

			if ( '' === refund_amount || '0' >= refund_amount ) {
				alert( wc_novalnet_admin_data.refund_amount_error );
				return false;
			} else if ( ! window.confirm( woocommerce_admin_meta_boxes.i18n_do_refund ) ) {
				return false;
			} else {
				$( '#novalnet_instalment_refund_amount' ).val( refund_amount );
				$( '#novalnet_instalment_refund_tid' ).val( refund_tid );
				$( '#novalnet_instalment_refund_reason' ).val( refund_reason );
			}
			wc_novalnet_admin.load_block( 'novalnet-instalment-details', null );
		},

		/**
		 * Toggle Instalment Refund Div
		 *
		 * @param  id
		 */
		show_instalment_refund : function (id) {
			$( '#div_refund_link_' + id ).slideDown();
			$( '.novalnet-instalment-data-row-toggle.refund_button_' + id ).not( '#div_refund_link_' + id ).slideUp( "fast" );
		},

		/**
		 * Toggle Instalment Refund Div
		 *
		 * @param  id
		 */
		hide_instalment_refund : function (id) {
			$( '#div_refund_link_' + id ).slideUp();
			$( '.novalnet-instalment-data-row-toggle.refund_button_' + id ).not( '#div_refund_link_' + id ).slideDown( "fast" );
		},

		/**
		 * Add loader block
		 *
		 * @param   id
		 * @param   message
		 */
		load_block: function( id, message ) {
			$( '#' + id ).block(
				{
					message: message,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				}
			);
		},

		/**
		 * Toggle title and description field based on language
		 *
		 * @param  {Element} e
		 * @param  id
		 */
		toggle_payment_name:  function(e, id) {
			var lang_id = e.value;
			lang_id     = lang_id.toLowerCase();
			if (lang_id ) {
				$( 'input[id*=woocommerce_' + id + '_title], textarea[id*=woocommerce_' + id + '_description], textarea[id*=woocommerce_' + id + '_instructions] ' ).closest( 'tr' ).hide();
				$( '#woocommerce_' + id + '_title_' + lang_id + ', #woocommerce_' + id + '_description_' + lang_id + ', #woocommerce_' + id + '_instructions_' + lang_id ).closest( 'tr' ).show();
			}
		},

		/**
		 * Toggle on-hold limit field
		 *
		 * @param  {Element} e
		 * @param  id
		 */
		toggle_onhold_limit:  function(e, id) {
			if ( 'authorize' === e.value ) {
				$( '#woocommerce_' + id + '_limit' ).closest( 'tr' ).slideDown( "fast" );
				if ( 'novalnet_paypal' === id ) {
					$( '#novalnet_paypal_notice' ).html( wc_novalnet_admin_data.paypal_notice );
				}
			} else {
				$( '#woocommerce_' + id + '_limit' ).closest( 'tr' ).slideUp( "fast" );
				if ( 'novalnet_paypal' === id ) {
					$( '#novalnet_paypal_notice' ).html( '' );
				}
			}
		},

		/**
		 * Instalment cancel
		 */
		entire_instalment_cancel: function(data) {
			if (confirm( 'Are you sure want to cancel All instalment' )) {
				wc_novalnet.load_block( 'mainform' );
				var data = {
					'novalnet_instalment_cancel_tid': $.trim( $( '#instalment_cancel_tid' ).val() ),
					'instalment_cancel_order_id': $.trim( $( '#instalment_cancel_order_id' ).val() ),
					'novalnet_key_password': $.trim( $( '#novalnet_key_password' ).val() ),
					'cancel_type': 'CANCEL_ALL_CYCLES',
					'action': 'handle_instalment_cancel',
				};

				wc_novalnet_admin.ajax_call( data, 'instalment_cancel' );
				return false;
			}
		},

		/**
		 * Stop instalment
		 */
		stop_upcoming_instalment: function() {
			if ( confirm( 'Are you sure want to stop upcoming instalment' ) ) {
				wc_novalnet.load_block( 'mainform' );
				var data = {
					'novalnet_instalment_cancel_tid': $.trim( $( '#instalment_cancel_tid' ).val() ),
					'instalment_cancel_order_id': $.trim( $( '#instalment_cancel_order_id' ).val() ),
					'novalnet_key_password': $.trim( $( '#novalnet_key_password' ).val() ),
					'cancel_type': 'CANCEL_REMAINING_CYCLES',
					'action': 'handle_instalment_cancel',
				};

				wc_novalnet_admin.ajax_call( data, 'instalment_cancel' );
				return false;
			}
		},

		/**
		 * Show instalment cancel
		 */
		show_instalment_cancel_option: function() {
			$( "#instalment_cancel" ).hide();
			$( "#entire_instalment_cancel" ).show();
			$( "#stop_upcoming_instalment" ).show();
		},

		/**
		 * Validate form changes
		 */
		validate_form_fields: function() {
			var payment_method = jQuery( '#_payment_method' ).val();
			var transaction_id = jQuery( '#_transaction_id' ).val();
			if ( ( -1 != jQuery.inArray( payment_method, wc_novalnet_admin_supported_payment ) ) ) {
				jQuery( '.wc_shop_admin_order_novalnet_method' ).hide();
				jQuery( '#wc_shop_order_admin_' + payment_method ).show();
				if ( -1 != payment_method.indexOf( 'novalnet_guaranteed' ) ) {
					jQuery( '#' + payment_method + '_dob_field' ).show();
					if (NovalnetUtility.isValidCompanyName( $( '#_billing_company' ).val() ) && -1 != jQuery( '#wc_shop_order_admin_' + payment_method ).attr( 'allow_b2b' ).indexOf( 'yes' ) ) {
						jQuery( '#' + payment_method + '_dob_field' ).hide();
					} else {
						jQuery( '#' + payment_method + '_dob_field' ).show();
					}
				}
				if ( '0'!= transaction_id.length ) {
					if ( '17' == transaction_id.length && transaction_id.match( /^\d+$/ )) {
						jQuery( '.wc_shop_admin_order_novalnet_method' ).hide();
					} else {
						alert( wc_novalnet_admin_data.invalid_tid );
						jQuery( '#_transaction_id' ).val( '' );
					}
				} else {
					jQuery( '#wc_shop_order_admin_' + payment_method ).show();
					jQuery( '#_payment_method' ).parent().show();
					jQuery( '#nn_tid_payment' ).remove();
				}
			} else {
				jQuery( '.wc_shop_admin_order_novalnet_method' ).hide();
			}
		}
	};

	$( document ).ready(
		function () {
			wc_novalnet_admin.init();
		}
	);
})( jQuery );

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

			$( '#novalnet_enable_subs' ).on(
				'change',
				function() {
					if ( $( '#novalnet_enable_subs' ).is( ':checked' ) ) {
						$( '#novalnet_subs_payments, #novalnet_subs_tariff_id, #novalnet_usr_subcl' ).closest( 'tr' ).slideDown( "fast" );
					} else {
						$( '#novalnet_subs_payments, #novalnet_subs_tariff_id, #novalnet_usr_subcl' ).closest( 'tr' ).slideUp( 'fast' );
					}
				}
			).change();
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
					if (confirm( wc_novalnet_admin_data.webhook_notice )) {
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
			if ($( '#novalnet_public_key' ).length && $( '#novalnet_key_password' ).length) {
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
			if ('undefined' !== typeof wc_novalnet_order_data) {
				if (  undefined !== wc_novalnet_order_data.disallow_refund_reversal && '1' === wc_novalnet_order_data.disallow_refund_reversal ) {
					$( ".delete_refund" ).hide();
				}
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

			if (undefined !== data.error && '' !== data.error ) {
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

			if ($( '#novalnet_tariff_id' ).prop( 'type' ) == 'text') {
				$( '#novalnet_tariff_id' ).replaceWith( '<select id="novalnet_tariff_id" style="width:25em;" name= "novalnet_tariff_id" ></select>' );
			}
			if ($( '#novalnet_subs_tariff_id' ).prop( 'type' ) == 'text') {
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
						)
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
			if (data.merchant.hook_url !== undefined && '' !== data.merchant.hook_url) {
				$( '#novalnet_webhook_url_message' ).hide();
			} else {
				$( '#novalnet_webhook_url_message' ).show();
			}
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
			if (undefined !== data.error && '' !== data.error ) {
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

			if ($( '#novalnet_tariff_id' ).prop( 'type' ) == 'select') {
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
		}
	};

	$( document ).ready(
		function () {
			wc_novalnet_admin.init();
		}
	);
})( jQuery );

/**
 * Novalnet Wallet JS.
 *
 * @category  JS
 * @package   Novalnet
 */

(function($){

	wc_novalnet_wallet = {
		initiate_wallet : function(id, wallet) {

			var instance = wallet + 'Instance';

			instance           = NovalnetPayment().createPaymentObject();
			var payment_method = wallet.toUpperCase();
			instance.setPaymentIntent( wc_novalnet_wallet.walletPaymentRequest( id, payment_method ) );
			instance.isPaymentMethodAvailable(
				function(canShowWallet) {
					if ( canShowWallet ) {
						$( "#" + id ).empty();
						instance.addPaymentButton( "#" + id );
						$( ".wallet_seperator" ).show();
						var productPage = "#product_page_" + wallet + "_button";
						if ( ['product_page_googlepay_button', 'product_page_applepay_button'].includes( id ) ) {
							var width = jQuery( productPage ).width() - 5 + "px";
							if ( wallet == 'applepay' ) {
								 $( "#product_page_applepay_button" ).find( "apple-pay-button" ).css( {'width': width} );
							} else {
								$( "#product_page_googlepay_button" ).find( "button" ).css( {'width': width} );
							}

						} else if ( ['mini_cart_page_applepay_button', 'mini_cart_page_googlepay_button'].includes( id ) ) {
							if ( 'mini_cart_page_applepay_button' == id ) {
								$( "#mini_cart_page_applepay_button" ).find( "apple-pay-button" ).css( {'min-width':'84%', 'width':'84%', 'margin-left' : '7.6%'} );
							} else {
								$( "#mini_cart_page_googlepay_button" ).find( "button" ).css( {'min-width':'85%', 'width':'20%', 'margin-left' : '7%'} );
							}
						} else {
							if ( ["shopping_cart_page_applepay_button", "checkout_page_applepay_button", "guest_checkout_page_applepay_button", "paylater_page_applepay_button" ].includes( id ) ) {
								$( "#shopping_cart_page_applepay_button, #checkout_page_applepay_button, #guest_checkout_page_applepay_button, #paylater_page_applepay_button" ).find( "apple-pay-button" ).css( {'width': "100%"} );
							} else {
								$( "#shopping_cart_page_googlepay_button, #checkout_page_googlepay_button, #guest_checkout_page_googlepay_button", "#paylater_page_googlepay_button" ).find( "button" ).css( {'width': "100%"} );
							}
						}
					} else {
						$( "#" + id ).hide();
					}
				}
			);
		},
		walletPaymentRequest : function(id, payment_method) {
			if ( payment_method.toLowerCase() == 'applepay' ) {
				var button_height = my_ajax_object.applepay_setting.apple_pay_button_height;
				var theme         = my_ajax_object.applepay_setting.apple_pay_button_theme;
				var style         = my_ajax_object.applepay_setting.apple_pay_button_type;
				var seller_name   = $( '<textarea/>' ).html( my_ajax_object.applepay_setting.seller_name ).text();
				var cornerRadius  = my_ajax_object.applepay_setting.apple_pay_button_corner_radius;

				var enforce_3d = (my_ajax_object.applepay_setting.enforce_3d == "yes") ? true : false;
				var mode       = (my_ajax_object.applepay_setting.test_mode == "yes") ? "SANDBOX" : "PRODUCTION";

				var boxSizingVal = 'border-box';
			} else {
				var button_height = my_ajax_object.googlepay_setting.google_pay_button_height;
				var style         = my_ajax_object.googlepay_setting.google_pay_button_type;
				var seller_name   = $( '<textarea/>' ).html( my_ajax_object.googlepay_setting.seller_name ).text();
				var enforce_3d    = (my_ajax_object.googlepay_setting.enforce_3d == "yes") ? true : false;
				var partner_id    = my_ajax_object.googlepay_setting.partner_id;
				var mode          = (my_ajax_object.googlepay_setting.test_mode == "yes") ? "SANDBOX" : "PRODUCTION";

				var cornerRadius = 0;
				var boxSizingVal = 'fill';
			}
			var shipping = ["postalAddress", "phone", "email"];
			var is_virtual        = ( id.indexOf( 'product_page' ) > -1 ) ? $( "#product_has_virtual_product" ).val() : $( "#cart_has_virtual" ).val();

			if ( is_virtual == 1 ) {
				var shipping = ["email"];
			}

			var button_dimensions = {
				width:"auto",
				cornerRadius:parseInt( cornerRadius ),
				height: parseInt( button_height ),
			}
			var billing_setting   = $( "#setpending" ).val();
			var setpending        = ( billing_setting == "1" || is_virtual == 1 ) ? true : false;

			/** Initiate Applepay process */
			var requestData = {
				clientKey: my_ajax_object.client_key,
				paymentIntent: {
					transaction: {
						amount: String( $( "#" + id ).attr( "data-total" ) ),
						currency: String( $( "#" + id ).attr( "data-currency" ) ),
						paymentMethod: payment_method,
						enforce3d: enforce_3d,
						environment: mode,
						setPendingPayment: setpending,
					},
					merchant: {
						countryCode :String( $( "#" + id ).attr( "data-country" ) ),
						paymentDataPresent: false,
					},
					custom: {
						lang: String( $( "#" + id ).attr( "data-storeLang" ) ),
					},
					order: {
						paymentDataPresent: false,
						merchantName: seller_name,
						lineItems: JSON.parse( $( "#novalnet_wallet_article_details" ).val() ),
						billing: {
							requiredFields: ["postalAddress", "phone", "email"]
						},
						shipping: {
							requiredFields: shipping,
							methodsUpdatedLater: true
						}
					},
					button: {
						dimensions: button_dimensions,
						locale: String( $( "#" + id ).attr( "data-storeLang" ) ),
						type: style,
						boxSizing: boxSizingVal,
					},
					callbacks: {
						onProcessCompletion: function (response, processedStatus) {
							// Only on success, we proceed further with the booking.
							if (response.result.status == "SUCCESS") {
								var response = {response : response};
								var data     = {
									'action': 'novalnet_order_creation', // your action name.
									'payment': payment_method.toLowerCase(), // your action name.
									'variable_name': response, // some additional data to send.
								};
								if ( $( "#pay_for_order_id" ).val() !== '' ) {
									data.pay_for_order_id = $( "#pay_for_order_id" ).val();
								}
								
								if ( is_virtual == 1 ) {
									data.is_virtual_order = 1;
								}
								
								$.ajax(
									{
										url: my_ajax_object.ajax_url, // this will point to admin-ajax.php.
										type: 'POST',
										data: data,
										success: function (order_response) {
											if ( order_response.result == 'success' ) {
												processedStatus( {status: "SUCCESS", statusText: ''} );
												window.location.replace( order_response.redirect );
											} else if (order_response.result == 'error' ) {
												processedStatus( {status: "FAILURE", statusText: order_response.redirect} );
												if( payment_method.toLowerCase() == 'applepay' ) {
													alert( order_response.redirect );
												}
											}
										},
										error: function(xhr){
											alert( xhr.responseText );
										}
									}
								);
							}
						},
						onShippingContactChange: function (shippingContact, updatedRequestData) {
							var payload = {address : shippingContact};
							var data    = {
								'action': 'novalnet_shipping_address_update', // your action name.
								'shippingInfo': JSON.stringify( payload ), // your action name.
								'shippingAddressChange': '1', // some additional data to send.
								'simple_product_id': $( "#novalnet_product_id" ).val(), // some additional data to send.
								'variable_product_id': $( "input[name=product_id]" ).val(), // some additional data to send.
								'variable_variant_id': $( "input[name=variation_id]" ).val(), // some additional data to send.
								'source_page': id // some additional data to send.
							};

							$.ajax(
								{
									url: my_ajax_object.ajax_url, // this will point to admin-ajax.php.
									type: 'POST',
									data: data,
									success: function (response) {
										let updatedInfo = {};
										if ( response.shipping_address.length == 0 ) {
											updatedInfo.methodsNotFound = "There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.";
										} else if ( response.shipping_address != undefined && response.shipping_address.length ) {
											updatedInfo.amount            = response.amount;
											updatedInfo.lineItems         = response.article_details;
											updatedInfo.methods           = response.shipping_address;
											updatedInfo.defaultIdentifier = response.shipping_address[0].identifier;
										}
										updatedRequestData( updatedInfo );
									}
								}
							);
						},
						onShippingMethodChange: function (choosenShippingMethod, updatedRequestData) {
							var payload = {shippingMethod : choosenShippingMethod};
							var data    = {
								'action': 'novalnet_shipping_method_update', // your action name.
								'shippingInfo': JSON.stringify( payload ), // your action name.
								'shippingAddressChange': '1' // some additional data to send.
							};

							$.ajax(
								{
									url: my_ajax_object.ajax_url, // this will point to admin-ajax.php.
									type: 'POST',
									data: data,
									success: function (response) {
										var updatedInfo = {
											amount: response.amount,
											lineItems: response.order_info,
										};
										updatedRequestData( updatedInfo );
									}
								}
							);
						},
						onPaymentButtonClicked: function(clickResult) {
							
							if ( $( "#pay_for_order_id" ).val() == '' ) {
								let text = $( ".single_add_to_cart_button" ).attr( "class" );
								if ( text != undefined ) {
									let ids = ['product_page_googlepay_button', 'mini_cart_page_googlepay_button', 'product_page_applepay_button', 'mini_cart_page_applepay_button'];
									if ( ids.includes( id ) && text.includes( "wc-variation-selection-needed" ) ) {
										$( ".single_add_to_cart_button" ).click();
										clickResult( {status: "FAILURE"} );
										return false;
									} else {
										if ( is_virtual == 1 && ids.includes( id ) ) {
											
											var data = {
												'action': 'add_virtual_product_in_cart', // your action name.
												'simple_product_id': $( "#novalnet_product_id" ).val(), // some additional data to send.
												'variable_product_id': $( "input[name=product_id]" ).val(), // some additional data to send.
												'variable_variant_id': $( "input[name=variation_id]" ).val(), // some additional data to send.
											};
											$.ajax(
												{
													url: my_ajax_object.ajax_url, // this will point to admin-ajax.php.
													type: 'POST',
													data: data,
													success: function (response) {
														console.log( 'Product added' );
													}
												}
											);
										}
										clickResult( {status: "SUCCESS"} );
									}
								} else {
									clickResult( {status: "SUCCESS"} );
								}
							}
							clickResult( {status: "SUCCESS"} );
						}
					}
				}
			};
			if ( payment_method.toLowerCase() == 'googlepay' ) {
				if ( is_virtual == 1 || $( "#pay_for_order" ).val() || $( "#cart_has_one_time_shipping" ).val() == 1 ) {
					delete requestData.paymentIntent.order.shipping;
				}
				delete requestData.paymentIntent.button.dimensions.cornerRadius;
				requestData.paymentIntent.merchant.partnerId = partner_id;
			} else {
				requestData.paymentIntent.button.style = theme;
				delete requestData.paymentIntent.transaction.enforce3d;
			}

			if ( payment_method.toLowerCase() == 'applepay' && ( is_virtual == 1 || $( "#pay_for_order" ).val() ) ) {
				delete requestData.paymentIntent.order.shipping.methodsUpdatedLater;
			}
			return requestData;
		},
	};

	$( document ).ready(
		function () {

			$( document ).ajaxComplete(
				function( event, xhr, settings ) {
					var id = jQuery( "div" ).find( `[data-id = "googlepay_wallet_button"]` ).attr( "id" );
					$( "#" + id ).empty();
					if ( undefined != id && $( "#novalnet_wallet_article_details" ).val() != undefined ) {
						wc_novalnet_wallet.initiate_wallet( id, "googlepay" );
					}

					var id = jQuery( "div" ).find( `[data-id = "applepay_wallet_button"]` ).attr( "id" );
					$( "#" + id ).empty();
					if ( undefined != id && $( "#novalnet_wallet_article_details" ).val() != undefined ) {
						wc_novalnet_wallet.initiate_wallet( id, "applepay" );
					}
				}
			);

			$( document.body ).on(
				'mouseenter',
				'.cart-contents',
				function(){
					var id = jQuery( "div" ).find( `[data-id = "googlepay_wallet_button"]` ).attr( "id" );
					if ( undefined != id && id == 'mini_cart_page_googlepay_button' ) {
						$( "#mini_cart_page_googlepay_button :button" ).remove();
						wc_novalnet_wallet.initiate_wallet( id, "googlepay" )
					}
					var id = jQuery( "div" ).find( `[data-id = "applepay_wallet_button"]` ).attr( "id" );
					if ( undefined != id && id == 'mini_cart_page_applepay_button') {
						$( "#mini_cart_page_applepay_button :button" ).remove();
						wc_novalnet_wallet.initiate_wallet( id, "applepay" )
					}
				}
			);
		}
	);
})( jQuery );

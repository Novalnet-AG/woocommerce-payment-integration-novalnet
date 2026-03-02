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
			if( 'undefined' === typeof id ){
				return;
			}
			instance           = NovalnetPayment().createPaymentObject();
			var payment_method = wallet.toUpperCase();
			instance.setPaymentIntent( wc_novalnet_wallet.walletPaymentRequest( id, payment_method ) );
			instance.isPaymentMethodAvailable(
				function(canShowWallet) {
					if (canShowWallet) {
						const $container = $("#" + id);
						$container.empty();
						instance.addPaymentButton("#" + id);
					  
						$(".wallet_seperator").show();
					  
						const isProductPage = [
						  "product_page_googlepay_button",
						  "product_page_applepay_button"
						].includes(id);
					  
						const isMiniCart = [
						  "mini_cart_page_applepay_button",
						  "mini_cart_page_googlepay_button"
						].includes(id);
					  
						const appleSelector = "apple-pay-button";
						const googleSelector = "button";
					  
						if (isProductPage) {
						  const width = $("#product_page_" + wallet + "_button").width() - 5 + "px";
					  
						  $container
							.find(wallet === "applepay" ? appleSelector : googleSelector)
							.css({ width });
					  
						} else if (isMiniCart) {
					  
						  if (wallet === "applepay") {
							$container.find(appleSelector).css({
							  "min-width": "84%",
							  width: "84%",
							  "margin-left": "7.6%"
							});
						  } else {
							$container.find(googleSelector).css({
							  "min-width": "85%",
							  width: "20%",
							  "margin-left": "7%"
							});
						  }
					  
						} else {

							
								if (id.includes("paylater")) {
									const $btn = $("#" + id);

									$(".shop_table").after($btn);

									$btn.css({
									marginBottom: "20px"
									});
								}

								$(".wallet_seperator").show();

						  
							const appleIds = [
							  "#shopping_cart_page_applepay_button",
							  "#checkout_page_applepay_button",
							  "#guest_checkout_page_applepay_button",
							  "#paylater_page_applepay_button"
							];
						  
							const googleIds = [
							  "#shopping_cart_page_googlepay_button",
							  "#checkout_page_googlepay_button",
							  "#guest_checkout_page_googlepay_button",
							  "#paylater_page_googlepay_button"
							];
						  
							const selector = wallet === "applepay"
							  ? appleIds.join(", ")
							  : googleIds.join(", ");
						  
							$(selector)
							  .find(wallet === "applepay" ? appleSelector : googleSelector)
							  .css({
								width: "100%"
							  });
						  }
						  
					  }
					   else {
						$( "#" + id ).hide();
					}
				}
			);
		},
		walletPaymentRequest : function(id, payment_method) {
			if ( 'applepay' == payment_method.toLowerCase() ) {
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
			var needs_payer_phone = my_ajax_object .needs_payer_phone;
			var shipping = ["postalAddress", "phone", "email"];
			var is_virtual        = ( id.indexOf( 'product_page' ) > -1 ) ? $( "#product_has_virtual_product" ).val() : $( "#cart_has_virtual" ).val();
			if ( 1 == is_virtual ) {
				if(needs_payer_phone == 1){
				    var shipping = ["email","phone"];
				}
				else{
					var shipping = ["email"];
				}
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
						lang: $( "#" + id ).attr( "data-storeLang" ) === 'de'
						? 'de-DE'
						: 'en-US',
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
						locale:$( "#" + id ).attr( "data-storeLang" ) === 'de'
						? 'de-DE'
						: 'en-US',
						type: style,
						boxSizing: boxSizingVal,
					},
					callbacks: {
						onProcessCompletion: function (response, processedStatus) {
							
							let customer_billing  = {};
														let customer_shipping = {};

														let billing  = response?.order?.billing?.contact || {};
														let shipping = response?.order?.shipping?.contact || {};

														let phone_number =
															billing.phoneNumber ||
															shipping.phoneNumber ||
															"";

										
														const billing_address =
															Array.isArray(billing.addressLines)
																? billing.addressLines.join(' ')
																: (billing.addressLines || "");

														const shipping_address =
															Array.isArray(shipping.addressLines)
																? shipping.addressLines.join(' ')
																: (shipping.addressLines || "");

														
														customer_billing = {
															first_name: billing.firstName || "",
															last_name:  billing.lastName || "",
															address_1:  billing_address,
															city:       billing.locality || "",
															email:      billing.email || shipping.email || "",
															postcode:   billing.postalCode || "",
															country:    billing.countryCode || "",
															phone:      phone_number
														};

														if (shipping.firstName || shipping.lastName) {
															customer_shipping = {
																first_name: shipping.firstName || "",
																last_name:  shipping.lastName || "",
																address_1:  shipping_address,
																city:       shipping.locality || "",
																email:      billing.email || shipping.email || "",
																postcode:   shipping.postalCode || "",
																country:    shipping.countryCode || "",
																phone:      shipping.phoneNumber || ""
															};
														}			
							// Only on success, we proceed further with the booking.
							if ( "SUCCESS" == response.result.status ) {
								var response = {response : response};
								var data     = {
									'action': 'novalnet_order_creation', // your action name.
									'payment': payment_method.toLowerCase(), // your action name.
									'customer_billing':customer_billing,
                                    'customer_shipping':customer_shipping,
									'billing':billing,
									'shipping':shipping,
									'variable_name':response,
								};
								if ( '' !== $( "#pay_for_order_id" ).val() ) {
									data.pay_for_order_id = $( "#pay_for_order_id" ).val();
								}

								if ( 1 == is_virtual ) {
									data.is_virtual_order = 1;
								}
								$.ajax(
									{
										url: my_ajax_object.ajax_url, // this will point to admin-ajax.php.
										type: 'POST',
										data: data,
										success: function (order_response) {
											if ( 'success' == order_response.result ) {
												processedStatus( {status: "SUCCESS", statusText: ''} );
												wc_novalnet_wallet.blockDocumentBody();
												window.location.replace( order_response.redirect );
											} else if ( 'error' == order_response.result ) {
												processedStatus( {status: "FAILURE", statusText: order_response.redirect} );
												if( 'applepay' == payment_method.toLowerCase() ) {
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
										if ( 0 == response.shipping_address.length ) {
											updatedInfo.methodsNotFound = "There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.";
										} else if ( undefined != response.shipping_address && response.shipping_address.length ) {
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
							if ( '' == $( "#pay_for_order_id" ).val() ) {
								let text = $( ".single_add_to_cart_button" ).attr( "class" );
								if ( undefined != text ) {
									let ids = ['product_page_googlepay_button', 'mini_cart_page_googlepay_button', 'product_page_applepay_button', 'mini_cart_page_applepay_button'];
									if ( ids.includes( id ) && text.includes( "wc-variation-selection-needed" ) ) {
										$( ".single_add_to_cart_button" ).click();
										clickResult( {status: "FAILURE"} );
										return false;
									} else {
										if ( 1 == is_virtual && ids.includes( id ) ) {
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
							} else {
								clickResult( {status: "SUCCESS"} );
							}
						}
					}
				}
			};
			if ( 'googlepay' == payment_method.toLowerCase() ) {
				if ( 1 == is_virtual || $( "#pay_for_order" ).val() || 1 == $( "#cart_has_one_time_shipping" ).val() ) {
					delete requestData.paymentIntent.order.shipping;
				}
				delete requestData.paymentIntent.button.dimensions.cornerRadius;
				requestData.paymentIntent.merchant.partnerId = partner_id;
			} else {
				requestData.paymentIntent.button.style = theme;
				delete requestData.paymentIntent.transaction.enforce3d;
			}

			if ( 'applepay' == payment_method.toLowerCase() && ( 1 == is_virtual || $( "#pay_for_order" ).val() ) ) {
				delete requestData.paymentIntent.order.shipping.methodsUpdatedLater;
			}
			return requestData;
		},
		blockDocumentBody : function() {
			var isBodyBlocked = $( document.body ).data( 'blockUI.isBlocked' );
			if ( 1 !== isBodyBlocked ) {
				$( document.body ).block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});
			}
		},
		walletInitiator : function() {
			var id = jQuery( "div" ).find( `[data-id = "googlepay_wallet_button"]` ).attr( "id" );
			$( "#" + id ).empty();
			if ( undefined != id && undefined != $( "#novalnet_wallet_article_details" ).val() ) {
				wc_novalnet_wallet.initiate_wallet( id, "googlepay" );
			}

			var id = jQuery( "div" ).find( `[data-id = "applepay_wallet_button"]` ).attr( "id" );
			$( "#" + id ).empty();
			if ( undefined != id && undefined != $( "#novalnet_wallet_article_details" ).val() ) {
				wc_novalnet_wallet.initiate_wallet( id, "applepay" );
			}
		},
	};

	$( document ).ready(
		function () {

			$( document.body ).on( 'update_checkout wc_fragments_refreshed updated_cart_totals', function( response ) {
				wc_novalnet_wallet.walletInitiator();
			});

			$( document.body ).on(
				'mouseenter',
				'.cart-contents',
				function(){
					var id = jQuery( "div" ).find( `[data-id = "googlepay_wallet_button"]` ).attr( "id" );
					if ( undefined != id && 'mini_cart_page_googlepay_button' == id ) {
						$( "#mini_cart_page_googlepay_button :button" ).remove();
						wc_novalnet_wallet.initiate_wallet( id, "googlepay" )
					}
					var id = jQuery( "div" ).find( `[data-id = "applepay_wallet_button"]` ).attr( "id" );
					if ( undefined != id && 'mini_cart_page_applepay_button' == id ) {
						$( "#mini_cart_page_applepay_button :button" ).remove();
						wc_novalnet_wallet.initiate_wallet( id, "applepay" )
					}
				}
			);
		}
	);
})( jQuery );

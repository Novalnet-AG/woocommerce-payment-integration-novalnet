/**
 * Novalnet Applepay JS.
 *
 * @category  JS
 * @package   Novalnet
 */

(function($){

	wc_novalnet_applepay = {

		/** Initiate Applepay process */
		init : function() {

			$('.nn-apple-pay').addClass(`${my_ajax_object.payment_setting.apple_pay_button_type} ${my_ajax_object.payment_setting.apple_pay_button_theme}`).css({ 'height': `${my_ajax_object.payment_setting.apple_pay_button_height}px`, 'border-radius':  `${my_ajax_object.payment_setting.apple_pay_button_corner_radius}px`});
                
			var isApplePayAvailable = NovalnetUtility.isApplePayAllowed();
			// Only if Apple Pay is available, we display the button for processing
			if (isApplePayAvailable) {
				$(".nn-apple-pay").show();
				$(".apple_split").show();
			} else{
				$(".nn-apple-pay").hide();
				$(".apple_split").hide();
			}

			$( document ).ajaxComplete(
				function( event, xhr, settings ) {
					$('.nn-apple-pay').addClass(`${my_ajax_object.payment_setting.apple_pay_button_type} ${my_ajax_object.payment_setting.apple_pay_button_theme}`).css({ 'height': `${my_ajax_object.payment_setting.apple_pay_button_height}px`, 'border-radius':  `${my_ajax_object.payment_setting.apple_pay_button_corner_radius}px`});
					var isApplePayAvailable = NovalnetUtility.isApplePayAllowed();
					// Only if Apple Pay is available, we display the button for processing
					if (isApplePayAvailable) {
						$(".nn-apple-pay").show();
						$(".apple_split").show();
					} else{
						$(".nn-apple-pay").hide();
						$(".apple_split").hide();
					}
				}
			);
		},
	};

	$( document ).ready(
		function () {

			$(document.body).on('mouseenter','.cart-contents',function(){
				$('.nn-apple-pay').addClass(`${my_ajax_object.payment_setting.apple_pay_button_type} ${my_ajax_object.payment_setting.apple_pay_button_theme}`).css({ 'height': `${my_ajax_object.payment_setting.apple_pay_button_height}px`, 'border-radius':  `${my_ajax_object.payment_setting.apple_pay_button_corner_radius}px`});

				var isApplePayAvailable = NovalnetUtility.isApplePayAllowed();
				// Only if Apple Pay is available, we display the button for processing.
				if (isApplePayAvailable) {
					$("#minicart_apple_pay_button").show();
				}else{
					$(".payment_method_novalnet_applepay").hide();
					$("#minicart_apple_pay_button").hide();
				}
			});

			$(document.body).on('click','#minicart_apple_pay_button, #cart_page_apple_pay_button, #product_details_page_apple_pay_button,#myaccount_page_apple_pay_button,#checkout_apple_pay_button',function(){

				var sourch_button_id = $(this).attr("id");
				if( 'product_details_page_apple_pay_button' == $(this).attr("id")) {
					let text = $(".single_add_to_cart_button").attr("class");

					if( text.includes("wc-variation-selection-needed") ) {
						$(".single_add_to_cart_button").click();
						return false;
					}
				}
				

				// Set Your Client Key
				NovalnetUtility.setClientKey( my_ajax_object.client_key );

				var requestData = {
					transaction: {
						amount: $(this).attr("data-applepayTotal"),
						currency: $(this).attr("data-applepayCurrency"),
					},
					merchant: {
						country_code :$(this).attr("data-applepayCountry"),
						shop_name: $(this).attr("data-applepayShopname"),
					}, 
					custom: {
						lang: $(this).attr("data-storeLang"),
					},
					wallet: {
								shop_name: $(this).attr("data-applepayShopname"),
								order_info: JSON.parse($("#novalnet_applepay_article_details").val()),
								shipping_methods: JSON.parse($("#novalnet_applepay_shipping_details").val()),
								shipping_configuration: 
								{
								  calc_final_amount_from_shipping : '0'
								},
								required_fields: {
									shipping: ['postalAddress', 'email', 'name', 'phone'],
									contact: ['postalAddress'],
								}
							},
					callback: {
						on_completion: function (responseData, processedStatus) {
							// Only on success, we proceed further with the booking
							if (responseData.result.status == 'SUCCESS') {
								var response = {response : responseData};
								var data = {
								'action': 'novalnet_order_creation', // your action name 
								'variable_name': response, // some additional data to send
								};

								$.ajax({
									url: my_ajax_object.ajax_url, // this will point to admin-ajax.php
									type: 'POST',
									data: data,
									success: function (order_response) {
										if( order_response.result == 'success' ) {
											window.location.replace(order_response.redirect);
										} else if(order_response.result == 'error' ) {
											alert( order_response.redirect );
										}
									},
									error: function(xhr){
										alert(xhr.responseText);
									}
								});
							} else {
								alert(responseData.result.status_text);
							}
						},
						on_shippingcontact_change: function (shippingContact, updatedData) {
							var payload = {address : shippingContact};
							var data = {
							'action': 'novalnet_shipping_address_update', // your action name 
							'shippingInfo': JSON.stringify(payload), // your action name 
							'shippingAddressChange': '1', // some additional data to send
							'simple_product_id': $("#novalnet_applepay_product_id").val(), // some additional data to send
							'variable_product_id': $("input[name=product_id]").val(), // some additional data to send
							'variable_variant_id': $("input[name=variation_id]").val(), // some additional data to send
							'source_page': sourch_button_id // some additional data to send
							};

							$.ajax({
								url: my_ajax_object.ajax_url, // this will point to admin-ajax.php
								type: 'POST',
								data: data,
								success: function (response) {
									var updatedInfo = {
										amount:response.amount,
										order_info: response.article_details,
										shipping_methods:response.shipping_address,  
									};
									updatedData(updatedInfo);    
								}
							});
						},
						on_shippingmethod_change: function (choosenShippingMethod, updatedData) {
							var payload = {shippingMethod : choosenShippingMethod};
							var data = {
							'action': 'novalnet_shipping_method_update', // your action name 
							'shippingInfo': JSON.stringify(payload), // your action name 
							'shippingAddressChange': '1' // some additional data to send
							};

							$.ajax({
								url: my_ajax_object.ajax_url, // this will point to admin-ajax.php
								type: 'POST',
								data: data,
								success: function (response) {
									var updatedInfo = {
										amount: response.amount,
										order_info: response.order_info,
									};
									updatedData(updatedInfo);    
								}
							});
						}
					}
				};
				NovalnetUtility.processApplePay(requestData);
				return false;
			});
			wc_novalnet_applepay.init();
		}
	);

})( jQuery );

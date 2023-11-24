(function( $, blockRegistry, wcSettings, wpElement, i18n, htmlEntities ){
    novalnetPaymentElement = {
        getBirthField: ( paymentType ) => {
            return Object(wpElement.createElement)(
                'div',
                {
                    id: paymentType + '_dob_field',
                    className: 'wc-block-gateway-container',
                },
                Object(wpElement.createElement)(
                    'input',
                    {
                        type: 'text',
                        id: paymentType + '_dob',
                        name: paymentType + '_dob',
                        className : 'wc-block-gateway-input',
                        autoComplete: 'OFF',
                        placeholder:Object(i18n.__)('DD.MM.YYYY', 'woocommerce-novalnet-gateway'),
                        style: {
                            textTransform:'uppercase',
                        },
                        onKeyDown:(e) => {
                            return NovalnetUtility.isNumericBirthdate( e.target, e.nativeEvent );
                        },
                        onBlur:(e) => {
                            return wc_novalnet.validate_date_format( e.target, paymentType )
                        },
                    }
                ),
                Object(wpElement.createElement)(
                    'label',
                    { htmlFor: paymentType + '_dob' },
                    Object(i18n.__)(
                      'Your date of birth',
                      'woocommerce-novalnet-gateway'
                    )
                ),
            );
        },
        getIBANField: ( paymentType ) => {
            return Object(wpElement.createElement)(
                'div',
                {
                    id: paymentType + '_iban_field',
                    className: 'wc-block-gateway-container',
                },
                Object(wpElement.createElement)(
                    'input',
                    {
                        type: 'text',
                        id: paymentType + '_iban',
                        name: paymentType + '_iban',
                        className : 'wc-block-gateway-input',
                        autoComplete: 'OFF',
                        placeholder:'DE00 0000 0000 0000 0000 00',
                        style: {
                            textTransform:'uppercase',
                        },
                        onKeyPress:(e) => {
                            return NovalnetUtility.checkIban(e.nativeEvent, paymentType + '_bic_field' );
                        },
                        onChange:(e) => {
                            return NovalnetUtility.formatIban(e.nativeEvent, paymentType + '_bic_field' );
                        },
                        onKeyUp:(e) => {
                            return NovalnetUtility.formatIban(e.nativeEvent, paymentType + '_bic_field' );
                        },
                    }
                ),
                Object(wpElement.createElement)(
                    "label",
                    { htmlFor: paymentType + '_iban' },
                    Object(i18n.__)(
                      "IBAN ",
                      "woocommerce-novalnet-gateway"
                    )
                ),
            );
        },
        getBICField:( paymentType ) => {
            return Object(wpElement.createElement)(
                'div',
                {
                    id: paymentType + '_bic_field',
                    className: 'wc-block-gateway-container',
                    style: {
                        display: 'none',
                    }
                },
                Object(wpElement.createElement)(
                    'input',
                    {
                        type: 'text',
                        id: paymentType + '_bic',
                        name: paymentType + '_bic',
                        className : 'wc-block-gateway-input',
                        autoComplete: 'OFF',
                        placeholder:'XXXX XX XX XXX',
                        style: {
                            textTransform:'uppercase',
                        },
                        onKeyPress:(e) => {
                            return NovalnetUtility.formatBic(e.nativeEvent);
                        },
                        onChange:(e) => {
                            return NovalnetUtility.formatBic(e.nativeEvent);
                        },
                    }
                ),
                Object(wpElement.createElement)(
                    "label",
                    { htmlFor: paymentType + '_bic' },
                    Object(i18n.__)(
                      "BIC ",
                      "woocommerce-novalnet-gateway"
                    )
                ),
            );
        },
        getPaymentMethodLabel: ( component, paymentData, paymentTitle ) => {
            const paymentMethodIcon = ( null !== paymentData && paymentData.icons.length ) ? Object(wpElement.createElement)( component.PaymentMethodIcons, { icons: paymentData.icons, align : 'right' } ) : null;
            return Object(wpElement.createElement)(
                'div',
                {className: 'novalnet-block-checkout-payment-label'},
                Object(wpElement.createElement)( component.PaymentMethodLabel, { text: paymentTitle }),
                paymentMethodIcon
            );
        },
        getWalletButtonContainer: ( buttonContainerId ) => {
            return Object(wpElement.createElement)(
                'div',
                { id: buttonContainerId }
            );
        },
        getWalletRequestData: ( wallet, buttonContainerId, paymentMethodData, cartTotal, billingCurrency ) => {
            if ( 'applepay' == wallet ) {
				var button_height = paymentMethodData.settings.apple_pay_button_height;
				var theme         = paymentMethodData.settings.apple_pay_button_theme;
				var style         = paymentMethodData.settings.apple_pay_button_type;
				var cornerRadius  = paymentMethodData.settings.apple_pay_button_corner_radius;
				var boxSizingVal  = 'border-box';
			} else {
				var button_height = paymentMethodData.settings.google_pay_button_height;
				var style         = paymentMethodData.settings.google_pay_button_type;
				var partner_id    = paymentMethodData.settings.partner_id;
				var cornerRadius  = 0;
				var boxSizingVal  = 'fill';
			}

			var shipping = ["postalAddress", "phone", "email"];
			var is_virtual        = paymentMethodData.walletSheetDetails.cart_has_virtual;
			if ( 1 == is_virtual ) {
				var shipping = ["email"];
			}
            var requestData = {
				clientKey: paymentMethodData.settings.client_key,
				paymentIntent: {
					transaction: {
						amount: String( cartTotal.value ),
						currency: String( billingCurrency.code ),
						paymentMethod: wallet.toUpperCase(),
						enforce3d: (paymentMethodData.settings.enforce_3d == "yes") ? true : false,
						environment: (paymentMethodData.settings.test_mode == "yes") ? "SANDBOX" : "PRODUCTION",
						setPendingPayment: ( paymentMethodData.walletSheetDetails.setpending == "1" || is_virtual == 1 ) ? true : false,
					},
					merchant: {
						countryCode :String( paymentMethodData.walletSheetDetails.default_country ),
						paymentDataPresent: false,
					},
					custom: {
						lang: String( paymentMethodData.walletSheetDetails.store_lang ),
					},
					order: {
						paymentDataPresent: false,
						merchantName: $( '<textarea/>' ).html( paymentMethodData.walletSheetDetails.seller_name ).text(),
						lineItems: paymentMethodData.walletSheetDetails.article_details,
						billing: {
							requiredFields: ["postalAddress", "phone", "email"]
						},
						shipping: {
							requiredFields: shipping,
							methodsUpdatedLater: true
						}
					},
					button: {
						dimensions: {
                            width:"auto",
                            cornerRadius:parseInt( cornerRadius ),
                            height: parseInt( button_height ),
                        },
						locale: String( paymentMethodData.walletSheetDetails.store_lang ),
						type: style,
						boxSizing: boxSizingVal,
					},
					callbacks: {
						onProcessCompletion: function (response, processedStatus) {
                            // Only on success, we proceed further with the booking.
							if ( "SUCCESS" == response.result.status ) {
								var response = {response : response};
								console.log( response );
								var data     = {
									'action': 'novalnet_order_creation', // your action name.
									'payment': wallet, // your action name.
									'variable_name': response, // some additional data to send.
								};
								if ( '' !== paymentMethodData.walletSheetDetails.pay_for_order_id ) {
									data.pay_for_order_id = paymentMethodData.walletSheetDetails.pay_for_order_id;
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
								'simple_product_id': paymentMethodData.walletSheetDetails.add_product, // some additional data to send.
								'variable_product_id': $( "input[name=product_id]" ).val(), // some additional data to send.
								'variable_variant_id': $( "input[name=variation_id]" ).val(), // some additional data to send.
								'source_page': buttonContainerId // some additional data to send.
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
							clickResult( {status: "SUCCESS"} );
						}
					}
				}
			};

			if ( 'googlepay' == wallet ) {
				if ( 1 == is_virtual || paymentMethodData.walletSheetDetails.pay_for_order || 1 == paymentMethodData.walletSheetDetails.cart_has_one_time_shipping ) {
					delete requestData.paymentIntent.order.shipping;
				}
				delete requestData.paymentIntent.button.dimensions.cornerRadius;
				requestData.paymentIntent.merchant.partnerId = partner_id;
			} else {
				requestData.paymentIntent.button.style = theme;
				delete requestData.paymentIntent.transaction.enforce3d;

                if ( 'applepay' == wallet && ( 1 == is_virtual || paymentMethodData.walletSheetDetails.pay_for_order ) ) {
                    delete requestData.paymentIntent.order.shipping.methodsUpdatedLater;
                }
			}
			return requestData;
        }
    };
})( jQuery, wc.wcBlocksRegistry, window.wc.wcSettings, window.wp.element, window.wp.i18n, window.wp.htmlEntities );


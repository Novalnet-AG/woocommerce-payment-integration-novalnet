!(function( blockRegistry, wcSettings, wpElement, i18n, htmlEntities, $ ){
    const paymentMethodId = 'novalnet_cc',
    paymentMethodData     = wcSettings.getPaymentMethodData( paymentMethodId );
    if ( null !== paymentMethodData ) {
        const defaultTitle = Object(i18n.__)( 'Credit / Debit Card', 'woocommerce-novalnet-gateway' ),
        paymentTitle = Object(htmlEntities.decodeEntities)((paymentMethodData.title) || "") || defaultTitle,
        paymentDescription = Object(htmlEntities.decodeEntities)((paymentMethodData.description) || ""),
        paymentIframe = ( e ) => {
            const
            { billingAddress : billingCustomer, currency : billingCurrency, cartTotal : cartTotal } = e.billing,
            { settings : paymentSettings }      = e.paymentMethodData,
            { eventRegistration, emitResponse } = e,
            { onPaymentSetup }             = eventRegistration;
            wpElement.useEffect( () => {
                var cardHashPromiseResolve, cardHashPromiseReject;
                const request_object = {
                    callback: {
                        on_success: function (data) {
                            cardHashPromiseResolve(data);
                        },
                        on_error:  function (data) {
                            cardHashPromiseResolve(data);
                        },
                    },
                    iframe: {
                        id: 'novalnet_cc_iframe',
                        inline: ( paymentSettings.enable_iniline_form == 'yes' ) ? 1 : 0,
                        style: {
                            container: paymentSettings.standard_css,
                            input: paymentSettings.standard_input,
                            label: paymentSettings.standard_label,
                        }
                    },
                    customer: {
                        first_name: billingCustomer.first_name,
                        last_name: billingCustomer.last_name,
                        email: billingCustomer.email,
                        billing: {
                            street: billingCustomer.address_1 + ' ,' + billingCustomer.address_2,
                            city: billingCustomer.city,
                            zip: billingCustomer.postcode,
                            country_code: billingCustomer.country,
                        },
                    },
                    transaction: {
                        amount: cartTotal.value,
                        currency: billingCurrency.code,
                        test_mode: ( paymentSettings.test_mode == 'yes' ) ? 1 : 0,
                        enforce_3d: ( paymentSettings.enforce_3d == 'yes' ) ? 1 : 0,
                    },
                    custom: {
                        lang : paymentMethodData.lang
                    }
                };

                NovalnetUtility.setClientKey( paymentSettings.client_key );
                NovalnetUtility.createCreditCardForm( request_object );
                const unsubscribe = onPaymentSetup( async () => {
                    NovalnetUtility.getPanHash();
                    const getCardHashPromise = new Promise(function( resolve, reject ){
                        cardHashPromiseResolve = resolve;
                        cardHashPromiseReject  = reject;
                    }).catch((data) => {
                        if( 'undefined' !== typeof data.error_message ) {
                            alert( data.error_message );
                        }
                    });
                    const cardHashResponse = await getCardHashPromise;
                    if ( 'success' ==  cardHashResponse.result ) {
                        return {
                            type: emitResponse.responseTypes.SUCCESS,
                            meta: {
                                paymentMethodData: {
                                    novalnet_cc_pan_hash: cardHashResponse.hash,
                                    novalnet_cc_unique_id: cardHashResponse.unique_id,
                                    novalnet_cc_force_redirect: cardHashResponse.do_redirect,
                                },
                            },
                        };
                    }
                    return {
                        type: emitResponse.responseTypes.ERROR,
                        message: cardHashResponse.error_message,
                    };
                });
                return () => {
                    unsubscribe();
                };
            }, [
                emitResponse.responseTypes.ERROR,
                emitResponse.responseTypes.SUCCESS,
                onPaymentSetup,
            ] );

            return Object(wpElement.createElement)(
                'div',
                null,
                Object(wpElement.createElement)(
                    'iframe',
                    {
                        id:'novalnet_cc_iframe',
                        scrolling:'no',
                        width:'100% !important',
                        frameBorder:"0",
                    }
                ),
                Object(wpElement.RawHTML)({ children: paymentDescription })
            )
        },

        paymentObject = {
            name : paymentMethodId,
            label: Object(wpElement.createElement)((e) => {
                return novalnetPaymentElement.getPaymentMethodLabel( e.components, paymentMethodData, paymentTitle );
            }, null),
            ariaLabel: paymentTitle,
            content: Object(wpElement.createElement)(
                paymentIframe,
                {className: '', paymentMethodData: paymentMethodData },
            ),
            edit: Object(wpElement.createElement)(paymentDescription, null),
            canMakePayment:()=>{
                return true;
            },
            paymentMethodId : paymentMethodId,
            supports:{
                showSavedCards: paymentMethodData.enableTokenization,
                showSaveOption: paymentMethodData.enableTokenization,
                features: paymentMethodData.supports,
            }
        };
        blockRegistry.registerPaymentMethod( paymentObject );
    }
})( wc.wcBlocksRegistry, window.wc.wcSettings, window.wp.element, window.wp.i18n, window.wp.htmlEntities, jQuery );



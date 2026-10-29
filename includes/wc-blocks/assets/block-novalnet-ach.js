!(function( blockRegistry, wcSettings, wpElement, i18n, htmlEntities ){
    const paymentMethodId = 'novalnet_ach',
    paymentMethodData = wcSettings.getPaymentMethodData( paymentMethodId );
    if ( null !== paymentMethodData ) {
        const defaultTitle = Object(i18n.__)( 'Direct Debit ACH', 'woocommerce-novalnet-gateway' ),
        paymentTitle       = Object(htmlEntities.decodeEntities)((paymentMethodData.title) || "") || defaultTitle,
        paymentDescription = Object(htmlEntities.decodeEntities)((paymentMethodData.description) || ""),
        paymentObject      = {
            name : paymentMethodId,
            label: Object(wpElement.createElement)((e) => {
                return novalnetPaymentElement.getPaymentMethodLabel( e.components, paymentMethodData, paymentTitle );
            }, null),
            ariaLabel: paymentTitle,
            content: Object(wpElement.createElement)((e)=>{
                const { billingAddress : billingCustomer, currency : billingCurrency, cartTotal : cartTotal } = e.billing;
                const { eventRegistration, emitResponse } = e;
                const { onPaymentSetup } = eventRegistration;
                wpElement.useEffect( () => {
                    const unsubscribe = onPaymentSetup( async () => {
                        const novalnet_ach_holder = document.getElementById( paymentMethodId + '_holder' ).value;
                        const novalnet_ach_account = document.getElementById( paymentMethodId + '_account' ).value;
                        const novalnet_ach_routing = document.getElementById( paymentMethodId + '_routing' ).value;
                        if ( novalnet_ach_holder.length != 0 && novalnet_ach_account.length != 0 && novalnet_ach_routing.length != 0 ) {
                            return {
                                type: emitResponse.responseTypes.SUCCESS,
                                meta: {
                                    paymentMethodData: {
                                        novalnet_ach_holder,
                                        novalnet_ach_account,
                                        novalnet_ach_routing
                                    },
                                },
                            };
                        }
                        return {
                            type: emitResponse.responseTypes.ERROR,
                            message: Object(i18n.__)( 'Your account details are invalid', 'woocommerce-novalnet-gateway' ),
                        };
                    } );
                    // Unsubscribes when this component is unmounted.
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
                    novalnetPaymentElement.getHolderField( paymentMethodId, billingCustomer ),
                    novalnetPaymentElement.getAccountField( paymentMethodId ),
                    novalnetPaymentElement.getRoutingField( paymentMethodId ),
                    Object(wpElement.RawHTML)({ children: paymentDescription })
                );
            }, null),
            edit: Object(wpElement.createElement)((e)=>{
                return Object(wpElement.RawHTML)({ children: paymentDescription });
            }, null),
            canMakePayment:(e)=>{
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
})( wc.wcBlocksRegistry, window.wc.wcSettings, window.wp.element, window.wp.i18n, window.wp.htmlEntities );
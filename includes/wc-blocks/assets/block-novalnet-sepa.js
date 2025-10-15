!(function( blockRegistry, wcSettings, wpElement, i18n, htmlEntities ){
    const paymentMethodId = 'novalnet_sepa',
    paymentMethodData = wcSettings.getPaymentMethodData( paymentMethodId );
    if ( null !== paymentMethodData ) {
        const defaultTitle = Object(i18n.__)( 'Direct Debit SEPA', 'woocommerce-novalnet-gateway' ),
        paymentTitle       = Object(htmlEntities.decodeEntities)((paymentMethodData.title) || "") || defaultTitle,
        paymentDescription = Object(htmlEntities.decodeEntities)((paymentMethodData.description) || ""),
        paymentObject      = {
            name : paymentMethodId,
            label: Object(wpElement.createElement)((e) => {
                return novalnetPaymentElement.getPaymentMethodLabel( e.components, paymentMethodData, paymentTitle );
            }, null),
            ariaLabel: paymentTitle,
            content: Object(wpElement.createElement)((e)=>{
                const { eventRegistration, emitResponse } = e;
                const { billingAddress : billingCustomer } = e.billing;
                const { onPaymentSetup } = eventRegistration;
                wpElement.useEffect( () => {
                    const unsubscribe = onPaymentSetup( async () => {
                        const novalnet_sepa_holder = document.getElementById( paymentMethodId + '_holder' ).value;
                        const novalnet_sepa_iban = document.getElementById( paymentMethodId + '_iban' ).value;
                        const novalnet_sepa_bic = document.getElementById( paymentMethodId + '_bic' ).value;
                        if ( novalnet_sepa_iban.length != 0 ) {
                            return {
                                type: emitResponse.responseTypes.SUCCESS,
                                meta: {
                                    paymentMethodData: {
                                        novalnet_sepa_holder,
                                        novalnet_sepa_iban,
                                        novalnet_sepa_bic
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
                    novalnetPaymentElement.getIBANField( paymentMethodId ),
                    novalnetPaymentElement.getBICField( paymentMethodId ),
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
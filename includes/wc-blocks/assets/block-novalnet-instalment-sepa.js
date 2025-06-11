!(function( blockRegistry, wcSettings, wpElement, i18n, htmlEntities ){
    const paymentMethodId = 'novalnet_instalment_sepa',
    paymentMethodData = wcSettings.getPaymentMethodData( paymentMethodId );
    if ( null !== paymentMethodData ) {
        const defaultTitle     = Object(i18n.__)( 'Instalment by Direct Debit SEPA', 'woocommerce-novalnet-gateway' ),
        paymentTitle           = Object(htmlEntities.decodeEntities)((paymentMethodData.title) || "") || defaultTitle,
        paymentDescription     = Object(htmlEntities.decodeEntities)((paymentMethodData.description) || ""),
        paymentObject = {
            name : paymentMethodId,
            label: Object(wpElement.createElement)((e) => {
                return novalnetPaymentElement.getPaymentMethodLabel( e.components, paymentMethodData, paymentTitle );
            }, null),
            ariaLabel: paymentTitle,
            content: Object(wpElement.createElement)((e)=>{
                const { eventRegistration, emitResponse } = e;
                const { onPaymentSetup } = eventRegistration;
                const { company: billingCompany} =  e.billing.billingAddress;
                const { cartTotal : cartTotal } = e.billing;
                const dateOfBirth = ( 0 !== billingCompany.length && 'yes' == paymentMethodData.settings.allow_b2b ) ? null : novalnetPaymentElement.getBirthField( paymentMethodId );
                wpElement.useEffect( () => {
                    const unsubscribe = onPaymentSetup( async () => {
                        const novalnet_instalment_sepa_iban = document.getElementById( paymentMethodId + '_iban' ).value;
                        const novalnet_instalment_sepa_bic  = document.getElementById( paymentMethodId + '_bic' ).value;
                        const novalnet_instalment_sepa_period  = document.getElementById( paymentMethodId + '_cycle' ).value;
                        if ( novalnet_instalment_sepa_iban.length != 0 ) {
                            const paymentMethodData = {
                                novalnet_instalment_sepa_iban,
                                novalnet_instalment_sepa_bic,
                                novalnet_instalment_sepa_period
                            };
                            if ( null !== dateOfBirth ) {
                                const novalnet_instalment_sepa_dob = ( document.getElementById( paymentMethodId + '_dob' ) ) ? document.getElementById( paymentMethodId + '_dob' ).value : 0;
                                if ( novalnet_instalment_sepa_dob.length != 0 ) {
                                    paymentMethodData[ paymentMethodId + '_dob' ] = novalnet_instalment_sepa_dob;
                                }
                            }
                            return {
                                type: emitResponse.responseTypes.SUCCESS,
                                meta: {
                                    paymentMethodData,
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
                    novalnetPaymentElement.getIBANField( paymentMethodId ),
                    novalnetPaymentElement.getBICField( paymentMethodId ),
                    dateOfBirth,
                    novalnetPaymentElement.getInstalmentField( paymentMethodId, paymentMethodData.settings.instalment_total_period, cartTotal.value ),
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

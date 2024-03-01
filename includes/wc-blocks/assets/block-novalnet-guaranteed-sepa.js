!(function( blockRegistry, wcSettings, wpElement, i18n, htmlEntities ){
    const paymentMethodId = 'novalnet_guaranteed_sepa',
    paymentMethodData = wcSettings.getPaymentMethodData( paymentMethodId );
    if ( null !== paymentMethodData ) {
        const defaultTitle       = Object(i18n.__)( 'Direct Debit SEPA', 'woocommerce-novalnet-gateway' ),
        paymentTitle       = Object(htmlEntities.decodeEntities)((paymentMethodData.title) || "") || defaultTitle,
        paymentDescription = Object(htmlEntities.decodeEntities)((paymentMethodData.description) || ""),
        paymentObject = {
            name : paymentMethodId,
            label: Object(wpElement.createElement)((e) => {
                return novalnetPaymentElement.getPaymentMethodLabel( e.components, paymentMethodData, paymentTitle );
            }, null),
            ariaLabel: paymentTitle,
            content: Object(wpElement.createElement)((e)=>{
                const { eventRegistration, emitResponse } = e;
                const { onPaymentProcessing } = eventRegistration;
                const { company: billingCompany} =  e.billing.billingAddress;
                const dateOfBirth = ( 0 !== billingCompany.length && 'yes' == paymentMethodData.settings.allow_b2b ) ? null : novalnetPaymentElement.getBirthField( paymentMethodId );
                wpElement.useEffect( () => {
                    const unsubscribe = onPaymentProcessing( async () => {
                        const novalnet_guaranteed_sepa_iban = document.getElementById( paymentMethodId + '_iban' ).value;
                        const novalnet_guaranteed_sepa_bic  = document.getElementById( paymentMethodId + '_bic' ).value;
                        if ( novalnet_guaranteed_sepa_iban.length != 0 ) {
                            const paymentMethodData = {
                                novalnet_guaranteed_sepa_iban,
                                novalnet_guaranteed_sepa_bic
                            };
                            if ( null !== dateOfBirth ) {
                                const novalnet_guaranteed_sepa_dob = ( document.getElementById( paymentMethodId + '_dob' ) ) ? document.getElementById( paymentMethodId + '_dob' ).value : 0;
                                if ( novalnet_guaranteed_sepa_dob.length != 0 ) {
                                    paymentMethodData[ paymentMethodId + '_dob' ] = novalnet_guaranteed_sepa_dob;
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
                    onPaymentProcessing,
                ] );

                return Object(wpElement.createElement)(
                    'div',
                    null,
                    novalnetPaymentElement.getIBANField( paymentMethodId ),
                    novalnetPaymentElement.getBICField( paymentMethodId ),
                    dateOfBirth,
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
                features: paymentMethodData.supports,
            }
        };
        blockRegistry.registerPaymentMethod( paymentObject );

    }
})( wc.wcBlocksRegistry, window.wc.wcSettings, window.wp.element, window.wp.i18n, window.wp.htmlEntities );

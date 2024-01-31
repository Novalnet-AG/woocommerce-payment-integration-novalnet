!(function( blockRegistry, wcSettings, wpElement, i18n, htmlEntities ){
    const paymentMethodId = 'novalnet_guaranteed_invoice',
    paymentMethodData     = wcSettings.getPaymentMethodData( paymentMethodId );
    if ( null !== paymentMethodData ) {
        const defaultTitle    = Object(i18n.__)( 'Invoice', 'woocommerce-novalnet-gateway' ),
        paymentTitle          = Object(htmlEntities.decodeEntities)((paymentMethodData.title) || "") || defaultTitle,
        paymentDescription    = Object(htmlEntities.decodeEntities)((paymentMethodData.description) || ""),
        paymentObject   = {
            name : paymentMethodId,
            label: Object(wpElement.createElement)((e) => {
                return novalnetPaymentElement.getPaymentMethodLabel( e.components, paymentMethodData, paymentTitle );
            }, null),
            ariaLabel: paymentTitle,
            content: Object(wpElement.createElement)((e)=>{
                const { company: billingCompany} =  e.billing.billingAddress;
                const { eventRegistration, emitResponse } = e;
                const { onPaymentProcessing } = eventRegistration;
                const dateOfBirth = ( billingCompany.length == 0 ) ? novalnetPaymentElement.getBirthField( paymentMethodId ) : null ;
                wpElement.useEffect( () => {
                    const unsubscribe = onPaymentProcessing( async () => {
                        if ( null !== dateOfBirth ) {
                            const novalnet_guaranteed_invoice_dob = document.getElementById( paymentMethodId + '_dob' ).value;
                            if ( novalnet_guaranteed_invoice_dob.length != 0 ) {
                                return {
                                    type: emitResponse.responseTypes.SUCCESS,
                                    meta: {
                                        paymentMethodData: {
                                            novalnet_guaranteed_invoice_dob,
                                        },
                                    },
                                };
                            }
                            return {
                                type: emitResponse.responseTypes.ERROR,
                                message: Object(i18n.__)( 'Your account details are invalid', 'woocommerce-novalnet-gateway' ),
                            };
                        }
                        return {
                            type: emitResponse.responseTypes.SUCCESS,
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
                    dateOfBirth,
                    Object(wpElement.RawHTML)({ children: paymentDescription })
                );
            }, null),
            edit: Object(wpElement.createElement)((e)=>{
                return Object(wpElement.RawHTML)({ children: paymentDescription });
            }, null),
            canMakePayment:()=>{
                return true;
            },
            paymentMethodId : paymentMethodId,
            supports:{
                features: paymentMethodData.supports,
            },
        };
        blockRegistry.registerPaymentMethod( paymentObject );
    }
})( wc.wcBlocksRegistry, window.wc.wcSettings, window.wp.element, window.wp.i18n, window.wp.htmlEntities );

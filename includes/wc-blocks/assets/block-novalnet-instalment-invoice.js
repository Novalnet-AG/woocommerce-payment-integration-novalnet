!(function( blockRegistry, wcSettings, wpElement, i18n, htmlEntities ){
    const paymentMethodId = 'novalnet_instalment_invoice',
    paymentMethodData     = wcSettings.getPaymentMethodData( paymentMethodId );
    if ( null !== paymentMethodData ) {
        const defaultTitle    = Object(i18n.__)( 'Instalment by Invoice', 'woocommerce-novalnet-gateway' ),
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
                const { onPaymentSetup } = eventRegistration;
                const { cartTotal : cartTotal } = e.billing;
                const dateOfBirth = ( 0 !== billingCompany.length && 'yes' == paymentMethodData.settings.allow_b2b ) ? null : novalnetPaymentElement.getBirthField( paymentMethodId );
                wpElement.useEffect( () => {
                    const unsubscribe = onPaymentSetup( async () => {
                        const novalnet_instalment_invoice_period  = document.getElementById( paymentMethodId + '_cycle' ).value;
                        if ( novalnet_instalment_invoice_period.length != 0 ) {
                            const paymentMethodData = {
                                novalnet_instalment_invoice_period,
                            };
                            if ( null !== dateOfBirth ) {
                                const novalnet_instalment_invoice_dob = ( document.getElementById( paymentMethodId + '_dob' ) ) ? document.getElementById( paymentMethodId + '_dob' ).value : 0;
                                if ( novalnet_instalment_invoice_dob.length != 0 ) {
                                    paymentMethodData[ paymentMethodId + '_dob' ] = novalnet_instalment_invoice_dob;
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
                    onPaymentSetup,
                ] );
                return Object(wpElement.createElement)(
                    'div',
                    null,
                    dateOfBirth,
                    novalnetPaymentElement.getInstalmentField( paymentMethodId, paymentMethodData.settings.instalment_total_period, cartTotal.value ),
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

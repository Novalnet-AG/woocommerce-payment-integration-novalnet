!(function( blockRegistry, wcSettings, wpElement, i18n, htmlEntities, $ ){
    const paymentMethodId = 'novalnet_googlepay',
    paymentMethodData     = wcSettings.getPaymentMethodData( paymentMethodId );
    if ( null !== paymentMethodData ) {
        const defaultTitle    = Object(i18n.__)( 'Google Pay', 'woocommerce-novalnet-gateway' ),
        paymentTitle          = Object(htmlEntities.decodeEntities)((paymentMethodData.title) || "") || defaultTitle,
        paymentDescription    = Object(htmlEntities.decodeEntities)((paymentMethodData.description) || ""),
        NovalnetPaymentObject = ( paymentMethodData.settings ) ? NovalnetPayment().createPaymentObject() : '',
        paymentObject         = {
            name : paymentMethodId,
            label: Object(wpElement.createElement)((e) => {
                return novalnetPaymentElement.getPaymentMethodLabel( e.components, paymentMethodData, paymentTitle );
            }, null),
            ariaLabel: paymentTitle,
            content: Object(wpElement.createElement)(( e )=>{
                const { currency : billingCurrency, cartTotal : cartTotal } = e.billing;
                const requestObject = novalnetPaymentElement.getWalletRequestData( paymentMethodData.paymentWallet, paymentMethodData.walletContainerId , paymentMethodData, cartTotal, billingCurrency );
                NovalnetPaymentObject.setPaymentIntent( requestObject );
                wpElement.useEffect( () => {
                    NovalnetPaymentObject.isPaymentMethodAvailable(
                        function(canShowWallet) {
                            if ( canShowWallet ) {
                                $( '#' + paymentMethodData.walletContainerId ).empty();
                                NovalnetPaymentObject.addPaymentButton( "#" + paymentMethodData.walletContainerId );
                            } else {
                                $( '#' + paymentMethodData.walletContainerId ).parent().hide();
                            }
                        }
                    );
                }, []);
                return novalnetPaymentElement.getWalletButtonContainer( paymentMethodData.walletContainerId );
            }, null),
            edit: Object(wpElement.createElement)(()=>{
                return Object(wpElement.RawHTML)({ children: paymentDescription });
            }, null),
            canMakePayment:()=>{
                return true;
            },
            paymentMethodId : paymentMethodId,
            supports:{
                features: paymentMethodData.supports,
            }
        };
        blockRegistry.registerExpressPaymentMethod( paymentObject );
    }
})( wc.wcBlocksRegistry, window.wc.wcSettings, window.wp.element, window.wp.i18n, window.wp.htmlEntities, jQuery );

!(function( blockRegistry, wcSettings, wpElement, i18n, htmlEntities ){
    const paymentMethodId = 'novalnet_postfinance_card',
    paymentMethodData     = wcSettings.getPaymentMethodData( paymentMethodId );
    if ( null !== paymentMethodData ) {
        defaultTitle          = Object(i18n.__)( 'Postfinance Card', 'woocommerce-novalnet-gateway' ),
        paymentTitle          = Object(htmlEntities.decodeEntities)((paymentMethodData.title) || "") || defaultTitle,
        paymentDescription    = Object(htmlEntities.decodeEntities)((paymentMethodData.description) || ""),
        paymentObject         = {
            name : paymentMethodId,
            label: Object(wpElement.createElement)((e) => {
                return novalnetPaymentElement.getPaymentMethodLabel( e.components, paymentMethodData, paymentTitle );
            }, null),
            ariaLabel: paymentTitle,
            content: Object(wpElement.createElement)(()=>{
                return Object(wpElement.RawHTML)({ children: paymentDescription });
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
        }
        blockRegistry.registerPaymentMethod( paymentObject );
    }
})( wc.wcBlocksRegistry, window.wc.wcSettings, window.wp.element, window.wp.i18n, window.wp.htmlEntities );



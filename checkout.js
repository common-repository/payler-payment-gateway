// Retrieve settings for the custom gateway using the WooCommerce settings API.
const settings = window.wc.wcSettings.getSetting( 'plpg_data', {} );

// Decode the title from HTML entities or fallback to 'Payler Gateway' if not available.
const label = window.wp.htmlEntities.decodeEntities( settings.title ) || window.wp.i18n.__( 'Payler Gateway' );

// Define the content component which decodes the description from HTML entities.
const Content = () => {
    return window.wp.htmlEntities.decodeEntities( settings.description || '' );
};

// Define the Block_Gateway object representing the payment method for WooCommerce Blocks.
const Block_Gateway = {
    name: 'payler',
    label: label,
    content: Object( window.wp.element.createElement )( Content, null ),
    edit: Object( window.wp.element.createElement )( Content, null ),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};

// Register the custom gateway as a payment method in WooCommerce Blocks.
window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );
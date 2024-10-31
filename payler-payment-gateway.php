<?php
/**
 * Plugin Name: Payler Payment Gateway
 * Description: A WooCommerce payment gateway for Payler
 * Version: 1.0.0
 * Author: Paylerlab
 * Author URI: https://payler.com/
 * License: GPL-2.0
 * Requires Plugins: woocommerce
 * Text Domain: payler-payment-gateway
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly to prevent unauthorized access
}

/**
 * Check if HPOS (High-Performance Order Storage) is enabled.
 * If HPOS is enabled, deactivate the plugin.
 */
register_activation_hook(__FILE__, 'plpg_check_woocommerce_compatibility');

function plpg_check_woocommerce_compatibility() {
    // Check if WooCommerce is installed and active
    if (!class_exists('WooCommerce')) {
        // WooCommerce is not installed or active, display error and deactivate the plugin
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('The Payler Payment Gateway plugin requires WooCommerce to be installed and active. Please install and activate WooCommerce.', 'payler-payment-gateway'),
            esc_html__('Plugin Activation Error', 'payler-payment-gateway'),
            ['back_link' => true]
        );
    }
}

/**
 * Load the custom payment gateway class when WooCommerce is initialized.
 */
add_action('plugins_loaded', 'plpg_gateway_payler', 0);

function plpg_gateway_payler(){
    // Ensure that the WC_Payment_Gateway class is available
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Include the payment gateway class file
    require_once __DIR__ . '/classes/payler-gateway.class.php';

    /**
     * Add the Payler gateway to the list of WooCommerce payment methods.
     *
     * @param array $methods List of existing payment gateways.
     * @return array Updated list of payment gateways.
     */
    function init_payler_gateway($methods)
    {
        $methods[] = 'PLPG_Gateway_Payler'; // Add the Payler gateway class
        return $methods;
    }

    // Hook the custom payment gateway into WooCommerce
    add_filter('woocommerce_payment_gateways', 'init_payler_gateway');
}

/**
 * Add a settings link to the plugin action links.
 *
 * @param array $links Existing plugin action links.
 * @return array Updated plugin action links.
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'plpg_gateway_action_links');

function plpg_gateway_action_links($links) {
    // Create a settings link for the plugin
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=payler') . '">' . esc_html__('Settings', 'payler-payment-gateway') . '</a>';
    array_unshift($links, $settings_link); // Add the settings link to the beginning of the action links array
    return $links;
}

/**
 * Custom function to declare compatibility with cart_checkout_blocks feature 
*/
function plpg_cart_checkout_blocks_compatibility() {
    // Check if the required class exists
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        // Declare compatibility for 'cart_checkout_blocks'
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}
// Hook the custom function to the 'before_woocommerce_init' action
add_action('before_woocommerce_init', 'plpg_cart_checkout_blocks_compatibility');

// Hook the custom function to the 'woocommerce_blocks_loaded' action
add_action( 'woocommerce_blocks_loaded', 'plpg_register_order_approval_payment_method_type' );

/**
 * Custom function to register a payment method type

 */
function plpg_register_order_approval_payment_method_type() {
    // Check if the required class exists
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . 'class-block.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            // Register an instance of My_Custom_Gateway_Blocks
            $payment_method_registry->register( new PLPG_Gateway_Payler_Blocks );
        }
    );
}
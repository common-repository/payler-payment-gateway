<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * PLPG_Gateway_Payler_Blocks class
 * 
 * This class integrates the Payler payment gateway with WooCommerce Blocks.
 */
final class PLPG_Gateway_Payler_Blocks extends AbstractPaymentMethodType {
    /**
     * @var PLPG_Gateway_Payler $gateway Instance of the Payler gateway class.
     */
    private $gateway;
    /**
     * @var string $name Name of the payment method.
     */
    protected $name = 'payler';

    /**
     * Initialize the payment gateway settings and instance.
     */
    public function initialize() {
        $this->settings = get_option( 'plpg_settings', [] );
        $this->gateway = new PLPG_Gateway_Payler();
    }

    /**
     * Check if the payment method is active.
     * 
     * @return bool True if the gateway is available, false otherwise.
     */
    public function is_active() {
        return $this->gateway->is_available();
    }

    /**
     * Get the handles of scripts used by the payment method.
     * 
     * This method registers and returns the script handle(s) for the Payler 
     * integration with WooCommerce Blocks.
     * 
     * @return array Array of script handles.
     */
    public function get_payment_method_script_handles() {
        $script_version = filemtime( plugin_dir_path( __FILE__ ) . 'checkout.js' );

        // Register the script for the Payler Blocks integration.
        wp_register_script(
            'plpg-blocks-integration',
            plugin_dir_url(__FILE__) . 'checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            $script_version,
            true
        );

        // Set script translations if the function exists.
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('plpg-blocks-integration');
        }

        // Return the script handle.
        return ['plpg-blocks-integration'];
    }

    /**
     * Get the payment method data.
     * 
     * This method returns the title and description of the payment method 
     * to be displayed in the checkout.
     * 
     * @return array Associative array containing the title and description.
     */
    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
        ];
    }

}
?>
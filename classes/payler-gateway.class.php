<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly to prevent unauthorized access
}

/**
 * PLPG_Gateway_Payler Class
 *
 * Handles Payler payment gateway integration with WooCommerce.
 */
class PLPG_Gateway_Payler extends WC_Payment_Gateway {
    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        // Define gateway properties
        $this->id = 'payler';
        $this->has_fields = true;
        $this->method_title = __('Payler Payment Gateway', 'payler-payment-gateway');
        $this->method_description = __('Accept payments through Payler payment gateway.', 'payler-payment-gateway');
        $this->liveurl = 'https://facade-api.main.gate-api.com/gapi/payout/v1/sessions';
        $this->testurl = 'https://facade-api.neo.gate.paylerlab.com/gapi/v1/sessions';

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

         // Add support for refunds
        $this->supports = array(
            'products',
            'refunds', // This line enables refund support
        );

        // Assign settings values to class properties
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->test_merchant_id = $this->get_option('test_merchant_id');
        $this->test_secret_key = $this->get_option('test_secret_key');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->secret_key = $this->get_option('secret_key');
        $this->test_mode = 'yes' === $this->get_option('test_mode');

        // Handle payment notifications
        add_action('woocommerce_api_' . $this->id, array($this, 'plpg_handle_payment_notification'));

        // Save admin options
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Initialize gateway settings form fields.
     */
    public function init_form_fields() {
        // Define form fields for the admin settings page
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'payler-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Payler Payment Gateway', 'payler-payment-gateway'),
                'default' => 'yes',
            ),
            'test_mode' => array(
                'title' => __('Test Mode', 'payler-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Test Mode', 'payler-payment-gateway'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Title', 'payler-payment-gateway'),
                'type' => 'text',
                'description' => __('This controls the title the user sees during checkout.', 'payler-payment-gateway'),
                'default' => __('Payler Payment', 'payler-payment-gateway'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'payler-payment-gateway'),
                'type' => 'textarea',
                'description' => __('This controls the description the user sees during checkout.', 'payler-payment-gateway'),
                'default' => __('Pay securely using your credit card with Payler.', 'payler-payment-gateway'),
            ),
            'test_merchant_id' => array(
                'title' => __('Test Authorization Key', 'payler-payment-gateway'),
                'type' => 'text',
                'description' => __('Your Test Authorization Key.', 'payler-payment-gateway'),
                'default' => '',
            ),
            'test_secret_key' => array(
                'title' => __('Test Authorization Password', 'payler-payment-gateway'),
                'type' => 'password',
                'description' => __('Your Test Authorization Password.', 'payler-payment-gateway'),
                'default' => '',
            ),
            'merchant_id' => array(
                'title' => __('Live Authorization Key', 'payler-payment-gateway'),
                'type' => 'text',
                'description' => __('Your Live Authorization Key.', 'payler-payment-gateway'),
                'default' => '',
            ),
            'secret_key' => array(
                'title' => __('Live Authorization Password', 'payler-payment-gateway'),
                'type' => 'password',
                'description' => __('Your Live Authorization Password.', 'payler-payment-gateway'),
                'default' => '',
            ),
        );
    }

    /**
     * Process payment and return the result.
     *
     * @param int $order_id The ID of the WooCommerce order.
     * @return array Payment result including the redirect URL.
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id); // Get the order object

        // Payler API integration
        $payment_url = $this->create_payler_payment($order);

        // Return success and redirect to the payment page
        return [
            'result'   => 'success',
            'redirect' => $payment_url,
        ];
    }

    /**
     * Create a Payler payment session and return the payment URL.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return string|WP_Error The payment URL or WP_Error on failure.
     */
    private function create_payler_payment($order) {
        $order_id = $order->get_id();
        $customer_id = $order->get_user_id();
        $hash_order_id = $this->generateOrderId($order_id);
        $hash_customer_id = $this->generateOrderId($customer_id);

        // Save hashed order and customer IDs in order meta for reference
        $this->update_order_meta($order, '_hash_order_id', $hash_order_id);
        $this->update_order_meta($order, '_hash_customer_id', $hash_customer_id);

        // Prepare the payload for the Payler API request
        $payload = [
            'session' => [
                'lifetime' => '200000',
                'returnURLs' => [
                    'default' => ['url' => '/'],
                    'success' => ['url' => $this->get_success_url($order)],
                    'failure' => ['url' => $this->get_fail_url($order)],
                ],
                'notificationURL' => $this->get_notification_url(),
                'payment' => [
                    'type' => 'regular',
                    'page' => [
                        'template' => 'test',
                        'lang' => 'en',
                        'showSavedCards' => false,
                    ],
                    'isTwoPhase' => false,
                ],
                'order' => [
                    'id' => $hash_order_id, 
                    'currency' => 'USD',
                    'amount' => intval($order->get_total() * 100), // This amount should be in cents
                    'customer' => [
                        'id' => $hash_customer_id,
                        'email' => $order->get_billing_email(),
                        'personsData' => [
                            'phoneNumber' => $order->get_billing_phone(),
                            'firstName' => $order->get_billing_first_name(),
                            'lastName' => $order->get_billing_last_name(),
                            'country' => $order->get_billing_country(),
                            'state' => $order->get_billing_state(),
                            'city' => $order->get_billing_city(),
                            'zip' => $order->get_billing_postcode(),
                            'address' => $order->get_billing_address_1(),
                        ],
                    ],
                    'additionalFields' => [
                        'custom' => [
                            'orignal_order_id' => strval($order_id),
                            'orignal_customer_id' => strval($customer_id)
                        ],
                    ],
                ],
            ],
        ];

        // Setup request arguments for the API call
        $args = [
            'body'    => wp_json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json',
                'SessionTerminalKey' => $this->get_merchant_id(),
                'SessionTerminalPassword' => $this->get_secret_key(),
            ],
            'timeout' => 45,
            'data_format' => 'body',
        ];

        // Perform the HTTP POST request
        $response = wp_remote_post($this->get_payler_endpoint(), $args);

        if (is_wp_error($response)) {
            return new WP_Error('payler_error', 'Failed to create Payler session');
        }

        $response_data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($response_data['session']['error'])) {
            return new WP_Error('payler_error', 'Payler API Error: ' . $response_data['session']['error']['title']);
        }

        $redirect_url = $response_data['session']['payment']['page']['url'];
        $session_id = sanitize_text_field($response_data['session']['id']);
        update_post_meta($order_id, 'payler_session_order_id', $session_id);
        
        return $redirect_url;
    }

    /**
     * Get the success URL for the order.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return string The success URL.
     */
    private function get_success_url($order) {
        return $order->get_checkout_order_received_url();
    }

    /**
     * Get the failure URL for the order.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return string The failure URL.
     */
    private function get_fail_url($order) {
        return wc_get_checkout_url();
    }

    /**
     * Get the notification URL for Payler payment notifications.
     *
     * @return string The notification URL.
     */
    private function get_notification_url() {
        return WC()->api_request_url($this->id);
    }

    /**
     * Get the appropriate Payler API endpoint based on test mode.
     *
     * @return string The API endpoint URL.
     */
    private function get_payler_endpoint(){
        return $this->test_mode == 'yes' ? $this->testurl : $this->liveurl;
    }

    /**
     * Get the merchant ID based on the test mode setting.
     *
     * Returns the test merchant ID if test mode is enabled,
     * otherwise returns the live merchant ID.
     *
     * @return string The appropriate merchant ID for the current mode.
     */
    private function get_merchant_id(){
        return $this->test_mode == 'yes' ? $this->test_merchant_id : $this->merchant_id;
    }

    /**
     * Get the secret key based on the test mode setting.
     *
     * Returns the test secret key if test mode is enabled,
     * otherwise returns the live secret key.
     *
     * @return string The appropriate secret key for the current mode.
     */
    private function get_secret_key(){
        return $this->test_mode == 'yes' ? $this->test_secret_key : $this->secret_key;
    }

    /**
     * Generate a unique order ID in the format of a UUID.
     *
     * This function generates a random 128-bit number and formats it as a UUID,
     * which can be used as a unique identifier for orders.
     *
     * @return string The generated UUID.
     */
    public function generateOrderId() {
        $bytes = random_bytes(16);
        
        // Convert the bytes to a hexadecimal string
        $hex = bin2hex($bytes);

        // Format the hexadecimal string as a UUID
        $uuid = vsprintf('%s-%s-%s-%s-%s', [
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        ]);

        return $uuid;
    }
    
    /**
     * Handle Payler payment notifications.
     *
     * This method processes the incoming payment notification from Payler.
     */
    public function plpg_handle_payment_notification() {
        $raw_post_data = file_get_contents('php://input');
        
        $notification_data = json_decode($raw_post_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            exit('Invalid JSON format');
        }

        $orignal_order_id = $this->get_woocommerce_order_ID($notification_data['orderId']);

        if (empty($orignal_order_id)) {
            http_response_code(400);
            exit('Order ID not found');
        }

        if (isset($notification_data['orderId']) && isset($notification_data['state'])) {
            $order = wc_get_order($orignal_order_id);
            $order_url = $order->get_checkout_order_received_url();

            if ($order) {
                
                switch ($notification_data['state']) {
                    case 'authorized':
                        $order->payment_complete();
                        $order->add_order_note(__('Payment completed via Payler.', 'payler-payment-gateway'));
                        wp_redirect($order_url);
                        exit;
                    case 'cancelled':
                        $order->update_status('failed', __('Payment cancelled via Payler.', 'payler-payment-gateway'));
                        wp_redirect($order->get_cancel_order_url());
                        exit;
                    case 'refunded':
                        $order->update_status('refunded', __('Payment refunded via Payler.', 'payler-payment-gateway'));
                        wp_redirect($order->get_cancel_order_url());
                        exit;
                    default:
                        $order->update_status('failed', __('Payment cancelled via Payler.', 'payler-payment-gateway'));
                        wp_redirect($order->get_cancel_order_url());
                        exit;
                }

            } else {
                $order->update_status('failed', __('Payment cancelled via Payler.', 'payler-payment-gateway'));
                wp_redirect($order->get_cancel_order_url());
                exit;
            }
        } else {
            $order->update_status('failed', __('Payment cancelled via Payler.', 'payler-payment-gateway'));
            wp_redirect($order->get_cancel_order_url());
            exit;
        }
    }

    /**
     * Retrieve the WooCommerce order ID based on the order hash ID stored in post meta.
     *
     * This function queries the WordPress database to find the original order ID
     * associated with a given order hash ID.
     *
     * @param string $order_hash_id The hashed order ID.
     * @return string The WooCommerce order ID if found, otherwise an empty string.
     */
    public function get_woocommerce_order_ID($order_hash_id){
        $order_hash_id = sanitize_text_field($order_hash_id);
        if ($this->is_hpos_enabled()) {
            // HPOS is enabled - Use WooCommerce's wc_get_orders to find the order
            $args = array(
                'limit' => 1,
                'meta_key' => '_hash_order_id',
                'meta_value' => $order_hash_id,
                'return' => 'ids',
            );
            $orders = wc_get_orders($args);
            return !empty($orders) ? $orders[0] : '';
        } else {
            // HPOS is disabled - Use legacy query method
            global $wpdb;
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_hash_order_id' AND meta_value = %s",
                $order_hash_id
            ));
            return $result ? $result->post_id : '';
        }
    }

    /**
     * Process a refund request.
     *
     * @param int $order_id The ID of the WooCommerce order.
     * @param float $amount The amount to refund.
     * @param string $reason The reason for the refund.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('invalid_order', __('Invalid order ID.', 'payler-payment-gateway'));
        }

        $order_total = intval($order->get_total() * 100); // Amount in cents
        $refund_amount = intval($amount * 100); // Refund amount in cents
        
        if ($refund_amount > $order_total) {
            return new WP_Error('invalid_amount', __('Refund amount cannot exceed order total.', 'payler-payment-gateway'));
        }

        $order_session_id = $this->get_order_meta($order, 'payler_session_order_id');

        // Prepare the payload for the Payler API refund request
        $payload = [
            'session' => [
                'refund' => [
                    'amount' => $refund_amount,
                ],
            ],
        ];

        $refund_url = $this->get_payler_endpoint() . '/' . $order_session_id . '/refund';

        // Set up the arguments for the HTTP request
        $args = [
            'body'        => wp_json_encode($payload),
            'headers'     => [
                'Content-Type'           => 'application/json',
                'Cache-Control'          => 'no-cache',
                'SessionTerminalKey'     => $this->get_merchant_id(),
                'SessionTerminalPassword' => $this->get_secret_key(),
            ],
            'timeout'     => 15, // Adjust the timeout as necessary
            'data_format' => 'body',
        ];

        // Send the request using wp_remote_post
        $response = wp_remote_post($refund_url, $args);

        if (!$response) {
            return new WP_Error('payler_error', __('Failed to process refund via Payler.', 'payler-payment-gateway'));
        }

        $response_data = json_decode($response, true);
        if (isset($response_data['refund']['status']) && strtolower($response_data['refund']['status']) == 'error') {
            return new WP_Error(
                'payler_error',
                // translators: %s is the error title returned by the Payler API for the refund process
                sprintf(__('Payler API Error: %s', 'payler-payment-gateway'), $response_data['refund']['error']['title'])
            );
        }


        // Refund was successful, add order note
        if (isset($response_data['refund']['status']) && strtolower($response_data['refund']['status']) == 'Refunded') {
            
            $order->add_order_note(
                // translators: 1 is the refunded amount, 2 is the refund reason.
                sprintf(__('Refunded %1$s via Payler. Reason: %2$s', 'payler-payment-gateway'), wc_price($amount), $reason)
            );
        }

        return true;
    }

    /**
     * Update order metadata.
     *
     * This function updates the metadata for a WooCommerce order.
     * It supports both the High-Performance Order Storage (HPOS) system
     * and the legacy post meta system, ensuring compatibility across different WooCommerce environments.
     *
     * @param WC_Order $order    The order object.
     * @param string   $meta_key The metadata key.
     * @param mixed    $meta_value The value to update for the given metadata key.
     */
    private function update_order_meta($order, $meta_key, $meta_value) {
        $meta_value = sanitize_text_field($meta_value);
        if ($this->is_hpos_enabled()) {
            // HPOS is enabled
            $order->update_meta_data($meta_key, $meta_value);
            $order->save(); // Always save after updating meta data in HPOS
        } else {
            // HPOS is disabled (legacy post meta handling)
            update_post_meta($order->get_id(), $meta_key, $meta_value);
        }
    }

    /**
     * Retrieve order metadata.
     *
     * This function retrieves the metadata for a WooCommerce order.
     * It supports both the HPOS system and the legacy post meta system,
     * ensuring compatibility across different WooCommerce environments.
     *
     * @param WC_Order $order    The order object.
     * @param string   $meta_key The metadata key to retrieve.
     * @return mixed The metadata value.
     */
    private function get_order_meta($order, $meta_key) {
        if ($this->is_hpos_enabled()) {
            // HPOS is enabled
            return $order->get_meta($meta_key);
        } else {
            // HPOS is disabled (legacy post meta handling)
            return get_post_meta($order->get_id(), $meta_key, true);
        }
    }

    /**
     * Check if HPOS is enabled.
     *
     * This helper function checks whether High-Performance Order Storage (HPOS) is enabled in WooCommerce.
     * It verifies this by checking if the WooCommerce function `wc_is_order_meta_lookup_enabled()` exists and returns true.
     *
     * @return bool True if HPOS is enabled, false otherwise.
     */
    private function is_hpos_enabled() {
        // Check if the function exists and HPOS is enabled
        return function_exists('wc_is_order_meta_lookup_enabled') && wc_is_order_meta_lookup_enabled();
    }

}
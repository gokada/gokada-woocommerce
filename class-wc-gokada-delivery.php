<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Main Gokada Delivery Class.
 *
 * @class    WC_Gokada_Delivery
 * @version  1.3.2
 */
class WC_Gokada_Delivery
{
    /** @var string version number */
    const VERSION = '1.3.2';

    /** @var \WC_Gokada_Delivery_API api for this plugin */
    public $api;

    /** @var array settings value for this plugin */
    public $settings;

    /** @var array order status value for this plugin */
    public $statuses;

    /** @var \WC_Gokada_Delivery single instance of this plugin */
    protected static $instance;

    /**
     * Loads functionality/admin classes and add auto schedule order hook.
     *
     * @since 1.0
     */
    public function __construct()
    {
        // get settings
        $this->settings = maybe_unserialize(get_option('woocommerce_Gokada_delivery_settings'));

        $this->statuses = [
            'estimate'                  => 'ESTIMATE',
            'pending-driver'            => 'PENDING PILOT',
            'driver-assigned'           => 'PILOT PICKUP ARRIVED',
            'driver-pickup-complete'    => 'PILOT PICKUP COMPLETE',
            'driver-dropoff-complete'   => 'PILOT DROPOFF COMPLETE',
            'driver-delivery-problem'   => 'PILOT DELIVERY PROBLEM',
            'no-drivers-found'          => 'NO PILOT FOUND',
        ];

        $this->init_plugin();

        $this->init_hooks();
    }

    /**
     * Initializes the plugin.
     *
     * @internal
     *
     * @since 2.4.0
     */
    public function init_plugin()
    {
        $this->includes();

        if (is_admin()) {
            $this->admin_includes();
        }

        // if ( is_ajax() ) {
        // 	$this->ajax_includes();
        // } elseif ( is_admin() ) {
        // 	$this->admin_includes();
        // }
    }

    /**
     * Includes the necessary files.
     *
     * @since 1.0.0
     */
    public function includes()
    {
        $plugin_path = $this->get_plugin_path();

        require_once $plugin_path . 'includes/class-wc-gok-api.php';

        require_once $plugin_path . 'includes/class-wc-gok-shipping-method.php';
    }

    public function admin_includes()
    {
        $plugin_path = $this->get_plugin_path();

        require_once $plugin_path . 'includes/class-wc-gok-orders.php';
    }

    /**
     * Initialize hooks.
     *
     * @since 1.0.0
     */
    public function init_hooks()
    {
        /**
         * Actions
         */
        $shipping_is_scheduled_on = $this->settings['shipping_is_scheduled_on'];
        if ($shipping_is_scheduled_on == 'payment_submit') {
            add_action('woocommerce_payment_complete', array($this, 'create_order_shipping_task'));
        } else if ($shipping_is_scheduled_on == 'order_submit') {
            add_action('woocommerce_order_status_completed', array($this, 'create_order_shipping_task'));
        }

        add_action('woocommerce_shipping_init', array($this, 'load_shipping_method'));

        // cancel a Gokada delivery task when an order is cancelled in WC
        add_action('woocommerce_order_status_cancelled', array($this, 'cancel_order_shipping_task'));

        // adds tracking button(s) to the View Order page
        add_action('woocommerce_order_details_after_order_table', array($this, 'add_view_order_tracking'));

        /**
         * Filters
         */
        // Add shipping icon to the shipping label
        add_filter('woocommerce_cart_shipping_method_full_label', array($this, 'add_shipping_icon'), PHP_INT_MAX, 2);

        add_filter('woocommerce_checkout_fields', array($this, 'edit_checkout_fields'));

        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));

        add_filter('woocommerce_shipping_calculator_enable_city', '__return_true');

        add_filter('woocommerce_shipping_calculator_enable_postcode', '__return_false');
  
        // Update delivery fee on checkout page when address changes
        add_action('wp_print_footer_scripts', array($this, 'update_woocommerce_delivery_fee_on_change'));

        // Add autocomplete script to frontend and admin pages
        add_action('wp_enqueue_scripts', array($this, 'script_load'));
        
        add_action('admin_enqueue_scripts', array($this, 'admin_script_load'));

        // AJAX action for autocomplete
        add_action('wp_ajax_nopriv_autocomplete', array($this, 'get_autocomplete_results'));

        add_action('wp_ajax_autocomplete', array($this, 'get_autocomplete_results'));
    }

    /**
     * shipping_icon.
     *
     * @version 2.0.0
     * @since   1.0.0
     */
    function add_shipping_icon($label, $method)
    {
        if ($method->method_id == 'gokada_delivery') {
            $plugin_path = WC_GOKADA_DELIVERY_MAIN_FILE;
            $logo_title = 'Gokada Delivery';
            $icon_url = plugins_url('assets/images/gokada.png', $plugin_path);
            $img = '<img class="Gokada-delivery-logo"' .
                ' alt="' . $logo_title . '"' .
                ' title="' . $logo_title . '"' .
                ' style="width:20px; height:20px; display:inline;"' .
                ' src="' . $icon_url . '"' .
                '>';
            $label = $img . ' ' . $label;
        }

        return $label;
    }

    public function create_order_shipping_task($order_id)
    {
        if ($this->settings['mode'] == 'test' && !strpos($this->settings['test_api_key'], 'test')) {
			wc_add_notice('Gokada Error: Production API Key used in Test mode', 'error');
			return;
        }
        
        $order = wc_get_order($order_id);
        // $order_status    = $order->get_status();
        $shipping_method = @array_shift($order->get_shipping_methods());

        if (strpos($shipping_method->get_method_id(), 'gokada_delivery') !== false) {

            $receiver_name      = $order->get_shipping_first_name() . " " . $order->get_shipping_last_name();
            $receiver_email     = $order->get_billing_email();
            $receiver_phone     = $this->normalize_number($order->get_billing_phone());
            $delivery_base_address  = $order->get_shipping_address_1();
            $delivery_city      = $order->get_shipping_city();
            $delivery_state_code    = $order->get_shipping_state();
            $delivery_country_code  = $order->get_shipping_country();;
            $delivery_state = WC()->countries->get_states($delivery_country_code)[$delivery_state_code];
            $delivery_country = WC()->countries->get_countries()[$delivery_country_code];

            $sender_name         = $this->settings['sender_name'];
            $sender_phone        = $this->normalize_number($this->settings['sender_phone_number']);
            $sender_email        = $this->settings['sender_email'];
            $pickup_base_address = $this->settings['pickup_base_address'];
            $pickup_state        = $this->settings['pickup_state'];
            $pickup_country      = $this->settings['pickup_country'];
            $pickup_coordinates   = $this->settings['pickup_coordinates'];
            if (trim($pickup_country) == '') {
                $pickup_country = 'NG';
            }

            $pickup_delay = $this->settings['pickup_delay_same'];
            $pickup_date = date('Y-m-d H:i:s');
            $pickup_datetime = null;

            if ($pickup_delay < 0) {
                $pickup_delay = 0;
            }
            if ($pickup_delay >= 1 && $this->settings['shipping_is_scheduled_on'] == 'order_submit') {
                $pickup_datetime = date('Y-m-d H:i:s', date(strtotime("+" . $pickup_delay . " hour", strtotime($pickup_date))));
            }

            else if ($this->settings['scheduled_submit'] && $this->settings['pickup_schedule_time']) {
                $scheduled_time = $this->settings['pickup_schedule_time'];
                $present_time = date('H:i');
                if ($present_time < $scheduled_time) {
                    $pickup_datetime = date('Y-m-d '.$scheduled_time);
                } else if ($present_time > $scheduled_time) {
                    $pickup_datetime = date('Y-m-d '.$scheduled_time, strtotime('+24 hours'));
                }
            }

            // $delivery_date = date('Y-m-d H:i:s', date(strtotime('+ 4 hour', strtotime($pickup_date))));

            $api = $this->get_api();
            
            $key = $this->settings['mode'] == 'test' ? $this->settings['test_api_key'] : $this->settings['live_api_key'];

            $params = array(
                'api_key'                 => $key,
                'pickup_address'          => $pickup_base_address,
                'delivery_address'        => $delivery_base_address,
                'pickup_name'             => $sender_name,
                'pickup_phone'            => $sender_phone,
                'pickup_email'            => $sender_email,
                'delivery_name'           => $receiver_name,
                'delivery_phone'          => $receiver_phone,
                'delivery_email'          => $receiver_email,
                'pickup_datetime'         => $pickup_datetime
            );

            $res = $api->create_task($params);

            if ($res['order_id']) {
                $status = $api->get_order_details(
                    array(
                        'api_key'    => $key,
                        'order_id'   =>  $res['order_id']
                    )
                );

                $order->add_order_note("Gokada Delivery: Successfully created order");

                update_post_meta($order_id, 'gokada_delivery_failed', false);
                update_post_meta($order_id, 'gokada_delivery_order_id', $res['order_id']);
                update_post_meta($order_id, 'gokada_delivery_pickup_tracking_url', $status['pickup_tracking_link']);
                update_post_meta($order_id, 'gokada_order_status', $this->statuses[$status['status']]); // UNASSIGNED
                update_post_meta($order_id, 'gokada_delivery_delivery_tracking_url', $status['dropoff_tracking_links'][0]);
                update_post_meta($order_id, 'gokada_delivery_order_response', $res);
                $note = sprintf(__('Shipment scheduled via Gokada delivery (Order Id: %s)'), $res['order_id']);
                $order->add_order_note($note);
            }
            else {
                update_post_meta($order_id, 'gokada_delivery_failed', true);
                if ($res['message']) {
                    $order->add_order_note("Gokada Delivery Error:". $res['message']);
                }
                else {
                    $message = "A Fatal error occured while processing order. Please Contact Gokada Support";
                    $order->add_order_note("Gokada Delivery Error:". $message);
                }
            }   
        }
    }

    /**
     * Cancels an order in Gokada Delivery when it is cancelled in WooCommerce.
     *
     * @since 1.0.0
     *
     * @param int $order_id
     */
    public function cancel_order_shipping_task($order_id)
    {
        if ($this->settings['mode'] == 'test' && !strpos($this->settings['test_api_key'], 'test')) {
			wc_add_notice('Gokada Error: Production API Key used in Test mode', 'error');
			return;
        }

        $key = $this->settings['mode'] == 'test' ? $this->settings['test_api_key'] : $this->settings['live_api_key'];
        $order = wc_get_order($order_id);
        $gokada_order_id = $order->get_meta('gokada_delivery_order_id');

        if ($gokada_order_id) {

            try {
                $res = $this->get_api()->cancel_task(array(
                    'api_key'    => $key,
                    'order_id'   =>  $gokada_order_id
                ));

                $order->update_status('cancelled');
                update_post_meta($order_id, 'gokada_order_status', 'CANCELLED');

                $order->add_order_note(__('Order has been cancelled in Gokada Delivery.'));
            } catch (Exception $exception) {

                $order->add_order_note(sprintf(
                    /* translators: Placeholder: %s - error message */
                    esc_html__('Unable to cancel order in Gokada Delivery: %s'),
                    $exception->getMessage()
                ));
            }
        }
    }

    /**
     * Update an order status by fetching the order details from Gokada Delivery.
     *
     * @since 1.0.0
     *
     * @param int $order_id
     */
    public function update_order_shipping_status($order_id)
    {
        if ($this->settings['mode'] == 'test' && !strpos($this->settings['test_api_key'], 'test')) {
			wc_add_notice('Gokada Error: Production API Key used in Test mode', 'error');
			return;
        }
        
        $order = wc_get_order($order_id);
        $key = $this->settings['mode'] == 'test' ? $this->settings['test_api_key'] : $this->settings['live_api_key'];

        $gokada_order_id = $order->get_meta('gokada_delivery_order_id');
        if ($gokada_order_id) {
            $res = $this->get_api()->get_order_details( array(
                'api_key'    => $key,
                'order_id'   =>  $gokada_order_id
            ));

            $order_status = $this->statuses[$res['status']];

            update_post_meta($order_id, 'gokada_order_status', $order_status);

            if ($order_status !== 'ESTIMATE' && $order_status !== 'PENDING PILOT' && $order_status !== 'PILOT DROPOFF COMPLETE') {
                $order->add_order_note("Gokada Delivery: $order_status");
            } elseif ($order_status == 'PILOT DROPOFF COMPLETE') {
                $order->update_status('completed', 'Gokada Delivery: Order completed successfully');
            }
                
            // update_post_meta($order_id, 'gokada_delivery_order_details_response', $res);
        }
    }

    /**
     * Adds the tracking information to the View Order page.
     *
     * @internal
     *
     * @since 2.0.0
     *
     * @param int|\WC_Order $order the order object
     */
    public function add_view_order_tracking($order)
    {
        if ($this->settings['enabled'] == 'no') {
            return;
        }
        $order = wc_get_order($order);

        $pickup_tracking_url = $order->get_meta('gokada_delivery_pickup_tracking_url');
        $delivery_tracking_url = $order->get_meta('gokada_delivery_delivery_tracking_url');

        if ($pickup_tracking_url) {
            ?>
                <p class="wc-gokada-delivery-track-pickup">
                    <a href="<?php echo esc_url($pickup_tracking_url); ?>" class="button" target="_blank">Track Gokada Pickup</a>
                </p>

            <?php
        }
        if ($delivery_tracking_url) {
            ?>
                <p class="wc-gokada-delivery-track-delivery">
                    <a href="<?php echo esc_url($delivery_tracking_url); ?>" class="button" target="_blank">Track Gokada Delivery</a>
                </p>
            <?php
        }
        if (!$pickup_tracking_url) {
            ?>
                 <p>Please Check Back for Gokada Delivery Tracking Information</p>
            <?php
        }
    }

    public function edit_checkout_fields($fields)
    {
        $fields['billing']['billing_city']['required'] = false;
        $fields['billing']['billing_city']['type'] = 'hidden';
        $fields['billing']['billing_city']['label'] = '';

        $fields['billing']['billing_address_2']['type'] = 'hidden';
        $fields['shipping']['shipping_address_2']['type'] = 'hidden';

        return $fields;
    }

    /**
     * Load Shipping method.
     *
     * Load the WooCommerce shipping method class.
     *
     * @since 1.0.0
     */
    public function load_shipping_method()
    {
        $this->shipping_method = new WC_Gokada_Delivery_Shipping_Method;
    }

    /**
     * Add shipping method.
     *
     * Add shipping method to the list of available shipping method..
     *
     * @since 1.0.0
     */
    public function add_shipping_method($methods)
    {
        if (class_exists('WC_Gokada_Delivery_Shipping_Method')) :
            $methods['gokada_delivery'] = 'WC_Gokada_Delivery_Shipping_Method';
        endif;

        return $methods;
    }

    /**
     * Initializes the and returns Gokada Delivery API object.
     *
     * @since 1.0
     *
     * @return \WC_Gokada_Delivery_API instance
     */
    public function get_api()
    {
        // return API object if already instantiated
        if (is_object($this->api)) {
            return $this->api;
        }

        $gokada_delivery_settings = $this->settings;

        // instantiate API
        return $this->api = new \WC_Gokada_Delivery_API($gokada_delivery_settings);
    }

    public function get_plugin_path()
    {
        return plugin_dir_path(__FILE__);
    }

    /**
     * Returns the main Gokada Delivery Instance.
     *
     * Ensures only one instance is/can be loaded.
     *
     * @since 1.0.0
     *
     * @return \WC_Gokada_Delivery
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Refresh Woocommerce Checkout on update of shipping totals
     *
     * @internal
     *
     * @since 2.0.0
     *
     * @param int|\number sender/receiver phone number
     */
    public function update_woocommerce_delivery_fee_on_change(){
        if ( function_exists('is_checkout') && is_checkout() ) {
            ?>
            <script>
                window.addEventListener('load', function(){
                    var el = document.getElementById("billing_address_1_field");
                    el.className += ' update_totals_on_change';
                });
            </script>
            <?php 
        }
    }

    /**
     * Normalizes phone number to required format by endpoint.
     *
     * @internal
     *
     * @since 2.0.0
     *
     * @param int|\number sender/receiver phone number
     */
    public static function normalize_number($number) {
        if (empty($number)) {
            return;
        }

        $phone_number_build = "";
        $phone_number_raw = str_replace([' ','-','(',')'], [''], $number);
        
        if(substr($phone_number_raw, 0, 5) == '+2340') {
            $phone_number_raw = substr($phone_number_raw, 5);
        } else if(substr($phone_number_raw, 0, 4) == '2340') {
            $phone_number_raw = substr($phone_number_raw, 4);
        } else if($phone_number_raw[0] == '0') {
            $phone_number_raw = substr($phone_number_raw, 1);
        }

        // check : +234
        $phone_cc_check = substr($phone_number_raw, 0, 4);
        if($phone_cc_check == '+234') {
            $phone_number_build = $phone_number_raw;
        }

        // check : 234
        $phone_cc_check = substr($phone_number_raw, 0, 3);
        if($phone_cc_check == '234') {
            $phone_number_build = '+' . $phone_number_raw;
        }

        if($phone_number_build == "") {
            $phone_number_raw = str_replace(array('+1','+'), '', $phone_number_raw);
            $phone_number_build = "+234" . $phone_number_raw;
        }
        
        return $phone_number_build;
    }

    public function script_load($where) {
        wp_enqueue_style('gokada-woocommerce', plugin_dir_url(__FILE__ ) . '/assets/css/gokada-woocommerce.css');
        wp_enqueue_script('gokada-woocommerce', plugin_dir_url( __FILE__ ) . '/assets/js/gokada-woocommerce.js', array( 'jquery' ));
        wp_localize_script('gokada-woocommerce', 'obj', $this->script_data());
    }
    
    public function admin_script_load($where){
        if ($where != 'woocommerce_page_wc-settings') {
            return;
        }

        wp_enqueue_style('gokada-woocommerce', plugin_dir_url(__FILE__ ) . '/assets/css/gokada-woocommerce.css');
        wp_enqueue_script('gokada-woocommerce', plugin_dir_url( __FILE__ ) . '/assets/js/gokada-woocommerce-admin.js', array( 'jquery' ));
        wp_localize_script('gokada-woocommerce', 'obj', $this->script_data());
    }

    public function get_autocomplete_results() {
        $query = sanitize_text_field($_POST['query']);
        $url = 'https://api.gokada.ng/api/v1/promo/autocomplete?q=' . urlencode($query) . '&context=pickup&lat=0&lng=0&session=' . date('ymdHis');

        $res = wp_remote_request($url);

        if (is_wp_error($res)) {
            throw new \Exception(__('You had an HTTP error connecting to Gokada delivery'));
        } else {
            $body = wp_remote_retrieve_body($res);
            
            if (null !== ($json = json_decode($body, true))) {
                wp_send_json_success($json);
            } else // Un-decipherable message
                throw new Exception(__('There was an issue connecting to Gokada delivery. Try again later.'));
        }

        return false;
    }

    public function script_data() {
        $data = array(
            'ajax_url' => admin_url('admin-ajax.php')
        );

        return $data;
    }
}


/**
 * Returns the Gokada Delivery instance.
 *
 * @since 1.0.0
 *
 * @return \WC_Gokada_Delivery
 */
function wc_gokada_delivery()
{
    return \WC_Gokada_Delivery::instance();
}

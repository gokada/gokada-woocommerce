<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Main Kwik Delivery Class.
 *
 * @class       WC_Kwik_Delivery
 * @version     1.0.0
 */
class WC_Kwik_Delivery
{
    /** @var string version number */
    const VERSION = '1.0.0';

    /** @var \WC_Kwik_Delivery single instance of this plugin */
    protected static $instance;

    /**
     * Loads functionality/admin classes and add auto order export hooks.
     *
     * @since 1.0
     */
    public function __construct()
    {
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

        // if ( is_ajax() ) {
        // 	$this->ajax_includes();
        // } elseif ( is_admin() ) {
        // 	$this->admin_includes();
        // }
    }

    /**
     * Initialize hooks.
     *
     * @since 1.0.0
     */
    public function init_hooks()
    {
        // Initialize shipping method class
        add_action('woocommerce_shipping_init', array($this, 'load_shipping_method'));

        // Add shipping method
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));

        add_filter('woocommerce_shipping_calculator_enable_address', '__return_true');

        add_filter('woocommerce_shipping_calculator_enable_city', '__return_true');

        add_filter('woocommerce_shipping_calculator_enable_postcode', '__return_false');
    }

    /**
     * Includes the necessary files.
     *
     * @since 1.0.0
     */
    public function includes()
    {
        $plugin_path = $this->get_plugin_path();

        require_once $plugin_path . 'includes/class-wc-kd-api.php';;

        require_once $plugin_path . 'includes/class-wc-kd-shipping-method.php';
    }

    /**
     * Initializes the and returns Kwik Delivery API object.
     *
     * @since 1.0
     *
     * @return \WC_Kwik_Delivery_API instance
     */
    public function get_api()
    {
        // return API object if already instantiated
        if (is_object($this->api)) {
            return $this->api;
        }

        // get settings
        $kwik_delivery_settings = maybe_unserialize( get_option('woocommerce_kwik_delivery_settings') );

        // instantiate API
        return $this->api = new \WC_Kwik_Delivery_API($kwik_delivery_settings);
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
        $this->shipping_method = new WC_Kwik_Delivery_Shipping_Method;
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
        if (class_exists('WC_Kwik_Delivery_Shipping_Method')) :
            $methods['kwik_delivery'] = 'WC_Kwik_Delivery_Shipping_Method';
        endif;

        return $methods;
    }

    public function get_plugin_path()
    {
        return plugin_dir_path(__FILE__);
    }

    /**
     * Returns the main Kwik Delivery Instance.
     *
     * Ensures only one instance is/can be loaded.
     *
     * @since 1.0.0
     *
     * @return \WC_Kwik_Delivery
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}


/**
 * Returns the One True Instance of WooCommerce KwikDelivery.
 *
 * @since 1.0.0
 *
 * @return \WC_Kwik_Delivery
 */
function wc_kwik_delivery()
{
    return \WC_Kwik_Delivery::instance();
}

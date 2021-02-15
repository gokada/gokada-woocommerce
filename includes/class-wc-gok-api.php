<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class WC_Gokada_Delivery_API
{
    public function __construct($settings = array())
    {
        $env = isset($settings['mode']) ? $settings['mode'] : 'test';

        // $this->request_url = 'http://gokada.local/';
        $this->request_url = 'https://api.gokada.ng/';
    }

    public function get_order_details($params)
    {
        return $this->send_request('api/developer/order_status', $params);
    }

    public function create_task($params)
    {
        return $this->send_request('api/developer/woocommerce_order_create', $params);
    }

    public function cancel_task($params)
    {
        return $this->send_request('api/developer/order_cancel', $params);
    }

    public function calculate_pricing($params)
    {
        return $this->send_request('api/developer/woocommerce_order_estimate', $params);
    }

    /**
     * Send HTTP Request
     * @param string $endpoint API request path
     * @param array $args API request arguments
     * @param string $method API request method
     * @return object|null JSON decoded transaction object. NULL on API error.
     */
    public function send_request(
        $endpoint,
        $args = array(),
        $method = 'post'
    ) {
        $uri = "{$this->request_url}{$endpoint}";

        $arg_array = array(
            'method'    => strtoupper($method),
            'body'      => $args,
            'headers'   => $this->get_headers()
        );

        $res = wp_remote_request($uri, $arg_array);

        if (is_wp_error($res)) {
            throw new \Exception(__('You had an HTTP error connecting to Gokada delivery'));
        } else {
            $body = wp_remote_retrieve_body($res);
            
            if (null !== ($json = json_decode($body, true))) {
                return $json;
            } else // Un-decipherable message
                throw new Exception(__('There was an issue connecting to Gokada delivery. Try again later.'));
        }

        return false;
    }

    /**
     * Generates the headers to pass to API request.
     */
    public function get_headers()
    {
        return array(
            'Accept' => 'application/json',
        );
    }
}

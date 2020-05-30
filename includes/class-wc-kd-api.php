<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class WC_Kwik_Delivery_API
{
    protected $login_credentials;

    protected $request_url;

    protected $domain_name = 'staging-client-panel.kwik.delivery';

    public function __construct($settings = array())
    {
        $email    = isset($settings['email']) ? $settings['email'] : '';
        $password = isset($settings['password']) ? $settings['password'] : '';
        $env      = isset($settings['mode']) ? $settings['mode'] : 'sandbox';

        $this->request_url = ('live' === $env) ? 'https://api.kwik.delivery/' : 'https://staging-api-test.kwik.delivery/';

        $this->vendor_login($email, $password);
    }

    /**
     * Call the Kwik Delivery Login API
     *
     * @param string $email
     * @param string $password
     * @return void
     */
    public function vendor_login($email, $password)
    {
        $login_credentials = get_transient('kwik_delivery_login_credentials');

        // Transient expired or doesn't exist, fetch the data
        if (false === $login_credentials) {
            //login credentials
            $params = [
                'domain_name'  => $this->domain_name,
                'email'        => $email,
                'password'     => $password,
                'api_login'    => 1
            ];

            $response = $this->send_request(
                'vendor_login',
                $params
            );

            $login_credentials = $response['data'];

            set_transient('kwik_delivery_login_credentials', $login_credentials, HOUR_IN_SECONDS);
        }

        $this->login_credentials = $login_credentials;
    }

    public function create_task($params, $schedule_task = false)
    {
        $params['domain_name']  = $this->domain_name;
        $params['access_token'] = $this->login_credentials['access_token'];
        $params['vendor_id']    = $this->login_credentials['vendor_details']['vendor_id'];

        if ($schedule_task) {
            $params['is_schedule_task'] = 1;
        }

        return $this->send_request('create_task_via_vendor', $params);
    }

    public function cancel_task($params)
    {
        $params['domain_name']  = $this->domain_name;
        $params['access_token'] = $this->login_credentials['access_token'];
        $params['vendor_id']    = $this->login_credentials['vendor_details']['vendor_id'];

        return $this->send_request('cancel_vendor_task', $params);
    }

    public function calculate_pricing($params)
    {
        $params['domain_name']  = $this->domain_name;
        $params['access_token'] = $this->login_credentials['access_token'];
        $params['vendor_id']    = $this->login_credentials['vendor_details']['vendor_id'];

        $params['user_id'] = 1;
        $params['form_id'] = 2;
        $params['layout_type'] =  0;
        $params['auto_assignment'] = 0;
        $params['is_multiple_tasks'] = 1;
        $params['custom_field_template'] = 'pricing-template';
        $params['pickup_custom_field_template'] =  'pricing-template';

        return $this->send_request('send_payment_for_task', $params);
    }

    public function get_lat_lng($address)
    {
        $address = rawurlencode($address);
        $coord   = get_transient('kwik_delivery_geocode_' . $address);
        if (empty($coord)) {
            $url  = 'http://nominatim.openstreetmap.org/?format=json&addressdetails=1&q=' . $address . '&format=json&limit=1';
            $json = wp_remote_get($url);
            if (200 === (int) wp_remote_retrieve_response_code($json)) {
                $body = wp_remote_retrieve_body($json);
                $json = json_decode($body, true);
            }

            $coord['lat']  = $json[0]['lat'];
            $coord['long'] = $json[0]['lon'];
            set_transient('kwik_delivery_geocode_' . $address, $coord, DAY_IN_SECONDS * 90);
        }
        return $coord;
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
            throw new \Exception(__('You had an HTTP error connecting to Kwik delivery'));
        } else {
            $body = wp_remote_retrieve_body($res);
            if (null !== ($json = json_decode($body, true))) {
                if (isset($json['error']) || $json['status'] == false)
                    throw new Exception("{$json['message']}");
                else
                    return $json;
            } else // Un-decipherable message
                throw new Exception(__('There was an issue connecting to kwik delivery. Try again later.'));
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

<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * gokada Delivery Shipping Method Class
 *
 * Provides real-time shipping rates from gokada delivery and handle order requests
 *
 * @since 1.0
 * @extends \WC_Shipping_Method
 */
class WC_Gokada_Delivery_Shipping_Method extends WC_Shipping_Method
{
	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct($instance_id = 0)
	{
		$this->id                 = 'gokada_delivery';
		$this->instance_id 		  = absint($instance_id);
		$this->method_title       = __('Gokada Delivery');
		$this->method_description = __('Get your parcels delivered better, cheaper and quicker via gokada Delivery');

		$this->supports  = array(
			'settings',
			'shipping-zones',
		);

		$this->init();

		$this->title = 'Gokada Delivery';

		$this->enabled = $this->get_option('enabled');
	}

	/**
	 * Init.
	 *
	 * Initialize gokada delivery shipping method.
	 *
	 * @since 1.0.0
	 */
	public function init()
	{
		$this->init_form_fields();
		$this->init_settings();

		// Save settings in admin if you have any defined
		add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
	}

	/**
	 * Init fields.
	 *
	 * Add fields to the gokada delivery settings page.
	 *
	 * @since 1.0.0
	 */
	public function init_form_fields()
	{
		$pickup_state_code = WC()->countries->get_base_state();
		$pickup_country_code = WC()->countries->get_base_country();

		$pickup_city = WC()->countries->get_base_city();
		$pickup_state = WC()->countries->get_states($pickup_country_code)[$pickup_state_code];
		$pickup_base_address = WC()->countries->get_base_address();

		$this->form_fields = array(
			'enabled' => array(
				'title' 	=> __('Enable/Disable'),
				'type' 		=> 'checkbox',
				'label' 	=> __('Enable this shipping method'),
				'default' 	=> 'no',
			),
			'mode' => array(
				'title'       => 	__('Mode'),
				'hidden'		  => 'true',
				'type'        => 	'select',
				'description' => 	__('Default is (Sandbox), choose (Live) when your ready to start processing orders via gokada delivery'),
				'default'     => 	'sandbox',
				'options'     => 	array('sandbox' => 'Sandbox', 'live' => 'Live'),
			),
			'api_key' => array(
				'title'       => 	__('API Key'),
				'type'        => 	'password',
				// 'description'       => __( 'Here’s how to get Gokada Developer API token:<br/>
				// 							1. Login into your Gokada Business Account<br/>
                // 							2. Copy the Key from your profile and paste it here.' ),
                'description'   => __( '<a href="https://business.gokada.ng/" target="_blank">Get your Gokada Developer API token</a>'),
				'default'     => 	__('')
			),
			'shipping_is_scheduled_on' => array(
				'title'        =>	__('Schedule shipping task'),
				'type'         =>	'select',
				'description'  =>	__('Select when the shipment will be created.'),
				'default'      =>	__('order_submit'),
				'desc_tip'          => false,
				'options'      =>	array('order_submit' => 'Order submit with complete payment(Auto Delivery)', 'scheduled_submit' => 'Schedule a time interval to submit all pending orders', 'shipment_submit' => 'Shipment submit from admin dashboard')
			),
			'shipping_handling_fee' => array(
				'title'       => 	__('Additional handling fee applied'),
				'type'        => 	'text',
				'description' => 	__("Additional handling fee applied"),
				'default'     => 	__('0')
			),
			'shipping_payment_method' => array(
				'title'        =>	__('Payment method for shipment'),
				'type'         =>	'select',
				'description'  =>	__('Select payment method.'),
				'default'      =>	__('1'),
				'options'      =>	array('1' => 'Wallet payment')
			),
			'pickup_delay_same' => array(
				'title'       => 	__('Enter pickup delay time in hours(Auto delivery only)'),
				'type'        => 	'text',
				'description' => 	__("If pickup time should be delayed by some hours. Defaults to 0"),
				'default'     => 	__('0')
			),
			'pickup_schedule_time' => array(
				'title'       => 	__('Enter Daily pickup schedule time in hours(Scheduled Delivery Only)'),
				'type'        => 	'time',
			),
			'pickup_country' => array(
				'title'       => 	__('Pickup Country'),
				'type'        => 	'select',
				'description' => 	__('gokada delivery/pickup is only available for Nigeria'),
				'default'     => 	'NG',
				'options'     => 	array("NG" => "Nigeria", "" => "Please Select"),
			),
			'pickup_state' => array(
				'title'       => 	__('Pickup State'),
				'type'        => 	'text',
				'description' => 	__('Service available in Lagos Only'),
				'default'     => 	__($pickup_state)
			),
			'pickup_city' => array(
				'title'       => 	__('Pickup City'),
				'type'        => 	'text',
				'description' => 	__('The local area where the parcel will be picked up.'),
				'default'     => 	__($pickup_city)
			),
			'pickup_base_address' => array(
				'title'       => 	__('Pickup Address'),
				'type'        => 	'text',
				'description' => 	__('The street address where the parcel will be picked up.'),
				'default'     => 	__($pickup_base_address)
			),
			'sender_name' => array(
				'title'       => 	__('Sender Name'),
				'type'        => 	'text',
				'description' => 	__("Sender Name"),
				'default'     => 	__('')
			),
			'sender_phone_number' => array(
				'title'       => 	__('Sender Phone Number'),
				'type'        => 	'text',
				'description' => 	__('Must be a valid phone number'),
				'default'     => 	__('')
			),
			'sender_email' => array(
				'title'       => 	__('Sender Email'),
				'type'        => 	'text',
				'description' => 	__('Must be a valid email address'),
				'default'     => 	__('')
			),
		);
	}

	/**
	 * Calculate shipping by sending destination/items to Gokada and parsing returned rates
	 *
	 * @since 1.0
	 * @param array $package
	 */
	public function calculate_shipping($package = array())
	{
		// return;
		if ($this->get_option('enabled') == 'no') {
			return;
		}

		if ($this->get_option('mode') == 'sandbox' && strpos($this->get_option('api_key'), 'test') != 0) {
			wc_add_notice('Gokada Error: Production API Key used in Sandbox mode', 'error');
			return;
		}

		// country required for all shipments
		if (!$package['destination']['country'] && 'NG' !== $package['destination']['country']) {
			return;
		}

		$delivery_country_code = $package['destination']['country'];
		$delivery_state_code = $package['destination']['state'];
		$delivery_city = $package['destination']['city'];
		$delivery_base_address = $package['destination']['address'];

		$delivery_state = WC()->countries->get_states($delivery_country_code)[$delivery_state_code];
		$delivery_country = WC()->countries->get_countries()[$delivery_country_code];

		if ('Lagos' !== $delivery_state) {
			wc_add_notice('Gokada Delivery only available within Lagos', 'error');
			return;
		}

		$api = wc_gokada_delivery()->get_api();

		$pickup_city = $this->get_option('pickup_city');
		$pickup_state = $this->get_option('pickup_state');
		$pickup_base_address = $this->get_option('pickup_base_address');
		$pickup_country = WC()->countries->get_countries()[$this->get_option('pickup_country')];

		$delivery_address = trim("$delivery_base_address $delivery_city, $delivery_state, $delivery_country");
		$delivery_coordinate = $api->get_lat_lng($delivery_address);
		if (!isset($delivery_coordinate['lat']) && !isset($delivery_coordinate['long'])) {
			$delivery_coordinate = $api->get_lat_lng("$delivery_city, $delivery_state, $delivery_country");
		}

		$pickup_address = trim("$pickup_base_address $pickup_city, $pickup_state, $pickup_country");
		$pickup_coordinate = $api->get_lat_lng($pickup_address);
		if (!isset($pickup_coordinate['lat']) && !isset($pickup_coordinate['long'])) {
			$pickup_coordinate = $api->get_lat_lng("$pickup_city, $pickup_state, $pickup_country");
		}

		$test_mode = $this->get_option('mode') == 'sandbox' ? true : false;

		$params = array(
			'api_key' => $this->get_option('api_key'),
			'pickup_latitude' => $pickup_coordinate['lat'],
			'pickup_longitude' => $pickup_coordinate['long'],
			'delivery_latitude' => $delivery_coordinate['lat'],
			'delivery_longitude' => $delivery_coordinate['long'],

		);

		$res = $api->calculate_pricing($params);

		if (!$res['fare']) {
			wc_add_notice(__($res['message']), 'error');
			return;
		} else {
			$data = $res;
			$handling_fee = $this->get_option('shipping_handling_fee');

			if ($handling_fee < 0) {
				$handling_fee = 0;
			}

			$cost = wc_format_decimal($data['fare']) + wc_format_decimal($handling_fee);
			
			$this->add_rate(array(
				'id'    	=> $this->id . $this->instance_id,
				'label' 	=> $this->title,
				'cost'  	=> $cost,
			));
		}
	}
}

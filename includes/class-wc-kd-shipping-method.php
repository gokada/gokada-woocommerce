<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

if (class_exists('Kwik_Delivery_Shipping_Method')) return; // Stop if the class already exists

/**
 * Kwik Delivery Shipping Method Class
 *
 * Provides real-time shipping rates from Kwik delivery and handle order requests
 *
 * @since 1.0
 * @extends \WC_Shipping_Method
 */
class WC_Kwik_Delivery_Shipping_Method extends WC_Shipping_Method
{
	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct($instance_id = 0)
	{
		$this->id                 = 'kwik_delivery';
		$this->instance_id 		  = absint($instance_id);
		$this->method_title       = __('Kwik Delivery');
		$this->method_description = __('Get your parcels delivered better, cheaper and quicker via Kwik Delivery');

		$this->supports  = array(
			'settings',
			'shipping-zones',
		);

		$this->init();

		$this->title   = __('Kwik Delivery');
		$this->enabled = $this->get_option('enabled');
	}

	/**
	 * Init.
	 *
	 * Initialize kwik delivery shipping method.
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
	 * Add fields to the kwik delivery settings page.
	 *
	 * @since 1.0.0
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title' 		=> __('Enable/Disable'),
				'type' 			=> 'checkbox',
				'label' 		=> __('Enable this shipping method'),
				'default' 		=> 'no',
			),
			'email' => array(
				'title'       => 	__('Email Address'),
				'type'        => 	'email',
				'description' => 	__('Your Kwik delivery account email', 'woocommerce-kwik-delivery'),
				'default'     => 	__('')
			),
			'password' => array(
				'title'       => 	__('Password'),
				'type'        => 	'password',
				'description' => 	__('Your Kwik delivery account password', 'woocommerce-kwik-delivery'),
				'default'     => 	__('')
			),
			'mode' => array(
				'title'       => 	__('Mode'),
				'type'        => 	'select',
				'description' => 	__('Default is (Sandbox), choose (Live) when your ready to start processing orders via kwik delivery'),
				'default'     => 	'sandbox',
				'options'     => 	array("sandbox" => "Sandbox", "live" => "Live"),
			),
		);
	}

	/**
	 * Calculate shipping by sending destination/items to Shipwire and parsing returned rates
	 *
	 * @since 1.0
	 * @param array $package
	 */
	public function calculate_shipping($package = array())
	{
		if ($this->enabled == 'no') {
			return;
		}

		// country required for all shipments
		if (!$package['destination']['country'] && 'NG' !== $package['destination']['country']) {
			return;
		}

		$api = wc_kwik_delivery()->get_api();

		$pickup_state_code = WC()->countries->get_base_state();
		$pickup_country_code = WC()->countries->get_base_country();

		$pickup_city = WC()->countries->get_base_city();
		$pickup_state = WC()->countries->get_states($pickup_country_code)[$pickup_state_code];
		$pickup_country = WC()->countries->get_countries()[$pickup_country_code];
		$pickup_base_address = WC()->countries->get_base_address();

		$pickup_address = trim("$pickup_base_address $pickup_city, $pickup_state, $pickup_country");
		$pickup_coordinate = $api->get_lat_lng($pickup_address);

		$delivery_country_code = $package['destination']['country'];
		$delivery_state_code = $package['destination']['state'];
		$delivery_city = $package['destination']['city'];
		$delivery_base_address = $package['destination']['address'];

		$delivery_state = WC()->countries->get_states($delivery_country_code)[$delivery_state_code];
		$delivery_country = WC()->countries->get_countries()[$delivery_country_code];

		$delivery_address = trim("$delivery_base_address $delivery_city, $delivery_state, $delivery_country");
		$delivery_coordinate = $api->get_lat_lng($delivery_address);

		$pickups = array(
			array(
				"address" => $pickup_address,
				"latitude" => $pickup_coordinate['lat'],
				"longitude" => $pickup_coordinate['long']
			)
		);

		$deliveries = array(
			array(
				"address" => $delivery_address,
				"latitude" => $delivery_coordinate['lat'],
				"longitude" => $delivery_coordinate['long']
			)
		);

		$params = array(
			'has_pickup' => 1,
			'has_delivery' => 1,
			'payment_method' => 32,
			'pickups' => $pickups,
			'deliveries' => $deliveries
		);

		$res = $api->calculate_pricing($params);
		
		$cost = $res['data']['per_task_cost'];

		$this->add_rate(array(
			'id'    => $this->id . $this->instance_id,
			'label' => $this->title,
			'cost'  => $cost,
		));
	}

	/**
	 * Format estimated delivery dates according to site date format
	 *
	 * @since 1.0
	 * @param string $min_time minimum time in transit for delivery (e.g. '2 days')
	 * @param string $max_time maximum time in transit for delivery (e.g. '4 days')
	 * @return string formatted datetime in "Estimated delivery on {date}" or "Estimated delivery between {date} and {date}"
	 */
	private function format_delivery_date($min_time, $max_time)
	{
		// shipping time estimates are business days
		$min_time = strtotime(str_replace('days', 'weekdays', $min_time));
		$max_time = strtotime(str_replace('days', 'weekdays', $max_time));

		// add a day when the delivery estimate is short so the customer has a reasonable expectation
		if ($min_time == $max_time) {
			$max_time += DAY_IN_SECONDS;
		}

		// pretty format
		$from_date = date_i18n(wc_date_format(), $min_time);
		$to_date   = date_i18n(wc_date_format(), $max_time);

		/* translators: Placeholders: %1$s - from date, %2$s - to date */
		return sprintf(__('(Estimated delivery between %1$s and %2$s)', 'woocommerce-shipwire'), $from_date, $to_date);
	}
}

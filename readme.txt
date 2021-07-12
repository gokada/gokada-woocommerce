=== Gokada Delivery for WooCommerce ===
Tags: ecommerce, shipping, delivery, gokada delivery
Tested up to: 5.7.2
Requires PHP: 5.4
Minimum Woocommerce version: 4.0
Stable tag: 1.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html


== Description ==

This is a Gokada Delivery plugin for WooCommerce.

This plugin allows you to create Gokada deliveries for your WooCommerce orders. To get an API key, visit [business.gokada.ng](https://business.gokada.ng) and signup for a Gokada Developer account.

= Note =

Gokada currently serves only __Lagos, Nigeria__.

= Features =

*   __Create deliveries__: Get access to hundreds of qualified pilots and deliver anywhere in Lagos
*   __Schedule tasks__: Choose whether to schedule delivery immediately payment is made, at a time of your choice, or manually via the Woocommerce dashboard
*   __Tracking__: Get real-time tracking links for you and your customers

== Installation ==

= Manual Installation =
1. 	Download the latest version of the plugin in zip format
2. 	Login to your WordPress Admin. Click on "Plugins > Add New" from the left hand menu.
3.  Click on the "Upload" option, then click "Choose File" to select the zip file from your computer. Once selected, press "OK" and press the "Install Now" button.
4.  Activate the plugin.
5. 	Open the settings page for WooCommerce and click the __WooCommerce > Settings__ and click the "Shipping" tab.
6.  Add a new Shipping Zone, with the `Zone Region` set as __Lagos__. Click on `Add Shipping Method` and select __Gokada Delviery__ from the list of options.
7.  Configure your __Gokada Delivery__ settings. See below for details.



= Configure the plugin =
To configure the plugin, go to __WooCommerce > Settings__ from the left hand menu, then click __Shipping__ from the top tab. You will see __Gokada__ as part of the available Shipping Options. Click on it to configure the shipping method.

* __Enable/Disable__ - check the box to enable the Gokada Delivery shipping method.
* __Mode__ - Select whether to enable test or live mode.
* __Test API Key__ - Enter your test API Key here.
* __Live API Key__ - Enter your live API Key here.
* __Schedule Shipping Task__ - Choose when Gokada orders are created.
* __Additional Handling Fee__ - Add an additional handling fee to the delivery total.
* __Pickup Delay Time__ - Enter a delay for auto-created Gokada deliveries.
* __Pickup Schedule Time__ - Enter a daily time for scheduled Gokada deliveries.
* __Pickup Country__ - Your store's country __(Default: Nigeria)__.
* __Pickup State__ - Your store's state __(Default: Lagos)__.
* __Pickup City__ - Your store's city.
* __Pickup Address__ - Your store's address.
* __Sender Name__ - Your store's name.
* __Sender Phone Number__ - Your store's phone number.
* __Sender Email__ - Your store's email address.
* Click on __Save Changes__.

= Note =

== Usage ==

= Manually creating orders =

To manually create Gokada Delivery orders if you have selected this option, go to the Woocommerce Orders page on **WooCommerce > Orders** and click on the order to the Detail page. On the top-right side of the page, select "Create Gokada Delivery order" from **Order Actions** and click the **>** button next to it.

Please ensure `Pickup City`, `Pickup Address` and `Sender Phone Number` fields are filled and correct, to avoid unwanted errors.


== Frequently Asked Questions ==

Visit [gokada.ng/faq](https://www.gokada.ng/faq).


== Changelog ==

= 1.3.2 = 
* Update autocomplete endpoint URL

= 1.3.1 = 
* Update API base URL

= 1.3 = 
* Remove lat/lng API dependency

= 1.2.1 =
* Tweak: Use billing_city to store delivery latitude/longitude

= 1.2 =
* Get autocomplete results on address fields
* Modify Scheduled delivery as a standalone order method
 
= 1.1 =
* Different fields for Test and Live API keys
* Create orders manually from Order page
* Scheduled orders are now created once the order status is "Processing"
* Display errors on order page
* Allow Retry of failed orders
* Allow Bulk Order creation on manual submit mode
 
= 1.0 =
* First release
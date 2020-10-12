<?php

/**
 * Plugin Name: WooCommerce Kwik Delivery 
 * Plugin URI: http://kwik.delivery/
 * Description: A plugin to handle order shipping via kwik delivery
 * Version: 1.0.0
 * Author: Kwik Delivery
 * Author URI: http://kwik.delivery/
 * Developer: Wisdom Ebong
 * Developer URI: http://wisdomebong.com
 * Requires at least: 4.4
 * Tested up to: 5.2
 * 
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Copyright: © 2020 Kwik Delivery
 */

if (!defined('WPINC')) {
    die('security by preventing any direct access to your plugin file');
}

/**
 * WooCommerce Kwik Delivery Loader.
 *
 */
class WC_Kwik_Delivery_Loader
{

    /** minimum PHP version required by this plugin */
    const MINIMUM_PHP_VERSION = '5.4.0';

    /** minimum WordPress version required by this plugin */
    const MINIMUM_WP_VERSION = '4.4';

    /** minimum WooCommerce version required by this plugin */
    const MINIMUM_WC_VERSION = '4.0';

    /** the plugin name, for displaying notices */
    const PLUGIN_NAME = 'WooCommerce KwikDelivery';

    /** @var \WC_KwikDelivery_Loader single instance of this class */
    private static $instance;

    private static $active_plugins;

    /** @var array the admin notices to add */
    private $notices = array();

    /**
     * Sets up the loader.
     *
     */
    protected function __construct()
    {
        $this->active_plugins = (array) get_option('active_plugins', array());

        if (is_multisite())
            $this->active_plugins = array_merge($this->active_plugins, get_site_option('active_sitewide_plugins', array()));

        if (!$this->wc_active_check()) {
            return;
        }

        register_activation_hook(__FILE__, array($this, 'activation_check'));

        add_action('admin_init', array($this, 'check_environment'));
        add_action('admin_init', array($this, 'add_plugin_notices'));

        add_action('admin_notices', array($this, 'admin_notices'), 15);

        // if the environment check fails, initialize the plugin
        if ($this->is_environment_compatible()) {
            add_action('plugins_loaded', array($this, 'init_plugin'));
        }
    }

    /**
     * Initializes the plugin.
     *
     */
    public function init_plugin()
    {
        if (!$this->plugins_compatible()) {
            return;
        }

        // load the main plugin class
        require_once(plugin_dir_path(__FILE__) . 'class-wc-kwik-delivery.php');

        wc_kwik_delivery();
    }

    public function wc_active_check()
    {
        return in_array('woocommerce/woocommerce.php', $this->active_plugins) || array_key_exists('woocommerce/woocommerce.php', self::$active_plugins);
    }

    /**
     * Checks the server environment and other factors and deactivates plugins as necessary.
     *
     * Based on http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments
     *
     */
    public function activation_check()
    {
        if (!$this->is_environment_compatible()) {

            $this->deactivate_plugin();

            wp_die(self::PLUGIN_NAME . ' could not be activated. ' . $this->get_environment_message());
        }
    }

    /**
     * Checks the environment on loading WordPress, just in case the environment changes after activation.
     *
     */
    public function check_environment()
    {
        if (!$this->is_environment_compatible() && is_plugin_active(plugin_basename(__FILE__))) {

            $this->deactivate_plugin();

            $this->add_admin_notice('bad_environment', 'error', self::PLUGIN_NAME . ' has been deactivated. ' . $this->get_environment_message());
        }
    }

    /**
     * Adds notices for out-of-date WordPress and/or WooCommerce versions.
     *
     */
    public function add_plugin_notices()
    {
        if (!$this->is_wp_compatible()) {

            $this->add_admin_notice('update_wordpress', 'error', sprintf(
                '%s requires WordPress version %s or higher. Please %supdate WordPress &raquo;%s',
                '<strong>' . self::PLUGIN_NAME . '</strong>',
                self::MINIMUM_WP_VERSION,
                '<a href="' . esc_url(admin_url('update-core.php')) . '">',
                '</a>'
            ));
        }

        if (!$this->is_wc_compatible()) {

            $this->add_admin_notice('update_woocommerce', 'error', sprintf(
                '%1$s requires WooCommerce version %2$s or higher. Please %3$supdate WooCommerce%4$s to the latest version, or %5$sdownload the minimum required version &raquo;%6$s',
                '<strong>' . self::PLUGIN_NAME . '</strong>',
                self::MINIMUM_WC_VERSION,
                '<a href="' . esc_url(admin_url('update-core.php')) . '">',
                '</a>',
                '<a href="' . esc_url('https://downloads.wordpress.org/plugin/woocommerce.' . self::MINIMUM_WC_VERSION . '.zip') . '">',
                '</a>'
            ));
        }
    }

    /**
     * Determines if the required plugins are compatible.
     *
     * @return bool
     */
    protected function plugins_compatible()
    {
        return $this->is_wp_compatible() && $this->is_wc_compatible();
    }

    /**
     * Determines if the WordPress compatible.
     *
     * @return bool
     */
    protected function is_wp_compatible()
    {
        return version_compare(get_bloginfo('version'), self::MINIMUM_WP_VERSION, '>=');
    }

    /**
     * Determines if the WooCommerce compatible.
     *
     * @return bool
     */
    protected function is_wc_compatible()
    {
        return defined('WC_VERSION') && version_compare(WC_VERSION, self::MINIMUM_WC_VERSION, '>=');
    }

    /**
     * Deactivates the plugin.
     *
     */
    protected function deactivate_plugin()
    {
        deactivate_plugins(plugin_basename(__FILE__));

        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }

    /**
     * Adds an admin notice to be displayed.
     *
     * @param string $slug the slug for the notice
     * @param string $class the css class for the notice
     * @param string $message the notice message
     */
    public function add_admin_notice($slug, $class, $message)
    {
        $this->notices[$slug] = array(
            'class'   => $class,
            'message' => $message
        );
    }

    /**
     * Displays any admin notices set.
     *
     * @see \WC_KwikDelivery_Loader_Loader::add_admin_notice()
     *
     */
    public function admin_notices()
    {
        foreach ($this->notices as $notice_key => $notice) :

?>
            <div class="<?php echo esc_attr($notice['class']); ?>">
                <p><?php echo wp_kses($notice['message'], array('a' => array('href' => array()))); ?></p>
            </div>
<?php

        endforeach;
    }

    /**
     * Determines if the server environment is compatible with this plugin.
     *
     * Override this method to add checks for more than just the PHP version.
     *
     * @return bool
     */
    protected function is_environment_compatible()
    {
        return version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=');
    }

    /**
     * Gets the message for display when the environment is incompatible with this plugin.
     *
     * @return string
     */
    protected function get_environment_message()
    {
        return sprintf('The minimum PHP version required for this plugin is %1$s. You are running %2$s.', self::MINIMUM_PHP_VERSION, PHP_VERSION);;
    }

    /**
     * Cloning instances is forbidden due to singleton pattern.
     *
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, sprintf('You cannot clone instances of %s.', get_class($this)), '1.0.0');
    }

    /**
     * Unserializing instances is forbidden due to singleton pattern.
     *
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, sprintf('You cannot unserialize instances of %s.', get_class($this)), '1.0.0');
    }

    /**
     * Gets the main loader instance.
     *
     * Ensures only one instance can be loaded.
     *
     *
     * @return \WC_Kwik_Delivery_Loader
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}

// fire it up!
WC_Kwik_Delivery_Loader::instance();

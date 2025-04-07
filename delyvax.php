<?php
/**
 * Delyva
 *
 * @package           Delyva
 * @author            Delyva
 * @copyright         2025 Delyva
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Delyva
 * Plugin URI:        https://delyva.com
 * Description:       The official Delyva plugin helps store owners to integrate WooCommerce with Delyva for seamless service comparison and order processing.
 * Version:           1.2.0
 * Author:            Delyva
 * Author URI:        https://delyva.com
 * Text Domain:       delyvax
 * License:           GPL v3 or later
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * WC requires at least: 3.0
 * WC tested up to:   7.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) or die();

// Define plugin constants
define('DELYVAX_PLUGIN_VERSION', '1.2.0');
define('DELYVAX_PLUGIN_FILE', __FILE__);
define('DELYVAX_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DELYVAX_API_ENDPOINT', 'https://api.delyva.app/');

/**
 * Main plugin class to handle initialization and hooks
 */
class DelyvaX_Plugin {
    /**
     * Constructor - setup hooks
     */
    public function __construct() {
        // Setup hooks - organized by initialization phase
        
        // Early WordPress hooks
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
        
        // Plugin lifecycle hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Plugin initialization
        add_action('plugins_loaded', array($this, 'init'));
        
        // Admin UI hooks
        add_filter('plugin_action_links_' . plugin_basename(DELYVAX_PLUGIN_FILE), array($this, 'add_plugin_action_links'));
    }
    
    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', DELYVAX_PLUGIN_FILE, true);
        }
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load core files
        $this->load_includes();
        
        // Init WooCommerce specific hooks
        add_action('woocommerce_shipping_init', array($this, 'init_shipping'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
    }
    
    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        return is_plugin_active('woocommerce/woocommerce.php') || 
               (is_multisite() && is_plugin_active_for_network('woocommerce/woocommerce.php'));
    }
    
    /**
     * Admin notice for WooCommerce dependency
     */
    public function woocommerce_missing_notice() {
        $message = esc_html__('Delyva requires WooCommerce to be installed and active.', 'delyvax');
        echo '<div class="error"><p>' . $message . '</p></div>';
    }
    
    /**
     * Load required files
     */
    private function load_includes() {
        require_once DELYVAX_PLUGIN_PATH . 'includes/utils.php';
        require_once DELYVAX_PLUGIN_PATH . 'functions.php';
        require_once DELYVAX_PLUGIN_PATH . 'includes/delyvax-webhook.php';
        require_once DELYVAX_PLUGIN_PATH . 'includes/delyvax-label.php';
        require_once DELYVAX_PLUGIN_PATH . 'includes/shipping-widget.php';
    }
    
    /**
     * Initialize shipping method
     */
    public function init_shipping() {
        include_once DELYVAX_PLUGIN_PATH . 'includes/delyvax-shipping.php';
    }
    
    /**
     * Add shipping method to WooCommerce
     *
     * @param array $methods Shipping methods.
     * @return array Modified shipping methods.
     */
    public function add_shipping_method($methods) {
        if (is_array($methods)) {
            $methods['delyvax'] = 'DelyvaX_Shipping_Method';
        }
        return $methods;
    }
    
    /**
     * Add settings link to plugin page
     *
     * @param array $links Plugin action links.
     * @return array Modified action links.
     */
    public function add_plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=wc-settings&tab=shipping&section=delyvax')),
            esc_html__('Settings', 'delyvax')
        );
        
        return array_merge($links, array($settings_link));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Activation code here
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Deactivation code here
    }
}

// Initialize the plugin
new DelyvaX_Plugin();
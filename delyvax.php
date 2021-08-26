<?php
    /*
    Plugin Name: Delyva
    Plugin URI: https://delyva.com
    description: The official Delyva plugin helps store owners to integrate WooCommerce with [Delyva](https://delyva.com) for seamless service comparison and order processing.
    Version: 1.1.16
    Author: Delyva
    Author URI: https://delyva.com
    License: GPLv3
    */

    // Include functions.php, use require_once to stop the script if functions.php is not found
    defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
    define('DELYVAX_API_ENDPOINT', 'https://api.delyva.app/');
    define('DELYVAX_PLUGIN_VERSION', '1.1.16');

    require_once plugin_dir_path(__FILE__) . 'functions.php';
    require_once plugin_dir_path(__FILE__) . 'includes/shipping-widget.php'; // TEMPORARILY DISABLED

    if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        if ( ! class_exists( 'WC_Integration_DelyvaX' ) ) {
            class WC_Integration_DelyvaX {
                public function __construct() {
                    add_action('woocommerce_shipping_init', array( $this, 'init' ) );
                    add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
                }

                public function init() {
                    add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
                    if (class_exists('WC_Integration')) {
                        // Include our integration class.
                        include_once 'includes/delyvax-shipping.php';
                        // Register the integration.
                        add_filter('woocommerce_shipping_methods', array($this, 'add_integration'));
                    }
                }

                public function plugin_action_links($links) {
                    return array_merge(
                        $links,
                        array('<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=delyvax') . '"> ' . __('Settings', 'delyvax') . '</a>')
                    );
                }

                public function add_shipping_method($methods) {
                    if (is_array($methods)) {
                        $methods['delyvax'] = 'DelyvaX_Shipping_Method';
                    }
                    return $methods;
                }

                public function add_integration($integrations) {
                    $integrations[] = 'DelyvaX_Shipping_Method';
                    return $integrations;
                }
            }
            $WC_Integration_DelyvaX = new WC_Integration_DelyvaX();
        }
    }

<?php
defined( 'ABSPATH' ) or die();

/**
 * Get order meta value with fallback options
 * 
 * @param WC_Order $order The order object
 * @param array $meta_keys Array of meta keys to check in order of preference
 * @return mixed The meta value or null if none found
 */
function delyvax_get_order_meta($order, $meta_keys) {
    foreach ($meta_keys as $key) {
        $value = $order->get_meta($key);
        if ($value) {
            return $value;
        }
    }
    return null;
}
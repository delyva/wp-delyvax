<?php
defined( 'ABSPATH' ) or die();

/**
 * Add bulk action for printing labels in WooCommerce orders page
 */

// Add bulk action to the dropdown
function delyvax_register_bulk_actions($bulk_actions) {
    $bulk_actions['print_delyvax_labels'] = __('Delyva: Print Labels', 'delyvax');
    return $bulk_actions;
}
add_filter('bulk_actions-edit-shop_order', 'delyvax_register_bulk_actions', 900);
add_filter('bulk_actions-woocommerce_page_wc-orders', 'delyvax_register_bulk_actions', 900);

// Handle the bulk action
function delyvax_handle_bulk_actions($redirect_to, $action, $order_ids) {
    if ($action !== 'print_delyvax_labels') {
        return $redirect_to;
    }

    $delyvax_order_ids = [];
    
    // Collect DelyvaX order IDs from each selected WooCommerce order
    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $delyvax_order_id = $order->get_meta('DelyvaXOrderID');
            // Make sure we have a valid string value
            if ($delyvax_order_id && is_string($delyvax_order_id) && !empty($delyvax_order_id)) {
                $delyvax_order_ids[] = $delyvax_order_id;
            }
        }
    }
    
    // If we have DelyvaX order IDs, redirect to our print page
    if (!empty($delyvax_order_ids)) {
        // Use the alternative page that handles error responses better
        $redirect_to = add_query_arg(
            'delyvax_print_labels',
            implode(',', $delyvax_order_ids),
            admin_url('admin.php?page=delyvax-print-labels-alt')
        );
    } else {
        // No DelyvaX orders found in selection
        $redirect_to = add_query_arg(
            'delyvax_print_error',
            'no_orders',
            $redirect_to
        );
    }
    
    return $redirect_to;
}
add_filter('handle_bulk_actions-edit-shop_order', 'delyvax_handle_bulk_actions', 10, 3);
add_filter('handle_bulk_actions-woocommerce_page_wc-orders', 'delyvax_handle_bulk_actions', 10, 3);

// Add admin menu page for printing labels
function delyvax_add_print_labels_page() {
    add_submenu_page(
        '',
        __('Delyva: Print Labels', 'delyvax'),
        __('Print Labels', 'delyvax'),
        'manage_woocommerce',
        'delyvax-print-labels-alt',
        'delyvax_print_labels_page_content'
    );
}
add_action('admin_menu', 'delyvax_add_print_labels_page');
function delyvax_print_labels_page_content() {
    // Check if we have order IDs to print
    if (!isset($_GET['delyvax_print_labels']) || $_GET['delyvax_print_labels'] === '') {
        wp_die(__('No orders selected for printing labels.', 'delyvax'));
    }
    
    $order_ids = sanitize_text_field($_GET['delyvax_print_labels']);
    
    // Extra validation to ensure we have valid data
    if (empty($order_ids)) {
        wp_die(__('Invalid order IDs provided.', 'delyvax'));
    }
    
    // Construct the direct URL to the DelyvaX label API
    $label_url = 'https://api.delyva.app/v1.0/order/' . urlencode($order_ids) . '/label';
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('Print DelyvaX Labels', 'delyvax'); ?></h1>
        <hr class="wp-header-end">

        <div id="popup-blocked-notice" class="notice notice-warning inline" style="display: none;">
            <p>
                <strong><?php _e('Pop-up Blocked', 'delyvax'); ?></strong>
                <br>
                <?php _e('Your browser blocked the automatic download. Please allow pop-ups for this site or click the download button below.', 'delyvax'); ?>
            </p>
        </div>

        <div class="postbox">
            <div class="inside">
                <div class="notice notice-info inline">
                    <p><?php _e('Your labels are being prepared. If the download doesn\'t start automatically, please click the button below:', 'delyvax'); ?></p>
                </div>

                <a href="<?php echo esc_url($label_url); ?>" class="button button-primary button-hero" id="downloadButton" target="_blank">
                    <?php _e('Download Labels', 'delyvax'); ?>
                </a>
            </div>
        </div>

        <a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order')); ?>" class="button button-secondary">
            <?php _e('← Return to Orders', 'delyvax'); ?>
        </a>
    </div>
    <?php
    // Ensure jQuery is loaded
    wp_enqueue_script('jquery');
    ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var downloadButton = document.getElementById('downloadButton');
            if (downloadButton) {
                // Try to open in new window
                var popupWindow = window.open(downloadButton.href, '_blank');
                
                // Check if popup was blocked
                setTimeout(function() {
                    if (!popupWindow || popupWindow.closed || typeof popupWindow.closed == 'undefined') {
                        // Show the popup blocked notice
                        document.getElementById('popup-blocked-notice').style.display = 'block';
                    }
                }, 100);
            }
        });
    </script>
    <?php
}

// Show admin notices for errors
function delyvax_admin_notices() {
    if (isset($_GET['delyvax_print_error'])) {
        $error_type = sanitize_text_field($_GET['delyvax_print_error']);
        
        switch ($error_type) {
            case 'no_orders':
                $message = __('No DelyvaX order IDs found in the selected orders. Make sure the orders have been processed with DelyvaX.', 'delyvax');
                break;
            case 'api_error':
                $message = isset($_GET['error_message']) 
                    ? sanitize_text_field(urldecode($_GET['error_message']))
                    : __('An error occurred while fetching labels from DelyvaX.', 'delyvax');
                break;
            default:
                $message = __('An error occurred while processing DelyvaX labels.', 'delyvax');
                break;
        }
        
        ?>
        <div class="notice notice-error is-dismissible">
            <p><strong><?php _e('DelyvaX Labels Error', 'delyvax'); ?></strong></p>
            <p><?php echo $message; ?></p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'delyvax_admin_notices');

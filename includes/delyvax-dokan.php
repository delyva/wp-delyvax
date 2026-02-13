<?php
/**
 * DelyvaX Dokan Integration
 *
 * Adds Print Label action button and tracking number display
 * to the Dokan Vendor Dashboard.
 *
 * @package Delyva
 */

defined( 'ABSPATH' ) or die();

/**
 * Register Dokan hooks only when Dokan is active.
 */
function delyvax_dokan_init() {
    if ( ! class_exists( 'WeDevs_Dokan' ) ) {
        return;
    }

    // Orders listing: Print Label action button + Track column
    add_filter( 'woocommerce_admin_order_actions', 'delyvax_dokan_add_print_label_action', 10, 2 );
    add_action( 'dokan_order_listing_header_before_action_column', 'delyvax_dokan_track_column_header' );
    add_action( 'dokan_order_listing_row_before_action_field', 'delyvax_dokan_track_column_content' );
    add_action( 'wp_footer', 'delyvax_dokan_print_label_new_tab' );
}
add_action( 'init', 'delyvax_dokan_init' );

/**
 * Add Print Label action button to the Dokan vendor dashboard order actions.
 * Only shows when tracking code exists and order is not completed (same as admin).
 *
 * @param array    $actions Existing order actions.
 * @param WC_Order $order   The order object.
 * @return array Modified actions.
 */
function delyvax_dokan_add_print_label_action( $actions, $order ) {
    if ( ! function_exists( 'dokan_is_seller_dashboard' ) || ! dokan_is_seller_dashboard() ) {
        return $actions;
    }

    $delyvax_tracking_code = $order->get_meta( 'DelyvaXTrackingCode' );
    $delyvax_order_id      = $order->get_meta( 'DelyvaXOrderID' );

    if ( ! $delyvax_tracking_code || ! $delyvax_order_id || $order->has_status( array( 'completed' ) ) ) {
        return $actions;
    }

    $label_url = 'https://api.delyva.app/v1.0/order/' . urlencode( $delyvax_order_id ) . '/label';

    $actions['delyvax_print_label'] = array(
        'url'    => $label_url,
        'name'   => __( 'Print Label', 'delyvax' ),
        'action' => 'delyvax-print-label',
        'icon'   => '<i class="fas fa-shipping-fast">&nbsp;</i>',
    );

    return $actions;
}

/**
 * Make Print Label buttons open in a new tab.
 */
function delyvax_dokan_print_label_new_tab() {
    if ( ! function_exists( 'dokan_is_seller_dashboard' ) || ! dokan_is_seller_dashboard() ) {
        return;
    }
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('a[href*="api.delyva.app"][href*="/label"]').forEach(function(link) {
                link.setAttribute('target', '_blank');
            });
        });
    </script>
    <?php
}

/**
 * Render "Track" column header in the Dokan vendor dashboard order listing.
 */
function delyvax_dokan_track_column_header() {
    echo '<th>' . esc_html__( 'Track', 'delyvax' ) . '</th>';
}

/**
 * Render Track column content for each order in Dokan vendor dashboard.
 *
 * @param WC_Order $order The order object.
 */
function delyvax_dokan_track_column_content( $order ) {
    $delyvax_tracking_code = $order->get_meta( 'DelyvaXTrackingCode' );

    if ( ! $delyvax_tracking_code ) {
        echo '<td></td>';
        return;
    }

    $settings     = get_option( 'woocommerce_delyvax_settings' );
    $company_code = ( $settings && isset( $settings['company_code'] ) ) ? $settings['company_code'] : '';

    echo '<td>';

    if ( $company_code ) {
        $tracking_url = 'https://' . $company_code . '.delyva.app/customer/strack?trackingNo=' . $delyvax_tracking_code;
        echo '<a href="' . esc_url( $tracking_url ) . '" target="_blank">' . esc_html( $delyvax_tracking_code ) . '</a>';
    } else {
        echo esc_html( $delyvax_tracking_code );
    }

    echo '</td>';
}

<?php
/**
 * DelyvaX WCFM Integration
 *
 * Adds Print Label action button and tracking number display
 * to the WCFM Vendor Dashboard.
 *
 * @package Delyva
 */

defined( 'ABSPATH' ) or die();

/**
 * Register WCFM hooks only when WCFM is active.
 */
function delyvax_wcfm_init() {
    if ( ! class_exists( 'WCFM' ) ) {
        return;
    }

    // Orders listing: Print Label action button (supports both base WCFM and WCFMmp Marketplace)
    add_filter( 'wcfm_orders_actions', 'delyvax_wcfm_add_print_label_action', 10, 3 );
    add_filter( 'wcfmmarketplace_orders_actions', 'delyvax_wcfmmp_add_print_label_action', 10, 5 );

    // Orders listing: Track column
    add_filter( 'wcfm_orders_additional_info_column_label', 'delyvax_wcfm_track_column_label' );
    add_filter( 'wcfm_orders_additonal_data_hidden', 'delyvax_wcfm_show_track_column' );
    add_filter( 'wcfm_orders_additonal_data', 'delyvax_wcfm_track_column_data', 10, 2 );
}
add_action( 'init', 'delyvax_wcfm_init' );

/**
 * Build Print Label action HTML for an order.
 *
 * @param WC_Order $order The order object.
 * @return string Action HTML or empty string.
 */
function delyvax_wcfm_print_label_html( $order ) {
    $delyvax_tracking_code = $order->get_meta( 'DelyvaXTrackingCode' );
    $delyvax_order_id      = $order->get_meta( 'DelyvaXOrderID' );

    if ( ! $delyvax_tracking_code || ! $delyvax_order_id || $order->has_status( array( 'completed' ) ) ) {
        return '';
    }

    $label_url = 'https://api.delyva.app/v1.0/order/' . urlencode( $delyvax_order_id ) . '/label';

    return '<a class="wcfm-action-icon" href="' . esc_url( $label_url ) . '" target="_blank">'
        . '<span class="fa fa-print text_tip" data-tip="' . esc_attr__( 'Print Label', 'delyvax' ) . '"></span>'
        . '</a>';
}

/**
 * Add Print Label action button — base WCFM (non-marketplace).
 *
 * @param string   $actions    Existing order actions HTML string.
 * @param object   $order_post Order post object.
 * @param WC_Order $the_order  The order object.
 * @return string Modified actions HTML.
 */
function delyvax_wcfm_add_print_label_action( $actions, $order_post, $the_order ) {
    $actions .= delyvax_wcfm_print_label_html( $the_order );
    return $actions;
}

/**
 * Add Print Label action button — WCFMmp Marketplace.
 *
 * @param string   $actions   Existing order actions HTML string.
 * @param int      $vendor_id Vendor user ID.
 * @param object   $order     Order post object.
 * @param WC_Order $the_order The order object.
 * @param int      $vendor_id_alt Vendor ID (duplicate param from controller).
 * @return string Modified actions HTML.
 */
function delyvax_wcfmmp_add_print_label_action( $actions, $vendor_id, $order, $the_order, $vendor_id_alt ) {
    $actions .= delyvax_wcfm_print_label_html( $the_order );
    return $actions;
}

/**
 * Set the additional info column label to "Track".
 *
 * @param string $label Column label.
 * @return string Modified label.
 */
function delyvax_wcfm_track_column_label( $label ) {
    return __( 'Track', 'delyvax' );
}

/**
 * Show the additional data column (not hidden).
 *
 * @param bool $hidden Whether the column is hidden.
 * @return bool Always false to show the column.
 */
function delyvax_wcfm_show_track_column( $hidden ) {
    return false;
}

/**
 * Render tracking number as a clickable link in the Track column.
 *
 * @param string $data     Existing column data.
 * @param int    $order_id The order ID.
 * @return string Modified column data.
 */
function delyvax_wcfm_track_column_data( $data, $order_id ) {
    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        return $data;
    }

    $delyvax_tracking_code = $order->get_meta( 'DelyvaXTrackingCode' );

    if ( ! $delyvax_tracking_code ) {
        return $data;
    }

    $settings     = get_option( 'woocommerce_delyvax_settings' );
    $company_code = ( $settings && isset( $settings['company_code'] ) ) ? $settings['company_code'] : '';

    if ( $company_code ) {
        $tracking_url = 'https://' . $company_code . '.delyva.app/customer/strack?trackingNo=' . $delyvax_tracking_code;
        $data = '<a href="' . esc_url( $tracking_url ) . '" target="_blank">' . esc_html( $delyvax_tracking_code ) . '</a>';
    } else {
        $data = esc_html( $delyvax_tracking_code );
    }

    return $data;
}

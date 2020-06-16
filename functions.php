<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
register_activation_hook('delyvax/delyvax.php', 'delyvaxPluginActivated');
register_deactivation_hook('delyvax/delyvax.php', 'delyvaxPluginDeActivated' );
register_uninstall_hook('delyvax/delyvax.php', 'delyvaxPluginUninstalled');

add_filter('parse_request', 'delyvaxRequest');

add_action('woocommerce_check_cart_items','delyvax_check_cart_weight');
add_action('woocommerce_checkout_before_customer_details', 'delyvax_check_checkout_weight');

add_action( 'woocommerce_payment_complete', 'delyvax_payment_complete' );
add_action( 'woocommerce_order_status_changed', 'delyvax_order_confirmed', 10, 3 );

add_action( 'widgets_init', 'delyvax_webhook_subscribe' );
add_action( 'woocommerce_after_register_post_type', 'delyvax_webhook_get_tracking' );


function delyvax_check_cart_weight(){
    $weight = WC()->cart->get_cart_contents_weight();
    $min_weight = 0.1; // kg
    $max_weight = 10000; // kg

    if($weight < $min_weight){
        wc_add_notice( sprintf( __( 'You have %s kg weight and we allow minimum ' . $min_weight . '  kg of weight per order. Increase the cart weight by adding more items to proceed checkout.', 'woocommerce' ), $weight ), 'error' );
    }

    if($weight > $max_weight){
        wc_add_notice( sprintf( __( 'You have %s kg weight and we allow maximum' . $max_weight . '  kg of weight per order. Reduce the cart weight by removing some items to proceed checkout.', 'woocommerce' ), $weight ), 'error' );
    }
}

function  delyvax_check_checkout_weight() {
    $weight = WC()->cart->get_cart_contents_weight();
    $min_weight = 0.1; // kg
    $max_weight = 10000; // kg

    if($weight < $min_weight) {
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }

    if($weight > $max_weight) {
        wp_safe_redirect( wc_get_cart_url() );
        exit;
    }
}

function delyvaxPluginActivated() {

}

function delyvaxPluginDeActivated() {
  // delete_option('delyvax_pricing_enable');
  // delete_option('delyvax_create_shipment_on_paid');
  // delete_option('delyvax_create_shipment_on_confirm');
  // delete_option('delyvax_change_order_status');
  // delete_option('delyvax_company_id');
  // delete_option('delyvax_user_id');
  // delete_option('delyvax_customer_id');
  // delete_option('delyvax_api_token');
  // delete_option('delyvax_api_webhook_enable');
  // delete_option('delyvax_api_webhook_key');
  // delete_option('wc_settings_delyvax_shipping_rate_adjustment');
  // delete_option('delyvax_rate_adjustment_percentage');
  // delete_option('delyvax_rate_adjustment_flat');
}

function delyvaxPluginUninstalled() {
    delete_option('delyvax_pricing_enable');
    delete_option('delyvax_create_shipment_on_paid');
    delete_option('delyvax_create_shipment_on_confirm');
    delete_option('delyvax_change_order_status');
    delete_option('delyvax_company_id');
    delete_option('delyvax_user_id');
    delete_option('delyvax_customer_id');
    delete_option('delyvax_api_token');
    delete_option('delyvax_api_webhook_enable');
    delete_option('delyvax_api_webhook_key');
    delete_option('wc_settings_delyvax_shipping_rate_adjustment');
    delete_option('delyvax_rate_adjustment_percentage');
    delete_option('delyvax_rate_adjustment_flat');
}

function delyvaxRequest() {
    if (isset($_GET['delyvax'])) {
        if ($_GET['delyvax'] == 'plugin_check') {
            header('Content-Type: application/json');

            die(json_encode([
                'url' => get_home_url(),
                'version' => DELYVAX_PLUGIN_VERSION,
            ], JSON_UNESCAPED_SLASHES));
        }
    }
}


function delyvax_payment_complete( $order_id ){
    $settings = get_option( 'woocommerce_delyvax_settings' );

    if ($settings['create_shipment_on_paid'] == 'yes')
    {
        $order = wc_get_order( $order_id );
        $user = $order->get_user();

        //check sub orders
        $sub_orders = get_children( array( 'post_parent' => $order_id, 'post_type' => 'shop_order' ) );

        if ( $sub_orders ) {
            $proceed_create_order = true;

            foreach ($sub_orders as $sub)
            {
                $sub_order = wc_get_order($sub->ID);
                // echo '<pre>'.$sub_order.'</pre>';

                $sub_order_status = $sub_order->get_status();

                $seller_id = dokan_get_seller_id_by_order($sub->ID);
                $store_info = dokan_get_store_info( $seller_id );
                // echo '<pre>'.print_r($store_info).'</pre>';

                if($sub_order_status != 'preparing' && $sub_order_status != 'cancelled' )
                {
                    $proceed_create_order = false;
                }
            }

            if($proceed_create_order)
            {
                delyvax_create_order($order, $user);
            }
        }else {
            if($order->get_status() == 'preparing') //$order->get_status() == 'cancelled'
            {
                delyvax_create_order($order, $user);
            }
        }
    }
}

function delyvax_order_confirmed( $order_id, $old_status, $new_status ) {
    // make action magic happen here...
    $settings = get_option( 'woocommerce_delyvax_settings' );

    if ($settings['create_shipment_on_confirm'] == 'yes')
    {
        $order = wc_get_order( $order_id );
        $user = $order->get_user();

        //check sub orders
        $sub_orders = get_children( array( 'post_parent' => $order_id, 'post_type' => 'shop_order' ) );

        if ( $sub_orders ) {
            $proceed_create_order = true;

            foreach ($sub_orders as $sub)
            {
                $sub_order = wc_get_order($sub->ID);
                // echo '<pre>'.$sub_order.'</pre>';

                $sub_order_status = $sub_order->get_status();

                $seller_id = dokan_get_seller_id_by_order($sub->ID);
                $store_info = dokan_get_store_info( $seller_id );
                // echo '<pre>'.print_r($store_info).'</pre>';

                if($sub_order_status != 'preparing' && $sub_order_status != 'cancelled' )
                {
                    $proceed_create_order = false;
                }
            }

            if($proceed_create_order)
            {
                delyvax_create_order($order, $user);
            }
        }else {
            if($order->get_status() == 'preparing') //$order->get_status() == 'cancelled'
            {
                delyvax_create_order($order, $user);
            }
        }
    }
}


function delyvax_create_order($order, $user) {
    try {
        //create order
        //start DelyvaX API
        if (!class_exists('DelyvaX_Shipping_API')) {
            include_once 'includes/delyvax-api.php';
        }

        $DelyvaXOrderID = $order->get_meta( 'DelyvaXOrderID' );
        $DelyvaXTrackingCode = $order->get_meta( 'DelyvaXTrackingCode' );

        if($DelyvaXOrderID == null && $DelyvaXTrackingCode == null)
        {
            $resultCreate = DelyvaX_Shipping_API::postCreateOrder($order, $user);

            if($resultCreate)
            {
                  $shipmentId = $resultCreate["id"];

                  $resultProcess = DelyvaX_Shipping_API::postProcessOrder($order, $user, $shipmentId);

                  if($resultProcess)
                  {
                      $trackingNo = $resultProcess["consignmentNo"];

                      //save tracking no into order to all parent order and suborders
                      if($order->parent_id)
                      {
                          $main_order = wc_get_order($order->parent_id);

                          $main_order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
                          $main_order->update_meta_data( 'DelyvaXTrackingCode', $trackingNo );
                          $main_order->save();

                          // update $sub_orders
                          $sub_orders = get_children( array( 'post_parent' => $order_id, 'post_type' => 'shop_order' ) );

                          if ( $sub_orders ) {
                              $proceed_create_order = true;

                              foreach ($sub_orders as $sub)
                              {
                                  $sub_order = wc_get_order($sub->ID);

                                  $sub_order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
                                  $sub_order->update_meta_data( 'DelyvaXTrackingCode', $trackingNo );
                                  $sub_order->save();
                              }
                          }
                          //
                      }else {
                          $main_order = $order;

                          $main_order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
                          $main_order->update_meta_data( 'DelyvaXTrackingCode', $trackingNo );
                          $main_order->save();
                      }
                      ///
                  }
            }
        }
        // exit;
    } catch (Exception $e) {
        print_r($e);
        // exit;
    }
}

//rewire logic here, API is only for post
function delyvax_post_create_order($order, $user) {

}

//rewire logic here, API is only for post
function delyvax_post_process_order($order, $user, $shipmentId) {

}

//subscribe to webhook here or when save the settings ?
function delyvax_webhook_subscribe() {
    $settings = get_option( 'woocommerce_delyvax_settings' );

    if ($settings['api_webhook_enable'] == 'yes') {

        //subscribe to webhook
        //start DelyvaX API
        if (!class_exists('DelyvaX_Shipping_API')) {
            include_once 'includes/delyvax-api.php';
        }
        try {
            if (strlen(get_option( 'delyvax_api_webhook_created_id' )) == 0 )
            {
                $result = DelyvaX_Shipping_API::postCreateWebhook("order.created");
                if($result["status"] == 1)
                {
                    //Save api_webhook_key
                    update_option( 'delyvax_api_webhook_created_id', $result["id"]);
                }
            }
            if (strlen(get_option( 'delyvax_api_webhook_failed_id' )) == 0 )
            {
                $result = DelyvaX_Shipping_API::postCreateWebhook("order.failed");
                if($result["status"] == 1)
                {
                    //Save api_webhook_key
                    update_option( 'delyvax_api_webhook_failed_id', $result["id"]);
                }
            }
            if (strlen(get_option( 'delyvax_api_webhook_updated_id' )) == 0 )
            {
                $result = DelyvaX_Shipping_API::postCreateWebhook("order.updated");
                if($result["status"] == 1)
                {
                    //Save api_webhook_key
                    update_option( 'delyvax_api_webhook_updated_id', $result["id"]);
                }
            }
            if (strlen(get_option( 'delyvax_api_webhook_id' )) == 0 ) {
                $result = DelyvaX_Shipping_API::postCreateWebhook("order_tracking.update");

                if($result["status"] == 1)
                {
                    //Save api_webhook_key
                    update_option( 'delyvax_api_webhook_id', $result["id"]);
                }
            }
        } catch (Exception $e) {

        }
    }
}

function delyvax_webhook_get_tracking()
{
    $raw = file_get_contents('php://input');
    // var_dump($raw);
    // throw new Exception();

    if($raw)
    {
        $json = json_decode($raw, true);

        if( isset($json) )
        {
            // $data = $json["data"];
            $data = $json;

            if( isset($data['orderId']) && isset($data['consignmentNo']) && isset($data['statusCode']) )
            {
                $settings = get_option( 'woocommerce_delyvax_settings' );

                if ($settings['change_order_status'] == 'yes') {

                      //get order id by tracking no
                      //order_tracking.update"
                      $companyId = $data['companyId'];
                      $shipmentId = $data['orderId'];
                      $consignmentNo = $data['consignmentNo'];
                      echo $statusCode = $data['statusCode'];

                      global $woocommerce;

                      ///find order_id by $shipmentId
                      $orders = wc_get_orders( array(
                          // 'limit'        => -1, // Query all orders
                          // 'orderby'      => 'date',
                          // 'order'        => 'DESC',
                          'meta_key'     => 'DelyvaXOrderID', // The postmeta key field
                          'meta_value' => $shipmentId, // The comparison argument
                      ));

                      for($i=0; $i < sizeof($orders); $i++)
                      {
                          $order = new WC_Order($orders[$i]->get_id());

                          echo 'order_id'.$orders[$i]->get_id();
                          echo 'status'.$order->get_status();

                          // if($statusCode == 100)
                          // {
                          //     //SaveTrackingNo($order_id, $consignmentNo);
                          //     if (!empty($order))
                          //     {
                          //         if( ! $order->has_status('preparing') )
                          //         {
                          //             echo 'preparing';
                          //             // $order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
                          //             // $order->update_meta_data( 'DelyvaXTrackingCode', $consignmentNo );
                          //             $order->set_status('preparing', 'Order status changed to Preparing', true); // order note is optional, if you want to  add a note to order
                          //             // $order->save();
                          //         }
                          //     }
                          // }
                          // if ( strtolower( $order->get_status() ) == 'pending' ) {
              								// $order->payment_complete();
                              //
              								// $order->add_order_note( 'Payment completed.');
                              // echo $order->get_status();
            							// }
                          if($statusCode == 200)
                          {
                              if (!empty($order))
                              {
                                  //on the way to pick up
                                  if( !$order->has_status('courier-accepted') )
                                  {
                                      echo 'courier-accepted';
                                      // $order->set_status('collected', 'Order status changed to Collected.', true); // order note is optional, if you want to  add a note to order
                                      // $order->save();

                                      $order->update_status('courier-accepted', 'Order status changed to Courier accepted.', true); // order note is optional, if you want to  add a note to order
                                      // $order->add_order_note( 'Order status changed to Driver On the way to the restaurant' );
                                  }
                              }
                          }else if($statusCode == 400)
                          {
                              if (!empty($order))
                              {
                                  //on the way to pick up
                                  if( !$order->has_status('start-collecting') )
                                  {
                                      echo 'start-collecting';
                                      // $order->set_status('collected', 'Order status changed to Collected.', true); // order note is optional, if you want to  add a note to order
                                      // $order->save();

                                      $order->update_status('start-collecting', 'Order status changed to Pending pick up.', true); // order note is optional, if you want to  add a note to order
                                      // $order->add_order_note( 'Order status changed to Driver On the way to the restaurant' );
                                  }
                              }
                          }else if($statusCode == 475)
                          {
                              if (!empty($order))
                              {
                                  //on the way to pick up
                                  if( !$order->has_status('failed-collection') )
                                  {
                                      echo 'failed-collection';
                                      // $order->set_status('collected', 'Order status changed to Collected.', true); // order note is optional, if you want to  add a note to order
                                      // $order->save();

                                      $order->update_status('failed-collection', 'Order status changed to Pick up failed.', true); // order note is optional, if you want to  add a note to order
                                      // $order->add_order_note( 'Order status changed to Driver On the way to the restaurant' );
                                  }
                              }
                          }else if($statusCode == 500) // }else if($statusCode == 500)
                          {
                              if (!empty($order))
                              {
                                  //on the way to pick up
                                  if( !$order->has_status('collected') )
                                  {
                                      echo 'collected';
                                      // $order->set_status('collected', 'Order status changed to Collected.', true); // order note is optional, if you want to  add a note to order
                                      // $order->save();

                                      $order->update_status('collected', 'Order status changed to Pick up complete.', true); // order note is optional, if you want to  add a note to order
                                      // $order->add_order_note( 'Order status changed to start_collecting' );
                                  }
                              }
                          }else if($statusCode == 600)
                          {
                              if (!empty($order))
                              {
                                  //on the way to pick up
                                  if( !$order->has_status('start-delivery') )
                                  {
                                      echo 'start-delivery';
                                      // $order->set_status('collected', 'Order status changed to Collected.', true); // order note is optional, if you want to  add a note to order
                                      // $order->save();

                                      $order->update_status('start-delivery', 'Order status changed to On the way for delivery.', true); // order note is optional, if you want to  add a note to order
                                      // $order->add_order_note( 'Order status changed to Driver On the way to the restaurant' );
                                  }
                              }
                          }else if($statusCode == 650)
                          {
                              if (!empty($order))
                              {
                                  //on the way to pick up
                                  if( !$order->has_status('failed-delivery') )
                                  {
                                      echo 'failed-delivery';
                                      // $order->set_status('collected', 'Order status changed to Collected.', true); // order note is optional, if you want to  add a note to order
                                      // $order->save();

                                      $order->update_status('failed-delivery', 'Order status changed to Delivery failed.', true); // order note is optional, if you want to  add a note to order
                                      // $order->add_order_note( 'Order status changed to Driver On the way to the restaurant' );
                                  }
                              }
                          }else if($statusCode == 700 || $statusCode == 1000)
                          {
                              if (!empty($order))
                              {
                                  if( !$order->has_status('completed') )
                                  {
                                      echo 'completed';
                                      // $order->set_status('completed', 'Order status changed to Completed', true); // order note is optional, if you want to  add a note to order
                                      // $order->save();

                                      $order->update_status('completed', 'Order status changed to Completed', true); // order note is optional, if you want to  add a note to order
                                      // $order->add_order_note( 'Order status changed to Completed' );
                                  }
                              }
                          }else if($statusCode == 900)
                          {
                              if (!empty($order))
                              {
                                  //on the way to pick up
                                  if( !$order->has_status('cancelled') )
                                  {
                                      echo 'cancelled';
                                      // $order->set_status('collected', 'Order status changed to Collected.', true); // order note is optional, if you want to  add a note to order
                                      // $order->save();

                                      $order->update_status('cancelled', 'Order status changed to Cancelled.', true); // order note is optional, if you want to  add a note to order
                                      // $order->add_order_note( 'Order status changed to Driver On the way to the restaurant' );
                                  }
                              }
                          }else
                          {
                              // $order->update_status('cancelled', 'Order status changed to Cancelled', true); // order note is optional, if you want to  add a note to order
                              echo 'else';
                          }
                          echo $order->get_status();
                          var_dump($raw);
                          // throw new Exception();
                      }
                }
            }
        }
    }
}


// Register new status
function delyvax_register_order_statuses() {
    register_post_status( 'preparing', array(
        'label'                     => _x('Preparing', 'woocommerce' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Preparing (%s)', 'Preparing (%s)' )
    ) );
    register_post_status( 'ready-to-collect', array(
        'label'                     => _x('Ready to collect', 'woocommerce' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Ready to collect (%s)', 'Ready to collect (%s)' )
    ) );
    register_post_status( 'courier-accepted', array(
        'label'                     => _x('Courier accepted', 'woocommerce' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Courier accepted (%s)', 'Courier accepted (%s)' )
        // 'label_count'               => _n_noop( 'Courier accepted class="count">(%s)</span>', 'Courier accepted <span class="count">(%s)</span>' )
    ) );
    register_post_status( 'start-collecting', array(
        'label'                     => _x('Pending pick up', 'woocommerce' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Pending pick up (%s)', 'Pending pick up (%s)' )
    ) );
    register_post_status( 'collected', array(
        'label'                     => _x('Pick up complete', 'woocommerce' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Pick up complete (%s)', 'Pick up complete (%s)' )
    ) );
    register_post_status( 'failed-collection', array(
        'label'                     => _x('Pick up failed', 'woocommerce' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Pick up failed (%s)', 'Pick up failed (%s)' )
    ) );
    register_post_status( 'start-delivery', array(
        'label'                     => _x('On the way for delivery', 'woocommerce' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'On the way for delivery (%s)', 'On the way for delivery (%s)' )
    ) );
    register_post_status( 'failed-delivery', array(
        'label'                     => _x('Delivery failed', 'woocommerce' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Delivery failed (%s)', 'Delivery failed (%s)' )
    ) );
    register_post_status( 'request-refund', array(
        'label'                     => _x('Request refund', 'woocommerce' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Request refund (%s)', 'Request refund (%s)' )
    ) );
}
add_action( 'init', 'delyvax_register_order_statuses' );


add_filter( 'woocommerce_reports_order_statuses', 'include_custom_order_status_to_reports', 20, 1 );
function include_custom_order_status_to_reports( $statuses ){
    // Adding the custom order status to the 3 default woocommerce order statuses
    return array( 'preparing', 'ready-to-collect', 'courier-accepted',
      'start-collecting', 'collected', 'failed-collection',
      'start-delivery', 'failed-delivery', 'request-refund'
    );
}

// Add to list of WC Order statuses
// Add custom status to order edit page drop down (and displaying orders with this custom status in the list)
function delyvax_add_to_order_statuses( $order_statuses ) {

    $new_order_statuses = array();

    // add new order status after processing
    foreach ( $order_statuses as $key => $status ) {
        $new_order_statuses[ $key ] = $status;

        if ( 'wc-processing' === $key ) {
            $new_order_statuses['wc-preparing'] = _x('Preparing', 'Order status', 'woocommerce' );
            $new_order_statuses['wc-ready-to-collect'] = _x('Ready to collect', 'Order status', 'woocommerce' );
            $new_order_statuses['wc-courier-accepted'] = _x('Courier accepted', 'Order status', 'woocommerce' );
            $new_order_statuses['wc-start-collecting'] = _x('Pending pick up', 'Order status', 'woocommerce' );
            $new_order_statuses['wc-collected'] = _x('Pick up complete', 'Order status', 'woocommerce' );
            $new_order_statuses['wc-failed-collection'] = _x('Pick up failed', 'Order status', 'woocommerce' );
            $new_order_statuses['wc-start-delivery'] = _x('On the way for delivery', 'Order status', 'woocommerce' );
            $new_order_statuses['wc-failed-delivery'] = _x('Delivery failed', 'Order status', 'woocommerce' );
            $new_order_statuses['wc-request-refund'] = _x('Request refund', 'Order status', 'woocommerce' );
        }
    }

    return $new_order_statuses;
}
add_filter( 'wc_order_statuses', 'delyvax_add_to_order_statuses' );
//

// Adding custom status  to admin order list bulk actions dropdown
function delyvax_dropdown_bulk_actions_shop_order( $actions ) {
    $new_actions = array();

    // add new order status before processing
    foreach ($actions as $key => $action) {
        $new_actions[$key] = $action;

        if ('mark_processing' === $key)
        {
            $new_actions['mark_preparing'] = __( 'Mark as Preparing', 'woocommerce' );
            $new_actions['mark_ready-to-collect'] = __( 'Mark as Ready to collect', 'woocommerce' );
            $new_actions['mark_courier-accepted'] = __( 'Mark as Courier accepted', 'woocommerce' );
            $new_actions['mark_start-collecting'] = __( 'Mark as Pending pick up', 'woocommerce' );
            $new_actions['mark_collected'] = __( 'Mark as Pick up complete', 'woocommerce' );
            $new_actions['mark_failed-collection'] = __( 'Mark as Pick up failed', 'woocommerce' );
            $new_actions['mark_start-delivery'] = __( 'Mark as On the way for delivery', 'woocommerce' );
            $new_actions['mark_failed-delivery'] = __( 'Mark as Delivery failed', 'woocommerce' );
            $new_actions['mark_request-refund'] = __( 'Mark as Request refund', 'woocommerce' );
        }
    }

    return $new_actions;
}
add_filter( 'bulk_actions-edit-shop_order', 'delyvax_dropdown_bulk_actions_shop_order', 20, 1 );


/*
function delyvax_woocommerce_exclude_order_status( $query_vars )
{
      global $typenow;

      // Using wc_get_order_types() instead of 'shop_order' as other order types could be added by other plugins
      if ( in_array( $typenow, wc_get_order_types( 'order-meta-boxes' ), true ) )
      {
          if ( isset( $_GET['exclude_status'] ) && '' != $_GET['exclude_status'] && isset( $query_vars['post_status'] ) )
          {
              $exclude_status = explode( ',', $_GET['exclude_status'] );
              foreach ( $exclude_status as $key => $value )
              {
                  if ( ( $key = array_search( $value, $query_vars['post_status'] ) ) !== false)
                  {
                      unset( $query_vars['post_status'][$key] );
                  }
              }
          }
      }
      return $query_vars;
}
add_filter( 'request', 'delyvax_woocommerce_exclude_order_status', 20, 1 );
*/

/*
{ data:
   { companyId: 'e44c7375-c4dc-47e9-8b24-70a28e024a83',
     orderId: 'b21a5f81-c68c-49b2-90e9-19d1148a28db',
     customerId: 18,
     userId: 'd50d1780-aabc-11ea-8557-fb3ba8b0c74b',
     consignmentNo: 'MDH00000007MY',
     statusCode: 625,
     statusText: null,
     description: 'DROPOFF arrived, WP2/2',
     location: '-',
     driverId: 4,
     taskId: 531,
     id: 3833,
     createdAt: '2020-06-12T08:59:51.743Z',
     coord: { lon: 101.7650304, lat: 3.2156424 },
     personnel:
      { name: '',
        phone: '',
        vehicleName: '',
        vehicleType: '',
        vehicleRegNo: '' } },
  apiSecret: '53dc2e95-75f6-4ac6-8eb6-9f7ff52b38a2',
  event: 'order_tracking.update',
  url: 'https://matdespatch.com/my/makan'
}
*/

// function SaveShipmentId( $order_id, $shipmentId ) {
//     if (!class_exists('DelyvaX_Shipping_API')) {
//         include_once 'includes/delyvax-api.php';
//     }
//     try {
//         //save tracking no into order and suborders
//         $order = wc_get_order ( $order_id );
//       	if ( $order ) {
//             global $wpdb;
//             $TableName = $wpdb->prefix . "posts";
//             $wpdb->update( $TableName,
//                 array(
//                     'ShipmentId' => $shipmentId,
//                     ),
//                 array( 'id' => $order_id )
//             );
//       	}
//     } catch (Exception $e) {
//
//     }
// }

/*
function SaveTrackingNo( $order_id, $trackingNo ) {
    try {
        //save tracking no into order and suborders
        $order = wc_get_order ( $order_id );
      	if ( $order ) {
            global $wpdb;
            $TableName = $wpdb->prefix . "posts";
            $wpdb->update( $TableName,
                array(
                    'TrackingCode' => $trackingNo,
                    ),
                array( 'id' => $order_id )
            );
      	}
    } catch (Exception $e) {

    }
}

function SaveOrderTrackingNo( $order_id, $shipmentId ) {
    if (!class_exists('DelyvaX_Shipping_API')) {
        include_once 'includes/delyvax-api.php';
    }
    try {
        $result = DelyvaX_Shipping_API::getTrackOrderByOrderId($shipmentId);

        $consignmentNo = $result["consignmentNo"];

        //save tracking no into order and suborders
        $order = wc_get_order ( $order_id );
      	if ( $order ) {
            $TrackingCode = $consignmentNo;

            global $wpdb;
            $TableName = $wpdb->prefix . "posts";
            $wpdb->update( $TableName,
                array(
                    'TrackingCode' => $TrackingCode,
                    ),
                array( 'id' => $order_id )
            );
      	}
    } catch (Exception $e) {

    }
}
*/

/*
function getOrderIdByShipmentId( $shipmentId) {
    $order_id = null;

    global $wpdb;
    $TableName = $wpdb->prefix . "posts";
    $result = $wpdb->get_results("SELECT * FROM $TableName WHERE id = 1");

    $wpdb->update( $TableName,
        array(
            'TrackingCode' => $trackingNo,
            ),
        array( 'id' => $order_id )
    );

    return $order_id;
}
*/

<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!');
add_action( 'woocommerce_update_options', 'delyvax_woocommerce_update_options', 10, 1 );
add_action( 'woocommerce_after_register_post_type', 'delyvax_webhook_order_created');
add_action( 'woocommerce_after_register_post_type', 'delyvax_webhook_get_tracking');

// check for duplicate, fix old url
function delyvax_webhook_duplicate_check() {
  if (!class_exists('DelyvaX_Shipping_API')) {
    include_once 'delyvax-api.php';
  }

  $old_url = get_site_url()."/";
  $valid_url = get_site_url()."/?delyvax=webhook";

  try {
    $webhooks = DelyvaX_Shipping_API::getWebhook();
    $available = [];

    for ($i=0; $i < sizeof($webhooks); $i++) {
      $wh = $webhooks[$i];
      if (array_key_exists($wh['event'], $available) && $wh['url'] === $valid_url) {
        DelyvaX_Shipping_API::deleteWebhook($wh['id']);
      } else if ($wh['url'] === $old_url) {
        DelyvaX_Shipping_API::updateWebhookUrl($wh['id']);
      } else if ($wh['url'] === $valid_url) {
        $available[$wh['event']] = $wh['url'];
      }
    }
  } catch (Exception $e) {

  }
}

function delyvax_webhook_unsubscribe() {
  if (!class_exists('DelyvaX_Shipping_API')) {
    include_once 'delyvax-api.php';
  }

  $valid_url = [
    get_site_url()."/",
    get_site_url()."/?delyvax=webhook",
  ];

  try {
    $webhooks = DelyvaX_Shipping_API::getWebhook();

    for ($i=0; $i < sizeof($webhooks); $i++) {
      $wh = $webhooks[$i];
      // only delete webhook related to this store
      if (in_array($wh['url'], $valid_url)) {
        DelyvaX_Shipping_API::deleteWebhook($wh['id']);
      }
    }
  } catch (Exception $e) {

  }
}

function delyvax_webhook_subscribe() {
  if (!class_exists('DelyvaX_Shipping_API')) {
    include_once 'delyvax-api.php';
  }
  $settings = get_option( 'woocommerce_delyvax_settings');

  $valid_url = get_site_url()."/?delyvax=webhook";
  $needed_event = ['order_tracking.update','order.created'];

  try {
    $webhooks = DelyvaX_Shipping_API::getWebhook();

    // check if subscribed to any event
    for ($i=0; $i < sizeof($webhooks); $i++) {
      $wh = $webhooks[$i];
      if ($wh['url'] === $valid_url) {
        unset($needed_event[array_search($wh['event'], $needed_event)]);
      }
    }

    // subscribe to remaining
    for ($i=0; $i < count($needed_event); $i++) {
      DelyvaX_Shipping_API::postCreateWebhook($needed_event[$i]);
    }
  } catch (Exception $e) {

  }
}


function delyvax_webhook_order_created()
{
    $raw = file_get_contents('php://input');
    // var_dump($raw);
    // throw new Exception();

    if($raw)
    {
        $json = json_decode($raw, true);

        if( isset($json) )
        {
            $data = $json;
            $settings = get_option( 'woocommerce_delyvax_settings');

            $company_id = $settings['company_id'];
            $company_code = $settings['company_code'];

            if( isset($data['id']) && isset($data['consignmentNo']) && isset($data['statusCode'])
                  && intval($settings['customer_id']) === intval($data['customerId']) )
            {
                  if ($settings['api_webhook_enable'] == 'yes')
                  {
                      $shipmentId = $data['id'];
                      $consignmentNo = $data['consignmentNo'];
                      $statusCode = $data['statusCode'];
                      $nanoId = $data['nanoId'];

                      if(strlen($shipmentId) < 3 || strlen($consignmentNo) < 3 )
                      {
                        return;
                      }

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
                          $order = wc_get_order($orders[$i]->get_id());

                          $order->get_status();

                          $labelUrl = 'https://api.delyva.app/v1.0/order/'.$shipmentId.'/label?companyId='.$company_id;

                          // $order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
                          $order->update_meta_data( 'DelyvaXTrackingCode', $consignmentNo );
                          $order->update_meta_data( 'DelyvaXTrackingShort', $nanoId );
                          $order->update_meta_data( 'DelyvaXLabelUrl', $labelUrl );
                          $order->save();

                          if (!empty($order))
                          {
                              //on the way to pick up
                              if( !$order->has_status('wc-ready-to-collect') )
                              {
                                  $order->update_status('wc-ready-to-collect', 'Delivery order number: '.$consignmentNo.' - <a href="https://api.delyva.app/v1.0/order/'.$shipmentId.'/label?companyId='.$company_id.'" target="_blank">Print label</a> - <a href="https://'.$company_code.'.delyva.app/customer/strack?trackingNo='.$consignmentNo.'" target="_blank">Track</a>.', false);
                                  // $order->update_status('wc-ready-to-collect', 'Order status changed to Ready.', false); // order note is optional, if you want to  add a note to order
                                  // $order->update_status('courier-accepted');

                                  // wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-ready-to-collect']);

                                  //no need - vendor will be fulfilling sub order instead of main order
                                  //start update sub orders
                                  // $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );
                                  //
                                  // if ( $sub_orders ) {
                                  //     foreach ($sub_orders as $sub)
                                  //     {
                                  //         $sub_order = wc_get_order($sub->ID);
                                  //         $sub_order->update_status('wc-ready-to-collect');
                                  //         wp_update_post(['ID' => $sub->ID, 'post_status' => 'wc-ready-to-collect']);
                                  //     }
                                  // }
                                  //end update sub orders
                              }
                          }
                      }

                  }
            }

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
            $settings = get_option( 'woocommerce_delyvax_settings');

            if( isset($data['orderId']) && isset($data['consignmentNo']) && isset($data['statusCode'])
              && intval($settings['customer_id']) === intval($data['customerId']))
            {
                if ($settings['api_webhook_enable'] == 'yes') {
                      //get order id by tracking no
                      //order_tracking.update"
                      $companyId = $data['companyId'];
                      $shipmentId = $data['orderId'];
                      $consignmentNo = $data['consignmentNo'];
                      $statusCode = $data['statusCode'];
                      $nanoId = $data['nanoId'];
                      $personnel = $data['personnel'];

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
                          $order = wc_get_order($orders[$i]->get_id());

                          $order->get_status();

                          // $order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
                          $order->update_meta_data( 'DelyvaXTrackingCode', $consignmentNo );
                          if($nanoId) 
                          {
                            $order->update_meta_data( 'DelyvaXTrackingShort', $nanoId );
                          }
                          if($personnel) 
                          {
                            $order->update_meta_data( 'DelyvaXPersonnel', json_encode($personnel) );
                          }
                          $order->save();

                          if($statusCode == 100 || $statusCode == 110)
                          {
                              if (!empty($order))
                              {
                                  //on the way to pick up
                                  if( !$order->has_status('wc-ready-to-collect') )
                                  {
                                      $order->update_status('wc-ready-to-collect', 'Order status changed to Ready.', false); // order note is optional, if you want to  add a note to order
                                      // $order->update_status('courier-accepted');

                                      // wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-ready-to-collect']);

                                      //no need - vendor will be fulfilling sub order instead of main order
                                      //start update sub orders
                                      // $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );
                                      //
                                      // if ( $sub_orders ) {
                                      //     foreach ($sub_orders as $sub)
                                      //     {
                                      //         $sub_order = wc_get_order($sub->ID);
                                      //         $sub_order->update_status('wc-ready-to-collect');
                                      //         wp_update_post(['ID' => $sub->ID, 'post_status' => 'wc-ready-to-collect']);
                                      //     }
                                      // }

                                      //end update sub orders
                                  }
                              }
                          }else if($statusCode == 200)
                          {
                              if (!empty($order))
                              {
                                  //on the way to pick up
                                  if( !$order->has_status('wc-courier-accepted') )
                                  {
                                      $order->update_status('courier-accepted', 'Order status changed to Courier accepted.', false); // order note is optional, if you want to  add a note to order
                                      // $order->update_status('courier-accepted');

                                      wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-courier-accepted']);

                                      //no need - vendor will be fulfilling sub order instead of main order
                                      //start update sub orders
                                      // $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );
                                      //
                                      // if ( $sub_orders ) {
                                      //     foreach ($sub_orders as $sub)
                                      //     {
                                      //         $sub_order = wc_get_order($sub->ID);
                                      //         $sub_order->update_status('courier-accepted');
                                      //         wp_update_post(['ID' => $sub->ID, 'post_status' => 'wc-courier-accepted']);
                                      //     }
                                      // }

                                      //end update sub orders
                                  }
                              }
                          }else if($statusCode == 400)
                          {
                              if (!empty($order))
                              {
                                  //on the way to pick up
                                  if( !$order->has_status('wc-start-collecting') )
                                  {
                                      $order->update_status('start-collecting', 'Order status changed to Pending pick up.', false); // order note is optional, if you want to  add a note to order
                                      // $order->update_status('start-collecting');

                                      wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-start-collecting']);

                                      //no need - vendor will be fulfilling sub order instead of main order
                                      //start update sub orders
                                      // $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );
                                      //
                                      // if ( $sub_orders ) {
                                      //     foreach ($sub_orders as $sub)
                                      //     {
                                      //         $sub_order = wc_get_order($sub->ID);
                                      //         $sub_order->update_status('start-collecting');
                                      //         wp_update_post(['ID' => $sub->ID, 'post_status' => 'wc-start-collecting']);
                                      //     }
                                      // }
                                      //end update sub orders
                                  }
                              }
                          }else if($statusCode == 475)
                          {
                              if (!empty($order))
                              {
                                  //on the way to pick up
                                  if( !$order->has_status('wc-failed-collection') )
                                  {
                                      $order->update_status('failed-collection', 'Order status changed to Pick up failed.', false); // order note is optional, if you want to  add a note to order
                                      // $order->update_status('failed-collection');

                                      wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-failed-collection']);

                                      //no need - vendor will be fulfilling sub order instead of main order
                                      //start update sub orders
                                      // $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );
                                      //
                                      // if ( $sub_orders ) {
                                      //     foreach ($sub_orders as $sub)
                                      //     {
                                      //         $sub_order = wc_get_order($sub->ID);
                                      //         $sub_order->update_status('failed-collection');
                                      //         wp_update_post(['ID' => $sub->ID, 'post_status' => 'wc-failed-collection']);
                                      //     }
                                      // }
                                      //end update sub orders
                                  }
                              }
                          }else if($statusCode == 500) // }else if($statusCode == 500)
                          {
                              if (!empty($order))
                              {
                                  //on the way to pick up
                                  if( !$order->has_status('wc-collected') )
                                  {
                                      $order->update_status('collected', 'Order status changed to Pick up complete.', false); // order note is optional, if you want to  add a note to order
                                      // $order->update_status('collected');

                                      wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-collected']);

                                      // no need - vendor will be fulfilling sub order instead of main order
                                      //start update sub orders
                                      // $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );
                                      //
                                      // if ( $sub_orders ) {
                                      //     foreach ($sub_orders as $sub)
                                      //     {
                                      //         $sub_order = wc_get_order($sub->ID);
                                      //         $sub_order->update_status('collected');
                                      //         wp_update_post(['ID' => $sub->ID, 'post_status' => 'wc-collected']);
                                      //     }
                                      // }
                                      //end update sub orders
                                  }
                              }
                          }else if($statusCode == 600)
                          {
                              if (!empty($order))
                              {
                                  //on the way to pick up
                                  if( !$order->has_status('wc-start-delivery') )
                                  {
                                      $order->update_status('start-delivery', 'Order status changed to On the way for delivery.', false); // order note is optional, if you want to  add a note to order
                                      // $order->update_status('start-delivery');

                                      wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-start-delivery']);

                                      //no need - vendor will be fulfilling sub order instead of main order
                                      //start update sub orders
                                      // $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );
                                      //
                                      // if ( $sub_orders ) {
                                      //     foreach ($sub_orders as $sub)
                                      //     {
                                      //         $sub_order = wc_get_order($sub->ID);
                                      //         $sub_order->update_status('start-delivery');
                                      //         wp_update_post(['ID' => $sub->ID, 'post_status' => 'wc-start-delivery']);
                                      //     }
                                      // }
                                      //end update sub orders
                                  }
                              }
                          }else if($statusCode == 650)
                          {
                              if (!empty($order))
                              {
                                  //on the way to pick up
                                  if( !$order->has_status('wc-failed-delivery') )
                                  {
                                      $order->update_status('failed-delivery', 'Order status changed to Delivery failed.', false); // order note is optional, if you want to  add a note to order
                                      // $order->update_status('failed-delivery');

                                      wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-failed-delivery']);

                                      //no need - vendor will be fulfilling sub order instead of main order
                                      //start update sub orders
                                      // $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );
                                      //
                                      // if ( $sub_orders ) {
                                      //     foreach ($sub_orders as $sub)
                                      //     {
                                      //         $sub_order = wc_get_order($sub->ID);
                                      //         $sub_order->update_status('failed-delivery');
                                      //         wp_update_post(['ID' => $sub->ID, 'post_status' => 'wc-failed-delivery']);
                                      //     }
                                      // }
                                      // //end update sub orders
                                  }
                              }
                          }else if($statusCode == 700 || $statusCode == 1000)
                          {
                              if (!empty($order))
                              {
                                  if( !$order->has_status('wc-completed') )
                                  {
                                      $order->update_status('completed', 'Order status changed to Completed', false); // order note is optional, if you want to  add a note to order
                                      // $order->update_status('completed');

                                      wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-completed']);

                                      // no need - vendor will be fulfilling sub order instead of main order
                                      //start update sub orders
                                      // $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );
                                      //
                                      // if ( $sub_orders ) {
                                      //     foreach ($sub_orders as $sub)
                                      //     {
                                      //         $sub_order = wc_get_order($sub->ID);
                                      //         $sub_order->update_status('completed');
                                      //         wp_update_post(['ID' => $sub->ID, 'post_status' => 'wc-completed']);
                                      //     }
                                      // }
                                      //end update sub orders
                                  }
                              }
                          }else if($statusCode == 900)
                          {                            
                              if ($settings['cancel_order'] == 'yes') 
                              {
                                  if (!empty($order))
                                  {
                                      if( !$order->has_status('wc-cancelled') )
                                      {
                                          $order->update_status('cancelled', 'Order status changed to Cancelled', false); 
                                          
                                          wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-cancelled']);
                                      }
                                  }
                              }
                          }else
                          {
                              echo 'else';
                          }
                      }
                      header('Content-Type: application/json');
                      die(json_encode([
                            'status' => 'OK',
                            'version' => DELYVAX_PLUGIN_VERSION,
                      ], JSON_UNESCAPED_SLASHES));
                }
            }

            // TODO: Move up in next version. Leaving it here for backward compatibility with older version.
            if ($_GET['delyvax'] === 'webhook') {
              header('Content-Type: application/json');
              die(json_encode([
                'status' => 'AWE=' . $settings['api_webhook_enable'],
                'version' => DELYVAX_PLUGIN_VERSION,
              ], JSON_UNESCAPED_SLASHES));
            }
        }
    }
}

function delyvax_woocommerce_update_options( $array ) {
  $settings = get_option( 'woocommerce_delyvax_settings');

  if ($settings['api_webhook_enable'] == 'yes') {
    delyvax_webhook_subscribe();
    delyvax_webhook_duplicate_check();
  } else {
    delyvax_webhook_unsubscribe();
  }
};

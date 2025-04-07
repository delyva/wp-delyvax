<?php
defined( 'ABSPATH' ) or die();

if (!class_exists('DelyvaX_Shipping_API')) {
    require_once dirname(__FILE__) . '/delyvax-api.php';
}

add_action( 'parse_request', 'delyvax_webhook_order_created',10,0);
add_action( 'parse_request', 'delyvax_webhook_get_tracking',10,0);

add_filter('woocommerce_settings_api_sanitized_fields_delyvax', 'delyvax_detect_webhook_changes', 10, 1);


// check for duplicate, fix old url
function delyvax_webhook_duplicate_check() {
  $valid_url = get_site_url()."/?delyvax=webhook";

  try {
    $webhooks = DelyvaX_Shipping_API::getWebhook();
    $available = [];

    for ($i=0; $i < sizeof($webhooks); $i++) {
      $wh = $webhooks[$i];
      if (array_key_exists($wh['event'], $available) && $wh['url'] === $valid_url) {
        DelyvaX_Shipping_API::deleteWebhook($wh['id']);
      } else if ($wh['url'] === $valid_url) {
        $available[$wh['event']] = $wh['url'];
      }
    }
  } catch (Exception $e) {

  }
}

function delyvax_webhook_unsubscribe() {
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
  $settings = get_option( 'woocommerce_delyvax_settings');

  $valid_url = get_site_url()."/?delyvax=webhook";
  $needed_event = ['order_tracking.change','order.created'];

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
    if (!class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
        return;
    }

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
            $company_code = $settings['company_code'];

            if( isset($data['id']) && isset($data['consignmentNo']) && isset($data['statusCode'])
                  && intval($settings['customer_id']) === intval($data['customerId']) )
            {
                  if($data['statusCode'] == 100)
                  {
                      if ($settings['api_webhook_enable'] == 'yes')
                      {
                          $shipmentId = $data['id'];
                          $consignmentNo = $data['consignmentNo'];
                          $statusCode = $data['statusCode'];

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

                              // $order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
                              $order->update_meta_data( 'DelyvaXTrackingCode', $consignmentNo );
                              $order->save();

                              if (!empty($order))
                              {
                                  //on the way to pick up
                                  if( !$order->has_status('wc-ready-to-collect') )
                                  {
                                      $order->update_status('wc-ready-to-collect', 'Delivery order number: '.$consignmentNo.' - <a href="https://api.delyva.app/v1.0/order/'.$shipmentId.'/label" target="_blank">Print label</a> - <a href="https://'.$company_code.'.delyva.app/customer/strack?trackingNo='.$consignmentNo.'" target="_blank">Track</a>.', false);
                                      
                                      // wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-ready-to-collect']);
                                      //end update sub orders
                                  }
                              }
                          }

                      }

                      header('Content-Type: application/json');
                      die(json_encode([
                            'status' => 'OK-delyvax_webhook_order_created',
                            'version' => DELYVAX_PLUGIN_VERSION,
                      ], JSON_UNESCAPED_SLASHES));
                  }
            }
        }
    }
}

function delyvax_webhook_get_tracking()
{
    if (!class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
        return;
    }

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

                          // $order->get_status();

                          // $order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
                          $order->update_meta_data( 'DelyvaXTrackingCode', $consignmentNo );
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
                                    
                                      // wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-ready-to-collect']);

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
                                      $order->update_status('wc-courier-accepted', 'Order status changed to Courier accepted.', false); // order note is optional, if you want to  add a note to order
                                      
                                      // wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-courier-accepted']);                                    

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
                                      $order->update_status('wc-start-collecting', 'Order status changed to Pending pick up.', false); // order note is optional, if you want to  add a note to order
                                      
                                      // wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-start-collecting']);

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
                                      $order->update_status('wc-failed-collection', 'Order status changed to Pick up failed.', false); // order note is optional, if you want to  add a note to order
                                      
                                      // wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-failed-collection']);

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
                                      $order->update_status('wc-collected', 'Order status changed to Pick up complete.', false); // order note is optional, if you want to  add a note to order

                                      // wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-collected']);

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
                                      $order->update_status('wc-start-delivery', 'Order status changed to On the way for delivery.', false); // order note is optional, if you want to  add a note to order
                                      
                                      // wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-start-delivery']);

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
                                      $order->update_status('wc-failed-delivery', 'Order status changed to Delivery failed.', false); // order note is optional, if you want to  add a note to order
                                      
                                      // wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-failed-delivery']);

                                      // //end update sub orders
                                  }
                              }
                          }else if($statusCode == 700 || $statusCode == 1000)
                          {
                              if (!empty($order))
                              {
                                  if( !$order->has_status('wc-completed') )
                                  {
                                      $order->update_status('wc-completed', 'Order status changed to Completed', false); // order note is optional, if you want to  add a note to order
                                      
                                      // wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-completed']);

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
                                          $order->update_status('wc-cancelled', 'Order status changed to Cancelled', false); 
                                          
                                          // wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-cancelled']);
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
                            'status' => 'OK-delyvax_webhook_get_tracking',
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

function delyvax_detect_webhook_changes($settings) {
  if (!class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
      return $settings;
  }
  
  $previous_settings = get_option('woocommerce_delyvax_settings', array());
  $previous_enabled = isset($previous_settings['api_webhook_enable']) && $previous_settings['api_webhook_enable'] === 'yes';
  $new_enabled = isset($settings['api_webhook_enable']) && $settings['api_webhook_enable'] === 'yes';
  
  if ($previous_enabled !== $new_enabled) {
      if ($new_enabled) {
          delyvax_webhook_subscribe();
          delyvax_webhook_duplicate_check();
      } else {
          delyvax_webhook_unsubscribe();
      }
  }
  
  return $settings;
}
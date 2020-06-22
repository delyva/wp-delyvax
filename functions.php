<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!');
register_activation_hook('delyvax/delyvax.php', 'delyvaxPluginActivated');
register_deactivation_hook('delyvax/delyvax.php', 'delyvaxPluginDeActivated');
register_uninstall_hook('delyvax/delyvax.php', 'delyvaxPluginUninstalled');

add_filter('parse_request', 'delyvaxRequest');

add_action('woocommerce_check_cart_items','delyvax_check_cart_weight');
add_action('woocommerce_checkout_before_customer_details', 'delyvax_check_checkout_weight');

add_action( 'woocommerce_payment_complete', 'delyvax_payment_complete');
add_action( 'woocommerce_order_status_changed', 'delyvax_order_confirmed', 10, 3 );
add_filter( 'woocommerce_cod_process_payment_order_status', 'delyvax_change_cod_payment_order_status', 10, 2 );

add_action( 'widgets_init', 'delyvax_webhook_subscribe');
add_action( 'woocommerce_after_register_post_type', 'delyvax_webhook_get_tracking');


function delyvax_check_cart_weight(){
    $weight = WC()->cart->get_cart_contents_weight();
    $min_weight = 0.1; // kg
    $max_weight = 10000; // kg

    if($weight < $min_weight){
        wc_add_notice( sprintf( __( 'You have %s kg weight and we allow minimum ' . $min_weight . '  kg of weight per order. Increase the cart weight by adding more items to proceed checkout.', 'default' ), $weight ), 'error');
    }

    if($weight > $max_weight){
        wc_add_notice( sprintf( __( 'You have %s kg weight and we allow maximum' . $max_weight . '  kg of weight per order. Reduce the cart weight by removing some items to proceed checkout.', 'default' ), $weight ), 'error');
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
    $settings = get_option( 'woocommerce_delyvax_settings');

    if ($settings['create_shipment_on_paid'] == 'yes')
    {
        $order = wc_get_order( $order_id );
        $user = $order->get_user();

        delyvax_create_order($order, $user);
    }
}

function delyvax_change_cod_payment_order_status( $order_status, $order ) {
    $settings = get_option( 'woocommerce_delyvax_settings');

    if ($settings['create_shipment_on_paid'] == 'yes')
    {
        // $order = wc_get_order( $order_id );
        $user = $order->get_user();

        delyvax_create_order($order, $user);
    }
    return 'processing';
}

function delyvax_order_confirmed( $order_id, $old_status, $new_status ) {
    $settings = get_option( 'woocommerce_delyvax_settings');

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

        $DelyvaXOrderID = $order->get_meta( 'DelyvaXOrderID');
        $DelyvaXTrackingCode = $order->get_meta( 'DelyvaXTrackingCode');

        if($DelyvaXOrderID == null && $DelyvaXTrackingCode == null)
        {
            // $resultCreate = DelyvaX_Shipping_API::postCreateOrder($order, $user);
            $resultCreate = delyvax_post_create_order($order, $user);
        }
        // exit;
    } catch (Exception $e) {
        print_r($e);
        // exit;
    }
}

//rewire logic here, API is only for post
function delyvax_post_create_order($order, $user) {
      $settings = get_option( 'woocommerce_delyvax_settings' );

      $company_id = $settings['company_id'];
      $user_id = $settings['user_id'];
      $customer_id = $settings['customer_id'];
      $api_token = $settings['api_token'];
      $processing_days = $settings['processing_days'];
      $processing_hours = $settings['processing_hours'];

      //----delivery date & time (pull from meta data), if not available, set to +next day 8am.
      $gmtoffset = get_option('gmt_offset');
      // echo '<br>';
      $stimezone = get_option('timezone_string');
      // echo '<br>';

      $dtimezone = new DateTimeZone($stimezone);

      // echo date('d-m-Y H:i:s');
      $timeslot = null;

      $delivery_date = new DateTime();
      $delivery_date->setTimezone($dtimezone);

      if($processing_days > 0)
      {
          $delivery_date->modify('+'.$processing_days.' day');
      }

      $delivery_date->format('d-m-Y H:i:s');
      // echo '<br>';
      if($order->get_meta( 'delivery_date' ) != null)
      {
          $delivery_date = new DateTime('@' .$order->get_meta( 'delivery_date' ));
          $delivery_date->setTimezone($dtimezone);
          // $delivery_type = $order->get_meta( 'delivery_type');
      }
      // $delivery_date->format('d-m-Y H:i:s');
      // echo '<br>';

      // echo $delivery_time = '08:00'; //8AM
      if($processing_hours > 0)
      {
          $delivery_time = date("H", strtotime("+".$processing_hours." hours"));
      }

      // echo '<br>';
      if($order->get_meta( 'delivery_time' ) != null)
      {
          $w_delivery_time = $order->get_meta( 'delivery_time'); //1440 minutes / 24
          // echo '<br>';

          $a_delivery_time = explode(",",$w_delivery_time);
          if(sizeof($a_delivery_time) > 0)
          {
              $delivery_time = $a_delivery_time[0]/60;
          }

          if(sizeof($a_delivery_time) > 1)
          {
              $timeslot_from = $a_delivery_time[0]/60;
              $timeslot_to = $a_delivery_time[1]/60;

              $timeslot_from_hour = 0;
              $timeslot_from_min = 0;

              $timeslot_to_hour = 0;
              $timeslot_to_min = 0;

              if ( strpos( $timeslot_from, "." ) !== false ) {
                  $timeslot_from_hour = $timeslot_from - 0.5;
                  $timeslot_from_min = "30";
              }else {
                  $timeslot_from_hour = $timeslot_from;
                  $timeslot_from_min = "00";
              }

              if ( strpos( $timeslot_to, "." ) !== false ) {
                  $timeslot_to_hour = $timeslot_to - 0.5;
                  $timeslot_to_min = "30";
              }else {
                  $timeslot_to_hour = $timeslot_to;
                  $timeslot_to_min = "00";
              }

              $timeslot = $timeslot_from_hour.':'.$timeslot_from_min.' - '.$timeslot_to_hour.':'.$timeslot_to_min.' (24H)';
          }
      }

      $delivery_type = 'delivery';
      // echo '<br>';
      if($order->get_meta( 'delivery_type' ) != null)
      {
          $delivery_type = $order->get_meta( 'delivery_type');
      }
      // echo $delivery_type;

      // $my_date_time = date("Y-m-d H:i:s", strtotime("+1 hours"));

      $scheduledAt = $delivery_date;
      $scheduledAt->setTime($timeslot_from_hour,$timeslot_from_min,00);

      // echo $delivery_date->format('d-m-Y H:i');
      // echo '<br>';

      // $scheduledAt = $scheduledAt->format('c');
      // echo '<br>';

      // echo $scheduledAt->format('d/m/Y H:i:s');
      // echo $scheduledAt->format('Y-m-d H:i:s');

      // echo $scheduledAt->format('d-m-Y H:i:s');

      // $scheduledAt = (new DateTime($my_date_time))->format('c');
      //2020-06-10T23:13:51-04:00 / "scheduledAt": "2019-11-15T12:00:00+0800", //echo date_format(date_create('17 Oct 2008'), 'c');

      //service
      $serviceCode = "";

      $main_order = $order;

      if($order->parent_id)
      {
          $main_order = wc_get_order($order->parent_id);
      }

      // Iterating through order shipping items
      foreach( $main_order->get_items( 'shipping' ) as $item_id => $shipping_item_obj )
      {
          $serviceobject = $shipping_item_obj->get_meta_data();

          // print_r($serviceobject);

          // echo json_encode($serviceobject);
          for($i=0; $i < sizeof($serviceobject); $i++)
          {
              if($serviceobject[$i]->key == "service_code")
              {
                  $serviceCode = $serviceobject[0]->value;
              }
          }
      }

      //inventory / items
      $count = 0;
      $inventories = array();
      $total_weight = 0;
      $total_price = 0;
      $order_notes = '';

      $store_name = null;
      $store_phone = null;
      $store_email = null;
      $store_address_1 = null;
      $store_address_2 = null;
      $store_city = null;
      $store_postcode = null;
      $store_country = null;
      $store_country = null;


      //loop inventory main n suborder
      if($order->parent_id)
      {
          $main_order = wc_get_order($order->parent_id);

          $order_notes = 'Order No: #'.$main_order->get_id().' <br>';
          $order_notes = $order_notes.'Date Time: '.$scheduledAt->format('d-m-Y H:i').' (24H) <br>';

          if($timeslot)
          {
              $order_notes = $order_notes.'Time slot: '.$timeslot.'';
          }

          $sub_orders = get_children( array( 'post_parent' => $main_order->get_id(), 'post_type' => 'shop_order' ) );

          if ( $sub_orders )
          {
              foreach ($sub_orders as $sub)
              {
                  $sub_order = wc_get_order($sub->ID);

                  $product_store_name = 'N/A';

                  if(function_exists(dokan_get_seller_id_by_order) && function_exists(dokan_get_store_info))
                  {
                      $seller_id = dokan_get_seller_id_by_order($sub_order->get_id());
                      $store_info = dokan_get_store_info( $seller_id );

                      // echo '<pre>'.print_r($store_info).'</pre>';
                      $product_store_name = $store_info['store_name'];

                      $store_name = $store_info['store_name'];
                      $store_first_name = $store_info['first_name'];
                      $store_last_name = $store_info['last_name'];
                      $store_phone = $store_info['phone'];
                      $store_email = $store_info['email'];
                      $store_address_1 = $store_info['address']['street_1'];
                      $store_address_2 = $store_info['address']['street_2'];
                      $store_city = $store_info['address']['city'];
                      $store_state = $store_info['address']['state'];
                      $store_postcode = $store_info['address']['zip'];
                      $store_country = $store_info['address']['country'];
                  }

                  foreach ( $sub_order->get_items() as $item )
                  {
                      $product_id = $item->get_product_id();
                      $product_variation_id = $item->get_variation_id();
                      $product = $item->get_product();
                      $product_name = $item->get_name();
                      $quantity = $item->get_quantity();
                      $subtotal = $item->get_subtotal();
                      $total = $item->get_total();
                      $tax = $item->get_subtotal_tax();
                      $taxclass = $item->get_tax_class();
                      $taxstat = $item->get_tax_status();
                      $allmeta = $item->get_meta_data();
                      // $somemeta = $item->get_meta( '_whatever', true );
                      $type = $item->get_type();

                      // print_r($allmeta);

                      $_pf = new WC_Product_Factory();

                      $product = $_pf->get_product($product_id);

                      $inventories[$count] = array(
                          "name" => $product_name,
                          "type" => "PARCEL", //$type PARCEL / FOOD
                          "price" => array(
                              "amount" => $total,
                              "currency" => $main_order->get_currency(),
                          ),
                          "weight" => array(
                              "value" => ($product->get_weight()*$quantity),
                              "unit" => "kg"
                          ),
                          "quantity" => $quantity,
                          "description" => '[Store: '.$product_store_name.'] '.$product_name
                      );

                      $total_weight = $total_weight + ($product->get_weight()*$quantity);
                      $total_price = $total_price + $total;

                      // $order_notes = $order_notes.'#'.($count+1).'. [Store: '.$store_name.'] '.$product_name.' X '.$quantity.'pcs          <br>';

                      $count++;
                  }
              }
          }else {
              foreach ( $main_order->get_items() as $item )
              {
                  $product_id = $item->get_product_id();
                  $product_variation_id = $item->get_variation_id();
                  $product = $item->get_product();
                  $product_name = $item->get_name();
                  $quantity = $item->get_quantity();
                  $subtotal = $item->get_subtotal();
                  $total = $item->get_total();
                  $tax = $item->get_subtotal_tax();
                  $taxclass = $item->get_tax_class();
                  $taxstat = $item->get_tax_status();
                  $allmeta = $item->get_meta_data();
                  // $somemeta = $item->get_meta( '_whatever', true );
                  $type = $item->get_type();

                  //get seller info
                  $product_store_name = 'N/A';

                  if(function_exists(dokan_get_seller_id_by_order) && function_exists(dokan_get_store_info))
                  {
                      $seller_id = dokan_get_seller_id_by_order($main_order->get_id());
                      $store_info = dokan_get_store_info( $seller_id );

                      // echo '<pre>'.print_r($store_info).'</pre>';
                      $product_store_name = $store_info['store_name'];

                      $store_name = $store_info['store_name'];
                      $store_first_name = $store_info['first_name'];
                      $store_last_name = $store_info['last_name'];
                      $store_phone = $store_info['phone'];
                      $store_email = $store_info['email'];
                      $store_address_1 = $store_info['address']['street_1'];
                      $store_address_2 = $store_info['address']['street_2'];
                      $store_city = $store_info['address']['city'];
                      $store_state = $store_info['address']['state'];
                      $store_postcode = $store_info['address']['zip'];
                      $store_country = $store_info['address']['country'];
                  }

                  $_pf = new WC_Product_Factory();

                  $product = $_pf->get_product($product_id);

                  $inventories[$count] = array(
                      "name" => $product_name,
                      "type" => "PARCEL", //$type PARCEL / FOOD
                      "price" => array(
                          "amount" => $total,
                          "currency" => $main_order->get_currency(),
                      ),
                      "weight" => array(
                          "value" => ($product->get_weight()*$quantity),
                          "unit" => "kg"
                      ),
                      "quantity" => $quantity,
                      "description" => '[Store: '.$product_store_name.'] '.$product_name
                  );

                  $total_weight = $total_weight + ($product->get_weight()*$quantity);
                  $total_price = $total_price + $total;

                  // $order_notes = $order_notes.'#'.($count+1).'. [Store: '.$store_name.'] '.$product_name.' X '.$quantity.'pcs          <br>';

                  $count++;
              }
          }
      }else {
          $main_order = $order;

          $order_notes = 'Order No: #'.$main_order->get_id().' <br>';
          $order_notes = $order_notes.'Date Time: '.$scheduledAt->format('d-m-Y H:i').' (24H) <br>';

          if($timeslot)
          {
              $order_notes = $order_notes.'Time slot: '.$timeslot.'';
          }

          foreach ( $main_order->get_items() as $item )
          {
              $product_id = $item->get_product_id();
              $product_variation_id = $item->get_variation_id();
              $product = $item->get_product();
              $product_name = $item->get_name();
              $quantity = $item->get_quantity();
              $subtotal = $item->get_subtotal();
              $total = $item->get_total();
              $tax = $item->get_subtotal_tax();
              $taxclass = $item->get_tax_class();
              $taxstat = $item->get_tax_status();
              $allmeta = $item->get_meta_data();
              // $somemeta = $item->get_meta( '_whatever', true );
              $type = $item->get_type();

              //get seller info
              $product_store_name = 'N/A';

              if(function_exists(dokan_get_seller_id_by_order) && function_exists(dokan_get_store_info))
              {
                  $seller_id = dokan_get_seller_id_by_order($main_order->get_id());
                  $store_info = dokan_get_store_info( $seller_id );

                  // echo '<pre>'.print_r($store_info).'</pre>';
                  $product_store_name = $store_info['store_name'];

                  $store_name = $store_info['store_name'];
                  $store_first_name = $store_info['first_name'];
                  $store_last_name = $store_info['last_name'];
                  $store_phone = $store_info['phone'];
                  $store_email = $store_info['email'];
                  $store_address_1 = $store_info['address']['street_1'];
                  $store_address_2 = $store_info['address']['street_2'];
                  $store_city = $store_info['address']['city'];
                  $store_state = $store_info['address']['state'];
                  $store_postcode = $store_info['address']['zip'];
                  $store_country = $store_info['address']['country'];
              }

              $_pf = new WC_Product_Factory();

              $product = $_pf->get_product($product_id);

              $inventories[$count] = array(
                  "name" => $product_name,
                  "type" => "PARCEL", //$type PARCEL / FOOD
                  "price" => array(
                      "amount" => $total,
                      "currency" => $main_order->get_currency(),
                  ),
                  "weight" => array(
                      "value" => ($product->get_weight()*$quantity),
                      "unit" => "kg"
                  ),
                  "quantity" => $quantity,
                  "description" => '[Store: '.$product_store_name.'] '.$product_name
              );

              $total_weight = $total_weight + ($product->get_weight()*$quantity);
              $total_price = $total_price + $total;

              // $order_notes = $order_notes.'#'.($count+1).'. [Store: '.$store_name.'] '.$product_name.' X '.$quantity.'pcs          <br>';

              $count++;
          }
      }

      /// check payment method and set codAmount
      $codAmount = 0;
      $codCurrency = $order->get_currency();

      if($order->get_payment_method() == 'cod')
      {
          $codAmount = $main_order->get_total();
      }

      //origin
      //Origin! -- hanlde multivendor, pickup address from vendor address or woocommerce address

      // The main address pieces:
      if($store_name == null) $store_name = $order->get_shipping_first_name().' '.$order->get_shipping_last_name();
      if($store_email == null) $store_email = $order->get_billing_email();
      if($store_phone == null) $store_phone = $order->get_billing_phone();
      if($store_address_1 == null) $store_address_1     = get_option( 'woocommerce_store_address');
      if($store_address_2 == null) $store_address_2   = get_option( 'woocommerce_store_address_2');
      if($store_city == null) $store_city        = get_option( 'woocommerce_store_city');
      if($store_postcode == null) $store_postcode    = get_option( 'woocommerce_store_postcode');

      // The country/state
      if($store_country == null)
      {
          $store_raw_country = get_option( 'woocommerce_default_country');

          // Split the country/state
          $split_country = explode( ":", $store_raw_country );

          // Country and state separated:
          $store_country = $split_country[0];
          $store_state   = $split_country[1];
      }

      $origin = array(
          "scheduledAt" => $scheduledAt->format('c'), //"2019-11-15T12:00:00+0800",
          "inventory" => $inventories,
          "contact" => array(
              "name" => $store_name,
              "email" => $store_email,
              "phone" => $store_phone,
              "mobile" => $store_phone,
              "address1" => $store_address_1,
              "address2" => $store_address_2,
              "city" => $store_city,
              "state" => $store_state,
              "postcode" => $store_postcode,
              "country" => $store_country,
              // "coord" => array(
              //     "lat" => "",
              //     "lon" => ""
              // )
          ),
          "note"=> $order_notes
      );

      //destination
      $destination = array(
          "scheduledAt" => $scheduledAt->format('c'), //"2019-11-15T12:00:00+0800",
          "inventory" => $inventories,
          "contact" => array(
              "name" => $order->get_shipping_first_name().' '.$order->get_shipping_last_name(),
              "email" => $order->get_billing_email(),
              "phone" => $order->get_billing_phone(),
              "mobile" => $order->get_billing_phone(),
              "address1" => $order->get_shipping_address_1(),
              "address2" => $order->get_shipping_address_2(),
              "city" => $order->get_shipping_city(),
              "state" => $order->get_shipping_state(),
              "postcode" => $order->get_shipping_postcode(),
              "country" => $order->get_shipping_country(),
              // "coord" => array(
              //     "lat" => "",
              //     "lon" => ""
              // )
          ),
          "note"=> $order_notes
      );

      $weight = array(
        "unit" => "kg",
        "value" => $total_weight
      );

      $cod = array(
        "amount" => $codAmount,
        "currency" => $codCurrency
      );

      $resultCreate = DelyvaX_Shipping_API::postCreateOrder($origin, $destination, $serviceCode, $order_notes, $cod);

      if($resultCreate)
      {
            $shipmentId = $resultCreate["id"];

            // $resultProcess = DelyvaX_Shipping_API::postProcessOrder($order, $user, $shipmentId);
            $resultProcess = delyvax_post_process_order($order, $user, $shipmentId);

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

                        $x = 0;
                        foreach ($sub_orders as $sub)
                        {
                            $sub_order = wc_get_order($sub->ID);

                            $sub_order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
                            $sub_order->update_meta_data( 'DelyvaXTrackingCode', $trackingNo );
                            $sub_order->save();

                            $consignmentNo = $trackingNo."-".($x+1);

                            //create task
                            delyvax_create_task($shipmentId, $consignmentNo, $sub_order, $user, $scheduledAt);
                            //

                            $x++;
                        }
                    }else {
                        $main_order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
                        $main_order->update_meta_data( 'DelyvaXTrackingCode', $trackingNo );
                        $main_order->save();

                        $consignmentNo = $trackingNo."-1";

                        //create task
                        delyvax_create_task($shipmentId, $consignmentNo, $main_order, $user, $scheduledAt);
                        //
                    }
                    //
                }else {
                    $main_order = $order;

                    $main_order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
                    $main_order->update_meta_data( 'DelyvaXTrackingCode', $trackingNo );
                    $main_order->save();

                    $consignmentNo = $trackingNo."-1";

                    //create tasks if split tasks
                    delyvax_create_task($shipmentId, $consignmentNo, $main_order, $user, $scheduledAt);
                    //end
                }
            }
      }
}

//rewire logic here, API is only for post
function delyvax_post_process_order($order, $user, $shipmentId) {
      //service
      $serviceCode = "";

      $main_order = $order;

      if($order->parent_id)
      {
          $main_order = wc_get_order($order->parent_id);
      }

      // Iterating through order shipping items
      foreach( $main_order->get_items( 'shipping' ) as $item_id => $shipping_item_obj )
      {
          $serviceobject = $shipping_item_obj->get_meta_data();

          // print_r($serviceobject);

          // echo json_encode($serviceobject);
          for($i=0; $i < sizeof($serviceobject); $i++)
          {
              if($serviceobject[$i]->key == "service_code")
              {
                  $serviceCode = $serviceobject[0]->value;
              }
          }
      }

      return $resultCreate = DelyvaX_Shipping_API::postProcessOrder($shipmentId, $serviceCode);
}


//get driver by extId = 1926
function delyvax_get_personnel($extIdType, $extId) {
    $driver = null;

    try {
        //create order
        //start DelyvaX API
        if (!class_exists('DelyvaX_Shipping_API')) {
            include_once 'includes/delyvax-api.php';
        }

        $result = DelyvaX_Shipping_API::getDrivers($extIdType, $extId);

        if($result)
        {
            $driver = $result;
        }
    } catch (Exception $e) {
        print_r($e);
        // exit;
    }
    return $driver;
}

//create task

//1926

function delyvax_create_task($shipmentId, $trackingNo, $order, $user, $scheduledAt) {
    try {
        $settings = get_option( 'woocommerce_delyvax_settings');

        //create task
        //start DelyvaX API
        if (!class_exists('DelyvaX_Shipping_API')) {
            include_once 'includes/delyvax-api.php';
        }

        //if split_tasks
        if($settings['split_tasks'] == 'yes')
        {
              //get driver
              if(function_exists(dokan_get_seller_id_by_order) && function_exists(dokan_get_store_info))
              {
                  // echo 'function_exists';

                  $seller_id = dokan_get_seller_id_by_order($order->get_id());
                  $store_info = dokan_get_store_info( $seller_id );

                  $product_store_name = $store_info['store_name'];

                  if($seller_id)
                  {
                      $scheduledAt = $scheduledAt->sub(new DateInterval('PT30M'));

                      $extIdType = null;
                      if(isset($settings['ext_id_type']))
                      {
                          $extIdType = $settings['ext_id_type'];
                      }

                      $driver = delyvax_get_personnel($extIdType, $seller_id);

                      if($driver)
                      {
                          $driver_id = $driver["id"];

                          if($driver_id)
                          {
                              $inventories = array();

                              $order_notes = 'Order No: #'.$order->get_id().' <br>';
                              $order_notes = $order_notes.'Date Time: '.$scheduledAt->format('d-m-Y H:i').' (24H) <br>';
                              $order_notes = $order_notes.'Time slot: '.$scheduledAt->format('H:i').'';

                              $count = 0;

                              foreach ( $order->get_items() as $item )
                              {
                                  $product_id = $item->get_product_id();
                                  $product_variation_id = $item->get_variation_id();
                                  $product = $item->get_product();
                                  $product_name = $item->get_name();
                                  $quantity = $item->get_quantity();
                                  $subtotal = $item->get_subtotal();
                                  $total = $item->get_total();
                                  $tax = $item->get_subtotal_tax();
                                  $taxclass = $item->get_tax_class();
                                  $taxstat = $item->get_tax_status();
                                  $allmeta = $item->get_meta_data();
                                  // $somemeta = $item->get_meta( '_whatever', true );
                                  $type = $item->get_type();

                                  $_pf = new WC_Product_Factory();

                                  $product = $_pf->get_product($product_id);

                                  $inventories[$count] = array(
                                      "name" => $product_name,
                                      "type" => "PARCEL", //$type PARCEL / FOOD
                                      "price" => array(
                                          "amount" => $total,
                                          "currency" => $order->get_currency(),
                                      ),
                                      "weight" => array(
                                          "value" => ($product->get_weight()*$quantity),
                                          "unit" => "kg"
                                      ),
                                      "quantity" => $quantity,
                                      "description" => '[Store: '.$product_store_name.'] '.$product_name
                                  );

                                  $total_weight = $total_weight + ($product->get_weight()*$quantity);
                                  $total_price = $total_price + $total;

                                  // $order_notes = $order_notes.'#'.($count+1).'. [Store: '.$store_name.'] '.$product_name.' X '.$quantity.'pcs          <br>';

                                  $count++;
                              }

                              //for task
                              $waypoints = array(array(
                                  "type" => "dropoff",
                                  "scheduledAt" => $scheduledAt->format('c'), //"2019-11-15T12:00:00+0800",
                                  "inventory" => $inventories,
                                  "contact" => array(
                                      "name" => $order->get_shipping_first_name().' '.$order->get_shipping_last_name(),
                                      "email" => $order->get_billing_email(),
                                      "phone" => $order->get_billing_phone(),
                                      "mobile" => $order->get_billing_phone(),
                                      "address1" => $order->get_shipping_address_1(),
                                      "address2" => $order->get_shipping_address_2(),
                                      "city" => $order->get_shipping_city(),
                                      "state" => $order->get_shipping_state(),
                                      "postcode" => $order->get_shipping_postcode(),
                                      "country" => $order->get_shipping_country(),
                                      // "coord" => array(
                                      //     "lat" => "",
                                      //     "lon" => ""
                                      // )
                                  ),
                                  "note"=> $order_notes,
                                  "flag" => array(
                                      "confirmFeedback" => false,
                                      "confirmSignature" => false,
                                  )
                              ));

                              //
                              $price = array(
                                  "amount" => 0,
                                  "currency" => $order->get_currency(),
                              );

                              //create and assign task
                              $resultPostCreateTask = DelyvaX_Shipping_API::postCreateTask($shipmentId, $trackingNo, $waypoints, $price, $driver_id, $order_notes);

                              print_r($resultPostCreateTask);

                              //
                          }
                      }
                  }
              }
        }
        //end
    } catch (Exception $e) {
        print_r($e);
    }
    // exit;
}



//subscribe to webhook here or when save the settings ?
function delyvax_webhook_subscribe() {
    $settings = get_option( 'woocommerce_delyvax_settings');

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
                $settings = get_option( 'woocommerce_delyvax_settings');

                if ($settings['change_order_status'] == 'yes') {

                      //get order id by tracking no
                      //order_tracking.update"
                      $companyId = $data['companyId'];
                      $shipmentId = $data['orderId'];
                      $consignmentNo = $data['consignmentNo'];
                      $statusCode = $data['statusCode'];

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
                          // $order = WC_Order($orders[$i]->get_id());
                          // $order = wc_update_order($orders[$i]->get_id());

                          echo 'order_id'.$orders[$i]->get_id();
                          echo 'status'.$order->get_status();

                          if($statusCode == 200)
                          {
                              if (!empty($order))
                              {
                                  //on the way to pick up
                                  if( !$order->has_status('wc-courier-accepted') )
                                  {
                                      echo 'courier-accepted';

                                      $order->update_status('courier-accepted', 'Order status changed to Courier accepted.', false); // order note is optional, if you want to  add a note to order
                                      // $order->update_status('courier-accepted');

                                      wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-courier-accepted']);

                                      //start update sub orders
                                      $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );

                                      if ( $sub_orders ) {
                                          foreach ($sub_orders as $sub)
                                          {
                                              $sub_order = wc_get_order($sub->ID);
                                              $sub_order->update_status('courier-accepted');
                                              wp_update_post(['ID' => $sub->ID, 'post_status' => 'wc-courier-accepted']);
                                          }
                                      }
                                      // update_post_meta( $this_order_id, $key, $value);
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
                                      echo 'start-collecting';

                                      $order->update_status('start-collecting', 'Order status changed to Pending pick up.', false); // order note is optional, if you want to  add a note to order
                                      // $order->update_status('start-collecting');

                                      wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-start-collecting']);

                                      //start update sub orders
                                      $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );

                                      if ( $sub_orders ) {
                                          foreach ($sub_orders as $sub)
                                          {
                                              $sub_order = wc_get_order($sub->ID);
                                              $sub_order->update_status('start-collecting');
                                              wp_update_post(['ID' => $sub->ID, 'post_status' => 'wc-start-collecting']);
                                          }
                                      }
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
                                      echo 'failed-collection';

                                      $order->update_status('failed-collection', 'Order status changed to Pick up failed.', false); // order note is optional, if you want to  add a note to order
                                      // $order->update_status('failed-collection');

                                      wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-failed-collection']);

                                      //start update sub orders
                                      $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );

                                      if ( $sub_orders ) {
                                          foreach ($sub_orders as $sub)
                                          {
                                              $sub_order = wc_get_order($sub->ID);
                                              $sub_order->update_status('failed-collection');
                                              wp_update_post(['ID' => $sub->ID, 'post_status' => 'wc-failed-collection']);
                                          }
                                      }
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
                                      echo 'collected';

                                      $order->update_status('collected', 'Order status changed to Pick up complete.', false); // order note is optional, if you want to  add a note to order
                                      // $order->update_status('collected');

                                      wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-collected']);

                                      //start update sub orders
                                      $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );

                                      if ( $sub_orders ) {
                                          foreach ($sub_orders as $sub)
                                          {
                                              $sub_order = wc_get_order($sub->ID);
                                              $sub_order->update_status('collected');
                                              wp_update_post(['ID' => $sub->ID, 'post_status' => 'wc-collected']);
                                          }
                                      }
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
                                      echo 'start-delivery';

                                      $order->update_status('start-delivery', 'Order status changed to On the way for delivery.', false); // order note is optional, if you want to  add a note to order
                                      // $order->update_status('start-delivery');

                                      wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-start-delivery']);

                                      //start update sub orders
                                      $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );

                                      if ( $sub_orders ) {
                                          foreach ($sub_orders as $sub)
                                          {
                                              $sub_order = wc_get_order($sub->ID);
                                              $sub_order->update_status('start-delivery');
                                              wp_update_post(['ID' => $sub->ID, 'post_status' => 'wc-start-delivery']);
                                          }
                                      }
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
                                      echo 'failed-delivery';

                                      $order->update_status('failed-delivery', 'Order status changed to Delivery failed.', false); // order note is optional, if you want to  add a note to order
                                      // $order->update_status('failed-delivery');

                                      wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-failed-delivery']);

                                      //start update sub orders
                                      $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );

                                      if ( $sub_orders ) {
                                          foreach ($sub_orders as $sub)
                                          {
                                              $sub_order = wc_get_order($sub->ID);
                                              $sub_order->update_status('failed-delivery');
                                              wp_update_post(['ID' => $sub->ID, 'post_status' => 'wc-failed-delivery']);
                                          }
                                      }
                                      // //end update sub orders
                                  }
                              }
                          }else if($statusCode == 700 || $statusCode == 1000)
                          {
                              if (!empty($order))
                              {
                                  if( !$order->has_status('wc-completed') )
                                  {
                                      echo 'completed';

                                      $order->update_status('completed', 'Order status changed to Completed', false); // order note is optional, if you want to  add a note to order
                                      // $order->update_status('completed');

                                      wp_update_post(['ID' => $order->get_id(), 'post_status' => 'wc-completed']);

                                      //start update sub orders
                                      $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );

                                      if ( $sub_orders ) {
                                          foreach ($sub_orders as $sub)
                                          {
                                              $sub_order = wc_get_order($sub->ID);
                                              $sub_order->update_status('completed');
                                              wp_update_post(['ID' => $sub->ID, 'post_status' => 'wc-completed']);
                                          }
                                      }
                                      //end update sub orders
                                  }
                              }
                          }else if($statusCode == 900)
                          {
                              if (!empty($order))
                              {
                                  //on the way to pick up
                                  if( !$order->has_status('wc-cancelled') )
                                  {
                                      echo 'cancelled';

                                      $order->update_status('cancelled', 'Order status changed to Cancelled.', false); // order note is optional, if you want to  add a note to order

                                      //start update sub orders
                                      $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );

                                      if ( $sub_orders ) {
                                          foreach ($sub_orders as $sub)
                                          {
                                              $sub_order = wc_get_order($sub->ID);
                                              $sub_order->update_status('cancelled');
                                              wp_update_post(['ID' => $sub->ID, 'post_status' => 'cancelled']);
                                          }
                                      }
                                      //end update sub orders
                                  }
                              }
                          }else
                          {
                              echo 'else';
                          }
                      }
                }
            }
        }
    }
}

// Register new status
function delyvax_register_order_statuses() {
    register_post_status( 'wc-preparing', array(
        'label'                     => _x('Preparing', 'Order status', 'default' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Preparing (%s)', 'Preparing (%s)' )
    ) );
    register_post_status( 'wc-ready-to-collect', array(
        'label'                     => _x('Ready to collect', 'Order status', 'default' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Ready to collect (%s)', 'Ready to collect (%s)' )
    ) );
    register_post_status( 'wc-courier-accepted', array(
        'label'                     => _x('Courier accepted', 'Order status', 'default' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Courier accepted (%s)', 'Courier accepted (%s)' )
        // 'label_count'               => _n_noop( 'Courier accepted class="count">(%s)</span>', 'Courier accepted <span class="count">(%s)</span>' )
    ) );
    register_post_status( 'wc-start-collecting', array(
        'label'                     => _x('Pending pick up', 'Order status', 'default' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Pending pick up (%s)', 'Pending pick up (%s)' )
    ) );
    register_post_status( 'wc-collected', array(
        'label'                     => _x('Pick up complete', 'Order status', 'default' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Pick up complete (%s)', 'Pick up complete (%s)' )
    ) );
    register_post_status( 'wc-failed-collection', array(
        'label'                     => _x('Pick up failed', 'Order status', 'default' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Pick up failed (%s)', 'Pick up failed (%s)' )
    ) );
    register_post_status( 'wc-start-delivery', array(
        'label'                     => _x('On the way for delivery', 'Order status', 'default' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'On the way for delivery (%s)', 'On the way for delivery (%s)' )
    ) );
    register_post_status( 'wc-failed-delivery', array(
        'label'                     => _x('Delivery failed', 'Order status', 'default' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Delivery failed (%s)', 'Delivery failed (%s)' )
    ) );
    register_post_status( 'wc-request-refund', array(
        'label'                     => _x('Request refund', 'Order status', 'default' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Request refund (%s)', 'Request refund (%s)' )
    ) );
}
add_action( 'init', 'delyvax_register_order_statuses');


add_filter( 'woocommerce_reports_order_statuses', 'include_custom_order_status_to_reports', 20, 1 );
function include_custom_order_status_to_reports( $statuses ){
    // Adding the custom order status to the 3 default woocommerce order statuses
    return array( 'wc-preparing', 'wc-ready-to-collect', 'wc-courier-accepted',
      'wc-start-collecting', 'wc-collected', 'wc-failed-collection',
      'wc-start-delivery', 'wc-failed-delivery', 'wc-request-refund'
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
            $new_order_statuses['wc-preparing'] = _x('Preparing', 'Order status', 'woocommerce');
            $new_order_statuses['wc-ready-to-collect'] = _x('Ready to collect', 'Order status', 'woocommerce');
            $new_order_statuses['wc-courier-accepted'] = _x('Courier accepted', 'Order status', 'woocommerce');
            $new_order_statuses['wc-start-collecting'] = _x('Pending pick up', 'Order status', 'woocommerce');
            $new_order_statuses['wc-collected'] = _x('Pick up complete', 'Order status', 'woocommerce');
            $new_order_statuses['wc-failed-collection'] = _x('Pick up failed', 'Order status', 'woocommerce');
            $new_order_statuses['wc-start-delivery'] = _x('On the way for delivery', 'Order status', 'woocommerce');
            $new_order_statuses['wc-failed-delivery'] = _x('Delivery failed', 'Order status', 'woocommerce');
            $new_order_statuses['wc-request-refund'] = _x('Request refund', 'Order status', 'woocommerce');
        }
    }

    return $new_order_statuses;
}
add_filter( 'wc_order_statuses', 'delyvax_add_to_order_statuses');
//

// Adding custom status  to admin order list bulk actions dropdown
function delyvax_dropdown_bulk_actions_shop_order( $actions ) {
    $new_actions = array();

    // add new order status before processing
    foreach ($actions as $key => $action) {
        $new_actions[$key] = $action;

        if ('mark_processing' === $key)
        {
            $new_actions['mark_preparing'] = __( 'Mark as Preparing', 'woocommerce');
            $new_actions['mark_ready-to-collect'] = __( 'Mark as Ready to collect', 'woocommerce');
            $new_actions['mark_courier-accepted'] = __( 'Mark as Courier accepted', 'woocommerce');
            $new_actions['mark_start-collecting'] = __( 'Mark as Pending pick up', 'woocommerce');
            $new_actions['mark_collected'] = __( 'Mark as Pick up complete', 'woocommerce');
            $new_actions['mark_failed-collection'] = __( 'Mark as Pick up failed', 'woocommerce');
            $new_actions['mark_start-delivery'] = __( 'Mark as On the way for delivery', 'woocommerce');
            $new_actions['mark_failed-delivery'] = __( 'Mark as Delivery failed', 'woocommerce');
            $new_actions['mark_request-refund'] = __( 'Mark as Request refund', 'woocommerce');
        }
    }

    return $new_actions;
}
add_filter( 'bulk_actions-edit-shop_order', 'delyvax_dropdown_bulk_actions_shop_order', 20, 1 );

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

include_once 'includes/delyvax-webhook.php';

function delyvax_check_cart_weight(){
    $weight = WC()->cart->get_cart_contents_weight();
    $min_weight = 0.000; // kg
    $max_weight = 1000000; // kg

    if($weight > $max_weight){
        wc_add_notice( sprintf( __( 'You have %s kg weight and we allow maximum' . $max_weight . '  kg of weight per order. Reduce the cart weight by removing some items to proceed checkout.', 'default' ), $weight ), 'error');
    }
}

function  delyvax_check_checkout_weight() {
    $weight = WC()->cart->get_cart_contents_weight();
    $min_weight = 0.000; // kg
    $max_weight = 1000000; // kg

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

}

function delyvaxPluginUninstalled() {
    delete_option('delyvax_pricing_enable');
    delete_option('delyvax_limit_service_options');    
    delete_option('delyvax_create_shipment_on_paid');
    delete_option('delyvax_create_shipment_on_confirm');
    delete_option('delyvax_change_order_status');
    delete_option('delyvax_company_id');
    delete_option('delyvax_company_name');    
    delete_option('delyvax_user_id');
    delete_option('delyvax_customer_id');
    delete_option('delyvax_api_token');
    delete_option('delyvax_api_webhook_enable');
    delete_option('delyvax_api_webhook_key');

    delete_option('delyvax_shop_name');
    delete_option('delyvax_shop_mobile');
    delete_option('delyvax_shop_email');
    delete_option('delyvax_shipping_phone');    

    delete_option('delyvax_processing_days');
    delete_option('delyvax_processing_hours');
    delete_option('delyvax_processing_time');
    delete_option('delyvax_item_type');
    delete_option('delyvax_volumetric_constant');
    delete_option('delyvax_weight_option');
    delete_option('delyvax_rate_adjustment_type');

    delete_option('delyvax_insurance_premium');
    delete_option('delyvax_source');
    delete_option('delyvax_include_order_note');
    delete_option('delyvax_cancel_delivery');
    delete_option('delyvax_cancel_order');

    delete_option('wc_settings_delyvax_shipping_rate_adjustment');
    delete_option('delyvax_rate_adjustment_percentage');
    delete_option('delyvax_rate_adjustment_flat');
    delete_option('delyvax_multivendor');

    delete_option('delyvax_free_shipping_type');
    delete_option('delyvax_free_shipping_condition');
    delete_option('delyvax_free_shipping_value');
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

function delyvax_get_order_shipping_method( $order_id ){
    $shipping_method = null;

    $order = wc_get_order( $order_id );
	foreach( $order->get_items( 'shipping' ) as $item_id => $item )
    {
        $shipping_method = $item->get_method_id();
	}
    return $shipping_method;
}


function delyvax_payment_complete( $order_id ){
    $settings = get_option( 'woocommerce_delyvax_settings');

    $order = wc_get_order( $order_id );
    $user = $order->get_user();

    //set pickup date, time and delivery date and time
    delyvax_set_pickup_delivery_time($order);

    if ($settings['create_shipment_on_paid'] == 'yes')
    {
        if($order->get_payment_method() != 'pos_cash') {
            delyvax_create_order($order, $user, true);
        }
    }else if ($settings['create_shipment_on_paid'] == 'nothing')
    {
        //do nothing
    }else {
        if($order->get_payment_method() != 'pos_cash') {
            delyvax_create_order($order, $user, false);
        }
    }
}

function delyvax_change_cod_payment_order_status( $order_status, $order ) {
    $settings = get_option( 'woocommerce_delyvax_settings');

    //set pickup date, time and delivery date and time
    delyvax_set_pickup_delivery_time($order);
    
    $user = $order->get_user();

    if ($settings['create_shipment_on_paid'] == 'yes')
    {
        if($order->get_payment_method() != 'pos_cash') {
            delyvax_create_order($order, $user, true);
        }
    }else if ($settings['create_shipment_on_paid'] == 'nothing')
    {
        //do nothing
    }else {
        if($order->get_payment_method() != 'pos_cash') {
            delyvax_create_order($order, $user, false);
        }
    }
    return 'processing';
}

function delyvax_order_confirmed( $order_id, $old_status, $new_status ) {
    $settings = get_option( 'woocommerce_delyvax_settings');

    $order = wc_get_order( $order_id );
    $user = $order->get_user();

    //set pickup date, time and delivery date and time
    delyvax_set_pickup_delivery_time($order);

    if($order->get_status() == 'processing')
    {
        if ($settings['create_shipment_on_paid'] == 'yes')
        {
            if($order->get_payment_method() != 'pos_cash') {
                delyvax_create_order($order, $user, true);
            }
        }else if ($settings['create_shipment_on_paid'] == 'nothing')
        {
            //do nothing
        }else {
            if($order->get_payment_method() != 'pos_cash') {
                delyvax_create_order($order, $user, false);
            }
        }
    }else if($order->get_status() == 'preparing')
    {
      if ($settings['create_shipment_on_confirm'] == 'yes')
      {
        if($order->get_payment_method() != 'pos_cash') {        
            delyvax_create_order($order, $user, true);
        }
      }else if ($settings['create_shipment_on_confirm'] == 'nothing')
      {
          //do nothing
      }else {
        if($order->get_payment_method() != 'pos_cash') {
            delyvax_create_order($order, $user, false);
        }
      }
    }else if($order->get_status() == 'cancelled')
    {
        //cancel
        if ($settings['cancel_delivery'] == 'yes')
        {
            delyvax_post_cancel_order($order);
        }        
    }
}


function delyvax_set_pickup_delivery_time($order)
{
    $settings = get_option( 'woocommerce_delyvax_settings' );

    $processing_days = $settings['processing_days'];
    $processing_hours = $settings['processing_hours'];
    $processing_time = $settings['processing_time'];

    $gmtoffset = get_option('gmt_offset');
    $stimezone = get_option('timezone_string');

    $dtimezone = new DateTimeZone($stimezone);

    //initialise
    $pickup_date = new DateTime();
    $pickup_date->setTimezone($dtimezone);

    $pickup_time = new DateTime();
    // $pickup_time->setTimezone($dtimezone);

    $delivery_date = new DateTime();
    $delivery_date->setTimezone($dtimezone);

    $delivery_time = new DateTime();
    $delivery_time->setTimezone($dtimezone);

    $dx_delivery_date = null;
    $dx_delivery_time = null;
    $dx_delivery_date_format = null;

    $dx_pickup_date = null;
    $dx_pickup_time = null;
    $dx_pickup_date_format = null;

    $delivery_type = null;
    //

    try {
        //pickup / delivery
        $delivery_type = $order->get_meta( 'delivery_type');
        //if = pickup, take dx_delivery_date from pickup_date //WooDelivery

        //set delivery_date
        if($order->get_meta( 'dx_delivery_date' ) != null)
        {
            $dx_delivery_date = $order->get_meta( 'dx_delivery_date' );

            $delivery_date = new DateTime( '@'.(int)$order->get_meta( 'dx_delivery_date' ));
            $delivery_date->setTimezone($dtimezone);
            $dx_delivery_date_format = $delivery_date->format('d-M-Y');
        }else if($order->get_meta( 'delivery_date' ) != null)
        {
            $delivery_date = new DateTime($order->get_meta( 'delivery_date' ));
            $delivery_date->setTimezone($dtimezone);

            $dx_delivery_date = $delivery_date->getTimestamp();

            $dx_delivery_date_format = $delivery_date->format('d-M-Y');
        }else if($processing_days > 0)
        {
            $delivery_date->modify('+'.$processing_days.' day');

            $dx_delivery_date = $delivery_date->getTimestamp();

            $dx_delivery_date_format = $delivery_date->format('d-M-Y');
        }else {
            // $delivery_date->modify('+0 day');

            $dx_delivery_date = $delivery_date->getTimestamp();

            $dx_delivery_date_format = $delivery_date->format('d-M-Y');
        }

        //set delivery time
        if($order->get_meta( 'dx_delivery_time' ) != null)
        {
            $dx_delivery_time = $order->get_meta( 'dx_delivery_time' );

        }else if($order->get_meta( 'delivery_time' ) != null)
        {
            $w_delivery_time = $order->get_meta( 'delivery_time'); //1440 minutes / 24
            $a_delivery_time = explode(",",$w_delivery_time);

            $timeslot_from_hour = 0;
            $timeslot_from_min = 0;

            if(sizeof($a_delivery_time) > 0)
            {
                $timeslot_from = $a_delivery_time[0]/60; //e.g. 675/60 = 11.25 =  11.15am

                $timeslot_from_hour = floor($timeslot_from);
                $timeslot_from_min = fmod($timeslot_from, 1) * 60;
            }else {
                //set current time add 1 hour
                $delivery_time->add(new DateInterval("PT5M"));

                $timeslot_from_hour = $delivery_time->format('H');
                $timeslot_from_min = $delivery_time->format('i');
            }

            $delivery_time = $delivery_date;
            $delivery_time->setTime($timeslot_from_hour,$timeslot_from_min,00);

            $dx_delivery_time = $delivery_time->format('H:i');
        }else if($processing_days > 0 && $processing_time != '')
        {
            $processing_time = str_replace(":00","",$processing_time);

            $delivery_time = $delivery_date;
            $delivery_time->setTime($processing_time,00,00);

            $dx_delivery_time = $delivery_time->format('H:i');
        }else if($processing_days > 0 && $processing_time == '')
        {
            $delivery_time = $delivery_date;
            $delivery_time->setTime('11',00,00);

            $dx_delivery_time = $delivery_time->format('H:i');
        }else if($processing_days == 0 && $processing_hours > 0)
        {
            $delivery_time->add(new DateInterval("PT".$processing_hours."H"));

            $dx_delivery_time = $delivery_time->format('H:i');
        }else {
            $delivery_time->add(new DateInterval("PT5M"));

            $dx_delivery_time = $delivery_time->format('H:i');
        }

        //set pick up date
        if($order->get_meta( 'dx_pickup_date' ) != null)
        {
            $dx_pickup_date = $order->get_meta( 'dx_pickup_date' );

            $pickup_date = new DateTime( '@'.(int)$order->get_meta( 'dx_pickup_date' ));
            $pickup_date->setTimezone($dtimezone);
            $dx_pickup_date_format = $pickup_date->format('d-M-Y');
        }else if($order->get_meta( 'pickup_date' ) != null)
        {
            $pickup_date = new DateTime( $order->get_meta( 'pickup_date' ));
            $pickup_date->setTimezone($dtimezone);

            $dx_pickup_date = $pickup_date->getTimestamp();

            $dx_pickup_date_format = $pickup_date->format('d-M-Y');
        }else if($processing_days > 0)
        {
            $pickup_date = $delivery_date;
            $dx_pickup_date = $pickup_date->getTimestamp();

            $dx_pickup_date_format = $pickup_date->format('d-M-Y');
        }else {
            $dx_pickup_date = $dx_delivery_date;

            $dx_pickup_date_format = $dx_delivery_date_format;
        }
    } catch(Exception $e) {
          echo 'Message: ' .$e->getMessage();

          $dx_delivery_date = $delivery_date->getTimestamp();
          $dx_delivery_time = $delivery_time->format('H:i');
          $dx_delivery_date_format = $delivery_date->format('d-M-Y');

          $dx_pickup_date = $dx_delivery_date;
          $dx_pickup_time = $dx_delivery_time;
          $dx_pickup_date_format = $dx_delivery_date_format;
    }

    $order->update_meta_data( 'dx_delivery_date', $dx_delivery_date );
    $order->update_meta_data( 'dx_delivery_time', $dx_delivery_time );
    $order->update_meta_data( 'dx_delivery_date_format', $dx_delivery_date_format );

    $order->update_meta_data( 'dx_pickup_date', $dx_pickup_date );
    $order->update_meta_data( 'dx_pickup_time', $dx_pickup_time );
    $order->update_meta_data( 'dx_pickup_date_format', $dx_pickup_date_format );

    $order->save();
}


function delyvax_create_order($order, $user, $process=false) {
    try {
        //create order
        //start DelyvaX API
        if (!class_exists('DelyvaX_Shipping_API')) {
            include_once 'includes/delyvax-api.php';
        }

        //ignore local_pickup
        $shipping_method = delyvax_get_order_shipping_method($order->id);
        if($shipping_method == 'local_pickup') return;
        //

        $DelyvaXOrderID = $order->get_meta( 'DelyvaXOrderID');
        $DelyvaXTrackingCode = $order->get_meta( 'DelyvaXTrackingCode');

        if(!$DelyvaXTrackingCode)
        {
            $resultCreate = delyvax_post_create_order($order, $user, $process);
        }
    } catch (Exception $e) {
        print_r($e);
    }
}

//rewire logic here, API is only for post
function delyvax_post_create_order($order, $user, $process=false) {
      $settings = get_option( 'woocommerce_delyvax_settings' );

      $company_id = $settings['company_id'];
      $company_code = $settings['company_code'];
      $user_id = $settings['user_id'];
      $customer_id = $settings['customer_id'];
      $api_token = $settings['api_token'];
      $processing_days = $settings['processing_days'];
      $processing_hours = $settings['processing_hours'];
      $item_type = ($settings['item_type']) ? $settings['item_type'] : "PARCEL" ;

      $include_order_note = $settings['include_order_note'];

      $multivendor_option = $settings['multivendor'];

      $insurance_premium = $settings['insurance_premium'] ?? '';

      //----delivery date & time (pull from meta data), if not available, set to +next day 8am.
      $gmtoffset = get_option('gmt_offset');

      $stimezone = get_option('timezone_string');

      $dtimezone = new DateTimeZone($stimezone);

      $timeslot_hour = 0;
      $timeslot_min = 0;

      $delivery_date = new DateTime();
      $delivery_date->setTimezone($dtimezone);

      $delivery_time = new DateTime();
      $delivery_time->setTimezone($dtimezone);

      $delivery_type = 'delivery';

      if($order->get_meta( 'delivery_type' ) != null)
      {
          $delivery_type = $order->get_meta( 'delivery_type'); //pickup / delivery
      }

      $delivery_date = new DateTime('@'.(int)$order->get_meta( 'dx_delivery_date' ));
      $delivery_date->setTimezone($dtimezone);

      $dx_delivery_time = $order->get_meta( 'dx_delivery_time' );
      $split_dx_delivery_time = explode( ":", $dx_delivery_time );
      $delivery_time->setTime($split_dx_delivery_time[0],$split_dx_delivery_time[1],00);

      $timeslot_hour = $delivery_time->format('H');
      $timeslot_min = $delivery_time->format('i');

      $scheduledAt = $delivery_date;
      $scheduledAt->setTime($timeslot_hour,$timeslot_min,00);

      //service      
      $serviceCode = "";

      $main_order = $order;

      if($order->parent_id)
      {
          $main_order = wc_get_order($order->parent_id);
      }

      // Iterating through order shipping items
      $DelyvaXServiceCode = $main_order->get_meta( 'DelyvaXServiceCode');

      if($DelyvaXServiceCode)
      {
            $serviceCode = $DelyvaXServiceCode;
      }else {
            foreach( $main_order->get_items( 'shipping' ) as $item_id => $shipping_item_obj )
            {
                $serviceobject = $shipping_item_obj->get_meta_data();
    
                for($i=0; $i < sizeof($serviceobject); $i++)
                {
                    if($serviceobject[$i]->key == "service_code")
                    {
                        $serviceCode = $serviceobject[0]->value;
                        
                        $main_order->update_meta_data( 'DelyvaXServiceCode', $serviceCode );
                        $main_order->save();
                    }
                }
            }
      }          

      //inventory / items
      $count = 0;
      $inventories = array();
      $total_weight = 0;
      $total_dimension = 0;
      $total_price = 0;
      $order_notes = '';
      $total_quantity = 0;

      $store_name = get_bloginfo( 'name' );
      $store_phone = null;
      $store_email = null;
      $store_address_1 = null;
      $store_address_2 = null;
      $store_city = null;
      $store_postcode = null;
      $store_country = null;
      $store_country = null;

      $product_id = null;

      //store info
	  $store_name = $settings['shop_name'];
      $store_email = $settings['shop_email'];
      $store_phone = $settings['shop_mobile'];

      $store_address_1 = get_option( 'woocommerce_store_address');
      $store_address_2 = get_option( 'woocommerce_store_address_2');
      $store_city = get_option( 'woocommerce_store_city');
      $store_postcode = get_option( 'woocommerce_store_postcode');

      $origin_lat = null;
      $origin_lon = null;

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

      $main_order = $order;

      if($include_order_note != 'empty') $order_notes = 'Order No: #'.$main_order->get_id().': ';

      $product_store_name = get_bloginfo( 'name' );

      foreach ( $main_order->get_items() as $item )
      {
          $product_id = $item->get_product_id();
      }

      if($multivendor_option == 'DOKAN')
      {
          if(function_exists('dokan_get_seller_id_by_order') && function_exists('dokan_get_store_info'))
          {
              $seller_id = dokan_get_seller_id_by_order($main_order->get_id());
              $store_info = dokan_get_store_info( $seller_id );
		  	      $user_info = get_userdata($seller_id);
		          $store_info['email'] = $user_info->user_email;

              $product_store_name = $store_info['store_name'];

              if($store_info['store_name']) $store_name = $store_info['store_name'];
              if($store_info['first_name']) $store_first_name = $store_info['first_name'];
              if($store_info['last_name']) $store_last_name = $store_info['last_name'];
              if($store_info['phone']) $store_phone = $store_info['phone'];
              if($store_info['email']) $store_email = $store_info['email'];
              $store_address_1 = $store_info['address']['street_1'];
              $store_address_2 = $store_info['address']['street_2'];
              $store_city = $store_info['address']['city'];
              $store_state = $store_info['address']['state'];
              $store_postcode = $store_info['address']['zip'];
              $store_country = $store_info['address']['country'];

              $origin_lat = isset($store_info['address']['lat']) ? $store_info['address']['lat'] : null;
              $origin_lon = isset($store_info['address']['lon']) ? $store_info['address']['lon'] : null;
          }
      }else if($multivendor_option == 'WCFM')
      {
          if(function_exists('wcfm_get_vendor_id_by_post'))
          {
              $vendor_id = wcfm_get_vendor_id_by_post( $product_id );

              $store_info = get_user_meta( $vendor_id, 'wcfmmp_profile_settings', true );

              if($store_info)
              {
                  $product_store_name = $store_name = $store_info['store_name'];
                  $store_first_name = $store_info['store_name'];
                  $store_last_name = $store_info['store_name'];
                  $store_phone = $store_info['phone'];
                  $store_email = $store_info['store_email'] ? $store_info['store_email'] : $store_info['customer_support']['email'];
                  $store_address_1 = isset( $store_info['address']['street_1'] ) ? $store_info['address']['street_1'] : '';
                  $store_address_2 = isset( $store_info['address']['street_2'] ) ? $store_info['address']['street_2'] : '';
                  $store_city     = isset( $store_info['address']['city'] ) ? $store_info['address']['city'] : '';
                  $store_state    = isset( $store_info['address']['state'] ) ? $store_info['address']['state'] : '';
                  $store_postcode      = isset( $store_info['address']['zip'] ) ? $store_info['address']['zip'] : '';
                  $store_country  = isset( $store_info['address']['country'] ) ? $store_info['address']['country'] : '';

                  $origin_lat = isset($store_info['address']['lat']) ? $store_info['address']['lat'] : null;
                  $origin_lon = isset($store_info['address']['lon']) ? $store_info['address']['lon'] : null;
              }
          }
        }else if($multivendor_option == 'MKING')
        {
            $vendor_id = marketking()->get_product_vendor( $product_id );

            // $company = get_user_meta($vendor_id, 'billing_company', true);      
            $store_name = marketking()->get_store_name_display($vendor_id);              
            // $store_name = get_user_meta($vendor_id, 'marketking_store_name', true);
            $store_first_name = get_user_meta($vendor_id, 'billing_first_name', true);
            $store_last_name = get_user_meta($vendor_id, 'billing_last_name', true);
            $store_phone = get_user_meta($vendor_id, 'billing_phone', true);
            $store_email = marketking()->get_vendor_email($vendor_id);
            // $store_email = get_user_meta($vendor_id, 'billing_email', true);

            $store_address_1 = get_user_meta($vendor_id, 'billing_address_1', true);
            $store_address_2 = get_user_meta($vendor_id, 'billing_address_2', true);
            $store_city = get_user_meta($vendor_id, 'billing_city', true);
            $store_state = get_user_meta($vendor_id, 'billing_postcode', true);
            $store_postcode = get_user_meta($vendor_id, 'billing_state', true);
            $store_country = get_user_meta($vendor_id, 'billing_country', true);
            
            // $origin_lat = isset($store_info['address']['lat']) ? $store_info['address']['lat'] : null;
            // $origin_lon = isset($store_info['address']['lon']) ? $store_info['address']['lon'] : null;
      }else {
          // echo 'no multivendor';
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

          $product_weight = 0;
          $product_length = 0;
          $product_width = 0;
          $product_height = 0;

          //get seller info
          $product_store_name = get_bloginfo( 'name' );

          $_pf = new WC_Product_Factory();

          $product = $_pf->get_product($product_id);

          if( $product->is_type( 'variable' ) ){
              $variation = $_pf->get_product( $product_variation_id );

              if($variation)
              {
                  $product_name = $variation->get_name();
                  $product_weight = delyvax_default_weight(delyvaX_weight_to_kg($variation->get_weight()));
                  $product_length = delyvax_default_dimension(delyvax_dimension_to_cm($variation->get_length()));
                  $product_width = delyvax_default_dimension(delyvax_dimension_to_cm($variation->get_width()));
                  $product_height = delyvax_default_dimension(delyvax_dimension_to_cm($variation->get_height()));
              }else {
                  $product_weight = delyvax_default_weight(delyvaX_weight_to_kg($product->get_weight()));
                  $product_length = delyvax_default_dimension(delyvax_dimension_to_cm($product->get_length()));
                  $product_width = delyvax_default_dimension(delyvax_dimension_to_cm($product->get_width()));
                  $product_height = delyvax_default_dimension(delyvax_dimension_to_cm($product->get_height()));
              }
          }else{
                $product_weight = delyvax_default_weight(delyvaX_weight_to_kg($product->get_weight()));
                $product_length = delyvax_default_dimension(delyvax_dimension_to_cm($product->get_length()));
                $product_width = delyvax_default_dimension(delyvax_dimension_to_cm($product->get_width()));
                $product_height = delyvax_default_dimension(delyvax_dimension_to_cm($product->get_height()));
          }

          $product_description = '['.$product_store_name.'] '.$product_name.' - Order ID #'.$main_order->get_id();

          $inventories[$count] = array(
              "name" => $product_name,
              "type" => $item_type, //$type PARCEL / FOOD
              "price" => array(
                  "amount" => $total,
                  "currency" => $main_order->get_currency(),
              ),
              "weight" => array(
                  "value" => $product_weight,
                  "unit" => 'kg'
              ),
              "dimension" => array(
                  "unit" => 'cm',
                  "width" => $product_width,
                  "length" => $product_length,
                  "height" => $product_height
              ),
              "quantity" => $quantity,
              "description" => $product_description
          );

          $total_weight = $total_weight + ($product_weight*$quantity);

          $total_dimension = $total_dimension + $product_width
                * $product_length
                * $product_height;

          $total_price = $total_price + $subtotal;

          $total_quantity = $total_quantity + $quantity;

          if($include_order_note == 'ordernproduct') $order_notes = $order_notes.'#'.($count+1).'. ['.$store_name.'] '.$product_name.' X '.$quantity.'pcs. ';

          $count++;
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
              "country" => $store_country
              // "coord" => array(
              //     "lat" => "",
              //     "lon" => ""
              // )
          ),
          "note"=> $order_notes
      );

      if($origin_lat && $origin_lon)
      {
          $origin['contact']['coord']['lat'] = $origin_lat;
          $origin['contact']['coord']['lon'] = $origin_lon;
      }
      //

      //destination
      $r_shipping_phone = $order->get_meta( 'shipping_phone' ) ? $order->get_meta( 'shipping_phone' ) : $order->get_meta( '_shipping_phone' );

      $destination_lat = $order->get_meta( 'shipping_lat' ) ? $order->get_meta( 'shipping_lat' ) : null;
      $destination_lon = $order->get_meta( 'shipping_lon' ) ? $order->get_meta( 'shipping_lon' ) : null;

      if($order->get_shipping_address_1() || $order->get_shipping_address_2())
      {
          $destination = array(
              "scheduledAt" => $scheduledAt->format('c'), //"2019-11-15T12:00:00+0800",
              "inventory" => $inventories,
              "contact" => array(
                  "name" => $order->get_shipping_first_name().' '.$order->get_shipping_last_name(),
                  "email" => $order->get_billing_email(),
                  "phone" => $r_shipping_phone ? $r_shipping_phone : $order->get_billing_phone(),
                  "mobile" => $r_shipping_phone ? $r_shipping_phone : $order->get_billing_phone(),
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
              // "note"=> $order_notes
          );
      }else {
          $destination = array(
              "scheduledAt" => $scheduledAt->format('c'), //"2019-11-15T12:00:00+0800",
              "inventory" => $inventories,
              "contact" => array(
                  "name" => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
                  "email" => $order->get_billing_email(),
                  "phone" => $order->get_billing_phone(),
                  "mobile" => $order->get_billing_phone(),
                  "address1" => $order->get_billing_address_1(),
                  "address2" => $order->get_billing_address_2(),
                  "city" => $order->get_billing_city(),
                  "state" => $order->get_billing_state(),
                  "postcode" => $order->get_billing_postcode(),
                  "country" => $order->get_billing_country(),
                  // "coord" => array(
                  //     "lat" => "",
                  //     "lon" => ""
                  // )
              ),
              // "note"=> $order_notes
          );
      }

      if($destination_lat && $destination_lon)
      {
          $destination['contact']['coord']['lat'] = $destination_lat;
          $destination['contact']['coord']['lon'] = $destination_lon;
      }
      //

      //calculate volumetric weight
      $total_actual_weight = delyvax_default_weight(delyvaX_weight_to_kg($total_weight));
      $total_weight = $total_actual_weight;

      $weight = array(
        "value" => $total_weight,
        "unit" => 'kg'
      );

      //
      $cod = array(
        "id"=> -1,
        "qty"=> 1,
        "value"=> $total_price
      );

      $insurance = array(
        "id"=> -3,
        "qty"=> 1,
        "value"=> $total_price
      );

      $addons = array();

      if($order->get_payment_method() == 'cod')
      {
          array_push($addons, $cod);
      }

      if($insurance_premium == 'yes')
      {
          array_push($addons, $insurance);
      }

      $referenceNo = $main_order->get_id();
      //
      
      $DelyvaXOrderID = $order->get_meta( 'DelyvaXOrderID');

      if($DelyvaXOrderID)
      {
            $shipmentId = $DelyvaXOrderID;
      }else {
            $resultCreate = DelyvaX_Shipping_API::postCreateOrder($order, $origin, $destination, $weight, $serviceCode, $order_notes, $addons, $referenceNo);

            if($resultCreate)
            {
                $shipmentId = $resultCreate["id"];

                $order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
                $order->save();
            }
      }

    if($process)
    {
        $resultProcess = delyvax_post_process_order($order, $user, $shipmentId);

        if($resultProcess)
        {
            $trackingNo = $resultProcess["consignmentNo"];
            $nanoId = $resultProcess["nanoId"];

            $main_order = $order;

            $main_order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
            $main_order->update_meta_data( 'DelyvaXTrackingCode', $trackingNo );
            $main_order->update_meta_data( 'DelyvaXTrackingShort', $nanoId );
            $main_order->save();

            // $main_order->update_status('wc-ready-to-collect');
            // $main_order->update_status('wc-ready-to-collect', '<a href="https://api.delyva.app/v1.0/order/'.$shipmentId.'/label?companyId='.$company_id.'" target="_blank">Print label</a>.', false);
            // $main_order->update_status('wc-ready-to-collect', '<a href="https://'.$company_code.'.delyva.app/customer/strack?trackingNo='.$trackingNo.'" target="_blank">Track</a>.', false);

            $main_order->update_status('wc-ready-to-collect', 'Delivery order number: '.$trackingNo.' - <a href="https://api.delyva.app/v1.0/order/'.$shipmentId.'/label?companyId='.$company_id.'" target="_blank">Print label</a> - <a href="https://'.$company_code.'.delyva.app/customer/strack?trackingNo='.$trackingNo.'" target="_blank">Track</a>.', false);

            $consignmentNo = $trackingNo;

            //store price discount or markup
            if($resultProcess["price"])
            {
                $deliveryPrice = $resultProcess['price']['amount'];
                $deliveryMarkup = 0;
                $deliveryDiscount = 0;

                $rate_adjustment_type = $settings['rate_adjustment_type'] ?? 'discount';

                $ra_percentage = $settings['rate_adjustment_percentage'] ?? 1;
                $percentRate = $ra_percentage / 100 * $deliveryPrice;

                $flatRate = $settings['rate_adjustment_flat'] ?? 0;

                if($rate_adjustment_type == 'markup')
                {
                    $deliveryMarkup = round($percentRate + $flatRate, 2);
                }else {
                    $deliveryDiscount = round($percentRate + $flatRate, 2);
                }

                $main_order->update_meta_data( 'DelyvaXDeliveryPrice', $deliveryPrice );
                $main_order->update_meta_data( 'DelyvaXMarkup', $deliveryMarkup );
                $main_order->update_meta_data( 'DelyvaXDiscount', $deliveryDiscount );
                $main_order->save();
            }

            //handle free shipping
            $free_shipping_type = $settings['free_shipping_type'] ?? '';
            $free_shipping_condition = $settings['free_shipping_condition'] ?? '';
            $free_shipping_value = $settings['free_shipping_value'] ?? '0';

            if($free_shipping_type == 'total_quantity')
            {
                if($free_shipping_condition == '>')
                {
                    if($total_quantity > $free_shipping_value)
                    {
                        $deliveryDiscount = $deliveryPrice;
                    }
                }else if($free_shipping_condition == '>=')
                {
                    if($total_quantity >= $free_shipping_value)
                    {
                        $deliveryDiscount = $deliveryPrice;
                    }
                }else if($free_shipping_condition == '==')
                {
                    if($total_quantity == $free_shipping_value)
                    {
                        $deliveryDiscount = $deliveryPrice;
                    }
                }else if($free_shipping_condition == '<=')
                {
                    if($total_quantity <= $free_shipping_value)
                    {
                        $deliveryDiscount = $deliveryPrice;
                    }
                }else if($free_shipping_condition == '<')
                {
                    if($total_quantity < $free_shipping_value)
                    {
                        $deliveryDiscount = $deliveryPrice;
                    }
                }
            }else if($free_shipping_type == 'total_amount')
            {
                if($free_shipping_condition == '>')
                {
                    if($total_price > $free_shipping_value)
                    {
                        $deliveryDiscount = $deliveryPrice;
                    }
                }else if($free_shipping_condition == '>=')
                {
                    if($total_price >= $free_shipping_value)
                    {
                        $deliveryDiscount = $deliveryPrice;
                    }
                }else if($free_shipping_condition == '==')
                {
                    if($total_price == $free_shipping_value)
                    {
                        $deliveryDiscount = $deliveryPrice;
                    }
                }else if($free_shipping_condition == '<=')
                {
                    if($total_price <= $free_shipping_value)
                    {
                        $deliveryDiscount = $deliveryPrice;
                    }
                }else if($free_shipping_condition == '<')
                {
                    if($total_price < $free_shipping_value)
                    {
                        $deliveryDiscount = $deliveryPrice;
                    }
                }
            }

            $main_order->update_meta_data( 'DelyvaXDiscount', $deliveryDiscount );
            $main_order->save();
            ////end free shipping

            // no need - vendor to process sub order separately
            //save tracking no into order to all parent order and suborders
            /*
            $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );

            if( sizeof($sub_orders) > 0 )
            {
                $main_order = wc_get_order($order->get_id());

                $main_order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
                $main_order->update_meta_data( 'DelyvaXTrackingCode', $trackingNo );
                $main_order->save();

                $main_order->update_status('wc-ready-to-collect');

                $count = 0;
                foreach ($sub_orders as $sub)
                {
                    $sub_order = wc_get_order($sub->ID);

                    $sub_order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
                    $sub_order->update_meta_data( 'DelyvaXTrackingCode', $trackingNo );
                    $sub_order->save();

                    $sub_order->update_status('wc-ready-to-collect');

                    $consignmentNo = $trackingNo."-".($count+1);


                    $count++;
                }
            }else {
                $main_order = $order;

                $main_order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
                $main_order->update_meta_data( 'DelyvaXTrackingCode', $trackingNo );
                $main_order->save();

                $main_order->update_status('wc-ready-to-collect');

                $consignmentNo = $trackingNo."-1";
            }*/
        }
    }else {
            $main_order = $order;

            $main_order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
            // $main_order->update_meta_data( 'DelyvaXTrackingCode', $trackingNo );
            $main_order->save();

            $consignmentNo = $trackingNo;

            // no need vendor to process sub order separately
            //save tracking no into order to all parent order and suborders
            // $sub_orders = get_children( array( 'post_parent' => $order->get_id(), 'post_type' => 'shop_order' ) );
            //
            // if( sizeof($sub_orders) > 0 )
            // {
            //     $main_order = wc_get_order($order->get_id());
            //
            //     $main_order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
            //     // $main_order->update_meta_data( 'DelyvaXTrackingCode', $trackingNo );
            //     $main_order->save();
            //
            //     $count = 0;
            //     foreach ($sub_orders as $sub)
            //     {
            //         $sub_order = wc_get_order($sub->ID);
            //
            //         $sub_order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
            //         // $sub_order->update_meta_data( 'DelyvaXTrackingCode', $trackingNo );
            //         $sub_order->save();
            //
            //         $consignmentNo = $trackingNo."-".($count+1);
            //
            //         $count++;
            //     }
            // }else {
            //     $main_order = $order;
            //
            //     $main_order->update_meta_data( 'DelyvaXOrderID', $shipmentId );
            //     // $main_order->update_meta_data( 'DelyvaXTrackingCode', $trackingNo );
            //     $main_order->save();
            //
            //     $consignmentNo = $trackingNo."-1";
            // }
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
      $DelyvaXServiceCode = $main_order->get_meta( 'DelyvaXServiceCode');

      if($DelyvaXServiceCode)
      {
            $serviceCode = $DelyvaXServiceCode;
      }else {
            foreach( $main_order->get_items( 'shipping' ) as $item_id => $shipping_item_obj )
            {
                $serviceobject = $shipping_item_obj->get_meta_data();
    
                for($i=0; $i < sizeof($serviceobject); $i++)
                {
                    if($serviceobject[$i]->key == "service_code")
                    {
                        $serviceCode = $serviceobject[0]->value;
                    }
                }
            }
      }

      return $resultProcess = DelyvaX_Shipping_API::postProcessOrder($order, $shipmentId, $serviceCode);
}

//rewire logic here, API is only for post
function delyvax_post_cancel_order($order) {
    if (!class_exists('DelyvaX_Shipping_API')) {
        include_once 'includes/delyvax-api.php';
    }
    
    $shipmentId = $order->get_meta( 'DelyvaXOrderID');

    if($shipmentId)
    {
        return $resultCancel = DelyvaX_Shipping_API::postCancelOrder($order, $shipmentId);
    }
}

function delyvaX_weight_to_kg($weight)
{
    $weight_unit = get_option('woocommerce_weight_unit');
    // convert other unit into kg

    if($weight > 0)
    {
        if ($weight_unit != 'kg') {
            if ($weight_unit == 'g') {
                return $weight * 0.001;
            } else if ($weight_unit == 'lbs') {
                return $weight * 0.453592;
            } else if ($weight_unit == 'oz') {
                return $weight * 0.0283495;
            }
        }
    }
    // already kg
    return $weight;
}

function delyvax_dimension_to_cm($length)
{
  $dimension_unit = get_option('woocommerce_dimension_unit');

  // convert other units into cm
  if($length > 0)
  {
      if ($dimension_unit != 'cm') {
          if ($dimension_unit == 'm') {
              return $length * 100;
          } else if ($dimension_unit == 'mm') {
              return $length * 0.1;
          } else if ($dimension_unit == 'in') {
              return $length * 2.54;
          } else if ($dimension_unit == 'yd') {
              return $length * 91.44;
          }
      }
  }
  // already in cm
  return $length;
}

function delyvax_default_weight($weight)
{
    // default dimension to 1 if it is 0
    return $weight > 0 ? $weight : 1;
}

function delyvax_default_dimension($length)
{
    // default dimension to 1 if it is 0
    return $length > 0 ? $length : 0;
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
        'label'                     => _x('Package is Ready', 'Order status', 'default' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Package is Ready (%s)', 'Package is Ready (%s)' )
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
            $new_order_statuses['wc-ready-to-collect'] = _x('Package is Ready', 'Order status', 'woocommerce');
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
            $new_actions['mark_ready-to-collect'] = __( 'Mark as Package is Ready', 'woocommerce');
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

// Add new column(s) to the "My Orders" table in the account.
function filter_woocommerce_account_orders_columns( $columns ) {
    $new_columns = array();

    foreach ( $columns as $column_name => $column_info ) {
        $new_columns[ $column_name ] = $column_info;
        if ( 'order-total' === $column_name ) {            
            $new_columns['order_track'] = __( 'Track', 'woocommerce' );
        }
    }

    return $new_columns;
}

add_filter( 'woocommerce_account_orders_columns', 'filter_woocommerce_account_orders_columns', 10, 1 );

// Adds data to the custom column in "My Account > Orders"
function filter_woocommerce_my_account_my_orders_column_order_track( $order ) {
    $settings = get_option( 'woocommerce_delyvax_settings' );
    $company_code = $settings['company_code'];

    $DelyvaXTrackingCode = $order->get_meta( 'DelyvaXTrackingCode');
    $DelyvaXTrackingShort = $order->get_meta( 'DelyvaXTrackingShort');
    $DelyvaXPersonnel = $order->get_meta( 'DelyvaXPersonnel');
    
    $url = 'https://'.$company_code.'.delyva.app/customer/strack?trackingNo='.$DelyvaXTrackingCode;
    $shorturl = 'https://'.$company_code.'.delyva.app/customer/etrack/'.$DelyvaXTrackingShort;

    if($DelyvaXTrackingShort)
    {
        $theurl = $shorturl;
    }else {
        $theurl = $url;
    }

    echo '<a href="'.$theurl.'" target="_blank" >'.$DelyvaXTrackingCode.'</a>';

    if($DelyvaXPersonnel && !is_array($DelyvaXPersonnel))
    {
        $personnelInfo = json_decode($DelyvaXPersonnel);

        // var_dump($personnelInfo);

        $personnelName = $personnelInfo->name;
        $personnelPhone = $personnelInfo->phone;

        echo '<br/><a href="tel:+'.$personnelPhone.'" target="_blank" >'.$personnelName.'</a>';
    }

}
add_action( 'woocommerce_my_account_my_orders_column_order_track', 'filter_woocommerce_my_account_my_orders_column_order_track', 10, 1 );

/* add custom column under order listing page */
/**
 * Add 'track' column header to 'Orders' page immediately after 'status' column.
 *
 * @param string[] $columns
 * @return string[] $new_columns
 */
function sv_wc_cogs_add_order_profit_column_header( $columns ) {

    $new_columns = array();

    foreach ( $columns as $column_name => $column_info ) {
        $new_columns[ $column_name ] = $column_info;
        if ( 'order_total' === $column_name ) {            
            $new_columns['order_track'] = __( 'Track', 'woocommerce' );
        }
    }

    return $new_columns;
}
add_filter( 'manage_edit-shop_order_columns', 'sv_wc_cogs_add_order_profit_column_header', 20 );

/**
 * Add 'track' column content to 'Orders' page immediately after 'Total' column.
 *
 * @param string[] $column name of column being displayed
 */
function sv_wc_cogs_add_order_profit_column_order_track( $column ) {
    global $post;

    if ( 'order_track' === $column ) {    
        // $company_name = !empty(get_post_meta($post->ID,'track',true)) ? get_post_meta($post->ID,'track',true) : 'N/A';
        
        // echo $company_name;
        
        $settings = get_option( 'woocommerce_delyvax_settings' );
        $company_code = $settings['company_code'];

        $DelyvaXTrackingCode = !empty(get_post_meta($post->ID,'DelyvaXTrackingCode',true)) ? get_post_meta($post->ID,'DelyvaXTrackingCode',true) : '';
        $DelyvaXTrackingShort = !empty(get_post_meta($post->ID,'DelyvaXTrackingShort',true)) ? get_post_meta($post->ID,'DelyvaXTrackingShort',true) : '';
        $DelyvaXLabelUrl = !empty(get_post_meta($post->ID,'DelyvaXLabelUrl',true)) ? get_post_meta($post->ID,'DelyvaXLabelUrl',true) : '';
        $DelyvaXPersonnel = !empty(get_post_meta($post->ID,'DelyvaXPersonnel',true)) ? get_post_meta($post->ID,'DelyvaXPersonnel',true) : '';
        
        $url = 'https://'.$company_code.'.delyva.app/customer/strack?trackingNo='.$DelyvaXTrackingCode;
        $shorturl = 'https://'.$company_code.'.delyva.app/customer/etrack/'.$DelyvaXTrackingShort;

        if($DelyvaXTrackingShort)
        {
            $theurl = $shorturl;
        }else {
            $theurl = $url;
        }
        
        echo '<a href="'.$theurl.'" target="_blank" >'.$DelyvaXTrackingCode.'</a>';
        if($DelyvaXLabelUrl)
        {
            echo '<br/>';
            echo '<a href="'.$DelyvaXLabelUrl.'" target="_blank" >Print Label</a>';
        }

        if($DelyvaXPersonnel && !is_array($DelyvaXPersonnel))
        {
            $personnelInfo = json_decode($DelyvaXPersonnel);
    
            // var_dump($personnelInfo);
    
            $personnelName = $personnelInfo->name;
            $personnelPhone = $personnelInfo->phone;
    
            echo '<br/><a href="tel:+'.$personnelPhone.'" target="_blank" >'.$personnelName.'</a>';
        }
    }
    
}
add_action( 'manage_shop_order_posts_custom_column', 'sv_wc_cogs_add_order_profit_column_order_track' );

// Shipping field on my account edit-addresses and checkout
function delyvax_filter_woocommerce_shipping_fields( $fields ) {   
    $settings = get_option( 'woocommerce_delyvax_settings');
    $is_shipping_phone = $settings['shipping_phone'];

    if($is_shipping_phone == 'yes')
    {
        $fields['shipping_phone'] = array(
            'label' => __( 'Shipping Phone', 'woocommerce' ),
            'required' => true,
            'class' => array( 'form-row-wide' ),
            'priority'    => 4
        );
    }    
    
    return $fields;
}
add_filter( 'woocommerce_shipping_fields' , 'delyvax_filter_woocommerce_shipping_fields', 10, 1 ); 

// Display on the order edit page (backend)
function delyvax_action_woocommerce_admin_order_data_after_shipping_address( $order ) {
    $settings = get_option( 'woocommerce_delyvax_settings');
    $is_shipping_phone = $settings['shipping_phone'];

    if($is_shipping_phone == 'yes')
    {
        if ( $value = $order->get_meta( '_shipping_phone' ) ) {
            echo '<p><strong>' . __( 'Shipping Phone', 'woocommerce' ) . ':</strong> ' . $value . '</p>';
        }
    }
}
add_action( 'woocommerce_admin_order_data_after_shipping_address', 'delyvax_action_woocommerce_admin_order_data_after_shipping_address', 10, 1 );

// Display on email notifications
// function filter_woocommerce_email_order_meta_fields( $fields, $sent_to_admin, $order ) {
//     $settings = get_option( 'woocommerce_delyvax_settings');
//     $is_shipping_phone = $settings['shipping_phone'];
    
//     if($is_shipping_phone == 'yes')
//     {
//         // Get meta
//         $shipping_phone = $order->get_meta( '_shipping_phone' );
//     }
// }

//

<?php

if (!defined('WPINC')) {
    die;
}
if (!class_exists('DelyvaX_Shipping_API')) {
    class DelyvaX_Shipping_API
    {
        private static $api_endpoint = "https://api.delyva.app";

        //instant quote
        public static function getPriceQuote($origin, $destination, $weight)
        {
            $url = Self::$api_endpoint . "/service/instantQuote/";// . trim(esc_attr($settings['integration_id']), " ");

            $settings = get_option( 'woocommerce_delyvax_settings' );

            $company_id = $settings['company_id'];
            $user_id = $settings['user_id'];
            $customer_id = $settings['customer_id'];
            $api_token = $settings['api_token'];

            // $origin = array(
            //     "address1" => "Suite 8.0.1, Level 8",
            //     "address2" => "Menara Binjai, No.2, Jalan Binjai",
            //     "city" => "Kuala Lumpur",
            //     "state" => "WP Kuala Lumpur",
            //     "postcode" => "50450",
            //     "country" => "MY",
            //     "coord" => array(
            //         "lat" => "",
            //         "lon" => ""
            //     )
            // );

            // $destination = array(
            //     "address1" => "28 Jalan 5",
            //     "address2" => "Taman Mesra",
            //     "city" => "Kajang",
            //     "state" => "Selangor",
            //     "postcode" => "43000",
            //     "country" => "MY",
            //     "coord" => array(
            //         "lat" => "",
            //         "lon" => ""
            //     )
            // );

            $postRequestArr = [
                // 'companyId' => $company_id,
                // 'userId' => $user_id,
                'customerId' => $customer_id,
                'origin' => $origin,
                'destination' => $destination,
                "weight" => $weight,
            ];

            // print_r(json_encode($postRequestArr));

            $response = wp_remote_post($url, array(
                'headers' => array(
                  'content-type' => 'application/json',
                  'X-Delyvax-Access-Token' => $api_token
                ),
                'body' => json_encode($postRequestArr),
                'method' => 'POST',
                'timeout' => 25
            ));

            // print_r($response['body']);
            // exit;

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                if ($error_message == 'fsocket timed out') {
                    throw new Exception("Sorry, the shipping rates are currently unavailable, please refresh the page or try again later");
                } else {
                    throw new Exception("Sorry, something went wrong with the shipping rates. If the problem persists, please contact us!");
                }
            } else {
                if ($response['response']['code'] == 200) {
                    $body = json_decode($response['body'], true);
                    return $body['data'];
                } else {
                    throw new Exception("Sorry, something went wrong with the shipping rates. If the problem persists, please contact us!");
                }
            }
        }

        public static function postCreateOrder($order, $user)
        {
              $url = Self::$api_endpoint . "/order";// . trim(esc_attr($settings['integration_id']), " ");

              $settings = get_option( 'woocommerce_delyvax_settings' );

              $company_id = $settings['company_id'];
              $user_id = $settings['user_id'];
              $customer_id = $settings['customer_id'];
              $api_token = $settings['api_token'];

              // echo 'order';
              // var_dump($order);
              // echo 'user';
              // var_dump($user);

              //----delivery date & time (pull from woo delivery), if not available, set to +next day 8am.

              $gmtoffset = get_option('gmt_offset');
              // echo '<br>';
              $timezones = get_option('timezone_string');
              // echo '<br>';

              $dtimezone = new DateTimeZone($timezones);

              $delivery_date = new DateTime();
              $delivery_date->setTimezone($dtimezone);
              // $delivery_date->modify('+1 day');
              $delivery_date->format('d-m-Y H:i:s');
              // echo '<br>';
              if($order->get_meta( 'delivery_date' ) != null)
              {
                  $delivery_date = new DateTime('@' .$order->get_meta( 'delivery_date' ));
                  $delivery_date->setTimezone($dtimezone);
                  // $delivery_type = $order->get_meta( 'delivery_type' );
              }
              $delivery_date->format('d-m-Y H:i:s');
              // echo '<br>';

              // echo $delivery_time = '08:00'; //8AM
              $delivery_time = date("H", strtotime("+1 hours"));
              // echo '<br>';
              if($order->get_meta( 'delivery_time' ) != null)
              {
                  $w_delivery_time = $order->get_meta( 'delivery_time' ); //1440 minutes / 24
                  // echo '<br>';

                  $a_delivery_time = explode(",",$w_delivery_time);
                  if(sizeof($a_delivery_time) > 0)
                  {
                      $a_delivery_time[0].'<br>';
                      $delivery_time = $a_delivery_time[0]/60;
                  }

              }
              $delivery_time;
              // echo '<br>';

              $delivery_type = 'delivery';
              // echo '<br>';
              if($order->get_meta( 'delivery_type' ) != null)
              {
                  $delivery_type = $order->get_meta( 'delivery_type' );
              }
              // echo $delivery_type;

              // $my_date_time = date("Y-m-d H:i:s", strtotime("+1 hours"));

              $scheduledAt = $delivery_date;
              $scheduledAt->setTime($delivery_time,00,00);

              $delivery_date->format('d-m-Y H:i:s');
              // echo '<br>';

              $scheduledAt = $scheduledAt->format('c');
              // echo '<br>';

              // $scheduledAt = (new DateTime($my_date_time))->format('c');
              //2020-06-10T23:13:51-04:00 / "scheduledAt": "2019-11-15T12:00:00+0800", //echo date_format(date_create('17 Oct 2008'), 'c');
              // echo $scheduledAt;
              // exit;


              //service
              $serviceCode = "";

              // Iterating through order shipping items
              foreach( $order->get_items( 'shipping' ) as $item_id => $shipping_item_obj ){
                  $serviceobject = $shipping_item_obj->get_meta_data();

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

              foreach ( $order->get_items() as $item )
              {
                  $product_name = $item->get_name();
                  $product_id = $item->get_product_id();
                  $product_variation_id = $item->get_variation_id();

                  $product_id = $item->get_product_id();
                  $variation_id = $item->get_variation_id();
                  $product = $item->get_product();
                  $name = $item->get_name();
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
                      "name" => $name,
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
                      "description" => $order_notes
                  );

                  $total_weight = $total_weight + ($product->get_weight()*$quantity);
                  $total_price = $total_price + $total;

                  $order_notes = $order_notes.'#'.($count+1).'. '.$name.' X '.$quantity.'pcs \n';

                  $count++;
              }

              //echo json_encode($inventories);

              // echo $order_notes;

              //origin
              // The main address pieces:
              $store_address     = get_option( 'woocommerce_store_address' );
              $store_address_2   = get_option( 'woocommerce_store_address_2' );
              $store_city        = get_option( 'woocommerce_store_city' );
              $store_postcode    = get_option( 'woocommerce_store_postcode' );

              // The country/state
              $store_raw_country = get_option( 'woocommerce_default_country' );

              // Split the country/state
              $split_country = explode( ":", $store_raw_country );

              // Country and state separated:
              $store_country = $split_country[0];
              $store_state   = $split_country[1];

              //TODO! Origin!
              $origin = array(
                  "scheduledAt" => $scheduledAt, //"2019-11-15T12:00:00+0800",
                  "inventory" => $inventories,
                  "contact" => array(
                      "name" => $order->get_shipping_first_name().' '.$order->get_shipping_last_name(),
                      "email" => $order->get_billing_email(),
                      "phone" => $order->get_billing_phone(),
                      "mobile" => $order->get_billing_phone(),
                      "address1" => $store_address,
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
                  "scheduledAt" => $scheduledAt, //"2019-11-15T12:00:00+0800",
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

              $postRequestArr = [
                  // 'companyId' => $company_id,
                  // 'userId' => $user_id,
                  "customerId" => $customer_id,
                  // "consignmentNo": "CJ00000007MY",
                  "process" => false,
                  "serviceCode" => $serviceCode,
                  'origin' => $origin,
                  'destination' => $destination
              ];

              $response = wp_remote_post($url, array(
                  'headers' => array(
                    'content-type' => 'application/json',
                    'X-Delyvax-Access-Token' => $api_token
                  ),
                  'body' => json_encode($postRequestArr),
                  'method' => 'POST',
                  'timeout' => 25
              ));

              // echo json_encode($postRequestArr);
              // echo json_encode($response);
              // exit;

              if (is_wp_error($response)) {
                  $error_message = $response->get_error_message();
                  if ($error_message == 'fsocket timed out') {
                      throw new Exception("Sorry, unable to create shipment, please try again later");
                  } else {
                      throw new Exception("Sorry, something went wrong with the API. If the problem persists, please contact us!");
                  }
              } else {
                  if ($response['response']['code'] == 200) {
                      $body = json_decode($response['body'], true);
                      return $body['data'];
                  } else {
                      throw new Exception("Sorry, something went wrong with the API. If the problem persists, please contact us!");
                  }
              }
              ///
        }

        public static function postProcessOrder($order, $user, $shipmentId)
        {
              $url = Self::$api_endpoint . "/order/:orderId/process";// . trim(esc_attr($settings['integration_id']), " ");

              $url = str_replace(":orderId", $shipmentId, $url);

              $settings = get_option( 'woocommerce_delyvax_settings' );

              $company_id = $settings['company_id'];
              $user_id = $settings['user_id'];
              $customer_id = $settings['customer_id'];
              $api_token = $settings['api_token'];

              //service
              $serviceCode = "";

              // Iterating through order shipping items
              foreach( $order->get_items( 'shipping' ) as $item_id => $shipping_item_obj ){
                  $serviceobject = $shipping_item_obj->get_meta_data();

                  // echo json_encode($serviceobject);
                  for($i=0; $i < sizeof($serviceobject); $i++)
                  {
                      if($serviceobject[$i]->key == "service_code")
                      {
                          $serviceCode = $serviceobject[0]->value;
                      }

                  }
              }

              $postRequestArr = [
                  'orderId' => $shipmentId,
                  "serviceCode" => $serviceCode,
                  "skipQueue" => true,
              ];

              $response = wp_remote_post($url, array(
                  'headers' => array(
                    'content-type' => 'application/json',
                    'X-Delyvax-Access-Token' => $api_token
                  ),
                  'body' => json_encode($postRequestArr),
                  'method' => 'POST',
                  'timeout' => 25
              ));

              echo json_encode($postRequestArr);
              echo json_encode($response);

              if (is_wp_error($response)) {
                  $error_message = $response->get_error_message();
                  if ($error_message == 'fsocket timed out') {
                      throw new Exception("Sorry, unable to create shipment, please try again later");
                  } else {
                      throw new Exception("Sorry, something went wrong with the API. If the problem persists, please contact us!");
                  }
              } else {
                  if ($response['response']['code'] == 200) {
                      $body = json_decode($response['body'], true);
                      return $body['data'];
                  } else {
                      throw new Exception("Sorry, something went wrong with the API. If the problem persists, please contact us!");
                  }
              }
              ///
        }

        public static function getTrackOrderByOrderId($shipmentId)
        {
            $settings = get_option( 'woocommerce_delyvax_settings' );

            $company_id = $settings['company_id'];
            $user_id = $settings['user_id'];
            $customer_id = $settings['customer_id'];
            $api_token = $settings['api_token'];

            $url = Self::$api_endpoint . "/order/:orderId/track";

            $url = str_replace(":orderId", $shipmentId, $url);
            // echo $url;

            $response = wp_remote_post($url, array(
                'headers' => array(
                  'content-type' => 'application/json',
                  'X-Delyvax-Access-Token' => $api_token
                ),
                // 'body' => json_encode($postRequestArr),
                'method' => 'GET',
                'timeout' => 25
            ));

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                if ($error_message == 'fsocket timed out') {
                    throw new Exception("Sorry, unable to create shipment, please try again later");
                } else {
                    throw new Exception("Sorry, something went wrong with the API. If the problem persists, please contact us!");
                }
            } else {
                if ($response['response']['code'] == 200) {
                    $body = json_decode($response['body'], true);

                    return $body;
                } else {
                    throw new Exception("Sorry, something went wrong with the API. If the problem persists, please contact us!");
                }
            }
        }

        public static function getTrackOrderByTrackingNo($trackingNo)
        {
            $settings = get_option( 'woocommerce_delyvax_settings' );

            $company_id = $settings['company_id'];
            $user_id = $settings['user_id'];
            $customer_id = $settings['customer_id'];
            $api_token = $settings['api_token'];

            $url = Self::$api_endpoint . "/order/track/:consignmentNo?companyId=".$company_id;

            $url = str_replace(":consignmentNo", $trackingNo, $url);

            $response = wp_remote_post($url, array(
                'headers' => array(
                  'content-type' => 'application/json',
                  'X-Delyvax-Access-Token' => $api_token
                ),
                // 'body' => json_encode($postRequestArr),
                'method' => 'GET',
                'timeout' => 25
            ));

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                if ($error_message == 'fsocket timed out') {
                    throw new Exception("Sorry, unable to create shipment, please try again later");
                } else {
                    throw new Exception("Sorry, something went wrong with the API. If the problem persists, please contact us!");
                }
            } else {
                if ($response['response']['code'] == 200) {
                    $body = json_decode($response['body'], true);

                    return $body['data'];
                } else {
                    throw new Exception("Sorry, something went wrong with the API. If the problem persists, please contact us!");
                }
            }
        }

        public static function postCreateWebhook()
        {
            $settings = get_option( 'woocommerce_delyvax_settings' );

            $company_id = $settings['company_id'];
            $user_id = $settings['user_id'];
            $customer_id = $settings['customer_id'];
            $api_token = $settings['api_token'];

            $url = Self::$api_endpoint . "//webhook/";

            // get_option( 'woocommerce_store_url' );
            $store_url = get_site_url(); //"https://matdespatch.com/my/makan";

            $postRequestArr = array(
                "event" => "order_tracking.update",
                "url" => $store_url,
            );

            $response = wp_remote_post($url, array(
                'headers' => array(
                  'content-type' => 'application/json',
                  'X-Delyvax-Access-Token' => $api_token
                ),
                'body' => json_encode($postRequestArr),
                'method' => 'POST',
                'timeout' => 25
            ));

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                if ($error_message == 'fsocket timed out') {
                    throw new Exception("Sorry, unable to create shipment, please try again later");
                } else {
                    throw new Exception("Sorry, something went wrong with the API. If the problem persists, please contact us!");
                }
            } else {
                if ($response['response']['code'] == 200) {
                    $body = json_decode($response['body'], true);

                    return $body['data'];
                } else {
                    throw new Exception("Sorry, something went wrong with the API. If the problem persists, please contact us!");
                }
            }
        }
    }
}


/*

Automattic\WooCommerce\Admin\Overrides\Order Object
(
    [refunded_line_items:protected] =>
    [status_transition:protected] =>
    [data:protected] => Array
        (
            [parent_id] => 0
            [status] => processing
            [currency] => MYR
            [version] => 4.2.0
            [prices_include_tax] =>
            [date_created] => WC_DateTime Object
                (
                    [utc_offset:protected] => 0
                    [date] => 2020-06-11 10:35:42.000000
                    [timezone_type] => 3
                    [timezone] => Asia/Kuala_Lumpur
                )

            [date_modified] => WC_DateTime Object
                (
                    [utc_offset:protected] => 0
                    [date] => 2020-06-11 10:35:42.000000
                    [timezone_type] => 3
                    [timezone] => Asia/Kuala_Lumpur
                )

            [discount_total] => 0
            [discount_tax] => 0
            [shipping_total] => 3.00
            [shipping_tax] => 0
            [cart_tax] => 0
            [total] => 15.00
            [total_tax] => 0
            [customer_id] => 4
            [order_key] => wc_order_NvJKMQ4zlMBKr
            [billing] => Array
                (
                    [first_name] => Muhammad
                    [last_name] => Wahid
                    [company] =>
                    [address_1] => D-3-2, Blok D, Pesona Villa, Jalan Melati Indah 1, Saujana Melawati, Hulu Klang
                    [address_2] =>
                    [city] => Ampang
                    [state] => KUL
                    [postcode] => 54200
                    [country] => MY
                    [email] => hanifw@yahoo.com
                    [phone] => 60162449954
                )

            [shipping] => Array
                (
                    [first_name] => Muhammad
                    [last_name] => Wahid
                    [company] =>
                    [address_1] => D-3-2, Blok D, Pesona Villa, Jalan Melati Indah 1, Saujana Melawati, Hulu Klang
                    [address_2] =>
                    [city] => Ampang
                    [state] => KUL
                    [postcode] => 54200
                    [country] => MY
                )

            [payment_method] => cod
            [payment_method_title] => Cash on delivery
            [transaction_id] =>
            [customer_ip_address] => 210.195.212.126
            [customer_user_agent] => Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.97 Safari/537.36
            [created_via] => checkout
            [customer_note] =>
            [date_completed] =>
            [date_paid] =>
            [cart_hash] => 152bbc83c4d835ecf3a220b5380ca2f4
        )

    [items:protected] => Array
        (
        )

    [items_to_delete:protected] => Array
        (
        )

    [cache_group:protected] => orders
    [data_store_name:protected] => order
    [object_type:protected] => order
    [id:protected] => 22080
    [changes:protected] => Array
        (
        )

    [object_read:protected] => 1
    [extra_data:protected] => Array
        (
        )

    [default_data:protected] => Array
        (
            [parent_id] => 0
            [status] =>
            [currency] =>
            [version] =>
            [prices_include_tax] =>
            [date_created] =>
            [date_modified] =>
            [discount_total] => 0
            [discount_tax] => 0
            [shipping_total] => 0
            [shipping_tax] => 0
            [cart_tax] => 0
            [total] => 0
            [total_tax] => 0
            [customer_id] => 0
            [order_key] =>
            [billing] => Array
                (
                    [first_name] =>
                    [last_name] =>
                    [company] =>
                    [address_1] =>
                    [address_2] =>
                    [city] =>
                    [state] =>
                    [postcode] =>
                    [country] =>
                    [email] =>
                    [phone] =>
                )

            [shipping] => Array
                (
                    [first_name] =>
                    [last_name] =>
                    [company] =>
                    [address_1] =>
                    [address_2] =>
                    [city] =>
                    [state] =>
                    [postcode] =>
                    [country] =>
                )

            [payment_method] =>
            [payment_method_title] =>
            [transaction_id] =>
            [customer_ip_address] =>
            [customer_user_agent] =>
            [created_via] =>
            [customer_note] =>
            [date_completed] =>
            [date_paid] =>
            [cart_hash] =>
        )

    [data_store:protected] => WC_Data_Store Object
        (
            [instance:WC_Data_Store:private] => WC_Order_Data_Store_CPT Object
                (
                    [internal_meta_keys:protected] => Array
                        (
                            [0] => _customer_user
                            [1] => _order_key
                            [2] => _order_currency
                            [3] => _billing_first_name
                            [4] => _billing_last_name
                            [5] => _billing_company
                            [6] => _billing_address_1
                            [7] => _billing_address_2
                            [8] => _billing_city
                            [9] => _billing_state
                            [10] => _billing_postcode
                            [11] => _billing_country
                            [12] => _billing_email
                            [13] => _billing_phone
                            [14] => _shipping_first_name
                            [15] => _shipping_last_name
                            [16] => _shipping_company
                            [17] => _shipping_address_1
                            [18] => _shipping_address_2
                            [19] => _shipping_city
                            [20] => _shipping_state
                            [21] => _shipping_postcode
                            [22] => _shipping_country
                            [23] => _completed_date
                            [24] => _paid_date
                            [25] => _edit_lock
                            [26] => _edit_last
                            [27] => _cart_discount
                            [28] => _cart_discount_tax
                            [29] => _order_shipping
                            [30] => _order_shipping_tax
                            [31] => _order_tax
                            [32] => _order_total
                            [33] => _payment_method
                            [34] => _payment_method_title
                            [35] => _transaction_id
                            [36] => _customer_ip_address
                            [37] => _customer_user_agent
                            [38] => _created_via
                            [39] => _order_version
                            [40] => _prices_include_tax
                            [41] => _date_completed
                            [42] => _date_paid
                            [43] => _payment_tokens
                            [44] => _billing_address_index
                            [45] => _shipping_address_index
                            [46] => _recorded_sales
                            [47] => _recorded_coupon_usage_counts
                            [48] => _download_permissions_granted
                            [49] => _order_stock_reduced
                        )

                    [meta_type:protected] => post
                    [object_id_field_for_meta:protected] =>
                    [must_exist_meta_keys:protected] => Array
                        (
                        )

                )

            [stores:WC_Data_Store:private] => Array
                (
                    [coupon] => WC_Coupon_Data_Store_CPT
                    [customer] => WC_Customer_Data_Store
                    [customer-download] => WC_Customer_Download_Data_Store
                    [customer-download-log] => WC_Customer_Download_Log_Data_Store
                    [customer-session] => WC_Customer_Data_Store_Session
                    [order] => WC_Order_Data_Store_CPT
                    [order-refund] => WC_Order_Refund_Data_Store_CPT
                    [order-item] => WC_Order_Item_Data_Store
                    [order-item-coupon] => WC_Order_Item_Coupon_Data_Store
                    [order-item-fee] => WC_Order_Item_Fee_Data_Store
                    [order-item-product] => WC_Order_Item_Product_Data_Store
                    [order-item-shipping] => WC_Order_Item_Shipping_Data_Store
                    [order-item-tax] => WC_Order_Item_Tax_Data_Store
                    [payment-token] => WC_Payment_Token_Data_Store
                    [product] => WC_Product_Data_Store_CPT
                    [product-grouped] => WC_Product_Grouped_Data_Store_CPT
                    [product-variable] => WC_Product_Variable_Data_Store_CPT
                    [product-variation] => WC_Product_Variation_Data_Store_CPT
                    [shipping-zone] => WC_Shipping_Zone_Data_Store
                    [webhook] => WC_Webhook_Data_Store
                    [report-revenue-stats] => Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore
                    [report-orders] => Automattic\WooCommerce\Admin\API\Reports\Orders\DataStore
                    [report-orders-stats] => Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore
                    [report-products] => Automattic\WooCommerce\Admin\API\Reports\Products\DataStore
                    [report-variations] => Automattic\WooCommerce\Admin\API\Reports\Variations\DataStore
                    [report-products-stats] => Automattic\WooCommerce\Admin\API\Reports\Products\Stats\DataStore
                    [report-categories] => Automattic\WooCommerce\Admin\API\Reports\Categories\DataStore
                    [report-taxes] => Automattic\WooCommerce\Admin\API\Reports\Taxes\DataStore
                    [report-taxes-stats] => Automattic\WooCommerce\Admin\API\Reports\Taxes\Stats\DataStore
                    [report-coupons] => Automattic\WooCommerce\Admin\API\Reports\Coupons\DataStore
                    [report-coupons-stats] => Automattic\WooCommerce\Admin\API\Reports\Coupons\Stats\DataStore
                    [report-downloads] => Automattic\WooCommerce\Admin\API\Reports\Downloads\DataStore
                    [report-downloads-stats] => Automattic\WooCommerce\Admin\API\Reports\Downloads\Stats\DataStore
                    [admin-note] => Automattic\WooCommerce\Admin\Notes\DataStore
                    [report-customers] => Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore
                    [report-customers-stats] => Automattic\WooCommerce\Admin\API\Reports\Customers\Stats\DataStore
                    [report-stock-stats] => Automattic\WooCommerce\Admin\API\Reports\Stock\Stats\DataStore
                )

            [current_class_name:WC_Data_Store:private] => WC_Order_Data_Store_CPT
            [object_type:WC_Data_Store:private] => order
        )

    [meta_data:protected] => Array
        (
            [0] => WC_Meta_Data Object
                (
                    [current_data:protected] => Array
                        (
                            [id] => 353482
                            [key] => is_vat_exempt
                            [value] => no
                        )

                    [data:protected] => Array
                        (
                            [id] => 353482
                            [key] => is_vat_exempt
                            [value] => no
                        )

                )

            [1] => WC_Meta_Data Object
                (
                    [current_data:protected] => Array
                        (
                            [id] => 353483
                            [key] => _dokan_vendor_id
                            [value] => 2252
                        )

                    [data:protected] => Array
                        (
                            [id] => 353483
                            [key] => _dokan_vendor_id
                            [value] => 2252
                        )

                )

            [2] => WC_Meta_Data Object
                (
                    [current_data:protected] => Array
                        (
                            [id] => 353484
                            [key] => shipping_fee_recipient
                            [value] => admin
                        )

                    [data:protected] => Array
                        (
                            [id] => 353484
                            [key] => shipping_fee_recipient
                            [value] => admin
                        )

                )

            [3] => WC_Meta_Data Object
                (
                    [current_data:protected] => Array
                        (
                            [id] => 353485
                            [key] => tax_fee_recipient
                            [value] => admin
                        )

                    [data:protected] => Array
                        (
                            [id] => 353485
                            [key] => tax_fee_recipient
                            [value] => admin
                        )

                )

        )

)
WP_User Object
(
    [data] => stdClass Object
        (
            [ID] => 4
            [user_login] => hanifw
            [user_pass] => $P$BBseIxwBlkc10Hm4vX.mEEk31be4aT.
            [user_nicename] => hanifw
            [user_email] => hanifw@delyva.com
            [user_url] => https://delyva.com/
            [user_registered] => 2019-03-08 08:04:40
            [user_activation_key] =>
            [user_status] => 0
            [display_name] => Hanif
        )

    [ID] => 4
    [caps] => Array
        (
            [administrator] => 1
        )

    [cap_key] => wb_capabilities
    [roles] => Array
        (
            [0] => administrator
        )

    [allcaps] => Array
        (
            [switch_themes] => 1
            [edit_themes] => 1
            [activate_plugins] => 1
            [edit_plugins] => 1
            [edit_users] => 1
            [edit_files] => 1
            [manage_options] => 1
            [moderate_comments] => 1
            [manage_categories] => 1
            [manage_links] => 1
            [upload_files] => 1
            [import] => 1
            [unfiltered_html] => 1
            [edit_posts] => 1
            [edit_others_posts] => 1
            [edit_published_posts] => 1
            [publish_posts] => 1
            [edit_pages] => 1
            [read] => 1
            [level_10] => 1
            [level_9] => 1
            [level_8] => 1
            [level_7] => 1
            [level_6] => 1
            [level_5] => 1
            [level_4] => 1
            [level_3] => 1
            [level_2] => 1
            [level_1] => 1
            [level_0] => 1
            [edit_others_pages] => 1
            [edit_published_pages] => 1
            [publish_pages] => 1
            [delete_pages] => 1
            [delete_others_pages] => 1
            [delete_published_pages] => 1
            [delete_posts] => 1
            [delete_others_posts] => 1
            [delete_published_posts] => 1
            [delete_private_posts] => 1
            [edit_private_posts] => 1
            [read_private_posts] => 1
            [delete_private_pages] => 1
            [edit_private_pages] => 1
            [read_private_pages] => 1
            [delete_users] => 1
            [create_users] => 1
            [unfiltered_upload] => 1
            [edit_dashboard] => 1
            [update_plugins] => 1
            [delete_plugins] => 1
            [install_plugins] => 1
            [update_themes] => 1
            [install_themes] => 1
            [update_core] => 1
            [list_users] => 1
            [remove_users] => 1
            [promote_users] => 1
            [edit_theme_options] => 1
            [delete_themes] => 1
            [export] => 1
            [dokandar] => 1
            [dokan_view_sales_overview] => 1
            [dokan_view_sales_report_chart] => 1
            [dokan_view_announcement] => 1
            [dokan_view_order_report] => 1
            [dokan_view_review_reports] => 1
            [dokan_view_product_status_report] => 1
            [dokan_view_overview_report] => 1
            [dokan_view_daily_sale_report] => 1
            [dokan_view_top_selling_report] => 1
            [dokan_view_top_earning_report] => 1
            [dokan_view_statement_report] => 1
            [dokan_view_order] => 1
            [dokan_manage_order] => 1
            [dokan_manage_order_note] => 1
            [dokan_manage_refund] => 1
            [dokan_add_coupon] => 1
            [dokan_edit_coupon] => 1
            [dokan_delete_coupon] => 1
            [dokan_view_reviews] => 1
            [dokan_manage_reviews] => 1
            [dokan_manage_withdraw] => 1
            [dokan_add_product] => 1
            [dokan_edit_product] => 1
            [dokan_delete_product] => 1
            [dokan_view_product] => 1
            [dokan_duplicate_product] => 1
            [dokan_import_product] => 1
            [dokan_export_product] => 1
            [dokan_view_overview_menu] => 1
            [dokan_view_product_menu] => 1
            [dokan_view_order_menu] => 1
            [dokan_view_coupon_menu] => 1
            [dokan_view_report_menu] => 1
            [dokan_view_review_menu] => 1
            [dokan_view_withdraw_menu] => 1
            [dokan_view_store_settings_menu] => 1
            [dokan_view_store_payment_menu] => 1
            [dokan_view_store_shipping_menu] => 1
            [dokan_view_store_social_menu] => 1
            [dokan_view_store_seo_menu] => 1
            [vc_access_rules_post_types/post] => 1
            [vc_access_rules_post_types/page] => 1
            [vc_access_rules_post_types/product] => 1
            [vc_access_rules_post_types] => custom
            [vc_access_rules_backend_editor] => 1
            [vc_access_rules_frontend_editor] => 1
            [vc_access_rules_post_settings] => 1
            [vc_access_rules_settings] => 1
            [vc_access_rules_templates] => 1
            [vc_access_rules_shortcodes] => 1
            [vc_access_rules_grid_builder] => 1
            [vc_access_rules_presets] => 1
            [vc_access_rules_dragndrop] => 1
            [dokan_view_booking_menu] => 1
            [dokan_add_booking_product] => 1
            [dokan_edit_booking_product] => 1
            [dokan_delete_booking_product] => 1
            [dokan_manage_booking_products] => 1
            [dokan_manage_booking_calendar] => 1
            [dokan_manage_bookings] => 1
            [dokan_manage_booking_resource] => 1
            [dokan_view_store_verification_menu] => 1
            [dokan_view_tools_menu] => 1
            [dokan_manage_support_tickets] => 1
            [dokan_view_store_rma_menu] => 1
            [dokan_view_store_rma_settings_menu] => 1
            [dokan_view_auction_menu] => 1
            [dokan_add_auction_product] => 1
            [dokan_edit_auction_product] => 1
            [dokan_delete_auction_product] => 1
            [wf2fa_activate_2fa_self] => 1
            [wf2fa_activate_2fa_others] => 1
            [wf2fa_manage_settings] => 1
            [manage_berocket] => 1
            [manage_berocket_account] => 1
            [edit_post] => 1
            [read_post] => 1
            [delete_post] => 1
            [manage_rewards] => 1
            [ure_edit_roles] => 1
            [ure_create_roles] => 1
            [ure_delete_roles] => 1
            [ure_create_capabilities] => 1
            [ure_delete_capabilities] => 1
            [ure_manage_options] => 1
            [ure_reset_roles] => 1
            [manage_woocommerce_csv_exports] => 1
            [delete_szbdzones] => 1
            [delete_others_szbdzones] => 1
            [delete_private_szbdzones] => 1
            [delete_published_szbdzones] => 1
            [edit_szbdzones] => 1
            [edit_others_szbdzones] => 1
            [edit_private_szbdzones] => 1
            [edit_published_szbdzones] => 1
            [publish_szbdzones] => 1
            [read_private_szbdzones] => 1
            [manage_woocommerce] => 1
            [view_woocommerce_reports] => 1
            [edit_product] => 1
            [read_product] => 1
            [delete_product] => 1
            [edit_products] => 1
            [edit_others_products] => 1
            [publish_products] => 1
            [read_private_products] => 1
            [delete_products] => 1
            [delete_private_products] => 1
            [delete_published_products] => 1
            [delete_others_products] => 1
            [edit_private_products] => 1
            [edit_published_products] => 1
            [manage_product_terms] => 1
            [edit_product_terms] => 1
            [delete_product_terms] => 1
            [assign_product_terms] => 1
            [edit_shop_order] => 1
            [read_shop_order] => 1
            [delete_shop_order] => 1
            [edit_shop_orders] => 1
            [edit_others_shop_orders] => 1
            [publish_shop_orders] => 1
            [read_private_shop_orders] => 1
            [delete_shop_orders] => 1
            [delete_private_shop_orders] => 1
            [delete_published_shop_orders] => 1
            [delete_others_shop_orders] => 1
            [edit_private_shop_orders] => 1
            [edit_published_shop_orders] => 1
            [manage_shop_order_terms] => 1
            [edit_shop_order_terms] => 1
            [delete_shop_order_terms] => 1
            [assign_shop_order_terms] => 1
            [edit_shop_coupon] => 1
            [read_shop_coupon] => 1
            [delete_shop_coupon] => 1
            [edit_shop_coupons] => 1
            [edit_others_shop_coupons] => 1
            [publish_shop_coupons] => 1
            [read_private_shop_coupons] => 1
            [delete_shop_coupons] => 1
            [delete_private_shop_coupons] => 1
            [delete_published_shop_coupons] => 1
            [delete_others_shop_coupons] => 1
            [edit_private_shop_coupons] => 1
            [edit_published_shop_coupons] => 1
            [manage_shop_coupon_terms] => 1
            [edit_shop_coupon_terms] => 1
            [delete_shop_coupon_terms] => 1
            [assign_shop_coupon_terms] => 1
            [manage_woocommerce_order_status_emails] => 1
            [administrator] => 1
        )

    [filter] =>
    [site_id:WP_User:private] => 1
)

*/

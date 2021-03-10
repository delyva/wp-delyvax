<?php

if (!defined('WPINC')) {
    die;
}
if (!class_exists('DelyvaX_Shipping_API')) {
    class DelyvaX_Shipping_API
    {
        private static $api_endpoint = "https://api.delyva.app";

        //instant quote
        public static function getPriceQuote($origin, $destination, $weight, $cod)
        {
            $url = Self::$api_endpoint . "/service/instantQuote/";// . trim(esc_attr($settings['integration_id']), " ");

            $settings = get_option( 'woocommerce_delyvax_settings' );

            $company_id = $settings['company_id'];
            $user_id = $settings['user_id'];
            $customer_id = $settings['customer_id'];
            $api_token = $settings['api_token'];
            $processing_days = $settings['processing_days'];
            $item_type = ($settings['item_type']) ? $settings['item_type'] : "PARCEL" ;

            $postRequestArr = [
                // 'companyId' => $company_id,
                // 'userId' => $user_id,
                'customerId' => $customer_id,
                'origin' => $origin,
                'destination' => $destination,
                "weight" => $weight,
                "itemType" => $item_type,
                // "cod" => $cod,
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

        public static function postCreateOrder($origin, $destination, $serviceCode, $order_notes, $cod)
        {
              $url = Self::$api_endpoint . "/order";// . trim(esc_attr($settings['integration_id']), " ");

              $settings = get_option( 'woocommerce_delyvax_settings' );

              $company_id = $settings['company_id'];
              $user_id = $settings['user_id'];
              $customer_id = $settings['customer_id'];
              $api_token = $settings['api_token'];
              $processing_days = $settings['processing_days'];

              $postRequestArr = [
                  // 'companyId' => $company_id,
                  // 'userId' => $user_id,
                  "customerId" => $customer_id,
                  // "consignmentNo": "CJ00000007MY",
                  "process" => false,
                  "serviceCode" => $serviceCode,
                  'origin' => $origin,
                  'destination' => $destination,
                  'note' => $order_notes,
                  'cod'=>$cod
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

        public static function postProcessOrder($shipmentId, $serviceCode)
        {
              $url = Self::$api_endpoint . "/order/:orderId/process";// . trim(esc_attr($settings['integration_id']), " ");

              $url = str_replace(":orderId", $shipmentId, $url);

              $settings = get_option( 'woocommerce_delyvax_settings' );

              $company_id = $settings['company_id'];
              $user_id = $settings['user_id'];
              $customer_id = $settings['customer_id'];
              $api_token = $settings['api_token'];
              $processing_days = $settings['processing_days'];

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

              if (is_wp_error($response)) {
                  $error_message = $response->get_error_message();
                  if ($error_message == 'fsocket timed out') {
                      throw new Exception("Sorry, unable to process shipment, please try again later");
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
                    throw new Exception("Sorry, unable to track shipment, please try again later");
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
                    throw new Exception("Sorry, unable to track shipment, please try again later");
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

        public static function postCreateWebhook($event_name)
        {
            $settings = get_option( 'woocommerce_delyvax_settings' );

            $company_id = $settings['company_id'];
            $user_id = $settings['user_id'];
            $customer_id = $settings['customer_id'];
            $api_token = $settings['api_token'];

            $url = Self::$api_endpoint . "//webhook/";

            // get_option( 'woocommerce_store_url' );
            $store_url = get_site_url()."/"; //"https://matdespatch.com/my/makan";

            $postRequestArr = array(
                "event" => $event_name,
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
                    throw new Exception("Sorry, unable to create webhook, please try again later");
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

        public static function getDrivers($extIdType, $extId)
        {
            // GET /fleet/driver?extId=
            $url = Self::$api_endpoint . "/fleet/driver?extId=".$extId;

            $settings = get_option( 'woocommerce_delyvax_settings' );

            $company_id = $settings['company_id'];
            $user_id = $settings['user_id'];
            $customer_id = $settings['customer_id'];
            $api_token = $settings['api_token'];
            $processing_days = $settings['processing_days'];

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
                    throw new Exception("Sorry, unable to get driver, please try again later");
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

        public static function postCreateTask($shipmentId, $consignmentNo, $waypoints, $price, $driverId, $order_notes)
        {
            //POST {{API_ENDPOINT}}/task
            $url = Self::$api_endpoint . "/task";// . trim(esc_attr($settings['integration_id']), " ");

            $settings = get_option( 'woocommerce_delyvax_settings' );

            $company_id = $settings['company_id'];
            $user_id = $settings['user_id'];
            $customer_id = $settings['customer_id'];
            $api_token = $settings['api_token'];
            $processing_days = $settings['processing_days'];

            $postRequestArr = [
                'companyId' => $company_id,
                'userId' => $user_id,
                "customerId" => $customer_id,
                "orderId" => $shipmentId,
                // "serviceCode" => $serviceCode,
                // "serviceId" => "",
                "driverId" => $driverId,
                // "status" => "ASSIGN",
                // "statusCode" => 200,
                "cn" => $consignmentNo,
                "price" => $price,
                'waypoint' => $waypoints,
                "note" => $order_notes
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

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                if ($error_message == 'fsocket timed out') {
                    throw new Exception("Sorry, unable to create task, please try again later");
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

    }
}

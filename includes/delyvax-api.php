<?php

if (!defined('WPINC')) {
    die;
}
if (!class_exists('DelyvaX_Shipping_API')) {
    class DelyvaX_Shipping_API
    {
        private static $api_endpoint = "https://api.delyva.app/v1.0";

        //instant quote
        public static function getPriceQuote($origin, $destination, $weight, $addons, $inventories)
        {
            $url = Self::$api_endpoint . "/service/instantQuote/";// . trim(esc_attr($settings['integration_id']), " ");

            $settings = get_option( 'woocommerce_delyvax_settings' );

            $company_id = $settings['company_id'];
            $user_id = $settings['user_id'];
            $customer_id = $settings['customer_id'];
            $api_token = $settings['api_token'];
            $processing_days = $settings['processing_days'];
            $item_type = ($settings['item_type']) ? $settings['item_type'] : "woocommerce" ;

            $postRequestArr = [
                // 'companyId' => $company_id,
                // 'userId' => $user_id,
                'customerId' => $customer_id,
                'origin' => $origin,
                'destination' => $destination,
                "weight" => $weight,
                "itemType" => $item_type,
                "inventory" => $inventories,
              	"serviceAddon" => $addons
            ];

            $response = wp_remote_post($url, array(
                'headers' => array(
                  'content-type' => 'application/json',
                  'X-Delyvax-Access-Token' => $api_token,
                  'X-Delyvax-Wp-Version' => DELYVAX_PLUGIN_VERSION,
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

        public static function postCreateOrder($order, $origin, $destination, $weight, $serviceCode, $order_notes, $addons, $referenceNo)
        {
              $url = Self::$api_endpoint . "/order";// . trim(esc_attr($settings['integration_id']), " ");

              $settings = get_option( 'woocommerce_delyvax_settings' );

              $company_id = $settings['company_id'];
              $user_id = $settings['user_id'];
              $customer_id = $settings['customer_id'];
              $api_token = $settings['api_token'];
              $processing_days = $settings['processing_days'];

              $source = ($settings['source']) ? $settings['source'] : "PARCEL" ;

              if($serviceCode)
              {
                  $postRequestArr = [
                      // 'companyId' => $company_id,
                      // 'userId' => $user_id,
                      "customerId" => $customer_id,
                      "process" => false,
                      "serviceCode" => $serviceCode,
                      'origin' => $origin,
                      'destination' => $destination,
                      'weight' => $weight,
                      'note' => $order_notes,
                      "serviceAddon" => $addons,
                      'source'=> $source,
                      'referenceNo'=> $referenceNo.""
                  ];
              }else {
                  $postRequestArr = [
                      // 'companyId' => $company_id,
                      // 'userId' => $user_id,
                      "customerId" => $customer_id,
                      "process" => false,
                      'origin' => $origin,
                      'destination' => $destination,
                      'weight' => $weight,
                      'note' => $order_notes,
                      "serviceAddon" => $addons,
                      'source'=> $source,
                      'referenceNo'=> $referenceNo.""
                  ];
              }

              $response = wp_remote_post($url, array(
                  'headers' => array(
                    'content-type' => 'application/json',
                    'X-Delyvax-Access-Token' => $api_token,
                    'X-Delyvax-Wp-Version' => DELYVAX_PLUGIN_VERSION,
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
                      $body = json_decode($response['body'], true);
                      $order->update_meta_data( 'DelyvaXError', $body['error']['message'] );
                      $order->save();
                      throw new Exception("Error: ".$body['error']['message'].". Sorry, something went wrong with the API. If the problem persists, please contact us!");
                  }
              }
              ///
        }

        public static function postProcessOrder($order, $shipmentId, $serviceCode)
        {
              $url = Self::$api_endpoint . "/order/:orderId/process";// . trim(esc_attr($settings['integration_id']), " ");

              $url = str_replace(":orderId", $shipmentId, $url);

              $settings = get_option( 'woocommerce_delyvax_settings' );

              $company_id = $settings['company_id'];
              $user_id = $settings['user_id'];
              $customer_id = $settings['customer_id'];
              $api_token = $settings['api_token'];
              $processing_days = $settings['processing_days'];

              if($serviceCode)
              {
                  $postRequestArr = [
                      'orderId' => $shipmentId,
                      "serviceCode" => $serviceCode,
                      "skipQueue" => true,
                  ];
              }else {
                  $postRequestArr = [
                      'orderId' => $shipmentId,
                      "skipQueue" => true,
                  ];
              }

              $response = wp_remote_post($url, array(
                  'headers' => array(
                    'content-type' => 'application/json',
                    'X-Delyvax-Access-Token' => $api_token,
                    'X-Delyvax-Wp-Version' => DELYVAX_PLUGIN_VERSION,
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
                      $body = json_decode($response['body'], true);
                      $order->update_meta_data( 'DelyvaXError', $body['error']['message'] );
                      $order->save();
                      throw new Exception("Error: ".$body['error']['message'].". Sorry, something went wrong with the API. If the problem persists, please contact us!");
                  }
              }
              ///
        }

        public static function postCancelOrder($order, $shipmentId)
        {
              $url = Self::$api_endpoint . "/order/:orderId/cancel";

              $url = str_replace(":orderId", $shipmentId, $url);

              $settings = get_option( 'woocommerce_delyvax_settings' );

              $company_id = $settings['company_id'];
              $user_id = $settings['user_id'];
              $customer_id = $settings['customer_id'];
              $api_token = $settings['api_token'];

            //   $postRequestArr = [];

              $response = wp_remote_post($url, array(
                  'headers' => array(
                    'content-type' => 'application/json',
                    'X-Delyvax-Access-Token' => $api_token,
                    'X-Delyvax-Wp-Version' => DELYVAX_PLUGIN_VERSION,
                  ),
                //   'body' => json_encode($postRequestArr),
                  'method' => 'POST',
                  'timeout' => 25
              ));

              if (is_wp_error($response)) {
                  $error_message = $response->get_error_message();
                  if ($error_message == 'fsocket timed out') {
                      throw new Exception("Sorry, unable to cancel shipment, please try again later");
                  } else {
                      throw new Exception("Sorry, something went wrong with the API. If the problem persists, please contact us!");
                  }
              } else {
                  if ($response['response']['code'] == 200) {
                      $body = json_decode($response['body'], true);
                      return $body['data'];
                  } else {
                      $body = json_decode($response['body'], true);
                      $order->update_meta_data( 'DelyvaXError', $body['error']['message'] );
                      $order->save();
                      throw new Exception("Error: ".$body['error']['message'].". Sorry, something went wrong with the API. If the problem persists, please contact us!");
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

        public static function getOrderQuotesByOrderId($shipmentId)
        {
            $settings = get_option( 'woocommerce_delyvax_settings' );

            $company_id = $settings['company_id'];
            $user_id = $settings['user_id'];
            $customer_id = $settings['customer_id'];
            $api_token = $settings['api_token'];

            $url = Self::$api_endpoint . "/order/:orderId?retrieve=quotes";

            $url = str_replace(":orderId", $shipmentId, $url);

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

        public static function getWebhook()
        {
            $url = Self::$api_endpoint . "/webhook";
            $settings = get_option( 'woocommerce_delyvax_settings' );

            $api_token = $settings['api_token'];

            $response = wp_remote_post($url, array(
                'headers' => array(
                  'content-type' => 'application/json',
                  'X-Delyvax-Access-Token' => $api_token
                ),
                'method' => 'GET',
                'timeout' => 25
            ));

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                if ($error_message == 'fsocket timed out') {
                    throw new Exception("Sorry, unable to get webhook, please try again later");
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

        public static function deleteWebhook($webhook_id)
        {
            $url = Self::$api_endpoint . "/webhook/" . $webhook_id;
            $settings = get_option( 'woocommerce_delyvax_settings' );

            $api_token = $settings['api_token'];

            $response = wp_remote_post($url, array(
                'headers' => array(
                  'content-type' => 'application/json',
                  'X-Delyvax-Access-Token' => $api_token
                ),
                'method' => 'DELETE',
                'timeout' => 25
            ));

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                if ($error_message == 'fsocket timed out') {
                    throw new Exception("Sorry, unable to delete webhook, please try again later");
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
            $store_url = get_site_url()."/?delyvax=webhook"; //"https://matdespatch.com/my/makan";

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

        public static function updateWebhookUrl($webhook_id)
        {
            $settings = get_option( 'woocommerce_delyvax_settings' );

            $company_id = $settings['company_id'];
            $user_id = $settings['user_id'];
            $customer_id = $settings['customer_id'];
            $api_token = $settings['api_token'];

            $url = Self::$api_endpoint . "//webhook/" . $webhook_id;

            $store_url = get_site_url()."/?delyvax=webhook"; //"https://matdespatch.com/my/makan";

            $postRequestArr = array(
                "url" => $store_url,
            );

            $response = wp_remote_post($url, array(
                'headers' => array(
                  'content-type' => 'application/json',
                  'X-Delyvax-Access-Token' => $api_token
                ),
                'body' => json_encode($postRequestArr),
                'method' => 'PATCH',
                'timeout' => 25
            ));

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                if ($error_message == 'fsocket timed out') {
                    throw new Exception("Sorry, unable to update webhook, please try again later");
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

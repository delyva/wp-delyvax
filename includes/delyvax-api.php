<?php

defined( 'ABSPATH' ) or die();

if (!class_exists('DelyvaX_Shipping_API')) {
    class DelyvaX_Shipping_API
    {
        private static $api_endpoint = "https://api.delyva.app/v1.0";
        private static function get_idempotency_window() {
            $timestamp = current_time('timestamp');
            $hour = (int) date('G', $timestamp);
            $minutes = (int) date('i', $timestamp);
            
            // If in last 5 minutes of the hour, skip to next hour
            if ($minutes >= 55) {
                $hour += 1;
                // Handle midnight rollover
                if ($hour === 24) {
                    $hour = 0;
                }
            }
            
            return $hour;
        }

        //instant quote
        public static function getPriceQuote($origin, $destination, $weight, $addons, $inventories)
        {
            $url = Self::$api_endpoint . "/service/instantQuote/";// . trim(esc_attr($settings['integration_id']), " ");

            $settings = get_option( 'woocommerce_delyvax_settings' );
            $customer_id = $settings['customer_id'];
            $api_token = $settings['api_token'];
            $processing_days = $settings['processing_days'];
            $item_type = ($settings['item_type']) ? $settings['item_type'] : "woocommerce" ;

            $postRequestArr = [
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

        public static function postCreateOrder($order, $ms2781Request)
        {
            $url = Self::$api_endpoint . "/order/ms2781/create";
            $settings = get_option('woocommerce_delyvax_settings');
            $api_token = $settings['api_token'];

            $site_url = parse_url(get_site_url(), PHP_URL_HOST);
            $payload_hash = md5(json_encode($ms2781Request));
            $idempotency_key = 'wp-' . md5($site_url . '_order_' . $order->get_id() . '_' . self::get_idempotency_window() . '_' . $payload_hash);
        
            // Make the API request
            $response = wp_remote_post($url, array(
                'headers' => array(
                    'content-type' => 'application/json',
                    'X-Delyvax-Access-Token' => $api_token,
                    'X-Delyvax-Wp-Version' => DELYVAX_PLUGIN_VERSION,
                    'idempotency-key' => $idempotency_key,
                ),
                'body' => json_encode($ms2781Request),
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
            }

            if ($response['response']['code'] == 200) {
                $body = json_decode($response['body'], true);
                $order->delete_meta_data('delyvax_error');
                return $body;
            } else {
                $body = json_decode($response['body'], true);
                $order->update_meta_data('delyvax_error', $body['error']['message']);
                $order->save();
                throw new Exception("Error: " . $body['error']['message'] . ". Sorry, something went wrong with the API. If the problem persists, please contact us!");
            }
        }

        public static function postProcessOrder($order, $shipmentId, $serviceCode)
        {
              $url = Self::$api_endpoint . "/order/process";

              $settings = get_option( 'woocommerce_delyvax_settings' );
              $api_token = $settings['api_token'];

              $postRequestArr = [
                'orderId' => $shipmentId,
                "skipQueue" => true,
              ];

              if ($serviceCode) {
                $postRequestArr['serviceCode'] = $serviceCode;
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
                      $order->delete_meta_data('delyvax_error');
                      $order->save();
                      return $body['data'];
                  } else {
                      $body = json_decode($response['body'], true);
                      $order->update_meta_data( 'delyvax_error', $body['error']['message']);
                      $order->save();
                      throw new Exception("Error: ".$body['error']['message'].". Sorry, something went wrong with the API. If the problem persists, please contact us!");
                  }
              }
        }

        public static function postCancelOrder($order, $shipmentId) {
            $url = Self::$api_endpoint . "/order/ms2781/cancel";
        
            $settings = get_option('woocommerce_delyvax_settings');
            $api_token = $settings['api_token'];
        
            $postRequestArr = [
                "orderId" => $shipmentId
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
                    throw new Exception("Sorry, unable to cancel shipment, please try again later");
                } else {
                    throw new Exception("Sorry, something went wrong with the API. If the problem persists, please contact us!");
                }
            } 
        
            if ($response['response']['code'] == 200) {
                $body = json_decode($response['body'], true);
                return $body;
            } 
            
            $body = json_decode($response['body'], true);
            $order->update_meta_data('delyvax_error', $body['error']['message']);
            $order->save();
            throw new Exception("Error: " . $body['error']['message'] . ". Sorry, something went wrong with the API. If the problem persists, please contact us!");
        }

        public static function getOrderQuotesByOrderId($shipmentId)
        {
            $settings = get_option( 'woocommerce_delyvax_settings' );
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
                    throw new Exception("Sorry, unable to quote, please try again later");
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
            $api_token = $settings['api_token'];

            $url = Self::$api_endpoint . "/webhook/";
            $store_url = get_site_url()."/?delyvax=webhook";

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
            $api_token = $settings['api_token'];
            $url = Self::$api_endpoint . "/webhook/" . $webhook_id;
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
    
        public static function updateOrderData($order, $shipmentId, $postRequestArr)
        {
              $url = Self::$api_endpoint . "/order/:orderId";

              $url = str_replace(":orderId", $shipmentId, $url);

              $settings = get_option( 'woocommerce_delyvax_settings' );
              $api_token = $settings['api_token'];

              $response = wp_remote_post($url, array(
                  'headers' => array(
                    'content-type' => 'application/json',
                    'X-Delyvax-Access-Token' => $api_token,
                    'X-Delyvax-Wp-Version' => DELYVAX_PLUGIN_VERSION,
                  ),
                  'body' => json_encode($postRequestArr),
                  'method' => 'PATCH',
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
                      $order->update_meta_data( 'delyvax_error', $body['error']['message'] );
                      $order->save();
                      throw new Exception("Error: ".$body['error']['message'].". Sorry, something went wrong with the API. If the problem persists, please contact us!");
                  }
              }
              ///
        }
        
    }
}

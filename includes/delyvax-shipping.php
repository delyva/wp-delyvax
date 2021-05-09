<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if (!class_exists('DelyvaX_Shipping_Method')) {

  class DelyvaX_Shipping_Method extends WC_Shipping_Method {

      /**
       * Constructor for your shipping class.
       */
      public function __construct($instance_id = 0) {
          $this->id = 'delyvax';
          $this->instance_id = absint($instance_id);
          $this->method_title = __('DelyvaX', 'delyvax');  // Title shown in admin
          $settings = get_option( 'woocommerce_delyvax_settings' );
          if ($settings['api_token']) {
              $this->method_description = __('<a href="https://www.delyva.com/solutions" target="_blank">DelyvaX</a> dynamic shipping rates at checkout.', 'delyvax');
          } else {
              $this->method_description = __('<h3 style="color:red"><b>NOTICE!</b> This app is not configured! Please contact your account manager.</h3>', 'delyvax');
          }
          $this->supports = array(
              'shipping-zones',
              'settings'
          );
          $this->init();
          $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
          $this->title = isset($this->settings['title']) ? $this->settings['title'] : __('DelyvaX', 'delyvax');
          add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
      }

      /**
       * Init your settings.
       */
      public function init() {
          // Load the settings API
          $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
          $this->init_settings(); // This is part of the settings API. Loads settings you previously init.
          add_filter('woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50);
          // Save settings in admin if you have any defined
          add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
      }

      public static function add_settings_tab($settings_tabs) {
          $settings_tabs['shipping&section=delyvax'] = __('Delyvax', 'delyvax-shipping');
          return $settings_tabs;
      }

      //settings
      public function init_form_fields() {
          $this->form_fields = array(
            array(
                'title' => __( 'Settings', 'delyvax' ),
                'type' => 'title',
                'id' => 'delyvax_settings_title',
            ),
            'enable' => array(
                'title'    	=> __( 'Shipping Rate', 'delyvax' ),
                'id'       	=> 'delyvax_pricing_enable',
                'description'  	=> __( 'Enable dynamic shipping rate on checkout', 'delyvax' ),
                'type'     	=> 'checkbox',
                'default'	=> 'yes'
            ),
            'create_shipment_on_paid' => array(
                'title'    	=> __( 'Auto Create Delivery Order on Payment Complete', 'delyvax' ),
                'id'       	=> 'delyvax_create_shipment_on_paid',
                'description'  	=> __( 'Create shipment on successful payment by customer', 'delyvax' ),
                'type'     	=> 'checkbox',
                'default'	=> ''
            ),
            'create_shipment_on_confirm' => array(
                'title'    	=> __( 'Manual Create Delivery Order on Preparing', 'delyvax' ),
                'id'       	=> 'delyvax_create_shipment_on_confirm',
                'description'  	=> __( 'Create shipment on order status = "preparing" by Store/Merchant/Vendor', 'delyvax' ),
                'type'     	=> 'checkbox',
                'default'	=> ''
            ),
            'change_order_status' => array(
                'title'    	=> __( 'Auto Change Order Status', 'delyvax' ),
                'id'       	=> 'delyvax_change_order_status',
                'description'  	=> __( 'Create webhook on DelyvaX customer profile pointing to your store url e.g. https://kedai.matdespatch.com', 'delyvax' ),
                'type'     	=> 'checkbox',
                'default'	=> ''
            ),
            'company_id' => array(
                'title' => __('Company ID', 'delyvax'),
                'type' => 'text',
                'default' => __('', 'delyvax'),
                'id' => 'delyvax_company_id',
                'description' => __( 'DelyvaX Company ID (e.g. e44c7375-c4dc-47e9-8b24-70a28e024a83)' ),
            ),
            'company_code' => array(
                'title' => __('Company Code', 'delyvax'),
                'type' => 'text',
                'default' => __('', 'delyvax'),
                'id' => 'delyvax_company_code',
                'description' => __( 'DelyvaX Company Code (e.g. matdespatch-my)' ),
            ),
            'company_name' => array(
                'title' => __('Company Name', 'delyvax'),
                'type' => 'text',
                'default' => __('', 'delyvax'),
                'id' => 'delyvax_company_name',
                'description' => __( 'DelyvaX Company Name (e.g. Matdespatch)' ),
            ),
            'user_id' => array(
                'title' => __('User ID', 'delyvax'),
                'type' => 'text',
                'default' => __('', 'delyvax'),
                'id' => 'delyvax_user_id',
                'description' => __( 'DelyvaX User ID (e.g. d50d1780-aabc-11ea-8557-fb3ba8b0c74b)' ),
            ),
            'customer_id' => array(
                'title' => __('Customer ID', 'delyvax'),
                'type' => 'text',
                'default' => __('', 'delyvax'),
                'id' => 'delyvax_customer_id',
                'description' => __( 'DelyvaX Customer ID (e.g. 323)' ),
            ),
            'api_token' => array(
                'title' => __('API token', 'delyvax'),
                'type' => 'text',
                'default' => __('', 'delyvax'),
                'id' => 'delyvax_api_token',
                'description' => __( 'DelyvaX API Token (e.g. d50d1780-aabc-11ea-8557-fb3ba8b0c74b)' ),
            ),
            'api_webhook_enable' => array(
                'title'    	=> __( 'API Enable Webhook', 'delyvax' ),
                'id'       	=> 'delyvax_api_webhook_enable',
                'description'  	=> __( 'Enable API Webhook for status tracking updates', 'delyvax' ),
                'type'     	=> 'checkbox',
                'default'	=> ''
            ),
            'shop_name' => array(
                'title'    	=> __( 'Store - Contact Name', 'delyvax' ),
                'type' => 'text',
                'default' => __('Store name', 'delyvax'),
                'id' => 'delyvax_shop_name',
                'description' => __( 'e.g. John Woo' ),
            ),
            'shop_mobile' => array(
                'title'    	=> __( 'Store - Contact Mobile No', 'delyvax' ),
                'type' => 'text',
                'default' => __('60129908855', 'delyvax'),
                'id' => 'delyvax_shop_mobile',
                'description' => __( 'e.g. 60129908855' ),
            ),
            'shop_email' => array(
                'title'    	=> __( 'Store - Contact E-mail', 'delyvax' ),
                'type' => 'text',
                'default' => __('', 'delyvax'),
                'id' => 'delyvax_shop_email',
                'description' => __( 'e.g. your@email.com' ),
            ),
            'processing_days' => array(
                'title'    	=> __( 'Processing days', 'delyvax' ),
                'type' => 'text',
                'default' => __('0', 'delyvax'),
                'id' => 'delyvax_processing_days',
                'description' => __( 'Number of processing days. e.g. 0 - same day ship out; 1 - next day ship out.' ),
            ),
            'processing_hours' => array(
                'title'    	=> __( 'Processing hours', 'delyvax' ),
                'type' => 'text',
                'default' => __('0', 'delyvax'),
                'id' => 'delyvax_processing_hours',
                'description' => __( 'Number of processing hours. e.g. 1 - ship in 1 hour; 4 - ship in 4 hours.' ),
            ),
            'item_type' => array(
                'title'    	=> __( 'Default Order - Item type', 'delyvax' ),
                'type' => 'text',
                'default' => __('0', 'delyvax'),
                'id' => 'delyvax_item_type',
                'description' => __( 'Default order - package item type. e.g. DOCUMENT / PARCEL / FOOD / PACKAGE.' ),
            ),
            'weight_option' => array(
                'title'    	=> __( 'Weight consideration', 'delyvax' ),
                'type' => 'text',
                'default' => __('BEST', 'delyvax'),
                'id' => 'delyvax_item_type',
                'description' => __( 'e.g. BEST-Whichever is higher / ACTUAL-Actual weight / VOL-Volumetric Weight.' ),
            ),
            'volumetric_constant' => array(
                'title'    	=> __( 'Volumetric weight constant', 'delyvax' ),
                'type' => 'text',
                'default' => __('5000', 'delyvax'),
                'id' => 'delyvax_item_type',
                'description' => __( 'e.g. 1000 / 5000 / 6000.' ),
            ),
            // 'task_item_type' => array(
            //     'title'    	=> __( 'Default Task - Item type', 'delyvax' ),
            //     'type' => 'text',
            //     'default' => __('0', 'delyvax'),
            //     'id' => 'delyvax_task_item_type',
            //     'description' => __( 'Default task - package item type. e.g. DOCUMENT / PARCEL / FOOD / PACKAGE.' ),
            // ),
            // 'split_tasks' => array(
            //     'title'    	=> __( 'Fulfilment by vendors', 'delyvax' ),
            //     'id'       	=> 'delyvax_split_tasks',
            //     'description'  	=> __( 'Create tasks and assign to vendors by ext id type and ext id', 'delyvax' ),
            //     'type'     	=> 'checkbox',
            //     'default'	=> ''
            // ),
            // 'ext_id_type' => array(
            //     'title'    	=> __( 'Personnel External ID Type', 'delyvax' ),
            //     'type' => 'text',
            //     'default'	=> '',
            //     'id'       	=> 'delyvax_ext_id_type',
            //     'description'  	=> __( 'Personnel External ID Type. e.g. dokan', 'delyvax' ),
            // ),
            // 'pickup_minutes' => array(
            //     'title'    	=> __( 'Fulfilment minutes before delivery', 'delyvax' ),
            //     'type' => 'text',
            //     'default' => __('0', 'delyvax'),
            //     'id' => 'delyvax_processing_hours',
            //     'description' => __( 'Number of minutes before delivery. e.g. 30 - 30 minutes befor delivery time; 60 - 60 minutes befor delivery time.' ),
            // ),
            // 'api_webhook_key' => array(
            //     'title' => __('API API Webhook Key', 'delyvax'),
            //     'type' => 'text',
            //     'default' => __('', 'delyvax'),
            //     'id' => 'delyvax_api_webhook_key',
            //     'description' => __( 'Do not touch this. We will automatically update this field once the system subscribed to DelyvaX webhook.'),
            // ),
            array(
                'title' => __( 'Shipping Rate Adjustments', 'delyvax' ),
                'type' => 'title',
                'id' => 'wc_settings_delyvax_shipping_rate_adjustment',
                'description' => __( 'Formula, shipping cost = shipping price + % rate + flat rate' ),
            ),
            'rate_adjustment_percentage' => array(
                'title' => __('Percentage Rate %', 'delyvax'),
                'type' => 'text',
                'default' => __('0', 'delyvax'),
                'id' => 'delyvax_rate_adjustment_percentage'
            ),
            'rate_adjustment_flat' => array(
                'title' => __('Flat Rate', 'delyvax'),
                'type' => 'text',
                'default' => __('0', 'delyvax'),
                'id' => 'delyvax_rate_adjustment_flat'
            ),
            'rate_adjustment_type' => array(
                'title' => __('Rate Adjustment Type ("discount"/"markup")', 'delyvax'),
                'type' => 'text',
                'default' => __('discount', 'delyvax'),
                'id' => 'delyvax_rate_rate_adjustment_type'
            )
          );
      }

      //instant quote
      /**
       * calculate_shipping function.
       *
       * @param mixed $package
       */
      public function calculate_shipping($package = array())
      {
            // print_r($package);

            $status_allow_checkout = true;

            $pdestination = $package["destination"];
            $items = array();
            $product_factory = new WC_Product_Factory();
            $currency = get_woocommerce_currency();

            $total_weight = 0;
            $total_dimension = 0;
            $total_volumetric_weight = 0;

            if (defined('WOOCS_VERSION')) {
                $currency = get_option('woocs_welcome_currency');
            }
            if (method_exists(WC()->cart, 'get_discount_total')) {
                $total_discount = WC()->cart->get_discount_total();
            } elseif (method_exists(WC()->cart, 'get_cart_discount_total')) {
                $total_discount = WC()->cart->get_cart_discount_total();
            } else {
                $total_discount = 0;
            }
            if (method_exists(WC()->cart, 'get_subtotal')) {
                $total_cart_without_discount = WC()->cart->get_subtotal();
            } else {
                $total_cart_without_discount = WC()->cart->subtotal;
            }
            if (!empty($total_discount) && ($total_discount > 0)) {
                $discount_for_item = ($total_discount / $total_cart_without_discount) * 100;
                $this->setDiscountForItem($discount_for_item);
                unset($discount_for_item);
            }
            foreach ($package["contents"] as $key => $item) {
                $product = $product_factory->get_product($item["product_id"]);
                $skip_shipping_class = $this->get_option('skip_shipping_class');
                if (!empty($skip_shipping_class) && ($product->get_shipping_class() === $skip_shipping_class)) {
                    continue;
                }
                if (WC()->version < '2.7.0') {
                    // if this item is variation, get variation product instead
                    if ($item["data"]->product_type == "variation") {
                        $product = $product_factory->get_product($item["variation_id"]);
                    }
                    // exclude virtual and downloadable product
                    if ($item["data"]->virtual == "yes") {
                        continue;
                    }
                } else {
                    if ($item["data"]->get_type() == "variation") {
                        $product = $product_factory->get_product($item["variation_id"]);
                    }
                    if ($item["data"]->get_virtual() == "yes") {
                        continue;
                    }
                }

                for ($i = 0; $i < $item["quantity"]; $i++) {
                    $items[] = array(
                        "actual_weight" => $this->weightToKg($product->get_weight()),
                        "height" => $this->defaultDimension($this->dimensionToCm($product->get_height())),
                        "width" => $this->defaultDimension($this->dimensionToCm($product->get_width())),
                        "length" => $this->defaultDimension($this->dimensionToCm($product->get_length())),
                        "declared_currency" => $currency,
                        "declared_customs_value" => $this->declaredCustomsValue($item['line_subtotal'], $item['quantity']),
                        "identifier_id" => array_key_exists("variation_id", $item) ? ($item["variation_id"] == 0 ? $item["product_id"] : $item["variation_id"]) : $item["product_id"]
                    );

                    $total_weight = $total_weight + $this->weightToKg($product->get_weight());

                    $total_dimension = $total_dimension + ($this->defaultDimension($this->dimensionToCm($product->get_width()))
                          * $this->defaultDimension($this->dimensionToCm($product->get_length()))
                          * $this->defaultDimension($this->dimensionToCm($product->get_height())));
                }
            }
            if (method_exists(WC()->cart, 'get_cart_contents_total')) {
                $total_cart_with_discount = (float)WC()->cart->get_cart_contents_total();
            } else {
                $total_cart_with_discount = WC()->cart->cart_contents_total;
            }

            if ($this->control_discount != $total_cart_with_discount) {
                if (is_array($items) && isset($items[0]) && isset($items[0]['declared_customs_value'])) {
                    $diff = round(($total_cart_with_discount - $this->control_discount), 2);
                    $items[0]['declared_customs_value'] += $diff;
                    $this->addControlDiscount($diff);
                    unset($diff);
                }
            }

            // The main address pieces:
            $store_address_1     = get_option( 'woocommerce_store_address' );
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

            $weight_option = $settings['weight_option'] ?? 'BEST';
            $volumetric_constant = $settings['volumetric_constant'] ?? '5000';

            if(function_exists(dokan_get_seller_id_by_order) && function_exists(dokan_get_store_info))
            {
                $seller_id = $package['seller_id'];

                if($seller_id)
                {
                    $store_info = dokan_get_store_info( $seller_id );
                    if($store_info)
                    {
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
                }
            }

            $origin = array(
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
            );

            $destination = array(
                "address1" => $pdestination["address"],
                "address2" => $pdestination["address_2"],
                "city" => $pdestination["city"],
                "state" => $pdestination["state"],
                "postcode" => $pdestination["postcode"],
                "country" => $pdestination["country"]
                // "coord" => array(
                //     "lat" => "",
                //     "lon" => ""
                // )
            );

            //calculate volumetric weight
            $total_actual_weight = $total_weight;

            if($total_dimension > 0)
            {
                if($volumetric_constant == 1000)
                {
                    $total_volumetric_weight = $total_dimension/1000;
                }else if($volumetric_constant == 6000)
                {
                    $total_volumetric_weight = $total_dimension/6000;
                }else {
                    $total_volumetric_weight = $total_dimension/5000;
                }
            }else {
                $total_volumetric_weight = $total_actual_weight;
            }

            if($weight_option == 'ACTUAL')
            {
                $total_weight = $total_actual_weight;
            }else if($weight_option == 'VOL')
            {
                $total_weight = $total_volumetric_weight;
            }else {
                if($total_actual_weight > $total_volumetric_weight)
                {
                    $total_weight = $total_actual_weight;
                }else {
                    $total_weight = $total_volumetric_weight;
                }
            }
            //

            //
            $weight = array(
              "unit" => "kg",
              "value" => $total_weight
            );

            //
            $codAmount = 0;
            $cod = array(
              "amount" => $codAmount,
              "currency" => $currency,
            );

            //start DelyvaX API
            if (!class_exists('DelyvaX_Shipping_API')) {
                include_once 'delyvax-api.php';
            }

            $rates = array();

            try {
                if(strlen($pdestination["address"]) >= 3 && strlen($pdestination["country"]) >= 2 && $total_weight > 0)
                {
                    $rates = DelyvaX_Shipping_API::getPriceQuote($origin, $destination, $weight, $cod);
                }
            } catch (Exception $e) {
                $rates = array();
            }

            if($pdestination["address"] == '' || $pdestination["country"] == '' || $total_weight <= 0 )
            {
                $status_allow_checkout = false;
            }

            $services = $rates['services'];

            foreach ($services as $shipper) {
                if (isset($shipper['service']['name'])) {
                    $settings = get_option( 'woocommerce_delyvax_settings' );

                    $rate_adjustment_type = $settings['rate_adjustment_type'] ?? 'discount';

                    $ra_percentage = $settings['rate_adjustment_percentage'] ?? 1;
                    $percentRate = $ra_percentage / 100 * $shipper['price']['amount'];

                    $flatRate = $settings['rate_adjustment_flat'] ?? 0;

                    if($rate_adjustment_type == 'markup')
                    {
                        $cost = round($shipper['price']['amount'] + $percentRate + $flatRate, 2);
                    }else {
                        $cost = round($shipper['price']['amount'] - $percentRate - $flatRate, 2);
                    }

                    $rate = array(
                        'id' => $shipper['service']['code'],
                        'label' => $shipper['service']['name'],
                        'cost' => $cost,
                        'taxes' => 'false',
                        'calc_tax' => 'per_order',
                        'meta_data' => array(
                            'service_code' => $shipper['service']['code'],
                        ),
                    );

                    if($status_allow_checkout)
                    {
                        // Register the rate
                        wp_cache_add('delyvax' . $rate["id"], $rate);
                        $this->add_rate($rate);
                    }
                }
            }
            //end DelyvaX API
        }

      protected function weightToKg($weight)
        {
            $weight_unit = get_option('woocommerce_weight_unit');
            // convert other unit into kg
            if ($weight_unit != 'kg') {
                if ($weight_unit == 'g') {
                    return $weight * 0.001;
                } else if ($weight_unit == 'lbs') {
                    return $weight * 0.453592;
                } else if ($weight_unit == 'oz') {
                    return $weight * 0.0283495;
                }
            }
            // already kg
            return $weight;
        }

        protected function dimensionToCm($length)
        {
            $dimension_unit = get_option('woocommerce_dimension_unit');
            // convert other units into cm
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
            // already in cm
            return $length;
        }

        protected function defaultDimension($length)
        {
            // default dimension to 1 if it is 0
            return $length > 0 ? $length : 1;
        }

        protected function setDiscountForItem($count)
        {
            $this->discount_for_item = $count;
        }

        protected function getDiscountForItem()
        {
            return $this->discount_for_item;
        }

        protected function addControlDiscount($val)
        {
            $this->control_discount += $val;
        }

        protected function declaredCustomsValue($subtotal, $count)
        {
            $price = (float)(($subtotal / $count) * ((100 - $this->getDiscountForItem()) / 100));
            $price = round($price, 2);
            $this->addControlDiscount($price);
            return $price;
        }
  }
}

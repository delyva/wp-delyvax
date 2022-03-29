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
                'title'    	=> __( 'After order has been paid', 'delyvax' ),
                'id'       	=> 'delyvax_create_shipment_on_paid',
                'description'  	=> __( 'After order has been paid, create and process the delivery order immediately OR only create delivery order and save as draft, then you can confirm the delivery order in the customer portal.', 'delyvax' ),
                'default'	=> '',
                'type'    => 'select',
                'options' => array(
                  '' => __( 'Save as draft', 'woocommerce' ),
                  'yes' => __( 'Process immediately', 'woocommerce' ),
                  'nothing' => __( 'Do nothing', 'woocommerce' )
                )
            ),
            'create_shipment_on_confirm' => array(
                'title'    	=> __( 'After order marked as preparing', 'delyvax' ),
                'id'       	=> 'delyvax_create_shipment_on_confirm',
                'description'  	=> __( 'After order has been marked as preparing, create and process the delivery order immediately OR only create delivery order and save as draft then, you can confirm the delivery order in the customer portal.', 'delyvax' ),
                'default'	=> '',
                'type'    => 'select',
                'options' => array(
                  '' => __( 'Save as draft', 'woocommerce' ),
                  'yes' => __( 'Process immediately', 'woocommerce' ),
                  'nothing' => __( 'Do nothing', 'woocommerce' )
                )
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
                'title'    	=> __( 'Auto Change Order Status', 'delyvax' ),
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
            'multivendor' => array(
                'title'    	=> __( 'Multi-vendor system', 'delyvax' ),
                'default' => __('SINGLE', 'delyvax'),
                'id' => 'delyvax_multivendor',
                'description' => __( '' ),
                'type'    => 'select',
                'options' => array(
                  'SINGLE' => __( 'Single vendor', 'woocommerce' ),
                  'DOKAN' => __( 'Dokan', 'woocommerce' ),
                  'WCFM' => __( 'WCFM', 'woocommerce' ),
                )
            ),
            'processing_days' => array(
                'title'    	=> __( 'Processing days', 'delyvax' ),
                'default' => __('1', 'delyvax'),
                'id' => 'delyvax_processing_days',
                'description' => __( 'Number of processing days. e.g. 0 - same day ship out; 1 - next day ship out.' ),
                'type'    => 'select',
                'options' => array(
                  '0' => __( 'Same day', 'woocommerce' ),
                  '1' => __( 'Next (1) day', 'woocommerce' ),
                  '2' => __( 'Next (2) day', 'woocommerce' ),
                  '3' => __( 'Next (3) day', 'woocommerce' ),
                  '4' => __( 'Next (4) day', 'woocommerce' ),
                  '5' => __( 'Next (5) day', 'woocommerce' ),
                  '6' => __( 'Next (6) day', 'woocommerce' ),
                  '7' => __( 'Next (7) day', 'woocommerce' ),
                )
            ),
            'processing_hours' => array(
                'title'    	=> __( 'Processing hours', 'delyvax' ),
                'default' => __('1', 'delyvax'),
                'id' => 'delyvax_processing_hours',
                'description' => __( 'Number of processing hours if processing day is 0. e.g. 0 - ship now; 1 - ship in 1 hour; 4 - ship in 4 hours.' ),
                'type'    => 'select',
                'options' => array(
                  '0' => __( 'Now', 'woocommerce' ),
                  '1' => __( 'Next (1) hour', 'woocommerce' ),
                  '2' => __( 'Next (2) hours', 'woocommerce' ),
                  '3' => __( 'Next (3) hours', 'woocommerce' ),
                  '4' => __( 'Next (4) hours', 'woocommerce' ),
                  '5' => __( 'Next (5) hours', 'woocommerce' ),
                  '6' => __( 'Next (6) hours', 'woocommerce' ),
                  '7' => __( 'Next (7) hours', 'woocommerce' ),
                  '8' => __( 'Next (8) hours', 'woocommerce' ),
                )
            ),
            'processing_time' => array(
                'title'    	=> __( 'Processing time', 'delyvax' ),
                'default' => __('11:00', 'delyvax'),
                'id' => 'delyvax_processing_time',
                'description' => __( 'If processing day is 1 or more, system will use this time as processing time and ignore processing hour. e.g. processing day: 1 and processing time: 11:00, delivery order will be scheduled to tomorrow at 11:00.' ),
                'type'    => 'select',
                'options' => array(
                  '08:00' => __( '08:00', 'woocommerce' ),
                  // '08:30' => __( '08:30', 'woocommerce' ),
                  '09:00' => __( '09:00', 'woocommerce' ),
                  // '09:30' => __( '09:30', 'woocommerce' ),
                  '10:00' => __( '10:00', 'woocommerce' ),
                  // '10:30' => __( '10:30', 'woocommerce' ),
                  '11:00' => __( '11:00', 'woocommerce' ),
                  // '11:30' => __( '11:30', 'woocommerce' ),
                  '12:00' => __( '12:00', 'woocommerce' ),
                  // '12:30' => __( '12:30', 'woocommerce' ),
                  '13:00' => __( '13:00', 'woocommerce' ),
                  // '13:30' => __( '13:30', 'woocommerce' ),
                  '14:00' => __( '14:00', 'woocommerce' ),
                  // '14:30' => __( '14:30', 'woocommerce' ),
                  '15:00' => __( '15:00', 'woocommerce' ),
                  // '15:30' => __( '15:30', 'woocommerce' ),
                  '16:00' => __( '16:00', 'woocommerce' ),
                  // '16:30' => __( '16:30', 'woocommerce' ),
                  '17:00' => __( '17:00', 'woocommerce' ),
                  // '17:30' => __( '17:30', 'woocommerce' ),
                  '18:00' => __( '18:00', 'woocommerce' ),
                  // '18:30' => __( '18:30', 'woocommerce' ),
                  '19:00' => __( '19:00', 'woocommerce' ),
                  // '19:30' => __( '19:30', 'woocommerce' ),
                  '20:00' => __( '20:00', 'woocommerce' ),
                )
            ),
            'item_type' => array(
                'title'    	=> __( 'Default Order - Item type', 'delyvax' ),
                'default' => __('PARCEL', 'delyvax'),
                'id' => 'delyvax_item_type',
                'description' => __( 'Default order - package item type. e.g. DOCUMENT / PARCEL / FOOD / PACKAGE.' ),
                'type'    => 'select',
                'options' => array(
                  'PARCEL' => __( 'PARCEL', 'woocommerce' ),
                  'DOCUMENT' => __( 'DOCUMENT', 'woocommerce' ),
                  'FOOD' => __( 'FOOD', 'woocommerce' ),
                  'PACKAGE' => __( 'PACKAGE', 'woocommerce' ),
                  'BULKY' => __( 'BULKY', 'woocommerce' )
                )
            ),
            'weight_option' => array(
                'title'    	=> __( 'Weight consideration', 'delyvax' ),
                'default' => __('BEST', 'delyvax'),
                'id' => 'delyvax_weight_option',
                'description' => __( 'e.g. BEST-Whichever is higher / ACTUAL-Actual weight / VOL-Volumetric Weight.' ),
                'type'    => 'select',
                'options' => array(
                  'BEST' => __( 'BEST - Whichever is higher', 'woocommerce' ),
                  'ACTUAL' => __( 'ACTUAL - Actual weight', 'woocommerce' ),
                  'VOL' => __( 'VOL - Volumetric Weight', 'woocommerce' )
                )
            ),
            'volumetric_constant' => array(
                'title'    	=> __( 'Volumetric weight constant', 'delyvax' ),
                'default' => __('5000', 'delyvax'),
                'id' => 'delyvax_volumetric_constant',
                'description' => __( 'e.g. 1000 / 5000 / 6000.' ),
                'type'    => 'select',
                'options' => array(
                  '5000' => __( '5000', 'woocommerce' ),
                  '6000' => __( '6000', 'woocommerce' ),
                  '1000' => __( '1000', 'woocommerce' )
                )
            ),
            'source' => array(
                'title'    	=> __( 'Source of', 'delyvax' ),
                'type' => 'text',
                'default' => __('woocommerce', 'delyvax'),
                'id' => 'delyvax_source',
                'description' => __( 'Leave empty or type `woocommerce` or your web design agency code.' ),
            ),
            'include_order_note' => array(
                'title'    	=> __( 'Order note', 'delyvax' ),
                'default' => __('orderno', 'delyvax'),
                'id' => 'delyvax_include_order_note',
                'type'    => 'select',
                'options' => array(
                  'orderno' => __( 'Include order no.', 'woocommerce' ),
                  'ordernproduct' => __( 'Include order no. + product info', 'woocommerce' ),
                  'empty' => __( 'Empty', 'woocommerce' )
                )
            ),
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
                'default' => __('discount', 'delyvax'),
                'id' => 'delyvax_rate_rate_adjustment_type',
                'type'    => 'select',
                'options' => array(
                  'discount' => __( 'Discount', 'woocommerce' ),
                  'markup' => __( 'Markup', 'woocommerce' )
                )
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
            $status_allow_checkout = true;

            $settings = get_option( 'woocommerce_delyvax_settings' );
            $multivendor_option = $settings['multivendor'];

            $checkout_pricing_enable = $settings['enable'];

            if($checkout_pricing_enable != 'yes')
            {
                return;
            }

            $weight_unit = get_option('woocommerce_weight_unit');

            $pdestination = $package["destination"];
            $items = array();
            $product_factory = new WC_Product_Factory();
            $currency = get_woocommerce_currency();

            $total_weight = 0;
            $total_dimension = 0;
            $total_volumetric_weight = 0;

            $product_id = null;

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
                $product_id = $item["product_id"];

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

                    $total_weight = $total_weight + $product->get_weight();

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

            if($multivendor_option == 'DOKAN')
            {
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
            }else if($multivendor_option == 'DOKAN')
            {
                if(function_exists(wcfm_get_vendor_id_by_post))
                {
                    $vendor_id = wcfm_get_vendor_id_by_post( $product_id );

                    $store_info = get_user_meta( $vendor_id, 'wcfmmp_profile_settings', true );

                    if($store_info)
                    {
                        $store_name = $store_info['store_name'];
                        $store_first_name = $store_info['store_name'];
                        $store_last_name = $store_info['store_name'];
                        $store_phone = $store_info['phone'];
                        $store_email = $store_info['store_email'];
                        $store_address_1 = isset( $store_info['address']['street_1'] ) ? $store_info['address']['street_1'] : '';
                        $store_address_2 = isset( $store_info['address']['street_2'] ) ? $store_info['address']['street_2'] : '';
                        $store_city     = isset( $store_info['address']['city'] ) ? $store_info['address']['city'] : '';
                        $store_state    = isset( $store_info['address']['state'] ) ? $store_info['address']['state'] : '';
                        $store_postcode      = isset( $store_info['address']['zip'] ) ? $store_info['address']['zip'] : '';
                        $store_country  = isset( $store_info['address']['country'] ) ? $store_info['address']['country'] : '';
                    }
                }
            }else {
                // echo 'no multivendor';
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
            $total_actual_weight = $this->weightToKg($total_weight);

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
              "value" => $total_weight,
              "unit" => 'kg'
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
                //if domestic delivery must have postcode, else
                if( ($total_weight > 0 && strlen($store_country) >= 2 && strlen($pdestination["country"]) >= 2 && strlen($pdestination["postcode"]) >= 3)
                    || ($total_weight > 0 && strlen($store_country) >= 2 && strlen($pdestination["country"]) >= 2 && ($store_country != $pdestination["country"]) )
                  )
                {
                    $rates = DelyvaX_Shipping_API::getPriceQuote($origin, $destination, $weight, $cod);
                }else {
                    $status_allow_checkout = false;
                }
            } catch (Exception $e) {
                $rates = array();
                $status_allow_checkout = false;
            }

            $services = $rates['services'];

            foreach ($services as $shipper) {
                if (isset($shipper['service']['name'])) {

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

                    $service_label = $shipper['service']['name'];
                    $service_label = str_replace('(DROP)', '', $service_label);
                    $service_label = str_replace('(PICKUP)', '', $service_label);
					          $service_label = str_replace('(PARCEL)', '', $service_label);
					          //$service_label = str_replace('(COD)', '', $service_label);

                    $service_code = $shipper['service']['serviceCompany']['companyCode'] ? $shipper['service']['serviceCompany']['companyCode'] : $shipper['service']['code'];

                    $rate = array(
                        'id' => $service_code,
                        'label' => $service_label,
                        'cost' => $cost,
                        'taxes' => 'false',
                        'calc_tax' => 'per_order',
                        'meta_data' => array(
                            'service_code' => $service_code,
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

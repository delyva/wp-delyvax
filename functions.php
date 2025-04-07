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

add_action('init', 'register_shipping_phone_validation_filter');

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
    delete_option('delyvax_api_token');
    delete_option('delyvax_api_webhook_enable');
    delete_option('delyvax_api_webhook_key');
    delete_option('delyvax_customer_id');
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

    // ---
    delete_option('delyvax_company_id');
    delete_option('delyvax_company_name');    
    delete_option('delyvax_user_id');
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

function delyvax_get_order_shipping_method($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return null;
    }
    $shipping_method = null;
    foreach($order->get_items('shipping') as $item_id => $item) {
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
    $settings = get_option('woocommerce_delyvax_settings');
    $processing_days = $settings['processing_days'];
    $processing_hours = $settings['processing_hours'];
    $processing_time = $settings['processing_time'];

    // Fetch WordPress timezone setting
    $timezone_string = get_option('timezone_string') ?: 'UTC';
    $wp_timezone = new DateTimeZone($timezone_string);

    try {
        $delivery_date = new DateTime('now', $wp_timezone);

        // Processing days
        if ($processing_days > 0) {
            $delivery_date->modify("+{$processing_days} day");
        }

        // Processing hours if no days
        if ($processing_days == 0 && $processing_hours > 0) {
            $delivery_date->add(new DateInterval("PT{$processing_hours}H"));
        }

        // Set delivery time
        if (!empty($processing_time)) {
            [$hours, $minutes] = explode(':', $processing_time);
            $delivery_date->setTime($hours, $minutes, 0);
        }

        $order->update_meta_data('dx_delivery_datetime', $delivery_date->format('Y-m-d H:i:s'));
        $order->save();

    } catch (Exception $e) {
        echo 'Message: ' . $e->getMessage();
        $now = new DateTime();
        $order->update_meta_data('dx_delivery_datetime', $now->format('Y-m-d H:i:s'));
        $order->save();
    }
}

function delyvax_create_order($order, $user, $process=false) {
    try {
        //create order
        //start DelyvaX API
        if (!class_exists('DelyvaX_Shipping_API')) {
            include_once 'includes/delyvax-api.php';
        }

        //ignore local_pickup
        $shipping_method = delyvax_get_order_shipping_method($order->get_id());
        if($shipping_method == 'local_pickup') return;

        //skip virtual product
        if ( only_virtual_order_items( $order ) ) return; 
        //

        $delyvax_order_id = $order->get_meta( 'DelyvaXOrderID');
        $DelyvaXTrackingCode = $order->get_meta( 'DelyvaXTrackingCode');

        if(!$DelyvaXTrackingCode)
        {
            $resultCreate = delyvax_post_create_order($order, $process);
        }
    } catch (Exception $e) {
        print_r($e);
    }
}

/**
 * Optimize box dimensions based on total volume and maximum item dimensions for multiple items
 */
function optimize_box_dimensions($total_volume, $max_length, $max_width, $max_height) {
    // Ensure box is at least as large as the biggest item
    $box_length = $max_length;
    $box_width = $max_width;
    $box_height = $max_height;
    
    // Adjust dimensions to accommodate total volume while maintaining proportions
    $current_volume = $box_length * $box_width * $box_height;
    if ($current_volume < $total_volume) {
        $scale_factor = pow($total_volume / $current_volume, 1/3);
        $box_length *= $scale_factor;
        $box_width *= $scale_factor;
        $box_height *= $scale_factor;
    }
    
    return [
        'length' => ceil($box_length),
        'width' => ceil($box_width),
        'height' => ceil($box_height),
        'volume' => ceil($box_length * $box_width * $box_height)
    ];
}

function process_delivery_pricing($main_order, $deliveryPrice, $total_quantity, $total_price)
{
    $settings = get_option('woocommerce_delyvax_settings');
    $deliveryMarkup = 0;
    $deliveryDiscount = 0;

    //store price discount or markup
    if ($deliveryPrice && $deliveryPrice > 0) {
        $rate_adjustment_type = $settings['rate_adjustment_type'] ?? 'discount';

        $ra_percentage = $settings['rate_adjustment_percentage'] ?? 1;
        $percentRate = $ra_percentage / 100 * $deliveryPrice;

        $flatRate = $settings['rate_adjustment_flat'] ?? 0;

        if ($rate_adjustment_type == 'markup') {
            $deliveryMarkup = round($percentRate + $flatRate, 2);
        } else {
            $deliveryDiscount = round($percentRate + $flatRate, 2);
        }

        //handle free shipping
        $free_shipping_type = $settings['free_shipping_type'] ?? '';
        $free_shipping_condition = $settings['free_shipping_condition'] ?? '';
        $free_shipping_value = $settings['free_shipping_value'] ?? '0';

        if ($free_shipping_type == 'total_quantity') {
            if ($free_shipping_condition == '>') {
                if ($total_quantity > $free_shipping_value) {
                    $deliveryDiscount = $deliveryPrice;
                }
            } else if ($free_shipping_condition == '>=') {
                if ($total_quantity >= $free_shipping_value) {
                    $deliveryDiscount = $deliveryPrice;
                }
            } else if ($free_shipping_condition == '==') {
                if ($total_quantity == $free_shipping_value) {
                    $deliveryDiscount = $deliveryPrice;
                }
            } else if ($free_shipping_condition == '<=') {
                if ($total_quantity <= $free_shipping_value) {
                    $deliveryDiscount = $deliveryPrice;
                }
            } else if ($free_shipping_condition == '<') {
                if ($total_quantity < $free_shipping_value) {
                    $deliveryDiscount = $deliveryPrice;
                }
            }
        } else if ($free_shipping_type == 'total_amount') {
            if ($free_shipping_condition == '>') {
                if ($total_price > $free_shipping_value) {
                    $deliveryDiscount = $deliveryPrice;
                }
            } else if ($free_shipping_condition == '>=') {
                if ($total_price >= $free_shipping_value) {
                    $deliveryDiscount = $deliveryPrice;
                }
            } else if ($free_shipping_condition == '==') {
                if ($total_price == $free_shipping_value) {
                    $deliveryDiscount = $deliveryPrice;
                }
            } else if ($free_shipping_condition == '<=') {
                if ($total_price <= $free_shipping_value) {
                    $deliveryDiscount = $deliveryPrice;
                }
            } else if ($free_shipping_condition == '<') {
                if ($total_price < $free_shipping_value) {
                    $deliveryDiscount = $deliveryPrice;
                }
            }
        }
    }

    return array(
        'deliveryMarkup' => $deliveryMarkup,
        'deliveryDiscount' => $deliveryDiscount
    );
}

function get_product_dimensions($product) {
    return array(
        'weight' => delyvax_default_weight(delyvaX_weight_to_kg($product->get_weight())),
        'length' => delyvax_default_dimension(delyvax_dimension_to_cm($product->get_length())),
        'width' => delyvax_default_dimension(delyvax_dimension_to_cm($product->get_width())),
        'height' => delyvax_default_dimension(delyvax_dimension_to_cm($product->get_height()))
    );
}

function get_receiver_address($order) {
    $r_shipping_phone = $order->get_shipping_phone();
    
    if ($order->get_shipping_address_1() || $order->get_shipping_address_2()) {
        return [
            'contactName' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'contactEmail' => $order->get_billing_email(),
            'contactNumber' => $r_shipping_phone ?: $order->get_billing_phone(),
            'location' => [
                'address' => $order->get_shipping_address_1(),
                'address2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'country' => $order->get_shipping_country(),
                'postcode' => $order->get_shipping_postcode(),
            ]
        ];
    }
    
    return [
        'contactName' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        'contactEmail' => $order->get_billing_email(),
        'contactNumber' => $order->get_billing_phone(),
        'location' => [
            "address" => $order->get_billing_address_1(),
            "address2" => $order->get_billing_address_2(),
            "city" => $order->get_billing_city(),
            "state" => $order->get_billing_state(),
            "postcode" => $order->get_billing_postcode(),
            "country" => $order->get_billing_country(),
        ]
    ];
}

function delyvax_post_create_order($order, $process = false)
{
    if (!$order) return false;
    $settings = get_option('woocommerce_delyvax_settings');
    $multivendor_option = $settings['multivendor'];
    $customer_id = $settings['customer_id'];
    $company_code = $settings['company_code'];

    $delyvax_order_id = $order->get_meta('DelyvaXOrderID');
    $store_name = get_bloginfo('name');

    $delivery_datetime = $order->get_meta('dx_delivery_datetime');
    $wp_timezone = new DateTimeZone(get_option('timezone_string') ?: 'UTC');

    if ($delivery_datetime) {
        $scheduledAt = DateTime::createFromFormat('Y-m-d H:i:s', $delivery_datetime, $wp_timezone);
        $scheduledAt->setTimezone($wp_timezone);
    } else {
        $scheduledAt = new DateTime('now', $wp_timezone);
    }

    $main_order = $order;

    // Don't create shipment for parent order
    if ($multivendor_option == 'DOKAN') {
        $has_sub_order = $order->get_meta('has_sub_order');
        if ($has_sub_order == '1') return;
    } else if ($order->get_parent_id()) {
        $main_order = wc_get_order($order->get_parent_id());
    }

    // Iterating through order shipping items
    $DelyvaXServiceCode = $main_order->get_meta('DelyvaXServiceCode');
    $include_order_note = $settings['include_order_note'] ?? '';
    $insurance_premium = $settings['insurance_premium'] ?? '';
    $total_weight = 0;
    $total_volume = 0;
    $max_length = 0;
    $max_width = 0;
    $max_height = 0;
    $order_notes = '';
    $product_id = null;

    if ($DelyvaXServiceCode) {
        $serviceCode = $DelyvaXServiceCode;
    } else {
        foreach ($main_order->get_items('shipping') as $item_id => $shipping_item_obj) {
            $serviceobject = $shipping_item_obj->get_meta_data();

            for ($i = 0; $i < sizeof($serviceobject); $i++) {
                if ($serviceobject[$i]->key == "service_code") {
                    $serviceCode = $serviceobject[0]->value;

                    $main_order->update_meta_data('DelyvaXServiceCode', $serviceCode);
                    $main_order->save();
                }
            }
        }
    }

    $count = 0;
    $total_quantity = 0;
    $total_price = 0;
    $inventories = [];

    foreach ($main_order->get_items() as $item) {
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
        $product_store_name = get_bloginfo('name');

        $_pf = new WC_Product_Factory();

        $product = $_pf->get_product($product_id);
        
        $weightDimension = [
            'weight' => 0,
            'length' => 0,
            'width' => 0,
            'height' => 0,
        ];

        if ($product->is_type('variable')) {
            $variation = $_pf->get_product($product_variation_id);

            if ($variation) {
                $product_name = $variation->get_name();
                $weightDimension = get_product_dimensions($variation);
            }
        } else {
            $weightDimension = get_product_dimensions($product);
        }

        $product_weight = $weightDimension['weight'];
        $product_length = $weightDimension['length'];
        $product_width = $weightDimension['width'];
        $product_height = $weightDimension['height'];

        // Calculate volume for this product considering quantity
        $item_volume = $product_length * $product_width * $product_height * $quantity;
        $total_volume += $item_volume;

        // Track maximum dimensions
        $max_length = max($max_length, $product_length);
        $max_width = max($max_width, $product_width);
        $max_height = max($max_height, $product_height);

        $product_description = '[' . $product_store_name . '] ' . $product_name . ' - Order ID #' . $main_order->get_id();

        $inventories[] = [
            "name" => $product_name,
            "value" => array(
                "amount" => $total,
                "currency" => $main_order->get_currency(),
            ),
            "weight" => array(
                "value" => $product_weight,
                "unit" => 'kg'
            ),
            "quantity" => $quantity,
            "description" => $product_description
        ];

        $total_weight = $total_weight + ($product_weight * $quantity);
        $total_price = $total_price + $subtotal;
        $total_quantity = $total_quantity + $quantity;

        if ($include_order_note == 'ordernproduct') {
            $order_notes = $order_notes . '#' . ($count + 1) . '. [' . $store_name . '] ' . $product_name . ' X ' . $quantity . 'pcs. ';
        }
        $count++;
    }

    if ($delyvax_order_id && $process == true) {
        $resultProcess = delyvax_post_process_order($order, $delyvax_order_id);

        $trackingNo = $resultProcess["consignmentNo"];
        $main_order->update_meta_data('DelyvaXTrackingCode', $trackingNo);

        $main_order->update_status('wc-ready-to-collect', 'Delivery order number: ' . $trackingNo . ' - <a href="https://api.delyva.app/v1.0/order/' . $delyvax_order_id . '/label" target="_blank">Print label</a> - <a href="https://' . $company_code . '.delyva.app/customer/strack?trackingNo=' . $trackingNo . '" target="_blank">Track</a>.', false);

        $pricing = process_delivery_pricing($main_order, $resultProcess['price']['amount'], $total_quantity, $total_price);

        $main_order->update_meta_data('DelyvaXDeliveryPrice', $resultProcess['price']['amount']);
        $main_order->update_meta_data('DelyvaXMarkup', $pricing['deliveryMarkup']);
        $main_order->update_meta_data('DelyvaXDiscount', $pricing['deliveryDiscount']);
        $main_order->save();
    } else {
        $main_order = $order;
        $store_raw_country = get_option('woocommerce_default_country');

        // Split the country/state
        $split_country = explode(":", $store_raw_country);

        // Country and state separated:
        $store_country = $split_country[0];
        $store_state = $split_country[1];

        if ($include_order_note != 'empty')
            $order_notes = 'Order No: #' . $main_order->get_id() . ': ';

        $box_dimensions = optimize_box_dimensions($total_volume, $max_length, $max_width, $max_height);

        $receiver = get_receiver_address($order);
        $sender = [
            'contactName' => $settings['shop_name'],
            'contactEmail' => $settings['shop_email'],
            'contactNumber' => $settings['shop_mobile'],
            'location' => [
                'address' => get_option('woocommerce_store_address'),
                'address2' => get_option('woocommerce_store_address_2'),
                'city' => get_option('woocommerce_store_city'),
                'state' => $store_state,
                'country' => $store_country,
                'postcode' => get_option('woocommerce_store_postcode'),
            ]
        ];

        if ($multivendor_option == 'DOKAN') {
            if (function_exists('dokan_get_seller_id_by_order') && function_exists('dokan_get_store_info')) {
                $seller_id = dokan_get_seller_id_by_order($main_order->get_id());
                $store_info = dokan_get_store_info($seller_id);
                $user_info = get_userdata($seller_id);
                $store_info['email'] = $user_info->user_email;

                $product_store_name = $store_info['store_name'];

                if ($store_info['store_name'])
                    $sender['contactName'] = $store_info['store_name'];
                // if ($store_info['first_name'])
                //     $store_first_name = $store_info['first_name'];
                // if ($store_info['last_name'])
                //     $store_last_name = $store_info['last_name'];
                if ($store_info['phone'])
                    $sender['contactNumber'] = $store_info['phone'];
                if ($store_info['email'])
                    $sender['contactEmail'] = $store_info['email'];
                $sender['location']['address'] = $store_info['address']['street_1'];
                $sender['location']['address2'] = $store_info['address']['street_2'];
                $sender['location']['city'] = $store_info['address']['city'];
                $sender['location']['state'] = $store_info['address']['state'];
                $sender['location']['postcode'] = $store_info['address']['zip'];
                $sender['location']['country'] = $store_info['address']['country'];

                $origin_lat = isset($store_info['address']['lat']) ? $store_info['address']['lat'] : null;
                $origin_lon = isset($store_info['address']['lon']) ? $store_info['address']['lon'] : null;

                if ($origin_lat && $origin_lon) {
                    $sender['location']['coordinates'] = [
                        'lat' => $origin_lat,
                        'lom' => $origin_lon,
                    ];
                }
            }
        } else if ($multivendor_option == 'WCFM') {
            if (function_exists('wcfm_get_vendor_id_by_post')) {
                $vendor_id = $order->get_meta('_vendor_id');
                if (!$vendor_id && function_exists('wcfm_get_vendor_id_by_post')) {
                    $vendor_id = wcfm_get_vendor_id_by_post($product_id);
                }            

                $store_info = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true);

                if ($store_info) {
                    $sender['contactName'] = $store_name = $store_info['store_name'];
                    // $store_first_name = $store_info['store_name'];
                    // $store_last_name = $store_info['store_name'];
                    $sender['contactNumber'] = $store_info['phone'];
                    $sender['contactEmail'] = $store_info['store_email'] ? $store_info['store_email'] : $store_info['customer_support']['email'];
                    $sender['location']['address'] = isset($store_info['address']['street_1']) ? $store_info['address']['street_1'] : '';
                    $sender['location']['address2'] = isset($store_info['address']['street_2']) ? $store_info['address']['street_2'] : '';
                    $sender['location']['city'] = isset($store_info['address']['city']) ? $store_info['address']['city'] : '';
                    $sender['location']['state'] = isset($store_info['address']['state']) ? $store_info['address']['state'] : '';
                    $sender['location']['postcode'] = isset($store_info['address']['zip']) ? $store_info['address']['zip'] : '';
                    $sender['location']['country'] = isset($store_info['address']['country']) ? $store_info['address']['country'] : '';

                    $origin_lat = isset($store_info['address']['lat']) ? $store_info['address']['lat'] : null;
                    $origin_lon = isset($store_info['address']['lon']) ? $store_info['address']['lon'] : null;

                    if ($origin_lat && $origin_lon) {
                        $sender['location']['coordinates'] = [
                            'lat' => $origin_lat,
                            'lom' => $origin_lon,
                        ];
                    }
                }
            }
        } else if ($multivendor_option == 'MKING') {
            $vendor_id = marketking()->get_product_vendor($product_id);

            // $company = get_user_meta($vendor_id, 'billing_company', true);      
            $sender['contactName'] = marketking()->get_store_name_display($vendor_id);
            // $store_name = get_user_meta($vendor_id, 'marketking_store_name', true);
            // $store_first_name = get_user_meta($vendor_id, 'billing_first_name', true);
            // $store_last_name = get_user_meta($vendor_id, 'billing_last_name', true);
            $sender['contactNumber'] = get_user_meta($vendor_id, 'billing_phone', true);
            $sender['contactEmail'] = marketking()->get_vendor_email($vendor_id);
            // $store_email = get_user_meta($vendor_id, 'billing_email', true);

            $sender['location']['address'] = get_user_meta($vendor_id, 'billing_address_1', true);
            $sender['location']['address2'] = get_user_meta($vendor_id, 'billing_address_2', true);
            $sender['location']['city'] = get_user_meta($vendor_id, 'billing_city', true);
            $sender['location']['state'] = get_user_meta($vendor_id, 'billing_postcode', true);
            $sender['location']['postcode'] = get_user_meta($vendor_id, 'billing_state', true);
            $sender['location']['country'] = get_user_meta($vendor_id, 'billing_country', true);

            // $origin_lat = isset($store_info['address']['lat']) ? $store_info['address']['lat'] : null;
            // $origin_lon = isset($store_info['address']['lon']) ? $store_info['address']['lon'] : null;
        }

        $ms2781Request = [
            'customerId' => $customer_id,
            'orderNumber' => (string) $order->get_id(),
            'sender' => $sender,
            'receiver' => $receiver,
            'parcel' => [
                'weight' => [
                    "unit" => "kg",
                    "parcelWeight" => $total_weight,
                ],
                'quantity' => 1,
                'orderRemarks' => $order_notes,
            ],
            'inventory' => $inventories,
            'deliveryService' => [
                'serviceType' => $serviceCode,
                'pickupDateTime' => $scheduledAt->format('Y-m-d H:i:s'),
                'codAmount' => $order->get_payment_method() == 'cod' ? $total_price : 0,
                'insuranceAmount' => $insurance_premium == 'yes' ? $total_price : 0,
                'itemType' => $settings['item_type'] ? $settings['item_type'] : 'PARCEL',
            ],
            'source' => $settings["source"],

            'isDraft' => !$process,
        ];

        if (
            isset($box_dimensions)
            && floatval($box_dimensions['width']) > 0
            && floatval($box_dimensions['length']) > 0
            && floatval($box_dimensions['height']) > 0
        ) {
            $ms2781Request['parcel']['dimension'] = [
                "unit" => "cm",
                "width" => floatval($box_dimensions['width']),
                "length" => floatval($box_dimensions['length']),
                "height" => floatval($box_dimensions['height'])
            ];
        }

        $resultCreate = DelyvaX_Shipping_API::postCreateOrder($order, $ms2781Request);

        if ($resultCreate) {
            $deliveryService = $resultCreate["deliveryService"];
            $shipmentId = $deliveryService["orderId"];
            $trackingNo = $deliveryService["trackingNumber"];

            $main_order->update_meta_data('DelyvaXOrderID', $shipmentId);
            $main_order->update_meta_data('DelyvaXTrackingCode', $trackingNo);

            $deliveryCost = $deliveryService['deliveryCost']['amount'];

            if ($process) {
                $main_order->update_status('wc-ready-to-collect', 'Delivery order number: ' . $trackingNo . ' - <a href="https://api.delyva.app/v1.0/order/' . $shipmentId . '/label" target="_blank">Print label</a> - <a href="https://' . $company_code . '.delyva.app/customer/strack?trackingNo=' . $trackingNo . '" target="_blank">Track</a>.', false);
            }
        
            if ($deliveryCost > 0) {
                $pricing = process_delivery_pricing($main_order, $deliveryCost, $total_quantity, $total_price);
                $main_order->update_meta_data('DelyvaXDeliveryPrice', $deliveryCost);
                $main_order->update_meta_data('DelyvaXMarkup', $pricing['deliveryMarkup']);
                $main_order->update_meta_data('DelyvaXDiscount', $pricing['deliveryDiscount']);
            }
            $main_order->save();
        }
    }
}

//rewire logic here, API is only for post
function delyvax_post_process_order($order, $shipmentId) {
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
    // Register statuses regardless of HPOS status
    register_post_status('wc-preparing', array(
        'label' => _x('Preparing', 'Order status', 'default'),
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('Preparing (%s)', 'Preparing (%s)')
    ));
    register_post_status( 'wc-ready-to-collect', array(
        'label' => _x('Package is Ready', 'Order status', 'default' ),
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop( 'Package is Ready (%s)', 'Package is Ready (%s)' )
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
add_action( 'init', 'delyvax_register_order_statuses',20,0);


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
add_filter( 'wc_order_statuses', 'delyvax_add_to_order_statuses',20,1);

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
add_filter('manage_edit-shop_order_columns', 'sv_wc_cogs_add_order_profit_column_header', 20);
add_filter('woocommerce_shop_order_list_table_columns', 'sv_wc_cogs_add_order_profit_column_header', 20);

function sv_wc_cogs_add_order_profit_column_order_track($column, $post_or_order_object = null) {
    if ('order_track' === $column) {
        // Get order object based on context
        $order = null;
        
        // Check if we're dealing with an order object (HPOS)
        if (is_object($post_or_order_object) && method_exists($post_or_order_object, 'get_id')) {
            $order = $post_or_order_object;
            $order_id = $order->get_id();
        } 
        // Check if we have a post ID (traditional)
        else if (is_numeric($post_or_order_object)) {
            $order_id = $post_or_order_object;
            $order = wc_get_order($order_id);
        }
        // Fallback
        else {
            global $theorder;
            
            if ($theorder) {
                $order = $theorder;
            } else if (!empty($_GET['id'])) {
                $order_id = absint($_GET['id']);
                $order = wc_get_order($order_id);
            }
        }

        if (!$order) {
            return;
        }
        
        $settings = get_option('woocommerce_delyvax_settings');
        if (!$settings || !isset($settings['company_code'])) {
            return;
        }
        
        $company_code = $settings['company_code'];
        $delyvax_tracking_code = $order->get_meta('DelyvaXTrackingCode');
        $delyvax_order_id = $order->get_meta( 'DelyvaXOrderID');
        $DelyvaXPersonnel = $order->get_meta('DelyvaXPersonnel');
        $delyvax_error = delyvax_get_order_meta($order, ['delyvax_error', 'DelyvaXError']);

        if ($delyvax_error && !$delyvax_tracking_code) {
            $display_error = strlen($delyvax_error) > 30 ? substr($delyvax_error, 0, 30) . '...' : $delyvax_error;
            echo '<span style="color: red;" title="' . esc_attr($delyvax_error) . '">Delyva Error: ' . $display_error . '</span>';
        }
        
        if ($delyvax_tracking_code) {
            $url = 'https://'.$company_code.'.delyva.app/customer/strack?trackingNo='.$delyvax_tracking_code;
            
            echo '<a href="'.esc_url($url).'" target="_blank">'.esc_html($delyvax_tracking_code).'</a> ';
            
            if($delyvax_order_id && !$order->has_status( array( 'completed' ))) {
                $label_url = 'https://api.delyva.app/v1.0/order/' . urlencode($delyvax_order_id) . '/label';

                echo '<a href="' . esc_url($label_url) . '" target="_blank" class="button button-small">' . 
                 __('Print Label', 'delyvax') . '</a>';
            }

            if($DelyvaXPersonnel && !is_array($DelyvaXPersonnel)) {
                $personnelInfo = json_decode($DelyvaXPersonnel);
                if ($personnelInfo && isset($personnelInfo->name) && isset($personnelInfo->phone)) {
                    $personnelName = $personnelInfo->name;
                    $personnelPhone = $personnelInfo->phone;
                    echo '<br/><a href="tel:+'.esc_attr($personnelPhone).'" target="_blank">'.esc_html($personnelName).'</a>';
                }
            }
        }
    }
}

// Support both traditional and HPOS column content
add_action('manage_shop_order_posts_custom_column', 'sv_wc_cogs_add_order_profit_column_order_track', 10, 2);
add_action('manage_woocommerce_page_wc-orders_custom_column', 'sv_wc_cogs_add_order_profit_column_order_track', 10, 2);

// Shipping field on my account edit-addresses and checkout
add_filter( 'woocommerce_shipping_fields' , 'delyvax_make_phone_required', 10, 1 ); 
function delyvax_make_phone_required($address_fields) {
    $settings = get_option( 'woocommerce_delyvax_settings');
    $is_shipping_phone = $settings['shipping_phone'];

    if($is_shipping_phone == 'yes') {
        $address_fields['shipping_phone']['required'] = true;
    }
    return $address_fields;
}

/**
 * Common validation function for shipping phone
 * 
 * @param string $phone The phone number to validate
 * @return array [is_valid, error_message]
 */
function delyvax_validate_shipping_phone($phone) {
    // Check if empty
    if (empty($phone)) {
        return [false, 'Shipping phone number is required. Please enter a phone number for shipping.'];
    }
    
    // Check for alphabetic characters which suggest multiple numbers
    if (preg_match('/[a-zA-Z]/', $phone)) {
        return [false, 'Please enter only one valid phone number without text.'];
    }
    
    // Basic phone format validation
    if (!preg_match('/^[0-9+\-\s()]{6,20}$/', $phone)) {
        return [false, 'Please enter a valid phone number format.'];
    }
    
    // If we made it here, the phone is valid
    return [true, ''];
}

/**
 * Regular checkout validation
 */
add_action('woocommerce_checkout_process', 'validate_shipping_phone_field');

function validate_shipping_phone_field() {
    $settings = get_option('woocommerce_delyvax_settings');
    $is_shipping_phone = $settings['shipping_phone'];

    if($is_shipping_phone == 'yes') {
        $phone = isset($_POST['shipping_phone']) ? sanitize_text_field($_POST['shipping_phone']) : '';
        
        list($is_valid, $error_message) = delyvax_validate_shipping_phone($phone);
        
        if (!$is_valid) {
            wc_add_notice($error_message, 'error');
        }
    }
}

/**
 * Blocks checkout validation
 */
function register_shipping_phone_validation_filter() {
    $settings = get_option('woocommerce_delyvax_settings');
    $is_shipping_phone = $settings['shipping_phone'];

    if ($is_shipping_phone == 'yes' && class_exists('Automattic\WooCommerce\StoreApi\StoreApi')) {
        add_action('woocommerce_store_api_checkout_order_processed', 'validate_shipping_phone_blocks_checkout', 10, 1);
    }
}

function validate_shipping_phone_blocks_checkout($order) {
    // Only run this on actual checkout form submission
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $order;
    }
    
    // Check if we're in the actual checkout process, not just viewing the page
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['payment_method'])) {
        return $order;
    }
    
    // Get the phone data from the order
    $shipping_phone = $order->get_shipping_phone();
    
    // Use the common validation function
    list($is_valid, $error_message) = delyvax_validate_shipping_phone($shipping_phone);
    
    if (!$is_valid) {
        // Log the error
        error_log('Shipping phone validation error: ' . $error_message);
        
        // Add error notice
        wc_add_notice($error_message, 'error');
        
        // Prevent order completion
        add_action('woocommerce_after_checkout_validation', function($data, $errors) use ($error_message) {
            $errors->add('shipping_phone_error', $error_message);
        }, 10, 2);
    }
    
    return $order;
}

// Conditional function that check if order items are all virtual
function only_virtual_order_items( $order ) {
    $only_virtual_items = true; // Initializing

    // Loop through order items
    foreach( $order->get_items() as $item ) {
        $product = $item->get_product();
        // Check if there are items that are not virtual
        if ( ! ( $product->is_virtual() || $product->is_downloadable() ) ) {
            $only_virtual_items = false;
            break;
        }

        //check product addons

    }
    return $only_virtual_items;
}

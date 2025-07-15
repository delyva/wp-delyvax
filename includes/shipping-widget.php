<?php
defined( 'ABSPATH' ) or die();

add_action('woocommerce_admin_order_data_after_order_details', 'delyvax_meta_save', 10, 1);
add_action('woocommerce_admin_order_data_after_order_details', 'admin_order_delyvax_metabox', 20, 1);

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

// Add a delyva metabox
add_action( 'add_meta_boxes', 'admin_order_delyvax_metabox' );
function admin_order_delyvax_metabox() {
    // Check if WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    // Check if we're in admin area
    if ( ! is_admin() ) {
        return;
    }

    // Check if add_meta_box function is available
    if ( ! function_exists( 'add_meta_box' ) ) {
        return;
    }

    // Get current screen
    $screen = get_current_screen();
    if ( ! $screen ) {
        return;
    }

    // Determine the correct screen for WooCommerce orders
    $order_screen = null;
    
    // Check if WooCommerce HPOS (High-Performance Order Storage) is enabled
    if ( class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
        try {
            $container = wc_get_container();
            if ( $container ) {
                $controller = $container->get( CustomOrdersTableController::class );
                if ( $controller && method_exists( $controller, 'custom_orders_table_usage_is_enabled' ) ) {
                    if ( $controller->custom_orders_table_usage_is_enabled() ) {
                        $order_screen = wc_get_page_screen_id( 'shop-order' );
                    } else {
                        $order_screen = 'shop_order';
                    }
                }
            }
        } catch ( Exception $e ) {
            // Fallback to traditional post type
            $order_screen = 'shop_order';
        }
    } else {
        // Fallback to traditional post type
        $order_screen = 'shop_order';
    }

    // Only add metabox if we have a valid order screen and the screen matches
    if ( $order_screen && ( $screen->id === $order_screen || $screen->post_type === 'shop_order' ) ) {
        try {
            add_meta_box(
                'DelyvaMetaBox',
                'Delyva',
                'delyvax_show_box',
                $order_screen,
                'side',
                'high'
            );
        } catch ( Exception $e ) {
            // Silently fail if add_meta_box throws an error
            return;
        }
    }
}

// Metabox content
function delyvax_show_box( $object ) {
    // Validate input object
    if ( ! $object ) {
        return;
    }

    // Get the WC_Order object with proper validation
    $order = null;
    if ( is_a( $object, 'WP_Post' ) ) {
        $order = wc_get_order( $object->ID );
    } elseif ( is_a( $object, 'WC_Order' ) ) {
        $order = $object;
    } else {
        return;
    }

    // Validate order object
    if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
        return;
    }

    // Check if WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    // Get settings with validation
    $settings = get_option( 'woocommerce_delyvax_settings', array() );
    $company_code = isset( $settings['company_code'] ) ? $settings['company_code'] : '';
    $company_name = isset( $settings['company_name'] ) ? $settings['company_name'] : 'Delyva';
    $create_shipment_on_paid = isset( $settings['create_shipment_on_paid'] ) ? $settings['create_shipment_on_paid'] : '';
    $create_shipment_on_confirm = isset( $settings['create_shipment_on_confirm'] ) ? $settings['create_shipment_on_confirm'] : '';

    // Set default company name if null
    if ( empty( $company_name ) ) {
        $company_name = 'Delyva';
    }

    // Check if order has valid ID
    $order_id = $order->get_id();
    if ( ! $order_id ) {
        return;
    }

    // Check if required functions exist
    if ( ! function_exists( 'delyvax_get_order_shipping_method' ) || ! function_exists( 'only_virtual_order_items' ) ) {
        return;
    }

    // Ignore local_pickup
    $shipping_method = delyvax_get_order_shipping_method( $order_id );
    if ( $shipping_method === 'local_pickup' ) {
        return;
    }

    // Skip virtual product
    if ( only_virtual_order_items( $order ) ) {
        return;
    }

    // Get order meta with validation
    $delyvax_order_id = $order->get_meta( 'DelyvaXOrderID' );
    $TrackingCode = $order->get_meta( 'DelyvaXTrackingCode' );
    $DelyvaXServiceCode = $order->get_meta( 'DelyvaXServiceCode' );
    $delyvax_error = $order->get_meta( 'delyvax_error' );

    // Build URLs with validation
    $trackUrl = '';
    $printLabelUrl = '';
    
    if ( ! empty( $company_code ) && ! empty( $TrackingCode ) ) {
        $trackUrl = 'https://' . esc_attr( $company_code ) . '.delyva.app/customer/strack?trackingNo=' . esc_attr( $TrackingCode );
    }
    
    if ( ! empty( $delyvax_order_id ) ) {
        $printLabelUrl = 'https://api.delyva.app/v1.0/order/' . esc_attr( $delyvax_order_id ) . '/label';
    }
	
	//processing
	if ( $order->has_status(array('processing'))) {
		if($create_shipment_on_paid == 'yes' || $create_shipment_on_paid == ''
			 || $create_shipment_on_confirm == 'yes' )
		{
			//create order and display list of services
			$DelyvaXServices = $order->get_meta( 'DelyvaXServices' );

			$adxservices = array();

			if($delyvax_order_id != null && !$DelyvaXServices)
			{
				$order = delyvax_get_order_services($order);
				$DelyvaXServices = $order->get_meta( 'DelyvaXServices' );
			}

			if($DelyvaXServices)
			{
				$adxservices = json_decode($DelyvaXServices);
			}

			if($delyvax_error) {
				echo "Error: ".$delyvax_error;
			}

			if($delyvax_order_id != null && sizeof($adxservices) > 0) {
				delyvax_get_services_select($adxservices, $DelyvaXServiceCode);

				echo '<p><button id="fulfill-button" onclick="
						this.disabled=true;
						this.textContent=\'Processing...\';
						this.form.submit();
				" class="button button-primary">Fulfill with '.esc_html($company_name).'</button></p>';
			}else {
				echo "<div><p>
					<a href=\"".wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=preparing&order_id=' . $order->get_id() ), 'woocommerce-mark-order-status' )."\" class=\"button button-primary\">Fulfill with ".$company_name."</a>
				</p></div>";				
			}
		}else {
			echo "<div><p>
				<a href=\"".wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=preparing&order_id=' . $order->get_id() ), 'woocommerce-mark-order-status' )."\" class=\"button button-primary\">Fulfill with ".$company_name."</a>
			</p></div>";
		}	
	//preparing
	} else if ( $order->has_status( array( 'preparing' )) ) {
		if($delyvax_order_id != null && $TrackingCode != null){
			echo 'Tracking No.: <b>'.$TrackingCode.'</b>';
			echo "<div><p>
				<a href=\"".$printLabelUrl."\" class=\"button button-primary\" target=\"_blank\">Print label</a>
				</p></div>";
			echo "<div><p>
				<a href=\"".$trackUrl."\" class=\"button button-primary\" target=\"_blank\">Track shipment</a>
				</p></div>";
		}else {
			echo "<div>
        			<p>
            		Set your order to <b>Processing</b> to fulfill with ".$company_name.", it also works with <i>bulk actions</i> too!
        			</p>
    				</div>";
		}
	}else if ( $order->has_status( array( 'completed' ) ) ) {
		if($delyvax_order_id != null && $TrackingCode != null){
			echo 'Tracking No.: <b>'.$TrackingCode.'</b>';
			echo "<div><p>
				<a href=\"".$trackUrl."\" class=\"button button-primary\" target=\"_blank\">Track shipment</a>
				</p></div>";
		}
	//others
	}else {
		if($delyvax_order_id != null && $TrackingCode != null){
			echo 'Tracking No.: <b>'.$TrackingCode.'</b>';
			echo "<div><p>
				<a href=\"".$printLabelUrl."\" class=\"button button-primary\" target=\"_blank\">Print label</a>
				</p></div>";
			echo "<div><p>
				<a href=\"".$trackUrl."\" class=\"button button-primary\" target=\"_blank\">Track shipment</a>
				</p></div>";
		}else {
			echo "<div>
        			<p>
            		Set your order to <b>Processing</b> to fulfill with ".$company_name.", it also works with <i>bulk actions</i> too!
        			</p>
    				</div>";
		}
	}    
}

/**
 * Saves the custom meta input
 */
function delyvax_meta_save($order_id) {
    // Validate order ID
    if ( ! $order_id || ! is_numeric( $order_id ) ) {
        return false;
    }

    // Check if WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        return false;
    }

    // Get order with validation
    $order = wc_get_order( $order_id );
    if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
        return false;
    }

    // Check if service_code is set and sanitize it
    if ( isset( $_POST['service_code'] ) ) {
        $service_code = sanitize_text_field( $_POST['service_code'] );
        
        if ( ! empty( $service_code ) ) {
            // Check if order is in processing status
            if ( $order->has_status( array( 'processing' ) ) ) {
                // Update meta data
                $order->update_meta_data( 'DelyvaXServiceCode', $service_code );
                $order->save();

                // Check if delyvax_update_service function exists
                if ( function_exists( 'delyvax_update_service' ) ) {
                    delyvax_update_service( $order, $service_code );
                }

                // Change status to preparing
                $order->update_status( 'wc-preparing', 'Order status changed to Preparing.', false );
            }
        }
    }
    
    return $order;
}

function delyvax_get_order_services( $order ) {
    if (!class_exists('DelyvaX_Shipping_API')) {
        include_once 'delyvax-api.php';
    }

	$user = $order->get_user();

	$delyvax_order_id = $order->get_meta( 'DelyvaXOrderID' );

	$dxorder = null;
	$services = array();
	
	if($delyvax_order_id)
	{
		$dxorder = DelyvaX_Shipping_API::getOrderQuotesByOrderId($delyvax_order_id);
	}else {
		delyvax_create_order($order, $user, false);

		$delyvax_order_id = $order->get_meta( 'DelyvaXOrderID' );
		$dxorder = DelyvaX_Shipping_API::getOrderQuotesByOrderId($delyvax_order_id);
	}

	if($dxorder)
	{
		if(isset($dxorder['data']))
		{
			if(isset($dxorder['data']['quotes']))
			{
				$quotes = $dxorder['data']['quotes'];

				if($quotes)
				{
					$services = $dxorder['data']['quotes']['services'];
					
					if($services)
					{
						$jservices = json_encode($services);

						$order->update_meta_data( 'DelyvaXServices', $jservices );
						$order->save();
					}				
				}				
			}
		}
	}

	return $order;
}

function delyvax_get_services_select($adxservices, $DelyvaXServiceCode)
{
	if(sizeof($adxservices) > 0)
	{
		// echo '<label for"service">Select Service</a>';

		echo '<select name="service_code" id="service_code">';
		echo '<option value="">(Select Service)</option>';

		foreach( $adxservices as $i => $service )	
		{
			$serviceName = $service->name;

			if($service->price)
			{
				$serviceName = $serviceName.' '.$service->price->currency.number_format($service->price->amount,2);
			}
		
			if($DelyvaXServiceCode 
				&& ( $DelyvaXServiceCode == $service->code || $DelyvaXServiceCode == $service->serviceCompanyCode ) )
			{
				echo '<option value="'.$service->serviceCompanyCode.'" selected>'.$serviceName.'</option>';
			}else {
				echo '<option value="'.$service->serviceCompanyCode.'">'.$serviceName.'</option>';
			}
		}
		echo '</select>';
	}
}

function delyvax_update_service($order, $service_code)
{
	if (!class_exists('DelyvaX_Shipping_API')) {
        include_once 'delyvax-api.php';
    }

	$delyvax_order_id = $order->get_meta( 'DelyvaXOrderID' );

	if ($delyvax_order_id) {
		$postRequestArr = [
			"serviceCode" => $service_code,
		];

		$resultProcess = DelyvaX_Shipping_API::updateOrderData($order, $delyvax_order_id, $postRequestArr);
	}
}
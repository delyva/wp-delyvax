<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

add_action( 'woocommerce_process_shop_order_meta', 'delyvax_meta_save', 10, 1 );

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

// Add a delyva metabox
add_action( 'add_meta_boxes', 'admin_order_delyvax_metabox' );
function admin_order_delyvax_metabox() {
    $screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
        ? wc_get_page_screen_id( 'shop-order' )
        : 'shop_order';

    add_meta_box(
        'DelyvaMetaBox',
        'Delyva',
        'delyvax_show_box',
        $screen,
        'side',
        'high'
    );
}

// Metabox content
function delyvax_show_box( $object ) {
    // Get the WC_Order object
    $order = is_a( $object, 'WP_Post' ) ? wc_get_order( $object->ID ) : $object;
    //$order = wc_get_order ( $post->ID );
	// $TrackingCode = isset( $post->TrackingCode ) ? $post->TrackingCode : '';

	$settings = get_option( 'woocommerce_delyvax_settings' );
	$company_id = $settings['company_id'];
	$company_code = $settings['company_code'];
	$company_name = $settings['company_name'];
	$create_shipment_on_paid = $settings['create_shipment_on_paid'];
	$create_shipment_on_confirm = $settings['create_shipment_on_confirm'];

	if($company_name == null)
	{
		$company_name = 'Delyva';
	}

	//ignore local_pickup
	$shipping_method = delyvax_get_order_shipping_method($order->id);
	if($shipping_method == 'local_pickup') return;
	//skip virtual product
	if ( only_virtual_order_items( $order ) ) return; 
	//

	$DelyvaXOrderID = $order->get_meta( 'DelyvaXOrderID' );
	$TrackingCode = $order->get_meta( 'DelyvaXTrackingCode' );

	$DelyvaXServiceCode = $order->get_meta( 'DelyvaXServiceCode' );

	$DelyvaXError = $order->get_meta( 'DelyvaXError' );	

	$trackUrl = 'https://'.$company_code.'.delyva.app/customer/strack?trackingNo='.$TrackingCode;
	$printLabelUrl = 'https://api.delyva.app/v1.0/order/'.$DelyvaXOrderID.'/label?companyId='.$company_id;
	
	//processing
	if ( $order->has_status(array('processing'))) {
		if($create_shipment_on_paid == 'yes' || $create_shipment_on_paid == ''
			 || $create_shipment_on_confirm == 'yes' )
		{
			//create order and display list of services
			$DelyvaXServices = $order->get_meta( 'DelyvaXServices' );

			$adxservices = array();

			if($DelyvaXOrderID != null && !$DelyvaXServices)
			{
				$order = delyvax_get_order_services($order);
				$DelyvaXServices = $order->get_meta( 'DelyvaXServices' );
			}

			if($DelyvaXServices)
			{
				$adxservices = json_decode($DelyvaXServices);
			}

			if($DelyvaXError) {
				echo "Error: ".$DelyvaXError;
			}

			if($DelyvaXOrderID != null && sizeof($adxservices) > 0) {
				delyvax_get_services_select($adxservices, $DelyvaXServiceCode);

				echo '<p><button class="button button-primary" type="submit">Fulfill with '.$company_name.'</button></p>';
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
		if($DelyvaXOrderID != null && $TrackingCode != null){
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
		if($DelyvaXOrderID != null && $TrackingCode != null){
			echo 'Tracking No.: <b>'.$TrackingCode.'</b>';
			echo "<div><p>
				<a href=\"".$trackUrl."\" class=\"button button-primary\" target=\"_blank\">Track shipment</a>
				</p></div>";
		}
	//others
	}else {
		if($DelyvaXOrderID != null && $TrackingCode != null){
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
    $order = wc_get_order($order_id);

    // Checks for input and sanitizes/saves if needed
    if( isset( $_POST[ 'service_code' ] ) ) {
    	$service_code = $_POST[ 'service_code' ];
    	if($service_code !== '')
    	{
    	    if ( $order->has_status(array('processing'))) {
        		$order->update_meta_data('DelyvaXServiceCode',$service_code);
        // 		$order->update_post_meta( 'DelyvaXServiceCode', $service_code);

        		$order->save();

				delyvax_update_service($order, $service_code);
        		
        		//change status to preparing
        	    $order->update_status('wc-preparing', 'Order status changed to Preparing.', false);        		
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

	$DelyvaXOrderID = $order->get_meta( 'DelyvaXOrderID' );

	$dxorder = null;
	$services = array();
	
	if($DelyvaXOrderID)
	{
		$dxorder = DelyvaX_Shipping_API::getOrderQuotesByOrderId($DelyvaXOrderID);
	}else {
		delyvax_create_order($order, $user, false);

		$DelyvaXOrderID = $order->get_meta( 'DelyvaXOrderID' );
		$dxorder = DelyvaX_Shipping_API::getOrderQuotesByOrderId($DelyvaXOrderID);
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

	$DelyvaXOrderID = $order->get_meta( 'DelyvaXOrderID' );
	
	$postRequestArr = [
		"serviceCode" => $service_code,
	];

	$resultProcess = DelyvaX_Shipping_API::updateOrderData($order, $DelyvaXOrderID, $postRequestArr);

}
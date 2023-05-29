<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
add_action ( 'add_meta_boxes', 'delyvax_add_box' );
add_action( 'save_post', 'delyvax_meta_save' );

function delyvax_add_box() {

	add_meta_box (
	    'DelyvaTrackingMetaBox',
	    'Delyva',
	    'delyvax_show_box',
	    'shop_order',
	    'side',
	    'high'
    );
}

function delyvax_show_box( $post ) {
    $order = wc_get_order ( $post->ID );
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
function delyvax_meta_save($post_id) {
    $order = wc_get_order( $post_id );

    // Checks save status
    $is_autosave = wp_is_post_autosave( $post_id );
    $is_revision = wp_is_post_revision( $post_id );
    $is_valid_nonce = ( isset( $_POST[ 'prfx_nonce' ] ) && wp_verify_nonce( $_POST[ 'prfx_nonce' ], basename( __FILE__ ) ) ) ? 'true' : 'false';
 
    // Exits script depending on save status
    if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
        return;
    }

    // Checks for input and sanitizes/saves if needed
    if( isset( $_POST[ 'service_code' ] ) ) {
		echo $service_code = $_POST[ 'service_code' ];
		
		$order->update_meta_data('DelyvaXServiceCode',sanitize_text_field($service_code));
		$order->save();
		
		//change status to preparing
		$order->update_status('preparing', 'Order status changed to Preparing.', false);
    }
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


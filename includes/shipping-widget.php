<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
add_action ( 'add_meta_boxes', 'AddBox' );
// add_action ( 'woocommerce_process_shop_order_meta', 'SaveData');
// add_action( 'woocommerce_order_status_completed', 'GetTrackingCode');

function AddBox() {

	add_meta_box (
	    'DelyvaTrackingMetaBox',
	    'DelyvaX',
	    'ShowBox',
	    'shop_order',
	    'side',
	    'high'
    );
}

function ShowBox( $post ) {
    $order = wc_get_order ( $post->ID );
    // $TrackingCode = isset( $post->TrackingCode ) ? $post->TrackingCode : '';

		$settings = get_option( 'woocommerce_delyvax_settings' );
		$company_id = $settings['company_id'];
		$company_code = $settings['company_code'];
		$company_name = $settings['company_name'];
		$create_shipment_on_confirm = $settings['create_shipment_on_confirm'];

		if($company_name == null)
		{
				$company_name = 'DelyvaX';
		}

		$DelyvaXOrderID = $order->get_meta( 'DelyvaXOrderID' );
		$TrackingCode = $order->get_meta( 'DelyvaXTrackingCode' );

		$trackUrl = 'https://'.$company_code.'.delyva.app/customer/track/&trackingNo='.$TrackingCode;
		$printLabelUrl = 'https://api.delyva.app/v1.0/order/'.$DelyvaXOrderID.'/label?companyId='.$company_id;


    if ($TrackingCode == 'Service Unavailable') {
				echo 'Tracking No.: <b>'.$TrackingCode.'</b>';
        echo "<div><p>Failed to create shipment in ".$company_name.", you can try again by changing order status to <b>Preparing</b></p></div>";
    } else if ( $order->has_status( array( 'processing' ) ) ) {
				if($TrackingCode)
				{
						echo 'Tracking No.: <b>'.$TrackingCode.'</b>';
						echo "<div>
						<p>
								Set your order to <b>Preparing</b> to print label and track your shipment with ".$company_name.".
						</p>
						</div>";
						echo "<div><p>
		            <a href=\"".wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=preparing&order_id=' . $order->get_id() ), 'woocommerce-mark-order-status' )."\" class=\"button button-primary\">Set to Preparing</a>
		            </p></div>";
				}

				if($create_shipment_on_confirm == 'yes')
				{
						echo "<div><p>
							<a href=\"".wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=preparing&order_id=' . $order->get_id() ), 'woocommerce-mark-order-status' )."\" class=\"button button-primary\">Fulfill with ".$company_name."</a>
							</p></div>";
				}
	  } else if ( $order->has_status( array( 'preparing' ) ) ) {
				echo 'Tracking No.: <b>'.$TrackingCode.'</b>';
				if($TrackingCode)
				{
						echo "<div><p>
			          <a href=\"".$printLabelUrl."\" class=\"button button-primary\" target=\"_blank\">Print label</a>
			          </p></div>";
			      echo "<div><p>
			          <a href=\"".$trackUrl."\" class=\"button button-primary\" target=\"_blank\">Track shipment</a>
			          </p></div>";
				}else {
						echo "<div>
						<p>
								Set your order to <b>Processing</b> again to fulfill with ".$company_name.", it also works with <i>bulk actions</i> too!
						</p>
						</div>";
						echo "<div><p>
		            <a href=\"".wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=processing&order_id=' . $order->get_id() ), 'woocommerce-mark-order-status' )."\" class=\"button button-primary\">Set to Processing</a>
		            </p></div>";
				}
    } else if ( $order->has_status( array( 'completed' ) ) ) {
				echo 'Tracking No.: <b>'.$TrackingCode.'</b>';
        echo "<div>
		    	<p>
		    		<label  for=\"TrackingCode\"> Tracking No :</label>
		    		<br />
		    		$TrackingCode
		        </p>
		    </div>";
	      // echo "<div><p>
	      //     <a href=\"".$printLabelUrl."\" class=\"button button-primary\" target=\"_blank\">Print label</a>
	      //     </p></div>";
	      echo "<div><p>
	          <a href=\"".$trackUrl."\" class=\"button button-primary\" target=\"_blank\">Track shipment</a>
	          </p></div>";
    } else {
				echo 'Tracking No.: <b>'.$TrackingCode.'</b>';
        echo "<div>
        <p>
            Set your order to <b>Processing</b> to fulfill with ".$company_name.", it also works with <i>bulk actions</i> too!
        </p>
    		</div>";
    }
}

/*
function SaveData( $post_id ) {
	$order = wc_get_order ( $post_id );
	if ( $order ) {
		if(isset($_POST['TrackingCode']))
		{
				$TrackingCode = $_POST['TrackingCode'];

				global $wpdb;
				$TableName = $wpdb->prefix . "posts";
				$wpdb->update( $TableName,
		        array(
		            'TrackingCode' => $_POST['TrackingCode'],
		            ),
		        array( 'id' => $post_id ) );
		}
	}
}*/

/*
function GetTrackingCode( $order_id ) {
    $order = wc_get_order ( $order_id );
		if ( $order ) {
				global $wpdb;
				$TableName = $wpdb->prefix . "delyvax";
		        $result = $wpdb->get_results("SELECT * FROM $TableName WHERE id = 1");
		        if (isset($result[0]))
		            $Shop = $result[0];
		        else{
		            echo "Failed to initialize. Please install plugin again.";
		            return;
		        }
		        $TableName = $wpdb->prefix . "woocommerce_order_";
						$Service = $wpdb->get_results("SELECT t2.meta_value AS Service FROM
								(SELECT * FROM `".$TableName."items` WHERE order_item_type = 'shipping' AND order_id = $order_id) t1 INNER JOIN
								(SELECT * FROM `".$TableName."itemmeta` WHERE meta_key = 'service_code' ) t2 ON
								t1.order_item_id = t2.order_item_id")[0]->Service;
		        $Customer = new WC_Customer( $order->get_customer_id() );
						$TableName = $wpdb->prefix . "posts";
						$wpdb->update( $TableName,
		        	array(
		            	'TrackingCode' => GetFromAPI($Shop, $Service,$Customer, $order),
		            	),
		        	array( 'id' => $order_id )
						);
			}
}
*/
/*
function GetFromAPI($Shop,$service,$Customer, $order){
    $settings = get_option( 'woocommerce_delyvax_settings' );

    global $woocommerce;

    $item_names = [];
    $item_total_price = 0;

    foreach ($order->get_items() as $cart_item ) {
        array_push($item_names, $cart_item->get_name() . ' x ' . $cart_item->get_quantity());
        $item_total_price += floatval($cart_item->get_total());
    }

    $name = implode(', ', $item_names);
    $Grams = 0;
    switch(get_option('woocommerce_weight_unit')){
        case 'kg':
            $Grams = $woocommerce->cart->cart_contents_weight;
            break;
        case 'g':
            $Grams = $woocommerce->cart->cart_contents_weight / 1000;
            break;
        case 'lbs':
            $Grams = $woocommerce->cart->cart_contents_weight / 0.45359237;
            break;
        case 'oz':
            $Grams = $woocommerce->cart->cart_contents_weight / 0.028349523125;
            break;
        default:
            $Grams = '0.5';
            break;
    }
    if ($Grams < '0.5')   $Grams = '0.5';
    $price = $item_total_price;

    $adddays = '+0';
    if (isset($settings['PickupDay'])){
        $adddays = '+' . $settings['PickupDay'];
    }

    $datetime = date('Y-m-d', strtotime($adddays.' day')).'T'.$settings['PickupTime']. ':00.000+08:00';

    //Get Order
    $Currency = get_woocommerce_currency();
    $rate = 1;

    if ($Currency != 'MYR') {
        try{
            $rate = json_decode(file_get_contents('http://free.currencyconverterapi.com/api/v5/convert?q='.$Currency.'_MYR&compact=y'),true);
            $rate = $rate[$Currency.'_MYR']['val'];
        }catch(Exception $e){
            //Assuming its MYR
        }
    }

    $price = $price * $rate;
    $recieverNum = '';

    if ($Customer->get_billing_phone() != null && $Customer->get_billing_phone() != ''){
        $recieverNum = $Customer->get_billing_phone();
    }else
    {
        $recieverNum = $settings['PhoneNo'];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, DELYVAX_SHIPMENT);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);

    $SendVal = "{
      \"sender_name\": \"".$settings['FullName']."\",
      \"sender_phone\": \"".$settings['PhoneNo']."\", "
      . ($Shop->EmailID == ''?'':"\"sender_email\": \"".$settings['EmailID']."\",") .
      "\"sender_address1\": \"".$settings['DispatchAddress']."\",
      \"sender_postcode\": \"".$settings['PostalCode']."\",
      \"sender_city\": \"".$settings['City']."\",
      \"sender_state\": \"".$settings['State']."\",
      \"sender_country\": \"".$settings['Country']."\",
      \"receiver_name\": \"".$Customer->get_first_name()."\",
      \"receiver_phone\": \"".$recieverNum."\"," . ($Customer->get_email() == ''?'':"\"receiver_email\": \"".$Customer->get_email()."\",") .
      "\"receiver_address1\": \"".$Customer->get_shipping_address_1()."\"," . ($Customer->get_shipping_address_2() == ''?'':"\"receiver_address2\": \"".$Customer->get_shipping_address_2()."\",") .
      "\"receiver_postcode\": \"".$Customer->get_shipping_postcode()."\",
      \"receiver_city\": \"".$Customer->get_shipping_city()."\",
      \"receiver_country\": \"".$Customer->get_shipping_country()."\",
      \"pickup_date_time\": \"".$datetime."\",
      \"item_name\": \"".$name."\",
      \"item_value\": ".$price.",
      \"item_weight\": ".$Grams.",
      \"service\": \"".$service."\",
      \"receiver_state\": \"".$Customer->get_shipping_state()."\",
      \"item_type\": \"PARCEL\"
    }";

    curl_setopt($ch, CURLOPT_POSTFIELDS, $SendVal);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Content-Type: application/json"
    ));
    curl_setopt($ch, CURLOPT_USERPWD, $settings['UserID'] . ":" . $settings['ApiKey']);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = $response;
    $result = json_decode($result,true);

    $trackingNum = '';
    if (isset($result['tracking_no']))
        $trackingNum = $result['tracking_no'];
    else
        $trackingNum = 'Service Unavailable';
    return $trackingNum;
}
*/

//

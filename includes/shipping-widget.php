<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
add_action ( 'add_meta_boxes', 'delyvax_add_box' );
// add_action ( 'woocommerce_process_shop_order_meta', 'SaveData');
// add_action( 'woocommerce_order_status_completed', 'GetTrackingCode');

function delyvax_add_box() {

	add_meta_box (
	    'DelyvaTrackingMetaBox',
	    'DelyvaX',
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
		$create_shipment_on_confirm = $settings['create_shipment_on_confirm'];

		if($company_name == null)
		{
				$company_name = 'DelyvaX';
		}

		$DelyvaXOrderID = $order->get_meta( 'DelyvaXOrderID' );
		$TrackingCode = $order->get_meta( 'DelyvaXTrackingCode' );

		$trackUrl = 'https://'.$company_code.'.delyva.app/customer/strack?trackingNo='.$TrackingCode;
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
				}else {
						echo "<div><p>
							<a href=\"".wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=preparing&order_id=' . $order->get_id() ), 'woocommerce-mark-order-status' )."\" class=\"button button-primary\">Fulfill with ".$company_name."</a>
							</p></div>";

				}
		} else if ( $order->has_status( array( 'preparing' )) || $order->has_status( array( 'ready-to-collect' )) ) {
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

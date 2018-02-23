<?php
/*
	Nochex APC Handler
	CRE Loaded, Commerical Open Source eCommerce
	Released under the GNU General Public License
*/

header("HTTP/1.0 200 OK");
include('includes/application_top.php');
include(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_PROCESS);
echo("\n");
if(strtoupper($_SERVER["REQUEST_METHOD"])!="POST"){
	die("<html><body>Access denied</body></html>");
}

// load nochex_apc payment module
$payment = 'nochex';
require(DIR_WS_CLASSES . 'payment.php');
$payment_modules = new payment($payment);

require(DIR_WS_CLASSES . 'order.php');
$order = new order($_POST['order_id']);

$apc_url = "https://www.nochex.com/apcnet/apc.aspx";
$query_string = array();
if(!isset($_POST)){
	$_POST = $HTTP_POST_VARS;
}
foreach($_POST AS $key => $value){
	$query_string[] = "{$key}=".urlencode($value);
}
$query_string = implode("&", $query_string);
$response = make_request($apc_url, $query_string);
if(stristr($response, "AUTHORISED")){
	$apc_result = 'AUTHORISED';
	$sql_data_array = array(
		'nc_transaction_id' => $_POST["transaction_id"],
		'nc_to_email' => $_POST["to_email"],
		'nc_from_email' => $_POST["from_email"],
		'nc_transaction_date' => $_POST["transaction_date"],
		'nc_order_id' => $_POST["order_id"],
		'nc_amount' => $_POST["amount"],
		'nc_security_key' => $_POST["security_key"],
		'nc_status' => $_POST["status"],
		'nochex_response' => $apc_result
	);
	tep_db_perform('nochex_apc_transactions', $sql_data_array);
	if(strtolower($_POST["status"])=="test") {
	   $test_mode = "true";
	}else{
	   $test_mode = "false";
	}
		if($test_mode==strtolower(MODULE_PAYMENT_NOCHEX_TEST_MODE)){
				for($i=0, $n=sizeof($order->products); $i<$n; $i++){
					if(STOCK_LIMITED == 'true'){
						if(DOWNLOAD_ENABLED == 'true'){
							$stock_query_raw = "SELECT products_quantity, pad.products_attributes_filename 
							FROM " . TABLE_PRODUCTS . " p
							LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa
							ON p.products_id=pa.products_id
							LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
							ON pa.products_attributes_id=pad.products_attributes_id
							WHERE p.products_id = '" . tep_get_prid($order->products[$i]['id']) . "'";
							// Will work with only one option for downloadable products
							// otherwise, we have to build the query dynamically with a loop
							$products_attributes = $order->products[$i]['attributes'];
							if(is_array($products_attributes)){
								$stock_query_raw .= " AND pa.options_id = '" . $products_attributes[0]['option_id'] . "' AND pa.options_values_id = '" . $products_attributes[0]['value_id'] . "'";
							}
							$stock_query = tep_db_query($stock_query_raw);
						}else{
							$stock_query = tep_db_query("select products_quantity from " . TABLE_PRODUCTS . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
						}
						if(tep_db_num_rows($stock_query) > 0){
							$stock_values = tep_db_fetch_array($stock_query);
							// do not decrement quantities if products_attributes_filename exists
							if((DOWNLOAD_ENABLED != 'true')||(!$stock_values['products_attributes_filename'])){
								$stock_left = $stock_values['products_quantity'] - $order->products[$i]['qty'];
							}else{
								$stock_left = $stock_values['products_quantity'];
							}
							tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = '" . $stock_left . "' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
							if($stock_left<1&&STOCK_ALLOW_CHECKOUT=='false'){
								tep_db_query("update " . TABLE_PRODUCTS . " set products_status = '0' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
							}
						}
					}
				}
			
			if(MODULE_PAYMENT_NOCHEX_ORDER_STATUS_COMPLETE_ID > 0){
				$order_status = MODULE_PAYMENT_NOCHEX_ORDER_STATUS_COMPLETE_ID;
			}else{
				$order_status = DEFAULT_ORDERS_STATUS_ID;
			}
			// Split Order ID field to get just the ID number
			$data = $_POST["order_id"];
			list($unique_order_id, $temporderdate) = split('-',$data);
			$sql_data_array = array('orders_status' => $order_status);
			tep_db_perform(TABLE_ORDERS,$sql_data_array,'update','orders_id='.$unique_order_id);
			$customer_notification = (SEND_EMAILS == 'true') ? '1' : '0';
			
			$comment_mode = "This was a " . $_POST["status"] . " transaction, and APC was " . $apc_result;
			
			$sql_data_array = array('orders_id' => $unique_order_id, 
			'orders_status_id' => $order_status, 
			'date_added' => 'now()',
			'customer_notified' => $customer_notification,
			'comments' => $comment_mode);
			tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
			// lets start with the email confirmation
			for($i=0, $n=sizeof($order->products); $i<$n; $i++){
				if(sizeof($order->products[$i]['attributes']) > 0){
					$attributes_exist = '1';
					$products_ordered_attributes = "\n";
					for($j = 0, $k = sizeof($order->products[$i]['attributes']); $j < $k; $j++){
						$products_ordered_attributes .= '  '. $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'];
						if($order->products[$i]['attributes'][$j]['price'] != '0') $products_ordered_attributes .= ' (' . $order->products[$i]['attributes'][$j]['prefix'] . $currencies->format($order->products[$i]['attributes'][$j]['price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . ')' . "\n";
					}
				}
				$products_ordered .= $order->products[$i]['qty'] . ' x ' . $order->products[$i]['name'] . ' (' . $order->products[$i]['model'] . ') = ' . $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']) . $products_ordered_attributes . "\n";
				$products_ordered_attributes = '';
			}
			$email_order = STORE_NAME . "\n" . 
			EMAIL_SEPARATOR . "\n" . 
			EMAIL_TEXT_ORDER_NUMBER . ' ' . $unique_order_id . "\n" .
			EMAIL_TEXT_INVOICE_URL . ' ' . tep_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $unique_order_id, 'SSL', false) . "\n" .
			EMAIL_TEXT_DATE_ORDERED . ' ' . strftime(DATE_FORMAT_LONG) . "\n\n";
			if($order->info['comments']){
				$email_order .= tep_db_output($order->info['comments']) . "\n\n";
			}
			$email_order .= EMAIL_TEXT_PRODUCTS . "\n" . 
			EMAIL_SEPARATOR . "\n" . 
			$products_ordered . 
			EMAIL_SEPARATOR . "\n";
			for($i=0, $n=sizeof($order->totals); $i<$n; $i++){
				$email_order .= strip_tags($order->totals[$i]['title']) . ' ' . strip_tags($order->totals[$i]['text']) . "\n";
			}
			if($order->content_type != 'virtual'){
				$email_order .= "\n" . EMAIL_TEXT_DELIVERY_ADDRESS . "\n" . 
				EMAIL_SEPARATOR . "\n";
				if($order->delivery['company']){ $email_order .= $order->delivery['company'] . "\n"; }
				$email_order .= $order->delivery['name'] . "\n" .
				$order->delivery['street_address'] . "\n";
				if($order->delivery['suburb']){ $email_order .= $order->delivery['suburb'] . "\n"; }
				$email_order .= $order->delivery['city'] . ', ' . $order->delivery['postcode'] . "\n";
				if($order->delivery['state']){ $email_order .= $order->delivery['state'] . ', '; }
				$email_order .= $order->delivery['country'] . "\n";
			}
			$email_order .= "\n" . EMAIL_TEXT_BILLING_ADDRESS . "\n" .
			EMAIL_SEPARATOR . "\n";
			if($order->billing['company']){ $email_order .= $order->billing['company'] . "\n"; }
			$email_order .= $order->billing['name'] . "\n" .
			$order->billing['street_address'] . "\n";
			if($order->billing['suburb']){ $email_order .= $order->billing['suburb'] . "\n"; }
			$email_order .= $order->billing['city'] . ', ' . $order->billing['postcode'] . "\n";
			if($order->billing['state']){ $email_order .= $order->billing['state'] . ', '; }
			$email_order .= $order->billing['country'] . "\n\n";
			if(is_object($$payment)){
				$email_order .= EMAIL_TEXT_PAYMENT_METHOD . "\n" . 
				EMAIL_SEPARATOR . "\n";
				$payment_class = $$payment;
				$email_order .= $payment_class->title . "\n\n";
				if($payment_class->email_footer){ 
					$email_order .= $payment_class->email_footer . "\n\n";
				}
			}
			tep_mail($order->customer['name'],$order->customer['email_address'], EMAIL_TEXT_SUBJECT, nl2br($email_order), STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, '');
			if(SEND_EXTRA_ORDER_EMAILS_TO != ''){ // send emails to other people
				tep_mail('', SEND_EXTRA_ORDER_EMAILS_TO, EMAIL_TEXT_SUBJECT,  nl2br($email_order), STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, '');
			}
			tep_db_query("delete from " .TABLE_CUSTOMERS_BASKET. " where customers_id=".$order->customer['id']);
			tep_db_query("delete from " .TABLE_CUSTOMERS_BASKET_ATTRIBUTES. " where customers_id=".$order->customer['id']);
			
			$cart->reset(true); // Clear the cart's content
			unset($_SESSION['sendto']);
		        unset($_SESSION['billto']);
		        unset($_SESSION['shipping']);
		        unset($_SESSION['payment']);
		        unset($_SESSION['comments']);
		        unset($_SESSION['products_ordered']);
		}
}

function make_request($url, $vars){
	$ch = curl_init(); // Initialise the curl tranfer
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_VERBOSE, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $vars); // Set POST fields
	curl_setopt($ch, CURLOPT_HTTPHEADER, "Host: www.nochex.com");
	curl_setopt($ch, CURLOPT_POSTFIELDSIZE, 0); 
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60); // set connection time out variable - 60 seconds	
	$output = curl_exec($ch); 
	curl_close($ch);
	return $output;
}

?>
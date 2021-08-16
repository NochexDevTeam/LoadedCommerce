<?php
/*
	Nochex Setup File
  	CRE Loaded, Commerical Open Source eCommerce
	Released under the GNU General Public License
*/

  class nochex {
    var $code, $title, $description, $enabled;

// class constructor
    function nochex() {
      global $order;

      $this->code = 'nochex';
      $this->title = MODULE_PAYMENT_NOCHEX_TEXT_TITLE;
      $this->description = MODULE_PAYMENT_NOCHEX_TEXT_DESCRIPTION;
      $this->pci = true;

      $this->billing_details_hidden = ((MODULE_PAYMENT_NOCHEX_BILLING_DETAILS == 'True') ? true : false);
      
      if (defined('MODULE_PAYMENT_NOCHEX_SORT_ORDER')) {
        $this->sort_order = (int)MODULE_PAYMENT_NOCHEX_SORT_ORDER;
      } else {
        $this->sort_order = '';
      }

      if (defined('MODULE_PAYMENT_NOCHEX_TEST_MODE')) {
        $this->enabled = ((MODULE_PAYMENT_NOCHEX_TEST_MODE== 'True') ? true : false);
      } else {
        $this->enabled = false;
      }

      if (defined('MODULE_PAYMENT_NOCHEX_STATUS')) {
        $this->enabled = ((MODULE_PAYMENT_NOCHEX_STATUS == 'True') ? true : false);
      } else {
        $this->enabled = false;
      }

      if (defined('MODULE_PAYMENT_NOCHEX_ORDER_STATUS_ID')) {
        if ((int)MODULE_PAYMENT_NOCHEX_ORDER_STATUS_ID > 0) {
          $this->order_status = MODULE_PAYMENT_NOCHEX_ORDER_STATUS_ID;
        }
      } else {
        $this->order_status = 0;
      }

      if (is_object($order)) $this->update_status();

      $this->form_action_url = 'https://secure.nochex.com';
    }

// class methods
    function update_status() {
      global $order;

      if ( ($this->enabled == true) && ((int)MODULE_PAYMENT_NOCHEX_ZONE > 0) ) {
        $check_flag = false;
        $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_NOCHEX_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
        while ($check = tep_db_fetch_array($check_query)) {
          if ($check['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check['zone_id'] == $order->billing['zone_id']) {
            $check_flag = true;
            break;
          }
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }
    }

    function javascript_validation() {
      return false;
    }

    function selection() {
    global $cart_nochex_ID;

    if (isset($_SESSION['cart_nochex_ID'])) {
      $cart_nochex_ID = $_SESSION['cart_nochex_ID'];
      $order_id = substr($cart_nochex_ID, strpos($cart_nochex_ID, '-')+1);
      $check_query = tep_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');
      if (tep_db_num_rows($check_query) < 1) {
        tep_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
        tep_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
        tep_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
        tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
        tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
        tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');

        unset($_SESSION['cart_nochex_ID']);
      }
    }        
    
				
		$hideBillingEnabled = "<img src=\"https://www.nochex.com/logobase-secure-images/logobase-banners/clear.png\" alt='card logos' height='80px;' /><br/>";	
						
		if (MODULE_PAYMENT_NOCHEX_BILLING_DETAILS == "True"){
		$hideBillingEnabled .= "<span style=\"font-weight:bold;color:red;\">Please check your billing address details match the details on your card that you are going to use.</span>";
		}
		 
      $fields[] = array('title' => '',
                        'field' => '<div>' . $hideBillingEnabled . '</div>');		
						
      return array('id' => $this->code,
                   'module' => $this->title,
                   'fields' => $fields);
    }

    function pre_confirmation_check() {
    global $cartID, $cart;
    if (isset($cart->cartID) && $cart->cartID != '') {
      $cartID = $cart->cartID;     
    } else {
      $cartID = $cart->generate_cart_id();
    }
    if ((!isset($_SESSION['cartID'])) || (isset($_SESSION['cartID']) && $_SESSION['cartID'] == '')) {
      $_SESSION['cartID'] = $cartID;
    }
    return true;
    }

    function confirmation() {
   global $cartID, $cart_nochex_ID, $customer_id, $languages_id, $currencies, $order, $order_total_modules;

    $insert_order = false;

    if (isset($_SESSION['cart_nochex_ID'])) {
      $cart_nochex_ID = $_SESSION['cart_nochex_ID'];
      $order_id = substr($cart_nochex_ID, strpos($cart_nochex_ID, '-')+1);

      $curr_check = tep_db_query("select currency from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");
      $curr = tep_db_fetch_array($curr_check);

      if ( ($curr['currency'] != $order->info['currency']) || ($cartID != substr($cart_nochex_ID, 0, strlen($cartID))) ) {
        $check_query = tep_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');

        if (tep_db_num_rows($check_query) < 1) {
          tep_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
          tep_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
          tep_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
          tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
          tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
          tep_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');
        }
        $insert_order = true;
      }
    } else {
      $insert_order = true;
    }        
    if ($insert_order == true) {
      if (defined('MODULE_ADDONS_ONEPAGECHECKOUT_STATUS') && MODULE_ADDONS_ONEPAGECHECKOUT_STATUS == 'True') {
        if (is_array($order_total_modules->modules)) {
          // OPC has not run the process, so do so now
          $order_total_modules->process();
        }
      }
      $order_totals = array();
      if (is_array($order_total_modules->modules)) {
        reset($order_total_modules->modules);
        while (list(, $value) = each($order_total_modules->modules)) {
          $class = substr($value, 0, strrpos($value, '.'));
          if ($GLOBALS[$class]->enabled) {
            for ($i=0, $n=sizeof($GLOBALS[$class]->output); $i<$n; $i++) {
              if (tep_not_null($GLOBALS[$class]->output[$i]['title']) && tep_not_null($GLOBALS[$class]->output[$i]['text'])) {
              // temp fix for buysafe issue
              if ($class == 'ot_buysafe') $GLOBALS[$class]->output[$i]['title'] = 'buySAFE Bond Guarantee:';
                $order_totals[] = array('code' => $GLOBALS[$class]->code,
                                        'title' => $GLOBALS[$class]->output[$i]['title'],
                                        'text' => $GLOBALS[$class]->output[$i]['text'],
                                        'value' => $GLOBALS[$class]->output[$i]['value'],
                                        'sort_order' => $GLOBALS[$class]->sort_order);
              }
            }
          }
        }
      }
      $sql_data_array = array('customers_id' => $customer_id,
                              'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
                              'customers_company' => $order->customer['company'],
                              'customers_street_address' => $order->customer['street_address'],
                              'customers_suburb' => $order->customer['suburb'],
                              'customers_city' => $order->customer['city'],
                              'customers_postcode' => $order->customer['postcode'],
                              'customers_state' => $order->customer['state'],
                              'customers_country' => $order->customer['country']['title'],
                              'customers_telephone' => $order->customer['telephone'],
                              'customers_email_address' => $order->customer['email_address'],
                              'customers_address_format_id' => $order->customer['format_id'],
                              'delivery_name' => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'],
                              'delivery_company' => $order->delivery['company'],
                              'delivery_street_address' => $order->delivery['street_address'],
                              'delivery_suburb' => $order->delivery['suburb'],
                              'delivery_city' => $order->delivery['city'],
                              'delivery_postcode' => $order->delivery['postcode'],
                              'delivery_state' => $order->delivery['state'],
                              'delivery_country' => $order->delivery['country']['title'],
                              'delivery_address_format_id' => $order->delivery['format_id'],
                              'billing_name' => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
                              'billing_company' => $order->billing['company'],
                              'billing_street_address' => $order->billing['street_address'],
                              'billing_suburb' => $order->billing['suburb'],
                              'billing_city' => $order->billing['city'],
                              'billing_postcode' => $order->billing['postcode'],
                              'billing_state' => $order->billing['state'],
                              'billing_country' => $order->billing['country']['title'],
                              'billing_address_format_id' => $order->billing['format_id'],
                              'payment_method' => $order->info['payment_method'],
                              'payment_info' => $cartID,
                              'cc_type' => $order->info['cc_type'],
                              'cc_owner' => $order->info['cc_owner'],
                              'cc_number' => $order->info['cc_number'],
                              'cc_expires' => $order->info['cc_expires'],
                              'date_purchased' => 'now()',
                              'orders_status' => MODULE_PAYMENT_NOCHEX_ORDER_STATUS_ID,
                              'currency' => $order->info['currency'],
                              'currency_value' => $order->info['currency_value']);
      if (isset($order->delivery['telephone']) && isset($order->delivery['email_address'])) {
        $sql_data_array['delivery_telephone'] = $order->delivery['telephone'];
        $sql_data_array['delivery_email_address'] = $order->delivery['email_address'];
      }
      if (isset($order->billing['telephone']) && isset($order->billing['email_address'])) {
        $sql_data_array['billing_telephone'] = $order->billing['telephone'];
        $sql_data_array['billing_email_address'] = $order->billing['email_address'];
      }
                              
                              
      tep_db_perform(TABLE_ORDERS, $sql_data_array);
      $insert_id = tep_db_insert_id();
      for ($i=0, $n=sizeof($order_totals); $i<$n; $i++) {
        $sql_data_array = array('orders_id' => $insert_id,
                                'title' => $order_totals[$i]['title'],
                                'text' => $order_totals[$i]['text'],
                                'value' => $order_totals[$i]['value'],
                                'class' => $order_totals[$i]['code'],
                                'sort_order' => $order_totals[$i]['sort_order']);
        tep_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
      }
      $_SESSION['products_ordered'] = '';
      for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
        $sql_data_array = array('orders_id' => $insert_id,
                                'products_id' => tep_get_prid($order->products[$i]['id']),
                                'products_model' => $order->products[$i]['model'],
                                'products_name' => $order->products[$i]['name'],
                                'products_price' => $order->products[$i]['price'],
                                'final_price' => $order->products[$i]['final_price'],
                                'products_tax' => $order->products[$i]['tax'],
                                'products_quantity' => $order->products[$i]['qty']);
        tep_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);
        $order_products_id = tep_db_insert_id();
        
        // product attributes
        $products_ordered_attributes = '';
        if (isset($order->products[$i]['attributes'])) {               
          for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
            if ($this->version == 'CRE') {  
              $sql_data_array = array('orders_id' => $insert_id,
                                      'orders_products_id' => $order_products_id,
                                      'products_options' => $order->products[$i]['attributes'][$j]['option'],
                                      'products_options_values' => $order->products[$i]['attributes'][$j]['value'],
                                      'options_values_price' => $order->products[$i]['attributes'][$j]['price'],
                                      'price_prefix' => $order->products[$i]['attributes'][$j]['prefix'],
                                      'products_options_id' => $order->products[$i]['attributes'][$j]['option_id'],
                                      'products_options_values_id' => $order->products[$i]['attributes'][$j]['value_id']);
            } else {
              $sql_data_array = array('orders_id' => $insert_id,
                                      'orders_products_id' => $order_products_id,
                                      'products_options' => $order->products[$i]['attributes'][$j]['option'],
                                      'products_options_values' => $order->products[$i]['attributes'][$j]['value'],
                                      'options_values_price' => $order->products[$i]['attributes'][$j]['price'],
                                      'price_prefix' => $order->products[$i]['attributes'][$j]['prefix']);                  
            }
            tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);
            if (DOWNLOAD_ENABLED == 'true') {     
              $attributes_query = "SELECT pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename
                                     from " . TABLE_PRODUCTS_ATTRIBUTES . " pa,
                                          " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                   WHERE pa.products_id = '" . $order->products[$i]['id'] . "'
                                     and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'
                                     and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'
                                     and pa.products_attributes_id = pad.products_attributes_id";
                                     
              $attributes = tep_db_query($attributes_query);
              $attributes_values = tep_db_fetch_array($attributes);
              if ( isset($attributes_values['products_attributes_filename']) && tep_not_null($attributes_values['products_attributes_filename']) ) {
                $sql_data_array = array('orders_id' => $insert_id,
                                        'orders_products_id' => $order_products_id,
                                        'orders_products_filename' => $attributes_values['products_attributes_filename'],
                                        'download_maxdays' => $attributes_values['products_attributes_maxdays'],
                                        'download_count' => $attributes_values['products_attributes_maxcount']);
                tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
              }
            }
            $products_ordered_attributes .= "\n\t" . $order->products[$i]['attributes'][$j]['option'] . ' ' . $order->products[$i]['attributes'][$j]['value'] . ' ' . $order->products[$i]['attributes'][$j]['prefix'] . ' ' . $currencies->display_price($order->products[$i]['attributes'][$j]['price'], tep_get_tax_rate($products[$i]['tax_class_id']), 1);
          }
        } 
        $_SESSION['products_ordered'] .= $order->products[$i]['qty'] . ' x ' . tep_db_decoder($order->products[$i]['name']) . ' (' . $order->products[$i]['model'] . ') = ' . $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']) . $products_ordered_attributes . "\n";
      } // end for      
      $cart_nochex_ID = $insert_id;
      $_SESSION['cart_nochex_ID'] = $cart_nochex_ID;   
    }
    $confirmation = array('title' => $this->title . ': ' . $this->cc_card_type);  
    
    $sql_data_array = array('orders_id' => $insert_id, 
			'orders_status_id' =>  MODULE_PAYMENT_NOCHEX_ORDER_STATUS_ID, 
			'date_added' => 'now()',
			'customer_notified' => '0',
			'comments' => 'Nochex payment option selected');
    tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
    
   
    return $confirmation;
    }

    function process_button() {
      global $order, $currencies, $cart_nochex_ID;

	$billing_address = array();
	if(strlen($order->billing['street_address'])>0) $billing_address[] = $order->billing['street_address'];
	
	$delivery_address = array();
	if(strlen($order->delivery['street_address'])>0) $delivery_address[] = $order->delivery['street_address'];

 	$description = '';

	// Loop to find product details
        for ($i=0; $i<sizeof($order->products); $i++) {
        	$description .=  $order->products[$i]['qty'] . ' x ' . $order->products[$i]['name'];
        	if($i != sizeof($order->products)-1)
        	{
        	   $description .= ", ";
        	}
        	}

      $process_button_string = tep_draw_hidden_field('merchant_id', MODULE_PAYMENT_NOCHEX_ID) .
                               tep_draw_hidden_field('amount', number_format($order->info['total'] * $currencies->currencies['GBP']['value'], $currencies->currencies['GBP']['decimal_places'])) .
                               tep_draw_hidden_field('order_id', $cart_nochex_ID . '-' . date('Ymdhis')) .
                               tep_draw_hidden_field('description', $description) .
                               tep_draw_hidden_field('cancel_url', tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL')) . 
                               tep_draw_hidden_field('callback_url', tep_href_link('nochex_apc_handler.php', '', 'SSL')) .
                               tep_draw_hidden_field('billing_fullname', $order->billing['firstname'] . " " . $order->billing['lastname']) .
			       tep_draw_hidden_field('billing_address', implode("\n", $billing_address)) .
				   tep_draw_hidden_field('billing_city',  $order->billing['city']) .
			       tep_draw_hidden_field('billing_postcode', $order->billing['postcode']) .
			       tep_draw_hidden_field('delivery_fullname', $order->delivery['firstname'] . " " . $order->delivery['lastname']) .
			       tep_draw_hidden_field('delivery_address', implode("\n", $delivery_address)) .
				   tep_draw_hidden_field('delivery_city', $order->delivery['city'] ) .
			       tep_draw_hidden_field('delivery_postcode', $order->delivery['postcode']) .
			       tep_draw_hidden_field('optional_2', "cb") .
			       tep_draw_hidden_field('email_address', $order->customer['email_address']) .
			       tep_draw_hidden_field('customer_phone_number', $order->customer['telephone']);
  
      if (MODULE_PAYMENT_NOCHEX_TEST_MODE == 'True'){
         $process_button_string .= tep_draw_hidden_field('test_success_url', tep_href_link(FILENAME_CHECKOUT_SUCCESS, 'order_id='. $cart_nochex_ID, 'SSL')) . 
         tep_draw_hidden_field('test_transaction', '100');
      }
      else{
         $process_button_string .= tep_draw_hidden_field('success_url', tep_href_link(FILENAME_CHECKOUT_SUCCESS, 'order_id='. $cart_nochex_ID, 'SSL'));
      }
      
      if (MODULE_PAYMENT_NOCHEX_BILLING_DETAILS == 'True'){
        $process_button_string .= tep_draw_hidden_field('hide_billing_details', 'true');
      }
      
                

      return $process_button_string;
    }

    function before_process() {
      return false;
    }

    function after_process() {
      return false;
    }

    function output_error() {
      return false;
    }

    function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_NOCHEX_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }

    function install() {
    $check_query = tep_db_query("select orders_status_id from " . TABLE_ORDERS_STATUS . " where orders_status_name = 'Nochex Processing' limit 1");
    $check_query_s = tep_db_query("select orders_status_id from " . TABLE_ORDERS_STATUS . " where orders_status_name = 'Nochex Authorised' limit 1");
    if (tep_db_num_rows($check_query) < 1 && tep_db_num_rows($check_query_s) < 1) {
      $status_query = tep_db_query("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
      $status = tep_db_fetch_array($status_query);
      $status_id = $status['status_id']+1;
      $status_id_t = $status['status_id']+2;
      $languages = tep_get_languages();
      for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
        tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ('" . $status_id . "', '" . $languages[$i]['id'] . "', 'Nochex Processing')");
        tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ('" . $status_id_t . "', '" . $languages[$i]['id'] . "', 'Nochex Authorised')");
      }
      $flags_query = tep_db_query("describe " . TABLE_ORDERS_STATUS . " public_flag");
      if (tep_db_num_rows($flags_query) == 1) {
        tep_db_query("update " . TABLE_ORDERS_STATUS . " set public_flag = 0 and downloads_flag = 0 where orders_status_id = '" . $status_id . "'");
        tep_db_query("update " . TABLE_ORDERS_STATUS . " set public_flag = 0 and downloads_flag = 0 where orders_status_id = '" . $status_id_t . "'");
      }
    } else {
      $check = tep_db_fetch_array($check_query);
      $status_id = $check['orders_status_id'];
    }
    
    // Create Nochex APC Transactions Table
    tep_db_query("CREATE TABLE IF NOT EXISTS `nochex_apc_transactions` (
    `transaction_id` int(11) unsigned NOT NULL auto_increment,
    `nc_transaction_id` varchar(30) default '0',
    `nc_to_email` varchar(255) default '0',
    `nc_from_email` varchar(255) default '0',
    `nc_transaction_date` varchar(100) default '0',
    `nc_order_id` int(11) unsigned default '0',
    `nc_amount` decimal(15,2) default '0.00',
    `nc_security_key` varchar(255) default '0',
    `nc_status` varchar(15) default '0',
    `nochex_response` varchar(255) default '0',
    `record_updated` varchar(100) default '0',
    PRIMARY KEY  (`transaction_id`)
    );");
    
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Nochex Module', 'MODULE_PAYMENT_NOCHEX_STATUS', 'True', 'Do you want to accept Nochex payments?', '6', '3', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Hide Billing Details', 'MODULE_PAYMENT_NOCHEX_BILLING_DETAILS', 'False', 'Do you want to hide the customer\'s billing details when they are sent to Nochex?', '6', '3', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Nochex E-Mail Address / Merchant ID', 'MODULE_PAYMENT_NOCHEX_ID', 'you@yourbuisness.com', 'The e-mail address / merchant ID to use for the Nochex service', '6', '4', now())");
      tep_db_query("insert ignore into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Test Mode', 'MODULE_PAYMENT_NOCHEX_TEST_MODE', 'False', 'Turn on test mode to enable test transactions.', '6', '2', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Nochex Sort Order of Display', 'MODULE_PAYMENT_NOCHEX_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Nochex Payment Zone', 'MODULE_PAYMENT_NOCHEX_ZONE', '0', 'If a zone is selected, enable this payment method for that zone only.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Pending Order Status', 'MODULE_PAYMENT_NOCHEX_ORDER_STATUS_ID', '0', 'For Pending orders, set the status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
    tep_db_query("insert ignore into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Completed Order Status', 'MODULE_PAYMENT_NOCHEX_ORDER_STATUS_COMPLETE_ID', '0', 'For Completed orders, set the status of orders made with this payment module to this value', '6', '5', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
    }

    function remove() {
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
      tep_db_query("delete from " . TABLE_ORDERS_STATUS . " where orders_status_name = 'Nochex Processing'");
      tep_db_query("delete from " . TABLE_ORDERS_STATUS . " where orders_status_name = 'Nochex Authorised'");
    }

    function keys() {
      return array(
      'MODULE_PAYMENT_NOCHEX_STATUS', 
      'MODULE_PAYMENT_NOCHEX_ID',
      'MODULE_PAYMENT_NOCHEX_TEST_MODE',
      'MODULE_PAYMENT_NOCHEX_BILLING_DETAILS',
      'MODULE_PAYMENT_NOCHEX_ZONE', 
      'MODULE_PAYMENT_NOCHEX_ORDER_STATUS_ID',
      'MODULE_PAYMENT_NOCHEX_ORDER_STATUS_COMPLETE_ID',
      'MODULE_PAYMENT_NOCHEX_SORT_ORDER');
    }
  }
?>

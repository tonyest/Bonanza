<?php
/*
	Created by TwinCitiesTech.com
	(website: twincitiestech.com       email : support@twincitiestech.com)
*/

function pp_invoice_backend_wrap($title = false, $content = false) {
	?>
	<div class='wrap'>
	<h2><?php echo $title; ?></h2>
	<?php echo $content; ?>
	</div>
	<?php
}

function pp_invoice_lookup() {  ?>
<div class="pp_invoice_lookup">
	<form action="<?php echo get_permalink(get_option('pp_invoice_web_invoice_page')); ?>" method="POST">
	<label for="pp_invoice_lookup_input"><?php echo stripslashes(get_option('pp_invoice_lookup_text')); ?></label>
	<?php echo pp_invoice_draw_inputfield('pp_invoice_lookup_input', '',' AUTOCOMPLETE="off" '); ?>
	<input type="submit" value="<?php echo stripslashes(get_option('pp_invoice_lookup_submit')); ?>" class="pp_invoice_lookup_submit" />
	</form>
 </div>
<?php
}

/*
	Draw invoice row for overview tables
*/
function pp_invoice_invoice_row($invoice, $page) {

	$invoice_id = $invoice->id;

	if($page == 'outgoing') {
		$overview_link = admin_url("admin.php?page=outgoing_invoices");;
		$columns = get_column_headers("toplevel_page_outgoing_invoices");
		$hidden = get_hidden_columns("toplevel_page_outgoing_invoices");
		$user_class = $invoice->payer_class;
		$invoice_send_pay_link = admin_url("admin.php?page=send_invoice&invoice_id={$invoice->id}");
	}

	if($page == 'incoming') {
		$overview_link = admin_url("admin.php?page=incoming_invoices");;
		$columns = get_column_headers("web-invoice_page_incoming_invoices");
		$hidden = get_hidden_columns("web-invoice_page_incoming_invoices");			
		$user_class = $invoice->payee_class;
		$invoice_send_pay_link = admin_url("admin.php?page=make_payment&invoice_id={$invoice->id}");
	}

	// Color coding
	if($invoice->is_paid) $class_settings .= " alternate ";
	if($invoice->is_archived) $class_settings .= " pp_invoice_archived ";

	// Days Since Sent
	if($invoice->is_paid) { 
		$days_since = "<span style='display:none;'>-1</span>".__(' Paid', 'prospress'); }
	else { 
		if($invoice->sent_date) {

		$date1 = $invoice->sent_date;
		$date2 = date("Y-m-d", time());
		$difference = abs(strtotime($date2) - strtotime($date1));
		$days = round(((($difference/60)/60)/24), 0);
		if($days == 0) { $days_since = "<span style='display:none;'>$days</span>".__('Sent Today. ', 'prospress'); }
		elseif($days == 1) { $days_since = "<span style='display:none;'>$days</span>".__('Sent Yesterday. ', 'prospress'); }
		elseif($days > 1) { $days_since = "<span style='display:none;'>$days</span>".sprintf(__('Sent %s days ago. ', 'prospress'),$days); }
		}
		else {
		$days_since ="<span style='display:none;'>999</span>".__('Not Sent', 'prospress');	}
	}

	// Setup row actions

	if($page == 'outgoing') {
		$row_actions = 	"<div class='row-actions'>";

		if(!$invoice->is_paid)			
			$row_actions .= "<span class='edit'><a href='$invoice_send_pay_link'>Send Invoice</a> | </span>";

		if($invoice->is_archived)
			$row_actions .= "<span class='unarchive'><a href='$overview_link&pp_invoice_action=unrachive_invoice&multiple_invoices[0]=$invoice_id' class=''>Un-Archive</a> </span>";

		if(!$invoice->is_archived)
			$row_actions .= "<span class='archive'><a href='$overview_link&pp_invoice_action=archive_invoice&multiple_invoices[0]=$invoice_id' class=''>Archive</a>  </span>";

		// $row_actions .= "<span class='delete'><a onclick='return pp_invoice_confirm_delete();' href='$overview_link&pp_invoice_action=delete_invoice&multiple_invoices[0]=$invoice_id' class='submitdelete'>Delete</a></span>";		
		$row_actions .= "</div>";
	}

	if($page == 'incoming') {
		$row_actions = 	"<div class='row-actions'>";

		if(!$invoice->is_paid)
			$row_actions .= "<span class='edit'><a href='$invoice_send_pay_link'>Make Payment</a> | </span>";

		if($invoice->is_archived)
			$row_actions .= "<span class='unarchive'><a href='$overview_link&pp_invoice_action=unrachive_invoice&multiple_invoices[0]=$invoice_id' class=''>Un-Archive</a>  </span>";

		if(!$invoice->is_archived)
			$row_actions .= "<span class='archive'><a href='$overview_link&pp_invoice_action=archive_invoice&multiple_invoices[0]=$invoice_id' class=''>Archive</a>  </span>";

		$row_actions .= "</div>";

	}

	// Setup display

	$r = "<tr id='invoice-$invoice_id' class='{$invoice_id}_row $class_settings'>";

	foreach ( $columns as $column_name => $column_display_name ) {
	$class = "class=\"$column_name column-$column_name\"";

		$style = '';
	if ( in_array($column_name, $hidden) )
		$style = ' style="display:none;"';

	$attributes = "$class$style";

	switch ( $column_name ) {
		case 'cb':
			$r .= "<th scope='row' class='check-column'><input type='checkbox' name='multiple_invoices[]' value='$invoice_id'></th>";
			break;
		case 'subject':
			$r .= "<td $attributes><a class='row-title' href='$invoice_send_pay_link' title='Edit $subject'>{$invoice->post_title}</a>";
			$r .= $row_actions;
			$r .= "</td>";

			break;
		case 'balance':
			$r .= "<td $attributes>{$invoice->display_amount}</td>";
		break;			

		case 'status':
			$r .= "<td $attributes>". ($days_since ? " $days_since " : "-")."</td>";
		break;		

		case 'date_sent':
			$date_sent_string = strtotime(pp_invoice_meta($invoice_id,'sent_date')); 
			if(!empty($date_sent_string))
				$r .= "<td $attributes sortvalue='".date("Y-m-d", $date_sent_string)."'>". date("M d, Y", $date_sent_string). "</td>";
			else 
				$r .= "<td $attributes>&nbsp;</td>";

		break;		

		case 'invoice_id':
			$r .= "<td $attributes>{$invoice->id}</td>";
		break;			

		case 'user_email':
			$r .= "<td $attributes><a href='mailto:{{$user_class->user_email}'>{$user_class->user_email}</a></td>";
		break;			

		case 'display_name':
			$r .= "<td $attributes>{$user_class->display_name}</td>";
		break;	

		case 'company_name':
			$r .= "<td $attributes>{$user_class->company_name}&nbsp;</td>";
		break;			

		case 'due_date':

			$due_date_string = strtotime($invoice->due_date_day . "-" . $invoice->due_date_month . "-" . $invoice->due_date_year);
			if(!empty($due_date_string))
				$r .= "<td $attributes sortvalue='".date(get_option('date_format'), $due_date_string)."'>" . date(get_option('date_format'), $due_date_string). "</td>";
			else 
				$r .= "<td $attributes>&nbsp;</td>";
		break;			

		default:
			$r .= "<td $attributes>";
				$r .= "$column_name";
			$r .= "</td>";

	}
	}
	$r .= '</tr>';

	return $r;		

}

function pp_invoice_user_selection_screen() {
 	?>
	<style>
	#screen-meta {display:none;}
	</style>
	<div class="wrap">
		<div class="postbox" id="wp_new_invoice_div">
		<div class="inside">
		<?php pp_invoice_draw_user_selection_form(); ?>
		</div>
		</div>
	</div>

	<?php

}

function pp_invoice_show_welcome_message() {
	global $wpdb; ?>

<h2>Prospress Payments Setup Steps</h2>

	<ol style="list-style-type:decimal;padding-left: 20px;" id="pp_invoice_first_time_setup">
<?php 
	$pp_invoice_web_invoice_page = get_option("pp_invoice_web_invoice_page");
	$pp_invoice_paypal_address = get_option("pp_invoice_paypal_address");
	$pp_invoice_moneybookers_address = get_option("pp_invoice_moneybookers_address");
	$pp_invoice_googlecheckout_address = get_option("pp_invoice_googlecheckout_address");
	$pp_invoice_gateway_username = get_option("pp_invoice_gateway_username");
	$pp_invoice_payment_method = get_option("pp_invoice_payment_method");

?>
	<form action="admin.php?page=new_invoice" method='POST'>
	<input type="hidden" name="pp_invoice_action" value="first_setup">
<?php if(empty($pp_invoice_web_invoice_page) ) { ?>
	<li><a class="pp_invoice_tooltip"  title="Your clients will have to follow their secure link to this page to see their invoice. Opening this page without following a link will result in the standard page content begin shown.">Select a page to display your web invoices</a>:  
		<select name='pp_invoice_web_invoice_page'>
		<option></option>
		<?php $list_pages = $wpdb->get_results("SELECT ID, post_title, post_name, guid FROM ". $wpdb->prefix ."posts WHERE post_status = 'publish' AND post_type = 'page' ORDER BY post_title");
		foreach ($list_pages as $page)
		{ 
		echo "<option  style='padding-right: 10px;'";
		if(isset($pp_invoice_web_invoice_page) && $pp_invoice_web_invoice_page == $page->ID) echo " SELECTED ";
		echo " value=\"".$page->ID."\">". $page->post_title . "</option>\n"; 
		} ?>
		</select>
	</li>
<?php } ?>

<?php if(empty($pp_invoice_payment_method)) { ?>
	<li>Select how you want to accept money: 
		<select id="pp_invoice_payment_method" name="pp_invoice_payment_method">
		<option></option>
		<option value="paypal" style="padding-right: 10px;"<?php if(get_option('pp_invoice_payment_method') == 'paypal') echo 'selected="yes"';?>>PayPal</option>
		<option value="cc" style="padding-right: 10px;"<?php if(get_option('pp_invoice_payment_method') == 'cc') echo 'selected="yes"';?>>Credit Card</option>
		</select> 

		<li class="paypal_info payment_info">Your PayPal username: <input id='pp_invoice_paypal_address' name="pp_invoice_paypal_address" class="search-input input_field"  type="text" value="<?php echo stripslashes(get_option('pp_invoice_paypal_address')); ?>"></li>

		<li class="gateway_info payment_info">
		<a class="pp_invoice_tooltip"  title="Your credit card processor will provide you with a gateway username.">Gateway Username</a>
		<input AUTOCOMPLETE="off" name="pp_invoice_gateway_username" class="input_field search-input" type="text" value="<?php echo stripslashes(get_option('pp_invoice_gateway_username')); ?>">
		</li>

		<li class="gateway_info payment_info">
		<a class="pp_invoice_tooltip"  title="You will be able to generate this in our credit card processor's control panel.">Gateway Transaction Key</a>
		<input AUTOCOMPLETE="off" name="pp_invoice_gateway_tran_key" class="input_field search-input" type="text" value="<?php echo stripslashes(get_option('pp_invoice_gateway_tran_key')); ?>">
		</li>

		<li class="gateway_info payment_info">
		Gateway URL
		<input name="pp_invoice_gateway_url" class="input_field search-input" type="text" value="<?php echo stripslashes(get_option('pp_invoice_gateway_url')); ?>">
		<span class="pp_invoice_click_me" onclick="jQuery('#pp_invoice_gateway_url').val('https://gateway.merchantplus.com/cgi-bin/PAWebClient.cgi');">MerchantPlus</span> |
		<span class="pp_invoice_click_me" onclick="jQuery('#pp_invoice_gateway_url').val('https://secure.authorize.net/gateway/transact.dll');">Authorize.Net</span> |
		<span class="pp_invoice_click_me" onclick="jQuery('#pp_invoice_gateway_url').val('https://test.authorize.net/gateway/transact.dll');">Authorize.Net Developer</span> 
		</li>

<?php } ?>

	<li>Send an invoice:
		<select name='user_id' class='user_selection'>
		<option ></option>
		<?php
		$get_all_users = $wpdb->get_results("SELECT * FROM ". $wpdb->prefix . "users LEFT JOIN ". $wpdb->prefix . "usermeta on ". $wpdb->prefix . "users.id=". $wpdb->prefix . "usermeta.user_id and ". $wpdb->prefix . "usermeta.meta_key='last_name' ORDER BY ". $wpdb->prefix . "usermeta.meta_value");
		foreach ($get_all_users as $user)
		{ 
		$profileuser = @get_user_to_edit($user->ID);
		echo "<option ";
		if(isset($user_id) && $user_id == $user->ID) echo " SELECTED ";
		if(!empty($profileuser->last_name) && !empty($profileuser->first_name)) { echo " value=\"".$user->ID."\">". $profileuser->last_name. ", " . $profileuser->first_name . " (".$profileuser->user_email.")</option>\n";  }
		else 
		{
		echo " value=\"".$user->ID."\">". $profileuser->user_login. " (".$profileuser->user_email.")</option>\n"; 
		}
		}
		?>
		</select>
	</li>
	</ol>

	<input type='submit' class='button' value='Save Settings and Create Invoice'>
	</form>
	<?php  if(pp_invoice_is_not_merchant()) pp_invoice_cc_setup(false); ?>

<?php
}

function pp_invoice_cc_setup($show_title = TRUE) {
if($show_title) { ?> 	<div id="pp_invoice_need_mm" style="border-top: 1px solid #DFDFDF; ">Do you need to accept credit cards?</div> <?php } ?>

<div class="wrap">
<div class="pp_invoice_credit_card_processors pp_invoice_rounded_box">
<p>Prospress Payments users are eligible for special credit card processing rates from <a href="http://twincitiestech.com/links/MerchantPlus.php">MerchantPlus</a> (800-546-1997) and <a href="http://twincitiestech.com/links/MerchantExpress.php">MerchantExpress.com</a> (888-845-9457). <a href="http://twincitiestech.com/links/MerchantWarehouse.php">MerchantWarehouse</a> (866-345-5959) was unable to offer us special rates due to their unique pricing structure. However, they are one of the most respected credit card processing companies and have our recommendation.
</p>
</div>
</div>
<?php
}

function pp_invoice_show_email($invoice_id, $force_original = false) {
	global $pp_invoice_email_variables;
	$pp_invoice_email_variables = pp_invoice_email_variables($invoice_id);

	if(!$force_original && pp_invoice_meta($invoice_id, 'pp_invoice_email_message_content') != "") return str_replace("<br />", "\n",pp_invoice_meta($invoice_id, 'pp_invoice_email_message_content'));
	return str_replace("<br />", "\n",preg_replace_callback('/(%([a-z_]+)%)/',  'pp_invoice_email_apply_variables', get_option('pp_invoice_email_send_invoice_content')));

}

function pp_invoice_show_reminder_email($invoice_id) {
	global $pp_invoice_email_variables;

	$pp_invoice_email_variables = pp_invoice_email_variables($invoice_id);

	return preg_replace_callback('/(%([a-z_]+)%)/', 'pp_invoice_email_apply_variables', get_option('pp_invoice_email_send_reminder_content'));
}

function pp_invoice_show_receipt_email($invoice_id) {
	global $pp_invoice_email_variables;
	
	$pp_invoice_email_variables = pp_invoice_email_variables($invoice_id);

	return preg_replace_callback('/(%([a-z_]+)%)/', 'pp_invoice_email_apply_variables', get_option('pp_invoice_email_send_receipt_content'));
}

function pp_invoice_draw_itemized_table($invoice_id) {
	global $wpdb, $currency;

	$invoice_info = $wpdb->get_row("SELECT * FROM ".$wpdb->payments." WHERE id = '".$invoice_id."'");
	$itemized = $invoice_info->itemized;
	$amount = $invoice_info->amount;

	$pp_invoice_tax = pp_invoice_meta($invoice_id,'pp_invoice_tax');
	if($pp_invoice_tax == '') $pp_invoice_tax = pp_invoice_meta($invoice_id,'tax_value');

	if($pp_invoice_tax) {
		$tax_free_amount = $amount*(100/(100+(100*($pp_invoice_tax/100))));
		$tax_value = $amount - $tax_free_amount;
		}

	if(!strpos($amount,'.')) $amount = $amount . ".00";
	$itemized_array = unserialize(urldecode($itemized)); 

	if(is_array($itemized_array)) {
		$response .= "<table id=\"pp_invoice_itemized_table\">
		<tr>\n";
		if(get_option('pp_invoice_show_quantities') == "Show") { $response .= '<th style="width: 40px; text-align: right;">Quantity</th>'; }
		$response .="<th>Item</th><th style=\"width: 70px; text-align: right;\">Cost</th>
		</tr> ";
		$i = 1;
		foreach($itemized_array as $itemized_item){
		//Show Quantites or not
		if(get_option('pp_invoice_show_quantities') == '') $show_quantity = false;
		if(get_option('pp_invoice_show_quantities') == 'Hide') $show_quantity = false;
		if(get_option('pp_invoice_show_quantities') == 'Show') $show_quantity = true;

		if(!empty($itemized_item[name])) {
		if(!strpos($itemized_item[price],'.')) $itemized_item[price] = $itemized_item[price] . ".00";

		if($i % 2) { $response .= "<tr>"; } 
		else { $response .= "<tr  class='alt_row'>"; } 

		//Quantities
		if($show_quantity) {
		$response .= "<td style=\"width: 70px; text-align: right;\">" . $itemized_item[quantity] . "</td>";	}

		//Item Name
		$response .= "<td>" . stripslashes($itemized_item[name]) . " <br /><span class='description_text'>" . stripslashes($itemized_item[description]) . "</span></td>";

		//Item Price		
		if(!$show_quantity) {
		 $response .= "<td style=\"width: 70px; text-align: right;\">" . pp_money_format( $itemized_item[quantity] * $itemized_item[price] ) . "</td>"; 
		 } else {
		 $response .= "<td style=\"width: 70px; text-align: right;\">". pp_money_format( $itemized_item[price] ) . "</td>"; 
		 }

		$response .="</tr>";
		$i++;
		}

		}
		if($pp_invoice_tax) {
		$response .= "<tr>";
		if(get_option('pp_invoice_show_quantities') == "Show") { $response .= "<td></td>"; }
		$response .= "<td>". get_option('pp_invoice_custom_label_tax') . " (". round($pp_invoice_tax,2). "%) </td><td style='text-align:right;' colspan='2'>" . pp_money_format( $tax_value )."</td></tr>";
		}

		$response .="		
		<tr class=\"pp_invoice_bottom_line\">
		<td align=\"right\">Invoice Total:</td>
		<td  colspan=\"2\" style=\"text-align: right;\" class=\"grand_total\">";

		$response .= pp_money_format( $amount );
		$response .= "</td></table>";

		return $response;
	}

}

function pp_invoice_draw_itemized_table_plaintext($invoice_id) {
	global $wpdb;
	$invoice_info = $wpdb->get_row("SELECT * FROM ".$wpdb->payments." WHERE id = '".$invoice_id."'");
	$itemized = $invoice_info->itemized;
	$amount = $invoice_info->amount;
	if(!strpos($amount,'.')) $amount = $amount . ".00";

	$itemized_array = unserialize(urldecode($itemized)); 

	if(is_array($itemized_array)) {

		foreach($itemized_array as $itemized_item){
			if(!empty($itemized_item[name])) {
			$item_cost = $itemized_item[price] * $itemized_item[quantity];
			if(!strpos($item_cost,'.')) $item_cost = $item_cost . ".00";

		$response .= " $" . $item_cost . " \t - \t " . stripslashes($itemized_item[name]) . "\n";

		}
		}

		return $response;
	}

}

function pp_invoice_user_profile_fields(){
	global $wpdb, $user_id;

	$profileuser = @get_user_to_edit($user_id);
	?>

	<h3><?php _e( 'Billing Address', 'prospress' ); ?></h3>
	<a name="billing_info"></a>
	<table class="form-table" >

	<tr>
	<th><label for="company_name"><?php _e( 'Company Name', 'prospress' ); ?></label></th>
	<td><input type="text" name="company_name" id="company_name" class="regular-text" value="<?php echo get_usermeta($user_id,'company_name'); ?>" /></td>
	</tr>

	<tr>
	<th><label for="streetaddress"><?php _e( 'Street Address', 'prospress' ); ?></label></th>
	<td><input type="text" name="streetaddress" id="streetaddress" class="regular-text" value="<?php echo get_usermeta($user_id,'streetaddress'); ?>" /></td>
	</tr>

	<tr>
	<th><label for="city"><?php _e( 'City', 'prospress' ); ?></label></th>
	<td><input type="text" name="city" id="city" class="regular-text" value="<?php echo get_usermeta($user_id,'city'); ?>" /></td>
	</tr>

	<tr>
	<th><label for="state"><?php _e( 'State', 'prospress' ); ?></label></th>
	<td><input type="text" name="state" id="state" class="regular-text" value="<?php echo get_usermeta($user_id,'state'); ?>" /><br />
	<p class="note"><?php _e( 'Use two-letter state codes for safe credit card processing.', 'prospress' ); ?></p></td>
	</tr>

	<tr>
	<th><label for="streetaddress"><?php _e( 'ZIP Code', 'prospress' ); ?></label></th>
	<td><input type="text" name="zip" id="zip" class="regular-text" value="<?php echo get_usermeta($user_id,'zip'); ?>" /></td>
	</tr>

	<tr>
	<th><label for="phonenumber"><?php _e( 'Phone Number', 'prospress' ); ?></label></th>
	<td><input type="text" name="phonenumber" id="phonenumber" class="regular-text" value="<?php echo get_usermeta($user_id,'phonenumber'); ?>" />
	<p class="note"><?php _e( 'Enforce 555-555-5555 format if you are using PayPal.', 'prospress' ); ?></p></td>
	</tr>

	<tr>
	<th></th>
	<td>

	</td>
	</tr>

</table>
<?php
}

function pp_invoice_show_paypal_reciept($invoice_id) {

	$invoice = new PP_Invoice_GetInfo($invoice_id);

	if(isset($_POST['first_name'])) update_usermeta($invoice->recipient('user_id'), 'first_name', $_POST['first_name']);
	if(isset($_POST['last_name'])) update_usermeta($invoice->recipient('user_id'), 'last_name', $_POST['last_name']);

	if(get_option('pp_invoice_send_thank_you_email') == 'yes') pp_invoice_send_email_receipt($invoice_id);

	pp_invoice_paid($invoice_id);
	pp_invoice_update_log($invoice_id,'paid',"PayPal Reciept: (" . $_REQUEST['receipt_id']. ")");
	if(isset($_REQUEST['payer_email'])) pp_invoice_update_log($invoice_id,'paid',"PayPal payee user email: (" . $_REQUEST['payer_email']. ")");

	return '<div id="invoice_page" class="clearfix">
	<div id="invoice_overview" class="cleafix">
	<h2 class="invoice_page_subheading">'.$invoice->recipient("callsign"). ', thank you for your payment!</h2>
	<p><strong>Invoice ' . $invoice->display("display_id") . ' has been paid.</strong></p>
	</div>
	</div>';
}

function pp_invoice_show_already_paid( $invoice_id ) {
	$invoice = new PP_Invoice_GetInfo( $invoice_id );
	return '<p>Thank you, this invoice was paid on ' . $invoice->display( 'paid_date' ) . '.</p>';
}

function pp_invoice_show_invoice_overview($invoice_id) {
$invoice = new PP_Invoice_GetInfo($invoice_id);
?>
<div id="invoice_overview" class="clearfix">
	<h2 id="pp_invoice_welcome_message" class="invoice_page_subheading">Welcome, <?php echo $invoice->recipient('callsign'); ?>!</h2>
	<p class="pp_invoice_main_description">We have sent you invoice <b><?php echo $invoice->display('display_id'); ?></b> with a total amount of <?php echo $invoice->display('display_amount'); ?>.</p>
	<?php if($invoice->display('due_date')) { ?> <p class="pp_invoice_due_date">Due Date: <?php echo $invoice->display('due_date'); } ?>	
	<?php if($invoice->display('description')) { ?><p><?php echo $invoice->display('description');  ?></p><?php  } ?>
	<?php echo pp_invoice_draw_itemized_table($invoice_id); ?> 
</div>
<?php
}

function pp_invoice_show_business_address() {
?>
<div id="invoice_business_info" class="clearfix">
	<h2 class="invoice_page_subheading">Bill From:</h2>
	<p class="pp_invoice_business_name"><?php echo get_option('pp_invoice_business_name'); ?></p>
	<p class="pp_invoice_business_address"><?php echo nl2br(get_option('pp_invoice_business_address')); ?></p>
</div>

<?php
}

function pp_invoice_show_billing_information($invoice_id) {
	$invoice = new PP_Invoice_GetInfo($invoice_id);

?>

<div id="billing_overview" class="clearfix">
<h2 class="invoice_page_subheading"><?php _e( 'Billing Information', 'prospress' ); ?></h2>

<?php
// count how many payment options we have availble

// Create payment array

$payment_array = pp_invoice_accepted_payment($invoice_id);

//show dropdown if it is allowed, and there is more than one payment option
if($invoice->display('pp_invoice_client_change_payment_method') == 'yes' && count($payment_array) > 1) { ?>

<fieldset id="pp_invoice_select_payment_method">
	<ol>
	<li>
	<label for="first_name">Select Payment Method </label>
	<select id="pp_invoice_select_payment_method_selector" onChange="changePaymentOption()">
	<?php foreach ($payment_array as $payment_option) { ?>
		<option name="<?php echo $payment_option['name']; ?>" <?php if($payment_option['default']) { echo "SELECTED"; } ?>><?php echo $payment_option['nicename']; ?></option>
	<?php } ?>
	</select>
	</li>
	</ol>
</fieldset>
<?php } ?>

<?php // Include payment-specific UI files
 foreach ($payment_array as $payment_option) { ?>
	 <div class="<?php echo $payment_option['name']; ?>_ui payment_info"><?php include "ui/{$payment_option['name']}.php"; ?></div>
 <?php }  ?>

</div>

<?php
}

function pp_invoice_show_recurring_info($invoice_id) {
	$invoice = new PP_Invoice_GetInfo($invoice_id);
?>
<div id="recurring_info" class="clearfix">
	<?php if($invoice->display('due_date')) { ?> <p class="pp_invoice_due_date">Due Date: <?php echo $invoice->display('due_date'); } ?>	
	<h2 id="pp_invoice_welcome_message" class="invoice_page_subheading">Welcome, <?php echo $invoice->recipient('callsign'); ?>!</h2>
	<?php if($invoice->display('description')) { ?><p><?php echo $invoice->display('description');  ?></p><?php  } ?>

	<p class="recurring_info_breakdown">This is a recurring bill, id: <b><?php echo $invoice->display('display_id'); ?></b>.</p>
	<p>You will be billed <?php echo $invoice->display('display_billing_rate'); ?> in the amount of <?php echo $invoice->display('display_amount'); 

	// Determine if startning now or t a set date
	if (pp_invoice_meta($invoice_id,'pp_invoice_subscription_start_day') != '' && pp_invoice_meta($invoice_id,'pp_invoice_subscription_start_month')  != '' && pp_invoice_meta($invoice_id,'pp_invoice_subscription_start_year'  != ''))
	echo pp_invoice_meta($invoice_id,'pp_invoice_subscription_start_day') .", ". pp_invoice_meta($invoice_id,'pp_invoice_subscription_start_month') .", ".  pp_invoice_meta($invoice_id,'pp_invoice_subscription_start_year');
	?>.</p>

	<?php echo pp_invoice_draw_itemized_table($invoice_id); ?> 

</div>
<?php
}

function pp_invoice_draw_user_selection_form($user_id) {
	global $wpdb; ?>

	<form action="admin.php?page=new_invoice" method='POST'>
		<table class="form-table" id="get_user_info">
			<tr class="invoice_main">
				<th><?php if(isset($user_id)) { ?>Start New Invoice For: <?php } else { ?>Create New Invoice For:<?php } ?></th>
				<td> 

					<select name='user_id' class='user_selection'>
					<option></option>
					<?php
					$get_all_users = $wpdb->get_results("SELECT * FROM ". $wpdb->prefix . "users LEFT JOIN ". $wpdb->prefix . "usermeta on ". $wpdb->prefix . "users.id=". $wpdb->prefix . "usermeta.user_id and ". $wpdb->prefix . "usermeta.meta_key='last_name' ORDER BY ". $wpdb->prefix . "usermeta.meta_value");
					foreach ($get_all_users as $user)
					{ 
					$profileuser = @get_user_to_edit($user->ID);
					echo "<option ";
					if(isset($user_id) && $user_id == $user->ID) echo " SELECTED ";
					if(!empty($profileuser->last_name) && !empty($profileuser->first_name)) { echo " value=\"".$user->ID."\">". $profileuser->last_name. ", " . $profileuser->first_name . " (".$profileuser->user_email.")</option>\n";  }
					else 
					{
					echo " value=\"".$user->ID."\">". $profileuser->user_login. " (".$profileuser->user_email.")</option>\n"; 
					}
					}
					?>
					<option value="create_new_user">-- Create New User --</option>
					</select>
					<input type='submit' class='button' id="pp_invoice_create_new_invoice" value='Create New Invoice'> 

					<?php if(pp_invoice_number_of_invoices() > 0) { ?><span id="pp_invoice_copy_invoice" class="pp_invoice_click_me">copy from another</span>
					<br />

			<div class="pp_invoice_copy_invoice">
			<?php 	$all_invoices = $wpdb->get_results("SELECT * FROM ".$wpdb->payments); ?>
			<select name="copy_from_template">
<option SELECTED value=""></option>
		<?php 	foreach ($all_invoices as $invoice) { 
		$profileuser = @get_user_to_edit($invoice->user_id);
		?>

		<option value="<?php echo $invoice->id; ?>"><?php if(pp_invoice_recurring($invoice->id)) {?>(recurring)<?php } ?> <?php echo $invoice->subject . " - $" .$invoice->amount; ?> </option>

		<?php } ?>

		</select><input type='submit' class='button' value='New Invoice from Template'> <span id="pp_invoice_copy_invoice_cancel" class="pp_invoice_click_me">cancel</span>
			</div>
<?php } ?>	

				</td>
			</tr>

		</table>
	</form>

<?php
}

/*
	Function from WPI Premium
	Shorthand function for drawing input fields
*/
function wpi_input($args = '') {
	$defaults = array('name' => '', 'group' => '','special' => '','value' => '', 'type' => '', 'hidden' => false, 'style' => false, 'readonly' => false, 'label' => false);
	extract( wp_parse_args( $args, $defaults ), EXTR_SKIP );

	// if [ character is present, we do not use the name in class and id field
	if(!strpos("$name",'[')) {
		$id = $name;
		$class_from_name = $name;
	}

	if($label) $return .= "<label for='$name'>";
	$return .= "<input ".($type ?  "type=\"$type\" " : '')." ".($style ?  "style=\"$style\" " : '')." id=\"$id\" class=\"".($type ?  "" : "input_field")." $class_from_name $class ".($hidden ?  " hidden " : '').""  .($group ? "group_$group" : ''). " \"  	name=\"" .($group ? $group."[".$name."]" : $name). "\" 	value=\"".stripslashes($value)."\" 	title=\"$title\" $special ".($type == 'forget' ?  " autocomplete='off'" : '')." ".($readonly ?  " readonly=\"readonly\" " : "")." />";		
	if($label) $return .= "$label </label>";

	return $return;
}

/*
	Function from WPI Premium
	Shorthand function for drawing select boxes
*/
function select($args = '') {
	$defaults = array('name' => '', 'group' => '','special' => '','values' => '','current_value' => '');
	extract( wp_parse_args( $args, $defaults ), EXTR_SKIP );

	global $wpi_settings;

	// Get rid of all brackets
	if(strpos("$name",'[') || strpos("$name",']')) {
		$replace_variables = array('][',']','[');
		$class_from_name = $name;
		$class_from_name = "wpi_" . str_replace($replace_variables, '_', $class_from_name);
	} else {
		$class_from_name = "wpi_" . $name;	
	}

	// Overwrite class_from_name if class is set
	if($class)
		$class_from_name = $class;

		$values_array = unserialize($values);

	if($values == 'yon') {
		$values_array = array("yes" => __("Yes", 'prospress'),"no" => __("No", 'prospress'));
	}

	if($values == 'us_states') {
		$values_array = $wpi_settings['states'];
	}

	if($values == 'countries') {
		$values_array = $wpi_settings['countries'];
	}

	if($values == 'years') {
		// Create year array

		$current_year = intval(date('y'));
		$values_array = array();
		$counter = 0;
		while ( $counter < 7) {
			$values_array[$current_year] = "20". $current_year;
			$current_year++;
			$counter++;
		}
		}

	if($values == 'months') {
			$values_array = array("" => "", "01" => "Jan", "02" => "Feb", "03" => "Mar", "04" => "Apr", "05" => "May", "06" => "Jun", "07" => "Jul","08" => "Aug","09" => "Sep","10" => "Oct","11" => "Nov","12" => "Dec");
	}

	$output = "<select id='".($id ? $id : $class_from_name)."' name='"  .($group ? $group."[".$name."]" : $name). "' class='$class_from_name "  .($group ? "group_$group" : ''). "'>";

		foreach($values_array as $key => $value) {
	$output .=  "<option value='$key'";
	if($key == $current_value) $output .= " selected";	
	$output .= ">$value</option>";
	}
	$output .= "</select>";

	return $output;
}

/*
	Function from WPI Premium
	Shorthand function for drawing checkbox fields
*/	
function wpi_checkbox($args = '', $checked = false) {
	$defaults = array('name' => '', 'id' => false,'class' => false, 'group' => '','special' => '','value' => '', 'label' => false, 'maxlength' => false);
	extract( wp_parse_args( $args, $defaults ), EXTR_SKIP );

	// Get rid of all brackets
	if(strpos("$name",'[') || strpos("$name",']')) {
		$replace_variables = array('][',']','[');
		$class_from_name = $name;
		$class_from_name = "pp_invoice_" . str_replace($replace_variables, '_', $class_from_name);
	} else {
		$class_from_name = "pp_invoice_" . $name;	
	}

	// Setup Group
	if($group) {
		if(strpos($group,'|')) {
			$group_array = explode("|", $group);
			$count = 0;
			foreach($group_array as $group_member) {
				$count++;
				if($count == 1) {
					$group_string .= "$group_member";
				} else {
					$group_string .= "[$group_member]";
				}
			}
		} else {
			$group_string = "$group";
		}
	}

	// Use $checked to determine if we should check the box
	$checked = strtolower($checked);
	if($checked == 'yes') 	$checked = 'true'; 
	if($checked == 'true') 	$checked = 'true'; 
	if($checked == 'no') 	$checked = false;
	if($checked == 'false') $checked = false; 

//	WPI_Functions::qc($checked);

	$id					= 	($id ? $id : $class_from_name);

	$insert_id 			= 	($id ? " id='$id' " : " id='$class_from_name' ");
	$insert_name		= 	($group_string ? " name='".$group_string."[$name]' " : " name='$name' ");
	$insert_checked		= 	($checked ? " checked='checked' " : " ");
	$insert_value		= 	" value=\"$value\" ";
	$insert_class 		= 	" class='$class_from_name $class wpi_checkbox' ";
	$insert_maxlength	= 	($maxlength ? " maxlength='$maxlength' " : " ");

	// Determine oppositve value
	switch ($value) {
		case 'yes':
			$opposite_value = 'no';
			break;
		case 'true':
			$opposite_value = 'false';
			break;
	}

	// Print label if one is set
	if($label) $return .= "<label for='$id'>";

	// Print hidden checkbox
	$return .= "<input type='hidden' value='$opposite_value' $insert_name />";

	// Print checkbox
	$return .= "<input type='checkbox' $insert_name $insert_id $insert_class $insert_checked $insert_maxlength  $insert_value $special />";
	if($label) $return .= " $label</label>";	

	return $return;
}

?>
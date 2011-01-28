<?php 
/*
	Created by TwinCitiesTech.com
	(website: twincitiestech.com       email : support@twincitiestech.com)
*/

// Hide errors if using PHP4, otherwise we get many html_entity_decode() errors
if (phpversion() <= 5 ) { ini_set('error_reporting', 0); }

//Delete any invoices associated with a post that is being deleted.	
function pp_invoice_delete_post( $post_id ) {
	global $wpdb;

	if(!$post_id )
		return;

	$invoice_id = $wpdb->get_var("SELECT id FROM ".$wpdb->payments."  WHERE post_id = '$post_id'" );

	if(!$invoice_id )
		return;

	pp_invoice_delete( $invoice_id );	
}

//New function for sending invoices 
function pp_send_single_invoice( $invoice_id, $message = false ) {
	$invoice_class = new pp_invoice_get( $invoice_id );
	$invoice = $invoice_class->data;

	if( wp_mail( $invoice->payer_class->user_email, "Invoice: {$invoice->post_title}", $invoice_class->data->email_payment_request, "From: {$invoice->payee_class->display_name} <{$invoice->payee_class->user_email}>\r\n" ) ) {
		pp_invoice_update_invoice_meta( $invoice_id, "sent_date", date("Y-m-d", time()));
		pp_invoice_update_log( $invoice_id,'contact',"Invoice emailed to {$invoice->payer_class->user_email}" ); 
		return "Invoice sent.";
	} else {
		return "There was a problem sending the invoice, please try again.";
	}

}

//Converts payment venue slug into nice name
function pp_invoice_payment_nicename( $slug) {

	switch( $slug) {
		case 'paypal':
			return "PayPal";
		break;
		case 'cc':
			return "Credit Card";
		break;
		case 'draft':
			return "Bank Transfer";
		break;

	}

}

// Return a user's Prospress Payment setting, if no user_id is passed, current user is used
function pp_invoice_user_settings( $what, $user_id = false ) {
	global $user_ID;

	if(!$user_id )
		$user_id = $user_ID;

	// Load user ALL settings
	$user_settings = get_usermeta( $user_id, 'pp_invoice_settings' );

	// If there are no settin found, load defaults
 	if( !is_array( $user_settings ) || count( $user_settings ) < 1 )	{
		$user_settings = pp_invoice_load_default_user_settings( $user_id );
	}

	// Remove slashes from entire array
	$user_settings = stripslashes_deep( $user_settings);

	// Replace "false" and "true" strings with boolean values
	if(is_array( $user_settings)) {
		foreach( $user_settings as $setting_name => $setting_value ) {

			if( $setting_value == 'true' )
				$user_settings[$setting_name] = true;

			if( $setting_value == 'false' )
				$user_settings[$setting_name] = false;

		}
	}

	if( $what != 'all' ) 
		return $user_settings[$what];

	if( $what == 'all' ) 
		return $user_settings;

	return false;
}

// Load default user options into a users settings. Some settings are generated based on user account settings
function pp_invoice_load_default_user_settings( $user_id ) {

	$user_data = get_userdata( $user_id );

	$settings[ 'business_name' ] = $user_data->display_name;
	$settings[ 'user_email' ] = $user_data->user_email;
	$settings[ 'reminder_message' ] = "This is a reminder to pay your invoice.";
	$settings[ 'default_currency_code' ] = "USD";
	$settings[ 'tax_label' ] = "Tax";

	update_usermeta( $user_id, 'pp_invoice_settings', $settings);

	return $settings;
}

function pp_invoice_create( $args ) {
	global $blog_id, $wpdb;

	$defaults = array(
		'post_id' => false,
		'payer_id' => false, 
		'payee_id' => false,
		'amount' => false,
		'status' => 'pending',
		'type' => false,
		'blog_id' => $blog_id );

	$args = wp_parse_args( $args, $defaults);
	extract( $args, EXTR_SKIP);

	if( !$post_id || !$payer_id || !$payee_id || !$amount )
		return;

	if( $wpdb->query( "INSERT INTO " . $wpdb->payments . " ( post_id,payer_id,payee_id,amount,status,type,blog_id )	VALUES ('$post_id','$payer_id','$payee_id','$amount','$status','$type','$blog_id' )" ) ) {
		$message = __("New Invoice saved for $post_id.", 'prospress' );
		$invoice_id = $wpdb->insert_id;
		pp_invoice_update_log( $invoice_id, 'created', "Invoice created from post ( $post_id )." );;
	} 
	else { 
		$error = true; $message = __("There was a problem saving invoice.  Try deactivating and reactivating plugin.", 'prospress' ); 
	}

	return compact( 'error', 'message' );
}

function pp_invoice_user_has_permissions( $invoice_id, $user_id = false ) {
	global $user_ID, $wpdb;

	// Set to global variable if no user_id is passed
	if( !$user_id )
		$user_id = $user_ID;

 	// Get invoice with passed id where user is either a payee or a payer
	$invoices = $wpdb->get_row( "SELECT * FROM " . $wpdb->payments . " 
								WHERE id = '$invoice_id' 
								AND (payer_id = '$user_id'
								OR payee_id = '$user_id' )" );

	// If an invoice exists, return whether the user is payee or payer
	if( count( $invoices) > 0 ) {
		if( $invoices->payer_id == $user_id && $invoices->payee_id != $user_id )
			return 'payer';

		if( $invoices->payer_id != $user_id && $invoices->payee_id == $user_id )
			return 'payee';

		if( $invoices->payer_id == $user_id && $invoices->payee_id == $user_id )
			return 'self_invoice';	
	}

	return false;

}

function pp_invoice_number_of_invoices() {
	global $wpdb;
	$query = "SELECT COUNT(*) FROM ".$wpdb->payments."";
	$count = $wpdb->get_var( $query);
	return $count;
}

function pp_invoice_does_invoice_exist( $invoice_id ) {
	global $wpdb;
	return $wpdb->get_var("SELECT * FROM ".$wpdb->payments." WHERE id = $invoice_id" );
}

function pp_invoice_validate_cc_number( $cc_number) {
   /* Validate; return value is card type if valid. */
   $false = false;
   $card_type = "";
   $card_regexes = array(
      "/^4\d{12}(\d\d\d ){0,1}$/" => "visa",
      "/^5[12345]\d{14}$/"       => "mastercard",
      "/^3[47]\d{13}$/"          => "amex",
      "/^6011\d{12}$/"           => "discover",
      "/^30[012345]\d{11}$/"     => "diners",
      "/^3[68]\d{12}$/"          => "diners",
   );

   foreach ( $card_regexes as $regex => $type ) {
       if (preg_match( $regex, $cc_number)) {
           $card_type = $type;
           break;
       }
   }

   if (!$card_type ) {
       return $false;
   }

   /*  mod 10 checksum algorithm  */
   $revcode = strrev( $cc_number);
   $checksum = 0;

   for ( $i = 0; $i < strlen( $revcode ); $i++) {
       $current_num = intval( $revcode[$i]);
       if( $i & 1) {  /* Odd  position */
          $current_num *= 2;
       }
       /* Split digits and add. */
           $checksum += $current_num % 10; if
       ( $current_num >  9) {
           $checksum += 1;
       }
   }

   if ( $checksum % 10 == 0) {
       return $card_type;
   } else {
       return $false;
   }
}

function pp_invoice_update_log( $invoice_id,$action_type,$value )  {
	global $wpdb;
	if(isset( $invoice_id )) {
	$time_stamp = date("Y-m-d h-i-s" );
	$wpdb->query("INSERT INTO ".$wpdb->payments_log." 
	(invoice_id , action_type , value, time_stamp)
	VALUES ('$invoice_id', '$action_type', '$value', '$time_stamp' );" );
	}
}

function pp_invoice_query_log( $invoice_id,$action_type ) {
	global $wpdb;
	if( $results = $wpdb->get_results("SELECT * FROM ".$wpdb->payments_log." WHERE invoice_id = '$invoice_id' AND action_type = '$action_type' ORDER BY 'time_stamp' DESC" )) return $results;

}

function pp_invoice_meta( $invoice_id, $meta_key) {
	global $wpdb;
	return $wpdb->get_var("SELECT meta_value FROM `".$wpdb->paymentsmeta."` WHERE meta_key = '$meta_key' AND invoice_id = '$invoice_id'" );
}

function pp_invoice_update_status( $invoice_id, $status ) {
	global $wpdb;

	$wpdb->query( "UPDATE ".$wpdb->payments." SET status = '$status' WHERE  id = '$invoice_id'" );
}

function pp_invoice_update_invoice_meta( $invoice_id,$meta_key,$meta_value ) {

	global $wpdb;
	if(empty( $meta_value )) {
		// Dlete meta_key if no value is set
		$wpdb->query("DELETE FROM ".$wpdb->paymentsmeta." WHERE  invoice_id = '$invoice_id' AND meta_key = '$meta_key'" ); 
	}
	else {
		// Check if meta key already exists, then we replace it $wpdb->paymentsmeta
		if( $wpdb->get_var("SELECT meta_key 	FROM `".$wpdb->paymentsmeta."` WHERE meta_key = '$meta_key' AND invoice_id = '$invoice_id'" )) { $wpdb->query("UPDATE `".$wpdb->paymentsmeta."` SET meta_value = '$meta_value' WHERE meta_key = '$meta_key' AND invoice_id = '$invoice_id'" ); }
		else { $wpdb->query("INSERT INTO `".$wpdb->paymentsmeta."` (invoice_id, meta_key, meta_value ) VALUES ('$invoice_id','$meta_key','$meta_value' )" ); }
	}
}

function pp_invoice_delete_invoice_meta( $invoice_id,$meta_key='' ) {

	global $wpdb;
	if(empty( $meta_key))  { $wpdb->query("DELETE FROM `".$wpdb->paymentsmeta."` WHERE invoice_id = '$invoice_id' " );}
	else { $wpdb->query("DELETE FROM `".$wpdb->paymentsmeta."` WHERE invoice_id = '$invoice_id' AND meta_key = '$meta_key'" );}

}

function pp_invoice_delete( $invoice_id ) {
	global $wpdb;

	// Check to see if array is passed or single.
	if(is_array( $invoice_id )) {
		$counter=0;
		foreach ( $invoice_id as $single_invoice_id ) {
			$counter++;
			$wpdb->query("DELETE FROM ".$wpdb->payments." WHERE id = '$single_invoice_id'" );

			pp_invoice_update_log( $single_invoice_id, "deleted", "Deleted on " );

			// Get all meta keys for this invoice, then delete them

			$all_invoice_meta_values = $wpdb->get_col("SELECT invoice_id FROM ".$wpdb->paymentsmeta." WHERE invoice_id = '$single_invoice_id'" );

			//print_r( $all_invoice_meta_values);
			foreach ( $all_invoice_meta_values as $meta_key) {
				pp_invoice_delete_invoice_meta( $single_invoice_id );
			}
		}
		return $counter . __(' invoice(s) successfully deleted.', 'prospress' );

	}
	else {
		// Delete Single
		$wpdb->query("DELETE FROM ".$wpdb->payments." WHERE id = '$invoice_id'" );

		$all_invoice_meta_values = $wpdb->get_col("SELECT invoice_id FROM ".$wpdb->paymentsmeta." WHERE invoice_id = '$invoice_id'" );

		//print_r( $all_invoice_meta_values);
		foreach ( $all_invoice_meta_values as $meta_key) {
			pp_invoice_delete_invoice_meta( $single_invoice_id );
		}

			// Make log entry
		pp_invoice_update_log( $invoice_id, "deleted", "Deleted on " );

		return __('Invoice successfully deleted.', 'prospress' );
	}
}

function pp_invoice_archive( $invoice_id ) {
	global $wpdb;

	// Check to see if array is passed or single.
	if(is_array( $invoice_id )) {
		$counter=0;
		foreach ( $invoice_id as $single_invoice_id ) {
		$counter++;
		pp_invoice_update_invoice_meta( $single_invoice_id, "archive_status", "archived" );
		}
		return __("$counter  invoice(s) archived.", 'prospress' );

	}
	else {
		pp_invoice_update_invoice_meta( $invoice_id, "archive_status", "archived" );
		return __('Invoice successfully archived.', 'prospress' );
	}
}

function pp_invoice_mark_as_unpaid( $invoice_id ) {
	global $wpdb;

	// Check to see if array is passed or single.
	if(is_array( $invoice_id )) {
		$counter=0;
		foreach ( $invoice_id as $single_invoice_id ) {
			$counter++;
			pp_invoice_update_status( $single_invoice_id, 'pending' );
			pp_invoice_update_log( $single_invoice_id,'paid',"Invoice marked as un-paid" );
		}
		return sprintf( _n( "Invoice marked as unpaid.", "%d invoices marked as unpaid.", $counter ), $counter );
	}
	else {
		pp_invoice_update_status( $invoice_id, 'pending' );
		pp_invoice_update_log( $invoice_id,'paid',"Invoice marked as un-paid" );
		return __( 'Invoice marked as unpaid.', 'prospress' );
	}
}

function pp_invoice_mark_as_paid( $invoice_id ) {
	global $wpdb;

	// Check to see if array is passed or single.
	if(is_array( $invoice_id )) {
		$counter=0;
		foreach ( $invoice_id as $single_invoice_id ) {
			$counter++;

			pp_invoice_update_status( $single_invoice_id, 'paid' );
			pp_invoice_update_log( $single_invoice_id,'paid',"Invoice marked as paid" );

			if(get_option('pp_invoice_send_thank_you_email' ) == 'yes' ) 
				pp_invoice_send_email_receipt( $single_invoice_id );
		}
		if( get_option( 'pp_invoice_send_thank_you_email' ) == 'yes' ) {
			return sprintf( _n( "Invoice marked as paid, and thank you email sent to customer.", "%d invoices marked as paid, and thank you emails sent to customers.", $counter ), $counter );
		}
		else{
			return sprintf( _n( "Invoice marked as paid.", "%d invoices marked as paid.", $counter ), $counter );
		}
	}
	else {
		pp_invoice_update_status( $invoice_id, 'paid' );
		pp_invoice_update_log( $invoice_id,'paid',"Invoice marked as paid" );

		if(get_option('pp_invoice_send_thank_you_email' ) == 'yes' ) 
			pp_invoice_send_email_receipt( $single_invoice_id );

		if(get_option('pp_invoice_send_thank_you_email' ) == 'yes' ) {
			return sprintf( _n( "Invoice marked as paid, and thank you email sent to customer.", "%d invoices marked as paid, and thank you emails sent to customers.", $counter ), $counter );
		}
		else{
			return __('Invoice marked as paid.', 'prospress' );
		}}
}

function pp_invoice_unarchive( $invoice_id ) {
	global $wpdb;

	// Check to see if array is passed or single.
	if( is_array( $invoice_id ) ) {
		$counter=0;
		foreach ( $invoice_id as $single_invoice_id ) {
			$counter++;
			pp_invoice_delete_invoice_meta( $single_invoice_id, "archive_status" );
		}
		return $counter . __(' invoice(s) unarchived.', 'prospress' );
	} else {
		pp_invoice_delete_invoice_meta( $invoice_id, "archive_status" );
		return __('Invoice successfully unarchived', 'prospress' );
	}
}

function pp_invoice_mark_as_sent( $invoice_id ) {
	global $wpdb;

	// Check to see if array is passed or single.
	if( is_array( $invoice_id ) ) {
		$counter=0;
		foreach ( $invoice_id as $single_invoice_id ) {
			$counter++;
			pp_invoice_update_invoice_meta( $single_invoice_id, "sent_date", date("Y-m-d", time()));
			pp_invoice_update_log( $single_invoice_id,'contact','Invoice Maked as eMailed' ); //make sent entry
		}
		return sprintf( _n( "Invoice marked as sent.", "%d invoices marked as sent.", $counter ), $counter );
		//return $counter .  __(' invoice(s) marked as sent.', 'prospress' );
	} else {
		pp_invoice_update_invoice_meta( $invoice_id, "sent_date", date("Y-m-d", time()));
		pp_invoice_update_log( $invoice_id,'contact','Invoice Maked as eMailed' ); //make sent entry

		return __( 'Invoice market as sent.', 'prospress' );
	}
}

function pp_invoice_get_invoice_attrib( $invoice_id,$attribute ) {
	global $wpdb;
	$query = "SELECT $attribute FROM ".$wpdb->payments." WHERE id=".$invoice_id."";
	return $wpdb->get_var( $query);
}

function pp_invoice_get_invoice_status( $invoice_id,$count='1' ) {
	global $wpdb;

	if( $invoice_id != '' ) {
		$query = "SELECT * FROM ".$wpdb->payments_log."
		WHERE invoice_id = $invoice_id
		ORDER BY time_stamp DESC
		LIMIT 0 , $count";

		$status_update = $wpdb->get_results( $query);

		if(count( $status_update ) < 1)
			return false;

		foreach ( $status_update as $single_status) {
			$message .= "<li>" . $single_status->value . " on <span class='pp_invoice_tamp_stamp'>" . $single_status->time_stamp . "</span></li>";
		}

		return $message;
	}
}

function pp_invoice_clear_invoice_status( $invoice_id ) {
	global $wpdb;
	if(isset( $invoice_id )) {
	if( $wpdb->query("DELETE FROM ".$wpdb->payments_log." WHERE invoice_id = $invoice_id" ))
	return  __('Logs for invoice #', 'prospress' ) . $invoice_id .  __(' cleared.', 'prospress' );
	}
}

function pp_invoice_get_single_invoice_status( $invoice_id )  {
	// in class
	global $wpdb;
	if( $status_update = $wpdb->get_row("SELECT * FROM ".$wpdb->payments_log." WHERE invoice_id = $invoice_id ORDER BY `".$wpdb->payments_log."`.`time_stamp` DESC LIMIT 0 , 1" ))
	return $status_update->value . " - " . PP_Invoice_Date::convert( $status_update->time_stamp, 'Y-m-d H', 'M d Y' );
}

function pp_invoice_paid( $invoice_id ) {
	global $wpdb;

	pp_invoice_update_status( $invoice_id, 'paid' );	
 	pp_invoice_update_log( $invoice_id,'paid',"Invoice successfully processed by ". $_SERVER['REMOTE_ADDR']);
}

function pp_invoice_recurring( $invoice_id ) {
	global $wpdb;
	if(pp_invoice_meta( $invoice_id,'recurring_billing' )) return true;
}

function pp_invoice_recurring_started( $invoice_id ) {
	global $wpdb;
	if(pp_invoice_meta( $invoice_id,'subscription_id' )) return true;
}

function pp_invoice_is_paid( $invoice_id ) { //Merged with paid_status in class
	global $wpdb;

	if( 'paid' == $wpdb->get_var( "SELECT status FROM  " . $wpdb->payments . " WHERE id = '$invoice_id'" ) ) 
		return true;
}

function pp_invoice_paid_date( $invoice_id ) {
	// in invoice class
	global $wpdb;
	return $wpdb->get_var("SELECT time_stamp FROM  ".$wpdb->payments_log." WHERE action_type = 'paid' AND invoice_id = '".$invoice_id."' ORDER BY time_stamp DESC LIMIT 0, 1" );
}

function pp_invoice_build_invoice_link( $invoice_id ) {
	// in invoice class
	global $wpdb;

	$link_to_page = get_permalink(get_option('pp_invoice_web_invoice_page' ));

	$hashed_invoice_id = md5( $invoice_id );
	if(get_option("permalink_structure" )) { $link = $link_to_page . "?invoice_id=" .$hashed_invoice_id; } 
	else { $link =  $link_to_page . "&invoice_id=" . $hashed_invoice_id; } 

	return $link;
}

function pp_invoice_draw_inputfield( $name,$value,$special = '' ) {

	return "<input id='$name' type='text' class='$name input_field regular-text' name='$name' value='$value' $special />";
}
function pp_invoice_draw_textarea( $name,$value,$special = '' ) {

	return "<textarea id='$name' class='$name large-text' name='$name' $special >$value</textarea>";
}

function pp_invoice_draw_select( $name,$values,$current_value = '' ) {

	$output = "<select id='$name' name='$name' class='$name'>";
	foreach( $values as $key => $value ) {
	$output .=  "<option style='padding-right: 10px;' value='$key'";
	if( $key == $current_value ) $output .= " selected";	
	$output .= ">".stripslashes( $value )."</option>";
	}
	$output .= "</select>";

	return $output;
}

function pp_invoice_send_email_receipt( $invoice_id ) {
	global $wpdb, $pp_invoice_email_variables;

	$invoice_info = new PP_Invoice_GetInfo( $invoice_id );
	$pp_invoice_email_variables = pp_invoice_email_variables( $invoice_id );

	$message = pp_invoice_show_receipt_email( $invoice_id );

	$name = get_option("pp_invoice_business_name" );
	$from = get_option("pp_invoice_email_address" );

	$headers = "From: {$name} <{$from}>\r\n";
	if (get_option('pp_invoice_cc_thank_you_email' ) == 'yes' ) {
		$headers .= "CC: {$from}\r\n";
	}

	$message = pp_invoice_show_receipt_email( $invoice_id );
	$subject = preg_replace_callback('/(%([a-z_]+)%)/', 'pp_invoice_email_apply_variables', get_option('pp_invoice_email_send_receipt_subject' ));

	if(wp_mail( $invoice_info->recipient('email_address' ), $subject, $message, $headers)) {
 pp_invoice_update_log( $invoice_id,'contact','Receipt eMailed' ); }

	return $message;
}

function pp_invoice_format_phone( $phone ) {

	$phone = preg_replace("/[^0-9]/", "", $phone );

	if(strlen( $phone ) == 7)
		return preg_replace("/([0-9]{3})([0-9]{4})/", "$1-$2", $phone );
	elseif(strlen( $phone ) == 10)
		return preg_replace("/([0-9]{3})([0-9]{3})([0-9]{4})/", "( $1) $2-$3", $phone );
	else
		return $phone;
}

function pp_invoice_complete_removal()  {
	// Run regular deactivation, but also delete the main table - all invoice data is gone
	global $wpdb;
	pp_invoice_deactivation() ;;
	$wpdb->query("DROP TABLE " . $wpdb->payments_log .";" );
	$wpdb->query("DROP TABLE " . $wpdb->payments .";" );
	$wpdb->query("DROP TABLE " . $wpdb->paymentsmeta .";" );

	delete_option('pp_invoice_version' );
	delete_option('pp_invoice_payment_link' );
	delete_option('pp_invoice_payment_method' );
	delete_option('pp_invoice_protocol' );
	delete_option('pp_invoice_email_address' );
	delete_option('pp_invoice_business_name' );
	delete_option('pp_invoice_business_address' );
	delete_option('pp_invoice_business_phone' );
	delete_option('pp_invoice_paypal_address' );
	delete_option('pp_invoice_moneybookers_address' );
	delete_option('pp_invoice_googlecheckout_address' );
	delete_option('pp_invoice_default_currency_code' );
	delete_option('pp_invoice_web_invoice_page' );
	delete_option('pp_invoice_billing_meta' );
	delete_option('pp_invoice_show_quantities' );
	delete_option('pp_invoice_use_css' );
	delete_option('pp_invoice_hide_page_title' );
	delete_option('pp_invoice_send_thank_you_email' );
	delete_option('pp_invoice_reminder_message' );

	delete_option('pp_invoice_email_message_subject' );
	delete_option('pp_invoice_email_message_content' );

	//Gateway Settings
	delete_option('pp_invoice_gateway_username' );
	delete_option('pp_invoice_gateway_tran_key' );
	delete_option('pp_invoice_gateway_delim_char' );
	delete_option('pp_invoice_gateway_encap_char' );
	delete_option('pp_invoice_gateway_merchant_email' );
	delete_option('pp_invoice_gateway_url' );
	delete_option('pp_invoice_recurring_gateway_url' );
	delete_option('pp_invoice_gateway_MD5Hash' );
	delete_option('pp_invoice_gateway_test_mode' );
	delete_option('pp_invoice_gateway_delim_data' );
	delete_option('pp_invoice_gateway_relay_response' );
	delete_option('pp_invoice_gateway_email_customer' );

	return __("All settings and databases removed.", 'prospress' );
}


function pp_invoice_send_email( $invoice_array, $reminder = false ) {
	global $wpdb, $pp_invoice_email_variables;

	if(is_array( $invoice_array)) {
		$counter=0;
		foreach ( $invoice_array as $invoice_id ) {
			$pp_invoice_email_variables = pp_invoice_email_variables( $invoice_id );

			$invoice_info = $wpdb->get_row("SELECT * FROM ".$wpdb->payments." WHERE id = '".$invoice_id."'" );

			$profileuser = get_user_to_edit( $invoice_info->user_id );

			if ( $reminder) {
				$message = strip_tags(pp_invoice_show_reminder_email( $invoice_id ));
				$subject = preg_replace_callback('/(%([a-z_]+)%)/', 'pp_invoice_email_apply_variables', get_option('pp_invoice_email_send_reminder_subject' ));
			} else {
				$message = strip_tags(pp_invoice_show_email( $invoice_id ));
				$subject = preg_replace_callback('/(%([a-z_]+)%)/', 'pp_invoice_email_apply_variables', get_option('pp_invoice_email_send_invoice_subject' ));
			}

			$name = get_option("pp_invoice_business_name" );
			$from = get_option("pp_invoice_email_address" );

			$headers = "From: {$name} <{$from}>\r\n";

			$message = html_entity_decode( $message, ENT_QUOTES, 'UTF-8' );

			if(wp_mail( $profileuser->user_email, $subject, $message, $headers)) {
				$counter++; // Success in sending quantified.
				pp_invoice_update_log( $invoice_id,'contact','Invoice emailed' ); //make sent entry
				pp_invoice_update_invoice_meta( $invoice_id, "sent_date", date("Y-m-d", time()));
			}
		}
		return "Successfully sent $counter Web Invoices(s).";
	}
	else {
		$invoice_id = $invoice_array;
		$pp_invoice_email_variables = pp_invoice_email_variables( $invoice_id );
		$invoice_info = $wpdb->get_row("SELECT * FROM ".$wpdb->payments." WHERE id = '".$invoice_array."'" );

		$profileuser = get_user_to_edit( $invoice_info->user_id );

		if ( $reminder) {
			$message = strip_tags(pp_invoice_show_reminder_email( $invoice_id ));
			$subject = preg_replace_callback('/(%([a-z_]+)*)/', 'pp_invoice_email_apply_variables', get_option('pp_invoice_email_send_reminder_subject' ));
		} else {
			$message = strip_tags(pp_invoice_show_email( $invoice_id ));
			$subject = preg_replace_callback('/(%([a-z_]+)%)/', 'pp_invoice_email_apply_variables', get_option('pp_invoice_email_send_invoice_subject' ));
		}

		$name = get_option("pp_invoice_business_name" );
		$from = get_option("pp_invoice_email_address" );

		$headers = "From: {$name} <{$from}>\r\n";

		$message = html_entity_decode( $message, ENT_QUOTES, 'UTF-8' );

		if(wp_mail( $profileuser->user_email, $subject, $message, $headers)) {
			pp_invoice_update_invoice_meta( $invoice_id, "sent_date", date("Y-m-d", time()));
			pp_invoice_update_log( $invoice_id,'contact','Invoice emailed' ); return "Web invoice sent successfully."; }
			else { return "There was a problem sending the invoice."; }

	}
}

function pp_invoice_array_stripslashes( $slash_array = array()) {
	if( $slash_array) {
		foreach( $slash_array as $key=>$value ) {
			if(is_array( $value )) {
				$slash_array[$key] = pp_invoice_array_stripslashes( $value );
			}
			else {
				$slash_array[$key] = stripslashes( $value );
			}
		}
	}
	return( $slash_array);
}

function pp_invoice_profile_update() {
	global $wpdb;
	$user_id =  $_REQUEST['user_id'];

	if(isset( $_POST['company_name'])) update_usermeta( $user_id, 'company_name', $_POST['company_name']);
	if(isset( $_POST['streetaddress'])) update_usermeta( $user_id, 'streetaddress', $_POST['streetaddress']);
	if(isset( $_POST['zip']))  update_usermeta( $user_id, 'zip', $_POST['zip']);
	if(isset( $_POST['state'])) update_usermeta( $user_id, 'state', $_POST['state']);
	if(isset( $_POST['city'])) update_usermeta( $user_id, 'city', $_POST['city']);
	if(isset( $_POST['phonenumber'])) update_usermeta( $user_id, 'phonenumber', $_POST['phonenumber']);

}

class PP_Invoice_Date  {

	function convert( $string, $from_mask, $to_mask='', $return_unix=false ) {
		// define the valid values that we will use to check
		// value => length
		$all = array(
			's' => 'ss',
			'i' => 'ii',
			'H' => 'HH',
			'y' => 'yy',
			'Y' => 'YYYY', 
			'm' => 'mm', 
			'd' => 'dd'
		);

		// this will give us a mask with full length fields
		$from_mask = str_replace(array_keys( $all), $all, $from_mask);

		$vals = array();
		foreach( $all as $type => $chars) {
			// get the position of the current character
			if(( $pos = strpos( $from_mask, $chars)) === false )
				continue;

			// find the value in the original string
			$val = substr( $string, $pos, strlen( $chars));

			// store it for later processing
			$vals[$type] = $val;
		}

		foreach( $vals as $type => $val) {
			switch( $type ) {
				case 's' :
					$seconds = $val;
				break;
				case 'i' :
					$minutes = $val;
				break;
				case 'H':
					$hours = $val;
				break;
				case 'y':
					$year = '20'.$val; // Year 3k bug right here
				break;
				case 'Y':
					$year = $val;
				break;
				case 'm':
					$month = $val;
				break;
				case 'd':
					$day = $val;
				break;
			}
		}

		$unix_time = mktime(
			(int)$hours, (int)$minutes, (int)$seconds, 
			(int)$month, (int)$day, (int)$year);

		if( $return_unix)
			return $unix_time;

		return date( $to_mask, $unix_time );
	}
}

function pp_invoice_fix_billing_meta_array( $arr){
    $narr = array();
	$counter = 1;
    while(list( $key, $val) = each( $arr)){
        if (is_array( $val)){
            $val = array_remove_empty( $val);
            if (count( $val)!=0){
                $narr[$counter] = $val;$counter++;
            }
        }
        else {
            if (trim( $val) != "" ){
                $narr[$counter] = $val;$counter++;
            }
        }

    }
    unset( $arr);
    return $narr;
}

function pp_invoice_year_dropdown( $sel='' ) {
	$localDate=getdate();
	$minYear = $localDate["year"];
	$maxYear = $minYear + 15;

	$output =  "<option value=''>--</option>";
	for( $i=$minYear; $i<$maxYear; $i++) {
		$output .= "<option value='". substr( $i, 2, 2) ."'".( $sel==(substr( $i, 2, 2))?' selected':'' ).
		">". $i ."</option>";
	}
	return $output;
}

function pp_invoice_month_dropdown() {

	$months = array(
		"01" => __("Jan", 'prospress' ),
		"02" => __("Feb", 'prospress' ),
		"03" => __("Mar", 'prospress' ),
		"04" => __("Apr", 'prospress' ),
		"05" => __("May", 'prospress' ),
		"06" => __("Jun", 'prospress' ),
		"07" => __("Jul", 'prospress' ),
		"08" => __("Aug", 'prospress' ),
		"09" => __("Sep", 'prospress' ),
		"10" => __("Oct", 'prospress' ),
		"11" => __("Nov", 'prospress' ),
		"12" => __("Dec", 'prospress' )
	);

	$output =  "<option value=''>--</option>";
	foreach( $months as $key => $month )
		$output .=  "<option value='$key'>$month</option>";

	return $output;
}

function pp_invoice_state_array( $sel='' ) {
$StateProvinceTwoToFull = array(
   'AL' => 'Alabama',
   'AK' => 'Alaska',
   'AS' => 'American Samoa',
   'AZ' => 'Arizona',
   'AR' => 'Arkansas',
   'CA' => 'California',
   'CO' => 'Colorado',
   'CT' => 'Connecticut',
   'DE' => 'Delaware',
   'DC' => 'District of Columbia',
   'FM' => 'Federated States of Micronesia',
   'FL' => 'Florida',
   'GA' => 'Georgia',
   'GU' => 'Guam',
   'HI' => 'Hawaii',
   'ID' => 'Idaho',
   'IL' => 'Illinois',
   'IN' => 'Indiana',
   'IA' => 'Iowa',
   'KS' => 'Kansas',
   'KY' => 'Kentucky',
   'LA' => 'Louisiana',
   'ME' => 'Maine',
   'MH' => 'Marshall Islands',
   'MD' => 'Maryland',
   'MA' => 'Massachusetts',
   'MI' => 'Michigan',
   'MN' => 'Minnesota',
   'MS' => 'Mississippi',
   'MO' => 'Missouri',
   'MT' => 'Montana',
   'NE' => 'Nebraska',
   'NV' => 'Nevada',
   'NH' => 'New Hampshire',
   'NJ' => 'New Jersey',
   'NM' => 'New Mexico',
   'NY' => 'New York',
   'NC' => 'North Carolina',
   'ND' => 'North Dakota',
   'MP' => 'Northern Mariana Islands',
   'OH' => 'Ohio',
   'OK' => 'Oklahoma',
   'OR' => 'Oregon',
   'PW' => 'Palau',
   'PA' => 'Pennsylvania',
   'PR' => 'Puerto Rico',
   'RI' => 'Rhode Island',
   'SC' => 'South Carolina',
   'SD' => 'South Dakota',
   'TN' => 'Tennessee',
   'TX' => 'Texas',
   'UT' => 'Utah',
   'VT' => 'Vermont',
   'VI' => 'Virgin Islands',
   'VA' => 'Virginia',
   'WA' => 'Washington',
   'WV' => 'West Virginia',
   'WI' => 'Wisconsin',
   'WY' => 'Wyoming',
   'AB' => 'Alberta',
   'BC' => 'British Columbia',
   'MB' => 'Manitoba',
   'NB' => 'New Brunswick',
   'NF' => 'Newfoundland',
   'NW' => 'Northwest Territory',
   'NS' => 'Nova Scotia',
   'ON' => 'Ontario',
   'PE' => 'Prince Edward Island',
   'QU' => 'Quebec',
   'SK' => 'Saskatchewan',
   'YT' => 'Yukon Territory',
	);

  return( $StateProvinceTwoToFull);
}

function pp_invoice_country_array() {
	return array("US"=> "United States","AL"=> "Albania","DZ"=> "Algeria","AD"=> "Andorra","AO"=> "Angola","AI"=> "Anguilla","AG"=> "Antigua and Barbuda","AR"=> "Argentina","AM"=> "Armenia","AW"=> "Aruba","AU"=> "Australia","AT"=> "Austria","AZ"=> "Azerbaijan Republic","BS"=> "Bahamas","BH"=> "Bahrain","BB"=> "Barbados","BE"=> "Belgium","BZ"=> "Belize","BJ"=> "Benin","BM"=> "Bermuda","BT"=> "Bhutan","BO"=> "Bolivia","BA"=> "Bosnia and Herzegovina","BW"=> "Botswana","BR"=> "Brazil","VG"=> "British Virgin Islands","BN"=> "Brunei","BG"=> "Bulgaria","BF"=> "Burkina Faso","BI"=> "Burundi","KH"=> "Cambodia","CA"=> "Canada","CV"=> "Cape Verde","KY"=> "Cayman Islands","TD"=> "Chad","CL"=> "Chile","C2"=> "China","CO"=> "Colombia","KM"=> "Comoros","CK"=> "Cook Islands","CR"=> "Costa Rica","HR"=> "Croatia","CY"=> "Cyprus","CZ"=> "Czech Republic","CD"=> "Democratic Republic of the Congo","DK"=> "Denmark","DJ"=> "Djibouti","DM"=> "Dominica","DO"=> "Dominican Republic","EC"=> "Ecuador","SV"=> "El Salvador","ER"=> "Eritrea","EE"=> "Estonia","ET"=> "Ethiopia","FK"=> "Falkland Islands","FO"=> "Faroe Islands","FM"=> "Federated States of Micronesia","FJ"=> "Fiji","FI"=> "Finland","FR"=> "France","GF"=> "French Guiana","PF"=> "French Polynesia","GA"=> "Gabon Republic","GM"=> "Gambia","DE"=> "Germany","GI"=> "Gibraltar","GR"=> "Greece","GL"=> "Greenland","GD"=> "Grenada","GP"=> "Guadeloupe","GT"=> "Guatemala","GN"=> "Guinea","GW"=> "Guinea Bissau","GY"=> "Guyana","HN"=> "Honduras","HK"=> "Hong Kong","HU"=> "Hungary","IS"=> "Iceland","IN"=> "India","ID"=> "Indonesia","IE"=> "Ireland","IL"=> "Israel","IT"=> "Italy","JM"=> "Jamaica","JP"=> "Japan","JO"=> "Jordan","KZ"=> "Kazakhstan","KE"=> "Kenya","KI"=> "Kiribati","KW"=> "Kuwait","KG"=> "Kyrgyzstan","LA"=> "Laos","LV"=> "Latvia","LS"=> "Lesotho","LI"=> "Liechtenstein","LT"=> "Lithuania","LU"=> "Luxembourg","MG"=> "Madagascar","MW"=> "Malawi","MY"=> "Malaysia","MV"=> "Maldives","ML"=> "Mali","MT"=> "Malta","MH"=> "Marshall Islands","MQ"=> "Martinique","MR"=> "Mauritania","MU"=> "Mauritius","YT"=> "Mayotte","MX"=> "Mexico","MN"=> "Mongolia","MS"=> "Montserrat","MA"=> "Morocco","MZ"=> "Mozambique","NA"=> "Namibia","NR"=> "Nauru","NP"=> "Nepal","NL"=> "Netherlands","AN"=> "Netherlands Antilles","NC"=> "New Caledonia","NZ"=> "New Zealand","NI"=> "Nicaragua","NE"=> "Niger","NU"=> "Niue","NF"=> "Norfolk Island","NO"=> "Norway","OM"=> "Oman","PW"=> "Palau","PA"=> "Panama","PG"=> "Papua New Guinea","PE"=> "Peru","PH"=> "Philippines","PN"=> "Pitcairn Islands","PL"=> "Poland","PT"=> "Portugal","QA"=> "Qatar","CG"=> "Republic of the Congo","RE"=> "Reunion","RO"=> "Romania","RU"=> "Russia","RW"=> "Rwanda","VC"=> "Saint Vincent and the Grenadines","WS"=> "Samoa","SM"=> "San Marino","ST"=> "São Tomé and Príncipe","SA"=> "Saudi Arabia","SN"=> "Senegal","SC"=> "Seychelles","SL"=> "Sierra Leone","SG"=> "Singapore","SK"=> "Slovakia","SI"=> "Slovenia","SB"=> "Solomon Islands","SO"=> "Somalia","ZA"=> "South Africa","KR"=> "South Korea","ES"=> "Spain","LK"=> "Sri Lanka","SH"=> "St. Helena","KN"=> "St. Kitts and Nevis","LC"=> "St. Lucia","PM"=> "St. Pierre and Miquelon","SR"=> "Suriname","SJ"=> "Svalbard and Jan Mayen Islands","SZ"=> "Swaziland","SE"=> "Sweden","CH"=> "Switzerland","TW"=> "Taiwan","TJ"=> "Tajikistan","TZ"=> "Tanzania","TH"=> "Thailand","TG"=> "Togo","TO"=> "Tonga","TT"=> "Trinidad and Tobago","TN"=> "Tunisia","TR"=> "Turkey","TM"=> "Turkmenistan","TC"=> "Turks and Caicos Islands","TV"=> "Tuvalu","UG"=> "Uganda","UA"=> "Ukraine","AE"=> "United Arab Emirates","GB"=> "United Kingdom","UY"=> "Uruguay","VU"=> "Vanuatu","VA"=> "Vatican City State","VE"=> "Venezuela","VN"=> "Vietnam","WF"=> "Wallis and Futuna Islands","YE"=> "Yemen","ZM"=> "Zambia" );
}

function pp_invoice_month_array() {
	return array(
		"01" => __("Jan", 'prospress' ),
		"02" => __("Feb", 'prospress' ),
		"03" => __("Mar", 'prospress' ),
		"04" => __("Apr", 'prospress' ),
		"05" => __("May", 'prospress' ),
		"06" => __("Jun", 'prospress' ),
		"07" => __("Jul", 'prospress' ),
		"08" => __("Aug", 'prospress' ),
		"09" => __("Sep", 'prospress' ),
		"10" => __("Oct", 'prospress' ),
		"11" => __("Nov", 'prospress' ),
		"12" => __("Dec", 'prospress' ));
}

function pp_invoice_go_secure( $destination) {
    $reload = 'Location: ' . $destination;
    header( $reload );
} 

function pp_invoice_process_cc_ajax() {

	$nonce = $_REQUEST['pp_invoice_process_cc'];
	$invoice_id = $_REQUEST['invoice_id'];

 	if (! wp_verify_nonce( $nonce, 'pp_invoice_process_cc_' . $invoice_id ) ) die('Security check' ); 

	pp_invoice_process_cc_transaction();
}

function pp_invoice_process_cc_transaction( $cc_data = false ) {

	$errors = array();
	$errors_msg = null;
	$_POST['processing_problem'] = '';
	unset( $stop_transaction );
	$invoice_id = preg_replace("/[^0-9]/","", $_POST['invoice_id']);

	$invoice_class = new pp_invoice_get( $invoice_id );
	$invoice_class = $invoice_class->data;

	$wp_users_id = $_POST[ 'user_id' ];

	if( empty( $_POST['first_name'] ) ) {
		$errors[ 'first_name' ][] = "Please enter your first name.";
		$stop_transaction = true;
	}

	if( empty( $_POST['last_name' ] ) ) { 
		$errors[ 'last_name' ][] = "Please enter your last name. ";
		$stop_transaction = true;
	}

	if( empty( $_POST['email_address' ] ) ) { 
		$errors[ 'email_address' ][] = "Please provide an email address.";
		$stop_transaction = true;
	}

	if( empty( $_POST['phonenumber' ] ) ) { 
		$errors[ 'phonenumber' ][] = "Please enter your phone number.";
		$stop_transaction = true;
	}

	if( empty( $_POST['address' ] ) ) { 
		$errors[ 'address' ][] = "Please enter your address.";
		$stop_transaction = true;
	}

	if( empty( $_POST['city' ] ) ) { 
		$errors[ 'city' ][] = "Please enter your city.";
		$stop_transaction = true;
	}

	if( empty( $_POST['state' ] ) ) { 
		$errors[ 'state' ][] = "Please select your state.";
		$stop_transaction = true;
	}

	if( empty( $_POST['zip' ] ) ) { 
		$errors[ 'zip' ][] = "Please enter your ZIP code.";
		$stop_transaction = true;
	}

	if( empty( $_POST['country' ] ) ) { 
		$errors[ 'country' ][] = "Please enter your country.";
		$stop_transaction = true;
	}

	if( empty( $_POST['card_num'])) {
		$errors[ 'card_num' ][]  = "Please enter your credit card number.";	
		$stop_transaction = true;
	} elseif( !pp_invoice_validate_cc_number( $_POST['card_num' ] ) ) { 
		$errors[ 'card_num' ][] = "Please enter a valid credit card number."; 
		$stop_transaction = true; 
	}

	if( empty( $_POST['exp_month' ] ) ) { 
		$errors[ 'exp_month' ][] = "Please enter your credit card's expiration month.";
		$stop_transaction = true;
	}

	if( empty( $_POST['exp_year' ] ) ) { 
		$errors[ 'exp_year' ][] = "Please enter your credit card's expiration year.";
		$stop_transaction = true;
	}

	if( empty( $_POST['card_code' ] ) ) { 
		$errors[ 'card_code' ][] = "The <b>Security Code</b> is the code on the back of your card.";
		$stop_transaction = true;
	}

	// Charge Card
	if( !$stop_transaction ) {

		require_once('gateways/authnet.class.php' );
		require_once('gateways/authnetARB.class.php' );

		$payment = new PP_Invoice_Authnet( $invoice_class->payee_id );
		$payment->transaction( $_POST['card_num']);

		// Billing Info
		$payment->setParameter("x_card_code", $_POST['card_code']);
		$payment->setParameter("x_exp_date ", $_POST['exp_month'] . $_POST['exp_year']);
		$payment->setParameter("x_amount", $invoice_class->amount);

		// Order Info
		$payment->setParameter("x_description", $invoice_class->post_title );
		$payment->setParameter("x_invoice_num",  $invoice_id );
		$payment->setParameter("x_test_request", false );
		$payment->setParameter("x_duplicate_window", 30);

		//Customer Info
		$payment->setParameter("x_first_name", $_POST['first_name']);
		$payment->setParameter("x_last_name", $_POST['last_name']);
		$payment->setParameter("x_address", $_POST['address']);
		$payment->setParameter("x_city", $_POST['city']);
		$payment->setParameter("x_state", $_POST['state']);
		$payment->setParameter("x_country", $_POST['country']);
		$payment->setParameter("x_zip", $_POST['zip']);
		$payment->setParameter("x_phone", $_POST['phonenumber']);
		$payment->setParameter("x_email", $_POST['email_address']);
		$payment->setParameter("x_cust_id", "WP User - " . $invoice_class->payer_class->user_nicename );
		$payment->setParameter("x_customer_ip ", $_SERVER['REMOTE_ADDR']);

		$payment->process(); 

		if( $payment->isApproved() ) {

			// Returning valid nonce marks transaction as good on front-end
			echo wp_create_nonce('pp_invoice_process_cc_' . $invoice_id );

			update_usermeta( $wp_users_id,'last_name',$_POST['last_name']);
			update_usermeta( $wp_users_id,'last_name',$_POST['last_name']);
			update_usermeta( $wp_users_id,'first_name',$_POST['first_name']);
			update_usermeta( $wp_users_id,'city',$_POST['city']);
			update_usermeta( $wp_users_id,'state',$_POST['state']);
			update_usermeta( $wp_users_id,'zip',$_POST['zip']);
			update_usermeta( $wp_users_id,'streetaddress',$_POST['address']);
			update_usermeta( $wp_users_id,'phonenumber',$_POST['phonenumber']);
			update_usermeta( $wp_users_id,'country',$_POST['country']);

			//Mark invoice as paid
			pp_invoice_paid( $invoice_id );

			if( get_option('pp_invoice_send_thank_you_email' ) == 'yes' ) {
				pp_invoice_send_email_receipt( $invoice_id );
			}

		 } else {
			if( $payment->getResponseText() ) {
				$errors['processing_problem'][] .= $payment->getResponseText();
			} elseif ( $payment->getErrorMessage() ){
				foreach( preg_split( "/\n|(?<=\.)\s/", $payment->getErrorMessage() ) as $msg )
					$errors['processing_problem'][] .= $msg;
			} else {
				$errors['processing_problem'][] .= 'Processing Error. Please check your gateway settings.';
			}
			$stop_transaction = true;
		}
	}

	if( $stop_transaction && is_array( $_POST ) ) {
		foreach ( $_POST as $key => $value ) {
			if ( array_key_exists ( $key, $errors ) ) {
				foreach ( $errors [ $key ] as $k => $v ) {
					$errors_msg .= "error|$key|$v\n";
				}
			} else {
				$errors_msg .= "ok|$key\n";
			}
		}
		echo $errors_msg;
	}

	die();
}

function pp_invoice_process_invoice_update( $invoice_id ) {

	global $wpdb;

	if( $_REQUEST['user_id'] == 'create_new_user' ) {

		$user_info = array();
		$user_info['pp_invoice_first_name'] = $_REQUEST['pp_invoice_first_name'];
		$user_info['pp_invoice_last_name'] = $_REQUEST['pp_invoice_last_name'];
		$user_info['pp_invoice_new_user_username'] = $_REQUEST['pp_invoice_new_user_username'];
		$user_info['pp_invoice_new_user_email_address'] = $_REQUEST['pp_invoice_new_user_email_address'];

		$user_id = pp_invoice_create_wp_user( $user_info);

	} else {
		$user_id = $_REQUEST['user_id'];
	}

	//Update User Information
	$profileuser = get_user_to_edit( $_POST['user_id']);
	$description = $_REQUEST['description'];
	$subject = $_REQUEST['subject'];
	$amount = $_REQUEST['amount'];

	//Update User Information
	if(!empty( $_REQUEST['pp_invoice_first_name'])) update_usermeta( $user_id, 'first_name', $_REQUEST['pp_invoice_first_name']);
	if(!empty( $_REQUEST['pp_invoice_last_name'])) update_usermeta( $user_id, 'last_name', $_REQUEST['pp_invoice_last_name']);
	if(!empty( $_REQUEST['pp_invoice_streetaddress'])) update_usermeta( $user_id, 'streetaddress', $_REQUEST['pp_invoice_streetaddress']);
	if(!empty( $_REQUEST['pp_invoice_company_name'])) update_usermeta( $user_id, 'company_name',$_REQUEST['pp_invoice_company_name']);
	if(!empty( $_REQUEST['pp_invoice_city'])) update_usermeta( $user_id, 'city',$_REQUEST['pp_invoice_city']);
	if(!empty( $_REQUEST['pp_invoice_state'])) update_usermeta( $user_id, 'state', $_REQUEST['pp_invoice_state']);
	if(!empty( $_REQUEST['pp_invoice_zip'])) update_usermeta( $user_id, 'zip', $_REQUEST['pp_invoice_zip']);

	// Itemized List
	$itemized_list = $_REQUEST['itemized_list'];
	//remove items from itemized list that are missing a title, they are most likely deleted
	if(is_array( $itemized_list)) {
		$counter = 1;
		foreach( $itemized_list as $itemized_item){
			if(empty( $itemized_item[name])) {
				unset( $itemized_list[$counter]); 
			}
		$counter++;
		}
	array_values( $itemized_list);
	}
	$itemized = urlencode(serialize( $itemized_list));

	// Check if this is new invoice creation, or an update

	if(pp_invoice_does_invoice_exist( $invoice_id )) {
		// Updating Old Invoice

		if(pp_invoice_get_invoice_attrib( $invoice_id,'subject' ) != $subject) { $wpdb->query("UPDATE ".$wpdb->payments." SET subject = '$subject' WHERE id = $invoice_id" ); 			pp_invoice_update_log( $invoice_id, 'updated', ' Subject Updated ' ); $message .= "Subject updated. ";}
		if(pp_invoice_get_invoice_attrib( $invoice_id,'description' ) != $description) { $wpdb->query("UPDATE ".$wpdb->payments." SET description = '$description' WHERE id = $invoice_id" ); 			pp_invoice_update_log( $invoice_id, 'updated', ' Description Updated ' ); $message .= "Description updated. ";}
		if(pp_invoice_get_invoice_attrib( $invoice_id,'amount' ) != $amount) { $wpdb->query("UPDATE ".$wpdb->payments." SET amount = '$amount' WHERE id = $invoice_id" ); 			pp_invoice_update_log( $invoice_id, 'updated', ' Amount Updated ' ); $message .= "Amount updated. ";}
		if(pp_invoice_get_invoice_attrib( $invoice_id,'itemized' ) != $itemized ) { $wpdb->query("UPDATE ".$wpdb->payments." SET itemized = '$itemized' WHERE id = $invoice_id" ); 			pp_invoice_update_log( $invoice_id, 'updated', ' Itemized List Updated ' ); $message .= "Itemized List updated. ";}
	}
	else {
		// Create New Invoice

		if( $wpdb->query("INSERT INTO ".$wpdb->payments." (amount,description,id,user_id,subject,itemized,status)	VALUES ('$amount','$description','$invoice_id','$user_id','$subject','$itemized','0' )" )) {
			$message = __("New Invoice saved.", 'prospress' );
			pp_invoice_update_log( $invoice_id, 'created', ' Created ' );;
		} 
		else { 
			$error = true; $message = __("There was a problem saving invoice.  Try deactivating and reactivating plugin.", 'prospress' ); 
		}
	}

	// See if invoice is recurring
	if(!empty( $_REQUEST['pp_invoice_subscription_name']) &&	!empty( $_REQUEST['pp_invoice_subscription_unit']) && !empty( $_REQUEST['pp_invoice_subscription_total_occurances'])) {
		$pp_invoice_recurring_status = true;
		pp_invoice_update_invoice_meta( $invoice_id, "recurring_billing", true );
		$message .= __(" Recurring invoice saved.  This invoice may be viewed under <b>Recurring Billing</b>. ", 'prospress' );
	}

	$basic_invoice_settings = array(
	"pp_invoice_custom_invoice_id",
	"pp_invoice_tax",
	"pp_invoice_currency_code",
	"pp_invoice_due_date_day",
	"pp_invoice_due_date_month",
	"pp_invoice_due_date_year" );

	pp_invoice_process_updates( $basic_invoice_settings, 'pp_invoice_update_invoice_meta', $invoice_id );

	$payment_and_billing_settings_array = array(
	"pp_invoice_payment_method",
	"pp_invoice_client_change_payment_method",

	"pp_invoice_paypal_allow",
	"pp_invoice_paypal_address",

	"pp_invoice_cc_allow",
	"pp_invoice_gateway_url",
	"pp_invoice_gateway_username",
	"pp_invoice_gateway_tran_key",
	"pp_invoice_gateway_merchant_email",
	"pp_invoice_gateway_delim_data",
	"pp_invoice_gateway_delim_char",
	"pp_invoice_gateway_encap_char",
	"pp_invoice_gateway_MD5Hash",
	"pp_invoice_gateway_test_mode",
	"pp_invoice_gateway_relay_response",
	"pp_invoice_gateway_email_customer",
	"pp_invoice_recurring_gateway_url",

	"pp_invoice_moneybookers_allow",
	"pp_invoice_moneybookers_address",
	"pp_invoice_moneybookers_merchant",
	"pp_invoice_moneybookers_secret",
	"pp_invoice_moneybookers_ip",

	"pp_invoice_googlecheckout_address",

	"pp_invoice_alertpay_allow",
	"pp_invoice_alertpay_address",
	"pp_invoice_alertpay_merchant",
	"pp_invoice_alertpay_secret",
	"pp_invoice_gateway_email_customer",
	"pp_invoice_alertpay_test_mode",

	"pp_invoice_subscription_name",
	"pp_invoice_subscription_unit",
	"pp_invoice_subscription_length",
	"pp_invoice_subscription_start_month",
	"pp_invoice_subscription_start_day",
	"pp_invoice_subscription_start_year",
	"pp_invoice_subscription_total_occurances" );

	pp_invoice_process_updates( $payment_and_billing_settings_array, 'pp_invoice_update_invoice_meta', $invoice_id );

	//If there is a message, append it with the web invoice link
	if( $message && $invoice_id ) {
	$invoice_info = new PP_Invoice_GetInfo( $invoice_id ); 
	$message .= " <a href='".$invoice_info->display('link' )."'>".__("View Web Invoice", 'prospress' )."</a>.";
	}

	if(!$error) return $message;
	if( $error) return "An error occured: $message.";

}

function pp_invoice_show_message( $content,$type="updated fade" ) {
if( $content) echo "<div id=\"message\" class='$type' ><p>".$content."</p></div>";
}

/*
	Throw warnings if any required configuation settings are missing
*/
function pp_invoice_detect_config_erors() {
	global $wpdb;

	if( get_option("pp_invoice_web_invoice_page" ) == '' ) { 
		$warning_message .= __('Invoice page not selected. ', 'prospress' ); 
	}
	if( get_option("pp_invoice_payment_method" ) == '' ) { 
		$warning_message .= __('Payment method not set. ', 'prospress' ); 
	}
	if( get_option("pp_invoice_payment_method" ) == '' || get_option("pp_invoice_web_invoice_page" ) == '' ) {
		$warning_message .= __("Visit ", 'prospress' )."<a href='admin.php?page=invoice_settings'>settings page</a>".__(" to configure.", 'prospress' );
	}

	if( !$wpdb->query("SHOW TABLES LIKE '".$wpdb->paymentsmeta."';" ) || !$wpdb->query("SHOW TABLES LIKE '".$wpdb->payments."';" ) || !$wpdb->query("SHOW TABLES LIKE '".$wpdb->payments_log."';" )) { 
		$warning_message .= __("The plugin database tables are gone, deactivate and reactivate plugin to re-create them.", 'prospress' );
	}

	return $warning_message;
}

function pp_invoice_is_not_merchant() {
	if(get_option('pp_invoice_gateway_username' ) == '' || get_option('pp_invoice_gateway_tran_key' ) == '' ) return true;
}

function pp_invoice_process_updates( $array, $type = "update_option", $invoice_id = '' ) {

	if( $type == "update_option" )
		foreach( $array as $item_name )
			if( isset( $_POST[ $item_name ] ) )
				update_option( $item_name, $_POST[ $item_name ] );

	if( $type == "pp_invoice_update_invoice_meta" ) foreach( $array as $item_name ) { 
		if(isset( $_POST[$item_name])) pp_invoice_update_invoice_meta( $invoice_id, $item_name, $_POST[$item_name]); }
}

function pp_invoice_md5_to_invoice( $md5) {
	global $wpdb, $_pp_invoice_md5_to_invoice_cache;
	if (isset( $_pp_invoice_md5_to_invoice_cache[$md5]) && $_pp_invoice_md5_to_invoice_cache[$md5]) {
		return $_pp_invoice_md5_to_invoice_cache[$md5];
	}

	$md5_escaped = mysql_escape_string( $md5);
	$all_invoices = $wpdb->get_col("SELECT id FROM ".$wpdb->payments." WHERE MD5(id ) = '{$md5_escaped}'" );
	foreach ( $all_invoices as $value ) {
		if(md5( $value ) == $md5) {
			$_pp_invoice_md5_to_invoice_cache[$md5] = $value;
			return $_pp_invoice_md5_to_invoice_cache[$md5];
		}
	}
}

function pp_invoice_create_paypal_itemized_list( $itemized_array,$invoice_id ) {
	$invoice = new PP_Invoice_GetInfo( $invoice_id );
	$tax = $invoice->display('tax_percent' );
	$amount = $invoice->display('amount' );
	$display_id = $invoice->display('display_id' );

	$tax_free_sum = 0;
	$counter = 1;
	foreach( $itemized_array as $itemized_item) {

		// If we have a negative item, PayPal will not accept, we must group everything into one amount
		if( $itemized_item[price] * $itemized_item[quantity] < 0) {
			$tax = 0;
			$output = "
			<input type='hidden' name='item_name' value='Reference Invoice #$display_id' /> \n
			<input type='hidden' name='amount' value='$amount' />\n";

			$single_item = true;
			break;
		}

		$output .= "<input type='hidden' name='item_name_$counter' value='".$itemized_item[name]."' />\n";
		$output .= "<input type='hidden' name='amount_$counter' value='".$itemized_item[price] * $itemized_item[quantity]."' />\n";

		$tax_free_sum = $tax_free_sum + $itemized_item[price] * $itemized_item[quantity];
		$counter++;
	}

	// Add tax onnly by using tax_free_sum (which is the sums of all the individual items * quantities. 
	if(!empty( $tax)) {
		$tax_cart = round( $tax_free_sum * ( $tax / 100),2);
		$output .= "<input type='hidden' name='tax_cart' value='". $tax_cart ."' />\n";	
	}

	if( $single_item) $output .= "<input type='hidden' name='cmd' value='_xclick' />\n";	
	if(!$single_item) $output .= "
	<input type='hidden' name='cmd' value='_ext-enter' />
	<input type='hidden' name='redirect_cmd' value='_cart' />\n";	
	return $output;
}

function pp_invoice_create_googlecheckout_itemized_list( $itemized_array,$invoice_id ) {
	$invoice = new PP_Invoice_GetInfo( $invoice_id );
	$tax = $invoice->display('tax_percent' );
	$amount = $invoice->display('amount' );
	$display_id = $invoice->display('display_id' );
	$currency = $invoice->display('currency' );
	$tax_percent = $invoice->display('tax_percent' );

	$tax_free_sum = 0;
	$counter = 1;
	foreach( $itemized_array as $itemized_item) {
		// If we have a negative item, PayPal will not accept, we must group everything into one amount
		$output .= "<input type='hidden' name='item_name_$counter' value='".$itemized_item[name]."'>\n";
		$output .= "<input type='hidden' name='item_quantity_$counter' value='".$itemized_item[quantity]."'>\n";
		$output .= "<input type='hidden' name='item_price_$counter' value='".$itemized_item[price]."'>\n";
		$output .= "<input type='hidden' name='item_currency_$counter' value='$currency'>\n";
		$tax_free_sum = $tax_free_sum + $itemized_item[price] * $itemized_item[quantity];
		$counter++;
	}

	// Add tax onnly by using tax_free_sum (which is the sums of all the individual items * quantities. 
	if(!empty( $tax)) {
	$tax_cart = round( $tax_free_sum * ( $tax / 100),2);
		$output .= "<input type='hidden' value='$tax_percent' name='tax_rate'>\n";
		}

	return $output;
}

function pp_invoice_create_moneybookers_itemized_list( $itemized_array,$invoice_id ) {
	$invoice = new PP_Invoice_GetInfo( $invoice_id );
	$tax = $invoice->display('tax_percent' );
	$amount = $invoice->display('amount' );
	$display_id = $invoice->display('display_id' );
	$single_item = false;

	$tax_free_sum = 0;
	$counter = 1;

	if (empty( $tax) && count( $itemized_array) >  3) {
		$single_item = true;
	} else if (count( $itemized_array) >  2) {
		$single_item = true;
	}

	foreach( $itemized_array as $itemized_item) {
		if (!$single_item) {
			$output .= "<input type='hidden' name='detail{$counter}_description' value='".$itemized_item[name]."' />\n";
			$output .= "<input type='hidden' name='detail{$counter}_text' value='".$itemized_item[description]."' />\n";

			$counter++;

			$output .= "<input type='hidden' name='amount{$counter}' value='".$itemized_item[price] * $itemized_item[quantity]."' />\n";
		}

		$tax_free_sum = $tax_free_sum + $itemized_item[price] * $itemized_item[quantity];
	}

	// Add tax only by using tax_free_sum (which is the sums of all the individual items * quantities.
	if(!$single_item && !empty( $tax)) {
		$tax_cart = round( $tax_free_sum * ( $tax / 100),2);
		$output .= "<input type='hidden' name='detail{$counter}_description' value='Tax' />\n";
		$output .= "<input type='hidden' name='detail{$counter}_text' value='({$tax} %)' />\n";
		$counter++;
		$output .= "<input type='hidden' name='amount{$counter}' value='". $tax_cart ."' />\n";
	}

	$output .= "<input type='hidden' name='detail1_description' value='Reference Invoice #:' />\n";
	$output .= "<input type='hidden' name='detail1_text' value='$display_id' />\n";

	return $output;
}

function pp_invoice_create_alertpay_itemized_list( $itemized_array,$invoice_id ) {
	$invoice = new PP_Invoice_GetInfo( $invoice_id );
	$tax = $invoice->display('tax_percent' );
	$amount = $invoice->display('amount' );
	$display_id = $invoice->display('display_id' );

	$tax_free_sum = 0;
	$counter = 1;
	foreach( $itemized_array as $itemized_item) {
		$counter++;
		$tax_free_sum = $tax_free_sum + $itemized_item[price] * $itemized_item[quantity];
	}

	$output = "
		<input type='hidden' name='ap_description' value='Reference Invoice # $display_id' /> \n
		<input type='hidden' name='ap_amount' value='$tax_free_sum' />\n
		<input type='hidden' name='ap_quantity' value='1' />\n";

	// Add tax only by using tax_free_sum (which is the sums of all the individual items * quantities.
	if(!empty( $tax)) {
		$tax_cart = round( $tax_free_sum * ( $tax / 100),2);
		$output .= "<input type='hidden' name='ap_taxamount' value='". $tax_cart ."' />\n";
	}

	return $output;
}

function pp_invoice_user_accepted_payments( $payee_id ) {

	if(pp_invoice_user_settings('paypal_allow', $payee_id ) == 'true' )
		$return[paypal_allow] = true;

	if(pp_invoice_user_settings('cc_allow', $payee_id ) == 'true' )
		$return[cc_allow] = true;

	if(pp_invoice_user_settings('draft_allow', $payee_id ) == 'true' )
		$return[draft_allow] = true;

	return $return;

}

function pp_invoice_accepted_payment( $invoice_id = 'global' ) {

	// fix the occasional issue with empty value being passed
	if(empty( $invoice_id ))
		$invoice_id = "global";

 	if( $invoice_id == 'global' ) {

		if(get_option('pp_invoice_paypal_allow' ) == 'yes' ) { 
			$payment_array['paypal']['name'] = 'paypal'; 
			$payment_array['paypal']['active'] = true; 
			$payment_array['paypal']['nicename'] = "PayPal"; 
			if(get_option('pp_invoice_payment_method' ) == 'paypal' || get_option('pp_invoice_payment_method' ) == 'PayPal' ) $payment_array['paypal']['default'] = true; 
		}

		if(get_option('pp_invoice_cc_allow' ) == 'yes' ) { 
			$payment_array['cc']['name'] = 'cc'; 
			$payment_array['cc']['active'] = true; 
			$payment_array['cc']['nicename'] = "Credit Card"; 
			if(get_option('pp_invoice_payment_method' ) == 'cc' || get_option('pp_invoice_payment_method' ) == 'Credit Card' ) $payment_array['cc']['default'] = true; 
		}
/*
		if(get_option('pp_invoice_moneybookers_allow' ) == 'yes' ) { 
			$payment_array['moneybookers']['name'] = 'moneybookers'; 
			$payment_array['moneybookers']['active'] = true; 
			$payment_array['moneybookers']['nicename'] = "Moneybookers"; 
			if(get_option('pp_invoice_payment_method' ) == 'moneybookers' ) $payment_array['moneybookers']['default'] = true; 
		}

		if(get_option('pp_invoice_alertpay_allow' ) == 'yes' ) { 
			$payment_array['alertpay']['name'] = 'alertpay'; 
			$payment_array['alertpay']['active'] = true; 
			$payment_array['alertpay']['nicename'] = "AlertPay"; 
			if(get_option('pp_invoice_payment_method' ) == 'alertpay' ) $payment_array['alertpay']['default'] = true; 
		}	
*/	

		return $payment_array;
	} else {

		$invoice_info = new PP_Invoice_GetInfo( $invoice_id );
		$payment_array = array();
		if( $invoice_info->display('pp_invoice_payment_method' ) != '' ) { $custom_default_payment = true; } else { $custom_default_payment = false; }

		if( $invoice_info->display('pp_invoice_paypal_allow' ) == 'yes' ) {
			$payment_array['paypal']['name'] = 'paypal'; 
			$payment_array['paypal']['active'] = true; 
			$payment_array['paypal']['nicename'] = "PayPal"; 

			if( $custom_default_payment && $invoice_info->display('pp_invoice_payment_method' ) == 'paypal' || $invoice_info->display('pp_invoice_payment_method' ) == 'PayPal' ) $payment_array['paypal']['default'] = true; 
			if(!$custom_default_payment &&  empty( $payment_array['paypal']['default']) && get_option('pp_invoice_payment_method' ) == 'paypal' ) { $payment_array['paypal']['default'] = true;}

		}

		if( $invoice_info->display('pp_invoice_cc_allow' ) == 'yes' ) { 
			$payment_array['cc']['name'] = 'cc'; 
			$payment_array['cc']['active'] = true; 
			$payment_array['cc']['nicename'] = "Credit Card"; 
			if( $custom_default_payment && $invoice_info->display('pp_invoice_payment_method' ) == 'cc' || $invoice_info->display('pp_invoice_payment_method' ) == 'Credit Card' ) $payment_array['cc']['default'] = true; 
			if(!$custom_default_payment && empty( $payment_array['cc']['default']) && get_option('pp_invoice_payment_method' ) == 'cc' ) $payment_array['cc']['default'] = true; 

		}

/*
		if( $invoice_info->display('pp_invoice_moneybookers_allow' ) == 'yes' ) { 
			$payment_array['moneybookers']['name'] = 'moneybookers'; 
			$payment_array['moneybookers']['active'] = true; 
			$payment_array['moneybookers']['nicename'] = "Moneybookers"; 
			if( $invoice_info->display('pp_invoice_payment_method' ) == 'moneybookers' ) $payment_array['moneybookers']['default'] = true; 
		}

		if( $invoice_info->display('pp_invoice_alertpay_allow' ) == 'yes' ) { 
			$payment_array['alertpay']['name'] = 'alertpay'; 
			$payment_array['alertpay']['active'] = true; 
			$payment_array['alertpay']['nicename'] = "AlertPay"; 
			if( $invoice_info->display('pp_invoice_payment_method' ) == 'alertpay' ) $payment_array['alertpay']['default'] = true; 
		}
*/

		return $payment_array;
	}
}

function pp_invoice_create_wp_user( $p) {

	$username = $p['pp_invoice_new_user_username'];
	if(!$username or pp_invoice_username_taken( $username )) {
		$username = pp_invoice_get_user_login_name();
	}   

	$userdata = array(
	 'user_pass' => wp_generate_password(),
	 'user_login' => $username,
	 'user_email' => $p['pp_invoice_new_user_email_address'],
	 'first_name' => $p['pp_invoice_first_name'],
	 'last_name' =>  $p['pp_invoice_last_name']);

	$wpuid = wp_insert_user( $userdata);

	return $wpuid;
}

function pp_invoice_username_taken( $username ) {
  $user = get_userdatabylogin( $username );
  return $user != false;
}

function pp_invoice_get_user_login_name() {
  return 'pp_invoice_'.rand(10000,100000);
}

function pp_invoice_email_variables( $invoice_id ) {
	global $pp_invoice_email_variables, $user_ID;

	$invoice_class = new pp_invoice_get( $invoice_id );
	$invoice_info = $invoice_class->data;

	$pp_invoice_email_variables = array(
		'business_name' => $invoice_info->payee_class->display_name,
		'recipient' => $invoice_info->payer_class->user_nicename,
  		'amount' => $invoice_info->display_amount,
 		'link' => $invoice_info->pay_link,
 		'business_email' => $invoice_info->payee_class->user_email,
		'subject' => $invoice_info->post_title,
		'description' => $invoice_info->post_content
	);

	return $pp_invoice_email_variables;
}

function pp_invoice_email_apply_variables( $matches) {
	global $pp_invoice_email_variables;

	if (isset( $pp_invoice_email_variables[$matches[2]])) {
		return $pp_invoice_email_variables[$matches[2]];
	}
	return $matches[2];
}

function pp_invoice_add_email_template_content() {

// Send invoice
		add_option('pp_invoice_email_send_invoice_subject','%subject%' );
		add_option('pp_invoice_email_send_invoice_content',
"Dear %recipient%, 

%business_name% has sent you an invoice in the amount of %amount% for:

%subject%

%description%

You may pay, view and print the invoice online by visiting the following link: 
%link%

Best regards,
%business_name% ( %business_email% )" );

		// Send reminder
		add_option('pp_invoice_email_send_reminder_subject','[Reminder] %subject%' );
		add_option('pp_invoice_email_send_reminder_content',
"Dear %recipient%, 

%business_name% has sent you a reminder for the invoice in the amount of %amount% for:

%subject%

%description%

You may pay, view and print the invoice online by visiting the following link: 
%link%.

Best regards,
%business_name% ( %business_email% )" );

		// Send receipt
		add_option('pp_invoice_email_send_receipt_subject','Receipt for %subject%' );
		add_option('pp_invoice_email_send_receipt_content',
"Dear %recipient%, 

%business_name% has received your payment for the invoice in the amount of %amount% for:

%subject%.

Thank you very much for your payment.

Best regards,
%business_name% ( %business_email% )" );

}	

class load_pp_invoice {

	var $invoice_id;

	function create_new( $user_id ) {
		global $currency;
		$profileuser = @get_user_to_edit( $user_id );

		// this is a new invoice, get defaults
		$this->client_change_payment_method = get_option('pp_invoice_client_change_payment_method' );
		$this->payment_method = get_option('pp_invoice_payment_method' );

		$this->paypal_allow = get_option('pp_invoice_paypal_allow' );
		$this->paypal_address = get_option('pp_invoice_paypal_address' );

		$this->googlecheckout_address = get_option('pp_invoice_googlecheckout_address' );

		$this->moneybookers_address = get_option('pp_invoice_moneybookers_address' );	
		$this->moneybookers_allow = get_option('pp_invoice_moneybookers_allow' );
		$this->moneybookers_secret = get_option('pp_invoice_moneybookers_secret' );
		$this->moneybookers_ip = get_option('pp_invoice_moneybookers_ip' );

		$this->cc_allow = get_option('pp_invoice_cc_allow' );		
		$this->gateway_username = get_option('pp_invoice_gateway_username' );
		$this->gateway_tran_key = get_option('pp_invoice_gateway_tran_key' );
		$this->gateway_url = get_option('pp_invoice_gateway_url' );
		$this->recurring_gateway_url = get_option('pp_invoice_recurring_gateway_url' );	
		$this->gateway_test_mode = get_option('pp_invoice_gateway_test_mode' );
		$this->gateway_delim_char = get_option('pp_invoice_gateway_delim_char' );
		$this->gateway_encap_char = get_option('pp_invoice_gateway_encap_char' );
		$this->gateway_merchant_email = get_option('pp_invoice_gateway_merchant_email' );
		$this->gateway_email_customer = get_option('pp_invoice_gateway_email_customer' );
		$this->gateway_MD5Hash = get_option('pp_invoice_gateway_MD5Hash' );	

		$this->alertpay_allow = get_option('pp_invoice_alertpay_allow' );
		$this->alertpay_secret = get_option('pp_invoice_alertpay_secret' );

		$this->currency_code = $currency;

		// create item rows 
		$this->itemized_array[1] = "";
		$this->itemized_array[2] = "";	

		// Check if new user is being created
		if( $user_id == 'create_new_user' ) {

				$this->create_new_user = true;

		} else {
			$profileuser = @get_user_to_edit( $user_id );

			if(!$profileuser->data->ID): 
				$this->user_deleted = true;
			else:
				$this->user_id = $user_id;
				$this->user_email = $profileuser->user_email;
				$this->first_name = $profileuser->first_name;
				$this->last_name = $profileuser->last_name;
				$this->streetaddress = $profileuser->streetaddress;
				$this->company_name = $profileuser->company_name;
				$this->city = $profileuser->city;
				$this->state = $profileuser->state;
				$this->zip = $profileuser->zip;
				$this->country = $profileuser->country;
			endif;	
		}

	}

	function create_from_template( $template_invoice_id, $user_id ) {
		global $wpdb;

 		$invoice_info = $wpdb->get_row("SELECT * FROM ".$wpdb->payments." WHERE id = '".$template_invoice_id."'" );
 		$this->invoice_id = rand(10000000, 90000000);
		$this->amount = $invoice_info->amount;
		$this->subject = $invoice_info->subject;
		$this->description = $invoice_info->description;
		$this->itemized = $invoice_info->itemized;
		$this->itemized_array = unserialize(urldecode( $this->itemized )); 

		// Verify user account still exists
		$profileuser = @get_user_to_edit( $user_id );

		if(!$profileuser->data->ID): 
			$this->user_deleted = true;
		else:
			$this->user_id = $user_id;
			$this->user_email = $profileuser->user_email;
			$this->first_name = $profileuser->first_name;
			$this->last_name = $profileuser->last_name;
			$this->streetaddress = $profileuser->streetaddress;
			$this->company_name = $profileuser->company_name;
			$this->city = $profileuser->city;
			$this->state = $profileuser->state;
			$this->zip = $profileuser->zip;
			$this->country = $profileuser->country;
		endif;		

		$this->pp_invoice_tax = pp_invoice_meta( $template_invoice_id,'pp_invoice_tax' );
		if( $this->pp_invoice_tax == '' ) $this->pp_invoice_tax = pp_invoice_meta( $template_invoice_id,'tax_value' );

		$this->currency_code = pp_invoice_meta( $template_invoice_id,'pp_invoice_currency_code' );
		$this->due_date_day = pp_invoice_meta( $template_invoice_id,'pp_invoice_due_date_day' );
		$this->due_date_month = pp_invoice_meta( $template_invoice_id,'pp_invoice_due_date_month' );
		$this->due_date_year = pp_invoice_meta( $template_invoice_id,'pp_invoice_due_date_year' );

		$this->subscription_name = pp_invoice_meta( $template_invoice_id,'pp_invoice_subscription_name' );
		$this->subscription_unit = pp_invoice_meta( $template_invoice_id,'pp_invoice_subscription_unit' );
		$this->subscription_length = pp_invoice_meta( $template_invoice_id,'pp_invoice_subscription_length' );
		$this->subscription_start_month = pp_invoice_meta( $template_invoice_id,'pp_invoice_subscription_start_month' );
		$this->subscription_start_day = pp_invoice_meta( $template_invoice_id,'pp_invoice_subscription_start_day' );
		$this->subscription_start_year = pp_invoice_meta( $template_invoice_id,'pp_invoice_subscription_start_year' );
		$this->subscription_total_occurances = pp_invoice_meta( $template_invoice_id,'pp_invoice_subscription_total_occurances' );

		$this->recurring_billing = pp_invoice_meta( $template_invoice_id,'pp_invoice_recurring_billing' );

		// Get billing information for invoice we are modifying
		$billing_information = new PP_Invoice_GetInfo( $template_invoice_id );

		$this->client_change_payment_method = $billing_information->display('pp_invoice_client_change_payment_method' );
		$this->payment_method = $billing_information->display('pp_invoice_payment_method' );

		$this->paypal_allow = $billing_information->display('pp_invoice_paypal_allow' ); 
		$this->paypal_address = $billing_information->display('pp_invoice_paypal_address' );

		$this->cc_allow = $billing_information->display('pp_invoice_cc_allow' );  
		$this->gateway_username = $billing_information->display('pp_invoice_gateway_username' );
		$this->gateway_tran_key = $billing_information->display('pp_invoice_gateway_tran_key' );
		$this->gateway_url = $billing_information->display('pp_invoice_gateway_url' );
		$this->recurring_gateway_url = $billing_information->display('pp_invoice_recurring_gateway_url' );

		$this->moneybookers_allow = $billing_information->display('pp_invoice_moneybookers_allow' ); 
		$this->moneybookers_secret = $billing_information->display('pp_invoice_moneybookers_secret' ); 
		$this->moneybookers_address = $billing_information->display('pp_invoice_moneybookers_address' );
		$this->moneybookers_ip = $billing_information->display('pp_invoice_moneybookers_ip' ); 

		$this->googlecheckout_address = $billing_information->display('pp_invoice_googlecheckout_address' );

		$this->alertpay_allow = $billing_information->display('pp_invoice_alertpay_allow' ); 
		$this->alertpay_address = $billing_information->display('pp_invoice_alertpay_address' );
		$this->alertpay_secret = $billing_information->display('pp_invoice_alertpay_secret' ); 

	}

	function load_existing( $invoice_id = false ) {
		global $wpdb;

		// If variable is passed, overwrite class variable
		if(isset( $invoice_id ))
			$this->invoice_id = $invoice_id;

		// set local variable to class variable
		$invoice_id = $this->invoice_id;

		$invoice_info = $wpdb->get_row("SELECT * FROM ".$wpdb->payments." WHERE id = '".$this->invoice_id."'" );

		$this->user_id = $invoice_info->user_id;
		$this->amount = $invoice_info->amount;
		$this->subject = $invoice_info->subject;
		$this->description = $invoice_info->description;
		$this->itemized = $invoice_info->itemized;
 		$this->itemized_array = unserialize(urldecode( $this->itemized )); 

		// Verify user account still exists
		$profileuser = @get_user_to_edit( $this->user_id );

		if(!$profileuser->data->ID): 
			$this->user_deleted = true;
		else:
			$this->user_id = $user_id;
			$this->user_email = $profileuser->user_email;
			$this->first_name = $profileuser->first_name;
			$this->last_name = $profileuser->last_name;
			$this->streetaddress = $profileuser->streetaddress;
			$this->company_name = $profileuser->company_name;
			$this->city = $profileuser->city;
			$this->state = $profileuser->state;
			$this->zip = $profileuser->zip;
			$this->country = $profileuser->country;
		endif;

		$this->tax = pp_invoice_meta( $invoice_id,'pp_invoice_tax' );
		if( $this->tax == '' ) $this->tax = pp_invoice_meta( $invoice_id,'tax_value' );

		$this->custom_invoice_id = pp_invoice_meta( $invoice_id,'pp_invoice_custom_invoice_id' );
		$this->due_date_day = pp_invoice_meta( $invoice_id,'pp_invoice_due_date_day' );
		$this->due_date_month = pp_invoice_meta( $invoice_id,'pp_invoice_due_date_month' );
		$this->due_date_year = pp_invoice_meta( $invoice_id,'pp_invoice_due_date_year' );
		$this->currency_code = pp_invoice_meta( $invoice_id,'pp_invoice_currency_code' );
		$this->recurring_billing = pp_invoice_meta( $invoice_id,'pp_invoice_recurring_billing' );

		$this->subscription_name = pp_invoice_meta( $invoice_id,'pp_invoice_subscription_name' );
		$this->subscription_unit = pp_invoice_meta( $invoice_id,'pp_invoice_subscription_unit' );
		$this->subscription_length = pp_invoice_meta( $invoice_id,'pp_invoice_subscription_length' );
		$this->subscription_start_month = pp_invoice_meta( $invoice_id,'pp_invoice_subscription_start_month' );
		$this->subscription_start_day = pp_invoice_meta( $invoice_id,'pp_invoice_subscription_start_day' );
		$this->subscription_start_year = pp_invoice_meta( $invoice_id,'pp_invoice_subscription_start_year' );
		$this->subscription_total_occurances = pp_invoice_meta( $invoice_id,'pp_invoice_subscription_total_occurances' );

		// Get billing information for invoice we are modifying
		$billing_information = new PP_Invoice_GetInfo( $invoice_id );

		$this->payment_method = $billing_information->display('pp_invoice_payment_method' );
		$this->client_change_payment_method = $billing_information->display('pp_invoice_client_change_payment_method' );

		$this->paypal_allow = $billing_information->display('pp_invoice_paypal_allow' ); 
		$this->paypal_address = $billing_information->display('pp_invoice_paypal_address' );

		$this->cc_allow = $billing_information->display('pp_invoice_cc_allow' );  
		$this->gateway_username = $billing_information->display('pp_invoice_gateway_username' );
		$this->gateway_tran_key = $billing_information->display('pp_invoice_gateway_tran_key' );
		$this->gateway_url = $billing_information->display('pp_invoice_gateway_url' );
		$this->recurring_gateway_url = $billing_information->display('pp_invoice_recurring_gateway_url' );

		$this->moneybookers_allow = $billing_information->display('pp_invoice_moneybookers_allow' ); 
		$this->moneybookers_secret = $billing_information->display('pp_invoice_moneybookers_secret' ); 
		$this->moneybookers_address = $billing_information->display('pp_invoice_moneybookers_address' );
		$this->moneybookers_ip = $billing_information->display('pp_invoice_moneybookers_ip' ); 

		$this->googlecheckout_address = $billing_information->display('pp_invoice_googlecheckout_address' );

		$this->alertpay_allow = $billing_information->display('pp_invoice_alertpay_allow' ); 
		$this->alertpay_address = $billing_information->display('pp_invoice_alertpay_address' );
		$this->alertpay_secret = $billing_information->display('pp_invoice_alertpay_secret' ); 	
	}

}

?>
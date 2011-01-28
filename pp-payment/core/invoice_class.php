<?php

/*
	Gets an invoice
*/
class pp_invoice_get {
	
	var $invoice_id; 
	var $data;
	var $error;

	//Load invoice variables
	function pp_invoice_get( $invoice_id) {
		global $wpdb, $user_ID, $currency, $currency_symbol;

		$this->invoice_id = $invoice_id;

		if(empty( $this->invoice_id))
			return false;
		
		$row_obj = $wpdb->get_row( "SELECT * FROM " . $wpdb->payments . "  WHERE id = '$invoice_id'" );

		foreach( $row_obj as $key => $value ) {		
			$this->data->$key = $value;
		}

		// Get meta
		$meta_obj = $wpdb->get_results( "SELECT meta_key, meta_value FROM " . $wpdb->paymentsmeta . "  WHERE invoice_id = '$invoice_id'" );
		foreach( $meta_obj as $meta_row ) {
			$meta_key = $meta_row->meta_key;
			$meta_value = $meta_row->meta_value;
			$this->data->$meta_key = $meta_value;
		}

		// Get user information
		$this->data->payer_class = get_userdata( $this->data->payer_id);
		$this->data->payee_class = get_userdata( $this->data->payee_id);
		
		// Get Post information
		$post_class = get_post( $this->data->post_id);

		if(count( $post_class ) > 0) {
			foreach( $post_class as $key => $value ) {
				$this->data->$key = $value;		
			}
		} else {
			$this->error[] = "Post with id of {$this->data->post_id} not found.";
			$this->data->post_title = "Error - post not found.";
		}

		// Determine if current user owes money or is owned
		$this->data->current_user_is = pp_invoice_user_has_permissions( $invoice_id);

		// Determine if invoice has been paid
		if( $this->data->status == 'paid')
			$this->data->is_paid = true;

		// Determine if invoice has been sent
		if(!empty( $this->data->sent_date ))
			$this->data->is_sent = true;

		// Determine if invoice is archived
		if( $this->data->archive_status == 'archived')
			$this->data->is_archived = true;

		// Load Invoice History
		if( $raw_history = $wpdb->get_results( "SELECT * FROM ".$wpdb->payments_log." WHERE invoice_id = '$invoice_id' ORDER BY time_stamp DESC" ))
			$this->data->log = $raw_history;
		else
			$this->data->log = false;
		
		// Load from default settings if not found in invoice setting
		$this->data->currency_code = ( empty( $this->data->currency_code ) ? $currency : $this->data->currency_code );		

		// Dynamic Variables
		$this->data->pay_link = admin_url( "admin.php?page=make_payment&invoice_id=$invoice_id" );
		$this->data->currency_symbol = $currency_symbol;
		$this->data->tax_free_amount = $this->data->amount;

		if(!empty( $this->data->tax)){
			$this->data->amount = $this->data->amount + ( $this->data->amount * ( $this->data->tax / 100 ) );
		}
		$this->data->display_amount = pp_money_format( $this->data->amount );

		return $this->data;		
	}
}
<?php
/*
Based on Web Invoicing and Billing Plugin
URL http://twincitiestech.com/services/wp-invoice/
*/

define("PP_INVOICE_UI_PATH", PP_PAYMENT_DIR . "/core/ui/");

require_once("core/functions.php");
require_once("core/display.php");
require_once("core/frontend.php");
require_once("core/invoice_class.php");

$PP_Invoice = new PP_Invoice();	

class PP_Invoice {

	var $Invoice;
	var $process_payment_cc;
	var $uri;
	var $the_path;
	var $frontend_path;

	function the_path() {
		$path =	PP_PAYMENT_URL."/".basename(dirname(__FILE__) );
		return $path;
	}

	function frontend_path() {
		$path =	PP_PAYMENT_URL;
		if(get_option( 'pp_invoice_force_https' ) == 'true' ) 
			$path = str_replace( 'http://','https://',$path);
		return $path;
	}

	function PP_Invoice() {
		global $user_ID, $pp_base_capability;

		$version = get_option( 'pp_invoice_version' );

		add_action( 'pp_activation', array( &$this, 'install' ) );

		$this->process_payment_cc = $pp_base_capability;
		$this->path = dirname(__FILE__);
		$this->file = basename(__FILE__);
		$this->directory = basename( $this->path);
		$this->uri = PP_PAYMENT_URL;
		$this->the_path = $this->the_path();

		$this->frontend_path = $this->frontend_path();

		add_action( 'wp_ajax_pp_invoice_process_cc_ajax', 'pp_invoice_process_cc_ajax' );

		add_action( 'init',  array( $this, 'init' ),0);

 		add_action( 'profile_update','pp_invoice_profile_update' );
		add_action( 'edit_user_profile', 'pp_invoice_user_profile_fields' );
		add_action( 'show_user_profile', 'pp_invoice_user_profile_fields' );
		add_action( 'admin_menu', array( $this, 'pp_invoice_add_pages' ) );
 		add_action( 'admin_init', array( $this, 'admin_init' ) );

		add_shortcode( 'pp-invoice-lookup', 'pp_invoice_lookup' );

		// Only run the content script if we are not using the replace_tag method.  We want to avoid running the function twice
		if(get_option( 'pp_invoice_where_to_display' ) != 'replace_tag' ) { add_filter( 'the_content', 'pp_invoice_the_content' );  } else { add_shortcode( 'pp-invoice', 'pp_invoice_the_content' ); 	}

		$this->SetUserAccess( $pp_base_capability );
	}

	function SetUserAccess( $capability = 'read') {
		$this->process_payment_cc = $capability;
	}

	function pp_invoice_add_pages() {
		global $_wp_last_object_menu, $pp_invoice_page_names, $screen_layout_columns;

		//necessary to insert the page link correctly into admin menu
		$_wp_last_object_menu++;

		// outgoing_invoices is currently sent to main
		$unsent_invoices = ( count( $this->unsent_invoices) > 0 ? "(" . count( $this->unsent_invoices) . ")" : "");
		$unpaid_invoices = ( count( $this->unpaid_invoices) > 0 ? "(" . count( $this->unpaid_invoices) . ")" : "");

		// Global Settings
		$pp_invoice_page_names[ 'global_settings' ] 	= add_submenu_page( 'Prospress',  __( 'Payment Settings', 'prospress' ),  __( 'Payment Settings', 'prospress' ), 'manage_options', 'invoice_settings', array( &$this,'settings_page' ) );

		// Invoice Pages
		$pp_invoice_page_names[ 'web_invoice' ] 		= add_menu_page( __( 'Payments', 'prospress' ), __( 'Payments', 'prospress' ), $this->process_payment_cc,'outgoing_invoices', array( &$this,'outgoing_invoices' ), $this->uri."/core/images/payments16.png", $_wp_last_object_menu );
		$pp_invoice_page_names[ 'outgoing_invoices' ] 	= add_submenu_page( 'outgoing_invoices', __( "Incoming Payments $unsent_invoices", 'prospress' ), __( "Incoming $unsent_invoices", 'prospress' ), $this->process_payment_cc, 'outgoing_invoices', array( &$this,'outgoing_invoices' ) );
		$pp_invoice_page_names[ 'incoming_invoices' ] 	= add_submenu_page( 'outgoing_invoices', __( "Outgoing Payments $unpaid_invoices", 'prospress' ), __( "Outgoing $unpaid_invoices", 'prospress' ), $this->process_payment_cc, 'incoming_invoices', array( &$this,'incoming_invoices' ) );
		$pp_invoice_page_names[ 'user_settings' ] 		= add_submenu_page( 'outgoing_invoices', __( 'Settings', 'prospress' ),  __( 'Settings', 'prospress' ), $this->process_payment_cc, 'user_settings_page', array( &$this,'user_settings_page' ) );

		$pp_invoice_page_names[ 'make_payment' ]		= add_submenu_page( 'hidden', __( 'View Invoice', 'prospress' ), __( 'View Invoice', 'prospress' ), $this->process_payment_cc, 'make_payment', array( &$this,'make_payment' ) );
		$pp_invoice_page_names[ 'send_invoice' ] 		= add_submenu_page( 'hidden', __( 'Send Invoice', 'prospress' ), __( 'Send Invoice', 'prospress' ), $this->process_payment_cc, 'send_invoice', array( &$this,'send_invoice' ) );
		$pp_invoice_page_names[ 'save_and_preview' ] 	= add_submenu_page( 'hidden', __( 'Save and Preview', 'prospress' ), __( 'Save and Preview', 'prospress' ), $this->process_payment_cc, 'save_and_preview', array( &$this,'save_and_preview' ) );

		foreach( $pp_invoice_page_names as $name => $menu) {
 			add_action("admin_print_scripts-$menu", array( $this, 'admin_print_scripts' ) );
		}

		//Make Payment Page Metaboxes
		add_meta_box( 'pp_invoice_metabox_invoice_details', __( 'Invoice Details','prospress' ), 'pp_invoice_metabox_invoice_details', 'admin_page_make_payment', 'normal', 'high' );
		add_meta_box( 'pp_invoice_metabox_billing_details', __( 'Billing Details','prospress' ), 'pp_invoice_metabox_billing_details', 'admin_page_make_payment', 'normal', 'high' );
		add_meta_box( 'pp_invoice_metabox_payee_details', __( 'Payment Recipient','prospress' ), 'pp_invoice_metabox_payee_details','admin_page_make_payment', 'side', 'default' );

		//Send Payment Page Metaboxes
		add_meta_box( 'pp_invoice_metabox_history', __( 'Invoice History','prospress' ), 'pp_invoice_metabox_history','admin_page_send_invoice', 'normal', 'default' );
		add_meta_box( 'pp_invoice_metabox_invoice_details', __( 'Invoice Details','prospress' ), 'pp_invoice_metabox_invoice_details','admin_page_send_invoice', 'normal', 'default' );
  		add_meta_box( 'pp_invoice_metabox_payer_details', __( 'Recipient','prospress' ), 'pp_invoice_metabox_payer_details','admin_page_send_invoice', 'side', 'default' );

		add_filter( 'screen_layout_columns', array( &$this, 'on_screen_layout_columns' ), 10, 2);		

		register_column_headers("web-invoice_page_incoming_invoices", array(
			'cb' => '<input type="checkbox" />',
			'subject' => __( 'Subject', 'prospress' ),
			'balance' => __( 'Amount', 'prospress' ),
			'display_name' => __( 'Recipient', 'prospress' ),
			'user_email' => __( 'User Email', 'prospress' ),
			'status' => __( 'Status', 'prospress' ),
			'date_sent' => __( 'Invoice Received', 'prospress' ),
			'due_date' => __( 'Payment Due', 'prospress' ),
		) );

		register_column_headers("toplevel_page_outgoing_invoices", array(
			'cb' => '<input type="checkbox" />',
			'subject' => __( 'Subject', 'prospress' ),
			'balance' => __( 'Amount', 'prospress' ),
			'display_name' => __( 'From', 'prospress' ),
			'user_email' => __( 'User Email', 'prospress' ),
			'status' => __( 'Status', 'prospress' ),
			'date_sent' => __( 'Invoice Sent', 'prospress' ),
			'due_date' => __( 'Payment Due', 'prospress' ),
		) );		
 	}

/*
	Add columns to invoice editing page
*/
	function on_screen_layout_columns( $columns, $screen) {
		global $pp_invoice_page_names;

			$columns[ $pp_invoice_page_names[ 'make_payment' ] ] = '2';

		return $columns;
	}	

	function admin_print_scripts() {
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );

		wp_enqueue_script( 'jquery.cookie',$this->uri."/core/js/jquery.cookie.js", array( 'jquery' ) );
		wp_enqueue_script( 'jquery.livequery',$this->uri."/core/js/jquery.livequery.js", array( 'jquery' ) );
		wp_enqueue_script( 'jquery.formatCurrency',$this->uri."/core/js/jquery.formatCurrency.js", array( 'jquery' ) );
		wp_enqueue_script( 'jquery.idTabs',$this->uri."/core/js/jquery.idTabs.min.js", array( 'jquery' ) );
		wp_enqueue_script( 'jquery.impromptu',$this->uri."/core/js/jquery-impromptu.1.7.js", array( 'jquery' ) );
		wp_enqueue_script( 'jquery.field',$this->uri."/core/js/jquery.field.min.js", array( 'jquery' ) );
		wp_enqueue_script( 'jquery.calculation',$this->uri."/core/js/jquery.calculation.min.js", array( 'jquery' ) );
		wp_enqueue_script( 'jquery.tablesorter',$this->uri."/core/js/jquery.tablesorter.min.js", array( 'jquery' ) );
		wp_enqueue_script( 'jquery.autogrow-textarea',$this->uri."/core/js/jquery.autogrow-textarea.js", array( 'jquery' ) );
		wp_enqueue_script( 'pp-invoice',$this->uri."/core/js/pp-invoice-2.0.js", array( 'jquery' ) );		

   		wp_enqueue_style( 'pp_invoice_css', $this->uri . "/core/css/pp-admin-2.0.css");
		wp_print_styles();

		?>

	<?php
	}

	function send_invoice() {
		global $user_ID, $wpdb, $page_now, $pp_invoice_page_names, $screen_layout_columns;
		echo $page_now;
		$invoice_id = $_REQUEST[ 'invoice_id' ];
		$has_invoice_permissions = pp_invoice_user_has_permissions( $invoice_id, $user_id);

		if( $has_invoice_permissions ) {
			$invoice_class = new pp_invoice_get( $invoice_id);
			$errors = $invoice_class->error;
			$invoice = $invoice_class->data;

			// Get invoice reporting information
			if( $invoice->is_paid) {
				$paid_data = $wpdb->get_row("SELECT value, time_stamp FROM  ".$wpdb->payments_log." WHERE action_type = 'paid' AND invoice_id = '".$invoice_id."' ORDER BY time_stamp DESC LIMIT 0, 1");			
				$paid_date = date_i18n(get_option( 'date_format' ).' '.get_option( 'time_format' ), strtotime( $paid_data->time_stamp) );
			}

			if(!$invoice->is_paid && $has_invoice_permissions == 'payer' )
				$messages[] = __( 'You have not yet paid this invoice.', 'prospress' );

			if(!$invoice->is_paid && $has_invoice_permissions == 'payee' )
				$messages[] = sprintf( __( '%s has not paid this invoice.', 'prospress' ), $invoice->payer_class->user_nicename );

			if( $invoice->is_paid)
				$messages[] = $paid_data->value . ' ' . $paid_date;

			// UI Modifications
			// Remove payment metabox if current user is not the payer, or if the invoice has already been paid
			if( $has_invoice_permissions != 'payer' || $invoice->is_paid) {
				remove_meta_box( 'pp_invoice_metabox_billing_details', $pp_invoice_page_names[ 'make_payment' ], 'normal' );
 			}

			include PP_INVOICE_UI_PATH . 'metaboxes/send_invoice.php';	

			$page_title = ( $invoice->is_paid ) ? __( 'Invoice Details', 'prospress' ) : $page_title = __( 'Send Invoice', 'prospress' );

			include PP_INVOICE_UI_PATH . 'send_invoice.php';

		} else {
			pp_invoice_backend_wrap( "Error", __( 'You are not allowed to view this invoice.', 'prospress' ) );
		}
	}

	function make_payment() {
		global $user_ID, $wpdb, $page_now, $pp_invoice_page_names, $screen_layout_columns;
		
		$invoice_id = $_REQUEST[ 'invoice_id' ];
		$has_invoice_permissions = pp_invoice_user_has_permissions( $invoice_id, $user_id);

		if( $has_invoice_permissions) {

			// Invoice Update Actions:

			// Draft Message
			if (wp_verify_nonce( $_REQUEST[ 'pp_invoice_process_cc' ], 'pp_invoice_process_cc_' . $invoice_id) ) {
				$draft_message = nl2br( $_REQUEST[ 'draft_message' ]);
				pp_invoice_update_status( $invoice_id, 'paid' );
				pp_invoice_update_log( $invoice_id,'paid', sprintf( __( "Invoice paid via bank transfer. Message from payer: %s", 'prospress' ), $draft_message ) );
			}

			// PayPal return
			if( $_REQUEST[ 'return_info' ] == 'cancel' ) {
				$errors[] = __( "Your PayPal payment has not been processed.", 'prospress' );
			} elseif( $_REQUEST[ 'return_info' ] == 'success' ) {
				pp_invoice_update_status( $invoice_id, 'paid' );
				pp_invoice_update_log( $invoice_id, 'paid', __( 'Invoice paid via PayPal.', 'prospress' ) );
 			}

			// Load invoice
			$invoice_class = new pp_invoice_get( $invoice_id);
			$invoice_errors = $invoice_class->error;
			$invoice = $invoice_class->data;

			// Get invoice reporting information
			if( $invoice->is_paid) {
				$paid_data = $wpdb->get_row( "SELECT value, time_stamp FROM  " . $wpdb->payments_log . " WHERE action_type = 'paid' AND invoice_id = '" . $invoice_id . "' ORDER BY time_stamp DESC LIMIT 0, 1" );			
				$paid_date = date_i18n(get_option( 'date_format' ).' '.get_option( 'time_format' ), strtotime( $paid_data->time_stamp) );
			}

			if(!$invoice->is_paid && $has_invoice_permissions == 'payer' )
				$messages[] = __( 'You have not paid this invoice.', 'prospress' );

			if(!$invoice->is_paid && $has_invoice_permissions == 'payee' )
				$messages[] = sprintf( __( "%s has not paid this invoice.", 'prospress' ), $invoice->payer_class->user_nicename );

			if( $invoice->is_paid){
				$messages[] = $paid_data->value;				
				$messages[] = sprintf( __( "Payment processed on %s.", 'prospress' ), $paid_date );
			}

			// UI Modifications
			// Remove payment metabox if current user is not the payer, or if the invoice has already been paid
			if( $has_invoice_permissions != 'payer' || $invoice->is_paid) {
				remove_meta_box( 'pp_invoice_metabox_billing_details', $pp_invoice_page_names[ 'make_payment' ], 'normal' );
 			}

			include PP_INVOICE_UI_PATH . 'metaboxes/make_payment.php';			
			include PP_INVOICE_UI_PATH . 'make_payment.php';		
		} else {
			pp_invoice_backend_wrap( "Error", __( 'You are not allowed to view this invoice.', 'prospress' ) );
		}
	}

	function save_and_preview() {

	global $user_ID, $wpdb,$pp_invoice_email_variables;
		echo $page_now;
		$invoice_id = $_REQUEST[ 'invoice_id' ];
		$has_invoice_permissions = pp_invoice_user_has_permissions( $invoice_id, $user_id);

		if( $has_invoice_permissions) {

			// Update invoice settings that can be modified at invoice management page
			if( is_array( $_REQUEST[ 'pp_invoice' ] ) ) {
				$nonce = $_REQUEST[ 'pp_invoice_update_single' ];
			 	if (!wp_verify_nonce( $nonce, 'pp_invoice_update_single_' . $invoice_id) ) die( 'Security check' );

				foreach( $_REQUEST[ 'pp_invoice' ] as $updated_item_key => $updated_item_value)
					pp_invoice_update_invoice_meta( $invoice_id, $updated_item_key, $updated_item_value);
			}

			$invoice_class = new pp_invoice_get( $invoice_id);
			$errors = $invoice_class->error;
			$invoice = $invoice_class->data;

			$pp_invoice_email_variables = pp_invoice_email_variables( $invoice_id);

			include PP_INVOICE_UI_PATH . 'save_and_preview.php';
		} else {
			pp_invoice_backend_wrap( "Error", __( 'You are not allowed to view this invoice.', 'prospress' ) );
		}

	}

	function incoming_invoices() {
 		global $wpdb, $user_ID, $pp_invoice_page_names;

		// Bulk options
		if( isset( $_REQUEST[ 'pp_invoice_action' ] ) ) {
			$action = $_REQUEST[ 'pp_invoice_action' ];
			$invoice_array = $_REQUEST[ 'multiple_invoices' ];

			switch( $action) {

				case 'archive_invoice':				
					$message[] = pp_invoice_archive( $invoice_array );
				break;		

				case 'unrachive_invoice':
					$message[] = pp_invoice_unarchive( $invoice_array );
				break;		

			}
		}

		$incoming_invoices = $wpdb->get_col( "SELECT id FROM ".$wpdb->payments." WHERE payer_id = '$user_ID'" );

		include PP_INVOICE_UI_PATH . 'incoming_invoices.php';
 	}

	function outgoing_invoices() {		

		$needs_to_setup_billing = pp_invoice_user_settings( 'all' );

		// Bulk options
		if( isset( $_REQUEST[ 'pp_invoice_action' ] ) ) {
			$action = $_REQUEST[ 'pp_invoice_action' ];
			$invoice_array = $_REQUEST[ 'multiple_invoices' ];

			switch( $action ) {
				case 'archive_invoice':				
					$message[] = pp_invoice_archive( $invoice_array );
					break;

				case 'unrachive_invoice':
					$message[] = pp_invoice_unarchive( $invoice_array );
					break;

				case 'mark_as_sent':
					$message[] = pp_invoice_mark_as_sent( $invoice_array );
					break;

				case 'mark_as_paid':
					$message[] = pp_invoice_mark_as_paid( $invoice_array );
					break;

				case 'mark_as_unpaid':
					$message[] = pp_invoice_mark_as_unpaid( $invoice_array );
					break;
			}
		}

		if( $_REQUEST[ 'action' ] == 'post_save_and_preview' ) {
			$invoice_id = $_REQUEST[ 'invoice_id' ];
 			if( $_REQUEST[ 'pp_invoice_action' ] == 'Email to Client' ) {
				pp_invoice_update_invoice_meta( $invoice_id, 'email_payment_request', $_REQUEST[ 'pp_invoice_payment_request' ][ 'email_message_content' ]);
				$message = pp_send_single_invoice( $invoice_id);
			}			

			if( $_REQUEST[ 'pp_invoice_action' ] == 'Save for Later' ) {			
				// Do nothing, invoice was already saved by visiting the save_and_preview page
			}
		}

		global $wpdb, $user_ID, $pp_invoice_page_names;
		$outgoing_invoices = $wpdb->get_col("SELECT id FROM ".$wpdb->payments." WHERE payee_id = '$user_ID'");
		include PP_INVOICE_UI_PATH . 'outgoing_invoices.php';
	}

	function user_settings_page() {
		global $user_ID;

		$user_settings = pp_invoice_user_settings( 'all', $user_ID);

		// Save settings
		if(count( $_REQUEST[pp_invoice_user_settings]) > 1) {
			$user_settings = $_REQUEST[pp_invoice_user_settings];
			update_usermeta( $user_ID, 'pp_invoice_settings', $user_settings);		

		} else {			

			if(!$user_settings) {
				$user_settings = pp_invoice_load_default_user_settings( $user_ID);
				}
		}

		// The pp_invoice_user_settings() needs to be ran, it converts certain text values into bool values
		$user_settings = pp_invoice_user_settings( 'all', $user_ID);
		include PP_INVOICE_UI_PATH . 'user_settings_page.php';	
	}

	function settings_page() {
		global $wpdb;

		if(!empty( $_REQUEST[ 'pp_invoice_custom_label_tax' ] ) )
			update_option( 'pp_invoice_custom_label_tax', $_REQUEST[ 'pp_invoice_custom_label_tax' ]);

		if( empty( $_REQUEST[ 'pp_invoice_using_godaddy' ] ) )
			$_REQUEST[ 'pp_invoice_using_godaddy' ] = 'false';
		update_option( 'pp_invoice_using_godaddy', $_REQUEST[ 'pp_invoice_using_godaddy' ] );

		if( empty( $_REQUEST[ 'pp_invoice_force_https' ] ) )
			$_REQUEST[ 'pp_invoice_force_https' ] = 'false';
		update_option( 'pp_invoice_force_https', $_REQUEST[ 'pp_invoice_force_https' ] );

		if(!empty( $_REQUEST[ 'pp_invoice_email_send_invoice_subject' ] ) )
			update_option( 'pp_invoice_email_send_invoice_subject', $_REQUEST[ 'pp_invoice_email_send_invoice_subject' ]);
		if(!empty( $_REQUEST[ 'pp_invoice_email_send_invoice_content' ] ) )
			update_option( 'pp_invoice_email_send_invoice_content', $_REQUEST[ 'pp_invoice_email_send_invoice_content' ]);

		if(!empty( $_REQUEST[ 'pp_invoice_email_send_reminder_subject' ] ) )
			update_option( 'pp_invoice_email_send_reminder_subject', $_REQUEST[ 'pp_invoice_email_send_reminder_subject' ]);
		if(!empty( $_REQUEST[ 'pp_invoice_email_send_reminder_content' ] ) )
			update_option( 'pp_invoice_email_send_reminder_content', $_REQUEST[ 'pp_invoice_email_send_reminder_content' ]);

		if(!empty( $_REQUEST[ 'pp_invoice_email_send_receipt_subject' ] ) )
			update_option( 'pp_invoice_email_send_receipt_subject', $_REQUEST[ 'pp_invoice_email_send_receipt_subject' ]);
		if(!empty( $_REQUEST[ 'pp_invoice_email_send_receipt_content' ] ) )
			update_option( 'pp_invoice_email_send_receipt_content', $_REQUEST[ 'pp_invoice_email_send_receipt_content' ]);

		if(!$wpdb->query("SHOW TABLES LIKE '".$wpdb->paymentsmeta."';") || !$wpdb->query("SHOW TABLES LIKE '".$wpdb->payments."';") || !$wpdb->query("SHOW TABLES LIKE '".$wpdb->payments_log."';") ) { $warning_message = "The plugin database tables are gone, deactivate and reactivate plugin to re-create them."; }if( $warning_message) echo "<div id=\"message\" class='error' ><p>$warning_message</p></div>";

		include PP_INVOICE_UI_PATH . 'settings_page.php';
	}

	function admin_init() {

		// Admin Redirections. Has to go here to load before headers
		if( $_REQUEST[ 'pp_invoice_action' ] == __( 'Continue Editing', 'prospress' ) ) {
			wp_redirect( admin_url( "admin.php?page=new_invoice&pp_invoice_action=doInvoice&invoice_id={$_REQUEST[ 'invoice_id' ]}" ) );
			die();
		}

	}


	function init() {
		global $wpdb, $wp_version, $user_ID;

		if( isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] == 'make_payment' && get_option( 'pp_invoice_force_https' ) == 'true' && !is_ssl() ){
			$redirect = admin_url( 'admin.php', 'https' );
			$redirect = add_query_arg( $_GET, $redirect );
			wp_safe_redirect( $redirect );
			exit();
		}

		// Load default user settings if none exist
		if(!get_usermeta( $user_ID, 'pp_invoice_settings' ) ) {
			pp_invoice_load_default_user_settings( $user_ID);
		}

		// Load these variables early
		$this->incoming_invoices = $wpdb->get_col("SELECT id FROM ".$wpdb->payments." WHERE payer_id = '$user_ID'");
		$this->outgoing_invoices = $wpdb->get_col("SELECT id FROM ".$wpdb->payments." WHERE payee_id = '$user_ID'");

		foreach( $this->incoming_invoices as $incoming_id) {
			$invoice_class = new pp_invoice_get( $incoming_id);

			// Don't include archived invoices in the counts
			if( $invoice_class->data->is_archived)
				continue;

			// Don't include paid invocies either
			if( $invoice_class->data->is_paid)
				continue;

			if(!$invoice_class->data->is_paid)
				$this->unpaid_invoices[$incoming_id] = true;
		}

		foreach( $this->outgoing_invoices as $outgoing_id) {

			// Don't add this invoice to unset array if it was just sent
			if( $_REQUEST[ 'pp_invoice_action' ] == 'Email to Client' && $_REQUEST[ 'invoice_id' ] == $outgoing_id)
				continue;

			$invoice_class = new pp_invoice_get( $outgoing_id);			

			// Don't include archived invoices in the counts
			if( $invoice_class->data->is_archived)
				continue;

			// Don't include paid invocies either
			if( $invoice_class->data->is_paid)
				continue;

			if(!$invoice_class->data->is_sent) 
				$this->unsent_invoices[$outgoing_id] = true;

		}

			// Make sure proper MD5 is being passed (32 chars), and strip of everything but numbers and letters
			if( isset( $_GET[ 'invoice_id' ]) && strlen( $_GET[ 'invoice_id' ]) != 32) unset( $_GET[ 'invoice_id' ]); 
			$_GET[ 'invoice_id' ] = preg_replace( '/[^A-Za-z0-9-]/', '', $_GET[ 'invoice_id' ]);

			if(!empty( $_GET[ 'invoice_id' ] ) ) {

				$md5_invoice_id = $_GET[ 'invoice_id' ];

				// Convert MD5 hash into Actual Invoice ID
				$all_invoices = $wpdb->get_col("SELECT id FROM ".$wpdb->payments." ");
				foreach ( $all_invoices as $value) { if(md5( $value) == $md5_invoice_id) {$invoice_id = $value;} }		

				//Check if invoice exists, SSL enforcement is setp, and we are not currently browing HTTPS,  then reload page into HTTPS 
				if(!function_exists( 'wp_https_redirect' ) ) {
					if(pp_invoice_does_invoice_exist( $invoice_id) && get_option( 'pp_invoice_force_https' ) == 'true' && $_SERVER[ 'HTTPS' ] != "on") {  header("Location: https://" . $_SERVER[ 'SERVER_NAME' ] . $_SERVER[ 'REQUEST_URI' ]); exit;}
				}

			}

			if( isset( $_POST[ 'pp_invoice_id_hash' ] ) ) {

				$md5_invoice_id = $_POST[ 'pp_invoice_id_hash' ];

				// Convert MD5 hash into Actual Invoice ID
				$all_invoices = $wpdb->get_col("SELECT id FROM ".$wpdb->payments." ");
				foreach ( $all_invoices as $value) { if(md5( $value) == $md5_invoice_id) {$invoice_id = $value;} }

				//Check to see if this is a credit card transaction, if so process
				if(pp_invoice_does_invoice_exist( $invoice_id) ) { pp_invoice_process_cc_transaction( $_POST); exit; }
				}				

		if(empty( $_GET[ 'invoice_id' ] ) ) unset( $_GET[ 'invoice_id' ]);
		}

		function install() {
			global $wpdb;

			$current_db_version = get_option( 'PP_PAYMENTS_DB_VERSION' );

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

			if ( !empty( $wpdb->charset ) )
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";

			if( $wpdb->get_var("SHOW TABLES LIKE '". $wpdb->payments ."'") != $wpdb->payments || $current_db_version < PP_PAYMENTS_DB_VERSION ) {
				$sql_main = "CREATE TABLE $wpdb->payments (
						id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
						post_id bigint(20) NOT NULL,
						payer_id bigint(20) NOT NULL,
						payee_id bigint(20) NOT NULL,
						amount float(16,6) default '0',
						status varchar(20) NOT NULL,
						type varchar(255) NOT NULL,
						blog_id int(11) NOT NULL,
				    	KEY post_id (post_id),
				    	KEY payer_id (payer_id),
			    		KEY payee_id (payee_id)
						) {$charset_collate};";
				dbDelta( $sql_main);
			}

			if( $wpdb->get_var("SHOW TABLES LIKE '". $wpdb->paymentsmeta ."'") != $wpdb->paymentsmeta || $current_db_version < PP_PAYMENTS_DB_VERSION ) {
				$sql_meta= "CREATE TABLE $wpdb->paymentsmeta (
					meta_id bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
					invoice_id bigint(20) NOT NULL default '0',
					meta_key varchar(255) default NULL,
					meta_value longtext,
		    		KEY invoice_id ( invoice_id)
					) {$charset_collate};";
				dbDelta( $sql_meta);
			}

			if( $wpdb->get_var("SHOW TABLES LIKE '". $wpdb->payments_log ."'") != $wpdb->payments_log || $current_db_version < PP_PAYMENTS_DB_VERSION ) {
				$sql_log = "CREATE TABLE $wpdb->payments_log (
					id bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
					invoice_id int(11) NOT NULL default '0',
					action_type varchar(255) NOT NULL,
					value longtext NOT NULL,
					time_stamp timestamp NOT NULL,
		    		KEY invoice_id ( invoice_id)
					) {$charset_collate};";
				dbDelta( $sql_log);
			}

			update_option( 'PP_PAYMENTS_DB_VERSION', PP_PAYMENTS_DB_VERSION );

			// Localization Labels
			add_option( 'pp_invoice_custom_label_tax', "Tax");
			add_option( 'pp_invoice_force_https','true' );

			pp_invoice_add_email_template_content();
	}
}

global $_pp_invoice_getinfo;

class PP_Invoice_GetInfo {
	var $id;
	var $_row_cache;

	function __construct( $invoice_id) {
		global $_pp_invoice_getinfo, $wpdb;

		$this->id = $invoice_id;
	
		if ( isset( $_pp_invoice_getinfo[$this->id]) && $_pp_invoice_getinfo[$this->id]) {
			$this->_row_cache = $_pp_invoice_getinfo[$this->id];
		}

		if (!$this->_row_cache) {
			$this->_setRowCache( $wpdb->get_row("SELECT * FROM " . $wpdb->payments . " WHERE id = '{$this->id}'") );
		}
		}

	function _setRowCache( $row) {
		global $_pp_invoice_getinfo;

		if (!$row) {
			$this->id = null;
			return;
		}

		$this->_row_cache = $row;
		$_pp_invoice_getinfo[$this->id] = $this->_row_cache;
	}

	function recipient( $what) {
		global $wpdb;
		
		if (!$this->_row_cache) {
			$this->_setRowCache( $wpdb->get_row("SELECT * FROM " . $wpdb->payments . " WHERE id = '{$this->id}'") );
		}

		if ( $this->_row_cache) {
			$uid = $this->_row_cache->user_id;
			$user_email = $wpdb->get_var("SELECT user_email FROM " . $wpdb->prefix . "users WHERE id=".$uid);
		} else {
			$uid = false;
			$user_email = false;
		}

		$invoice_info = $this->_row_cache;
		
		switch ( $what) {
			case 'callsign':
				$first_name = $this->recipient( 'first_name' );
				$last_name = $this->recipient( 'last_name' );
				$company_name = $this->recipient( 'company_name' );

				if(!empty( $company_name) && empty( $first_name) || empty( $last_name) ) return $company_name; 
				if(empty ( $company_name) && empty( $first_name) || empty( $last_name) ) return $user_email; 

				return $first_name . " " . $last_name;
			break;
			
			case 'user_id':
				return $uid;
			break;	
			
			case 'email_address':
					return $user_email;
			break;

			case 'first_name':
				return get_usermeta( $uid,'first_name' );
			break;
			
			case 'last_name':
				return get_usermeta( $uid,'last_name' );
			break;
			
			case 'company_name':
				return get_usermeta( $uid,'company_name' );
			break;
			
			case 'phonenumber':
				return pp_invoice_format_phone(get_usermeta( $uid,'phonenumber' ) );
			break;
			
			case 'paypal_phonenumber':
				return get_usermeta( $uid,'phonenumber' );
			break;
			
			case 'streetaddress':
				return get_usermeta( $uid,'streetaddress' );	
			break;
			
			case 'state':
				return strtoupper(get_usermeta( $uid,'state' ) );
			break;
			
			case 'city':
				return get_usermeta( $uid,'city' );
			break;
			
			case 'zip':
				return get_usermeta( $uid,'zip' );
			break;
			
			case 'country':
				if(get_usermeta( $uid,'country' ) ) return get_usermeta( $uid,'country' );  else  return "US";
			break;	
		}
		
	}
	
	function display( $what) {
		global $wpdb;	
		
		if (!$this->_row_cache) {
			$this->_setRowCache( $wpdb->get_row("SELECT * FROM " . $wpdb->payments . " WHERE id = '{$this->id}'") );
		}

		$invoice_info = $this->_row_cache ;		

		switch ( $what) {
			case 'pp_invoice_payment_method':
				if(pp_invoice_meta( $this->id,'pp_invoice_payment_method' ) ) return pp_invoice_meta( $this->id,'pp_invoice_payment_method' );
				return get_option( 'pp_invoice_payment_method' );	
			break;

			case 'pp_invoice_client_change_payment_method':
				if(pp_invoice_meta( $this->id,'pp_invoice_client_change_payment_method' ) ) return pp_invoice_meta( $this->id,'pp_invoice_client_change_payment_method' );
				return get_option( 'pp_invoice_client_change_payment_method' );	
			break;

			case 'pp_invoice_paypal_allow':
				if(pp_invoice_meta( $this->id,'pp_invoice_paypal_allow' ) == 'yes' ) return  'yes';
				if(pp_invoice_meta( $this->id,'pp_invoice_paypal_allow' ) == 'no' ) return 'no';
				if(get_option( 'pp_invoice_paypal_allow' ) == 'yes' ) return  'yes';
				if(get_option( 'pp_invoice_paypal_allow' ) == 'no' ) return 'no';
				return false;
			break;	

			case 'pp_invoice_paypal_address':
				if(pp_invoice_meta( $this->id,'pp_invoice_paypal_address' ) ) return pp_invoice_meta( $this->id,'pp_invoice_paypal_address' );
				if(get_option( 'pp_invoice_paypal_address' ) != '' ) return get_option( 'pp_invoice_paypal_address' );	
				return false;
			break;

			case 'pp_invoice_cc_allow':
				if(pp_invoice_meta( $this->id,'pp_invoice_cc_allow' ) == 'yes' ) return  'yes';
				if(pp_invoice_meta( $this->id,'pp_invoice_cc_allow' ) == 'no' ) return 'no';
				if(get_option( 'pp_invoice_cc_allow' ) == 'yes' ) return  'yes';
				if(get_option( 'pp_invoice_cc_allow' ) == 'no' ) return 'no';
				return false;

			break;	

			case 'pp_invoice_gateway_username':
				if(pp_invoice_meta( $this->id,'pp_invoice_gateway_username' ) ) return pp_invoice_meta( $this->id,'pp_invoice_gateway_username' );
				if(get_option( 'pp_invoice_gateway_username' ) != '' ) return get_option( 'pp_invoice_gateway_username' );	
				return false;	
			break;

			case 'pp_invoice_is_merchant':
				if(pp_invoice_meta( $this->id,'pp_invoice_gateway_tran_key' ) && pp_invoice_meta( $this->id,'pp_invoice_gateway_username' ) ) return true;
				if(get_option( 'pp_invoice_gateway_username' ) == '' || get_option( 'pp_invoice_gateway_tran_key' ) == '' ) return true;
			break;

			case 'pp_invoice_gateway_tran_key':
				if(pp_invoice_meta( $this->id,'pp_invoice_gateway_tran_key' ) ) return pp_invoice_meta( $this->id,'pp_invoice_gateway_tran_key' );
				return get_option( 'pp_invoice_gateway_tran_key' );		
			break;

			case 'pp_invoice_gateway_url':
				if(pp_invoice_meta( $this->id,'pp_invoice_gateway_url' ) ) return pp_invoice_meta( $this->id,'pp_invoice_gateway_url' );
				// if no custom paypal address is set, use default
				return get_option( 'pp_invoice_gateway_url' );		
			break;

			case 'pp_invoice_recurring_gateway_url':
				if(pp_invoice_meta( $this->id,'pp_invoice_recurring_gateway_url' ) ) return pp_invoice_meta( $this->id,'pp_invoice_recurring_gateway_url' );
				// if no custom paypal address is set, use default
				return get_option( 'pp_invoice_recurring_gateway_url' );		
			break;

			case 'pp_invoice_moneybookers_allow':
				if(pp_invoice_meta( $this->id,'pp_invoice_moneybookers_allow' ) == 'yes' ) return  'yes';
				if(pp_invoice_meta( $this->id,'pp_invoice_moneybookers_allow' ) == 'no' ) return 'no';
				if(get_option( 'pp_invoice_moneybookers_allow' ) == 'yes' ) return  'yes';
				if(get_option( 'pp_invoice_moneybookers_allow' ) == 'no' ) return 'no';
				return false;

			break;	

			case 'pp_invoice_moneybookers_ip':
				if(pp_invoice_meta( $this->id,'pp_invoice_moneybookers_ip' ) ) return pp_invoice_meta( $this->id,'pp_invoice_moneybookers_ip' );	
				return false;
			break;	

			case 'pp_invoice_moneybookers_secret':
				if(pp_invoice_meta( $this->id,'pp_invoice_moneybookers_secret' ) ) return pp_invoice_meta( $this->id,'pp_invoice_moneybookers_secret' );	
				return false;
			break;	

			case 'pp_invoice_moneybookers_address':
				if(pp_invoice_meta( $this->id,'pp_invoice_moneybookers_address' ) ) return pp_invoice_meta( $this->id,'pp_invoice_moneybookers_address' );
				if(get_option( 'pp_invoice_moneybookers_address' ) != '' ) return get_option( 'pp_invoice_moneybookers_address' );	
				return false;		
			break;	
	
			case 'pp_invoice_alertpay_allow':
				if(pp_invoice_meta( $this->id,'pp_invoice_alertpay_allow' ) == 'yes' ) return 'yes';
				if(pp_invoice_meta( $this->id,'pp_invoice_alertpay_allow' ) == 'no' ) return 'no';
				if(get_option( 'pp_invoice_alertpay_allow' ) == 'yes' ) return  'yes';
				if(get_option( 'pp_invoice_alertpay_allow' ) == 'no' ) return  'no';
				return false;
			break;	

			case 'pp_invoice_alertpay_address':
				if(pp_invoice_meta( $this->id,'pp_invoice_alertpay_address' ) ) return pp_invoice_meta( $this->id,'pp_invoice_alertpay_address' );	
				return false;
			break;		

			case 'pp_invoice_alertpay_secret':
				if(pp_invoice_meta( $this->id,'pp_invoice_alertpay_secret' ) ) return pp_invoice_meta( $this->id,'pp_invoice_alertpay_secret' );	
				return false;
			break;	

			case 'pp_invoice_googlecheckout_address':
				if(pp_invoice_meta( $this->id,'pp_invoice_googlecheckout_address' ) ) return pp_invoice_meta( $this->id,'pp_invoice_googlecheckout_address' );
				if(get_option( 'pp_invoice_googlecheckout_address' ) != '' ) return get_option( 'pp_invoice_googlecheckout_address' );	
				return false;		
			break;

			case 'log_status':
				if( $status_update = $wpdb->get_row("SELECT * FROM ".PP_Invoice::tablename( 'log' )." WHERE invoice_id = ".$this->id ." ORDER BY ".PP_Invoice::tablename( 'log' ).".time_stamp DESC LIMIT 0 , 1") )
				return $status_update->value . " - " . PP_Invoice_Date::convert( $status_update->time_stamp, 'Y-m-d H', 'M d Y' );
			break;
			
			case 'paid_date':
				$paid_date = $wpdb->get_var("SELECT time_stamp FROM  ".PP_Invoice::tablename( 'log' )." WHERE action_type = 'paid' AND invoice_id = '".$this->id."' ORDER BY time_stamp DESC LIMIT 0, 1");
				if( $paid_date) return PP_Invoice_Date::convert( $paid_date, 'Y-m-d H', 'M d Y' );
				//echo "SELECT time_stamp FROM  ".PP_Invoice::tablename( 'log' )." WHERE action_type = 'paid' AND invoice_id = '".$this->id."' ORDER BY time_stamp DESC LIMIT 0, 1";
			break;

			case 'subscription_name':
				return pp_invoice_meta( $this->id,'pp_invoice_subscription_name' ); 
			break;
			
			case 'interval_length':
				return pp_invoice_meta( $this->id,'pp_invoice_subscription_length' ); 
			break;
			
			case 'interval_unit':
				return pp_invoice_meta( $this->id,'pp_invoice_subscription_unit' ); 
			break;
			
			case 'totalOccurrences':
				return pp_invoice_meta( $this->id,'pp_invoice_subscription_total_occurances' ); 
			break;
			
			case 'startDate':
				$pp_invoice_subscription_start_day = pp_invoice_meta( $this->id,'pp_invoice_subscription_start_day' );
				$pp_invoice_subscription_start_year = pp_invoice_meta( $this->id,'pp_invoice_subscription_start_year' );
				$pp_invoice_subscription_start_month = pp_invoice_meta( $this->id,'pp_invoice_subscription_start_month' );
				
				if( $pp_invoice_subscription_start_month && $pp_invoice_subscription_start_year && $pp_invoice_subscription_start_day ) {
					return $pp_invoice_subscription_start_year . "-" . $pp_invoice_subscription_start_month . "-" . $pp_invoice_subscription_start_day;
				} else {
					return date("Y-m-d");
				}
			break;

			case 'endDate':
				return date( 'Y-m-d', strtotime("+".( $this->display( 'interval_length' )*$this->display( 'totalOccurrences' ) )." ".$this->display( 'interval_unit' ), strtotime( $this->display( 'startDate' ) )) );
			break;
			
			case 'archive_status':
				$result = $wpdb->get_col("SELECT action_type FROM  ".PP_Invoice::tablename( 'log' )." WHERE invoice_id = '".$this->id."' ORDER BY time_stamp DESC");
				foreach( $result as $event){
					if ( $event == 'unarchive' ) { return ''; break; }
					if ( $event == 'archive' ) { return 'archive'; break; }
				}
			break;
			
			case 'display_billing_rate': 
				$length = pp_invoice_meta( $this->id,'pp_invoice_subscription_length' ); 
				$unit = pp_invoice_meta( $this->id,'pp_invoice_subscription_unit' ); 
				$occurances = pp_invoice_meta( $this->id,'pp_invoice_subscription_total_occurances' ); 
				// days
				if( $unit == "days") {
					if( $length == '1' ) return "daily for $occurances days";
					if( $length > '1' ) return "every $length days for a total of $occurances billing cycles";
				}
				//months
				if( $unit == "months"){
					if( $length == '1' ) return "monthly for $occurances months";
					if( $length > '1' ) return "every $length months $occurances times";
				}
			break;

			case 'link':
				$link_to_page = get_permalink(get_option( 'pp_invoice_web_invoice_page' ) );
				$hashed = md5( $this->id);
				if(get_option("permalink_structure") ) { return $link_to_page . "?invoice_id=" .$hashed; } 
				else { return  $link_to_page . "&invoice_id=" . $hashed; } 		
			break;
			
			case 'hash':
				return md5( $this->id);
			break;

			case 'currency':
				if(pp_invoice_meta( $this->id,'pp_invoice_currency_code' ) != '' ) {
					$currency_code = pp_invoice_meta( $this->id,'pp_invoice_currency_code' );
				} else if (get_option( 'pp_invoice_default_currency_code' ) != '' ) {
					$currency_code = get_option( 'pp_invoice_default_currency_code' );
				} else {
					$currency_code = "USD";
				}
				return $currency_code;	
			break;

			case 'display_id':
				$pp_invoice_custom_invoice_id = pp_invoice_meta( $this->id,'pp_invoice_custom_invoice_id' );
				if(empty( $pp_invoice_custom_invoice_id) ) { return $this->id; }	else { return $pp_invoice_custom_invoice_id; }	
			break;
			
			case 'due_date':
				$pp_invoice_due_date_month = pp_invoice_meta( $this->id,'pp_invoice_due_date_month' );
				$pp_invoice_due_date_year = pp_invoice_meta( $this->id,'pp_invoice_due_date_year' );
				$pp_invoice_due_date_day = pp_invoice_meta( $this->id,'pp_invoice_due_date_day' );
				if(!empty( $pp_invoice_due_date_month) && !empty( $pp_invoice_due_date_year) && !empty( $pp_invoice_due_date_day ) ) return "$pp_invoice_due_date_year/$pp_invoice_due_date_month/$pp_invoice_due_date_day";	
			break;
			
			case 'amount':
				return $invoice_info->amount;	
			break;
			
			case 'tax_percent':
				if(pp_invoice_meta( $this->id,'pp_invoice_tax' ) != "") return pp_invoice_meta( $this->id,'pp_invoice_tax' );	
				return pp_invoice_meta( $this->id,'tax_value' );
			break;
			
			case 'tax_total':
				if(pp_invoice_meta( $this->id,'pp_invoice_tax' ) != "") return  pp_invoice_meta( $this->id,'pp_invoice_tax' ) * $invoice_info->amount;	
				return  pp_invoice_meta( $this->id,'tax_value' ) * $invoice_info->amount;
			break;
			
			case 'subject':
				return $invoice_info->subject;	
			break;
			
			case 'pp_invoice_email_message_content':
				return pp_invoice_meta( $this->id,'pp_invoice_email_message_content' );
			break;
			
			case 'display_amount':
				if(!strpos( $invoice_info->amount,'.' ) ) $amount = $invoice_info->amount . ".00"; else $amount = $invoice_info->amount;
				return pp_money_format( $amount );
			break;
			
			case 'description':
				return  str_replace("\n", "<br />", $invoice_info->description);
			break;

			case 'itemized':
				return unserialize(urldecode( $invoice_info->itemized) );
			break;
		}
	}
}

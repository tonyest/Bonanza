<?php

function pp_invoice_metabox_submit_payment( $invoice ) {
	?>
	<div id="misc-publishing-actions">
		<div class="misc-pub-section">
			<label for="post_status">Status:</label>
			Unpaid
		</div>
		<div class="price_information">
		<?php _e( 'Total Due: ', 'prospress' ); echo pp_money_format( $invoice->amount ); ?>
		</div>

	</div>

<?php
}

function pp_invoice_metabox_invoice_details( $invoice ) { ?>
	<table class="form-table" id="pp_invoice_main_info">

		<tr class="invoice_main">
			<th><?php _e("Post Title", 'prospress') ?></th>
			<td><?php echo $invoice->post_title; ?></td>
		</tr>
		<tr class="invoice_main">
			<th><?php _e("Post Content", 'prospress') ?></th>
			<td><?php echo $invoice->post_content; ?></td>	
		</tr>
		<tr class="invoice_main">
			<th><?php _e("Total Amount", 'prospress') ?></th>
			<td><?php echo $invoice->display_amount; ?></td>	
		</tr>

	</table>
	<?php
}

function pp_invoice_metabox_billing_details( $invoice ) {

	// Create payment array
	$payment_array = pp_invoice_user_accepted_payments( $invoice->payee_class->ID );
	?>
<script type="text/javascript">
//<![CDATA[
	function changePaymentOption(){
		var dropdown = document.getElementById("pp_invoice_select_payment_method_selector");
		var index = dropdown.selectedIndex;
		var ddVal = dropdown.options[index].value;
		var ddText = dropdown.options[index].text;
		if(ddVal == 'PayPal') {
			jQuery(".payment_info").hide();
			jQuery(".paypal_ui").show();
		}		
		if(ddVal == 'Credit Card') {
			jQuery(".payment_info").hide();
			jQuery(".cc_ui").show();
		}
		if(ddVal == 'Bank Transfer') {
			jQuery(".payment_info").hide();
 			jQuery(".draft_ui").show();
		}
	}
//]]>
</script>
<style>
.payment_info {display: none;}
.<?php echo pp_invoice_user_settings('default_payment_venue', $invoice->payee_class->ID); ?>_ui {display: block; } 
</style>

	<?php
	//show dropdown there is more than one payment option
	if( count( $payment_array ) > 1 ) { ?>
	<fieldset id="pp_invoice_select_payment_method">
		<ol>
			<li>
				<label for="first_name">Select Payment Method </label>
				<select id="pp_invoice_select_payment_method_selector" onChange="changePaymentOption()">
					<?php foreach ($payment_array as $payment_name => $allowed) { 
						$name =  str_replace('_allow', '', $payment_name); ?>
						<option name="<?php echo $name; ?>" <?php if(pp_invoice_user_settings('default_payment_venue', $invoice->payee_class->ID) == $name) { echo "SELECTED"; } ?>><?php echo pp_invoice_payment_nicename($name); ?></option>
					<?php } ?>
				</select>
			</li>
		</ol>
	</fieldset>
	<?php } ?>

	<?php // Include payment-specific UI files
	if( is_array( $payment_array ) ) {
		foreach ( $payment_array as $payment_name => $allowed ) { 
			$name =  str_replace( '_allow', '', $payment_name );?>
		 	<div class="<?php echo $name; ?>_ui payment_info"><?php include PP_INVOICE_UI_PATH . "payment_{$name}.php"; ?></div>
	 	<?php }
	} else { ?>
		<p>The payee has not set up any billing options yet. You cannot pay until this is done. Please contact the payee to resolve this.</p>
	<?php }
}

function pp_invoice_metabox_payee_details( $invoice ) { ?>
	<dl class="payee_details clearfix">
		<dt>Email</dt>
		<dd><?php echo $invoice->payee_class->user_email; ?></dd>

		<dt>Username</dt>
		<dd><?php echo $invoice->payee_class->user_nicename; ?></dd>

		<dt>First Name</dt>
		<dd><?php echo $invoice->payee_class->first_name; ?></dd>

		<dt>Last Name</dt>
		<dd><?php echo $invoice->payee_class->last_name; ?></dd>
	</dl>
	<?php
}

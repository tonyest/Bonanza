	<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready(function(){
			jQuery("#pp_invoice_payment_form").submit(function() {
 				jQuery('#process_payment_cc').attr('disabled', true);
				process_cc_checkout();
				return false;
			});
			jQuery("#card_num").keyup(function(){
				cc_card_pick();
				return false;
			});
		});

	function cc_card_pick(){
		numLength = jQuery('#card_num').val().length;
		number = jQuery('#card_num').val();
		if(numLength > 10)
		{
			if((number.charAt(0) == '4') && ((numLength == 13)||(numLength==16))) { jQuery('#cardimage').removeClass(); jQuery('#cardimage').addClass('visa_card'); }
			else if((number.charAt(0) == '5' && ((number.charAt(1) >= '1') && (number.charAt(1) <= '5'))) && (numLength==16)) { jQuery('#cardimage').removeClass(); jQuery('#cardimage').addClass('mastercard'); }
			else if(number.substring(0,4) == "6011" && (numLength==16)) 	{ jQuery('#cardimage').removeClass(); jQuery('#cardimage').addClass('amex'); }
			else if((number.charAt(0) == '3' && ((number.charAt(1) == '4') || (number.charAt(1) == '7'))) && (numLength==15)) { jQuery('#cardimage').removeClass(); jQuery('#cardimage').addClass('discover_card'); }
			else { jQuery('#cardimage').removeClass(); jQuery('#cardimage').addClass('nocard'); }

		}
	}

	function process_cc_checkout(){
		jQuery("#ajax-loading-cc").show();
		jQuery("#credit_card_information input").removeClass('cc_error');
		jQuery("#credit_card_information select").removeClass('cc_error');
		jQuery("#pp_cc_response ol li").remove();

		jQuery.post( ajaxurl, jQuery('#pp_invoice_payment_form').serialize(), function(html){

			if(html == '<?php echo wp_create_nonce('pp_invoice_process_cc_' . $invoice->id); ?>') {
				jQuery('#credit_card_information').fadeOut("slow");
				jQuery('#major-publishing-actions').fadeOut("slow");
				jQuery('#process_payment_cc').fadeOut("slow");
				jQuery('#pp_invoice_select_payment_method').fadeOut("slow");
				jQuery(".updated").hide();
				jQuery('#pp_cc_response').fadeIn("slow").addClass('success');
				jQuery('#pp_cc_response ol').html("<li><?php _e('Payment processed successfully, thank you!', 'prospress'); ?></li>");
				jQuery("#ajax-loading-cc").hide();
				return;
			}

			// Error occured
			var explode = html.toString().split('\n');
			// Remove all errors
			jQuery(".pp_invoice_error_wrapper div").remove();

			for( var i in explode ) {
				var explode_again = explode[i].toString().split('|');
				if (explode_again[0]=='error'){ 
					var id = explode_again[1];
					var description = explode_again[2];
					var parent = jQuery("#" + id).parent();
					//jQuery(parent).css('border', '1px solid red');
					jQuery("#" + id).addClass('cc_error');
					jQuery("#pp_cc_response").show();
					jQuery("#pp_cc_response ol").append('<li>' + description + '</li>');
				}
				else if (explode_again[0]=='ok') {
				}
			}
			jQuery('#process_payment_cc').attr('disabled', false);
			jQuery("#ajax-loading-cc").hide();
 		});
	}

	//]]>
	</script>

	<form id='pp_invoice_payment_form' action="#" method='POST' >

	<input type="hidden" name="action" value="pp_invoice_process_cc_ajax">
	<input type="hidden" name="user_id" value="<?php echo $invoice->payer_class->ID; ?>">
	<input type="hidden" name="invoice_id" value="<?php echo $invoice->id; ?>">
	<input type="hidden" name="amount" id="total_amount" value="<?php echo round( $invoice->amount, 2 ); ?>" />
	<?php 
	wp_nonce_field( 'pp_invoice_process_cc_' . $invoice->id, 'pp_invoice_process_cc' , false );
	?>

	<input type="hidden" name="amount" value="<?php echo $invoice->amount; ?>">
 	<input type="hidden" name="email_address" value="<?php echo $invoice->payee_class->user_email; ?>">
	<input type="hidden" name="id" value="<?php echo  $invoice->id; ?>">
	<input type="hidden" name="currency_code" id="currency_code"  value="<?php echo $invoice->currency_code; ?>">
 	<fieldset id="credit_card_information" class="clearfix">
	<ol>
	<li>
		<label for="first_name"><?php _e('First Name', 'prospress'); ?></label>
		<?php echo pp_invoice_draw_inputfield("first_name",$invoice->payer_class->first_name); ?>
		</li>

		<li>
		<label for="last_name"><?php _e('Last Name', 'prospress'); ?></label>
		<?php echo pp_invoice_draw_inputfield("last_name",$invoice->payer_class->last_name); ?>
		</li>

		<li>
		<label for="email"><?php _e('Email Address', 'prospress'); ?></label>
		<?php echo pp_invoice_draw_inputfield("email_address",$invoice->payer_class->user_email); ?>
		</li>

		<li>
		<label class="inputLabel" for="phonenumber"><?php _e('Phone Number', 'prospress'); ?></label>
		<input name="phonenumber" class="input_field"  type="text" id="phonenumber" size="40" maxlength="50" value="<?php print $invoice->payer_class->phonenumber; ?>" />
		</li>

		<li>
		<label for="address"><?php _e('Address', 'prospress'); ?></label>
		<?php echo pp_invoice_draw_inputfield("address",$invoice->payer_class->streetaddress); ?>
		</li>

		<li>
		<label for="city"><?php _e('City', 'prospress'); ?></label>
		<?php echo pp_invoice_draw_inputfield("city",$invoice->payer_class->city); ?>
		</li>

		<li id="state_field">
		<label for="state"><?php _e('State', 'prospress'); ?></label>
		<?php echo pp_invoice_draw_inputfield("state",$invoice->payer_class->state); ?>
		</li>

		<li>
		<label for="zip"><?php _e('Zip/Postal Code', 'prospress'); ?></label>
		<?php echo pp_invoice_draw_inputfield("zip",$invoice->payer_class->zip); ?>
		</li>

		<li>
		<label for="country"><?php _e('Country', 'prospress'); ?></label>
		<?php echo pp_invoice_draw_select('country',pp_invoice_country_array(),$invoice->payer_class->country); ?>
		</li>

		<li>
		<label class="inputLabel" for="card_num"><?php _e('Credit Card Number', 'prospress'); ?></label>
		<input name="card_num" autocomplete="off" onkeyup="cc_card_pick();"  id="card_num" class="credit_card_number input_field"  type="text"  size="22"  maxlength="22" />
		</li>

		<li class="nocard"  id="cardimage" style=" background: url(<?php echo PP_Invoice::frontend_path(); ?>/core/images/card_array.png) no-repeat;">
		</li>

		<li>
		<label class="inputLabel" for="exp_month"><?php _e('Expiration Date', 'prospress'); ?></label>
		<?php _e('Month', 'prospress'); ?> <select name="exp_month" id="exp_month"><?php echo pp_invoice_month_dropdown(); ?></select>
		<?php _e('Year', 'prospress'); ?> <select name="exp_year" id="exp_year"><?php echo pp_invoice_year_dropdown(); ?></select>
		</li>

		<li>
		<label class="inputLabel" for="card_code"><?php _e('Security Code', 'prospress'); ?></label>
		<input id="card_code" autocomplete="off"  name="card_code" class="input_field"  style="width: 70px;" type="text" size="4" maxlength="4" />
	</li>
	</ol>
	</fieldset>
	<div id="pp_cc_response"><ol></ol></div>
	<div id="major-publishing-actions">
		<input type="submit" value="Process Credit Card Payment" accesskey="p" id="process_payment_cc" class="button-primary" name="process_payment_cc">
		<img alt="" id="ajax-loading-cc" style="display:none" src="<?php echo admin_url('images/wpspin_light.gif');?>">
	</div>
</form>
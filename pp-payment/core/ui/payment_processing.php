<div id="pp_invoice_billing_information" class="pp_invoice_tabbed_content"> 
	<ul> 
		<li><a <?php if($pp_invoice_payment_method == 'paypal') echo 'class="selected"';?>  href="#paypal_tab"><?php _e("PayPal") ?></a></li> 
<?php /*<li><a <?php if($pp_invoice_payment_method == 'moneybookers') echo 'class="selected"';?> href="#moneybookers_tab"><?php _e("Moneybookers") ?></a></li> */ ?>
		<li><a <?php if($pp_invoice_payment_method == 'cc') echo 'class="selected"';?> href="#cc_tab"><?php _e("Credit Card") ?></a></li> 
<?php /*<li><a <?php if($pp_invoice_payment_method == 'alertpay') echo 'class="selected"';?> href="#alertpay_tab"><?php _e("Alertpay") ?></a></li> */ ?>
	</ul> 

  <div id="paypal_tab" class="pp_invoice_tab" >
		<table class="form-table">
			<tr>
				<th width="300"><?php _e("Accept This Payment Venue?"); ?></th>
				<td><?php echo pp_invoice_draw_select('pp_invoice_paypal_allow',array("yes" => "Yes","no" => "No"), $pp_invoice_paypal_allow); ?></td>
			</tr>

			<tr>
				<th width="300"><?php _e("PayPal Username"); ?></th>
				<td><?php echo pp_invoice_draw_inputfield('pp_invoice_paypal_address',$pp_invoice_paypal_address); ?></td>
			</tr>

<?php if($hide_advanced_paypal_features) { ?>
			<tr>
				<th width="300"><?php _e("PayPal Pay Button URL"); ?></th>
				<td><?php echo pp_invoice_draw_inputfield('pp_invoice_fe_paypal_link_url',$pp_invoice_fe_paypal_link_url); ?></td>
			</tr>
<?php } ?>

		</table>
  </div>

  <div id="cc_tab" class="pp_invoice_tab" >
		<table class="form-table">

			<tr class="">
				<th width="300"><?php _e("Accept this Payment Venue?") ?></th>
				<td><?php echo pp_invoice_draw_select('pp_invoice_cc_allow',array("yes" => "Yes","no" => "No"), $pp_invoice_cc_allow); ?></td>
			</tr>
		
			<tr class="gateway_info payment_info">
				<th width="300"><a class="pp_invoice_tooltip" title="<?php _e('Your credit card processor will provide you with a gateway username.', 'prospress'); ?>"><?php _e('Gateway Username', 'prospress'); ?></a></th>
				<td><?php echo pp_invoice_draw_inputfield('pp_invoice_gateway_username',$pp_invoice_gateway_username, ' AUTOCOMPLETE="off"  '); ?>
				</td>
			</tr>

			<tr class="gateway_info payment_info">
				<th width="300"><a class="pp_invoice_tooltip" title="<?php _e("You will be able to generate this in your credit card processor's control panel.", 'prospress'); ?>"><?php _e('Gateway Transaction Key', 'prospress'); ?></a></th>
				<td><?php echo pp_invoice_draw_inputfield('pp_invoice_gateway_tran_key',$pp_invoice_gateway_tran_key, ' AUTOCOMPLETE="off"  '); ?></td>
			</tr>

			<tr class="gateway_info payment_info">
				<th width="300"><a class="pp_invoice_tooltip"  title="<?php _e('This is the URL provided to you by your credit card processing company.', 'prospress'); ?>"><?php _e('Gateway URL', 'prospress'); ?></a></th>
				<td><?php echo pp_invoice_draw_inputfield('pp_invoice_gateway_url',$pp_invoice_gateway_url); ?><br />
				<span class="pp_invoice_click_me" onclick="jQuery('#pp_invoice_gateway_url').val('https://gateway.merchantplus.com/cgi-bin/PAWebClient.cgi');">MerchantPlus</span> |
				<span class="pp_invoice_click_me" onclick="jQuery('#pp_invoice_gateway_url').val('https://secure.authorize.net/gateway/transact.dll');">Authorize.Net</span> |
				<span class="pp_invoice_click_me" onclick="jQuery('#pp_invoice_gateway_url').val('https://test.authorize.net/gateway/transact.dll');">Authorize.Net Developer</span> 
				</td>
			</tr>

			<tr class="gateway_info payment_info">
				<th width="300"><a class="pp_invoice_tooltip"  title="<?php _e('Recurring billing gateway URL is most likely different from the Gateway URL, and will almost always be with Authorize.net. Be advised - test credit card numbers will be declined even when in test mode.', 'prospress'); ?>"><?php _e('Recurring Billing Gateway URL', 'prospress'); ?></a></th>
				<td><?php echo pp_invoice_draw_inputfield('pp_invoice_recurring_gateway_url',$pp_invoice_recurring_gateway_url); ?><br />
				<span class="pp_invoice_click_me" onclick="jQuery('#pp_invoice_recurring_gateway_url').val('https://api.authorize.net/xml/v1/request.api');">Authorize.net ARB</span> |
				<span class="pp_invoice_click_me" onclick="jQuery('#pp_invoice_recurring_gateway_url').val('https://apitest.authorize.net/xml/v1/request.api');">Authorize.Net ARB Testing</span>
				</td>
			</tr>

<?php if($hide_advanced_cc_features) { ?>
			<tr class="gateway_info payment_info">
				<th>Test / Live Mode:</th>
				<td><?php echo pp_invoice_draw_select('pp_invoice_gateway_test_mode',array("TRUE" => "Test - Do Not Process Transactions","FALSE" => "Live - Process Transactions"), $pp_invoice_gateway_test_mode); ?></td>
			</tr>

			<tr class="gateway_info">
				<th width="300"><a class="pp_invoice_tooltip"  title="<?php _e('Get this from your credit card processor. If the transactions are not going through, this character is most likely wrong.', 'prospress'); ?>"><?php _e('Delimiter Character', 'prospress'); ?></a></th>
				<td><?php echo pp_invoice_draw_inputfield('pp_invoice_gateway_delim_char',$pp_invoice_gateway_delim_char); ?>
			</tr>

			<tr class="gateway_info">
				<th width="300"><a class="pp_invoice_tooltip" title="<?php _e('Authorize.net default is blank. Otherwise, get this from your credit card processor. If the transactions are going through, but getting strange responses, this character is most likely wrong.', 'prospress'); ?>"><?php _e('Encapsulation Character', 'prospress'); ?></a></th>
				<td><?php echo pp_invoice_draw_inputfield('pp_invoice_gateway_encap_char',$pp_invoice_gateway_encap_char); ?></td>
			</tr>

			<tr class="gateway_info">
				<th width="300"><?php _e('Merchant Email', 'prospress'); ?></th>
				<td><?php echo pp_invoice_draw_inputfield('pp_invoice_gateway_merchant_email',$pp_invoice_gateway_merchant_email); ?></td>
			</tr>

			<tr class="gateway_info">
				<th><?php _e('Email Customer (on success):', 'prospress'); ?></th>
				<td><?php echo pp_invoice_draw_select('pp_invoice_gateway_email_customer',array("TRUE" => "Yes","FALSE" => "No"), $pp_invoice_gateway_test_mode); ?></td>
			</tr>

			<tr class="gateway_info">
				<th width="300"><?php _e('Security: MD5 Hash', 'prospress'); ?></th>
				<td><?php echo pp_invoice_draw_inputfield('pp_invoice_gateway_MD5Hash',$pp_invoice_gateway_MD5Hash); ?></td>				</td>
			</tr>

			<tr class="gateway_info">
				<th><?php _e('Delim Data:', 'prospress'); ?></th>
				<td><?php echo pp_invoice_draw_select('pp_invoice_gateway_delim_data',array("TRUE" => "True","FALSE" => "False"), $pp_invoice_gateway_delim_data); ?></td>
			</tr>
<?php } ?>			
		</table>
  </div>

</div>
<script type="text/javascript"> 
  jQuery("#pp_invoice_billing_information ul").idTabs(); 
</script>

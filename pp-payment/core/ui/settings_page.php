
<div class="wrap">
<?php screen_icon( 'prospress' ); ?>
<h2><?php _e( 'Payment Settings', 'prospress') ?></h2>
<form id='pp_invoice_settings_page' method='POST'>
<table class="form-table">
	<tr>
		<th><?php _e('Default Tax Label:', 'prospress'); ?></th>
		<td>
			<?php echo pp_invoice_draw_inputfield('pp_invoice_custom_label_tax', get_option('pp_invoice_custom_label_tax')); ?>
			<?php _e( 'The name of tax in your country. eg. VAT, GST or Sales Tax.', 'prospress'); ?>
		</td>
	</tr>		
	<tr>
		<th>Using Godaddy Hosting</th>
		<td>
			<input type="checkbox" name="pp_invoice_using_godaddy" id="pp_invoice_using_godaddy" value="true" <?php checked( get_option('pp_invoice_using_godaddy'), 'true' );?> />
			<?php _e( 'A special proxy must be used for credit card transactions on GoDaddy servers.', 'prospress'); ?>
		</td>
	</tr>
	<tr>
		<th><label for="enforce_ssl"><?php _e('Use SSL:', 'prospress' ); ?></label></th>
		<td>
		<input type="checkbox" name="pp_invoice_force_https" id="pp_invoice_force_https" value="true" <?php checked( get_option('pp_invoice_force_https'), 'true' );?> />
		<?php _e('You should use SSL to secure the payment page if offering credit card as a payment option.', 'prospress' ); ?>
		<a href="http://affiliate.godaddy.com/redirect/12F28CD920606B123C313973412F6A1FFD2D46585278C9437B5EE5EBD9E46EB8" title="SSL Certificates from GoDaddy" class="wp_invoice_click_me"><?php _e('Do you need an SSL Certificate?', 'prospress'); ?></a>
		</td>
	</tr>
</table>
<h3>Email Templates</h3>
<table class="form-table pp_invoice_email_templates">
	<tr>
		<th><?php _e("<b>Invoice Notification</b> Subject", 'prospress') ?></th>
		<td><?php echo pp_invoice_draw_inputfield('pp_invoice_email_send_invoice_subject', get_option('pp_invoice_email_send_invoice_subject')); ?></td>
	</tr>
	<tr>
		<th><?php _e("<b>Invoice Notification</b> Content", 'prospress') ?></th>
		<td><?php echo pp_invoice_draw_textarea('pp_invoice_email_send_invoice_content', get_option('pp_invoice_email_send_invoice_content')); ?></td>
	</tr>
	<tr><td colspan="2">&nbsp;</td></tr>
	<tr>
		<th><?php _e("<b>Reminder</b> Subject", 'prospress') ?></th>
		<td><?php echo pp_invoice_draw_inputfield('pp_invoice_email_send_reminder_subject', get_option('pp_invoice_email_send_reminder_subject')); ?></td>
	</tr>
		<tr>
		<th><?php _e("<b>Reminder</b> Content", 'prospress') ?></th>
		<td><?php echo pp_invoice_draw_textarea('pp_invoice_email_send_reminder_content', get_option('pp_invoice_email_send_reminder_content')); ?></td>
	</tr>
	<tr><td colspan="2">&nbsp;</td></tr>
	<tr>
		<th><?php _e("<b>Receipt</b> Subject", 'prospress') ?></th>
		<td><?php echo pp_invoice_draw_inputfield('pp_invoice_email_send_receipt_subject', get_option('pp_invoice_email_send_receipt_subject')); ?></td>
	</tr>
		<tr>
		<th><?php _e("<b>Receipt</b> Content", 'prospress') ?></th>
		<td><?php echo pp_invoice_draw_textarea('pp_invoice_email_send_receipt_content', get_option('pp_invoice_email_send_receipt_content')); ?></td>
	</tr>
</table>
<div class="clear"></div>
<p class="submit">
	<input type="submit" value="Save Settings" class="button-primary">
</p>
</form>
</div>
<div class='wrap'>

<div class="metabox-holder">
	<h2><?php _e('Save and Preview', 'prospress'); ?></h2>
	
	<form method="post" action="admin.php?page=outgoing_invoices">
	<input type="hidden" value="<?php echo $invoice_id; ?>" name="invoice_id" >
	<input type="hidden" value="post_save_and_preview" name="action" >

	<div id="submitdiv" class="postbox" style="">	
	<h3 class="hndle"><span><?php _e('Notification Message', 'prospress'); ?></span></h3>
	<div class="inside">
	<div id="minor-publishing">

	<div id="misc-publishing-actions">
	<table class="form-table">

		<tr class="invoice_main">
			<th><?php _e('Subject:', 'prospress'); ?></th>
			<td style="font-size: 1.1em; padding-top:7px;">
			<?php echo preg_replace_callback('/(%([a-z_]+)%)/', 'pp_invoice_email_apply_variables', get_option('pp_invoice_email_send_invoice_subject')); ?>
			</td>
		</tr>
	
		<tr class="invoice_main">
			<th><?php _e('Email To:', 'prospress'); ?></th>
			<td style="font-size: 1.1em; padding-top:7px;">
			<?php echo $invoice->payer_class->user_email; ?>
			</td>
		</tr>

		<tr class="invoice_main">
			<th><?php _e('Email Message:', 'prospress'); ?></th>
			<td style="font-size: 1.1em; padding-top:7px;">
			<div class="email_message_content">
			<?php echo pp_invoice_draw_textarea("pp_invoice_payment_request[email_message_content]", pp_invoice_show_email($invoice_id), 'style="width: 95%; height: 250px;"'); ?>
			</div>
			
			<div class="email_message_content_original">
			<?php echo pp_invoice_draw_textarea("email_message_content_original", pp_invoice_show_email($invoice_id, true), ' style="display:none; "'); ?>
			</div>
			
			<span class="pp_invoice_click_me" onclick="pp_invoice_restore_original()"><?php _e('Reset Email Based on Template', 'prospress'); ?></span>
			</td>
		</tr>

	</table>
	</div>
	<div class="clear"></div>
	</div>

	<div id="major-publishing-actions">

	<div id="publishing-action">
		<input type="submit" value="<?php _e('Save for Later', 'prospress'); ?>" name="pp_invoice_action" class="button-secondary" />
 		<input type="submit" value="<?php _e('Email to Client', 'prospress'); ?>"  name="pp_invoice_action" class="button-primary" />
	</div>
	<div class="clear"></div>
	</div>

	</div>
	</div>
	</form>

</div>

</div>
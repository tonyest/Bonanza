<form id='pp_invoice_draft_form' action="#" method='POST' >

	<input type="hidden" name="action" value="pp_invoice_process_draft">
	<input type="hidden" name="user_id" value="<?php echo $invoice->payer_class->ID; ?>">
	<input type="hidden" name="invoice_id" value="<?php echo $invoice->id; ?>">

 	<?php wp_nonce_field( 'pp_invoice_process_cc_' . $invoice->id, 'pp_invoice_process_cc' , false ); ?>

 	<fieldset id="bank_draft_information" class="clearfix">
		<ol>
			<li>
				<span class="draft_isntructions"><?php _e( 'Instructions: ', 'prospress' )?></span>
				<span><?php echo pp_invoice_user_settings('draft_text', $invoice->payee_class->ID); ?></span>
			</li>
			<li class="clearfix">
				<label class="inputLabel" for="draft_message">Message to Payee:</label>
				<textarea id="draft_message" name="draft_message" ></textarea>
			</li>
		</ol>
	</fieldset>
 	<div id="major-publishing-actions">
		<input type="submit" value="Send Message" accesskey="p" id="process_draft_payment" class="button-primary" name="process_draft_payment">
 	</div>
</form>
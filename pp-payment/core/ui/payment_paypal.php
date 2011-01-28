 <form action="https://www<?php if($invoice->payee_class->pp_invoice_settings[paypal_sandbox] == 'true') echo ".sandbox"; ?>.paypal.com/cgi-bin/webscr" method="post" class="clearfix">
	<input type="hidden" name="cmd" value="_xclick">
 	<input type="hidden" name="business" value="<?php echo $invoice->payee_class->pp_invoice_settings[paypal_address]; ?>">
	<input type="hidden" name="item_name" value="<?php echo $invoice->post_title; ?>">	
    <input type="hidden" name="no_note" value="1">
	<input type="hidden" name="currency_code" value="<?php echo $invoice->currency_code; ?>">
 	<input type="hidden" name="no_shipping" value="1">
	<input type="hidden" name="upload" value="1">
	<input type="hidden" name="return" value="<?php echo $invoice->pay_link; ?>&return_info=success">
	<input type="hidden" name="cancel_return" value="<?php echo $invoice->pay_link; ?>&return_info=cancel">
	<input type="hidden" name="notify_url" value="<?php echo $invoice->pay_link; ?>">

	<?php if($invoice->tax_total == 0) : ?>
	<input type="hidden" name="tax"  value="<?php echo $invoice->tax_total; ?>">
	<?php endif; ?>
	<input type="hidden" name="rm" value="2">
	<input type="hidden" name="amount"  value="<?php echo round( $invoice->amount, 2 ); ?>">
	<input type="hidden" name="cbt"  value="<?php echo __( 'Return to', 'prospress' ) . '&nbsp;' , get_bloginfo( 'name' ) . '&nbsp;&raquo'; ?>">
	<input  type="hidden" name="invoice" id="id"  value="<?php echo  $invoice->id; ?>">
 
	<div id="major-publishing-actions">
		<img alt="" style="display: none;" id="ajax-loading" src="<?php echo admin_url('images/wpspin_light.gif');?>">
		<input type="submit" value="<?php _e( 'Proceed to PayPal', 'prospress' ); ?>" accesskey="p" id="process_payment" class="button-primary" name="process_payment">
	</div>
</form>
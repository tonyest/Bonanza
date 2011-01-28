<div class='wrap'>
	<h2>Make Payment</h2>
 
	<div class="pp_invoice_error_wrapper">
	<?php if( count( $errors ) > 0): ?>
	<div class="error">
		<?php foreach( $errors as $error ): ?>
			<p><?php echo $error; ?></p>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
	</div>

	<?php if(count($messages) > 0): ?>
	<div class="updated fade">
		<?php foreach($messages as $message): ?>
			<p><?php echo $message; ?></p>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
 
	<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready( function(jQuery) {
			jQuery('.if-js-closed').removeClass('if-js-closed').addClass('closed');
			postboxes.add_postbox_toggles('<?php echo $pp_invoice_page_names['view_invoice']; ?>');
		});
	//]]>
	</script>

	<?php
	wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
	wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); 
	?>
	
	<div id="poststuff" class="metabox-holder has-right-sidebar">
	
		<div id="side-info-column" class="inner-sidebar">
			<?php 
			if(!$invoice->is_paid)
				add_meta_box('pp_invoice_metabox_submit_payment', __('Payment Details','prospress'), 'pp_invoice_metabox_submit_payment', 'admin_page_make_payment', 'side', 'high');
				
			do_meta_boxes('admin_page_make_payment', 'side', $invoice); ?>				
		</div>

		<div id="post-body" class="has-sidebar">
			<div id="post-body-content">
				<?php do_meta_boxes('admin_page_make_payment', 'normal', $invoice); ?>
			</div>
		</div>
	</div>
</div>

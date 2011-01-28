<div class='wrap'>
	<h2><?php echo $page_title; ?></h2>

	<div class="pp_invoice_error_wrapper">
	<?php if(count($errors) > 0): ?>
	<div class="error"><p>
		<?php foreach($errors as $error): ?>
			<?php echo $error; ?><br />
		<?php endforeach; ?>
	</p></div>
	<?php endif; ?>
	</div> 

	<?php if(count($messages) > 0): ?>
	<div class="updated fade"><p>
		<?php foreach($messages as $message): ?>
			<?php echo $message; ?><br />
		<?php endforeach; ?>
	</p></div>
	<?php endif; ?>

	<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready( function(jQuery) {
			jQuery('.if-js-closed').removeClass('if-js-closed').addClass('closed');
			postboxes.add_postbox_toggles('admin_page_send_invoice');
		 
		});
		//]]>
	</script>
	
	<form id='new_invoice_form' action="admin.php?page=save_and_preview" method='POST'>

	<input type="hidden" name="user_id" value="<?php echo $user_ID; ?>">
	<input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
 
	<?php
	wp_nonce_field( 'pp_invoice_update_single_' . $invoice->id, 'pp_invoice_update_single' , false );
	wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
	wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); 
	?>
		
	<div id="poststuff" class="metabox-holder has-right-sidebar">
	
		<div id="side-info-column" class="inner-sidebar">
			<?php 
			if(!$invoice->is_paid)
				add_meta_box('pp_invoice_metabox_publish', __('Send Invoice','prospress'), 'pp_invoice_metabox_publish', 'admin_page_send_invoice', 'side', 'high');
				
			do_meta_boxes('admin_page_send_invoice', 'side', $invoice); ?>				
		</div>

		<div id="post-body" class="has-sidebar">
			<div id="post-body-content">
				<?php do_meta_boxes('admin_page_send_invoice', 'normal', $invoice); ?>
			</div>
		</div>
	</div>

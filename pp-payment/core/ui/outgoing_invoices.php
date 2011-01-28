<div class="wrap">
	<script>
	 pagenow = 'toplevel_page_outgoing_invoices';
	</script>
	<form id="invoices-filter" action="" method="post" >
	<?php screen_icon(); ?>
	<h2><?php _e('Incoming Payments', 'prospress'); ?></h2>

	<?php if( $message ): ?>
	<div class="updated fade">
		<?php if( is_array( $message ) ): foreach( $message as $m ): ?>
			<p><?php echo $m; ?></p>
		<?php endforeach; else: ?>
			<p><?php echo $message; ?></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="tablenav clearfix">
	
	<div class="alignleft">
	<select id="pp_invoice_action" name="pp_invoice_action">
		<option value="-1" selected="selected"><?php _e('-- Actions --', 'prospress'); ?></option>
		<option value="archive_invoice" name="archive" ><?php _e('Archive', 'prospress'); ?></option>
		<option value="unrachive_invoice" name="unarchive" ><?php _e('Un-Archive', 'prospress'); ?></option>
		<option value="mark_as_sent" name="mark_as_sent" ><?php _e('Mark as Sent', 'prospress'); ?></option>
		<option value="mark_as_paid" name="mark_as_paid" ><?php _e('Mark as Paid', 'prospress'); ?></option>
		<option value="mark_as_unpaid" name="mark_as_unpaid" ><?php _e('Unset Paid Status', 'prospress'); ?></option>
	</select>
	<input type="submit" value="Apply" id="submit_bulk_action" class="button-secondary action" />
	</div>

	<div class="alignright">
		<ul class="subsubsub" style="margin:0;">
		<li><?php _e('Filter:', 'prospress'); ?></li>
		<li><a href='#' class="" id=""><?php _e( 'All', 'prospress' ); ?></a> |</li>
		<li><a href='#'  class="paid" id=""><?php _e( 'Paid', 'prospress' ); ?></a> |</li>
		<li><a href='#'  class="sent" id=""><?php _e( 'Unpaid', 'prospress' ); ?></a> |</li>
		<li><?php _e('Custom: ', 'prospress'); ?><input type="text" id="FilterTextBox" class="search-input" name="FilterTextBox" /> </li>
		</ul>
	</div>
	</div>
	<br class="clear" />	
	<table class="widefat fixed" cellspacing="0"  id="invoice_sorter_table">
		<thead>
			<tr class="thead">
			<?php print_column_headers('toplevel_page_outgoing_invoices') ?>
			</tr>
		</thead>
		<tfoot>
			<tr class="thead">
			<?php print_column_headers('toplevel_page_outgoing_invoices', false) ?>
			</tr>
		</tfoot>
		<tbody id="invoices" class="list:invoices invoice-list">
			<?php
			$style = '';
			if( !empty( $outgoing_invoices ) ){
				foreach ($outgoing_invoices as $invoice_id) {			
					$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
					$invoice_class = new pp_invoice_get($invoice_id);
					echo "\n\t" . pp_invoice_invoice_row($invoice_class->data, 'outgoing');
				}
			} else { ?>
				<tr>
					<td colspan="5">
						<div>
							<?php _e('You have no incoming payments.', 'prospress'); ?>
						</div>
					</td>
				</tr>
			<?php }	?>
		</tbody>
	</table>
	<a href="" id="pp_invoice_show_archived">Show / Hide Archived</a>
	</form>
	<div class="pp_invoice_stats">
		Total of Displayed Invoices: <span id="pp_invoice_total_owed"></span>
	</div>
</div>
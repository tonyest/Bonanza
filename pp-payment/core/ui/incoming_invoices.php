<div class="wrap">
	<script>
	 pagenow = 'web-invoice_page_incoming_invoices';
	</script>
	<form id="invoices-filter" action="" method="post" >
	<?php screen_icon(); ?>
	<h2><?php _e('Outgoing Payments', 'prospress'); ?></h2>
	<div class="tablenav clearfix">

	<div class="alignleft">
	<select id="pp_invoice_action" name="pp_invoice_action">
		<option value="-1" selected="selected"><?php _e('-- Actions --', 'prospress'); ?></option>
		<option value="archive_invoice" name="archive" ><?php _e('Archive Invoice(s)', 'prospress'); ?></option>
		<option value="unrachive_invoice" name="unarchive" ><?php _e('Un-Archive Invoice(s)', 'prospress'); ?></option>
	</select>
	<input type="submit" value="Apply" id="submit_bulk_action" class="button-secondary action" />
	</div>

	<div class="alignright">
		<ul class="subsubsub" style="margin:0;">
		<li><?php _e( 'Filter:', 'prospress' ); ?></li>
		<li><a href='#' class="" id=""><?php _e( 'All', 'prospress' ); ?></a> |</li>
		<li><a href='#'  class="paid" id=""><?php _e( 'Paid', 'prospress' ); ?></a> |</li>
		<li><a href='#'  class="sent" id=""><?php _e( 'Unpaid', 'prospress' ); ?></a> |</li>
		<li><?php _e( 'Custom: ', 'prospress' ); ?><input type="text" id="FilterTextBox" class="search-input" name="FilterTextBox" /> </li>
		</ul>
	</div>
	</div>
	<br class="clear" />
	
	<table class="widefat fixed" cellspacing="0"  id="invoice_sorter_table">
	<thead>
		<tr class="thead">
		<?php print_column_headers('web-invoice_page_incoming_invoices') ?>
		</tr>
	</thead>
	<tfoot>
		<tr class="thead">
		<?php print_column_headers('web-invoice_page_incoming_invoices', false) ?>
		</tr>
	</tfoot>
	<tbody id="invoices" class="list:invoices invoice-list">
		<?php
		$style = '';
		if( !empty( $incoming_invoices ) ){
			foreach ($incoming_invoices as $invoice_id) {			
				$style = ( ' class="alternate"' == $style ) ? '' : ' class="alternate"';
				$invoice_class = new pp_invoice_get($invoice_id);
				echo "\n\t" . pp_invoice_invoice_row($invoice_class->data, 'incoming');
			}
		} else { ?>
			<tr>
				<td colspan="5">
					<div>
						<?php _e('You have no invoices to pay.', 'prospress'); ?>
					</div>
				</td>
			</tr>
		<?php }	?>
	</tbody>
	</table>
	<a href="" id="pp_invoice_show_archived">Show / Hide Archived</a>
	</form> 
	<div class="pp_invoice_stats">Total of Displayed Invoices: <span id="pp_invoice_total_owed"></span></div>

</div>
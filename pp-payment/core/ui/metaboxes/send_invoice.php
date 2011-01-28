<?php
	function pp_invoice_metabox_history( $ic ) {
	?>
		<ul id="invoice_history_log">
		<?php 
		if( $ic->log):
			foreach ( $ic->log as $single_status) {
				$time =  date(get_option('date_format') . ' ' . get_option('time_format'),  strtotime( $single_status->time_stamp));
				echo "<li><span class='pp_invoice_tamp_stamp'>" . $time . "</span>{$single_status->value} </li>";
			}
		else: ?>
		No history events for this invoice.
		<?php endif; ?>
		</ul>
	</div>

	<?php  }

function pp_invoice_metabox_publish( $ic ) { ?>
	<div id="minor-publishing">

		<div id="misc-publishing-actions">
		<table class="form-table">
			<tr class="invoice_main">
				<th>Invoice ID </th>
				<td ><?php echo $ic->id; ?></td>
			</tr>
<?php/*
			<tr class="invoice_main">
				<th>Tax </th>
				<td>
					<input name="pp_invoice[tax]" id="pp_invoice_tax" autocomplete="off" size="5" value="<?php echo $ic->tax ?>">%</input>
				</td>
			</tr>	
*/?>
			<tr class="">
				<th>Due Date</th>
				<td>
					<div id="timestampdiv" style="display:block;">
						<select id="mm" name="pp_invoice[due_date_month]">
							<option></option>
							<option value="1" <?php selected( $ic->due_date_month, '1' );?>>Jan</option>
							<option value="2" <?php selected( $ic->due_date_month, '2' );?>>Feb</option>
							<option value="3" <?php selected( $ic->due_date_month, '3' );?>>Mar</option>
							<option value="4" <?php selected( $ic->due_date_month, '4' );?>>Apr</option>
							<option value="5" <?php selected( $ic->due_date_month, '5' );?>>May</option>
							<option value="6" <?php selected( $ic->due_date_month, '6' );?>>Jun</option>
							<option value="7" <?php selected( $ic->due_date_month, '7' );?>>Jul</option>
							<option value="8" <?php selected( $ic->due_date_month, '8' );?>>Aug</option>
							<option value="9" <?php selected( $ic->due_date_month, '9');?>>Sep</option>
							<option value="10" <?php selected( $ic->due_date_month, '10');?>>Oct</option>
							<option value="11" <?php selected( $ic->due_date_month, '11');?>>Nov</option>
							<option value="12" <?php selected( $ic->due_date_month, '12');?>>Dec</option>
						</select>
						<input type="text" id="jj" name="pp_invoice[due_date_day]" value="<?php echo $ic->due_date_day; ?>" size="2" maxlength="2" autocomplete="off" />, 
						<input type="text" id="aa" name="pp_invoice[due_date_year]" value="<?php echo $ic->due_date_year; ?>" size="4" maxlength="5" autocomplete="off" />
					</div>
				</td>
			</tr>
			<tr class="hide-if-no-js">
				<th colspan="2">
					<div>
						<span onclick="pp_invoice_add_time(1);" class="pp_invoice_click_me"><?php _e( 'Today', 'prospress' ); ?></span> | 
						<span onclick="pp_invoice_add_time(7);" class="pp_invoice_click_me"><?php _e( 'In One Week', 'prospress' ); ?></span> | 
						<span onclick="pp_invoice_add_time(30);" class="pp_invoice_click_me"><?php _e( 'In 30 Days', 'prospress' ); ?></span> |
						<span onclick="pp_invoice_add_time('clear');" class="pp_invoice_click_me"><?php _e( 'Clear', 'prospress' ); ?></span>
					</div>
				</th>
			</tr>
		</table>
		</div>
		<div class="clear"></div>
		</div>

		<div id="major-publishing-actions">

		<div id="publishing-action">
			<input type="submit"  name="save" class="button-primary" value="Preview Email and Send"> 	
		</div>
		<div class="clear"></div>
	</div>

<?php }

function pp_invoice_metabox_invoice_details( $ic ) { ?>
	<table class="form-table" id="pp_invoice_main_info">

	<tr class="invoice_main">
		<th><?php _e("Post Title", 'prospress') ?></th>
		<td><?php echo $ic->post_title; ?></td>
	</tr>
	<tr class="invoice_main">
		<th><?php _e("Post Content", 'prospress') ?></th>
		<td><?php echo $ic->post_content; ?></td>	
	</tr>
	<tr class="invoice_main">
		<th><?php _e("Total Amount", 'prospress') ?></th>
		<td><?php echo $ic->display_amount; ?></td>	
	</tr>
	
	</table>
	<?php
}

function pp_invoice_metabox_payer_details( $invoice ) {?>
	<dl class="payee_details clearfix">
		<dt>Email</dt>
		<dd><?php echo $invoice->payer_class->user_email; ?></dd>

		<dt>Username</dt>
		<dd><?php echo $invoice->payer_class->user_nicename; ?></dd>

		<dt>First Name</dt>
		<dd><?php echo $invoice->payer_class->first_name; ?></dd>

		<dt>Last Name</dt>
		<dd><?php echo $invoice->payer_class->last_name; ?></dd>
	</dl>
	<?php	
}

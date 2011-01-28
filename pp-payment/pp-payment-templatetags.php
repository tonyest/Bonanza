<?php
/**
 * @package Prospress
 * @subpackage Feedback
 */

/**
 * Creates an anchor tag linking to the user's payments, optionally prints.
 * 
 */
function pp_the_payments_url( $desc = "View Payments", $echo = '' ) {

	$payment_tag = "<a href='" . pp_get_payments_url() . "' title='$desc'>$desc</a>";

	if( $echo == 'echo' )
		echo $payment_tag;
	else
		return $payment_tag;
}


/**
 * Gets the url to the user's feedback table.
 * 
 */
function pp_get_payments_url() {

	 return admin_url( 'admin.php?page=outgoing_invoices' );
}

<?php
/**
 * Prospress Payment
 * 
 * Money - the great enabler of trade. This component provides a system for traders in a Prospress market place 
 * to exchange money in return to posted items/services.
 * 
 * @package Prospress
 * @author Brent Shepherd
 * @version 0.1
 */

if ( !defined( 'PP_PAYMENTS_DB_VERSION'))
	define ( 'PP_PAYMENTS_DB_VERSION', '0003' );

if( !defined( 'PP_PAYMENT_DIR' ) )
	define( 'PP_PAYMENT_DIR', PP_PLUGIN_DIR . '/pp-payment' );
if( !defined( 'PP_PAYMENT_URL' ) )
	define( 'PP_PAYMENT_URL', PP_PLUGIN_URL . '/pp-payment' );

global $wpdb;

if ( !isset($wpdb->payments) || empty($wpdb->payments))
	$wpdb->payments = $wpdb->prefix . 'payments';
if ( !isset($wpdb->paymentsmeta) || empty($wpdb->paymentsmeta))
	$wpdb->paymentsmeta = $wpdb->prefix . 'paymentsmeta';
if ( !isset($wpdb->payments_log) || empty($wpdb->payments_log))
	$wpdb->payments_log = $wpdb->prefix . 'payments_log';

/**
 * The engine behind the payment system - TwinCitiesTech's WP Invoice modified for marketplace payments. 
 * All the payment system action happens in there. 
 */
require_once( PP_PAYMENT_DIR . '/pp-invoice.php' );

include_once( PP_PAYMENT_DIR . '/pp-payment-templatetags.php' );


/** 
 * Certain administration pages in Prospress provide a hook for other components to add an "action" link. This function 
 * determines and then outputs an appropriate payment/invoice action link, which may be any of send/view invoice or 
 * make payment.
 * 
 * The function receives the existing array of actions from the hook and adds to it an array with the url for 
 * performing a feedback action and label for outputting as the link text. 
 * 
 * @see bid_table_actions hook
 * @see winning_bid_actions hook
 * 
 * @param actions array existing actions for the hook
 * @param post_id int for identifying the post
 * @return array of actions for the hook, including the payment system's action
 */
function pp_add_payment_action( $actions, $post_id ) {
	global $user_ID, $blog_id, $wpdb;
	
	$post = get_post( $post_id );

	$is_winning_bidder = is_winning_bidder( $user_ID, $post_id );

	if ( $post->post_status != 'completed' || get_bid_count( $post_id ) == false || ( !$is_winning_bidder && $user_ID != $post->post_author ) ) 
		return $actions;

	$invoice_id = $wpdb->get_var( "SELECT id FROM $wpdb->payments WHERE post_id = $post_id" );
	$make_payment_url = add_query_arg( array( 'invoice_id' => $invoice_id ), 'admin.php?page=make_payment' );
	$invoice_url = add_query_arg( array( 'invoice_id' => $invoice_id ), 'admin.php?page=send_invoice' );
	$invoice_is_paid = pp_invoice_is_paid( $invoice_id );

	if ( $is_winning_bidder && !$invoice_is_paid ) {
		$actions[ 'make-payment' ] = array( 'label' => __( 'Make Payment', 'prospress' ), 
											'url' => $make_payment_url );
	} else if ( $user_ID == $post->post_author && !$invoice_is_paid ) {
		$actions[ 'send-invoice' ] = array( 'label' => __( 'Send Invoice', 'prospress' ),
											'url' => $invoice_url );
	} else {
		$actions[ 'view-invoice' ] = array( 'label' => __( 'View Invoice', 'prospress' ),
											'url' => $invoice_url );
	}

	return $actions;
}
add_filter( 'completed_post_actions', 'pp_add_payment_action', 10, 2 );
add_filter( 'bid_table_actions', 'pp_add_payment_action', 10, 2 );


// Generate invoice for a post of this market system type. 
// Automatically hooked on post completion.
function pp_generate_invoice( $post_id ) { //receive post ID from hook
	global $wpdb, $market_systems;

	$invoice_id = $wpdb->get_var( "SELECT id FROM " . $wpdb->payments . " WHERE post_id = '$post_id'" );

	if( $invoice_id != NULL )
		return $invoice_id;

	$type = get_post_type( $post_id );

	$winning_bid = $market_systems[ $type ]->get_winning_bid( $post_id );

	$payer_id 	= $winning_bid->bidder_id;
	$payee_id	= get_post( $post_id )->post_author;
	$amount		= $winning_bid->winning_bid_value;
	$status		= 'pending';

	$args = compact( 'post_id', 'payer_id', 'payee_id', 'amount', 'status', 'type' );

	return pp_invoice_create( $args );
}
add_action( 'post_completed', 'pp_generate_invoice' );


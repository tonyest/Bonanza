<?php
/**
 * An assortment of useful functions.
 * 
 * @package Prospress
 * @subpackage Feedback
 */

/**
 * Determines if a user has already given feedback on a post. 
 * 
 * @param $post_id the prospress post to check feedback records for
 * @param $user_id optionally specify if a feedback item has been place from a specified user. 
 */
function pp_post_has_feedback( $post_id, $user_id = NULL ){
	global $wpdb; 

	$query = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_parent = %d", $post_id );

	if( $user_id !== NULL )
		$query .= $wpdb->prepare( " AND post_author = %d ", $user_id );

	$feedback = $wpdb->get_var( $query );

	if( NULL != $feedback ){
		return true;		
	} else {
		return false;
	}
}


/**
 * Gets feedback optionally filtered with parameters matching the get_posts function.
 * 
 * @param array args an array of name value pairs that can be used to modify which feedback items are returned @see get_posts
 * @return array feedback items, or NULL if feedback doesn't exist
 */
function pp_get_feedback( $args = array() ){

	$args[ 'post_type' ] = 'feedback'; //brute force
	$args[ 'numberposts' ] = 0; //not all sorting/selection being done in sql

	$feedback = get_posts( $args );

	foreach( $feedback as $key => $feedback_item ){ // could be done with sql if didn't use get_posts

		$feedback[ $key ]->feedback_recipient = get_post_meta( $feedback[ $key ]->ID, 'feedback_recipient', true );
		if( array_key_exists( 'feedback_recipient', $args ) && $feedback[ $key ]->feedback_recipient != $args[ 'feedback_recipient' ] ){
			unset( $feedback[ $key ] );
			continue;
		}

		$feedback[ $key ]->feedback_score = get_post_meta( $feedback[ $key ]->ID, 'feedback_score', true );
		if( array_key_exists( 'feedback_score', $args ) && $feedback[ $key ]->feedback_score != $args[ 'feedback_score' ] ){
			unset( $feedback[ $key ] );
			continue;
		}
	}

	return $feedback;
}


function pp_users_feedback_count( $args ){
	global $wpdb;

	return count( pp_get_feedback( $args ) );
}


function pp_users_feedback_link( $user_id ){
	return "<a href='" . add_query_arg ( array( 'uid' => $user_id ), 'users.php?page=feedback' ) . "'> (" . pp_users_feedback_count(  array( 'feedback_recipient' => $user_id ) ) . ")</a>";
}

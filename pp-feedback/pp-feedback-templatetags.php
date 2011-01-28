<?php
/**
 * @package Prospress
 * @subpackage Feedback
 */

/**
 * Prints the feedback for an author. If user id is not specified, the function attempts 
 * 
 * @param $user_id optional the user id of the . 
 */
function the_users_feedback_items( $user_id = '' ){
	global $authordata;

	if( $user_id == '' )
		$user_id = $authordata->ID;

	$args = array( 'feedback_recipient' => $user_id, 'feedback_score' => 2 );

	echo '<ul>';
	echo '<li>' . pp_users_feedback_count( $args ) . ' ' . __( 'positive' ) . '</li>';
	$args[ 'feedback_score' ] = 1;
	echo '<li>' . pp_users_feedback_count( $args ) . ' ' . __( 'neutral' ) . '</li>';
	$args[ 'feedback_score' ] = 0;
	echo '<li>' . pp_users_feedback_count( $args ) . ' ' . __( 'negative' ) . '</li>';
	echo '</ul>';
}

/**
 * Prints the latest feedback comment a user received.
 * 
 * @param $user_id optionally specify if a feedback item has been place from a specified user. 
 */
function the_most_recent_feedback( $user_id = '' ){
	global $authordata;

	if( $user_id == '' )
		$user_id = $authordata->ID;

	$latest = pp_get_feedback( array( 'feedback_recipient' => $user_id ) );

	$latest = array_pop( $latest );

	if( $latest !== NULL ) {
		echo '<blockquote class="feedback-comment">' . $latest->post_content . '</blockquote>';
		echo '<div "feedback-author">';
		echo __( 'From: ', 'prospress' );
		echo get_userdata( $latest->post_author )->user_nicename;
		echo '</div>';
	} else {
		echo '<p>' . sprintf( __( '%s has not yet received any feedback.', 'prospress' ), get_userdata( $user_id )->user_nicename ) . '</p>';
	}
}


/**
 * Creates an anchor tag linking to the user's feedback table, optionally prints.
 * 
 */
function pp_the_feedback_url( $desc = "View Feedback", $echo = '' ) {
	if( current_user_can( 'edit_users' ) )
		$feedback_url = admin_url( 'users.php?page=feedback' );
	else
		$feedback_url = admin_url( 'profile.php?page=feedback' );

	$feedback_tag = "<a href='" . $feedback_url . "' title='$desc'>$desc</a>";

	if( $echo == 'echo' )
		echo $feedback_tag;
	else
		return $feedback_tag;
}

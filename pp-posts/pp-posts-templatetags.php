<?php

/**
 * Print the details of an individual post, including custom taxonomies.  
 * 
 * @param string $echo Optional, default 'echo'. If set to "echo" the function echo's the permalink, else, returns the permalink as a string. 
 * @return returns false if no index page set, true if echod the permalink or a string representing the permalink if 'echo' not set.
 */
function pp_get_the_term_list( $post_id = '' ){
	global $post, $market_systems;
	
	if( empty( $post_id ) )
		$post_id = $post->ID;

	$tax_types = $market_systems[ get_post_type( $post_id ) ]->taxonomy->get_taxonomies();

	if ( empty( $tax_types ) )
		return;

	foreach( $tax_types as $tax_name => $tax_type ){
		echo '<div class="pp-tax">';
		echo get_the_term_list( $post->ID, $tax_name, $tax_type[ 'labels' ][ 'singular_label' ] . ': ', ', ', '' );
		echo '</div>';
	}
}


/**
 * Get's the end time for a post. Can return it in GMT or user's timezone (specified by UTC offset). 
 * Can also return as either mysql date format or a unix timestamp.
 *
 * @param int $post_id Optional, default 0. The post id for which you want the max bid. 
 * @return returns false if post has no end time, or a string representing the time stamp or sql
 */
function get_post_end_time( $post_id, $format = 'timestamp', $timezone = 'gmt' ) {
	global $post;

	if( empty( $post_id ) )
		$post_id = $post->ID;

	$time = wp_next_scheduled( 'schedule_end_post', array( "ID" => $post_id ) );

	// If a post has not yet ended, use it's actual scheduled end time, if that doesn't exist, 
	// probably becasue the post has ended, get the post end time from the post_meta table.
	if( empty( $time ) )
		$time = strtotime( get_post_meta( $post_id, 'post_end_date_gmt', true ) );

	if( $time == false )
	 	return false;

	if( $timezone != 'gmt' ){
		$time = date( 'Y-m-d H:i:s', $time );
		$time = get_date_from_gmt( $time );
		$time = strtotime( $time );

	}
	if( $format != 'timestamp' ){
		if( $format == 'mysql' )
			$time = date( 'H:i Y/m/d', $time );
		else
			$time = date( $format, $time );
	}
	return $time;
}

/**
 * Prints the end time for a post. If the post is ending within a week, it will print
 * it as a countdown, eg. 2 days 1 hour 3 minutes. If the post ends in more than a week
 * it prints the date, in the user's time.
 *
 * @uses $post
 * @uses $wpdb
 *
 * @param int $post_id Optional, default 0. The post id for which you want the max bid. 
 * @return object Returns the row in the bids 
 */
function the_post_end_time( $post_id = '', $units = 3, $separator = ' ' ) {

	$post_end = get_post_end_time( $post_id, 'timestamp', 'gmt' );

	if( $post_end == false )
	 	echo __('Now', 'prospress' );
	elseif( $post_end - time() > 60 * 60 * 24 ) // Show date if ending more than a day in the future
		echo get_post_end_time( $post_id, 'g:ia', 'user' ) . $separator . get_post_end_time( $post_id, 'j-M-Y', 'user' );
	else
		echo pp_human_interval( $post_end - time(), $units, $separator );
}


/** 
 * Takes a period of time as a unix time stamp and returns a string 
 * describing how long the period of time is, eg. 2 weeks 1 day.
 * 
 * Inspired by WP Crontrol's Interval function
 * @param $time_period timestamp
 * @param $units int optional the depth of units ( seconds, minutes etc.) 
 * @param $separator string optional the separator displayed between units
 **/
function pp_human_interval( $time_period, $units = 3, $separator = ' ' ) {

	if( $time_period <= 0 ) {
	    return human_time_diff( time() - $time_period ) . ' ago';
	}

    // array of time period chunks
	$chunks = array(
    	array( 60 * 60 * 24 * 365 , _n_noop( '%s year', '%s years' ) ),
    	array( 60 * 60 * 24 * 30 , _n_noop( '%s month', '%s months' ) ),
    	array( 60 * 60 * 24 * 7, _n_noop( '%s week', '%s weeks' ) ),
    	array( 60 * 60 * 24 , _n_noop( '%s day', '%s days' ) ),
    	array( 60 * 60 , _n_noop( '%s hour', '%s hours' ) ),
    	array( 60 , _n_noop( '%s minute', '%s minutes' ) ),
    	array( 1 , _n_noop( '%s second', '%s seconds' ) ),
	);

	// 1st chunk
	for ($i = 0, $j = count($chunks); $i < $j; $i++) {

		$seconds = $chunks[$i][0];
		$name = $chunks[$i][1];

		// finding the biggest chunk (if the chunk fits, break)
		if ( ( $count = floor( $time_period / $seconds ) ) != 0 ) {
			break;
		}
	}

	// set output var
	$output = sprintf(_n($name[0], $name[1], $count), $count);

	// 2nd chunk
	if ( $i + 1 < $j && $units >= 2 ) {
		$seconds2 = $chunks[$i + 1][0];
		$name2 = $chunks[$i + 1][1];

		if ( ( $count2 = floor( ( $time_period - ( $seconds * $count ) ) / $seconds2) ) != 0 ) {
			// add to output var
			$output .= $separator.sprintf(_n($name2[0], $name2[1], $count2), $count2);
		}
	}

	// 3rd chunk (as long as it's not seconds or minutes)
	if ( $i + 2 < $j - 1 && $units >= 3 ) {
		$seconds3 = $chunks[$i + 2 ][0];
		$name3 = $chunks[$i + 2 ][1];

		if ( ( $count3 = floor( ( $time_period - ( $seconds * $count ) - ( $seconds2 * $count2 ) ) / $seconds3 ) ) != 0 ) {
			// add to output var
			$output .= $separator.sprintf( _n( $name3[0], $name3[1], $count3 ), $count3 );
		}
	}

	return $output;
}

?>
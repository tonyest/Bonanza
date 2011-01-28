<?php
/**
 * Print the bid form
 *
 * @since 0.1
 */
function the_bid_form( $post_id = '' ) {
	global $post, $market_systems;

	if ( empty( $post_id ) )
		$post_id = $post->ID;

	$market = $market_systems[ get_post_type( $post_id ) ];

	echo $market->bid_form();
}


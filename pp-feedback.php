<?php
/**
 * Prospress Feedback
 *
 * Leave feedback for other users on your prospress marketplace. 
 *
 * @package Prospress
 * @author Brent Shepherd
 * @version 0.1
 */

if ( !defined( 'PP_FEEDBACK_DB_VERSION' ))
	define ( 'PP_FEEDBACK_DB_VERSION', '0016' );
if ( !defined( 'PP_FEEDBACK_DIR' ))
	define( 'PP_FEEDBACK_DIR', PP_PLUGIN_DIR . '/pp-feedback' );
if ( !defined( 'PP_FEEDBACK_URL' ))
	define( 'PP_FEEDBACK_URL', PP_PLUGIN_URL . '/pp-feedback' );

require_once( PP_FEEDBACK_DIR . '/pp-feedback-functions.php' );

require_once( PP_FEEDBACK_DIR . '/pp-feedback-templatetags.php' );

include_once( PP_FEEDBACK_DIR . '/pp-feedback-widgets.php' );

/**
 * To save updating/installing the feedback tables when they already exist and are up-to-date, check 
 * the current feedback database version exists and is not of a prior version.
 * 
 * @uses pp_feedback_install to create the database table if it is not up to date
 **/
function pp_feedback_install() {
	global $wpdb;

	if ( !current_user_can( 'edit_plugins' ) )
		return false;

	if ( !get_site_option( 'pp_feedback_db_version' ) || get_site_option( 'pp_feedback_db_version' ) < PP_FEEDBACK_DB_VERSION ){
		update_site_option( 'pp_feedback_db_version', PP_FEEDBACK_DB_VERSION );
	}
}
add_action( 'pp_activation', 'pp_feedback_install' );


/**
 * Registers the feedback post type with WordPress
 * 
 **/
function pp_register_feedback_post_type(){
	$args = array(
			'label' 	=> __( 'Feedback', 'prospress' ),
			'public' 	=> true,
			'show_ui' 	=> false, // introduce later
			'rewrite' 	=> array( 'slug' => 'feedback', 'with_front' => false ),
			'show_in_nav_menus' => false,
			'exclude_from_search' => true,
			'capability_type' => 'prospress_post',
			'hierarchical' => true, // post parent is the post for which the feedback relates
			'supports' 	=> array(
							'title',
//							'excerpt',
							'editor',
							'revisions' ),
			'labels'	=> array( 'name'	=> __( 'Feedback', 'prospress' ),
							'singular_name'	=> __( 'Feedback', 'prospress' ),
							'add_new_item'	=> __( 'Provide Feedback', 'prospress' ),
							'edit_item'		=> __( 'Edit Feedback', 'prospress' ),
							'new_item'		=> __( 'New Feedback Item', 'prospress' ),
							'view_item'		=> __( 'View Feedback', 'prospress' ),
							'search_items'	=> __( 'Seach Feedback', 'prospress' ),
							'not_found'		=> __( 'No Feedback found', 'prospress' ),
							'not_found_in_trash' => __( 'No Feedback found in Trash', 'prospress' ),
							'parent_item_colon' => __('Feedback for post:') )
				);

		register_post_type( 'feedback', $args );
}
add_action( 'init', 'pp_register_feedback_post_type' );


/**
 * Adds the feedback admin page to the Profile/Users section
 **/
function pp_add_feedback_admin_pages() {
	if ( function_exists( 'add_submenu_page' ) )
		add_users_page( 'Feedback', 'Feedback', 'read', 'feedback', 'pp_feedback_controller' );
}
add_action( 'admin_menu', 'pp_add_feedback_admin_pages' );


/** 
 * Enqueues scripts and styles to the head of feedback admin pages.
 */
function pp_feedback_admin_head() {

	// Dashboard widget styles
	if( strpos( $_SERVER[ 'REQUEST_URI' ], 'wp-admin/index.php' ) !== false || preg_match ( '/wp-admin\/$/', $_SERVER[ 'REQUEST_URI' ] ) )
		wp_enqueue_style( "prospress-feedback", PP_FEEDBACK_URL . "/pp-feedback.css" );
}
add_action( 'admin_menu', 'pp_feedback_admin_head' );


/** 
 * Adds feedback history column headings to the built in print_column_headers function for the feedback admin page. 
 *
 * @see get_column_headers()
 */
function pp_feedback_columns_admin(){
	global $wp_post_statuses;

 	if( strpos( $_SERVER[ 'REQUEST_URI' ], 'given' ) !== false ) {
		$feedback_columns[ 'feedback_recipient' ] = __( 'For', 'prospress' );
		$feedback_columns[ 'role' ] = __( 'Receipient\'s Role', 'prospress' );
	} else {
		$feedback_columns[ 'feedback_author' ] = __( 'From', 'prospress' );
		$feedback_columns[ 'role' ] = __( 'Your Role', 'prospress' );
	}

	$feedback_columns = array_merge( $feedback_columns, array(
		'feedback_score' => __( 'Score', 'prospress' ),
		'feedback_comment' => __( 'Comment', 'prospress' ),
		'feedback_date' => __( 'Date', 'prospress' ),
		'post_id' => __( 'Post', 'prospress' )
	) );

	return $feedback_columns;
}
add_filter( 'manage_feedback_columns', 'pp_feedback_columns_admin' );


/** 
 * Outputs all the feedback items for the feedback admin page. 
 *
 * @param feedback array optional the feedback for a user
 */
function pp_feedback_rows( $feedback ){
	global $user_ID;

	if( !empty( $feedback ) ){
		$style = '';
		foreach ( $feedback as $feedback_item ) {
		 	$user_of_interest = ( strpos( $_SERVER[ 'REQUEST_URI' ], 'given' ) == false ) ? $feedback_item->post_author : $feedback_item->feedback_recipient;
			echo "<tr class='feedback $style' >";
			echo "<td scope='row'>" . ( ( $user_ID == $user_of_interest ) ? 'You' : get_userdata( $user_of_interest )->user_nicename ) . pp_users_feedback_link( $user_of_interest ) . "</td>";
			echo "<td>" . pp_get_users_role( $feedback_item->post_parent, $user_of_interest ) . "</td>";
			echo "<td>" . (( $feedback_item->feedback_score == 2) ? __("Positive", 'prospress' ) : (( $feedback_item->feedback_score == 1) ? __("Neutral", 'prospress' ) : __("Negative", 'prospress' ))) . "</td>";
			echo "<td>" . $feedback_item->post_content . "</td>";
			echo "<td>" . mysql2date( __( 'd M Y', 'prospress' ), $feedback_item->post_date ) . "</td>";
			echo "<td><a href='" . get_permalink( $feedback_item->post_parent ) . "' target='blank'>" . get_post( $feedback_item->post_parent )->post_title . "</a></td>";
			echo "</tr>";
			$style = ( 'alternate' == $style ) ? '' : 'alternate';
		}
	} else {
		echo '<tr><td colspan="5">You have no feedback.</td>';
	}
}


/**
 * Central controller to determine which functions are called and what view is output to the screen.
 * 
 * @uses pp_feedback_form_submit() to process a feedback form submission
 * @uses pp_edit_feedback() to add or edit feedback items upon submission
 **/
function pp_feedback_controller() {
	global $wpdb, $user_ID;

	get_currentuserinfo();

	$title = __( 'Feedback', 'prospress' );

	if( $_POST[ 'feedback_submit' ] ){
		extract( pp_feedback_form_submit( $_POST ) );
		include_once( PP_FEEDBACK_DIR . '/pp-feedback-form-view.php' );
	} elseif ( $_GET[ 'action' ] == 'give' || $_GET[ 'action' ] == 'edit' ){

		get_currentuserinfo();

		$_GET[ 'post' ] = (int)$_GET[ 'post' ];
		$post = get_post( $_GET[ 'post' ] );
		pp_can_edit_feedback( $post );
		$bidder_id = get_winning_bidder( $post->ID );
		$is_winning_bidder = is_winning_bidder( $user_ID, $post->ID );

		if ( pp_post_has_feedback( $post->ID, $user_ID ) ) { //user already left feedback for post
			$feedback = pp_get_feedback( array( 'post_parent' => $post->ID, 'author' => $user_ID ) );
			if( get_option( 'edit_feedback' ) != 'true' ){
				$feedback[ 'disabled' ] = 'disabled="disabled"';
				$feedback[ 'title' ] = __( 'Feedback', 'prospress' );
			} else {
				$feedback[ 'title' ] = __( 'Edit Feedback', 'prospress' );
				$disabled = '';
			}
		} else {
			$title = __( 'Give Feedback', 'prospress' );
			$disabled = '';
			$feedback = compact( 'post_id', 'from_user_id', 'for_user_id', 'role', 'disabled', 'title' );
		}

		if ( $post->post_author == $user_ID && !$is_winning_bidder ){
			$feedback_recipient = $bidder_id;
		} else {
			$feedback_recipient = $post->post_author;
		}

		include_once( PP_FEEDBACK_DIR . '/pp-feedback-form-view.php'  );
	} else {
		pp_feedback_history_admin();
	}
}


/**
 * Ensures the logged in user can give feedback on a post and that the post status is such that 
 * a feedback item is due. 
 *
 * @todo wp_die is a pretty nasty way to handle this simple error, better to just output an error message on the feedback page.
 *
 * @param bidder_id int the id of the winning bidder.
 * @param feedback_author int the user who is trying to give feedback.
 * @param post object the post for which the users should be validated against, including post status and post author.
 * @param post int|object either the post id or the post object for which the users should be validated against
 **/
function pp_can_edit_feedback( $post, $feedback_id = NULL ) {
	global $user_ID;

	get_currentuserinfo();

	if( is_numeric( $post ) )
		$post = get_post( $post );

	$bidder_id = get_winning_bidder( $post->ID );

	if( empty( $bidder_id ) )
		wp_die( __( 'Error: could not determine winning bidder.', 'prospress' ) );
	elseif( $bidder_id == $user_ID && $user_ID == $post->post_author )
		wp_die( __( 'You can not leave feedback for yourself, in fact, you should not have been able to win your own post!', 'prospress' ) );
	elseif ( $user_ID != $post->post_author && !is_winning_bidder( $user_ID, $post->ID ) )
		wp_die( __( 'You can not leave feedback for this post. It appears you are neither the author of the post nor the winning bidder.', 'prospress' ) );
	elseif ( NULL == $post->post_status || 'completed' != $post->post_status )
		wp_die( __( 'You can not leave feedback for this post. The post has either not completed or does not exist.', 'prospress' ) );
	else
		return true;
}


/**
 * Performs submission process for the feedback form.
 *
 * @param feedback array
 **/
function pp_feedback_form_submit( $feedback ) {
	global $wpdb, $user_ID;

	get_currentuserinfo();

	//error_log('in pp_feedback_form_submit, feedback = ' . print_r( $feedback, true ) );

	pp_can_edit_feedback( $feedback[ 'post_id' ] );

	if( pp_post_has_feedback( $feedback[ 'post_id' ], $user_ID ) ){

		if( get_option( 'edit_feedback' ) != 'true' ){ // user trying to edit feedback when not allowed
			$feedback = pp_get_feedback( array( 'post_parent' => $feedback[ 'post_id' ], 'author' => $user_ID ) );
			$feedback[ 'feedback_msg' ] = __( 'You are not allowed to edit this feedback.', 'prospress' );
		} else {
			pp_update_feedback( $feedback );
			$feedback[ 'feedback_msg' ] = __( 'Feedback Submitted.', 'prospress' );
		}
	} else {
		pp_insert_feedback( $feedback );
		$feedback[ 'feedback_msg' ] = __( 'Feedback Submitted.', 'prospress' );
	}

	return $feedback;
}


/**
 * Adds a new feedback item into the posts table. 
 *
 * @param feedback array with feedback fields conforming to the column structure of the feedback table.
 * @return int the ID of the updated feedback item
 **/
function pp_update_feedback( $feedback ) {
	global $user_ID;

	if( !isset( $feedback[ 'feedback_author' ] ) )
		$feedback[ 'feedback_author' ] = $user_ID;

	if( pp_post_has_feedback( $feedback[ 'post_id' ], $feedback[ 'feedback_author' ] ) ) {

		// First, get all of the original fields
		$existing_feedback = pp_get_feedback( array( 'post_parent' => $feedback[ 'post_id' ], 'author' => $feedback[ 'feedback_author' ] ) );

		// Escape data pulled from DB.
		$existing_feedback = add_magic_quotes($existing_feedback);

		// Drafts shouldn't be assigned a date unless explicitly done so by the user
		if ( isset( $existing_feedback['post_status'] ) && in_array($existing_feedback['post_status'], array('draft', 'pending', 'auto-draft')) && 
				empty($feedback['edit_date']) && ('0000-00-00 00:00:00' == $existing_feedback['post_date_gmt'] ) )
			$clear_date = true;
		else
			$clear_date = false;

		// Merge old and new fields with new fields overwriting old ones.
		$feedback = array_merge($existing_feedback, $feedback);

		if ( $clear_date ) {
			$feedback['post_date'] = current_time('mysql');
			$feedback['post_date_gmt'] = '';
		}
	}

	return pp_insert_feedback( $feedback );
}


/**
 * Adds a new feedback item into the posts table. 
 *
 * @param feedback array with feedback fields conforming to the column structure of the feedback table.
 * @return int the ID of the newly inserted feedback item
 **/
function pp_insert_feedback( $feedback ) {
	global $wpdb, $user_ID;

	if( !isset( $feedback[ 'feedback_author' ] ) )
		$feedback[ 'feedback_author' ] = $user_ID;

	$feedback_defaults = array(
		'post_status'		=> 'publish',
		'comment_status'	=> 'closed',
		'ping_status' 		=> 'closed',
		'post_type' 		=> 'feedback',
		'post_name' 		=> $feedback[ 'feedback_author' ] . '-feedback-' . $feedback[ 'feedback_recipient' ],
		'post_title' 		=> apply_filters( 'feedback_item_title', sprintf( __( "Feedback for %s from %s ", 'prospress' ), get_userdata( $feedback[ 'feedback_recipient' ] )->user_nicename, get_userdata( $feedback[ 'feedback_author' ] )->user_nicename ) )
		);

	$feedback_post = array(
		'post_author' 	=> $feedback[ 'feedback_author' ],
		'post_content' 	=> $feedback[ 'feedback_comment' ],
		'post_parent' 	=> $feedback[ 'post_id' ],
	);

	$feedback_post = wp_parse_args( $feedback_post, $feedback_defaults );

	// user trying to edit feedback when not allowed
	if( pp_post_has_feedback( $feedback[ 'post_id' ], $feedback[ 'feedback_author' ] ) && get_option( 'edit_feedback' ) != 'true' ) 
			wp_die( __( 'You are not allowed to edit this feedback.', 'prospress' ) );

	$feedback_id = wp_insert_post( $feedback_post );

	update_post_meta( $feedback_id, 'feedback_recipient', $feedback[ 'feedback_recipient' ] );
	update_post_meta( $feedback_id, 'feedback_score', $feedback[ 'feedback_score' ] );

	return $feedback_id;
}


/** 
 * Certain administration pages in Prospress provide a hook for other components to add an "action" link. This function 
 * determines and then outputs an appropriate feedback action link, which may be any of give, edit or view feedback. 
 * 
 * The function receives the existing array of actions from the hook and adds to it an array with the url for 
 * performing a feedback action and label for outputting as the link text. 
 * 
 * @see completed_post_actions hook
 * @see bid_table_actions hook
 * 
 * @param actions array existing actions for the hook
 * @param post_id int for identifying the post
 * @return array of actions for the hook, including the feedback action
 */
function pp_add_feedback_action( $actions, $post_id ) {
	global $user_ID, $blog_id;
	
	get_currentuserinfo();

	$post = get_post( $post_id );

	$is_winning_bidder = is_winning_bidder( $user_ID, $post_id );

	if ( $post->post_status != 'completed' || get_bid_count( $post_id ) == false || ( !$is_winning_bidder && $user_ID != $post->post_author && !is_super_admin() ) ) 
		return $actions;

	$action_details[ 'url' ] =  ( is_super_admin() ) ? 'users.php?page=feedback' : 'profile.php?page=feedback';

	if ( is_super_admin() && !$is_winning_bidder && $user_ID != $post->post_author ) {
		if( pp_post_has_feedback( $post_id ) ) { // Admin isn't bidder or author
			$action_details[ 'label' ] = __( 'View Feedback', 'prospress' );
			$actions[ 'view' ] = $action_details;
		}
	} elseif ( !pp_post_has_feedback( $post_id, $user_ID ) ) {
		$action_details[ 'label' ] = __( 'Give Feedback', 'prospress' );
		$actions[ 'give' ] = $action_details;
	} else if ( get_option( 'edit_feedback' ) == 'true' ) {
		$action_details[ 'label' ] = __( 'Edit Feedback', 'prospress' );
		$actions[ 'edit' ] = $action_details;
	} else {
		$action_details[ 'label' ] = __( 'View Feedback', 'prospress' );
		$actions[ 'view' ] = $action_details;
	}

	return $actions;
}
add_filter( 'completed_post_actions', 'pp_add_feedback_action', 10, 2 );
add_filter( 'bid_table_actions', 'pp_add_feedback_action', 10, 2 );


/** 
 * A central function to determine feedback history for a user and display the table of that user's feedback. 
 * 
 * @param user_id int optional used to determine whose feedback items to get
 */
function pp_feedback_history_admin( $user_id = '' ) {
	global $wpdb, $user_ID;

	if( empty( $user_id ) ){
		get_currentuserinfo();
		$user_id = $user_ID;
	}

	if( isset( $_GET[ 'uid' ] ) && $_GET[ 'uid' ] != $user_id ){
		if( isset( $_GET[ 'post' ] ) ){
			$feedback = pp_get_feedback( array( 'author' => $_GET[ 'uid' ], 'feedback_recipient' => $_GET[ 'uid' ], 'post_parent' => $_GET[ 'post' ] ) );
			$title = sprintf( __( 'Feedback for %1$s on Post %2$d', 'prospress' ), get_userdata( $_GET[ 'uid' ] )->user_nicename, $_GET[ 'post' ] );
		} else if( $_GET[ 'filter' ] == 'given' ){
			$feedback = pp_get_feedback( array( 'author' => $_GET[ 'uid' ] ) );
			$title = sprintf( __( 'Feedback Given by %s', 'prospress' ), get_userdata( $_GET[ 'uid' ] )->user_nicename );
		} else {
			$feedback = pp_get_feedback( array( 'feedback_recipient' => $_GET[ 'uid' ] ) );
			$title = sprintf( __( 'Feedback Received by %s', 'prospress' ), get_userdata( $_GET[ 'uid' ] )->user_nicename );
		}
		$user_id = $_GET[ 'uid' ];
	} else if( isset( $_GET[ 'post' ] ) ){
		$feedback = pp_get_feedback( array( 'author' => $user_id, 'feedback_recipient' => $user_id, 'post_parent' => $_GET[ 'post' ] ) );
		$title = sprintf( __( 'Feedback on Post ', 'prospress' ), $_GET[ 'post' ] );
	} else if( $_GET[ 'filter' ] == 'given' ){
		$feedback = pp_get_feedback( array( 'author' => $user_id ) );
		$title = __( "Feedback You've Given" , 'prospress' );
	} else {
		$feedback = pp_get_feedback( array( 'feedback_recipient' => $user_id ) );
		$title = __( 'Your Feedback', 'prospress' );
	}

	include_once( PP_FEEDBACK_DIR . '/pp-feedback-table-view.php' );
}


/**
 * Displays the fields for handling feedback options in the Core Prospress Settings admin page.
 *
 * @see pp_settings_page()
 **/
function pp_feedback_settings_section() { 
	$edit_feedback = get_option( 'edit_feedback' );
	?>
	<h3><?php _e( 'Feedback' , 'prospress' )?></h3>
	<p><?php _e( 'Allowing feedback to be amended helps to make it more accurate. Mistakes happen and circumstances change.' , 'prospress' ); ?></p>
	<label for='edit_feedback'>
		<input type='checkbox' value='true' name='edit_feedback' id='edit_feedback' <?php checked( (boolean)$edit_feedback ); ?> />
		  <?php _e( 'Allow feedback to be revised.' , 'prospress' ); ?>
	</label>
<?php
}
//vNext
//add_action( 'pp_core_settings_page', 'pp_feedback_settings_section' );


/**
 * Tells Prospress core to save this item upon submission of the Prospress Settings page.
 *
 * @param array with the existing whitelist of options for the Prospress settings page.
 **/
function pp_feedback_admin_option( $whitelist_options ) {

	$whitelist_options[ 'general' ][] = 'edit_feedback';

	return $whitelist_options;
}
add_filter( 'pp_options_whitelist', 'pp_feedback_admin_option' );


/**
 * Clean up if the plugin when deleted by removing feedback related options and database tables.
 * 
 **/
function pp_feedback_uninstall() {
	global $wpdb;

	if ( !current_user_can( 'edit_plugins' ) || !function_exists( 'delete_site_option' ) )
		return false;

	delete_site_option( 'pp_feedback_db_version' );

	// beta backward compatibility
	$wpdb->query( "DROP TABLE IF EXISTS $wpdb->feedback" );
	$wpdb->query( "DROP TABLE IF EXISTS $wpdb->feedbackmeta" );
}
add_action( 'pp_uninstall', 'pp_feedback_uninstall' );

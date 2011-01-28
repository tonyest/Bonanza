<?php
/**
 * Prospress Posts
 *
 * Adds a marketplace posting system along side WordPress.
 *
 * @package Prospress
 * @subpackage Posts
 * @author Brent Shepherd
 * @version 0.1
 */

if ( !defined( 'PP_POSTS_DIR' ) )
	define( 'PP_POSTS_DIR', PP_PLUGIN_DIR . '/pp-posts' );
if ( !defined( 'PP_POSTS_URL' ) )
	define( 'PP_POSTS_URL', PP_PLUGIN_URL . '/pp-posts' );

require_once( PP_POSTS_DIR . '/pp-post.class.php' );

require_once( PP_POSTS_DIR . '/pp-posts-templatetags.php' );

require_once( PP_POSTS_DIR . '/pp-capabilities.php' );

include_once( PP_POSTS_DIR . '/pp-post-sort.php' );

include_once( PP_POSTS_DIR . '/pp-post-widgets.php' );

if( is_using_custom_taxonomies() ){
	include_once( PP_POSTS_DIR . '/pp-taxonomy.class.php' );
	include_once( PP_POSTS_DIR . '/qmt/query-multiple-taxonomies.php' );
}

/**
 * Sets up Prospress environment with any settings required and/or shared across the 
 * other components. 
 *
 * @uses is_site_admin() returns true if the current user is a site admin, false if not.
 * @uses add_submenu_page() WP function to add a submenu item.
 * @uses get_role() WP function to get the administrator role object and add capabilities to it.
 *
 * @global wpdb $wpdb WordPress DB access object.
 * @global WP_Rewrite $wp_rewrite WordPress Rewrite Component.
 */
function pp_posts_install(){

	pp_add_default_caps();

}
add_action( 'pp_activation', 'pp_posts_install' );


/** 
 * Default capabilities the core WP roles have for Prospress custom post types. 
 *
 * Essentially replicates each role's capability for standard post types.
 * 
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 */
function pp_add_default_caps(){
	global $wp_roles;

	foreach ( $wp_roles->get_names() as $key => $role ) {

		$role = get_role( $key );

		if( $role->name == 'administrator' || $role->name == 'editor' ) {
			$role->add_cap( 'edit_others_prospress_posts' );
		} else {
			$role->remove_cap( 'edit_others_prospress_posts' );
		}

		if( $role->name == 'administrator' || $role->name == 'editor' || $role->name == 'author' ) {
			$role->add_cap( 'edit_published_prospress_posts' );
			$role->add_cap( 'edit_private_prospress_posts' );
			$role->add_cap( 'delete_prospress_post' );
			$role->add_cap( 'delete_published_prospress_posts' );
		} else {
			$role->remove_cap( 'publish_prospress_posts' );
			$role->remove_cap( 'edit_prospress_posts' );
			$role->remove_cap( 'edit_published_prospress_posts' );
			$role->remove_cap( 'edit_private_prospress_posts' );
			$role->remove_cap( 'delete_prospress_post' );
			$role->remove_cap( 'delete_published_prospress_posts' );
		}

		if( $role->name == 'administrator' || $role->name == 'editor' || $role->name == 'author' || $role->name == 'contributor' ) {
			$role->add_cap( 'publish_prospress_posts' );
			$role->add_cap( 'edit_prospress_posts' );
		} else {
			$role->remove_cap( 'publish_prospress_posts' );
			$role->remove_cap( 'edit_prospress_posts' );
		}

		$role->add_cap( 'read_private_prospress_posts' );
		$role->add_cap( 'read_prospress_posts' );
	}
}


/** 
 * When a Prospress post is saved, this function saves the custom information specific this special
 * type of post e.g. end time. 
 * 
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 */
function pp_post_save_postdata( $post_id, $post ) {
	global $wpdb;

	if( wp_is_post_revision( $post_id ) )
		$post_id = wp_is_post_revision( $post_id );

	if ( empty( $_POST ) || 'page' == $_POST['post_type'] ) {
		return $post_id;
	} else if ( !current_user_can( 'edit_post', $post_id )) {
		return $post_id;
	} else if ( !isset( $_POST['yye'] ) ){ // Make sure an end date is submitted (not submitted with quick edits etc.)
		return $post_id;
	}

	$yye = $_POST['yye'];
	$mme = $_POST['mme'];
	$dde = $_POST['dde'];
	$hhe = $_POST['hhe'];
	$mne = $_POST['mne'];
	$sse = $_POST['sse'];	
	$yye = ( $yye <= 0 ) ? date('Y' ) : $yye;
	$mme = ( $mme <= 0 ) ? date('n' ) : $mme;
	$dde = ( $dde > 31 ) ? 31 : $dde;
	$dde = ( $dde <= 0 ) ? date('j' ) : $dde;
	$hhe = ( $hhe > 23 ) ? $hhe -24 : $hhe;
	$mne = ( $mne > 59 ) ? $mne -60 : $mne;
	$sse = ( $sse > 59 ) ? $sse -60 : $sse;
	$post_end_date = sprintf( "%04d-%02d-%02d %02d:%02d:%02d", $yye, $mme, $dde, $hhe, $mne, $sse );

	$now_gmt = current_time( 'mysql', true ); // get current GMT
	$post_end_date_gmt = get_gmt_from_date( $post_end_date );
	$original_post_end_date_gmt = get_post_end_time( $post_id, 'mysql' );

	if( !$original_post_end_date_gmt || $post_end_date_gmt != $original_post_end_date_gmt ){
		update_post_meta( $post_id, 'post_end_date', $post_end_date );
		update_post_meta( $post_id, 'post_end_date_gmt', $post_end_date_gmt);		
	}

	if( $post_end_date_gmt <= $now_gmt && $_POST['save'] != 'Save Draft' ){
		wp_unschedule_event( strtotime( $original_post_end_date_gmt ), 'schedule_end_post', array( 'ID' => $post_id ) );
		pp_end_post( $post_id );
	} else {
		wp_unschedule_event( strtotime( $original_post_end_date_gmt ), 'schedule_end_post', array( 'ID' => $post_id ) );

		if( $post_status != 'draft' ){
			pp_schedule_end_post( $post_id, strtotime( $post_end_date_gmt ) );
			do_action( 'publish_end_date_change', $post_status, $post_end_date );
		}
	}
	update_option( 'pp_show_welcome', 'false' );
}
add_action( 'save_post', 'pp_post_save_postdata', 10, 2 );


/**
 * Schedules a post to end at a given post end time. 
 *
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 *
 * @uses wp_schedule_single_event function
 * @param post_id for identifying the post
 * @param post_end_time_gmt a unix time stamp of the gmt date/time the post should end
 */
function pp_schedule_end_post( $post_id, $post_end_time_gmt ) {
	wp_schedule_single_event( $post_end_time_gmt, 'schedule_end_post', array( 'ID' => $post_id ) );
}


/**
 * Changes the status of a given post to 'completed'. This function is added to the
 * schedule_end_post hook.
 *
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 *
 * @uses wp_unschedule_event function
 */
function pp_end_post( $post_id ) {
	global $wpdb;

	if( wp_is_post_revision( $post_id ) )
		$post_id = wp_is_post_revision( $post_id );

	$post_status = apply_filters( 'post_end_status', 'completed' );

	$wpdb->update( $wpdb->posts, array( 'post_status' => $post_status ), array( 'ID' => $post_id ) );
	do_action( 'post_completed', $post_id );
}
add_action('schedule_end_post', 'pp_end_post' );


/**
 * Unschedules the completion of a post in WP Cron.
 *
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 *
 * @uses wp_unschedule_event function
 */
function pp_unschedule_post_end( $post_id ) {
	$next = wp_next_scheduled( 'schedule_end_post', array('ID' => $post_id) );
	wp_unschedule_event( $next, 'schedule_end_post', array('ID' => $post_id) );
}
add_action( 'deleted_post', 'pp_unschedule_post_end' );


/**
 * What happens to Prospress posts when they end? They need to be marked with a special status. 
 * This function registers the "Completed" status to designate to posts upon their completion. 
 * 
 * Typically, a post will earns this status with the passing of a given period of time. However, 
 * eventually a post may complete due to a number of other circumstances, for example, the post 
 * may choose the winning bidder or a set goal may be achieved.
 *
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 *
 * @uses pp_register_completed_status function
 */
function pp_register_completed_status() {
	register_post_status(
	       'completed',
	       array('label' => _x( 'Completed', 'post' ),
				'label_count' => _n_noop('Completed <span class="count">(%s)</span>', 'Completed <span class="count">(%s)</span>' ),
				'public' => true,
				'show_in_admin_all' => false,
				'show_in_admin_all_list' => true,
				'show_in_admin_status_list' => true,
				'publicly_queryable' => false,
				'exclude_from_search' => true,
				'capability_type' => 'prospress_post'
	       )
	);
}
add_action( 'init', 'pp_register_completed_status' );


/**
 * Display custom Prospress post end date/time form fields.
 *
 * This code is sourced from the edit-form-advanced.php file. Additional code is added for 
 * dealing with 'completed' post status. 
 *
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 */
function pp_post_submit_meta_box() {
	global $action, $wpdb, $post;

	if( !is_pp_post_admin_page() )
		return;

	$datef = __( 'M j, Y @ G:i' );

	//Set up post end date label
	if ( 'completed' == $post->post_status ) // already finished
		$end_stamp = __('Ended: <b>%1$s</b>', 'prospress' );
	else
		$end_stamp = __('End on: <b>%1$s</b>', 'prospress' );

	//Set up post end date and time variables
	if ( 0 != $post->ID ) {
		$post_end = get_post_end_time( $post->ID, 'mysql', 'user' );

		if ( !empty( $post_end ) && '0000-00-00 00:00:00' != $post_end )
			$end_date = date_i18n( $datef, strtotime( $post_end ) );
	}

	// Default to one week if post end date is not set
	if ( !isset( $end_date ) ) {
		$end_date = date_i18n( $datef, strtotime( gmdate( 'Y-m-d H:i:s', ( time() + 604800 + ( get_option( 'gmt_offset' ) * 3600 ) ) ) ) );
	}
	?>
	<div class="misc-pub-section curtime misc-pub-section-last">
		<span id="endtimestamp">
		<?php printf( $end_stamp, $end_date); ?></span>
		<a href="#edit_endtimestamp" class="edit-endtimestamp hide-if-no-js" tabindex='4'><?php ('completed' != $post->post_status) ? _e('Edit', 'prospress' ) : _e('Extend', 'prospress' ); ?></a>
		<div id="endtimestampdiv" class="hide-if-js">
			<?php pp_touch_end_time( ( $action == 'edit' ), 5 ); ?>
		</div>
	</div><?php
}
add_action('post_submitbox_misc_actions', 'pp_post_submit_meta_box' );


/**
 * Copy of the WordPress "touch_time" template function for use with end time, instead of start time
 *
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 */
function pp_touch_end_time( $edit = 1, $tab_index = 0, $multi = 0 ) {
	global $wp_locale, $post, $comment;

	$post_end_date_gmt = get_post_end_time( $post->ID, 'mysql' );

	$edit = ( in_array( $post->post_status, array('draft', 'pending' ) ) && (!$post_end_date_gmt || '0000-00-00 00:00:00' == $post_end_date_gmt ) ) ? false : true;

	$tab_index_attribute = '';
	if ( (int) $tab_index > 0 )
		$tab_index_attribute = " tabindex=\"$tab_index\"";

	$time_adj = time() + ( get_option( 'gmt_offset' ) * 3600 );
	$time_adj_end = time() + 604800 + ( get_option( 'gmt_offset' ) * 3600 );

	$post_end_date = get_post_end_time( $post->ID, 'mysql', 'user' );
	if(empty( $post_end_date))
		$post_end_date = gmdate( 'Y-m-d H:i:s', ( time() + 604800 + ( get_option( 'gmt_offset' ) * 3600 ) ) );

	$dde = ( $edit) ? mysql2date( 'd', $post_end_date, false ) : gmdate( 'd', $time_adj_end );
	$mme = ( $edit) ? mysql2date( 'm', $post_end_date, false ) : gmdate( 'm', $time_adj_end );
	$yye = ( $edit) ? mysql2date( 'Y', $post_end_date, false ) : gmdate( 'Y', $time_adj_end );
	$hhe = ( $edit) ? mysql2date( 'H', $post_end_date, false ) : gmdate( 'H', $time_adj_end );
	$mne = ( $edit) ? mysql2date( 'i', $post_end_date, false ) : gmdate( 'i', $time_adj_end );
	$sse = ( $edit) ? mysql2date( 's', $post_end_date, false ) : gmdate( 's', $time_adj_end );

	$cur_dde = gmdate( 'd', $time_adj );
	$cur_mme = gmdate( 'm', $time_adj );
	$cur_yye = gmdate( 'Y', $time_adj );
	$cur_hhe = gmdate( 'H', $time_adj );
	$cur_mne = gmdate( 'i', $time_adj );
	$cur_sse = gmdate( 's', $time_adj );

	$month = "<select " . ( $multi ? '' : 'id="mme" ' ) . "name=\"mme\"$tab_index_attribute>\n";
	for ( $i = 1; $i < 13; $i = $i +1 ) {
		$month .= "\t\t\t" . '<option value="' . zeroise( $i, 2) . '"';
		if ( $i == $mme )
			$month .= ' selected="selected"';
		$month .= '>' . $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) ) . "</option>\n";
	}
	$month .= '</select>';

	$day = '<input type="text" ' . ( $multi ? '' : 'id="dde" ' ) . 'name="dde" value="' . $dde . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';
	$year = '<input type="text" ' . ( $multi ? '' : 'id="yye" ' ) . 'name="yye" value="' . $yye . '" size="4" maxlength="4"' . $tab_index_attribute . ' autocomplete="off" />';
	$hour = '<input type="text" ' . ( $multi ? '' : 'id="hhe" ' ) . 'name="hhe" value="' . $hhe . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';
	$minute = '<input type="text" ' . ( $multi ? '' : 'id="mne" ' ) . 'name="mne" value="' . $mne . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';
	/* translators: 1: month input, 2: day input, 3: year input, 4: hour input, 5: minute input */
	printf(__('%1$s%2$s, %3$s @ %4$s : %5$s' ), $month, $day, $year, $hour, $minute);

	echo '<input type="hidden" id="sse" name="sse" value="' . $sse . '" />';

	if ( $multi ) return;

	echo "\n\n";
	foreach ( array('mme', 'dde', 'yye', 'hhe', 'mne', 'sse' ) as $timeunit ) {
		echo '<input type="hidden" id="hidden_' . $timeunit . '" name="hidden_' . $timeunit . '" value="' . $$timeunit . '" />' . "\n";
		$cur_timeunit = 'cur_' . $timeunit;
		echo '<input type="hidden" id="'. $cur_timeunit . '" name="'. $cur_timeunit . '" value="' . $$cur_timeunit . '" />' . "\n";
	}
?>

<p>
	<a href="#edit_endtimestamp" class="save-endtimestamp hide-if-no-js button"><?php _e('OK', 'prospress' ); ?></a>
	<a href="#edit_endtimestamp" class="cancel-endtimestamp hide-if-no-js"><?php _e('Cancel', 'prospress' ); ?></a>
</p>
<?php
}


/** 
 * Enqueues scripts and styles to the head of Prospress post admin pages. 
 * 
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 */
function pp_posts_admin_head() {

	if( !is_pp_post_admin_page() )
		return;

	if( strpos( $_SERVER['REQUEST_URI'], 'post.php' ) !== false || strpos( $_SERVER['REQUEST_URI'], 'post-new.php' ) !== false ) {
		wp_enqueue_script( 'prospress-post', PP_POSTS_URL . '/pp-post-admin.js', array('jquery' ) );
		wp_localize_script( 'prospress-post', 'ppPostL10n', array(
			'endedOn' => __('Ended on:', 'prospress' ),
			'endOn' => __('End on:', 'prospress' ),
			'end' => __('End', 'prospress' ),
			'update' => __('Update', 'prospress' ),
			'repost' => __('Repost', 'prospress' ),
			));
		wp_enqueue_style( 'prospress-post',  PP_POSTS_URL . '/pp-post-admin.css' );
	}

	if ( strpos( $_SERVER['REQUEST_URI'], 'completed' ) !== false ){
		wp_enqueue_script( 'inline-edit-post' );
	}
}
add_action( 'admin_enqueue_scripts', 'pp_posts_admin_head' );



/** 
 * Prospress includes a widget & function for sorting Prospress posts. This function adds post
 * related meta values to optionally be sorted. 
 * 
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 * @see pp_set_sort_options()
 */
function pp_post_sort_options( $pp_sort_options ){

	$pp_sort_options['post-desc'] = __( 'Time: Newly posted', 'prospress' );
	$pp_sort_options['post-asc'] = __( 'Time: Oldest first', 'prospress' );
	$pp_sort_options['end-asc'] = __( 'Time: Ending soonest', 'prospress' );
	$pp_sort_options['end-desc'] = __( 'Time: Ending latest', 'prospress' );

	return $pp_sort_options;
}
add_filter( 'pp_sort_options', 'pp_post_sort_options' );


/** 
 * Small marketplaces do not require a custom classification system. Furthermore, custom taxonomies
 * add a degree of complexity that may bewilder young players - best to make it opt-in.
 * 
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 */
function pp_taxonomies_option_page() {
	global $market_systems;
	$market = $market_systems['auctions'];
?>
	<h3><?php _e( 'Custom Taxonomies', 'prospress' )?></h3>
	<p><?php echo sprintf( __( 'Custom taxonomies provide a way to classify %s. If your site lists more than 20 %s at a time, you should use custom taxonomies.', 'prospress' ), $market->label, $market->label ); ?></p>

	<label for="pp_use_custom_taxonomies">
		<input type="checkbox" value='true' id="pp_use_custom_taxonomies" name="pp_use_custom_taxonomies"<?php checked( (boolean)get_option( 'pp_use_custom_taxonomies' ) ); ?> />
		<?php _e( 'Use custom taxonomies' ); ?>
	</label>
<?php
}
add_action( 'pp_core_settings_page', 'pp_taxonomies_option_page' );


/** 
 * Save custom taxonomy setting @see pp_options_whitelist for details about adding pp_use_custom_taxonomies to
 * the settings whitelist.
 * 
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 */
function pp_taxonomies_whitelist( $whitelist_options ) {

	$whitelist_options[ 'general' ][] = 'pp_use_custom_taxonomies';

	return $whitelist_options;
}
add_filter( 'pp_options_whitelist', 'pp_taxonomies_whitelist' );


/**
 * A boolean function to centralise the logic for whether the current page is an admin page for this post type.
 *
 * This is required when enqueuing scripts, styles and performing other Prospress post admin page 
 * specific functions so it makes sense to centralise it. 
 * 
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 */
function is_pp_post_admin_page(){
	global $post, $market_systems;

	foreach( $market_systems as $market )
		if( $market->post->is_post_admin_page() )
			return true;
	return false;
}


/** 
 * Simple boolean function to check if current site is using custom taxonomies.
 * 
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 */
function is_using_custom_taxonomies(){

	if( get_option( 'pp_use_custom_taxonomies' ) == 'true' )
		return true;
	else
		return false;
}


/** 
 * Check is a query is for multiple Prospress taxonomies. A wrapper for
 * the QMT function that may or may not exist. 
 * 
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 */
function is_pp_multitax(){
	if( function_exists( '_is_pp_multitax' ) )
		return _is_pp_multitax();
	elseif( function_exists( 'is_multitax' ) )
		return is_multitax();
	else
		return false;
}


/** 
 * If a post author doesn't have permission to edit their own posts, they are redirected
 * to the dashboard once publishing a post. This is a bit cludgy, so this function redirects
 * them to that post type's admin index page and adds a message to show post was published.
 * 
 * @package Prospress
 * @subpackage Posts
 * @since 1.0
 */
function pp_post_save_access_denied_redirect() {
	global $pagenow;

	if( $pagenow == 'edit.php' ) { // @TODO find a way to determine this with better specificity
		wp_redirect( add_query_arg( array( 'updated' => 1 ), admin_url( 'edit.php?post_type=auctions' ) ) );
		exit;
	}
}
add_action( 'admin_page_access_denied', 'pp_post_save_access_denied_redirect', 20 ); //run after other functions


/** 
 * Clean up anything added on activation that does not need to persist incase of reactivation. 
 * 
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 */
function pp_posts_deactivate(){
	global $wp_roles;

	foreach ( $wp_roles->get_names() as $key => $role ) {

		$role = get_role( $key );

		$role->remove_cap( 'edit_others_prospress_posts' );
		$role->remove_cap( 'publish_prospress_posts' );
		$role->remove_cap( 'edit_prospress_posts' );
		$role->remove_cap( 'edit_published_prospress_posts' );
		$role->remove_cap( 'edit_private_prospress_posts' );
		$role->remove_cap( 'delete_prospress_post' );
		$role->remove_cap( 'delete_published_prospress_posts' );
		$role->remove_cap( 'publish_prospress_posts' );
		$role->remove_cap( 'edit_prospress_posts' );
		$role->remove_cap( 'read_private_prospress_posts' );
		$role->remove_cap( 'read_prospress_posts' );
	}
}
add_action( 'pp_deactivation', 'pp_posts_deactivate' );

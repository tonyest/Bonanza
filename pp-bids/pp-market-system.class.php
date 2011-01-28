<?php
/**
 * The Core Market System: this is where it get's exciting... and a little messy. 
 * 
 * This class forms the basis for all market systems. It provides a framework for creating a new market
 * systems and is extended to implement the core market systems, eg. Auction, that ship with Prospress.
 * 
 * The class takes care of the control logic and other generic functions and defines a few abstract 
 * functions for implementing your market specific code, but you can also overide many of its
 * other functions to create novel market types.
 * 
 * Extend this class to create a new bid system and implement PP_Market_System::bid_form_fields(), 
 * PP_Market_System::bid_form_submit(), PP_Market_System::bid_form_validate(), PP_Market_System::view_details(),
 * PP_Market_System::view_list() and PP_Market_System::post_fields().
 *
 * @package Prospress
 * @version 0.1
 */

abstract class PP_Market_System {

	private $name;					// Internal name of the market system, probably plural e.g. "auctions".
	public $label;					// Array of labels used to represent market system elements publicly, includes name & singular_name
	public $labels;					// Array of labels used to represent market system elements publicly, includes name & singular_name
	public $post;					// Hold the custom PP_Post object for this market system.
	public $bid_button_value;		// Text used on the submit button of the bid form.
	public $adds_post_fields;		// Flag indicating whether the market system adds new post fields. If anything but null, the post_fields_meta_box and post_fields_save functions are hooked
	public $post_table_columns;		// Array of arrays, each array is used to create a column in the post tables. By default it adds two columns, 
									// one for number of bids on the post and the other for the current winning bid on the post 
									// e.g. 'current_bid' => array( 'title' => 'Winning Bid', 'function' => 'get_winning_bid' ), 'bid_count' => array( 'title => 'Number of Bids', 'function' => 'get_bid_count' )
	public $bid_table_headings;		// Array of name/value pairs to be used as column headings when printing table of bids. 
									// e.g. 'bid_id' => 'Bid ID', 'post_id' => 'Post', 'bid_value' => 'Amount', 'bid_date' => 'Date'
	public $taxonomy;				// A PP_Taxonomy object for this post type
	protected $bid_status;
	protected $message;
	private $capability;			// the capability for making bids and viewing bid menus etc.

	public function __construct( $name, $args = array() ) {
		global $pp_base_capability;

		$this->name = sanitize_user( $name, true );

		$defaults = array(
						'description' => '',
						'labels' => array(
							'name' => ucfirst( $this->name ),
							'singular_name' => ucfirst( substr( $this->name, 0, -1) ), // Remove 's' - certainly not a catch all default!
							),
						'bid_button_value' => __( 'Bid Now!', 'prospress' ),
						'adds_post_fields' => null,
						'post_table_columns' => array (
											'current_bid' => array( 'title' => 'Price', 'function' => 'the_winning_bid_value' ),
											'winning_bidder' => array( 'title' => 'Winning Bidder', 'function' => 'the_winning_bidder' ) ),
						'bid_table_headings' => array( 
											'post_id' => 'Post', 
											'bid_value' => 'Bid Amount',
											'bid_status' => 'Status', 
											'winning_bid_value' => 'Current Price', 
											'bid_date' => 'Bid Date',
											'post_end' => 'Post End Date'
											),
						'capability' => $pp_base_capability
						);

		$args = wp_parse_args( $args, $defaults );

		$this->label 				= $args[ 'labels' ][ 'name' ];
		$this->labels 				= $args[ 'labels' ];
		$this->bid_button_value		= $args[ 'bid_button_value' ];
		$this->post_table_columns 	= $args[ 'post_table_columns' ];
		$this->bid_table_headings 	= $args[ 'bid_table_headings' ];
		$this->adds_post_fields 	= $args[ 'adds_post_fields' ];
		$this->capability 	= $args[ 'capability' ];

		$this->post	= new PP_Post( $this->name, array( 'labels' => $this->labels ) );

		if( is_using_custom_taxonomies() && class_exists( 'PP_Taxonomy' ) )
			$this->taxonomy = new PP_Taxonomy( $this->name, array( 'labels' => $this->labels ) );

		if( $this->adds_post_fields != null ){
			add_action( 'admin_menu', array( &$this, 'post_fields_meta_box' ) );
			add_action( 'save_post', array( &$this, 'post_fields_save' ), 10, 2 );
		}

		if( !empty( $this->post_table_columns ) ){
			add_filter( 'manage_posts_columns', array( &$this, 'add_post_column_headings' ) );
			add_action( 'manage_posts_custom_column', array( &$this, 'add_post_column_contents' ), 10, 2 );
		}

		add_filter( 'bid_table_actions', array( &$this, 'add_bid_table_actions' ), 10, 2 );

		// Determine if any of this class's functions should be called
		add_action( 'init', array( &$this, 'controller' ) );

		// Columns for printing bid history table
		add_action( 'admin_menu', array( &$this, 'add_admin_pages' ) );

		// Columns for printing bid history table
		add_filter( 'manage_' . $this->name . '_columns', array( &$this, 'get_column_headings' ) );

		// For Ajax & other scripts
		add_action( 'wp_print_scripts', array( &$this, 'enqueue_bid_form_scripts' ) );
		add_action( 'admin_menu', array( &$this, 'enqueue_bid_admin_scripts' ) );

		add_filter( 'pp_sort_options', array( &$this, 'add_sort_options' ) );
	}

	/************************************************************************************************
	 * Member functions that you must override.
	 ************************************************************************************************/

	// The fields that make up the bid form.
	// The <form> tag and a bid form header and footer are automatically generated for the class.
	// You only need to enter the fields to capture information required by your market system, eg. price.
	abstract protected function bid_form_fields( $post_id = NULL );

	// Process the bid form fields upon submission.
	abstract protected function bid_form_submit( $post_id = NULL, $bid_value = NULL, $bidder_id = NULL );

	// Validate and sanitize a bid upon submission, set bid_status and bid_message as needed
	abstract protected function validate_bid( $post_id, $bid_value, $bidder_id );

	// Psuedo abstract
	public function add_bid_table_actions( $actions, $post_id ) { return $actions; }

	// Form fields for receiving input from the edit and add new post type pages. Optionally abstract - only called if adds_post_fields flag set.
	public function post_fields() { return; }

	// Processes data taken from the post edit and add new post forms. Optionally abstract - only called if adds_post_fields flag set.
	protected function post_fields_save( $post_id, $post ) { return; }
	
	/************************************************************************************************
	 * Functions that you may wish to override, but don't need to in order to create a new market system
	 ************************************************************************************************/
	/**
	 * A getter for the market system's name. This is called from various places to refer to the both the market system and
	 * posts within that market system. 
	 * 
	 * By default this function returns the name of the marketplace system, as stored in the $name member variables, with an 
	 * upper case first letter; however, other market systems require additional words or operations performed on the name 
	 * member variable.
	 **/
	public function name() {
		return $this->name;
	}

	public function singular_name() {
		return $this->labels[ 'singular_name' ];
	}

	// The function that brings all the bid form elements together.
	public function bid_form( $post_id = NULL ) {
		global $post;

		$post_id = ( $post_id === NULL ) ? $post->ID : (int)$post_id;
		$the_post = ( empty ( $post ) ) ? get_post( $post_id) : $post;

		if ( $this->is_post_valid( $post_id ) ) {
			$form .= '<form id="bid_form-' . $post_id . '" class="bid-form" method="post" action="">';
			$form .= '<div class="bid-updated bid_msg" >' . $this->get_message() . '</div><div>';

			$form .= $this->bid_form_fields( $post_id );

			apply_filters( 'bid_form_hidden_fields', $form );

			$form .= wp_nonce_field( __FILE__, 'bid_nonce', false, false );
			$form .= '<input type="hidden" name="post_ID" value="' . $post_id . '" id="post_ID" /> ';
			$form .= '<input name="bid_submit" type="submit" id="bid_submit" value="' . $this->bid_button_value .'" />';
			$form .= '</div></form>';
		} else {
			$form .= '<div class="bid-form">';
			$form .= '<div class="bid-updated bid_msg" >' . $this->get_message() . '</div>';
			$form .= '</div>';
		}

		$form = apply_filters( 'bid_form', $form );

		return $form;		
	}
	
	protected function is_bid_valid( $post_id, $bid_value, $bidder_id ) {

		$this->validate_bid( $post_id, $bid_value, $bidder_id );

		if( $this->bid_status != 'invalid' )
			return true;
		else
			return false;
	}

	protected function is_post_valid( $post_id = '' ) {

		if( $this->validate_post( $post_id ) == 'valid' )
			return true;
		else
			return false;
	}

	/**
	 * Check's a post's status and verify's that it may receive bids. 
	 */
	protected function validate_post( $post_id = '' ) {
		global $post, $wpdb;

		if( empty( $post_id ))
			$post_id = $post->ID;

		//$post_status = $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM $wpdb->posts WHERE ID = %d", $post_id ) );
		$post_status = get_post( $post_id )->post_status;

		if ( $post_status == 'completed' ){
			do_action( 'bid_on_completed_post', $post_id);
			$this->message_id = 12;
		} elseif ( $post_status === NULL ) {
			do_action( 'bid_post_not_found', $post_id);
			$this->message_id = 13;
			$post_status = 'invalid';
		} elseif ( in_array( $post_status, array( 'draft', 'pending' ) ) ) {
			do_action( 'bid_on_draft', $post_id);
			$this->message_id = 14;
			$post_status = 'invalid';
		} else {
			return 'valid';
		}

		return $post_status;
	}

	// Calculates the value of the new winning bid and updates it in the DB if necessary
	// Returns the value of the winning bid (either new or existing)
	// function update_winning_bid( $bid_ms, $post_id, $bid_value, $bidder_id ){
	protected function update_bid( $bid ){
		global $wpdb;

		if ( $this->bid_status == 'invalid' ) // nothing to update
			return $this->get_winning_bid_value( $bid[ 'post_id' ] );

		$wpdb->insert( $wpdb->bids, $bid );

		return $bid[ 'bid_value' ];
	}


	/**
	 * Gets all the details of the highest bid on a post, optionally specified with $post_id.
	 *
	 * If no post id is specified, the global $post var is used. 
	 */
	public function get_max_bid( $post_id = '' ) {
		global $post, $wpdb;

		if ( empty( $post_id ) )
			$post_id = $post->ID;

		$max_bid = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->bids WHERE post_id = %d AND bid_value = (SELECT MAX(bid_value) FROM $wpdb->bids WHERE post_id = %d)", $post_id, $post_id ) );

		return $max_bid;
	}

	/**
	 * Prints the max bid value for a post, optionally specified with $post_id. Optional also to just return the value. 
	 */
	public function the_max_bid_value( $post_id = '', $echo = true ) {
		$max_bid = ( empty( $post_id ) ) ? $this->get_max_bid() : $this->get_max_bid( $post_id );
		
		$max_bid = ( $max_bid->bid_value ) ? pp_money_format( $max_bid->bid_value ) : __( 'No Bids', 'prospress' );

		if ( $echo ) 
			echo $max_bid;
		else 
			return $max_bid;
	}

	/**
	 * Gets all the details of the winning bid on a post, optionally specified with $post_id.
	 *
	 * At first glance, it may seem to be redundant having functions for both "max" and "winning" bid. 
	 * However, in some market systems, the winning bid is no determined by the "max" bid. 
	 * 
	 * If no post id is specified, the global $post var is used. 
	 */
	public function get_winning_bid( $post_id = '' ) {
		global $post, $wpdb;

		if ( empty( $post_id ) )
			$post_id = $post->ID;

		$winning_bid = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->bids WHERE post_id = %d AND bid_status = %s", $post_id, 'winning' ) );

		$winning_bid->winning_bid_value = $this->get_winning_bid_value( $post_id );

		return $winning_bid;
	}

	/**
	 * Gets the value of the current winning bid for a post, optionally specified with $post_id.
	 *
	 * The value of the winning bid is not necessarily equal to the bid's value. The winning bid
	 * value is calculated with the bid increment over the current second highest bid. It is then
	 * stored in the bidsmeta table. This function pulls the value from this table. 
	 * 
	 * If no winning value is stored in the bidsmeta table, then the function uses the winning bids
	 * value, which is equal to the maximum bid for that user on this post.
	 */
	public function get_winning_bid_value( $post_id = '' ) {
		global $post, $wpdb;

		if ( empty( $post_id ) )
			$post_id = $post->ID;

		if ( $this->get_bid_count( $post_id ) == 0 ){
			$winning_bid_value = get_post_meta( $post_id, 'start_price', true );
		} else {
			$winning_bid = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->bids WHERE post_id = %d AND bid_status = %s", $post_id, 'winning' ) );

			$winning_bid_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->bidsmeta WHERE bid_id = %d AND meta_key = %s", $winning_bid->bid_id, 'winning_bid_value' ) );

			if( empty( $winning_bid_value ) )
				$winning_bid_value = $winning_bid->bid_value;
		}

		return $winning_bid_value;
	}

	/**
	 * Prints the value of the current winning bid for a post, optionally specified with $post_id.
	 *
	 * The value of the winning bid is not necessarily equal to the maximum bid. 
	 */
	public function the_winning_bid_value( $post_id = '', $echo = true ) {
		$winning_bid = $this->get_winning_bid_value( $post_id );

		$winning_bid = ( $winning_bid == 0 ) ? __( 'No bids.', 'prospress' ) : pp_money_format( $winning_bid );

		if ( $echo ) 
			echo $winning_bid;
		else 
			return $winning_bid;
	}

	/**
	 * Prints the display name of the winning bidder for a post, optionally specified with $post_id.
	 */
	public function the_winning_bidder( $post_id = '', $echo = true ) {
		global $user_ID, $display_name;

		get_currentuserinfo(); // to set global $display_name

		$winning_bidder = $this->get_winning_bid( $post_id )->bidder_id;

		if ( !empty( $winning_bidder ) )
			$winning_bidder = ( $winning_bidder == $user_ID) ? __( 'You', 'prospress' ) : get_userdata( $winning_bidder )->display_name;
		else 
			$winning_bidder = 'No bids.';

		if ( $echo ) 
			echo $winning_bidder;
		else 
			return $winning_bidder;
	}

	/**
	 * Function to test if a given user is classified as a winning bidder for a given post. 
	 * 
	 * As some market systems may have multiple winners, it is important to use this function 
	 * instead of testing a user id directly against a user id provided with get_winning_bid.
	 * 
	 * Optionally takes $user_id and $post_id, if not specified, using the ID of the currently
	 * logged in user and post in the loop.
	 */
	public function is_winning_bidder( $user_id = '', $post_id = '' ) {
		global $user_ID, $post;

		if ( empty( $post_id ) )
			$post_id = $post->ID;
		
		if ( $user_id == '' )
			$user_id = $user_ID;

		return ( $user_id == $this->get_winning_bid( $post_id )->bidder_id ) ? true : false;
	}

	/**
	 * Gets the max bid for a post and user, optionally specified with $post_id and $user_id.
	 *
	 * If no user ID or post ID is specified, the function uses the global $post ad $user_ID 
	 * variables. 
	 */
	public function get_users_max_bid( $user_id = '', $post_id = '' ) {
		global $user_ID, $post, $wpdb;

		if ( empty( $user_id ) )
			$user_id = $user_ID;

		if ( empty( $post_id ) )
			$post_id = $post->ID;
			
		$users_max_bid = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->bids WHERE post_id = %d AND bidder_id = %d AND bid_value = (SELECT MAX(bid_value) FROM $wpdb->bids WHERE post_id = %d AND bidder_id = %d)", $post_id, $user_id, $post_id, $user_id));

		return $users_max_bid;
	}

	// Prints the max bid for a user on a post, optionally specified with $post_id.
	public function the_users_max_bid_value( $user_id = '', $post_id = '', $echo = true ) {
		$users_max_bid = get_users_max_bid( $user_id, $post_id );

		$users_max_bid = ( $users_max_bid->bid_value ) ? $users_max_bid->bid_value : __( 'No Bids.', 'prospress' );

		if ( $echo ) 
			echo $users_max_bid;
		else 
			return $users_max_bid;
	}

	// Gets the number of bids for a post, optionally specified with $post_id.
	public function get_bid_count( $post_id = '' ) {
		global $post, $wpdb;

		if ( empty( $post_id ) )
			$post_id = $post->ID;

		$bid_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->bids WHERE post_id = %d", $post_id ) );

		return $bid_count;
	}

	/**
	 * Prints the number of bids on a post, optionally specified with $post_id.
	 */
	public function the_bid_count( $post_id = '', $echo = true ) {
		$bid_count = ( empty( $post_id ) ) ? $this->get_bid_count() : $this->get_bid_count( $post_id );
		
		$bid_count = ( $bid_count ) ? $bid_count : __( 'No Bids', 'prospress' );

		if ( $echo ) 
			echo $bid_count;
		else 
			return $bid_count;
	}

	/**
	 * Extracts messages passed to a bid form and prints these messages.
	 * 
	 * A message can be passed to a bid form using the URL. This function pulls any messages
	 * passed to a page containing a bid form and prints the messages. 
	 */
	private function get_message(){

		// Avoid showing messages passed in latent url parameters
		if ( !is_user_logged_in() )
			return;

		if ( isset( $this->message_id ) )
			$message_id = $this->message_id;
		elseif ( isset( $_GET[ 'bid_msg' ] ) )
			$message_id = $_GET[ 'bid_msg' ];

		$message = '';

		if ( isset( $message_id ) ){
			switch( $message_id ) {
				case 0:
				case 1:
					$message = __( 'Congratulations, you are the winning bidder.', 'prospress' );
					break;
				case 2:
					$message = __( 'You have been outbid.', 'prospress' );
					break;
				case 3:
					$message = __( 'You must bid more than the winning bid.', 'prospress' );
					break;
				case 4:
					$message = __( 'Your maximum bid has been increased.', 'prospress' );
					break;
				case 5:
					$message = __( 'You can not decrease your maximum bid.', 'prospress' );
					break;
				case 6:
					$message = __( 'You have entered a bid equal to your current maximum bid.', 'prospress' );
					break;
				case 7:
					$message = __( 'Invalid bid. Please enter a valid number. e.g. 11.23 or 58', 'prospress' );
					break;
				case 8:
					$message = __( 'Invalid bid. Bid nonce did not validate.', 'prospress' );
					break;
				case 9:
					$message = __( 'Invalid bid. Please enter a bid greater than the starting price.', 'prospress' );
					break;
				case 10:
					$message = __( 'Bid submitted.', 'prospress' );
					break;
				case 11:
					$message = __( 'You cannot bid on your own ', 'prospress' ) . $this->labels[ 'singular_name' ] . '.';
					break;
				case 12:
					$message = __( 'This post has completed, bids cannot be accepted.', 'prospress' );
					break;
				case 13:
					$message = __( 'Fail: this post can not be found.', 'prospress' );
					break;
				case 14:
					$message = __( 'You cannot bid on a draft or pending post', 'prospress' );
					break;
			}
			$message = apply_filters( 'bid_message', $message );
			return $message;
		}
	}


	/**
	 * Convenience wrapper for the post object's get index id function.
	 */
	public function get_index_id() {
		return $this->post->get_index_id();
	}

	/**
	 * Convenience wrapper for the post object's get index permalink function.
	 */
	public function get_index_permalink() {
		return $this->post->get_index_permalink();
	}

	
	/**
	 * Convenience wrapper for the post object's is index function.
	 */
	public function is_index() {
		return $this->post->is_index();
	}


	/**
	 * Convenience wrapper for the post object's is index function.
	 */
	public function is_single() {
		return $this->post->is_single();
	}


	/**
	 * Creates an anchor tag linking to the user's payments, optionally prints.
	 * 
	 */
	function the_bids_url( $desc = "Your Bids", $echo = '' ) {

		$bids_tag = "<a href='" . $this->get_bids_url() . "' title='$desc'>$desc</a>";

		if( $echo == 'echo' )
			echo $bids_tag;
		else
			return $bids_tag;
	}


	/**
	 * Gets the url to the user's feedback table.
	 * 
	 */
	function get_bids_url() {

		 return admin_url( 'admin.php?page=' . $this->name . '-bids' );
	}


	/**
	 * Returns a user's role on a given post. If user has no role, false is returned.
	 * 
	 * @param $post int|array either the id of a post or a post object
	 */
	function get_users_role( $post, $user_id = NULL ) {
		global $user_ID;

		if( $user_id === NULL )
			$user_id = $user_ID;

		if ( is_numeric( $post ) )
			$post = get_post( $post );

		if ( $post->post_author == $user_id && !$is_winning_bidder )
			return __( 'Post Author', 'prospress' );
		else
			return __( 'Bidder', 'prospress' );
	}


	/************************************************************************************************
	 * Private Functions: don't worry about these, unless you want to get really tricky.
	 * Even if they're declared public, it's only because they are attached to a hook
	 ************************************************************************************************/

	/**
	 * 	Adds bid pages to admin menu
	 * 
	 * @uses add_object_page to add "Bids" top level menu
	 * @uses add_menu_page if add object page is not available to add "Bids" menu
	 * @uses add_submenu_page to add "Bids" and "Bid History" submenus to "Bids"
	 * @uses add_options_page to add administration pages for bid settings
	 * @return false if logged in user is not the site admin
	 **/
	public function add_admin_pages() {

		$base_page = $this->name . "-bids";

		$bids_title = apply_filters( 'bids_admin_title', __( 'Bids', 'prospress' ) );

		if ( function_exists( 'add_object_page' ) ) {
			add_object_page( $bids_title, $bids_title, $this->capability, $base_page, '', PP_PLUGIN_URL . '/images/auctions16.png' );
		} elseif ( function_exists( 'add_menu_page' ) ) {
			add_menu_page( $bids_title, $bids_title, $this->capability, $base_page, '', PP_PLUGIN_URL . '/images/auctions16.png' );
		}

		$completed_posts_menu_title = apply_filters( 'pp_completed_posts_menu_title', sprintf( __( 'Completed %s', 'prospress' ), $this->label ) );
		$active_posts_menu_title = apply_filters( 'pp_active_posts_menu_title', sprintf( __( 'Active %s', 'prospress' ), $this->label ) );

	    // Add submenu items to the bids top-level menu
		if (function_exists( 'add_submenu_page' )){
		    add_submenu_page( $base_page, $completed_posts_menu_title, $completed_posts_menu_title, $this->capability, $base_page, array( &$this, 'completed_history' ) );
		    add_submenu_page( $base_page, $active_posts_menu_title, $active_posts_menu_title, $this->capability, 'active-bids', array( &$this, 'active_history' ) );
		}
	}

	// Print the feedback history for a user
	public function active_history() {
	  	global $wpdb, $user_ID;

		get_currentuserinfo(); //get's user ID of currently logged in user and puts into global $user_id

		$order_by = 'bid_date_gmt';
		$query = $this->create_bid_page_query();

		$bids = $wpdb->get_results( $query, ARRAY_A );

		$bids = apply_filters( 'active_history_bids', $bids );

		$this->print_admin_bids_table( $bids, sprintf( __( 'Bids on Active %s', 'prospress' ), $this->label ), 'active-bids' );
	}

	public function completed_history() {
	  	global $wpdb, $user_ID;

		if ( !current_user_can( $this->capability ) )
			wp_die("You need to be able to read to view this page.");

		get_currentuserinfo(); //get's user ID of currently logged in user and puts into global $user_id

		$query = $this->create_bid_page_query( 'completed' );

		$bids = $wpdb->get_results( $query, ARRAY_A );

		$bids = apply_filters( 'completed_history_bids', $bids );

		$this->print_admin_bids_table( $bids, sprintf( __( 'Bids on Completed %s', 'prospress' ), $this->label ), 'bids' );
	}

	private function create_bid_page_query( $post_status = 'publish', $bid_status = '' ){
		global $wpdb, $user_ID;

		$query = $wpdb->prepare( "SELECT * FROM $wpdb->bids WHERE bidder_id = %d", $user_ID );

		$query .= $wpdb->prepare( " AND post_id IN ( SELECT ID FROM $wpdb->posts WHERE post_status = %s ) ", $post_status );

		if( !empty( $bid_status ) )
			$query .= $wpdb->prepare( ' AND bid_status = %s', $bid_status );

		// Only the latest bid
		$query .= $wpdb->prepare( " AND bid_id IN ( SELECT MAX( bid_id ) FROM $wpdb->bids WHERE bidder_id = %d GROUP BY post_id ) ", $user_ID );

		if( isset( $_GET[ 'm' ] ) && $_GET[ 'm' ] != 0 ){
			$month	= substr( $_GET[ 'm' ], -2 );
			$year	= substr( $_GET[ 'm' ], 0, 4 );
			$query .= $wpdb->prepare( ' AND MONTH(bid_date) = %d AND YEAR(bid_date) = %d ', $month, $year );
		}

		if( isset( $_GET[ 'bs' ] ) && $_GET[ 'bs' ] != 0 ){
			$query .= ' AND bid_status = ';
			switch( $_GET[ 'bs' ] ){
				case 1:
					$query .= "'outbid'";
					break;
				case 2:
					$query .= "'winning'";
					break;
				default:
					break;
				}
		}

		if( isset( $_GET[ 'sort' ] ) ){
			$query .= ' ORDER BY ';
			switch( $_GET[ 'sort' ] ){
				case 1:
					$query .= 'bid_value';
					break;
				case 2:
					$query .= 'post_id';
					break;
				case 3:
					$query .= 'bid_status';
					break;
				case 4:
					$query .= 'bid_date_gmt';
					break;
				default:
					$query .= apply_filters( 'sort_bids_by', 'bid_date_gmt' );
				}
		}

		return $query;
	}

	protected function print_admin_bids_table( $bids = array(), $title = 'Bids', $page ){
		global $wpdb, $user_ID, $wp_locale;

		if( !is_array( $bids ) )
			$bids = array();

		$sort = isset( $_GET[ 'sort' ] ) ? (int)$_GET[ 'sort' ] : 0;
		$bid_status = isset( $_GET[ 'bs' ] ) ? (int)$_GET[ 'bs' ] : 0;

		?>
		<div class="wrap feedback-history">
			<?php screen_icon(); ?>
			<h2><?php echo $title; ?></h2>

			<form id="bids-filter" action="" method="get" >
				<input type="hidden" id="page" name="page" value="<?php echo $page; ?>">
				<div class="tablenav clearfix">
					<div class="alignleft">
						<select name='bs'>
							<option<?php selected( $bid_status, 0 ); ?> value='0'><?php _e( 'Any bid status', 'prospress' ); ?></option>
							<option<?php selected( $bid_status, 1 ); ?> value='1'><?php _e( 'Outbid', 'prospress' ); ?></option>
							<option<?php selected( $bid_status, 2 ); ?> value='2'><?php _e( 'Winning', 'prospress' ); ?></option>
						</select>
						<?php
						if( strpos( $title, 'Winning' ) !== false )
							$arc_query = $wpdb->prepare("SELECT DISTINCT YEAR(bid_date) AS yyear, MONTH(bid_date) AS mmonth FROM $wpdb->bids WHERE bidder_id = %d AND bid_status = 'winning' ORDER BY bid_date DESC", $user_ID );
						else 
							$arc_query = $wpdb->prepare("SELECT DISTINCT YEAR(bid_date) AS yyear, MONTH(bid_date) AS mmonth FROM $wpdb->bids WHERE bidder_id = %d ORDER BY bid_date DESC", $user_ID );
						$arc_result = $wpdb->get_results( $arc_query );
						$month_count = count( $arc_result);

						if ( $month_count && !( 1 == $month_count && 0 == $arc_result[0]->mmonth ) ) {
							$m = isset( $_GET['m' ] ) ? (int)$_GET['m' ] : 0;
						?>
						<select name='m'>
						<option<?php selected( $m, 0 ); ?> value='0'><?php _e( 'Show all dates', 'prospress' ); ?></option>
						<?php
						foreach ( $arc_result as $arc_row) {
							if ( $arc_row->yyear == 0 )
								continue;
							$arc_row->mmonth = zeroise( $arc_row->mmonth, 2 );

							if ( $arc_row->yyear . $arc_row->mmonth == $m )
								$default = ' selected="selected"';
							else
								$default = '';

							echo "<option$default value='" . esc_attr("$arc_row->yyear$arc_row->mmonth") . "'>";
							echo $wp_locale->get_month( $arc_row->mmonth) . " $arc_row->yyear";
							echo "</option>\n";
						}
						?>
						</select>
						<?php } ?>
						<input type="submit" value="Filter" id="filter_action" class="button-secondary action" />

						<select name='sort'>
							<option<?php selected( $sort, 0 ); ?> value='0'><?php _e( 'Sort by', 'prospress' ); ?></option>
							<option<?php selected( $sort, 1 ); ?> value='1'><?php _e( 'Bid Value', 'prospress' ); ?></option>
							<option<?php selected( $sort, 2 ); ?> value='2'><?php _e( 'Post', 'prospress' ); ?></option>
							<option<?php selected( $sort, 3 ); ?> value='3'><?php _e( 'Bid Status', 'prospress' ); ?></option>
							<option<?php selected( $sort, 4 ); ?> value='5'><?php _e( 'Bid Date', 'prospress' ); ?></option>
						</select>
						<input type="submit" value="Sort" id="sort_action" class="button-secondary action" />
					</div>
					<br class="clear" />
				</div>

			<table class="widefat fixed" cellspacing="0">
				<thead>
					<tr class="thead">
						<?php print_column_headers( $this->name ); // Calls get_column_headings() added by add_filter( manage_$this->name_columns ?>
					</tr>
				</thead>
				<tbody id="bids" class="list:user user-list">
				<?php
					if( !empty( $bids ) ){
						$style = '';
						foreach ( $bids as $bid ) {
							$post = get_post( $bid[ 'post_id' ] );
							$post_end_date = get_post_meta( $bid[ 'post_id' ], 'post_end_date', true );
							?>
							<tr class='<?php echo $style; ?>'>
								<td><a href='<?php echo get_permalink( $bid[ 'post_id' ] ); ?>'><?php echo $post->post_title; ?></a></td>
								<td><?php echo pp_money_format( $bid[ 'bid_value' ] ); ?></td>
								<td><?php echo ucfirst( $bid[ 'bid_status' ] );  ?></td>
								<td><?php $this->the_winning_bid_value( $bid[ 'post_id' ] ); ?></td>
								<td><?php echo mysql2date( __( 'g:ia d M Y' , 'prospress' ), $bid[ 'bid_date' ] ); ?></td>
								<td><?php echo mysql2date( __( 'g:ia d M Y' , 'prospress' ), $post_end_date ); ?></td>
								<?php if( strpos( $_SERVER['REQUEST_URI' ], 'bids' ) !== false ){
									$actions = apply_filters( 'bid_table_actions', array(), $post->ID );
									echo '<td>';
									if( is_array( $actions ) && !empty( $actions ) ){
									?><div class="prospress-actions">
										<ul class="actions-list">
											<li class="base"><?php _e( 'Take action:', 'prospress' ) ?></li>
										<?php foreach( $actions as $action => $attributes )
											echo "<li class='action'><a href='" . add_query_arg ( array( 'action' => $action, 'post' => $post->ID ) , $attributes['url' ] ) . "'>" . $attributes['label' ] . "</a></li>";
										 ?>
										</ul>
									</div>
									<?php
									} else {
										_e( 'No action can be taken.', 'prospress' );
									}
									echo '</td>';
								}?>
							<tr>
							<?php
							$style = ( 'alternate' == $style ) ? '' : 'alternate';
						}
					} else {
						echo '<tr><td colspan="6">' . __( 'No bids.', 'prospress' ) . '</td></tr>';
					}
				?>
				</tbody>
				<tfoot>
					<tr class="thead">
						<?php print_column_headers( $this->name ); ?>
					</tr>
				</tfoot>
			</table>
		</div>
		<?php
	}

	// Returns bid column headings for market system. Used with the built in print_column_headers function.
	public function get_column_headings(){
		$column_headings = $this->bid_table_headings;

		if( strpos( $_SERVER['REQUEST_URI' ], 'bids' ) !== false )
			$column_headings[ 'bid_actions' ] = __( 'Action', 'prospress' );

		return $column_headings;
	}

	// Add market system columns to tables of posts
	public function add_post_column_headings( $column_headings ) {

		if( !( ( $_GET[ 'post_type' ] == $this->name ) || ( get_post_type( $_GET[ 'post' ] ) ==  $this->name ) ) )
			return $column_headings;

		foreach( $this->post_table_columns as $key => $column )
			$column_headings[ $key ] = $column[ 'title' ];

		return $column_headings;
	}

	/**
	 * Don't worry about this indecipherable function, you shouldn't need to create the bid table columns,
	 * instead you can rely on this to call the function assigned to the column through the constructor
	 **/
	public function add_post_column_contents( $column_name, $post_id ) {
		if( array_key_exists( $column_name, $this->post_table_columns ) ) {
			$function = $this->post_table_columns[ $column_name ][ 'function' ];
			$this->$function( $post_id );
		}
	}

	public function enqueue_bid_form_scripts(){
		if( is_admin() ) //only needed on public facing pages
			return;

  		wp_enqueue_script( 'bid-form-ajax', PP_BIDS_URL . '/bid-form-ajax.js', array( 'jquery' ) );
		wp_localize_script( 'bid-form-ajax', 'pppostL10n', array(
			'endedOn' => __( 'Ended on:', 'prospress' ),
			'endOn' => __( 'End on:', 'prospress' ),
			'end' => __( 'End', 'prospress' ),
			'update' => __( 'Update', 'prospress' ),
			'repost' => __( 'Repost', 'prospress' ),
			));
	}

	public function enqueue_bid_admin_scripts(){
		wp_enqueue_style( 'bids', PP_BIDS_URL . '/admin.css' );
	}

	// Adds bid system specific sort options to the post system sort widget, can be implemented, but doesn't have to be
	public function add_sort_options( $pp_sort_options ){
		return $pp_sort_options;
	}

	// Adds the meta box with post fields to the edit and add new post forms. 
	// This function is hooked in the constructor and is only called if post fields is defined. 
	public function post_fields_meta_box(){
		if( function_exists( 'add_meta_box' ) ) {
			add_meta_box( 'pp-bidding-options', sprintf( __( '%s Options', 'prospress' ), $this->labels->singular_name ), array(&$this, 'post_fields' ), $this->name, 'normal', 'core' );
		}
	}

	/**
	 * The logic for the market system.
	 * 
	 * Handles AJAX bid submission and makes sure a user is logged in before making a bid.
	 *
	 **/
	// Hooked to init to determine if a bid has been submitted. If it has, bid_form_submit is called.
	// Takes care of the logic of the class, determining if and when to call a function.
	public function controller(){

		// If a bid is not being sumbited, exist asap to avoid wasting user's time
		if( !isset( $_REQUEST[ 'bid_submit' ] ) )
			return;

		if ( !is_user_logged_in() ){ 
			do_action( 'bidder_not_logged_in' );
			$redirect = wp_get_referer();
			$redirect = add_query_arg( urlencode_deep( $_POST ), $redirect );
			$redirect = add_query_arg( 'bid_redirect', wp_get_referer(), $redirect );
			$redirect = wp_login_url( $redirect );
			$redirect = apply_filters( 'bid_login_redirect', $redirect );

			if( $_REQUEST[ 'bid_submit' ] == 'ajax' ){ // Bid being submitted with AJAX need to print redirect instead of using WP redirect
				echo '{"redirect":"' . $redirect . '"}';
				die();
			} else {
				wp_safe_redirect( $redirect );
				exit();
			}
		}

		// Verify bid nonce if bid is not coming from a login redirect
		if ( !isset( $_REQUEST[ 'bid_redirect' ] ) && ( !isset( $_REQUEST[ 'bid_nonce' ] ) || !wp_verify_nonce( $_REQUEST['bid_nonce' ], __FILE__) ) ) {
			$this->bid_status = 8;
		} elseif ( isset( $_GET[ 'bid_redirect' ] ) ) {
			//$this->bid_status = $this->bid_form_submit( $_GET[ 'post_ID' ], $_GET[ 'bid_value' ] );
			$this->bid_form_submit( $_GET[ 'post_ID' ], $_GET[ 'bid_value' ] );
		} else {
			//$this->bid_status = $this->bid_form_submit( $_POST[ 'post_ID' ], $_POST[ 'bid_value' ] );
			$this->bid_form_submit( $_POST[ 'post_ID' ], $_POST[ 'bid_value' ] );
		}

		// Redirect user back to post
		if ( !empty( $_REQUEST[ 'bid_redirect' ] ) ){
			$location = $_REQUEST[ 'bid_redirect' ];
			$location = add_query_arg( 'bid_msg', $this->message_id, $location );
			$location = add_query_arg( 'bid_nonce', wp_create_nonce( __FILE__ ), $location );
			wp_safe_redirect( $location );
			exit();
		}

		if( $_POST[ 'bid_submit' ] == 'ajax' ){
			echo $this->bid_form( $_POST[ 'post_ID' ] );
			die();
		}

		// If someone enters a URL with a bid_msg but they didn't make that bid
		if( isset( $_GET[ 'bid_msg' ] ) && isset( $_GET[ 'bid_nonce' ] ) && !wp_verify_nonce( $_GET[ 'bid_nonce' ], __FILE__ ) ){

			$redirect = remove_query_arg( 'bid_nonce' );
			$redirect = remove_query_arg( 'bid_msg', $redirect );
			wp_safe_redirect( $redirect );
			exit();
		}
	}

}


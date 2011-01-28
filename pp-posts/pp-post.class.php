<?php
/**
 * The Core Prospress Post Object.
 *
 * Each market type requires its own post type and a few functions that help put it into being. This 
 * class takes care of these functions and makes it possible for each market system to have it's own
 * object to take care of all of this.
 *
 * @package Prospress
 * @version 0.1
 */
//require_once( PP_POSTS_DIR . '/pp-custom-taxonomy.php' );

class PP_Post {

	public $name;
	private $labels;

	public function __construct( $name, $args ) {

		$this->name = $name;
		$this->labels = $args[ 'labels' ];

		add_action( 'pp_activation', array( &$this, 'activate' ) );

		add_action( 'init', array( &$this, 'register_post_type' ) );

		// Only use built-in sidebars if current theme doesn't support Prospress
		add_action( 'init', array( &$this, 'register_sidebars' ) );

		add_action( 'template_redirect', array( &$this, 'template_redirects' ) );

		add_action( 'pp_deactivation', array( &$this, 'deactivate' ) );

		add_action( 'pp_uninstall', array( &$this, 'uninstall' ) );
		
		add_filter( 'posts_search', array( &$this, 'remove_index' ) );

		add_filter( 'manage_' . $this->name . '_posts_columns', array( &$this, 'post_columns' ) );

		add_action( 'manage_posts_custom_column', array( &$this, 'post_custom_columns' ), 10, 2 );

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
	 * @global PP_Market_System $market_system Prospress market system object for this marketplace.
	 * @global WP_Rewrite $wp_rewrite WordPress Rewrite Component.
	 */
	public function activate(){
		global $wpdb;

		if( $this->get_index_id() == false ){ // Need an index page for this post type
			$index_page = array();
			$index_page['post_title'] = $this->labels[ 'name' ];
			$index_page['post_name'] = $this->name;
			$index_page['post_status'] = 'publish';
			$index_page['post_content'] = __( 'This is the index for your ' . $this->labels[ 'name' ] . '. Your ' . $this->labels[ 'name' ] . ' will automatically show up here, but you change this text to provide an introduction or instructions.', 'prospress' );
			$index_page['post_type'] = 'page';

			wp_insert_post( $index_page );

		} else { // Index page exists, make sure it's published as it get's trashed on plugin deactivation
			$index_page = get_post( $this->get_index_id(), ARRAY_A );
			$index_page[ 'post_status' ] = 'publish';

			wp_update_post( $index_page );

			update_option( 'pp_show_welcome', 'false' );
		}

		$this->add_sidebars_widgets();

		// Update rewrites to account for this post type
		$this->register_post_type();
		flush_rewrite_rules();
	}


	/** 
	 * Prospress posts are not your vanilla WordPress post, they have special meta which needs to
	 * be presented in a special way. They also need to be sorted and filtered to make them easier to
	 * browse and compare. That's why this function redirects individual Prospress posts to a default
	 * template for single posts - pp-single.php - and the auto-generated Prospress index page to a 
	 * special index template - pp-index.php.
	 * 
	 * However, before doing so, it provides a hook for overriding the templates and also checks if the 
	 * current theme has Prospress compatible templates.
	 * 
	 * @package Prospress
	 * @subpackage Posts
	 * @since 0.1
	 */
	public function template_redirects() {
		global $post, $market_systems;

		$market = $market_systems[ $this->name ];

		if ( is_using_custom_taxonomies() && is_pp_multitax() ) {

			wp_enqueue_style( 'prospress',  PP_CORE_URL . '/prospress.css' );

			do_action( 'pp_taxonomy_template_redirect' );

			if( file_exists( TEMPLATEPATH . '/taxonomy-' . $this->name . '.php' ) )
				include( TEMPLATEPATH . '/taxonomy-' . $this->name . '.php' );
			elseif( file_exists( TEMPLATEPATH . '/pp-taxonomy-' . $this->name . '.php' ) )
				include( TEMPLATEPATH . '/pp-taxonomy-' . $this->name . '.php' );
			else
				include( PP_POSTS_DIR . '/pp-taxonomy-' . $this->name . '.php' );
			exit;

		} elseif( $this->is_index() && TEMPLATEPATH . '/page.php' == get_page_template() ){ // No template set for default Prospress index

			wp_enqueue_style( 'prospress',  PP_CORE_URL . '/prospress.css' );

			do_action( 'pp_index_template_redirect' );

			if( file_exists( TEMPLATEPATH . '/index-' . $this->name . '.php' ) ) // Theme supports Prospress
				include( TEMPLATEPATH . '/index-' . $this->name . '.php' );
			elseif( file_exists( TEMPLATEPATH . '/pp-index-' . $this->name . '.php' ) )	// Copied the default template to the theme directory before customising?
				include( TEMPLATEPATH . '/pp-index-' . $this->name . '.php' );
			else   																// Default template
				include( PP_POSTS_DIR . '/pp-index-' . $this->name . '.php' );
			exit;

		} elseif ( $this->is_single() && is_single() && !isset( $_GET[ 's' ] ) ) {

			wp_enqueue_style( 'prospress',  PP_CORE_URL . '/prospress.css' );

			do_action( 'pp_single_template_redirect' );

			if( file_exists( TEMPLATEPATH . '/single-' . $this->name . '.php' ) )
				include( TEMPLATEPATH . '/single-' . $this->name . '.php' );
			elseif( file_exists( TEMPLATEPATH . '/pp-single-' . $this->name . '.php' ) )
				include( TEMPLATEPATH . '/pp-single-' . $this->name . '.php' );
			else
				include( PP_POSTS_DIR . '/pp-single-' . $this->name . '.php' );
			exit;
		}
	}


	/** 
	 * A custom post type especially for this market system's posts.
	 * 
	 * Admin's may want to allow or disallow users to create, edit and delete marketplace posts. 
	 * To do this without relying on the post capability type, Prospress creates it's own type. 
	 * 
	 * @package Prospress
	 * @since 0.1
	 * 
	 * @global PP_Market_System $market_system Prospress market system object for this marketplace.
	 */
	public function register_post_type() {

		$args = array(
				'label' 	=> $this->name,
				'public' 	=> true,
				'show_ui' 	=> true,
				'rewrite' 	=> array( 'slug' => $this->name, 'with_front' => false ),
				'capability_type' => 'prospress_post', //generic to cover multiple Prospress marketplace types
				'show_in_nav_menus' => false,
				'exclude_from_search' => true,
				'menu_icon' => PP_PLUGIN_URL . '/images/auctions16.png',
				'supports' 	=> array(
								'title',
								'editor',
								'thumbnail',
								'post-thumbnails',
								'comments',
								'revisions' ),
				'labels'	=> array( 'name'	=> $this->labels[ 'name' ],
								'singular_name'	=> $this->labels[ 'singular_name' ],
								'add_new_item'	=> sprintf( __( 'Add New %s', 'prospress' ), $this->labels[ 'singular_name' ] ),
								'edit_item'		=> sprintf( __( 'Edit %s', 'prospress' ), $this->labels[ 'singular_name' ] ),
								'new_item'		=> sprintf( __( 'New %s', 'prospress' ), $this->labels[ 'singular_name' ] ),
								'view_item'		=> sprintf( __( 'View %s', 'prospress' ), $this->labels[ 'singular_name' ] ),
								'search_items'	=> sprintf( __( 'Seach %s', 'prospress' ), $this->labels[ 'name' ] ),
								'not_found'		=> sprintf( __( 'No %s found', 'prospress' ), $this->labels[ 'name' ] ),
								'not_found_in_trash' => sprintf( __( 'No %s found in Trash', 'prospress' ), $this->labels[ 'name' ] ) )
					);

		register_post_type( $this->name, $args );

	}


	/** 
	 * Create default sidebars for Prospress pages if the current theme doesn't support Prospress.
	 *
	 * The index sidebar automatically has the Sort and Filter widgets added to it on activation. 
	 * 
	 * @package Prospress
	 * @subpackage Posts
	 * @since 0.1
	 */
	public function register_sidebars(){

		if ( !file_exists( TEMPLATEPATH . '/index-' . $this->name . '.php' ) ){
			register_sidebar( array (
				'name' => $this->labels[ 'name' ] . ' ' . __( 'Index Sidebar', 'prospress' ),
				'id' => $this->name . '-index-sidebar',
				'description' => sprintf( __( "The sidebar for the %s index.", 'prospress' ), $this->labels[ 'name' ] ),
				'before_widget' => '<li id="%1$s" class="widget-container %2$s">',
				'after_widget' => "</li>",
				'before_title' => '<h3 class="widget-title">',
				'after_title' => '</h3>'
			) );
		}

		if ( !file_exists( TEMPLATEPATH . '/single-' . $this->name . '.php' ) ){
			register_sidebar( array (
				'name' => sprintf( __( 'Single %s Sidebar', 'prospress' ), $this->labels[ 'singular_name' ] ),
				'id' => $this->name . '-single-sidebar',
				'description' => sprintf( __( "The sidebar for a single %s.", 'prospress' ), $this->labels[ 'singular_name' ] ),
				'before_widget' => '<li id="%1$s" class="widget-container %2$s">',
				'after_widget' => "</li>",
				'before_title' => '<h3 class="widget-title">',
				'after_title' => '</h3>'
			) );
		}
	}


	/** 
	 * Add the Sort and Filter widgets to the default Prospress sidebar. This function is called on 
	 * Prospress' activation to help get everything working with one-click.
	 * 
	 * @package Prospress
	 * @subpackage Posts
	 * @since 0.1
	 */
	public function add_sidebars_widgets(){

		$sidebars_widgets = wp_get_sidebars_widgets();

		if( !isset( $sidebars_widgets[ $this->name . '-index-sidebar' ] ) )
			$sidebars_widgets[ $this->name . '-index-sidebar' ] = array();

		$sort_widget = get_option( 'widget_pp-sort' );
		if( empty( $sort_widget ) ){ //sort widget not added to any sidebars yet

			$sort_widget['_multiwidget'] = 1;

			$sort_widget[] = array(
								'title' => __( 'Sort by:', 'prospress' ),
								'post-desc' => 'on',
								'post-asc' => 'on',
								'end-asc' => 'on',
								'end-desc' => 'on',
								'price-asc' => 'on',
								'price-desc' => 'on'
								);

			$widget_id = end( array_keys( $sort_widget ) );

			update_option( 'widget_pp-sort', $sort_widget );

			array_push( $sidebars_widgets[ $this->name . '-index-sidebar' ], 'pp-sort-' . $widget_id );
		}

		$filter_widget = get_option( 'widget_bid-filter' );
		if( empty( $filter_widget ) ){ //filter_widget widget not added to any sidebars yet

			$filter_widget['_multiwidget'] = 1;

			$filter_widget[] = array( 'title' => __( 'Price:', 'prospress' ) );

			$filter_id = end( array_keys( $filter_widget ) );

			update_option( 'widget_bid-filter', $filter_widget );
			array_push( $sidebars_widgets[ $this->name . '-index-sidebar' ], 'bid-filter-' . $filter_id );
		}

		wp_set_sidebars_widgets( $sidebars_widgets );
	}


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
	public function is_post_admin_page(){
		global $post;

		if( $_GET[ 'post_type' ] == $this->name || $_GET[ 'post' ] == $this->name || $post->post_type == $this->name )
			return true;
		else
			return false;
	}


	/** 
	 * Removes the Prospress index page from the search results as it's meant to be an empty place-holder.
	 * 
	 * @package Prospress
	 * @subpackage Posts
	 * @since 0.1
	 */
	public function remove_index( $search ){
		global $wpdb;

		if ( isset( $_GET['s'] ) ) // remove index post from search results
			$search .= "AND ID != " . $this->get_index_id() . " ";

		return $search;
	}


	/**
	 * Template tag - is the current page/post the index for this market system's posts.
	 */
	public function is_index() {
		global $post;

		if( $post->post_name == $this->name )
			return true;
		else
			return false;
	}


	/**
	 * Template tag - is the current post a single post of this market system's type.
	 */
	public function is_single() {
		global $post;

		if( $post->post_type == $this->name )
			return true;
		else
			return false;
	}


	public function get_index_id() {
		global $wpdb;

		$index_id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_name = '" . $this->name . "'" );

		if( $index_id == NULL)
			return false; 
		else 
			return $index_id;
	}

	public function get_index_permalink() {

		$index_id = $this->get_index_id();

		if( $index_id == false )
			return false; 
		else
			return get_permalink( $index_id );
	}

	/**
	 * Creates an anchor tag linking to the user's payments, optionally prints.
	 * 
	 */
	function the_add_new_url( $desc = "Add New", $echo = '' ) {

		$add_new_tag = "<a href='" . $this->get_add_new_url() . "' title='$desc'>$desc " . $this->labels[ 'singular_name' ] . "</a>";

		if( $echo == 'echo' )
			echo $add_new_tag;
		else
			return $add_new_tag;
	}


	/**
	 * Gets the url to the user's feedback table.
	 * 
	 */
	function get_add_new_url() {
		 return admin_url( '/post-new.php?post_type=' . $this->name );
	}


	/** 
	 * Prospress posts end and a post's end date/time is important enough to be shown on the posts 
	 * admin table. Completed posts also require follow up actions, so these actions are shown on 
	 * the posts admin table, but only for completed posts. 
	 *
	 * This function adds the end date and completed posts actions columns to the column headings array
	 * for Prospress posts admin tables. 
	 * 
	 * @package Prospress
	 * @subpackage Posts
	 * @since 0.1
	 */
	public function post_columns( $column_headings ) {

		if( !is_pp_post_admin_page() )
			return $column_headings;

		if( strpos( $_SERVER['REQUEST_URI'], 'completed' ) !== false ) {
			$column_headings[ 'end_date' ] = __( 'End Time', 'prospress' );
			$column_headings[ 'post_actions' ] = __( 'Action', 'prospress' );
			unset( $column_headings[ 'date' ] );
		} else {
			$column_headings[ 'date' ] = __( 'Date Published', 'prospress' );
			$column_headings[ 'end_date' ] = __( 'End Time', 'prospress' );
		}

		return $column_headings;
	}


	/** 
	 * The admin tables for Prospress posts have custom columns for Prospress specific information. 
	 * This function fills those columns with their appropriate information.
	 * 
	 * @package Prospress
	 * @subpackage Posts
	 * @since 0.1
	 */
	public function post_custom_columns( $column_name, $post_id ) {
		global $wpdb;

		if( $column_name == 'end_date' ) {
			$end_time_gmt = get_post_end_time( $post_id );

			if ( $end_time_gmt == false || empty( $end_time_gmt ) ) {
				$m_time = $human_time = __( 'Not set.', 'prospress' );
				$time_diff = 0;
			} else {
				$human_time = pp_human_interval( $end_time_gmt - time(), 3 );
				$human_time .= '<br/>' . get_post_end_time( $post_id, 'mysql', 'user' );
			}
			echo '<abbr>' . apply_filters( 'post_end_date_column', $human_time, $post_id, $column_name) . '</abbr>';
		}

		if( $column_name == 'post_actions' ) {
			$actions = apply_filters( 'completed_post_actions', array(), $post_id );
			if( is_array( $actions ) && !empty( $actions ) ){?>
				<div class="prospress-actions">
					<ul class="actions-list">
						<li class="base"><?php _e( 'Take action:', 'prospress' ) ?></li>
					<?php foreach( $actions as $action => $attributes )
						echo "<li class='action'><a href='" . add_query_arg ( array( 'action' => $action, 'post' => $post_id ) , $attributes['url'] ) . "'>" . $attributes['label'] . "</a></li>";
					 ?>
					</ul>
				</div>
			<?php
			} else {
				echo '<p>' . __( 'No action can be taken.', 'prospress' ) . '</p>';
			}
		}
	}



	/** 
	 * Clean up anything added on activation, including the index page. 
	 * 
	 * @package Prospress
	 * @subpackage Posts
	 * @since 0.1
	 */
	public function deactivate(){

		if ( !current_user_can( 'edit_plugins' ) || !function_exists( 'delete_site_option' ) )
			return false;

		wp_delete_post( $this->get_index_id() );

		flush_rewrite_rules();
	}


	/** 
	 * When Prospress is uninstalled completely, remove the index page created on activation. 
	 * 
	 * @package Prospress
	 * @subpackage Posts
	 * @since 0.1
	 */
	public function uninstall(){
		global $wpdb;

		if ( !current_user_can( 'edit_plugins' ) || !function_exists( 'delete_site_option' ) )
			return false;

		wp_delete_post( $this->get_index_id() );

		$pp_post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = '" . $this->name . "'" ) );

		if ( $pp_post_ids )
			foreach ( $pp_post_ids as $pp_post_id )
				wp_delete_post( $pp_post_id );
	}
}
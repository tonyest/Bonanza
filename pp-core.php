<?php
/**
 * @package Prospress
 * @author Brent Shepherd
 * @version 0.1
 */

if( !defined( 'PP_CORE_DIR' ) )
	define( 'PP_CORE_DIR', PP_PLUGIN_DIR . '/pp-core' );
if( !defined( 'PP_CORE_URL' ) )
	define( 'PP_CORE_URL', PP_PLUGIN_URL . '/pp-core' );

global $pp_base_capability; 
$pp_base_capability = apply_filters( 'pp_base_capability', 'read' );

include_once( PP_CORE_DIR . '/core-widgets.php' );

/**
 * Sets up Prospress environment with any settings required and/or shared across the 
 * other components. 
 *
 * @package Prospress
 * @since 0.1
 */
function pp_core_install(){
	add_option( 'currency_type', 'USD' ); //default to the mighty green back
}
add_action( 'pp_activation', 'pp_core_install' );


/**
 * Adds the Prospress admin menu item to the Site Admin tab.
 *
 * @package Prospress
 * @since 0.1
 */
function pp_add_core_admin_menu() {
	global $pp_core_admin_page;

	$pp_core_admin_page = add_menu_page( __( 'Prospress', 'prospress' ), __( 'Prospress', 'prospress' ), 10, 'Prospress', '', PP_PLUGIN_URL . '/images/prospress16.png', 3 );
	$pp_core_settings_page = add_submenu_page( 'Prospress', __( 'Prospress Settings', 'prospress' ), __( 'General Settings', 'prospress' ), 10, 'Prospress', 'pp_settings_page' );
}
add_action( 'admin_menu', 'pp_add_core_admin_menu' );


/**
 * The core component only knows about a few settings required for Prospress to run. This functions outputs those settings as a
 * central Prospress settings administration page and saves settings when it is submitted. 
 *
 * Other components, and potentially plugins for Prospress, can output their own settings on this page with the 'pp_core_settings_page'
 * hook. They can also save these by adding them to the 'pp_options_whitelist' filter. This filter works in the same was the Wordpress
 * settings page filter of the similar name.
 *
 * @package Prospress
 * @since 0.1
 */
function pp_settings_page(){
	global $currencies, $currency;

	if( isset( $_POST[ 'submit' ] ) && $_POST[ 'submit' ] == 'Save' ){

		$pp_options_whitelist = apply_filters( 'pp_options_whitelist', array( 'general' => array( 'currency_type' ) ) );

		foreach ( $pp_options_whitelist[ 'general' ] as $option ) {
			$option = trim($option);
			$value = null;
			if ( isset( $_POST[ $option ] ) )
				$value = $_POST[ $option ];
			if ( !is_array( $value ) )
				$value = trim( $value );
			$value = stripslashes_deep( $value );
			
			update_option( $option, $value );
			
			if( $option == 'currency_type' )
				$currency = $value;
		}
		update_option( 'pp_show_welcome', 'false' );
		$updated_message = __( 'Settings Updated.' );
	}
	?>
	<div class="wrap">
		<?php screen_icon( 'prospress' ); ?>
		<h2><?php _e( 'Prospress Settings', 'prospress' ) ?></h2>
		<?php if( isset( $updated_message ) ) { ?>
			<div id='message' class='updated fade'>
				<p><?php echo $updated_message; ?></p>
			</div>
		<?php } ?>
		<form action="" method="post">
			<h3><?php _e( 'Currency', 'prospress' )?></h3>
			<p><?php _e( 'Please choose a default currency for all transactions in your marketplace.', 'prospress' ); ?></p>

			<label for='currency_type'>
				<?php _e('Currency:' , 'prospress' );?>
				<select id='currency_type' name='currency_type'>
				<?php foreach( $currencies as $code => $details ) { ?>
					<option value='<?php echo $code; ?>' <?php selected( $currency, $code ); ?> >
						<?php echo $details[ 'currency_name' ]; ?> (<?php echo $code . ', ' . $details[ 'symbol' ]; ?>)
					</option>
				<?php } ?>
				</select>
			</label>
		<?php do_action( 'pp_core_settings_page' ); ?>
		<p class="submit">
			<input type="submit" value="Save" class="button-primary" name="submit">
		</p>
		</form>
	</div>
	<?php
}


/** 
 * Create and set global currency variables for sharing all currencies available in the marketplace and the currently 
 * selected currency type and symbol.
 * 
 * To make a new currency available, simply add an array to this variable. The key for this array must be the currency's 
 * ISO 4217 code. The array must contain the currency name and symbol. 
 * e.g. $currencies['CAD'] = array( 'currency' => __('Canadian Dollar', 'prospress' ), 'symbol' => '&#36;' ).
 * 
 * Once added, the currency will be available for selection from the admin page.
 * 
 * @package Prospress
 * @since 0.1
 * 
 * @global array currencies Prospress currency list. 
 * @global string currency The currency chosen for the marketplace. 
 * @global string currency_symbol Symbol of the marketplace's chosen currency, eg. $. 
 */
function pp_set_currency(){
	global $currencies, $currency, $currency_symbol;

	$currencies = array(
		'AUD' => array( 'currency_name' => __('Australian Dollar', 'prospress' ), 'symbol' => '&#36;' ),
		'GBP' => array( 'currency_name' => __('British Pound', 'prospress' ), 'symbol' => '&#163;' ),
		'EUR' => array( 'currency_name' => __('Euro', 'prospress' ), 'symbol' => '&#8364;' ),
		'USD' => array( 'currency_name' => __('United States Dollar', 'prospress' ), 'symbol' => '&#36;' )
		);

	$currency = get_option( 'currency_type' );

	$currency_symbol = $currencies[ $currency ][ 'symbol' ];
}
add_action( 'init', 'pp_set_currency' );


/** 
 * For displaying monetary numbers, it's important to transform the number to include the currency symbol and correct number of decimals. 
 * 
 * @param int | float $number the numerical value to be formatted
 * @param int | float optional $decimals the number of decimal places to return, default 2
 * @param string optional $currency ISO 4217 code representing the currency. eg. for Japanese Yen, $currency == 'JPY'.
 * @return string The formatted value with currency symbol.
 **/
function pp_money_format( $number, $decimals = '', $currency = '' ){
	global $currencies, $currency_symbol;

	if( empty( $decimals ) && $number > 1000 )
		$decimals = 0;
	else
		$decimals = 2;

	$currency = strtoupper( $currency );

	if( empty( $currency ) || !array_key_exists( $currency, $currencies ) )
		$currency_sym = $currency_symbol;
	else
		$currency_sym = $currencies[ $currency ][ 'symbol' ];

	return $currency_sym . ' ' . number_format_i18n( $number, $decimals );
}


/** 
 * Add admin style and scripts that are required by more than one component. 
 * 
 * @package Prospress
 * @since 0.1
 */
function pp_core_admin_head() {

	if( strpos( $_SERVER['REQUEST_URI'], 'Prospress' ) !== false || strpos( $_SERVER['REQUEST_URI'], 'invoice_settings' ) !== false || strpos( $_SERVER['REQUEST_URI'], '_tax' ) !== false || strpos( $_SERVER['REQUEST_URI'], 'completed' ) !== false  || strpos( $_SERVER['REQUEST_URI'], 'bids' ) !== false )
		wp_enqueue_style( 'prospress-admin',  PP_CORE_URL . '/prospress-admin.css' );
}
add_action('admin_menu', 'pp_core_admin_head');


/**
 * A welcome message with a few handy links to help people get started and encourage exploration of their
 * site's new Prospress features.
 *
 * @package Prospress
 * @since 0.1
 */
function pp_welcome_notice(){
	global $market_systems;
	
	if( get_option( 'pp_show_welcome' ) == 'false' ){
		return;
	}elseif( isset( $_GET[ 'pp_hide_wel' ] ) && $_GET[ 'pp_hide_wel' ] == 1 ) {
		update_option( 'pp_show_welcome', 'false' );
		return;
	}

	$index_id = $market_systems['auctions']->post->get_index_id();

	echo "<div id='prospress-welcome' class='updated fade'><p><strong>".__('Congratulations.', 'prospress')."</strong> ".
	sprintf( __('Your WordPress site is now prosperous. You can add your first <a href="%1$s">auction</a>, '), "post-new.php?post_type=auctions").
	sprintf( __('modify your auctions\' <a href="%1$s">index page</a> or '), "post.php?post=$index_id&action=edit").
	sprintf( __('configure your marketplace <a href="%1$s">settings</a>. '), "admin.php?page=Prospress").
	sprintf( __('<a href="%1$s">&laquo; Hide &raquo;</a>'), add_query_arg( 'pp_hide_wel', '1', $_SERVER['REQUEST_URI'] ))."</p></div>";
}
add_action( 'admin_notices', 'pp_welcome_notice' );


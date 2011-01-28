<?php
/**
* Crowd Funding for Wordpress
*
* PP_Crowd_Funding is an extension for vanilla Prospress to implement a crowd-funding auction framework in which Wordpress developers and supporters may contribute to the combined completion of features.
*
* @copyright 2011 Leonard's Ego
* @license GLP2 http://wordpress.org/about/gpl/
* @version Release: 0.1
* @package Prospress
* @link http://
* @since 0.1
*/
require_once ( PP_BIDS_DIR . '/pp-market-system.class.php' ); // Base class
class PP_Crowd_Funding extends PP_Market_System {

	/**
	* Constructor
	*
	* @param ;
	* @throws ;
	* @return ;
	*/
	public function __construct() {
		do_action( 'pcf_init' );
		
		$args = array(
				'description' => 'Project Bonanza: Prospress Crowd-funding system for Wordpress',
				'bid_button_value' => __( 'Bid!', 'prospress' ),
				'adds_post_fields' => true
				);

		parent::__construct( __( 'auctions', 'prospress' ), $args ); // complete parent constructor
	}
	
	
	
}
?>
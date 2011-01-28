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
		/*
			$args[ 'bid_button_value' ];
			$args[ 'post_table_columns' ];
			$args[ 'bid_table_headings' ];
			$args[ 'adds_post_fields' ];		
		*/
		$args = array(
				'description' => 'Project Bonanza: Prospress Crowd-funding system for Wordpress',
				'bid_button_value' => __( 'Bid!', 'prospress' ),
				'adds_post_fields' => true
				);

		parent::__construct( __( 'auctions', 'prospress' ), $args ); // complete parent constructor
	}
	/**
	* The fields that make up the bid form.
	* The <form> tag and a bid form header and footer are automatically generated for the class.
	* You only need to enter the fields to capture information required by your market system, eg. price.
	*
	* @param $post_id
	* @throws Some_Exception_Class If something interesting cannot happen
	* @return Status
	*/
	protected function bid_form_fields( $post_id = NULL ) {
		
	}
	/**
	* Process the bid form fields upon submission.
	*
	* @param $post_id
	* @param $bid_value
	* @param $bidder_id
	* @throws Some_Exception_Class If something interesting cannot happen
	* @return Status
	*/
	protected function bid_form_submit( $post_id = NULL, $bid_value = NULL, $bidder_id = NULL ) {
		
	}
	/**
	* Validate and sanitize a bid upon submission, set bid_status and bid_message as needed
	*
	* @param $post_id
	* @param $bid_value
	* @param $bidder_id
	* @throws Some_Exception_Class If something interesting cannot happen
	* @return Status
	*/
	protected function validate_bid( $post_id, $bid_value, $bidder_id ) {
		
	}
}
?>
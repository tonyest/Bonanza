<?php
/**
 * Auction bid system class.
 * 
 * This class extends the base Market System class to create a standard auction system.
 *
 * @package Prospress
 * @since 0.1
 */
require_once ( PP_BIDS_DIR . '/pp-market-system.class.php' ); // Base class

class PP_Auction_Bid_System extends PP_Market_System {

	// Constructors

	public function __construct() {
		do_action( 'auction_init' );

		if ( !defined( 'BID_INCREMENT' ) )
			define( 'BID_INCREMENT', '0.05' );

		$args = array(
				'description' => 'The Default Prospress Standard Auction System.',
				'bid_button_value' => __( 'Bid!', 'prospress' ),
				'adds_post_fields' => true
				);

		parent::__construct( __( 'auctions', 'prospress' ), $args );
	}

	protected function bid_form_fields( $post_id = NULL ) { 
		global $post_ID, $currency_symbol;

		$post_id = ( $post_id === NULL ) ? $post_ID : $post_id;
		$bid_count = $this->get_bid_count( $post_id );
		$bid_bid_form_fields = '';
		$dont_echo = false;

		if( $bid_count == 0 ){
			$bid_bid_form_fields .= '<div id="current_bid_val">' . __("Starting Price: ", 'prospress' ) . pp_money_format( get_post_meta( $post_id, 'start_price', true ) ) . '</div>';
		} else {
			$bid_bid_form_fields .= '<div id="current_bid_num">' . __("Number of Bids: ", 'prospress' ) . $this->the_bid_count( $post_id, $dont_echo ) . '</div>';
			$bid_bid_form_fields .= '<div id="winning_bidder">' . __("Winning Bidder: ", 'prospress' ) . $this->the_winning_bidder( $post_id, $dont_echo ) . '</div>';
			$bid_bid_form_fields .= '<div id="current_bid_val">' . __("Current Bid: ", 'prospress' ) . $this->the_winning_bid_value( $post_id, $dont_echo ) . '</div>';
		}
		$bid_bid_form_fields .= '<label for="bid_value" class="bid-label">' . __( 'Enter max bid: ', 'prospress' ) . $currency_symbol . ' </label>';
		$bid_bid_form_fields .= '<input type="text" aria-required="true" tabindex="1" size="8" value="" id="bid_value" name="bid_value"/>';
		
		return $bid_bid_form_fields;
	}

	protected function bid_form_submit( $post_id = NULL, $bid_value = NULL, $bidder_id = NULL ){
		global $user_ID, $wpdb, $blog_id;
		nocache_headers();

		//Get Bid details
		$post_id 		= ( isset( $_REQUEST[ 'post_ID' ] ) ) ? intval( $_REQUEST[ 'post_ID' ] ) : $post_id;
		$bid_value		= ( isset( $_REQUEST[ 'bid_value' ] ) ) ? floatval( $_REQUEST[ 'bid_value' ] ) : $bid_value;
		$bidder_id 		= ( isset( $bidder_id ) ) ? $bidder_id : $user_ID;
		$bid_date 		= current_time( 'mysql' );
		$bid_date_gmt 	= current_time( 'mysql', 1 );

		do_action( 'get_auction_bid', $post_id, $bid_value, $bidder_id, $_GET );

		$bid = compact("post_id", "bidder_id", "bid_value", "bid_date", "bid_date_gmt" );

		if( $this->is_post_valid( $post_id ) && $this->is_bid_valid( $post_id, $bid_value, $bidder_id ) ) {
			$bid[ 'bid_status' ] = $this->bid_status; //set in is_valid calls
			$bid = apply_filters( 'bid_pre_db_insert', $bid );
			$this->update_bid( $bid );
		} else {
			$bid[ 'bid_status' ] = $this->bid_status;
		}

		$bid[ 'message_id' ] = $this->message_id;
		return $bid;
	}

	protected function validate_bid( $post_id, $bid_value, $bidder_id ){

		$post_max_bid		= $this->get_max_bid( $post_id );
		$bidders_max_bid	= $this->get_users_max_bid( $bidder_id, $post_id );

		if ( $bidder_id == get_post( $post_id )->post_author ) {
			$this->message_id = 11;
			$this->bid_status = 'invalid';
		} elseif ( empty( $bid_value ) || $bid_value === NULL || !preg_match( '/^[0-9]*\.?[0-9]*$/', $bid_value ) ) {
			$this->message_id = 7;
			$this->bid_status = 'invalid';
		} elseif ( $bidder_id != $this->get_winning_bid( $post_id )->bidder_id ) {
			$current_winning_bid_value = $this->get_winning_bid_value( $post_id );
			if ( $this->get_bid_count( $post_id ) == 0 ) {
				$start_price = get_post_meta( $post_id, 'start_price', true );
				if ( $bid_value < $start_price ){
					$this->message_id = 9;
					$this->bid_status = 'invalid';
				} else {
					$this->message_id = 0;
					$this->bid_status = 'winning';
				}
			} elseif ( $bid_value > $post_max_bid->bid_value ) {
				$this->message_id = 1;
				$this->bid_status = 'winning';
			} elseif ( $bid_value <= $current_winning_bid_value ) {
				$this->message_id = 3;
				$this->bid_status = 'invalid';
			} elseif ( $bid_value <= $post_max_bid->bid_value ) {
				$this->message_id = 2;
				$this->bid_status = 'outbid';
			}
		} elseif ( $bid_value > $bidders_max_bid->bid_value ){ //user increasing max bid
			$this->message_id = 4;
			$this->bid_status = 'winning';
		} elseif ( $bid_value < $bidders_max_bid->bid_value ) { //user trying to decrease max bid
			$this->message_id = 5;
			$this->bid_status = 'invalid';
		} else {
			$this->message_id = 6;
			$this->bid_status = 'invalid';
		}

		return array( 'bid_status' => $this->bid_status, 'message_id' => $this->message_id );
	}

	protected function update_bid( $bid ){
		global $wpdb;

		$current_winning_bid_value 	= $this->get_winning_bid_value( $bid[ 'post_id' ] );

		// No need to update winning bid for invalid bids, bids too low
		if ( $bid[ 'bid_status' ] == 'invalid' )
			return $current_winning_bid_value;

		$posts_max_bid			= $this->get_max_bid( $bid[ 'post_id' ] );
		$current_winning_bid_id	= $this->get_winning_bid( $bid[ 'post_id' ] )->bid_id;

		if( $this->message_id == 0 ) { // first bid
			$start_price = get_post_meta( $bid[ 'post_id' ], 'start_price', true );
			if( (float)$start_price != 0 ){
				$new_winning_bid_value = $start_price;
			} else {
				$new_winning_bid_value = ( $bid[ 'bid_value' ] * BID_INCREMENT );
			}
		} elseif ( $this->message_id == 1 ) { //Bid value is over max bid & bidder different to current winning bidder
			if ( $bid[ 'bid_value' ] > ( $posts_max_bid->bid_value * ( BID_INCREMENT + 1 ) ) ) {
				$new_winning_bid_value = $posts_max_bid->bid_value * ( BID_INCREMENT + 1 );
			} else {
				$new_winning_bid_value = $bid[ 'bid_value' ];
			}
		} elseif ( $this->message_id == 2 ) {
			$bid_value_incremented = $bid[ 'bid_value' ] * ( 1 + BID_INCREMENT );
			if ( $posts_max_bid->bid_value > $bid_value_incremented ) {
				$new_winning_bid_value = $bid_value_incremented;
			} else {
				$new_winning_bid_value = $posts_max_bid->bid_value;
			}
		} elseif ( $this->message_id == 4 ) { // bidder increasing max bid, set their previous bid as 'outbid'
			$wpdb->update( $wpdb->bids, array( 'bid_status' => 'outbid' ), array( 'bid_id' => $current_winning_bid_id ) );
			$new_winning_bid_value = $current_winning_bid_value;
		}

		//$wpdb->insert( $wpdb->bids, $bid );
		parent::update_bid( $bid );

		if( $this->message_id != 2 ){ // valid bid, over existing max, change winning bid id and bid value in bids meta table
			$wpdb->update( $wpdb->bids, array( 'bid_status' => 'outbid' ), array( 'bid_id' => $current_winning_bid_id ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->bidsmeta WHERE bid_id = %d AND meta_key = 'winning_bid_value'", $current_winning_bid_id ) );
			$new_winning_bid_id = $this->get_winning_bid( $bid[ 'post_id' ] )->bid_id;
			$wpdb->insert( $wpdb->bidsmeta, array( 'bid_id' => $new_winning_bid_id, 'meta_key' => 'winning_bid_value', 'meta_value' => $new_winning_bid_value ) );
		} else { // current winning bid is still winning bid, just need to update winning bid value
			$wpdb->update( $wpdb->bidsmeta, array( 'meta_value' => $new_winning_bid_value ), array( 'bid_id' => $current_winning_bid_id, 'meta_key' => 'winning_bid_value' ) );
		}

		return $new_winning_bid_value;
	}

	public function post_fields(){
		global $post_ID, $currency_symbol;
		$start_price = get_post_meta( $post_ID, 'start_price', true );

		wp_nonce_field( __FILE__, 'selling_options_nonce', false ) ?>
		<table>
		  <tbody>
				<tr>
				  <td class="left">
					<label for="start_price"><?php echo __("Starting Price: ", 'prospress' ) . $currency_symbol; ?></label>
					</td>
					<td>
				 		<input type="text" name="start_price" value="<?php echo number_format_i18n( $start_price, 2 ); ?>" size="20" />
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	public function post_fields_save( $post_id, $post ){
		global $wpdb;

		if(wp_is_post_revision($post_id))
			$post_id = wp_is_post_revision($post_id);

		if ( 'page' == $_POST['post_type'] )
			return $post_id;
		elseif ( !current_user_can( 'edit_post', $post_id ))
			return $post_id;

		/** @TODO casting start_price as a float and removing ',' and ' ' will cause a bug for international currency formats. */
		$_POST[ 'start_price' ] = (float)str_replace( array(",", " "), "", $_POST[ 'start_price' ] );

		// Verify options nonce because save_post can be triggered at other times
		if ( !isset( $_POST[ 'selling_options_nonce' ] ) || !wp_verify_nonce( $_POST['selling_options_nonce'], __FILE__) ) {
			return $post_id;
		} else { //update post options
			update_post_meta( $post_id, 'start_price', $_POST[ 'start_price' ] );
		}
	}

	// Called on bid_table_actions hook
	public function add_bid_table_actions( $actions, $post_id ) {

		$post = get_post( $post_id );

		if( $post->post_status == 'completed' )
			return;

		$permalink = get_permalink( $post_id );

		$actions[ 'increase-bid' ] = array( 'label' => __( 'Increase Bid', 'prospress' ),
											'url' => $permalink );
		return $actions;
	}

	// Adds bid system specific sort options to the post system sort widget
	public function add_sort_options( $sort_options ){
		$sort_options['price-asc' ] = __( 'Price: low to high', 'prospress' );
		$sort_options['price-desc' ] = __( 'Price: high to low', 'prospress' );

		return $sort_options;
	}
}

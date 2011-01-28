<?php
/**
 * An assortment of widgets for providing information about Prospress posts.
 *
 * @package Prospress
 * @author Brent Shepherd
 * @version 0.1
 */

/**
 * Prospress custom taxonomy cloud widget
 *
 * @since 0.1
 */
class PP_Admin_Widget extends WP_Widget {

	function PP_Admin_Widget() {
		$widget_ops = array( 'description' => __( 'Links to signup, post auctions, view payments & bid history.' ) );
		$this->WP_Widget( 'pp_admin', __( 'Prospress Admin Links' ), $widget_ops );
	}

	function widget( $args, $instance ) {
		global $market_systems, $pp_base_capability;

		extract( $args );

		if ( !empty($instance['title']) ) {
			$title = $instance['title'];
		} else {
			$title = __( 'Your Prospress' );
		}
		$title = apply_filters('widget_title', $title, $instance, $this->id_base);

		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title;
		echo '<div class="prospress-meta">';
		echo '<ul>';
		if( !is_user_logged_in() || is_super_admin() ) {
			wp_register('<li>', ' | ');
			wp_loginout();
			echo '</li>';
		}
		if( !is_user_logged_in() || current_user_can( 'edit_prospress_posts' ) ) {
			echo '<li>' . $market_systems[ 'auctions' ]->post->the_add_new_url() . '</li>';
		}
		echo '<li>' . $market_systems[ 'auctions' ]->the_bids_url() . '</li>';
		echo '<li>' . pp_the_payments_url( 'Your Payments' ) . '</li>';
		echo '<li>' . pp_the_feedback_url( 'Your Feedback' ) . '</li>';
		echo '</ul>';
		echo "</div>\n";
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance['title'] = strip_tags(stripslashes($new_instance['title']));
		$instance['taxonomy'] = stripslashes($new_instance['taxonomy']);
		return $instance;
	}

	function form( $instance ) {
		?>
		<p><?php _e( 'Make it easy for traders to get into the backend by including links to add a new auction and view payment/bid/feedback history.', 'prospress' ); ?></p>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:') ?></label>
		<input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php if (isset ( $instance['title'])) {echo esc_attr( $instance['title'] );} ?>" /></p>
		<?php
	}

	private function get_current_taxonomy($instance) {
		if ( !empty($instance['taxonomy']) && taxonomy_exists($instance['taxonomy']) )
			return $instance['taxonomy'];
	}
}
add_action( 'widgets_init', create_function( '', 'return register_widget("PP_Admin_Widget");' ) );

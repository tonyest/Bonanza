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
class PP_Tag_Cloud_Widget extends WP_Widget {

	function PP_Tag_Cloud_Widget() {
		$widget_ops = array( 'description' => __( 'The most used items of your Prospress taxonomy, in cloud form' ) );
		$this->WP_Widget( 'pp_tag_cloud', __( 'Prospress Tag Cloud' ), $widget_ops );
	}

	function widget( $args, $instance ) {
		extract( $args );
		$current_taxonomy = $this->get_current_taxonomy($instance);
		if ( !empty($instance['title']) ) {
			$title = $instance['title'];
		} else {
			$tax = get_taxonomy($current_taxonomy);
			$title = $tax->labels->name;
		}
		$title = apply_filters('widget_title', $title, $instance, $this->id_base);

		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title;
		echo '<div>';
		wp_tag_cloud( apply_filters('widget_tag_cloud_args', array('taxonomy' => $current_taxonomy) ) );
		echo "</div>\n";
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance['title'] = strip_tags(stripslashes($new_instance['title']));
		$instance['taxonomy'] = stripslashes($new_instance['taxonomy']);
		return $instance;
	}

	function form( $instance ) {
		global $market_systems; 

		$current_taxonomy = $this->get_current_taxonomy($instance);
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:') ?></label>
		<input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php if (isset ( $instance['title'])) {echo esc_attr( $instance['title'] );} ?>" /></p>
		<p><label for="<?php echo $this->get_field_id('taxonomy'); ?>"><?php _e('Taxonomy:') ?></label>
		<select class="widefat" id="<?php echo $this->get_field_id('taxonomy'); ?>" name="<?php echo $this->get_field_name('taxonomy'); ?>">
		<?php foreach ( get_object_taxonomies( $market_systems[ 'auctions' ]->name() ) as $taxonomy ) :
				$tax = get_taxonomy( $taxonomy );
		?>
			<option value="<?php echo esc_attr($taxonomy) ?>" <?php selected($taxonomy, $current_taxonomy) ?>><?php echo $tax->labels->name; ?></option>
		<?php endforeach; ?>
		</select></p><?php
	}

	private function get_current_taxonomy($instance) {
		if ( !empty($instance['taxonomy']) && taxonomy_exists($instance['taxonomy']) )
			return $instance['taxonomy'];
	}
}
if( is_using_custom_taxonomies() )
	add_action( 'widgets_init', create_function( '', 'return register_widget("PP_Tag_Cloud_Widget");' ) );

/**
 * A list of the taxonomy items that apply to the current Prospress post
 *
 * @since 0.1
 */
class PP_Taxonomies_List_Widget extends WP_Widget {

	function PP_Taxonomies_List_Widget() {
		global $market_systems;

		$widget_ops = array( 'description' => sprintf( __('List of taxonomy items that apply to a single %s', 'prospress' ), $market_systems['auctions']->labels[ 'singular_name' ] ) );
		$this->WP_Widget( 'pp_single_tax', __( 'Prospress Taxonomy List' ), $widget_ops );
	}

	function widget( $args, $instance ) {

		if( !is_single() )
			return;

		extract( $args );

		echo $before_widget;

		echo $before_title;
		echo ( $instance['title'] ) ? $instance['title'] : __( 'Details:', 'prospress' );
		echo $after_title;

		echo '<div class="textwidget">';
		pp_get_the_term_list();
		echo '</div>';

		echo $after_widget;
	}

	function form( $instance ) {

		$title = ( $instance['title'] ) ? $instance['title'] : __( 'Details:', 'prospress' );
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'prospress' ); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></label></p>
		<?php
	}

	function update( $new_instance, $old_instance ) {

		$instance = $old_instance;

		/* Strip tags (if needed) and update the widget settings. */
		$instance['title'] = strip_tags( $new_instance['title'] );

		return $instance;
	}
}
if( is_using_custom_taxonomies() )
	add_action( 'widgets_init', create_function( '', 'return register_widget("PP_Taxonomies_List_Widget");' ) );


/**
 * Countdown to the end of a Prospress post.
 *
 * @since 0.1
 */
class PP_Countdown_Widget extends WP_Widget {

	function PP_Countdown_Widget() {
		global $market_systems;

		$widget_ops = array( 'description' => sprintf( __('The time until the end of an %s', 'prospress' ), $market_systems[ 'auctions' ]->labels[ 'singular_name' ] ) );
		$this->WP_Widget( 'pp_countdown', __( 'Prospress Countdown' ), $widget_ops );
	}

	function widget( $args, $instance ) {

		if( !is_single() )
			return;

		extract( $args );

		echo $before_widget;

		echo $before_title;
		echo ( $instance['title'] ) ? $instance['title'] : __( 'Ending:', 'prospress' );
		echo $after_title;
		echo "<div class='countdown' id='".get_post_end_time( $the_ID, 'timestamp', 'gmt' )."'>";
		the_post_end_time();
		echo '</div>';

		echo $after_widget;
	}

	function form( $instance ) {

		$title = ( $instance['title'] ) ? $instance['title'] : __( 'Ending:', 'prospress' );
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'prospress' ); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></label></p>
		<?php
	}

	function update( $new_instance, $old_instance ) {

		$instance = $old_instance;

		/* Strip tags (if needed) and update the widget settings. */
		$instance['title'] = strip_tags( $new_instance['title'] );

		return $instance;
	}
}
add_action( 'widgets_init', create_function( '', 'return register_widget("PP_Countdown_Widget");' ) );


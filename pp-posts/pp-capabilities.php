<?php
/**
 * Prospress Capabilities
 *
 * Map custom post type capabilities to augment the lack of custom post type capability checking in the WP core. 
 *
 * @package Prospress
 * @author Brent Shepherd
 * @version 0.1
 */


/** 
 * Allow site admins to choose which roles can do what to marketplace posts.
 * 
 * Admin's may want to allow or disallow users to create, edit and delete prospress marketplace 
 * posts. To do this without relying on the built-in post capability types, Prospress creates it's 
 * own type, 'prospress_post'. This function provides an admin menu for selecting which roles can do 
 * what to posts. Meaning, subscribers & custom roles can be given the capabiltiy to publish 
 * Prospress posts.
 * 
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 */
function pp_capabilities_settings_page() { 
	global $wp_roles;
	
	$post_type = 'Auctions'; // Less confusing referring to Prospress posts as Auctions when that is the only option for a Prospress post, this will be changed once there are more options available

	$role_names = $wp_roles->get_names();
	$roles = array();

	foreach ( $role_names as $key => $value ) {
		$roles[ $key ] = get_role( $key );
		$roles[ $key ]->display_name = $value;
	}
	?>

	<?php wp_nonce_field( 'pp_capabilities_settings' ); ?>
	<div class="prospress-capabilities">
		<h3><?php _e( 'Capabilities', 'prospress' ); ?></h3>
		<p><?php printf( __( 'All registered users can make bids, but you can control which users are able to publish and edit %s.', 'prospress' ), $post_type ); ?></p>
		<div class="prospress-capability">
			<h4><?php printf( __( "Publish %s", 'prospress' ), $post_type ); ?></h4>
			<?php foreach ( $roles as $role ): ?>
			<label for="<?php echo $role->name; ?>-publish">
				<input type="checkbox" id="<?php echo $role->name; ?>-publish" name="<?php echo $role->name; ?>-publish"<?php checked( $role->capabilities[ 'publish_prospress_posts' ], 1 ); ?> />
				<?php echo $role->display_name; ?>
			</label>
			<?php endforeach; ?>
		</div>
		<div class="prospress-capability">
			<h4><?php printf( __( "Edit Own %s", 'prospress' ), $post_type ); ?></h4>
			<?php foreach ( $roles as $role ): ?>
			<label for="<?php echo $role->name; ?>-edit">
			  	<input type="checkbox" id="<?php echo $role->name; ?>-edit" name="<?php echo $role->name; ?>-edit"<?php checked( $role->capabilities[ 'edit_published_prospress_posts' ], 1 ); ?> />
				<?php echo $role->display_name; ?>
			</label>
			<?php endforeach; ?>
		</div>
		<div class="prospress-capability">
			<h4><?php printf( __( "Edit Others' %s", 'prospress' ), $post_type ); ?></h4>
			<?php foreach ( $roles as $role ): ?>
			<label for="<?php echo $role->name; ?>-edit-others">
				<input type="checkbox" id="<?php echo $role->name; ?>-edit-others" name="<?php echo $role->name; ?>-edit-others"<?php checked( $role->capabilities[ 'edit_others_prospress_posts' ], 1 ); ?> />
				<?php echo $role->display_name; ?>
			</label>
			<?php endforeach; ?>
		</div>
		<div class="prospress-capability">
			<h4><?php printf( __( "View Private %s", 'prospress' ), $post_type ); ?></h4>
			<?php foreach ( $roles as $role ): ?>
			<label for="<?php echo $role->name; ?>-private">
				<input type="checkbox" id="<?php echo $role->name; ?>-private" name="<?php echo $role->name; ?>-private"<?php checked( $role->capabilities[ 'read_private_prospress_posts' ], 1 ); ?> />
				<?php echo $role->display_name; ?>
			</label>
			<?php endforeach; ?>
		</div>
		<div class="prospress-capability">
			<h4><?php printf( __( "Upload Media", 'prospress' ) ); ?></h4>
			<?php foreach ( $roles as $role ): ?>
			<label for="<?php echo $role->name; ?>-media">
				<input type="checkbox" id="<?php echo $role->name; ?>-media" name="<?php echo $role->name; ?>-media"<?php checked( $role->capabilities[ 'upload_files' ], 1 ); ?> />
				<?php echo $role->display_name; ?>
			</label>
			<?php endforeach; ?>
		</div>
	</div>
<?php
}
add_action( 'pp_core_settings_page', 'pp_capabilities_settings_page' );


/** 
 * Save capabilities settings when the admin page is submitted page. As the settings don't need to be stored in 
 * the options table of the database, they're not added to the whitelist as is expected by this filter, instead 
 * they're added to the appropriate roles.
 * 
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 */
function pp_capabilities_whitelist( $whitelist_options ) {
	global $wp_roles;

    if ( $_POST['_wpnonce' ] && check_admin_referer( 'pp_capabilities_settings' ) && current_user_can( 'manage_options' ) ){

		$role_names = $wp_roles->get_names();
		$roles = array();

		foreach ( $role_names as $key=>$value ) {
			$roles[ $key ] = get_role( $key );
			$roles[ $key ]->display_name = $value;
		}

		foreach ( $roles as $key => $role ) {

			// Shared capability
			if ( ( isset( $_POST[ $key . '-publish' ] )  && $_POST[ $key . '-publish' ] == 'on' ) || ( isset( $_POST[ $key . '-edit' ] )  && $_POST[ $key . '-edit' ] == 'on' ) || ( isset( $_POST[ $key . '-edit-others' ] )  && $_POST[ $key . '-edit-others' ] == 'on' ) ) {
				$role->add_cap( 'edit_prospress_posts' );
			} else {
				$role->remove_cap( 'edit_prospress_posts' );
			}

			if ( isset( $_POST[ $key . '-publish' ] )  && $_POST[ $key . '-publish' ] == 'on' ) {
				$role->add_cap( 'publish_prospress_posts' );
				$role->add_cap( 'delete_prospress_posts' );
			} else {
				$role->remove_cap( 'publish_prospress_posts' );
				$role->remove_cap( 'delete_prospress_posts' );
			}

			if ( ( isset( $_POST[ $key . '-edit' ] )  && $_POST[ $key . '-edit' ] == 'on' ) || ( isset( $_POST[ $key . '-edit-others' ] )  && $_POST[ $key . '-edit-others' ] == 'on' ) ) {
				$role->add_cap( 'edit_published_prospress_posts' );
				$role->add_cap( 'delete_published_prospress_posts' );
				$role->add_cap( 'edit_private_prospress_posts' );
			} else {
				$role->remove_cap( 'edit_published_prospress_posts' );
				$role->remove_cap( 'delete_published_prospress_posts' );
				$role->remove_cap( 'edit_private_prospress_posts' );
			}

			if ( isset( $_POST[ $key . '-edit-others' ] )  && $_POST[ $key . '-edit-others' ] == 'on' ) {
				$role->add_cap( 'edit_others_prospress_posts' );
			} else {
				$role->remove_cap( 'edit_others_prospress_posts' );
	        }

			if ( isset( $_POST[ $key . '-private' ] )  && $_POST[ $key . '-private' ] == 'on' ) {
				$role->add_cap( 'read_private_prospress_posts' );
			} else {
				$role->remove_cap( 'read_private_prospress_posts' );
			}

			if ( isset( $_POST[ $key . '-media' ] )  && $_POST[ $key . '-media' ] == 'on' ) {
				$role->add_cap( 'upload_files' );
			} else {
				$role->remove_cap( 'upload_files' );
			}

		}
    }

	return $whitelist_options;
}
add_filter( 'pp_options_whitelist', 'pp_capabilities_whitelist' );


/** 
 * Custom Post meta capabilities are not mapped by WordPress, so need to manually
 * map them.
 * 
 * @package Prospress
 * @subpackage Posts
 * @since 0.1
 */
function pp_map_meta_cap( $caps, $cap, $user_id, $args ){

	if( $cap == 'edit_prospress_post' ) {

		$author_data = get_userdata( $user_id );

		$post = get_post( $args[0] );

		$post_type = get_post_type_object( $post->post_type );

		$post_author_data = get_userdata( $post->post_author );

		if ( is_object( $post_author_data ) && $user_id == $post_author_data->ID ) {

			if ( 'publish' == $post->post_status ) {
				$caps[0] = 'edit_published_prospress_posts';
			} elseif ( 'private' == $post->post_status ) {
				$caps[0] = 'edit_private_prospress_posts';
			} elseif ( 'trash' == $post->post_status ) {
				if ('publish' == get_post_meta($post->ID, '_wp_trash_meta_status', true) )
					$caps[0] = 'edit_published_prospress_posts';
			} else {
				$caps[0] = 'edit_prospress_posts';
				$caps[] = 'publish_prospress_posts';
			}
		} else {
			$caps[0] = 'edit_others_prospress_posts';

			if ( 'publish' == $post->post_status )
				$caps[] = 'edit_published_prospress_posts';
			elseif ( 'private' == $post->post_status )
				$caps[] = 'edit_private_prospress_posts';
		}
	} elseif( $cap == 'delete_prospress_post' ) {
		$author_data = get_userdata( $user_id );
		$post = get_post( $args[0] );

		if ( '' != $post->post_author ) {
			$post_author_data = get_userdata( $post->post_author );
		} else {
			//No author, default to current user
			$post_author_data = $author_data;
		}

		if ( is_object( $post_author_data ) && $user_id == $post_author_data->ID ) {

			if ( 'publish' == $post->post_status ) {
				$caps[0] = 'delete_published_prospress_posts';
			} elseif ( 'trash' == $post->post_status ) {
				if ('publish' == get_post_meta($post->ID, '_wp_trash_meta_status', true) )
					$caps[0] = 'delete_published_prospress_posts';
			} else {
				$caps[0] = 'delete_prospress_posts';
			}
		} else {
			$caps[0] = 'edit_others_prospress_posts';

			if ( 'publish' == $post->post_status || 'private' == $post->post_status )
				$caps[] = 'delete_published_prospress_posts';
		}
	} elseif( $cap == 'read_prospress_post' ) {
		$post = get_post( $args[0] );

		if ( 'private' != $post->post_status ) {
			$caps[0] = 'read';
		} else {
			$author_data = get_userdata( $user_id );
			$post_author_data = get_userdata( $post->post_author );
			if ( is_object( $post_author_data ) && $user_id == $post_author_data->ID )
				$caps[0] = 'read';
			else
				$caps[0] = 'read_private_prospress_posts';
		}
	}

	return $caps;
}
add_filter( 'map_meta_cap', 'pp_map_meta_cap', 10, 4 );
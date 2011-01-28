<?php
/*
Plugin Name: Prospress
Plugin URI: http://prospress.org
Description: Add an auction marketplace to your WordPress site.
Author: Brent Shepherd, Prospress.org
Version: 1.1
Author URI: http://prospress.org/
*/

if ( !defined( 'PP_VERSION' ) )
	define( 'PP_VERSION', '0.2' );

if( !defined( 'PP_PLUGIN_DIR' ) )
	define( 'PP_PLUGIN_DIR', WP_PLUGIN_DIR . '/prospress' );
if( !defined( 'PP_PLUGIN_URL' ) )
	define( 'PP_PLUGIN_URL', WP_PLUGIN_URL . '/prospress' );

load_plugin_textdomain( 'prospress', PP_PLUGIN_DIR . '/languages', dirname( plugin_basename( __FILE__ ) ) . '/languages' );

require_once( PP_PLUGIN_DIR . '/pp-core.php' );

require_once( PP_PLUGIN_DIR . '/pp-posts.php' );

require_once( PP_PLUGIN_DIR . '/pp-bids.php' );

require_once( PP_PLUGIN_DIR . '/pp-feedback.php' );

require_once( PP_PLUGIN_DIR . '/pp-payment.php' );

function pp_activate(){

	if ( !function_exists( 'register_post_status' ) || version_compare( PHP_VERSION, '5.0.0', '<' ) ) { // Don't register on installations pre 3.0 with less than php 5
		deactivate_plugins( basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ) );
		if( !function_exists( 'register_post_status' ) )
			wp_die(__( "Sorry, but you can not run Prospress. It requires WordPress 3.0 or newer. Consider <a href='http://codex.wordpress.org/Updating_WordPress'>upgrading</a> your WordPress installation, it's worth the effort.<br/><a href=" . admin_url( 'plugins.php' ) . ">Return to Plugins Admin page &raquo;</a>"), 'prospress' );
		else
			wp_die(__( "Sorry, but you can not run Prospress. It requires PHP 5.0 or newer. Please <a href='http://www.php.net/manual/en/migration5.php'>migrate</a> your PHP installation to run Prospress.<br/><a href=" . admin_url( 'plugins.php' ) . ">Return to Plugins Admin page &raquo;</a>"), 'prospress' );
	}

	do_action( 'pp_activation' );
}
register_activation_hook( __FILE__, 'pp_activate' );

function pp_deactivate(){
	do_action( 'pp_deactivation' );
}
register_deactivation_hook( __FILE__, 'pp_deactivate' );

function pp_uninstall(){
	//do_action( 'pp_uninstall' ); // some don't want their plugins to delete all its data upon uninstallation
}
register_uninstall_hook( __FILE__, 'pp_uninstall' );


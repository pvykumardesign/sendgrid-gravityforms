<?php
/**
 * Plugin Name: Sendgrid Gravity Forms
 *
 * Description: Syncing gravity form fields with Sendgrid.
 *
 * Plugin URI: http://codesrushti.com/
 *
 * Author URI: http://codesrushti.com/
 *
 * Author: Vinothkumar Parthasarathy
 *
 * Version: 1.0
 */
 
if ( ! defined( 'ABSPATH' ) ) {
    
	exit; // Exit if accessed directly.
    
}

define( 'GF_SENDGRID_FEED_ADDON_VERSION', '1.0' );

add_action( 'gform_loaded', array( 'GF_Sendgrid_Feed_AddOn_Bootstrap', 'load' ), 5 );

require_once( 'country-code-country-name.php' );

class GF_Sendgrid_Feed_AddOn_Bootstrap {

	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
            
			return;
            
		}

		require_once( 'class.sendgridfeed.php' );

		GFAddOn::register( 'GFSendgridFeedAddOn' );
	}

}

function gf_sendgrid_feed_addon() {
    
	return GFSendgridFeedAddOn::get_instance();
    
}
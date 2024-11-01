<?php

/*
Copyright: 2009-2011 WooThemes.
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Plugin activation check
 */
function wc_ups_activation_check(){
	if ( ! function_exists( 'simplexml_load_string' ) ) {
        deactivate_plugins( basename( __FILE__ ) );
        wp_die( "Sorry, but you can't run this plugin, it requires the SimpleXML library installed on your server/hosting to function." );
	}
}

register_activation_hook( __FILE__, 'wc_ups_activation_check' );

/**
 * Plugin page links
 */
function wc_ups_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="http://wcdocs.woothemes.com/user-guide/ups/">' . __( 'Docs', 'wc_ups' ) . '</a>',
	);

	return array_merge( $plugin_links, $links );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_ups_plugin_links' );

/**
 * Check if WooCommerce is active
 */
if ( is_woocommerce_active() ) {

	/**
	 * wc_ups_init function.
	 *
	 * @access public
	 * @return void
	 */
	function wc_ups_init() {
		include_once( 'classes/class-wc-shipping-ups.php' );
	}

	add_action( 'woocommerce_shipping_init', 'wc_ups_init' );

	/**
	 * wc_ups_add_method function.
	 *
	 * @access public
	 * @param mixed $methods
	 * @return void
	 */
	function wc_ups_add_method( $methods ) {
		$methods[] = 'WC_Shipping_UPS';
		return $methods;
	}

	add_filter( 'woocommerce_shipping_methods', 'wc_ups_add_method' );

	/**
	 * wc_ups_scripts function.
	 *
	 * @access public
	 * @return void
	 */
	function wc_ups_scripts() {
		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	add_action( 'admin_enqueue_scripts', 'wc_ups_scripts' );
}

<?php

/**
 * Plugin Name: UltimateWoo Lite
 * Plugin URI: http://www.ultimatewoo.com
 * Description: Lets WooCommerce users add dozens of various modules to extend the power of their WooCommerce-powered eCommerce website. This is the lite version.
 * Version: 0.1.10
 * Author: UltimateWoo
 * Author URI: http://www.ultimatewoo.com
 *
 *
 * License: GPL 2.0+
 * License URI: http://www.opensource.org/licenses/gpl-license.php
 *
 * For a list of credits, please visit http://ultimatewoo.com/credit-attributions/
 *
 */

 /*

	Copyright 2014  UltimateWoo  (email : mail@ultimatewoo.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	Permission is hereby granted, free of charge, to any person obtaining a copy of this
	software and associated documentation files (the "Software"), to deal in the Software
	without restriction, including without limitation the rights to use, copy, modify, merge,
	publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons
	to whom the Software is furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in all copies or
	substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.

*/

require_once( 'woo-includes/woo-functions.php' );

register_activation_hook( __FILE__, 'ultimatewoo_activate' );
function ultimatewoo_activate() {

	if ( ! is_woocommerce_active() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( sprintf( __( 'Whoops! UltimateWoo requires you to install and activate WooCommerce first.', 'ultwoo' ) ) );
	}

}

require dirname( __FILE__ ) . '/modules/ultimatewoo_enable_modules.php';
require dirname( __FILE__ ) . '/modules/ultimatewoo_settings.php';

add_action( 'admin_enqueue_scripts', 'ultimatewoo_enqueues' );
function ultimatewoo_enqueues() {
	wp_enqueue_style( 'ultimatewoo-admin-css', plugin_dir_url( __FILE__ ) . '/includes/css/admin-styles.css' );
}

//* Display admin notices
add_action( 'admin_notices', 'uw_admin_notice' );
function uw_admin_notice() {

	$user_id = get_current_user_id();
	$get_license_url = 'http://www.ultimatewoo.com/';

	if ( current_user_can( 'manage_options' ) && ! get_user_meta( $user_id, 'uw_ignore_license_notice') ) : ?>

		<div class="updated">
			<p><?php printf( __( 'It looks like you are running the Lite version of UltimateWoo.
			If you are interested in upgrading, you\'ll receive instant access to dozens more awesome WooCommerce extensions and priority support.
			<a href="%1$s">Click here</a> to purchase your license key. | <a href="%2$s">Hide Notice</a>', 'woocommerce' ), esc_url( $get_license_url ), '?uw_ignore_license_notice=0' ); ?></p>
		</div>

	<?php endif;

}

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'ultimatewoo_action_links' );
function ultimatewoo_action_links( $links ) {

   $uw_links = array( '<a href="'. admin_url( 'admin.php?page=ultimatewoo-settings' ) .'">Settings</a>' );

   $links = array_merge( $uw_links, $links );

   return $links;

}

add_filter( 'plugin_row_meta', 'ultimatewoo_lite_plugin_row_links', 10, 2 );
function ultimatewoo_lite_plugin_row_links( $links, $file ) {

	$plugin = plugin_basename( __FILE__ );

	if ( $file == $plugin ) {

		$uw_links = array( '<a href="http://www.ultimatewoo.com" target="_blank">Upgrade to Pro!</a>' );

		$links = array_merge( $links, $uw_links );

	}

	return $links;

}

//* Make the admin notices dismissible
add_action( 'admin_init', 'uw_admin_notice_ignore' );
function uw_admin_notice_ignore() {

	$user_id = get_current_user_id();

	/* If user clicks to ignore the notice, add that to their user meta */
	if ( isset( $_GET['uw_ignore_license_notice'] ) && '0' == $_GET['uw_ignore_license_notice'] )
		add_user_meta( $user_id, 'uw_ignore_license_notice', 'true', true );

	if ( isset( $_GET['uw_ignore_email_optin'] ) && '0' == $_GET['uw_ignore_email_optin'] )
		add_user_meta( $user_id, 'uw_ignore_email_optin', 'true', true );

}

//* Dismissible opt-in form
function ultimatewoo_email_optin() {

	$user_id = get_current_user_id();

	if ( current_user_can( 'manage_options' ) && ! get_user_meta( $user_id, 'uw_ignore_email_optin') ) : ?>

		<link href="//cdn-images.mailchimp.com/embedcode/slim-081711.css" rel="stylesheet" type="text/css">

		<style type="text/css">

			#mc_embed_signup {
				background: #fff;
				clear: left;
				font: 14px Helvetica, Arial, sans-serif;
				padding: 20px;
				margin: 30px 0;
				width: 40%;
				position: fixed;
				right: 0;
				top: calc(100% - 300px);
			}

			@media only screen and (max-width: 600px) {
				#mc_embed_signup {
					width: 100%;
					position: static;
				}
			}

		</style>

		<div id="mc_embed_signup">

			<form action="//ultimatewoo.us9.list-manage.com/subscribe/post?u=6285efd766a43c081d3c85446&amp;id=73736030d8" method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank" novalidate>

				<div id="mc_embed_signup_scroll">

					<label for="mce-EMAIL">Get important UltimateWoo updates?</label>
					<input type="email" value="" name="EMAIL" class="email" id="mce-EMAIL" placeholder="Enter Your Email Address" required>
					<!-- real people should not fill this in and expect good things - do not remove this or risk form bot signups-->
					<div style="position: absolute; left: -5000px;"><input type="text" name="b_6285efd766a43c081d3c85446_73736030d8" tabindex="-1" value=""></div>
					<div class="clear">
						<input type="submit" value="Subscribe" name="subscribe" id="mc-embedded-subscribe" class="button">
						<p>We only send information you need as an UltimateWoo user...no spam or other junk.</p>
						<a href="<?php echo admin_url( 'admin.php?page=ultimatewoo-settings&uw_ignore_email_optin=0' ); ?>">Thanks but I've already signed up! (dismiss)</a>
					</div>

				</div>

			</form>

		</div>

	<?php endif;

}

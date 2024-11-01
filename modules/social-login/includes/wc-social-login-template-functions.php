<?php

/**
 * Social Login Global Functions
 *
 * @version 1.1.0
 * @since 1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! function_exists( 'woocommerce_social_login_buttons' ) ) :

/**
 * Pluggable function to render social login buttons
 *
 * @since 1.0
 * @param string $return_url Return url, defaults to the current url
 */
function woocommerce_social_login_buttons( $return_url = null ) {

	if ( is_user_logged_in() ) {
		return;
	}

	// If no return_url, use the current URL
	if ( ! $return_url ) {
		$return_url = home_url( add_query_arg( array() ) );
	}

	// Enqueue styles and scripts
	$GLOBALS['wc_social_login']->frontend->load_styles_scripts();

	// load the template
	wc_get_template(
		'global/social-login.php',
		array(
			'providers'  => $GLOBALS['wc_social_login']->get_available_providers(),
			'return_url' => $return_url,
			'login_text' => get_option( 'wc_social_login_text' ),
		),
		'',
		$GLOBALS['wc_social_login']->get_plugin_path() . '/templates/'
	);
}

endif;


if ( ! function_exists( 'woocommerce_social_login_link_account_buttons' ) ) :

	/**
	 * Pluggable function to render social login "link your account" buttons
	 *
	 * @since 1.1.0
	 * @param string $return_url Return url, defaults my account page
	 */
	function woocommerce_social_login_link_account_buttons( $return_url = null ) {

		if ( ! is_user_logged_in() ) {
			return;
		}

		// If no return_url, use the my account page
		if ( ! $return_url ) {
			$return_url = get_permalink( wc_get_page_id( 'myaccount' ) );
		}

		// Enqueue styles and scripts
		$GLOBALS['wc_social_login']->frontend->load_styles_scripts();

		$available_providers = array();

		// determine available providers for user
		foreach ( $GLOBALS['wc_social_login']->get_available_providers() as $provider ) {

			if ( ! get_user_meta( get_current_user_id(), '_wc_social_login_' . $provider->get_id() . '_profile', true ) ) {
				$available_providers[] = $provider;
			}
		}

		// load the template
		wc_get_template(
			'global/social-login-link-account.php',
			array(
				'available_providers'  => $available_providers,
				'return_url'           => $return_url,
			),
			'',
			$GLOBALS['wc_social_login']->get_plugin_path() . '/templates/'
		);
	}

endif;

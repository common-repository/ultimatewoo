<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_Social_Login_Provider_PayPal extends WC_Social_Login_Provider {


	/**
	 * Constructor for the provider.
	 *
	 * @since 1.1.0
	 * @param string $base_auth_path base authentication path
	 */
	public function __construct( $base_auth_path ) {

		$this->id                = 'paypal';
		$this->title             = __( 'PayPal', WC_Social_Login::TEXT_DOMAIN );
		$this->strategy_class    = 'PayPal';
		$this->color             = '#00457C';
		$this->internal_callback = 'oauth2callback';
		$this->requires_ssl      = false;

		$this->notices = array(
			'account_linked'         => __( 'Your PayPal account is now linked to your account.', WC_Social_Login::TEXT_DOMAIN ),
			'account_unlinked'       => __( 'PayPal account was successfully unlinked from your account.', WC_Social_Login::TEXT_DOMAIN ),
			'account_already_linked' => __( 'This PayPal account is already linked to another user account.', WC_Social_Login::TEXT_DOMAIN ),
			'account_already_exists' => __( 'A user account using the same email address as this PayPal account already exists.', WC_Social_Login::TEXT_DOMAIN ),
		);

		parent::__construct( $base_auth_path );
	}


	/**
	 * Get the description, overridden to display the callback URL
	 * as a convenience since PayPal requires the admin to enter it for the app
	 *
	 * @since 1.1.0
	 * @see WC_Social_Login_Provider::get_description()
	 * @return string
	 */
	public function get_description() {

		return sprintf( __( 'Need help setting up and configuring PayPal? %sRead the docs%s', WC_Social_Login::TEXT_DOMAIN ), '<a href="http://docs.woothemes.com/document/woocommerce-social-login-create-social-apps#paypal">', '</a>' ) . '<br/><br/>' . sprintf( __( 'The App redirect URL is %s', WC_Social_Login::TEXT_DOMAIN ), '<code>' . $this->get_callback_url() . '</code>' );
	}


	/**
	 * Return the providers opAuth config
	 *
	 * @since 1.1.0
	 * @return array
	 */
	public function get_opauth_config() {

		/**
		 * Filter provider's Opauth configuration.
		 *
		 * @since 1.1.0
		 * @param array $config See https://github.com/opauth/opauth/wiki/Opauth-configuration - Strategy
		 */
		return apply_filters( 'wc_social_login_' . $this->get_id() . '_opauth_config', array(
			'redirect_uri'      => $this->get_callback_url(),
			'strategy_class'    => $this->get_strategy_class(),
			'strategy_url_name' => $this->get_id(),
			'client_id'         => $this->get_client_id(),
			'client_secret'     => $this->get_client_secret(),
			'environment'       => $this->get_option( 'environment' ),
		) );
	}


	/**
	 * Override the default form fields to:
	 *
	 * 1) Add the environment setting
	 * 2) tweak the title for the client ID/secret so it matches PayPal's UI
	 *
	 * @since 1.1.0
	 * @see WC_Social_Login_Provider::init_form_fields()
	 */
	public function init_form_fields() {

		parent::init_form_fields();

		$this->form_fields['environment'] = array(
			'title'    => __( 'Environment', WC_Social_Login::TEXT_DOMAIN ),
			'type'     => 'select',
			'desc_tip' => __( 'Select which environment to process logins under.', WC_Social_Login::TEXT_DOMAIN ),
			'options'  => array(
				'live'    => __( 'Live', WC_Social_Login::TEXT_DOMAIN ),
				'sandbox' => __( 'Sandbox', WC_Social_Login::TEXT_DOMAIN ),
			),
			'default'  => 'live',
		);

		$this->form_fields['id']['title']     = __( 'Client ID', WC_Social_Login::TEXT_DOMAIN );
		$this->form_fields['secret']['title'] = __( 'Secret', WC_Social_Login::TEXT_DOMAIN );
	}


	/**
	 * Return the default login button text
	 *
	 * @since 1.1.0
	 * @see WC_Social_Login_Provider::get_default_login_button_text()
	 * @return string
	 */
	public function get_default_login_button_text() {

		return __( 'Log in with PayPal', WC_Social_Login::TEXT_DOMAIN );
	}


	/**
	 * Return the default login button text
	 *
	 * @since 1.1.0
	 * @see WC_Social_Login_Provider::get_default_login_button_text()
	 * @return string
	 */
	public function get_default_link_button_text() {

		return __( 'Link your account to PayPal', WC_Social_Login::TEXT_DOMAIN );
	}


}

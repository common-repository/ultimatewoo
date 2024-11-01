<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_Social_Login_Opauth {


	/** @var string base authentication path */
	private $base_auth_path;

	/** @var array configuration */
	private $config;


	/**
	 * Constructor
	 *
	 * @param string $base_auth_path base authentication path
	 */
	public function __construct( $base_auth_path ) {

		$this->base_auth_path = $base_auth_path;

		add_action( 'init', array( $this, 'init_config' ) );
		add_action( 'woocommerce_api_' . $base_auth_path . '/callback', array( $this, 'callback' ) );

		// redirect after updating email
		add_filter ( 'wp_redirect', array( $this, 'redirect_after_save_account_details' ) );
	}


	/**
	 * Initialize Opauth configuration
	 *
	 * Initializes Opauth configuration with the configured
	 * strategies. Opauth will be instantiated separately
	 * in the authentication and callback methods, because Opauth
	 * will try to create authentication request instantly when
	 * instantiated.
	 *
	 * @since 1.0
	 */
	public function init_config() {
		global $wc_social_login;

		$url = parse_url( home_url() );

		$config = array(
			'host'               => sprintf( '%s://%s', ! empty( $url['scheme'] ) ? $url['scheme'] : 'http', $url['host'] ),
			'path'               => sprintf( '%s/wc-api/%s/', ! empty( $url['path'] ) ? $url['path'] : '', $this->base_auth_path ),
			'callback_transport' => 'post',
			'security_salt'      => get_option( 'wc_social_login_opauth_salt' ),
			'Strategy'           => array(),
			'debug'              => true,
		);

		// Loop over available providers and add their configuration
		foreach ( $wc_social_login->get_available_providers() as $provider ) {

			if ( ! $provider->uses_opauth() ) {
				continue;
			}

			$config['Strategy'][ $provider->get_id() ] = $provider->get_opauth_config();
		}

		$this->config = $config;

		// render an error notice if the user has been redirected due to an error
		if ( ! empty( $_GET['wc-social-login-error'] ) ) {
			wc_add_notice( __( 'Provider Authentication error', WC_Social_Login::TEXT_DOMAIN ), 'error' );
		}

		// render a notice if the user has been redirected to update email address
		if ( ! empty( $_GET['wc-social-login-missing-email'] ) ) {
			wc_add_notice( __( 'Please enter your email address to complete your registration', WC_Social_Login::TEXT_DOMAIN ), 'notice' );
		}
	}


	/**
	 * Authenticate using Opauth
	 *
	 * Creates an instance of Opauth - this will instantly
	 * create an authentication request based on the current
	 * url route. Excpects a url route with the schema {$path}/{$strategy}.
	 *
	 * Providers using Opauth should call this method in their authentication routes
	 *
	 * @since 1.0
	 */
	public function authenticate() {
		new Opauth( $this->config );
		exit;
	}


	/**
	 * Authentication callback
	 *
	 * This method handles the `final` callback from Opauth
	 * to verify the response, handle errors and pass handling
	 * of user profile to the Provider class.
	 *
	 * @since 1.0
	 */
	public function callback() {

		// Create a new Opauth instance without triggering authentication
		$opauth = new Opauth( $this->config, false );

		try {

			// only GET/POST supported
			switch ( $opauth->env['callback_transport'] ) {

				case 'post':
					$response = maybe_unserialize( base64_decode( $_POST['opauth'] ) );
					break;

				case 'get':
					$response = maybe_unserialize( base64_decode( $_GET['opauth'] ) );
					break;

				default:
					throw new Exception( 'Opauth unsupported transport callback' );
			}

			$validation_reason = null;

			// check for error response
			if ( array_key_exists( 'error', $response ) ) {

				throw new Exception( 'Response error' );

			} elseif ( empty( $response['auth'] ) || empty( $response['timestamp'] ) || empty( $response['signature'] ) || empty( $response['auth']['provider'] ) || empty( $response['auth']['uid'] ) ) {

				// ensure required data
				throw new Exception( 'Invalid auth response - missing required components' );

			} elseif ( ! $opauth->validate( sha1( print_r( $response['auth'], true ) ), $response['timestamp'], $response['signature'], $validation_reason ) ) {

				// validate response has not been modified
				throw new Exception( sprintf( 'Invalid auth response - %s', $validation_reason ) );
			}

		} catch ( Exception $e ) {

			// log error messages and response data
			$GLOBALS['wc_social_login']->log( sprintf( 'Error: %s, Response: %s', $e->getMessage(), print_r( $response, true ) ) );

			$this->redirect( 'error' );
		}

		// valid response, get provider
		$provider = $GLOBALS['wc_social_login']->get_provider( strtolower( $response['auth']['provider'] ) );

		$profile = new WC_Social_Login_Provider_Profile( $response['auth'] );

		// Let the provider handle processing user profile and logging in
		$user_id = $provider->process_profile( $profile );

		// Redirect back to where we came from
		$this->redirect( null, $user_id );
	}


	/**
	 * Redirect back to the provided return_url
	 *
	 * @since 1.0
	 * @param string $type redirect type, currently only `error` or null
	 * @param int $user_id the user ID. Default 0.
	 */
	public function redirect( $type = null, $user_id = 0 ) {

		$user = get_user_by( 'id', $user_id );

		if ( isset( $user->user_email ) && '' === $user->user_email ) {
			$return_url = add_query_arg( 'wc-social-login-missing-email', 'true', wc_customer_edit_account_url() );
		} else {
			$return_url = get_transient( 'wcsl_' . md5( $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'] ) );
			$return_url = $return_url ? esc_url( urldecode( $return_url ) ) : get_permalink( wc_get_page_id( 'myaccount' ) );
			delete_transient( 'wcsl_' . md5( $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'] ) );
		}

		if ( 'error' == $type ) {

			// using a query arg because wc_add_notice() only works when the WC session is available
			// which is only on the cart/checkout pages
			$return_url = add_query_arg( 'wc-social-login-error', 'true', $return_url );
		}

		wp_redirect( $return_url );
		exit;
	}

	/**
	 * Redirect back to the provided return_url
	 *
	 * @since 1.2.0
	 * @param string $redirect_location
	 * @param string $redirect_location
	 */
	public function redirect_after_save_account_details( $redirect_location ) {

		$safe_redirect_location = get_permalink( wc_get_page_id( 'myaccount' ) );
		$safe_redirect_location = wp_sanitize_redirect( $safe_redirect_location );
		$safe_redirect_location = wp_validate_redirect( $safe_redirect_location, admin_url() );

		if ( $redirect_location === $safe_redirect_location && $new_location = get_transient( 'wcsl_' . md5( $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'] ) ) ) {
			$redirect_location = $new_location;
			delete_transient( 'wcsl_' . md5( $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'] ) );
		}

		return $redirect_location;
	}


} // end \WC_Social_Login_Opauth class

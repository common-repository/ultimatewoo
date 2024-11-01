<?php

/**
 * Copyright: (c) 2014 SkyVerge, Inc. (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Social-Login
 * @author    SkyVerge
 * @category  Integration
 * @copyright Copyright (c) 2014, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Required library class
if ( ! class_exists( 'SV_WC_Framework_Bootstrap' ) ) {
	$path = dirname( __DIR__ );
	require_once( $path . '/woocommerce/class-sv-wc-framework-bootstrap.php' );
}

SV_WC_Framework_Bootstrap::instance()->register_plugin( '3.0.0', __( 'WooCommerce Social Login', 'woocommerce-social-login' ), __FILE__, 'init_woocommerce_social_login', array( 'minimum_wc_version' => '2.1', 'backwards_compatible' => '3.0.0' ) );

function init_woocommerce_social_login() {

class WC_Social_Login extends SV_WC_Plugin {


	/** plugin version number */
	const VERSION = '1.2.1';

	/** plugin id */
	const PLUGIN_ID = 'social_login';

	/** plugin meta prefix */
	const PLUGIN_PREFIX = 'wc_social_login_';

	/** plugin text domain */
	const TEXT_DOMAIN = 'woocommerce-social-login';

	/** @var \WC_Social_Login_Admin instance */
	public $admin;

	/** @var \WC_Social_Login_Frontend instance */
	public $frontend;

	/** @var array login providers */
	public $providers;


	/**
	 * Initializes the plugin
	 *
	 * @since 1.0
	 * @return \WC_social_login
	 */
	public function __construct() {

		parent::__construct(
			self::PLUGIN_ID,
			self::VERSION,
			self::TEXT_DOMAIN
		);

		// autoload classes
		spl_autoload_register( array( $this, 'autoload' ) );

		// Initialize
		$this->init();
	}


	/**
	 * Autoload Opauth, Strategies, and Provider classes
	 *
	 * @since 1.0.2
	 * @param string $class class name to load
	 */
	public function autoload( $class ) {

		if ( 0 === stripos( $class, 'opauth' ) ) {

			// Opauth classes, note that Opauth handles loading strategies internally
			$path = $this->get_plugin_path() . '/lib/opauth/lib/Opauth/';

			$file = $class . '.php';

			if ( is_readable( $path . $file ) ) {
				require_once( $path . $file );
			}

		} elseif ( 0 === stripos( $class, 'wc_social_login_provider_' ) ) {

			$class = strtolower( $class );

			// Provider classes
			$path = $this->get_plugin_path() . '/includes/providers/';
			$file = 'class-' . str_replace( '_', '-', $class ) . '.php';

			if ( is_readable( $path . $file ) ) {
				require_once( $path . $file );
			}
		}
	}


	/**
	 * Initialize Social Login
	 *
	 * @since 1.0
	 */
	public function init() {

		// Base social login provider & profile
		require_once( 'includes/abstract-wc-social-login-provider.php' );
		require_once( 'includes/class-wc-social-login-provider-profile.php' );

		// Load providers
		$this->load_providers();

		// Frontend includes
		if ( ! is_admin() ) {
			$this->frontend_includes();
		}

		// Admin includes
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			$this->admin_includes();
		}

		// Register widgets
		add_action( 'widgets_init', array( $this, 'register_widgets' ) );

		// Set profile image avatar
		add_filter( 'get_avatar', array( $this, 'set_profile_image_avatar' ), 10, 2 );
	}


	/**
	 * Include required frontend files
	 *
	 * @since 1.0
	 */
	private function frontend_includes() {

		require_once( 'includes/class-wc-social-login-opauth.php' );
		$this->opauth = new WC_Social_Login_Opauth( $this->get_auth_path() );

		require_once( 'includes/wc-social-login-template-functions.php' );

		require_once( 'includes/frontend/class-wc-social-login-frontend.php' );
		$this->frontend = new WC_Social_Login_Frontend();

	}


	/**
	 * Include required admin files
	 *
	 * @since 1.0
	 */
	private function admin_includes() {

		require_once( 'includes/admin/class-wc-social-login-admin.php' );
		$this->admin = new WC_Social_Login_Admin();
	}


	/**
	 * Load plugin text domain.
	 *
	 * @since 1.0
	 * @see SV_WC_Plugin::load_translation()
	 */
	public function load_translation() {

		load_plugin_textdomain( 'woocommerce-social-login', false, dirname( plugin_basename( $this->get_file() ) ) . '/i18n/languages' );
	}


	/** Provider methods ******************************************************/


	/**
	 * load_providers function.
	 *
	 * Loads all social login providers which are hooked in.
	 *
	 * Providers are sorted into their user-defined order after being loaded.
	 *
	 * @return array
	 */
	public function load_providers() {

		$this->unregister_providers();

		// Providers can register themselves through this hook
		do_action( 'wc_social_login_load_providers' );

		// Register providers through a filter

		/**
		 * Filter the list of providers to load.
		 *
		 * @since 1.0
		 * @param array $providers_to_load list of provider classes to load
		 */
		$providers_to_load = apply_filters( 'wc_social_login_providers', array(
			'WC_Social_Login_Provider_Facebook',
			'WC_Social_Login_Provider_Twitter',
			'WC_Social_Login_Provider_Google',
			'WC_Social_Login_Provider_Amazon',
			'WC_Social_Login_Provider_LinkedIn',
			'WC_Social_Login_Provider_PayPal',
			'WC_Social_Login_Provider_Instagram',
		) );

		foreach ( $providers_to_load as $provider ) {
			$this->register_provider( $provider );
		}

		$this->sort_providers();

		return $this->providers;
	}


	/**
	 * Register a provider.
	 *
	 * @param  object|string $provider Either the name of the provider's class, or an instance of the provider's class
	 */
	public function register_provider( $provider ) {

		if ( ! is_object( $provider ) ) {
			$provider = new $provider( $this->get_auth_path() );
		}

		$id = empty( $provider->instance_id ) ? $provider->get_id() : $provider->instance_id;

		$this->providers[ $id ] = $provider;
	}


	/**
	 * unregister_providers function.
	 *
	 * @access public
	 * @return void
	 */
	public function unregister_providers() {
		unset( $this->providers );
	}


	/**
	 * sort_providers function.
	 *
	 * Sorts providers into the user defined order.
	 *
	 * @return array
	 */
	public function sort_providers() {

		$sorted_providers = array();

		// Get order option
		$ordering   = (array) get_option( 'wc_social_login_provider_order' );
		$order_end  = 999;

		// Load shipping providers in order
		foreach ( $this->providers as $provider ) {

			if ( isset( $ordering[ $provider->get_id() ] ) && is_numeric( $ordering[ $provider->get_id() ] ) ) {
				// Add in position
				$sorted_providers[ $ordering[ $provider->get_id() ] ][] = $provider;
			} else {
				// Add to end of the array
				$sorted_providers[ $order_end ][] = $provider;
			}
		}

		ksort( $sorted_providers );

		$this->providers = array();

		foreach ( $sorted_providers as $providers ) {
			foreach ( $providers as $provider ) {
				$id = empty( $provider->instance_id ) ? $provider->get_id() : $provider->instance_id;
				$this->providers[ $id ] = $provider;
			}
		}

		return $this->providers;
	}


	/**
	 * get_auth_path function.
	 *
	 * Returns the authentication base path, defaults to `auth`
	 *
	 * e.g.: skyverge.com/wc-api/auth/facebook
	 *
	 * @return string
	 */
	public function get_auth_path() {

		/**
		 * Filter the authentication base path.
		 *
		 * @since 1.0
		 * @param string $auth_path the authentication base path
		 */
		return apply_filters( 'wc_social_login_auth_path', 'auth' );
	}


	/**
	 * get_providers function.
	 *
	 * Returns all registered providers for usage.
	 *
	 * @return array
	 */
	public function get_providers() {
		return $this->providers;
	}


	/**
	 * get_provider function.
	 *
	 * Returns the requested provider, if found.
	 *
	 * @param string $provider_id
	 * @return array|null
	 */
	public function get_provider( $provider_id ) {
		return isset( $this->providers[ $provider_id ] ) ? $this->providers[ $provider_id ] : null;
	}


	/**
	 * Get available providers.
	 *
	 * @return array
	 */
	public function get_available_providers() {

		$_available_providers = array();

		foreach ( $this->get_providers() as $provider ) {

			if ( $provider->is_available() ) {
				$_available_providers[ $provider->get_id() ] = $provider;
			}

		}

		/**
		 * Filter the available providers
		 *
		 * @since 1.0
		 * @param array $_available_providers the available providers
		 */
		return apply_filters( 'wc_social_login_available_providers', $_available_providers );
	}


	/** Admin providers ******************************************************/


	/**
	 * Render a notice for the user to read the docs before configuring
	 *
	 * @since 1.1.0
	 * @see SV_WC_Plugin::add_delayed_admin_notices()
	 */
	public function add_delayed_admin_notices() {

		// show any dependency notices
		parent::add_delayed_admin_notices();

		// add notice for selecting export format
		if ( $this->is_plugin_settings() ) {

			$this->get_admin_notice_handler()->add_admin_notice(
				sprintf( __( 'Thanks for installing Social Login! Before you get started, please take a moment to %sread through the documentation%s.', $this->text_domain ),
					'<a href="' . $this->get_documentation_url() . '">', '</a>' ),
					'read-the-docs',
					array( 'always_show_on_settings' => false )
			);
		}

		$this->add_ssl_admin_notices();
	}


	/**
	 * Checks if SSL is required for any providers and not available and adds a
	 * dismissible admin notice if so. Notice will not be rendered to the admin
	 * user once dismissed unless on the plugin settings page, if any
	 *
	 * @since 1.1.0
	 * @see SV_WC_Payment_Gateway_Plugin::add_admin_notices()
	 */
	protected function add_ssl_admin_notices() {

		// Get available providers
		foreach ( $this->get_providers() as $provider ) {

			// Check if the provider requires SSL
			if ( $provider->is_enabled() && $provider->requires_ssl() ) {

				if ( 'no' === get_option( 'woocommerce_force_ssl_checkout' ) ) {

					$message = sprintf( _x( 'WooCommerce Social Login: %s requires SSL for authentication, please force WooCommerce over SSL.', 'Requires SSL', WC_Social_Login::TEXT_DOMAIN ), '<strong>' . $provider->get_title() . '</strong>' );

					$this->get_admin_notice_handler()->add_admin_notice( $message, $provider->get_id() . '-ssl-required' );
				}
			}
		}
	}


	/**
	 * Returns conditional dependencies based on the provider selected
	 *
	 * @since 1.0
	 * @see SV_WC_Plugin::get_dependencies()
	 * @return array of dependencies
	 */
	protected function get_dependencies() {

		$dependencies = array();

		foreach ( $this->get_providers() as $provider ) {

			if ( 'twitter' == $provider->get_id() && $provider->is_enabled() ) {
				$dependencies[] = 'curl';
			}
		}

		return array_merge( parent::get_dependencies(), $dependencies );
	}


	/**
	 * Register social login widgets
	 *
	 * @since 1.0
	 */
	public function register_widgets() {

		// load widget
		require_once( 'includes/widgets/class-wc-social-login-widget.php' );

		// register widget
		register_widget( 'WC_Social_Login_Widget' );
	}


	/** Helper providers ******************************************************/


	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.0
	 * @see SV_WC_Plugin::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() {

		return __( 'WooCommerce Social Login', $this->text_domain );
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 1.0
	 * @see SV_WC_Plugin::get_file()
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() {

		return __FILE__;
	}


	/**
	 * Gets the URL to the settings page
	 *
	 * @since 1.0
	 * @see SV_WC_Plugin::is_plugin_settings()
	 * @param string $_ unused
	 * @return string URL to the settings page
	 */
	public function get_settings_url( $_ = '' ) {

		return admin_url( 'admin.php?page=wc-settings&tab=social_login' );
	}


	/**
	 * Returns true if on the Social Login settings page
	 *
	 * @since 1.0
	 * @see SV_WC_Plugin::is_plugin_settings()
	 * @return boolean true if on the settings page
	 */
	public function is_plugin_settings() {

		return isset( $_GET['page'] ) && 'wc-settings' == $_GET['page'] && isset( $_GET['tab'] ) && 'social_login' == $_GET['tab'];
	}


	/** Lifecycle providers ******************************************************/


	/**
	 * Install default settings
	 *
	 * @since 1.0
	 * @see SV_WC_Plugin::install()
	 */
	protected function install() {

		add_option( 'wc_social_login_opauth_salt', wp_generate_password( 62, true, true ) );

		// settings page defaults.  unfortunately we can't dynamically pull these because the requisite core WC classes aren't loaded
		// a better solution may be to set any defaults within the save method of the social provider settings classes
		add_option( 'wc_social_login_display', 'checkout,my_account' );
		add_option( 'wc_social_login_text', __( 'For faster checkout, login or register using your social account.', WC_Social_Login::TEXT_DOMAIN ) );
	}


	/**
	 * Upgrade to the installed version
	 *
	 * @since 1.1.0
	 * @param string $installed_version
	 * @see SV_WC_Plugin::upgrade()
	 */
	protected function upgrade( $installed_version ) {

		// upgrade to 1.1.0
		if ( version_compare( $installed_version, '1.1.0', '<' ) ) {

			// display option is now a multiselect
			update_option( 'wc_social_login_display', explode( ',', get_option( 'wc_social_login_display', '' ) ) );
		}
	}


	/**
	 * Set profile image avatar
	 *
	 * Filters the get_avatar() function and sets the img src to stored profile image
	 *
	 * @since 1.1.0
	 * @param string $avatar Image tag for the user's avatar.
	 * @param mixed $id_or_email A user ID, email address, or comment object.
	 */
	public function set_profile_image_avatar( $avatar, $id_or_email ) {

		$user_id = 0;

		if ( is_numeric( $id_or_email ) ) {

			$user_id = (int) $id_or_email;

		} elseif ( is_object( $id_or_email ) ) {

			if ( ! empty( $id_or_email->user_id ) ) {
				$user_id = (int) $id_or_email->user_id;
			}

		} else {
			$user = get_user_by( 'email', $id_or_email );

			if ( $user ) {
				$user_id = $user->ID;
			}
		}

		if ( $user_id && $image = get_user_meta( $user_id, '_wc_social_login_profile_image', true ) ) {

			if ( ! strpos( $image, 'instagram.com' ) ) {
				$image = set_url_scheme( $image, 'https' );
			}

			/**
			 * Filter the profile image URL.
			 *
			 * @since 1.2.0
			 * @param string $image the profile image URL
			 */
			$image = apply_filters( 'wc_social_login_profile_image', $image );

			if ( ! ( ( is_ssl() || 'yes' == get_option( 'woocommerce_force_ssl_checkout' ) ) && strpos( $image, 'instagram.com' ) ) ) {
				$avatar = preg_replace( "/src='(.*?)'/i", "src='" . $image . "'", $avatar );
			}
		}

		return $avatar;
	}


} // end \WC_Social_Login class

/**
 * The WC_Social_Login global object
 * @name $wc_social_login
 * @global WC_Social_Login $GLOBALS['wc_social_login']
 */
$GLOBALS['wc_social_login'] = new WC_Social_Login();

} // init_woocommerce_social_login()

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

SV_WC_Framework_Bootstrap::instance()->register_plugin( '3.0.0', __( 'WooCommerce Tab Manager', 'woocommerce-tab-manager' ), __FILE__, 'init_woocommerce_tab_manager', array( 'minimum_wc_version' => '2.1', 'backwards_compatible' => '3.0.0' ) );

function init_woocommerce_tab_manager() {

class WC_Tab_Manager extends SV_WC_Plugin {


	/** Plugin version */
	const VERSION = '1.1.3';

	/** The plugins id, used for various slugs and such */
	const PLUGIN_ID = 'tab_manager';

	/** plugin text domain */
	const TEXT_DOMAIN = 'woocommerce-tab-manager';


	/**
	 * Local cache array of product tabs, keyed off product id
	 * @var array
	 */
	private $product_tabs = array();

	/**
	 * Array of third party tabs
	 * @var array
	 */
	private $third_party_tabs;

	/**
	 * @var boolean Temporary member used to defer plugin installation, until the plugin framework supports this
	 */
	private $wp_loaded_action = false;


	/**
	 * Setup main plugin class
	 *
	 * @since 1.0
	 * @see SV_WC_Plugin::__construct()
	 */
	public function __construct() {

		parent::__construct(
			self::PLUGIN_ID,
			self::VERSION,
			self::TEXT_DOMAIN
		);

		$this->includes();

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'include_template_functions' ), 25 );

		add_filter( 'woocommerce_product_tabs', array( $this, 'setup_tabs' ), 98 );

		// allow the use of shortcodes within the tab content
		add_filter( 'woocommerce_tab_manager_tab_panel_content', 'do_shortcode' );
	}


	/**
	 * Load plugin text domain.
	 *
	 * @since 1.1
	 * @see SV_WC_Plugin::load_translation()
	 */
	public function load_translation() {
		load_plugin_textdomain( 'woocommerce-tab-manager', false, dirname( plugin_basename( $this->get_file() ) ) . '/i18n/languages' );
	}


	/**
	 * Init WooCommerce Tab Manager
	 */
	public function init() {

		// Init user roles
		$this->init_user_roles();

		// Init WooCommerce Product Tab taxonomy
		$this->init_taxonomy();
	}


	/**
	 * Function used to init WooCommerce Tab Manager Template Functions, making them pluggable by plugins and themes
	 */
	public function include_template_functions() {
		require_once( 'woocommerce-tab-manager-template.php' );
	}


	/**
	 * Files required by both the admin and frontend
	 */
	private function includes() {
		if ( is_admin() ) {
			$this->admin_includes();
		}
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$this->ajax_includes();
		}
	}


	/**
	 * Include required admin files
	 */
	private function admin_includes() {
		require_once( 'admin/woocommerce-tab-manager-admin-init.php' );  // Admin section
	}


	/**
	 * Include required ajax files.
	 */
	private function ajax_includes() {
		require_once( 'woocommerce-tab-manager-ajax.php' ); // Ajax functions for admin and the front-end
	}


	/**
	 * Init WooCommerce Tab Manager user role
	 */
	private function init_user_roles() {

		global $wp_roles;

		if ( class_exists( 'WP_Roles' ) && ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}

		// it's fine if this gets executed more than once
		if ( is_object( $wp_roles ) ) {
			$wp_roles->add_cap( 'shop_manager',  'manage_woocommerce_tab_manager' );
			$wp_roles->add_cap( 'administrator', 'manage_woocommerce_tab_manager' );
		}
	}


	/**
	 * Init custom post type
	 */
	private function init_taxonomy() {

		// bail if the post type was already registered
		if ( post_type_exists( 'wc_product_tab' ) ) {
			return;
		}

		if ( current_user_can( 'manage_woocommerce' ) ) {
			$show_in_menu = 'woocommerce';
		} else {
			$show_in_menu = true;
		}

		register_post_type( "wc_product_tab",
			array(
				'labels' => array(
					'name'               => __( 'Tabs', self::TEXT_DOMAIN ),
					'singular_name'      => __( 'Tab', self::TEXT_DOMAIN ),
					'menu_name'          => _x( 'Tab Manager', 'Admin menu name', self::TEXT_DOMAIN ),
					'add_new'            => __( 'Add Tab', self::TEXT_DOMAIN ),
					'add_new_item'       => __( 'Add New Tab', self::TEXT_DOMAIN ),
					'edit'               => __( 'Edit', self::TEXT_DOMAIN ),
					'edit_item'          => __( 'Edit Tab', self::TEXT_DOMAIN ),
					'new_item'           => __( 'New Tab', self::TEXT_DOMAIN ),
					'view'               => __( 'View Tabs', self::TEXT_DOMAIN ),
					'view_item'          => __( 'View Tab', self::TEXT_DOMAIN ),
					'search_items'       => __( 'Search Tabs', self::TEXT_DOMAIN ),
					'not_found'          => __( 'No Tabs found', self::TEXT_DOMAIN ),
					'not_found_in_trash' => __( 'No Tabs found in trash', self::TEXT_DOMAIN ),
				),
				'description'     => __( 'This is where you can add new tabs that you can add to products.', self::TEXT_DOMAIN ),
				'public'          => true,
				'show_ui'         => true,
				'capability_type' => 'post',
				'capabilities' => array(
					'publish_posts'       => 'manage_woocommerce_tab_manager',
					'edit_posts'          => 'manage_woocommerce_tab_manager',
					'edit_others_posts'   => 'manage_woocommerce_tab_manager',
					'delete_posts'        => 'manage_woocommerce_tab_manager',
					'delete_others_posts' => 'manage_woocommerce_tab_manager',
					'read_private_posts'  => 'manage_woocommerce_tab_manager',
					'edit_post'           => 'manage_woocommerce_tab_manager',
					'delete_post'         => 'manage_woocommerce_tab_manager',
					'read_post'           => 'manage_woocommerce_tab_manager',
				),
				'publicly_queryable'  => true,
				'exclude_from_search' => true,
				'show_in_menu'        => $show_in_menu,
				'hierarchical'        => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title', 'editor' ),
				'show_in_nav_menus'   => false,
			)
		);
	}


	/**
	 * Return the plugin action links.  This will only be called if the plugin
	 * is active.
	 *
	 * @since 1.0.9
	 * @param array $actions associative array of action names to anchor tags
	 * @return array associative array of plugin action links
	 */
	public function add_plugin_action_links( $actions ) {

		$custom_actions = array(
			'configure' => sprintf( '<a href="%s">%s</a>', admin_url( 'edit.php?post_type=wc_product_tab' ), __( 'Configure', self::TEXT_DOMAIN ) ),
			'docs'      => sprintf( '<a href="%s">%s</a>', 'http://docs.woothemes.com/document/tab-manager/', __( 'Docs', self::TEXT_DOMAIN ) ),
			'support'   => sprintf( '<a href="%s">%s</a>', 'http://support.woothemes.com/', __( 'Support', self::TEXT_DOMAIN ) ),
			'review'    => sprintf( '<a href="%s">%s</a>', $this->get_review_url(), __( 'Write a Review', self::TEXT_DOMAIN ) ),
		);

		// add the links to the front of the actions list
		return array_merge( $custom_actions, $actions );
	}


	/** Frontend methods ******************************************************/


	/**
	 * Organizes the product tabs as configured within the Tab Manager
	 *
	 * $tabs structure:
	 * Array(
	 *   id => Array(
	 *     'title'    => (string) Tab title,
	 *     'priority' => (string) Tab priority,
	 *     'callback' => (mixed) callback function,
	 *   )
	 * )
	 *
	 * @since 1.0.5
	 * @param array $tabs array representing the product tabs
	 * @return array representing the product tabs
	 */
	public function setup_tabs( $tabs ) {
		global $product;

		// first off, make sure that we're dealing with an array rather than null
		if ( is_null( $tabs ) ) {
			$tabs = array();
		}

		$new_tabs = $tabs;

		$product_tabs = isset( $product->id ) ? $this->get_product_tabs( $product->id ) : null;

		// if product tabs have been configured for this product or globally (otherwise, allow default behavior)
		if ( is_array( $product_tabs ) ) {

			// start fresh
			$new_tabs = array();

			// unhook and load any third party tabs that have been added by this point
			$third_party_tabs = $this->get_third_party_tabs( $tabs );

			foreach ( $product_tabs as $key => $tab ) {

				$priority = ( $tab['position'] + 1 ) * 10;
				$tab_id   = $tab['id'];

				if ( 'core' == $tab['type'] ) {

					// the core tabs can be suppressed for a variety of reasons: description tab due to no content, attributes tab due to no attributes, etc
					if ( ! isset( $tabs[ $tab_id ] ) ) continue;

					// set the review (comment) count for the reviews tab, if they used the '%d' substitution
					if ( 'reviews' == $tab['id'] && false !== strpos( $tab['title'], '%d' ) ) $tab['title'] = str_replace( '%d', get_comments_number( $product->id ), $tab['title'] );

					// add the core tab to the new tab set
					$new_tabs[ $tab_id ] = array(
						'title'    => $tab['title'],                 // modified title
						'priority' => $priority,                     // modified priority
						'callback' => $tabs[ $tab_id ]['callback'],  // get the core tab callback
					);

					// handle core tab headings (displays just before the tab content)
					if ( 'additional_information' == $tab_id ) {
						add_filter( 'woocommerce_product_additional_information_heading',   array( $this, 'core_tab_heading' ) );
					} elseif ( 'description' == $tab_id ) {
						add_filter( 'woocommerce_product_description_heading',   array( $this, 'core_tab_heading' ) );
					}

				} elseif ( 'third_party' == $tab['type'] ) {

					// third-party provided tab: ensure it's still available
					if ( ! isset( $third_party_tabs[ $key ] ) || ( isset( $third_party_tabs[ $key ]['ignore'] ) && true == $third_party_tabs[ $key ]['ignore'] ) ) continue;

					// add the 3rd party tab in with the new priority
					$new_tabs[ $tab_id ] = $third_party_tabs[ $key ];
					$new_tabs[ $tab_id ]['priority'] = $priority;

				} else {
					// product/global tabs

					// skip any global/product tabs that have been deleted
					$tab_post = get_post( $tab_id );

					if ( ! $tab_post || 'publish' != $tab_post->post_status || ! $tab_post->post_title ) continue;

					$new_tabs[ $tab['name'] ] = array(
						'title'    => $tab_post->post_title,
						'priority' => $priority,
						'callback' => 'woocommerce_tab_manager_tab_content',
						'id'       => $tab['id'],
					);

				}
			}

			// finally add in any non-managed 3rd party tabs with their own priority
			foreach ( $third_party_tabs as $key => $tab ) {
				if ( isset( $tab['ignore'] ) && true == $tab['ignore'] ) {
					$new_tabs[ $key ] = $tab;
				}
			}

		}

		return apply_filters( 'wc_tab_manager_product_tabs', $new_tabs );
	}


	/**
	 * Filter to modify the Description and Additional Information core tab headings.
	 * The heading is not what shows up in the "tab" itself, this is the
	 * heading for the tab content area.
	 *
	 * @param string $heading the tab heading
	 * @return string the tab heading
	 */
	public function core_tab_heading( $heading ) {

		global $product;
		$tabs = $this->get_product_tabs( $product->id );

		$current_filter = current_filter();

		if ( 'woocommerce_product_additional_information_heading' == $current_filter ) {
			return $tabs['core_tab_additional_information']['heading'];
		} elseif ( 'woocommerce_product_description_heading' == $current_filter ) {
			return $tabs['core_tab_description']['heading'];
		}

		return $heading;
	}


	/** Helper methods ******************************************************/


	/**
	 * Get any third party tabs which have been added via the
	 * woocommerce_product_tabs action.  Any third party tabs so found are collected
	 * so they can be re-added by the manager in the appropriate order.  In the
	 * admin a human readable title is automatically generated (allowing for
	 * automatic integration of 3rd party plugin tabs) and three filters are
	 * fired to allow for improved integration with customized titles/descriptions:
	 *
	 * - woocommerce_tab_manager_integration_tab_allowed: allows plugin to mark tab as not available for management, its priority will not be modified and it will not appear within the Tab Manager Admin UI
	 * - woocommerce_tab_manager_integration_tab_title: allows plugin to provide a more descriptive tab title to display within the Tab Manager Admin UI
	 * - woocommerce_tab_manager_integration_tab_description: allows plugin to provide a description to display within the Tab Manager Admin UI
	 *
	 * $tabs structure:
	 * Array(
	 *   key => Array(
	 *     'title'       => (string) Tab title,
	 *     'priority'    => (string) Tab priority,
	 *     'callback'    => (mixed) callback function,
	 *     'description' => (string) An optional tab description added by this method to the return array,
	 *     'ignore'      => (boolean) Optional marker indicating this tab is not managed by the Tab Manager plugin and added by this method to the return array,
	 *     'id'          => (string) the original tab key,
	 *   )
	 * )
	 * Where key is: third_party_tab_{id}
	 *
	 * @since 1.0.5
	 * @param array $tabs optional array representing the product tabs
	 * @return array representing the product tabs
	 */
	public function get_third_party_tabs( $tabs = null ) {

		global $wp_filter;

		if ( is_null( $this->third_party_tabs ) ) {

			// gather the tabs if not provided
			if ( is_null( $tabs ) ) {

				// In WC 2.1+ the woocommerce_default_product_tabs filter (which
				//  requires a global $post/$product) is hooked into from the admin
				//  so unhook to avoid a fatal error (has no effect in pre WC 2.1)
				if ( is_admin() ) {
					remove_filter( 'woocommerce_product_tabs', 'woocommerce_default_product_tabs' );
					remove_filter( 'woocommerce_product_tabs', 'woocommerce_sort_product_tabs', 99 );
				}

				$tabs = apply_filters( 'woocommerce_product_tabs', array() );
			}

			$this->third_party_tabs = array();

			// remove the core tabs (if any) leaving only 3rd party tabs (if any)
			unset( $tabs['additional_information'], $tabs['reviews'], $tabs['description'] );

			foreach ( $tabs as $key => $tab ) {

				// is this tab available for management by the Tab Manager plugin?
				if ( apply_filters( 'woocommerce_tab_manager_integration_tab_allowed', true, $tab ) ) {

					if ( is_admin() ) {
						if ( ! isset( $tab['title'] ) || ! $tab['title'] ) {
							// on the off chance that the 3rd party tab doesn't have a title, provide it a default one based on the callback so it can be identified within the admin

							// get a title for the tab.  Default to humanizing the function name, or class name
							if ( is_array( $tab['callback'] ) ) $tab_title = ( is_object( $tab['callback'][0] ) ? get_class( $tab['callback'][0] ) : $tab['callback'][0] );
							else $tab_title = (string) $tab['callback'];
							$tab_title = ucwords( str_replace( '_', ' ', $tab_title ) );
							$tab_title = str_ireplace( array( 'woocommerce', 'wordpress' ), array( 'WooCommerce', 'WordPress' ), $tab_title );  // fix some common words
							$tab['title'] = $tab_title;
						}

						// improved 3rd party integration by allowing plugins to provide a more descriptive title/description for their tabs
						$tab['title']       = apply_filters( 'woocommerce_tab_manager_integration_tab_title',       $tab['title'], $tab );
						$tab['description'] = apply_filters( 'woocommerce_tab_manager_integration_tab_description', '',            $tab );
					}

					$tab['id'] = $key;

				} else {
					// this tab is not managed by the Tab Manager, so mark it as such
					$tab['ignore'] = true;
				}

				// save the tab
				$this->third_party_tabs[ 'third_party_tab_' . $key ] = $tab;

			}

		}

		return $this->third_party_tabs;
	}


	/**
	 * Get the default core tabs datastructure
	 *
	 * @return array the core tabs
	 */
	public function get_core_tabs() {

		// the core woocommerce tabs
		$core_tabs = array(
			'core_tab_description'            => array( 'id' => 'description',            'position' => 0, 'type' => 'core', 'title' => __( 'Description', 'woocommerce' ),            'description' => __( 'Displays the product content set in the main content editor.', 'woocommerce' ),                         'heading' => __( 'Product Description', 'woocommerce' ) ),
			'core_tab_additional_information' => array( 'id' => 'additional_information', 'position' => 1, 'type' => 'core', 'title' => __( 'Additional Information', 'woocommerce' ), 'description' => __( 'Displays the product attributes and properties configured in the Product Data panel.', 'woocommerce' ), 'heading' => __( 'Additional Information', 'woocommerce' ) ),
			'core_tab_reviews'                => array( 'id' => 'reviews',                'position' => 2, 'type' => 'core', 'title' => __( 'Reviews (%d)', 'woocommerce' ),           'description' => __( 'Displays the product review form and any reviews.  Use %d in the Title to substitute the number of reviews for the product.', 'woocommerce' ) )
		);

		return $core_tabs;
	}


	/**
	 * Gets the product tabs (if any) for the identified product.  If not
	 * configured at the product level, the default layout (if any) will be
	 * returned.
	 *
	 * returned tabs structure:
	 * Array(
	 *   key => Array(
	 *     'position' => (int) 0-indexed ordered position from the Tab Manager Admin UI,
	 *     'type'     => (string) one of 'core', 'global', 'third_party' or 'product',
	 *     'id'       => (string) Tab identifier, ie 'description', 'reviews', 'additional_information' for the core tabs, post id for product/global, and woocommerce_product_tabs key for third party tabs,
	 *     'title'    => (string) The tab title to display on the frontend (not used for 3rd party tabs, though it could be),
	 *     'heading'  => (string) Tab heading (core description/additional_information tabs only),
	 *     'name'     => (string) Product/Global tabs only, this is the sanitized title, and is used to key the tab in the final woocommerce tab data structure,
	 *   )
	 * )
	 * Where key is: {type}_tab_{id}
	 *
	 * @param int $product_id product identifier
	 *
	 * @return array product tabs data
	 */
	public function get_product_tabs( $product_id ) {

		if ( ! isset( $this->product_tabs[ $product_id ] ) ) {

			$override_tab_layout = get_post_meta( $product_id, '_override_tab_layout', true );

			if ( 'yes' == $override_tab_layout ) {
				// product defines its own tab layout?
				$this->product_tabs[ $product_id ] = get_post_meta( $product_id, '_product_tabs', true );
			} else {
				// otherwise, get the default layout if any
				$this->product_tabs[ $product_id ] = get_option( 'wc_tab_manager_default_layout', false );
			}
		}

		return $this->product_tabs[ $product_id ];
	}


	/**
	 * Gets the product tab or null if the tab cannot be found
	 *
	 * @param int $product_id product identifier
	 * @param int $tab_id tab identifier
	 * @param boolean $get_the_content whether to get the tab content and title
	 *
	 * @return array tab array, or null
	 */
	public function get_product_tab( $product_id, $tab_id, $get_the_content = false ) {

		$tab = null;

		// load the tabs
		$this->get_product_tabs( $product_id );

		if ( is_array( $this->product_tabs[ $product_id ] ) ) {

			foreach ( $this->product_tabs[ $product_id ] as $id => $tab ) {

				if ( $tab['id'] == $tab_id ) {

					// get the tab content, if needed
					if ( $get_the_content && ! isset( $tab['content'] ) ) {

						$tab_post = get_post( $tab_id );
						$content = apply_filters( 'the_content', $tab_post->post_content );
						$content = str_replace( ']]>', ']]&gt;', $content );
						$this->product_tabs[ $product_id ][ $id ]['content'] = $content;

						$this->product_tabs[ $product_id ][ $id ]['title'] = $tab_post->post_title;
					}

					$tab = $this->product_tabs[ $product_id ][ $id ];
					break;
				}
			}
		}

		return apply_filters( 'wc_tab_manager_get_product_tab', $tab, $product_id, $tab_id, $get_the_content );
	}


	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.1
	 * @see SV_WC_Plugin::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() {
		return __( 'WooCommerce Tab Manager', self::TEXT_DOMAIN );
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 1.1
	 * @see
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() {
		return __FILE__;
	}


	/**
	 * Gets the plugin documentation url, which defaults to:
	 * http://docs.woothemes.com/document/woocommerce-{dasherized plugin id}/
	 *
	 * @since 1.1
	 * @see SV_WC_Plugin::get_documentation_url()
	 * @return string documentation URL
	 */
	public function get_documentation_url() {
		return 'http://docs.woothemes.com/document/tab-manager/';
	}


	/**
	 * Gets the plugin review URL, which defaults to:
	 * {product page url}#tab-reviews
	 *
	 * @since 1.0.1
	 * @see SV_WC_Plugin::get_review_url()
	 * @return string review url
	 */
	public function get_review_url() {
		return '#';
	}


	/**
	 * Gets the plugin configuration URL
	 *
	 * @since 1.1
	 * @see SV_WC_Plugin::get_settings_url()()
	 * @see SV_WC_Plugin::get_settings_link()
	 * @param string $plugin_id optional plugin identifier.  Note that this can be a
	 *        sub-identifier for plugins with multiple parallel settings pages
	 *        (ie a gateway that supports both credit cards and echecks)
	 * @return string plugin settings URL
	 */
	public function get_settings_url( $plugin_id = null ) {
		return admin_url( 'edit.php?post_type=wc_product_tab' );
	}


	/**
	 * Returns true if on the admin tab configuration page
	 *
	 * @since 1.0.1
	 * @return boolean true if on the admin plugin settings page
	 */
	public function is_plugin_settings() {
		return isset( $_GET['post_type'] ) && 'wc_product_tab' == $_GET['post_type'];
	}


	/** Lifecycle methods ******************************************************/


	/**
	 * Run every time.  Used since the activation hook is not executed when updating a plugin
	 *
	 * @see SV_WC_Plugin::install()
	 */
	protected function install() {

		global $wpdb;

		// check for a pre 1.1 version
		$legacy_version = get_option( 'wc_tab_manager_db_version' );

		if ( false !== $legacy_version ) {

			// upgrade path from previous version, trash old version option
			delete_option( 'wc_tab_manager_db_version' );

			// upgrade path
			$this->upgrade( $legacy_version );

			// and we're done
			return;
		}

		// any Custom Product Lite Tabs?
		$results = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key='frs_woo_product_tabs'" );

		// prepare the core tabs
		$core_tabs = $this->get_core_tabs();
		foreach ( $core_tabs as $id => $tab ) {
			unset( $core_tabs[ $id ]['description'] );
		}

		// foreach product with a custom lite tab
		foreach ( $results as $result ) {

			$old_tabs = maybe_unserialize( $result->meta_value );

			$new_tabs = array( 'core_tab_description' => $core_tabs['core_tab_description'], 'core_tab_additional_information' => $core_tabs['core_tab_additional_information'] );

			// keep track of tab names to avoid clashes
			$found_names = array( 'description' => 1, 'additional_information' => 1, 'reviews' => 1 );

			foreach ( $old_tabs as $tab ) {

				if ( $tab['title'] && $tab['content'] ) {
					// create the product tab

					$new_tab = array( 'position' => count( $new_tabs ), 'type' => 'product' );

					$new_tab_data = array(
						'post_title'    => $tab['title'],
						'post_content'  => $tab['content'],
						'post_status'   => 'publish',
						'ping_status'   => 'closed',
						'post_author'   => get_current_user_id(),
						'post_type'     => 'wc_product_tab',
						'post_parent'   => $result->post_id,
						'post_password' => uniqid( 'tab_' ), // Protects the post just in case
					);

					// create the post and get the id
					$id = wp_insert_post( $new_tab_data );
					$new_tab['id'] = $id;

					// determine the unique tab name
					$tab_name = sanitize_title( $tab['title'] );
					if ( ! isset( $found_names[ $tab_name ] ) ) {
						$found_names[ $tab_name ] = 1;
					} else {
						$found_names[ $tab_name ]++;
					}
					if ( $found_names[ $tab_name ] > 1 ) {
						$tab_name .= '-' . ( $found_names[ $tab_name ] - 1 );
					}
					$new_tab['name'] = $tab_name;

					// tab is complete
					$new_tabs[ 'product_tab_' . $id ] = $new_tab;
				}
			}

			// add the core reviews tab on at the end
			$new_tabs['core_tab_reviews'] = $core_tabs['core_tab_reviews'];
			$new_tabs['core_tab_reviews']['position'] = count( $new_tabs ) - 1;


			if ( count( $new_tabs ) > 3 ) {
				// if we actually had any product tabs
				add_post_meta( $result->post_id, '_product_tabs',        $new_tabs, true );
				add_post_meta( $result->post_id, '_override_tab_layout', 'yes',     true );
			}
		}
	}



	/**
	 * Run when plugin version number changes
	 *
	 * @see SV_WC_Plugin::upgrade()
	 */
	protected function upgrade( $installed_version ) {

		global $wpdb;

		if ( version_compare( $installed_version, "1.0.4.1", '<=' ) ) {

			// in this version and before:
			// * custom product lite tabs were imported but their status was set
			//   to 'future' meaning they appeared in the Tab Manager menu, but
			//   not at the product level
			// * product tab layout had 'tab_name' rather than 'name' for imported
			//   custom product lite tabs
			// * imported custom product lite tabs attached to products did not
			//   have the '_override_tab_layout' meta set
			$tabs = get_posts( array( 'numberposts' => '', 'post_type' => 'wc_product_tab', 'nopaging' => true, 'post_status' => 'future' ) );

			if ( is_array( $tabs ) ) {
				foreach( $tabs as $tab ) {
					// make the tab post status 'publish'
					$update_tab = array(
						'ID'          => $tab->ID,
						'post_status' => 'publish',
					);
					wp_update_post( $update_tab );
					add_post_meta( $tab->ID, '_migrated_future', 'yes' );  // mark the tab as migrated, in case we need to reference them one day

					// fix the product tab layout 'tab_name' field, which should be 'name'
					$fixed = false;
					$product_tabs = get_post_meta( $tab->post_parent, '_product_tabs', true );
					foreach ( $product_tabs as $index => $product_tab ) {
						if ( isset( $product_tab['tab_name'] ) && $product_tab['tab_name'] && ! isset( $product_tab['name'] ) ) {
							$product_tabs[ $index ]['name'] = $product_tab['tab_name'];
							unset( $product_tabs[ $index ]['tab_name'] );
							$fixed = true;
						}
					}
					if ( $fixed ) {
						update_post_meta( $tab->post_parent, '_product_tabs', $product_tabs );
					}

					// It seems that setting the tab layout override in existing stores would be too dangerous, so for now the following is not used
					// enable the product '_override_tab_layout' so the product tab is actually used
					// update_post_meta( $tab->post_parent, '_override_tab_layout', 'yes' );
				}
			}
			unset( $tabs );
		}

		// In version 1.0.5 the core tab previously referred to as 'attributes' now
		//  needs to be referrred to as 'additional_information' for consistency with
		//  WC 2.0+, so fix the global and any product tab layouts
		if ( version_compare( $installed_version, "1.0.5", '<=' ) ) {

			// fix global tab layout
			$tab_layout = get_option( 'wc_tab_manager_default_layout', false );
			if ( $tab_layout && isset( $tab_layout['core_tab_attributes'] ) ) {
				$tab_layout['core_tab_additional_information'] = $tab_layout['core_tab_attributes'];
				$tab_layout['core_tab_additional_information']['id'] = 'additional_information';
				unset( $tab_layout['core_tab_attributes'] );

				update_option( 'wc_tab_manager_default_layout', $tab_layout );
			}

			// fix any product-level tab layouts
			$results = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key='_product_tabs'" );

			if ( is_array( $results ) ) {
				foreach ( $results as $row ) {
					$tab_layout = maybe_unserialize( $row->meta_value );

					if ( $tab_layout && isset( $tab_layout['core_tab_attributes'] ) ) {
						$tab_layout['core_tab_additional_information'] = $tab_layout['core_tab_attributes'];
						$tab_layout['core_tab_additional_information']['id'] = 'additional_information';
						unset( $tab_layout['core_tab_attributes'] );

						update_post_meta( $row->post_id, '_product_tabs', $tab_layout );
					}
				}
			}
			unset( $results );
		}
	}

} // class WC_Tab_Manager


/**
 * The WC_Tab_Manager global object
 * @name $wc_tab_manager
 * @global WC_Tab_Manager $GLOBALS['wc_tab_manager']
 */
$GLOBALS['wc_tab_manager'] = new WC_Tab_Manager();

} // init_woocommerce_tab_manager()

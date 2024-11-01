<?php

/**
 * The Default Tab Layout Admin UI and action handler for the WooCommerce Tab Manager plugin
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/**
 * Renders the default tab layout which allows global/core/3rd party tabs to be
 * rearranged.
 *
 * The following globals and variables are expected:
 *
 * @access public
 * @global WC_Tab_manager $wc_tab_manager the Tab Manager main class
 */
function wc_tab_manager_render_layout_page() {

	global $wc_tab_manager;

	$tabs = get_option( 'wc_tab_manager_default_layout', false );

	// show any error messages
	?>
	<style type="text/css">
		p.note {
			border: 1px solid #DDDDDD;
			float: left;
			margin-top: 0;
			padding: 8px;
		}
		#woocommerce-product-data { margin-top:20px; }
		#woocommerce-product-data h3.hndle { margin-bottom:0; }
		/* On the global layout page we want the box to span the whole page, rather than having the typical layout with the left-hand side menu items */
		#woocommerce-product-data #woocommerce_product_tabs { float:none; }
		#woocommerce-product-data .panel-wrap { padding-left: 0 }
	</style>

	<form action="admin-post.php" method="post">
		<div class="wrap woocommerce">
			<h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
				<a href="<?php echo admin_url( 'edit.php?post_type=wc_product_tab' ); ?>" class="nav-tab"><?php _e( 'Tabs', WC_Tab_Manager::TEXT_DOMAIN ); ?></a>
				<a href="<?php echo admin_url( 'post-new.php?post_type=wc_product_tab' ); ?>" class="nav-tab "><?php _e( 'Add Global Tab', WC_Tab_Manager::TEXT_DOMAIN ); ?></a>
				<a href="<?php echo admin_url( 'admin.php?page=' . WC_TAB_MANAGER::PLUGIN_ID ); ?>" class="nav-tab nav-tab-active"><?php _e( 'Default Tab Layout', WC_Tab_Manager::TEXT_DOMAIN ); ?></a>
			</h2>

			<?php if ( isset( $_GET['result'] ) ) : /* show any action messages */ ?>
			<div id="message" class="updated"><p><strong><?php printf( __( 'Tabs layout %s', WC_Tab_Manager::TEXT_DOMAIN ), esc_html( $_GET['result'] ) ); ?></strong></p></div>
			<?php endif; ?>

			<div class="postbox" id="woocommerce-product-data">
				<h3 class="hndle"><span><?php _e( 'Default Tab Layout', WC_Tab_Manager::TEXT_DOMAIN ); ?></span></h3>
				<div class="inside">
					<input type="hidden" value="9c065bb457" name="woocommerce_meta_nonce" id="woocommerce_meta_nonce">
					<input type="hidden" value="/wp-admin/post.php?post=234&amp;action=edit&amp;message=1" name="_wp_http_referer">

					<div class="panel-wrap product_data">
						<?php wc_tab_manager_sortable_product_tabs( $tabs ); ?>
					</div>
				</div>
			</div>
		</div>

		<p class="submit">
			<input type="hidden" name="action" value="wc_tab_manager_default_layout_save" />
			<input type="submit" name="save" value="<?php _e( 'Save Changes', WC_Tab_Manager::TEXT_DOMAIN ); ?>" class="button-primary" />
		</p>
	</form>

	<?php
}


add_action( 'admin_post_wc_tab_manager_default_layout_save', 'wc_tab_manager_default_layout_save' );

/**
 * Save the default tab layout settings
 * @access public
 */
function wc_tab_manager_default_layout_save() {

	global $wc_tab_manager;

	$tabs = wc_tab_manager_process_tabs();

	update_option( 'wc_tab_manager_default_layout', $tabs );

	return wp_redirect( add_query_arg( array( "page" => WC_TAB_MANAGER::PLUGIN_ID, 'result' => 'saved' ), admin_url( 'admin.php' ) ) );
}

<?php

/*
	Copyright: © 2009-2011 WooThemes.
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/


/**
 * WC_Brands classes
 **/
require_once( 'classes/class-wc-brands.php' );

if ( is_admin() )
	require_once( 'classes/class-wc-brands-admin.php' );

register_activation_hook( __FILE__, array( 'WC_Brands', 'init_taxonomy' ), 10 );
register_activation_hook( __FILE__, 'flush_rewrite_rules', 20 );

/**
 * Helper function :: get_brand_thumbnail_url function.
 *
 * @access public
 * @return string
 */
function get_brand_thumbnail_url( $brand_id, $size = 'full' ) {
	$thumbnail_id = get_woocommerce_term_meta( $brand_id, 'thumbnail_id', true );

	if ( $thumbnail_id )
		return current( wp_get_attachment_image_src( $thumbnail_id, $size ) );
}

/**
 * get_brands function.
 *
 * @access public
 * @param int $post_id (default: 0)
 * @param string $sep (default: ')
 * @param mixed '
 * @param string $before (default: '')
 * @param string $after (default: '')
 * @return void
 */
function get_brands( $post_id = 0, $sep = ', ', $before = '', $after = '' ) {
	global $post;

	if ( $post_id )
		$post_id = $post->ID;

	return get_the_term_list( $post_id, 'product_brand', $before, $sep, $after );
}

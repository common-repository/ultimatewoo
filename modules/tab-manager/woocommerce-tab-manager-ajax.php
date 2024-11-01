<?php

/**
 * WooCommerce Ajax Handlers
 *
 * Handles AJAX requests via wp_ajax hook (both admin and front-end events)
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/** Admin AJAX events **************************************************/


add_action( 'wp_ajax_wc_tab_manager_get_editor', 'wc_tab_manager_get_editor' );

/**
 * Gets a quicktags editor
 *
 * @access public
 */
function wc_tab_manager_get_editor() {

	check_ajax_referer( 'get-editor', 'security' );

	$size = esc_attr( $_POST['size'] );

	// TODO: would be nice to suppress $editor_buttons_css but doesn't seem possible (unless there's some way of hooking into the wp_print_styles('editor-buttons'); and stopping it
	//       (or, maybe call wp_editor twice, discarding the content from the first... ?)
	wp_editor( '', 'producttabcontent' . $size, array( 'textarea_name' => 'product_tab_content[' . $size . ']', 'tinymce' => false, 'textarea_rows' => 10 ) );

	// Quit out
	die();
}

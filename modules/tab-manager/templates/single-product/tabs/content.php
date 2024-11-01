<?php

/**
 * Tab Manager tab content template
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $product;

echo apply_filters( 'woocommerce_tab_manager_tab_panel_content', $tab['content'], $tab, $product );

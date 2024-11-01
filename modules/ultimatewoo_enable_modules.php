<?php

//* Add modules when enabled

if ( get_option( 'ultimatewoo_stripe' ) == '1' ) {
	include_once dirname( __FILE__ ) . '/stripe/woocommerce-gateway-stripe.php';
}

if ( get_option( 'ultimatewoo_usps' ) == '1' ) {
	include_once dirname( __FILE__ ) . '/shipping-usps/shipping-usps.php';
}

if ( get_option( 'ultimatewoo_ups' ) == '1' ) {
	include_once dirname( __FILE__ ) . '/shipping-ups/shipping-ups.php';
}

if ( get_option( 'ultimatewoo_advanced_notifications' ) == '1' ) {
	include_once dirname( __FILE__ ) . '/advanced-notifications/advanced-notifications.php';
}

if ( get_option( 'ultimatewoo_ajax_nav' ) == '1' ) {
	include_once dirname( __FILE__ ) . '/ajax-layered-nav/ajax-layered-nav-widget.php';
}

if ( get_option( 'ultimatewoo_amazon_s3' ) == '1' ) {
	include_once dirname( __FILE__ ) . '/amazon-s3-storage/amazon-s3-storage.php';
}

if ( get_option( 'ultimatewoo_name_your_price' ) == '1' ) {
	include_once dirname( __FILE__ ) . '/name-your-price/woocommerce-name-your-price.php';
}

if ( get_option( 'ultimatewoo_product_enquiry_form' ) == '1' ) {
	include_once dirname( __FILE__ ) . '/product-enquiry-form/product-enquiry-form.php';
}

if ( get_option( 'ultimatewoo_tab_manager' ) == '1' ) {
	include_once dirname( __FILE__ ) . '/tab-manager/woocommerce-tab-manager.php';
}

if ( get_option( 'ultimatewoo_product_image_watermark' ) == '1' ) {
	include_once dirname( __FILE__ ) . '/product-image-watermark/woocommerce-watermark.php';
}

if ( get_option( 'ultimatewoo_checkout_field_editor' ) == '1' ) {
	include_once dirname( __FILE__ ) . '/checkout-field-editor/checkout-field-editor.php';
}

if ( get_option( 'ultimatewoo_shipment_tracking' ) == '1' ) {
	include_once dirname( __FILE__ ) . '/shipment-tracking/shipment-tracking.php';
}

if ( get_option( 'ultimatewoo_social_login' ) == '1' ) {
	include_once dirname( __FILE__ ) . '/social-login/woocommerce-social-login.php';
}

if ( get_option( 'ultimatewoo_brands' ) == '1' ) {
	include_once dirname( __FILE__ ) . '/brands/woocommerce-brands.php';
}

if ( get_option( 'ultimatewoo_msrp' ) == '1' ) {
	include_once dirname( __FILE__ ) . '/msrp-pricing/woocommerce-msrp.php';
}
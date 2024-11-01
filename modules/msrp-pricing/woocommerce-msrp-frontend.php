<?php

if ( ! class_exists( 'woocommerce_msrp_frontend' ) ) {
    class woocommerce_msrp_frontend {


        /**
         * Add hooks for the default services.
         */
        function __construct() {

            // Add hooks for JS and CSS
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

            // Single Product Page
            add_action( 'woocommerce_single_product_summary', array( $this, 'show_msrp' ), 7 );
            add_action( 'woocommerce_available_variation', array( $this, 'add_msrp_to_js' ), 10, 3 );

            // Loop
            add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'show_msrp' ), 9);

            // Grouped product table
            add_action( 'woocommerce_grouped_product_list_before_price', array( $this, 'show_grouped_msrp' ), 9);

        }


        /**
         * add_msrp_to_js function.
         *
         * @access public
         * @param mixed $variation_data
         * @param mixed $product
         * @param mixed $variation
         * @return void
         */
        function add_msrp_to_js( $variation_data, $product, $variation ) {

            $msrp = get_post_meta( $variation->variation_id, '_msrp', true );

            if ( empty ( $msrp ) )
                return $variation_data;

            $variation_data['msrp'] = $msrp;
            $variation_data['msrp_html'] = woocommerce_price( $msrp );
            $variation_data['non_msrp_price'] = $variation->price;

            return $variation_data;

        }


        /**
         * Enqueue javascript required to show MSRPs on variable products
         */
        function enqueue_scripts() {
            wp_enqueue_script( 'woocommerce_msrp', plugins_url( 'js/frontend.js', __FILE__ ), array( 'jquery' ) );
        }


        /**
         * Enqueue frontend stylesheets
         */
        function enqueue_styles() {
            wp_enqueue_style( 'woocommerce_msrp', plugins_url( 'css/frontend.css', __FILE__ ) );
        }


        /**
         * Wrapper function to add markup required when showing the MSRP in a
         * grouped product table list
         */
        function show_grouped_msrp( $current_product ) {
            echo '<td class="woocommerce_msrp_price">';
            $this->show_msrp( $current_product );
            echo '</td>';
        }



        /**
         * Get the MSRP for a non-variable product
         * @param  object $current_product The product the MSRP is required for
         * @return string                  The MSRP, or empty string
         */
        function get_msrp_for_single_product( $current_product ) {
			return get_post_meta( $current_product->id, '_msrp_price', true );
        }



        /**
         * Get the MSRP for a variable product. This will be the cheapest MSRP for any
         * variation, and does not necessarily relate to the cheapest actual priced variation.
         * @param  object $current_product The product the MSRP is required for
         * @return string                  The MRSP, or empty string
         */
        function get_msrp_for_variable_product( $current_product ) {
        	$children = $current_product->get_children();

        	if ( ! $children )
        		return $this->get_msrp_for_single_product( $current_product );

        	$lowest_msrp = '';
        	foreach ( $children as $child ) {
        		$child_msrp = get_post_meta( $child, '_msrp', true );
        		if ( empty( $lowest_msrp ) || $child_msrp < $lowest_msrp ) {
        			$lowest_msrp = $child_msrp;
        		}
        	}
        	return $lowest_msrp;
        }



        /**
         * Show the MSRP on the frontend
         */
        function show_msrp( $current_product = null ) {

            global $product, $woocommerce_settings;

            $msrp_status = get_option( 'woocommerce_msrp_status' );

            if ( ! $current_product )
                $current_product = $product;

            // User has chosen not to show MSRP, don't show it
            if ( empty ( $msrp_status ) || $msrp_status == 'never' )
                return;

            $msrp_description = get_option( 'woocommerce_msrp_description' );

            echo '<script type="text/javascript">';
            echo 'var msrp_status = "'.esc_attr( $msrp_status ).'";';
            echo 'var msrp_description= "'.esc_attr( $msrp_description ).'";';
            echo '</script>';

            // Don't show the MSRP "from" on the single product page - it'll be handled
            // by the javascript
            if ( is_single() && $current_product->product_type == 'variable' ) {
            	return;
            }

            // Pricing display for variable products handled elsewhere
            if ( $current_product->product_type == 'variable' ) {
                $msrp = $this->get_msrp_for_variable_product( $current_product );
            } else {
            	$msrp = $this->get_msrp_for_single_product( $current_product );
            }

            // Don't show anything if MSRP not set
            if ( empty ( $msrp ) )
                return;

            if ( $msrp_status == 'always' ||
                ( $msrp != $current_product->price && $msrp_status == 'different' ) ) {
                echo '<div class="woocommerce_msrp">';
                esc_html_e( $msrp_description );
                echo ': ';
                echo '<span class="woocommerce_msrp_price">' . woocommerce_price( $msrp ) . '</span>';
                echo '</div>';
            }
        }
    }
}

$woocommerce_msrp_frontend = new woocommerce_msrp_frontend();
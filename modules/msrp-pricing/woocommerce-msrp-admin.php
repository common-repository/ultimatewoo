<?php

if ( ! class_exists( 'woocommerce_msrp_admin' ) ) {
	class woocommerce_msrp_admin {

		/**
		 * Add required hooks
		 */
		function __construct() {

			add_action( 'admin_init', array( $this, 'admin_init' ) );

			// Add meta box to the product page
			add_action( 'woocommerce_product_options_pricing', array( $this, 'product_meta_field') );
			add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'variation_show_fields'), 10, 2 );
			add_action( 'woocommerce_process_product_meta_variable', array( $this, 'variation_save_fields') );
			add_action( 'save_post', array( $this, 'save_product' ) );

		}

		/**
		 * Set up the plugin for translation
		 */
		function admin_init() {

			$domain = 'woocommerce_msrp';
			$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

			load_textdomain( $domain, WP_LANG_DIR . '/woocommerce_msrp/' . $domain . '-' . $locale . '.mo' );
			load_plugin_textdomain( 'woocommerce_msrp', null, basename( dirname( __FILE__ ) ) . '/languages' );

			// Add settings to the WooCommerce settings page
			if ( $this->is_wc_2_1() ) {
				add_filter( 'woocommerce_general_settings', array( $this, 'settings_array' ) ); // WC2.1
			} else {
				add_filter( 'woocommerce_catalog_settings', array( $this, 'settings_array' ) ); // WC2.0
			}

		}

		/**
		 * Add the settings to the WooCommerce settings page
		 */
		function settings_array( $settings ) {
			// Find the end of the pricing section
			foreach ( $settings as $key => $setting ) {
				if ( $setting['type'] == 'sectionend' && $setting['id'] == 'pricing_options' ) {
					$cutoff = $key;
					break;
				}
			}

			// Move the first chunk over
			$new_settings = array_slice( $settings, 0, $cutoff + 1);

			// Add the new fields

			// Heading
			$new_settings[] = array(
				'title' => __( 'MSRP pricing options', 'woocommerce_msrp' ),
				'type'  => 'title',
				'id'    => 'woocommerce_msrp',
				'desc'  => __( 'Options controlling when, and how to display MSRP pricing', 'woocommerce_msrp' ),
			);

			// Show always / only if different / never
			$new_settings[] = array(
				'name'     => __( 'Show MSRP Pricing?', 'woocommerce_msrp' ),
				'desc'     => __( 'When to show MSRP pricing', 'woocommerce_msrp' ),
				'tip'      => '',
				'id'       => 'woocommerce_msrp_status',
				'css'      => '',
				'std'      => 'always',
				'type'     => 'select',
				'options'  => array(
					'always'    => __( 'Always', 'woocommerce_msrp' ),
					'different' => __( 'Only if different', 'woocommerce_msrp' ),
					'never'     => __( 'Never', 'woocommerce_msrp' ),
				),
				'desc_tip' => __( 'You can choose to display MSRP pricing:<dl><dt>Always</dt><dd>All the time</dd><dt>Only if different</dt><dd>Only if the MSRP is different from your standard price for the product</dd><dt>Never</dt><dd>Never</dd></dl>', 'woocommerce_msrp' ),
			);

			// Description - text field
			$new_settings[] = array(
				'name'     => __( 'MSRP Labelling', 'woocommerce_msrp' ),
				'desc'     => __( 'MSRP prices will be labelled with this description', 'woocommerce_msrp' ),
				'tip'      => '',
				'id'       => 'woocommerce_msrp_description',
				'css'      => '',
				'std'      => __( 'MSRP', 'woocommerce_msrp' ),
				'type'     => 'text',
				'desc_tip' => __( 'MSRP prices will be labelled with this description', 'woocommerce_msrp' ),
			);

			$new_settings[] = array(
				'type' => 'sectionend',
				'id'   => 'woocommerce_msrp',
			);

			// Add the remainder back in
			$new_settings = array_merge( $new_settings, array_slice( $settings, $cutoff + 1, 999 ) );

			return $new_settings;

		}

		/**
		 * Display the meta field for MSRP prices on the product page
		 */
		function product_meta_field() {

			woocommerce_wp_text_input( array( 'id' => '_msrp_price', 'class' => 'wc_input_price short', 'label' => __( 'MSRP Price', 'woocommerce_msrp' ) . ' (' . get_woocommerce_currency_symbol() . ')', 'description' => '' ) );

		}

		/**
		 * Show the fields for editing the MSRP on the variations panel on the post edit screen
		 * @param  array $variation_data The variation data for this variation
		 * @param  [type] $loop          Unused
		 */
		function variation_show_fields( $loop, $variation_data ) {

?>
			<tr>
				<td>
					<label><?php echo __( 'MSRP Price', 'woocommerce_msrp' ) . ' (' . get_woocommerce_currency_symbol() . ')'; ?></label><input type="text" size="5" name="variable_msrp[<?php echo $loop; ?>]" value="<?php if (isset($variation_data['_msrp'][0])) echo $variation_data['_msrp'][0]; ?>" />
				</td>
			</tr>
			<?php

		}

		/**
		 * Save MSRP values for variable products
		 * @param  int $product_id The parent product ID (Unused)
		 */
		function variation_save_fields( $product_id ) {

			if ( ! isset ( $_POST['variable_post_id'] ) )
				return;

			$max_loop = max( array_keys( $_POST['variable_post_id'] ) );

			for ( $idx = 0; $idx <= $max_loop; $idx++ ) {
				if ( empty ( $_POST['variable_post_id'][$idx] ) )
					continue;
				$variation_id = (int) $_POST['variable_post_id'][$idx];
				update_post_meta( $variation_id, '_msrp', $_POST['variable_msrp'][$idx] );
			}

		}

		/**
		 * Save the product meta information
		 * @param int $product_id The product ID
		 */
		function save_product( $product_id ) {
			// Verify if this is an auto save routine.
			// If it is our form has not been submitted, so we dont want to do anything
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
				return;

			if ( ! isset ( $_POST['_msrp_price'] ) )
				return;

			update_post_meta( $product_id, '_msrp_price', $_POST['_msrp_price'] );
		}

		/**
		 * From:
		 * https://github.com/skyverge/wc-plugin-compatibility/
		 */
		private function get_wc_version() {
			if ( defined( 'WC_VERSION' )          && WC_VERSION )          return WC_VERSION;
			if ( defined( 'WOOCOMMERCE_VERSION' ) && WOOCOMMERCE_VERSION ) return WOOCOMMERCE_VERSION;
			return null;
		}

		/**
		 * From:
		 * https://github.com/skyverge/wc-plugin-compatibility/
		 */
		private function is_wc_2_1() {
			return version_compare( $this->get_wc_version(), '2.1-beta', '>' );
		}
	}
}

$woocommerce_msrp_admin = new woocommerce_msrp_admin();
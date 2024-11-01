<?php

/*
Copyright: © 2014 Gerhard Potgieter.
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/**
 * WooCommerce_Amazon_S3_Storage class
 **/
if ( ! class_exists( 'WooCommerce_Amazon_S3_Storage' ) ) {

	class WooCommerce_Amazon_S3_Storage {

		/**
		 * Class Variables
		 **/
		var $settings_name = 'woo_amazon_s3_storage';
		var $credentials = array();
		var $disable_ssl;

		/**
		 * Constructor
		 **/
		function __construct() {
			// Load AJAX functions
			require_once( 'amazon-s3-storage-ajax.php' );
			$admin_settings = get_option( $this->settings_name );
			$this->credentials['key'] = $admin_settings['amazon_access_key'];
			$this->credentials['secret'] = $admin_settings['amazon_access_secret'];
			$this->disable_ssl = $admin_settings['amazon_disable_ssl'];

			// Create Menu under WooCommerce Menu
			add_action( 'admin_menu', array( $this, 'register_menu' ) );
			$wc_version = (string) get_option( 'woocommerce_db_version' );
			if ( version_compare( $wc_version, '2.0', '<' ) ) {
				add_action( 'woocommerce_product_options_downloads', array( $this, 'product_fields' ) );
				add_action( 'woocommerce_process_product_meta', array( $this, 'product_fields_process' ), 1, 2 );
				add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'variable_fields' ), 10, 2 );
				add_action( 'woocommerce_product_after_variable_attributes_js', array( $this, 'variable_fields_js' ) );
				add_action( 'woocommerce_process_product_meta_variable', array( $this, 'variable_fields_process' ), 10, 1 );
				add_filter( 'woocommerce_file_download_path', array( $this, 'product_download' ), 1, 2 );
			} else {
				//add_action( 'woocommerce_product_options_downloads', array( $this, 'wc2_product_fields' ) );
				//add_action( 'woocommerce_process_product_meta', array( &$this, 'wc2_product_fields_process' ), 1, 2 );
				//add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'wc2_variable_fields' ), 10, 2 );
				//add_action( 'woocommerce_product_after_variable_attributes_js', array( $this, 'wc2_variable_fields_js' ) );
				//add_action( 'woocommerce_process_product_meta_variable', array( $this, 'wc2_variable_fields_process' ), 10, 1 );
				add_filter( 'woocommerce_file_download_path', array( $this, 'wc2_product_download' ), 1, 3 );
				register_activation_hook( __FILE__, array( $this, 'upgrade' ) );
			}

			// Add amazon_s3 shortcode
			add_shortcode( 'amazon_s3', array( $this, 'amazon_shortcode' ) );
		}

		function register_menu() {
			add_submenu_page( 'woocommerce', __( 'WooCommerce Amazon S3 Storage', 'wc_amazon_s3' ), __( 'Amazon S3 Storage', 'wc_amazon_s3' ), 'manage_woocommerce', 'woo_amazon_s3_storage', array( &$this, 'menu_setup' ) );
		}

		function upgrade() {
			$args = array(
				'post_type' =>  array( 'product', 'product_variation' ),
				'meta_key' => '_amazon_s3_bucket',
				'posts_per_page' => -1
				);
			$upgrade_query = new WP_Query( $args );
			while ( $upgrade_query->have_posts() ) {
				$upgrade_query->the_post();
				global $post;
				$use_s3 = get_post_meta( $post->ID, '_use_amazon_s3', true );
				if ( $use_s3 == 'yes' ) {
					$bucket = get_post_meta( $post->ID, '_amazon_s3_bucket', true );
					$object = get_post_meta( $post->ID, '_amazon_s3_object', true );
					$shortcode = '[amazon_s3 bucket=' . $bucket . ' object=' . $object . ']';
					$file_paths = get_post_meta( $post->ID, '_file_paths', true );
					$old_file_paths = $file_paths;
					if ( is_array( $file_paths ) || empty( $file_paths ) ) {
						if ( ! in_array( $shortcode, $file_paths ) ) {
							$file_paths[] = $shortcode;
							update_post_meta( $post->ID, '_file_paths', $file_paths, $old_file_paths );
							//delete_post_meta( $post->ID, '_use_amazon_s3' );
							//delete_post_meta( $post->ID, '_amazon_s3_bucket' );
							//delete_post_meta( $post->ID, '_amazon_s3_object' );
						}
					}
				}
			}
		}

		function array_to_options( $array, $selected, $escape = '' ) {
			$options = '';
			foreach( $array as $id => $value ) {
				if ( $selected === $id )
					$selected_text = ' selected="selected"';
				else $selected_text = '';
				$options .= '<option value="'.$id.'"'.$selected_text.'>' . $value . '</option>';
			}
			return $options . $escape;
		}

		function product_fields() {
			require_once 'amazon_sdk/sdk.class.php';
			global $thepostid, $post, $woocommerce;

			if ( ! $thepostid ) $thepostid = $post->ID;
			$product = new WC_Product( $thepostid );
			try {
				woocommerce_wp_checkbox( array( 'id' => '_use_amazon_s3', 'label' => __( 'Use Amazon S3', 'wc_amazon_s3' ), 'description' => __('Enable this option to use a file stored on your Amazon S3 service', 'wc_amazon_s3') ) );
				$bucket_arr = array();
				$object_arr = array();
				// Only make calls to amazon if product is downloadable.
				if ( $product->is_downloadable() ) {
					// Get buckets
					$s3 = new AmazonS3( $this->credentials );
					$this->set_ssl( $s3 );
					$buckets = $s3->get_bucket_list();
					$bucket_arr = array();
					$bucket_arr['-1'] = 'No Bucket';
					foreach( $buckets as $key => $bucket ) {
						$bucket_arr[$bucket] = $bucket;
					}
					//set_transient( 'woo_amazon_s3_buckets', $bucket_arr, 60*60 );

					$current_bucket = get_post_meta( $thepostid, '_amazon_s3_bucket', true );
					if ( empty( $current_bucket ) && count( $bucket_arr ) == 1 )
						$current_bucket = array_shift( array_keys( $bucket_arr ) );

					$object_arr = array();
					if( ! empty( $current_bucket ) && $current_bucket <> '-1' ) {
						$objects = $s3->get_object_list( $current_bucket );
						foreach( $objects as $key => $object ) {
							// We don't want to display folders
							if( substr( $object, -1 ) <> '/' )
								$object_arr[$object] = $object;
						}
					}
				}
				woocommerce_wp_select( array( 'id' => '_amazon_s3_bucket', 'label' => __( 'Amazon S3 Bucket', 'wc_amazon_s3' ), 'options' => $bucket_arr,
					'description' => '<img id="amazon_s3_bucket_refresh" style="cursor:pointer;" alt="Refresh Buckets" title="Refresh Buckets" src="'. plugins_url( 'assets/img/refresh.png' , __FILE__ ) . '"/>' . __( 'The bucket your file reside in.', 'wc_amazon_s3' ) ) );

				woocommerce_wp_select( array( 'id' => '_amazon_s3_object', 'label' => __( 'Amazon S3 Object', 'wc_amazon_s3' ), 'options' => $object_arr,
					'description' => __( 'The object that will serve as the downloadable file.', 'wc_amazon_s3' ) ) );

				$woocommerce->add_inline_js("
						jQuery('#amazon_s3_bucket_refresh').click(function(){
							jQuery(this).fadeTo('400', '0.6');

							var data = {
								action: 'woo_amazon_s3_load_buckets',
								security: '" . wp_create_nonce("amazon-s3-load-objects") . "'
							};
							jQuery.post( '" . admin_url( 'admin-ajax.php' ) . "', data, function( response ) {
								jQuery('select#_amazon_s3_bucket').html( response );
								jQuery('select#_amazon_s3_object').empty();
								jQuery('#amazon_s3_bucket_refresh').fadeTo('400', '1');
							});
						});
						jQuery('select#_amazon_s3_bucket').change(function(){
							var data = {
								action: 'woo_amazon_s3_load_objects',
								bucket: jQuery('select#_amazon_s3_bucket').val(),
								security: '" . wp_create_nonce("amazon-s3-load-objects") . "'
							};

							jQuery.post( '" . admin_url( 'admin-ajax.php' ) . "', data, function( response ) {
								jQuery('select#_amazon_s3_object').html( response );
							});
						});
						jQuery('.variable_amazon_s3_bucket').live('change', function(){
							var _bucket = this;
							var data = {
								action: 'woo_amazon_s3_load_objects',
								bucket: jQuery(this).val(),
								security: '" . wp_create_nonce("amazon-s3-load-objects") . "'
							};
							jQuery.post( '" . admin_url( 'admin-ajax.php' ) . "', data, function( response ) {
								jQuery(_bucket).closest('tr').find('select.variable_amazon_s3_object').html( response );
							});
						});
					");

			} catch ( Exception $e ) {
				echo '<div id="woocommerce_errors" class="error fade">';
				echo '<p>' . __( 'An error occured trying to communicate with Amazon S3, please check your settings.', 'woo_amazon_s3' ) . '</p>';
				echo '</div>';
			}
		}

		function wc2_product_fields() {
			require_once 'amazon_sdk/sdk.class.php';
			global $woocommerce, $post;

			$product = get_product( $post->ID );
			$bucket_arr = array();
			$object_arr = array();

			// Connect to Amazon and load buckets
			$s3 = new AmazonS3( $this->credentials );
			$this->set_ssl( $s3 );
			$buckets = $s3->get_bucket_list();
			$bucket_arr['-1'] = 'No Bucket';
			foreach( $buckets as $key => $bucket ) {
				$bucket_arr[$bucket] = $bucket;
			}

			// Fetch current product buckets & objects
			$current_buckets = get_post_meta( $post->ID, '_amazon_s3_bucket' );
			$current_objects = get_post_meta( $post->ID, '_amazon_s3_object' );
			if ( ! empty( $current_buckets ) && is_array( $current_buckets ) ) {
				$count = 0;
				foreach( $current_buckets[0] as $current_bucket ) {
					echo '	<p class="form-field amazon_s3_wrap"><label for="_amazon_s3_bucket">' . __( 'Amazon S3 File', 'wc_amazon_s3' ) . '</label>
							<select id="_amazon_s3_bucket" name="_amazon_s3_bucket[]">';
					echo $this->array_to_options( $bucket_arr, $current_bucket );
					$object_arr = array();
					if ( $current_bucket <> '-1' ) {
						$objects = $s3->get_object_list( $current_bucket );

						foreach( $objects as $key => $object ) {
							// We don't want to display folders
							if( substr( $object, -1 ) <> '/' )
								$object_arr[$object] = $object;
						}
					}
					echo '	</select>
							<select id="_amazon_s3_object" name="_amazon_s3_object[]">';
					echo $this->array_to_options( $object_arr, $current_objects[0][$count] );
					echo'	</select>';
					echo '<input type="button"  class="del_amazon_button button" value="'.__( 'Remove', 'wc_amazon_s3' ).'" />';
					$count++;
					if ( count( $current_buckets[0] ) == $count ) {
						echo '<input type="button"  class="add_amazon_button button" value="'.__( 'Add another file', 'wc_amazon_s3' ).'" />';
					}
				}

			// Backward compatability for pre 2.0 fields
			} elseif ( ! empty( $current_buckets ) && ! is_array( $current_buckets ) ) {

			// No Amazon fields
			} else {
				echo '	<p class="form-field amazon_s3_wrap"><label for="_amazon_s3_bucket">' . __( 'Amazon S3 File', 'wc_amazon_s3' ) . '</label>
							<select id="_amazon_s3_bucket" name="_amazon_s3_bucket[]">';
				echo $this->array_to_options( $bucket_arr, '-1' );
				echo '	</select>
						<select id="_amazon_s3_object" name="_amazon_s3_object[]">
						</select>
						<input type="button"  class="del_amazon_button button" value="'.__('Remove', 'wc_amazon_s3').'" />
						<input type="button"  class="add_amazon_button button" value="'.__('Add another file', 'wc_amazon_s3').'" />';
			}

			// Add some inline js to handle new files
			$woocommerce->add_inline_js("
				jQuery('.add_amazon_button').live('click', function() {
					jQuery('.add_amazon_button').hide();
					jQuery(this).after('<input type=\"button\"  class=\"del_amazon_button button\" value=\"" . __('Remove', 'wc_amazon_s3') . "\" />');
					jQuery(this).parent().after('\
						<p class=\"form-field amazon_s3_wrap\"><label for=\"_amazon_s3_bucket\">" . __( 'Amazon S3 File', 'wc_amazon_s3' ) . "</label>\
						<select id=\"_amazon_s3_bucket\" name=\"_amazon_s3_bucket[]\">\
							".$this->array_to_options( $bucket_arr, -1, '\\' )."
						</select>\
						<select id=\"_amazon_s3_object\" name=\"_amazon_s3_object[]\">\
							<option value=\"-1\" selected=\"selected\">No Object</option>\
						</select>\
						<input type=\"button\"  class=\"add_amazon_button button\" value=\"" . __( 'Add another file', 'wc_amazon_s3' ) . "\" />\
					');
				});
				jQuery('.del_amazon_button').live('click', function() {
					jQuery(this).parent().remove();
				});
			");

			$woocommerce->add_inline_js("
						jQuery('select#_amazon_s3_bucket').live('change',function(){
							var _bucket = this;
							var data = {
								action: 'woo_amazon_s3_load_objects',
								bucket: jQuery(this).val(),
								security: '" . wp_create_nonce("amazon-s3-load-objects") . "'
							};

							jQuery.post( '" . admin_url( 'admin-ajax.php' ) . "', data, function( response ) {
								jQuery(_bucket).next().html( response );
							});
						});
						jQuery('.variable_amazon_s3_bucket').live('change', function(){
							var _bucket = this;
							var data = {
								action: 'woo_amazon_s3_load_objects',
								bucket: jQuery(this).val(),
								security: '" . wp_create_nonce("amazon-s3-load-objects") . "'
							};
							jQuery.post( '" . admin_url( 'admin-ajax.php' ) . "', data, function( response ) {
								jQuery(_bucket).closest('tr').find('select.variable_amazon_s3_object').html( response );
							});
						});
					");
		}

		function product_fields_process( $post_id, $post ) {
			$product_type = sanitize_title( stripslashes( $_POST['product-type'] ) );
			if ( $product_type == 'simple' ) {
				if( isset( $_POST['_use_amazon_s3'] ) )
					update_post_meta( $post_id, '_use_amazon_s3', 'yes' );
				else update_post_meta( $post_id, '_use_amazon_s3', 'no' );
				update_post_meta( $post_id, '_amazon_s3_bucket', stripslashes( $_POST['_amazon_s3_bucket'] ) );
				update_post_meta( $post_id, '_amazon_s3_object', stripslashes( $_POST['_amazon_s3_object'] ) );
			}
		}

		function wc2_product_fields_process( $post_id, $post ) {
			$product_type = sanitize_title( stripslashes( $_POST['product-type'] ) );
			if ( $product_type == 'simple' ) {
				if ( isset( $_POST['_amazon_s3_bucket'] ) ) {
					$buckets = $_POST['_amazon_s3_bucket'];
					update_post_meta( $post_id, '_amazon_s3_bucket', $_POST['_amazon_s3_bucket'] );
					update_post_meta( $post_id, '_amazon_s3_object', $_POST['_amazon_s3_object'] );
				} else {
					delete_post_meta( $post_id, '_amazon_s3_bucket' );
					delete_post_meta( $post_id, '_amazon_s3_bucket' );
				}
			}
		}

		function variable_fields( $loop, $variation_data ) {
			require_once 'amazon_sdk/sdk.class.php';
			try {
				$bucket_arr = array();
				$object_arr = array();
				$current_bucket = '';
				// Get buckets
				$s3 = new AmazonS3( $this->credentials );
				$this->set_ssl( $s3 );
				$buckets = $s3->get_bucket_list();
				$bucket_arr = array();
				$bucket_arr['-1'] = 'No Bucket';
				foreach( $buckets as $key => $bucket ) {
					$bucket_arr[$bucket] = $bucket;
				}
				if ( isset( $variation_data['_use_amazon_s3'][0] ) )
					$current_bucket = $variation_data['_amazon_s3_bucket'][0];
				if ( empty( $current_bucket ) && count( $bucket_arr ) == 1 )
					$current_bucket = array_shift( array_keys( $bucket_arr ) );
				$object_arr = array();
				if( ! empty( $current_bucket ) && $current_bucket <> '-1' ) {
					$objects = $s3->get_object_list( $current_bucket );
					foreach( $objects as $key => $object ) {
						// We don't want to display folders
						if( substr( $object, -1 ) <> '/' )
							$object_arr[$object] = $object;
					}
				}
			} catch ( Exception $e ) {
				echo '<div id="woocommerce_errors" class="error fade">';
				echo '<p>' . __( 'An error occured trying to communicate with Amazon S3, please check your settings.', 'woo_amazon_s3' ) . '</p>';
				echo '</div>';
			}
			if ( isset( $variation_data['_amazon_s3_bucket'][0] ) )
				$current_bucket = $variation_data['_amazon_s3_bucket'][0];
			else $current_bucket = '-1';
			if ( isset( $variation_data['_amazon_s3_object'][0] ) )
				$current_object = $variation_data['_amazon_s3_object'][0];
			else $current_object = '';

			echo '<tr>';
			?>

			<td>
				<div class="show_if_variation_downloadable">
					<label><?php _e( 'Amazon S3 Bucket', 'wc_amazon_s3' ); ?></label>
					<select name="variable_amazon_s3_bucket[<?php echo $loop; ?>]" class="variable_amazon_s3_bucket">
					<?php foreach ( $bucket_arr as $key => $bucket ) : ?>
						<option value="<?php echo $key; ?>" <?php selected( $current_bucket, $key ); ?> ><?php echo $bucket; ?></option>
					<?php endforeach; ?>
					</select>
				</div>
			</td>
			<td>
				<div class="show_if_variation_downloadable">
					<label><?php _e( 'Amazon S3 Object', 'wc_amazon_s3' ); ?></label>
					<select name="variable_amazon_s3_object[<?php echo $loop; ?>]" class="variable_amazon_s3_object">
						<?php foreach ( $object_arr as $object ) : ?>
							<option value="<?php echo $object; ?>" <?php selected( $current_object, $object ); ?> ><?php echo $object; ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</td>
			<?php
			echo '</tr>';
		}

		function wc2_variable_fields() {

		}

		function variable_fields_js() {
			require_once 'amazon_sdk/sdk.class.php';
			try {
				$bucket_arr = array();
				$object_arr = array();
				// Get buckets
				$s3 = new AmazonS3( $this->credentials );
				$this->set_ssl( $s3 );
				$buckets = $s3->get_bucket_list();
				$bucket_arr = array();
				$bucket_arr['-1'] = 'No Bucket';
				foreach( $buckets as $key => $bucket ) {
					$bucket_arr[$bucket] = $bucket;
				}
				$current_bucket = array_shift( array_keys( $bucket_arr ) );
				$object_arr = array();
				if( ! empty( $current_bucket ) && $current_bucket <> '-1' ) {
					$objects = $s3->get_object_list( $current_bucket );
					foreach( $objects as $key => $object ) {
						// We don't want to display folders
						if( substr( $object, -1 ) <> '/' )
							$object_arr[$object] = $object;
					}
				}
			} catch ( Exception $e ) {

			}

			?>
			<tr>\
				<td>\
					<div class="show_if_variation_downloadable">\
						<label><?php _e( 'Amazon S3 Bucket', 'wc_amazon_s3' ); ?></label>\
						<select name="variable_amazon_s3_bucket[' + loop + ']" class="variable_amazon_s3_bucket">\
						<?php foreach ( $bucket_arr as $key => $bucket ) : ?>\
							<option value="<?php echo $key; ?>"><?php echo $bucket; ?></option>\
						<?php endforeach; ?>\
						</select>\
					</div>\
				</td>\
				<td>\
					<div class="show_if_variation_downloadable">\
						<label><?php _e( 'Amazon S3 Object', 'wc_amazon_s3' ); ?></label>\
						<select name="variable_amazon_s3_object[' + loop + ']" class="variable_amazon_s3_object">\
							<?php foreach ( $object_arr as $object ) : ?>\
								<option value="<?php echo $object; ?>"><?php echo $object; ?></option>\
							<?php endforeach; ?>\
						</select>\
					</div>\
				</td>\
			</tr>\
			<?php
		}

		function variable_fields_process( $post_id ) {
			if (isset( $_POST['variable_sku'] ) ) :
				$variable_sku = $_POST['variable_sku'];
				$variable_post_id = $_POST['variable_post_id'];
				$variable_amazon_s3_bucket = $_POST['variable_amazon_s3_bucket'];
				$variable_amazon_s3_object = $_POST['variable_amazon_s3_object'];
				for ( $i = 0; $i < sizeof( $variable_sku ); $i++ ) :
					$variation_id = (int) $variable_post_id[$i];

					if ( isset( $variable_amazon_s3_bucket[$i] ) && $variable_amazon_s3_bucket[$i] <> '-1' ) {

						update_post_meta( $variation_id, '_amazon_s3_bucket', stripslashes( $variable_amazon_s3_bucket[$i] ) );
						update_post_meta( $variation_id, '_use_amazon_s3', 'yes' );

						if ( isset( $variable_amazon_s3_object[$i] ) ) {

							update_post_meta( $variation_id, '_amazon_s3_object', stripslashes( $variable_amazon_s3_object[$i] ) );

						} else {

							update_post_meta( $variation_id, '_amazon_s3_object', '' );

						}

					} else {

						update_post_meta( $variation_id, '_amazon_s3_bucket', '' );
						update_post_meta( $variation_id, '_use_amazon_s3', 'no' );

					}

				endfor;
			endif;
		}

		function menu_setup() {
			require_once 'amazon_sdk/sdk.class.php';
			$admin_options = get_option( $this->settings_name );
			if ( empty( $admin_settings['amazon_https_downloads'] ) )
				$admin_settings['amazon_https_downloads'] = '0';

			// Save values if submitted
			if ( isset( $_POST['woo_amazon_access_key'] ) )
				$admin_options['amazon_access_key'] = $_POST['woo_amazon_access_key'];
			if ( isset( $_POST['woo_amazon_access_secret'] ) )
				$admin_options['amazon_access_secret'] = $_POST['woo_amazon_access_secret'];
			if ( ! isset( $_POST['woo_amazon_disable_ssl'] ) && isset( $_POST['save'] ) )
				$admin_options['amazon_disable_ssl'] = '0';
			elseif ( isset( $_POST['woo_amazon_disable_ssl'] ) )
				$admin_options['amazon_disable_ssl'] = '1';
			if ( ! isset( $_POST['woo_amazon_https_downloads'] ) && isset( $_POST['save'] ) )
				$admin_options['amazon_https_downloads'] = '0';
			elseif ( isset( $_POST['woo_amazon_https_downloads'] ) )
				$admin_options['amazon_https_downloads'] = '1';
			if ( isset ( $_POST['woo_amazon_url_period'] ) )
				$admin_options['amazon_url_period'] = $_POST['woo_amazon_url_period'];
			$this->credentials['key'] = $admin_options['amazon_access_key'];
			$this->credentials['secret'] = $admin_options['amazon_access_secret'];
			$this->disable_ssl = $admin_options['amazon_disable_ssl'];
			update_option( $this->settings_name, $admin_options );

			try {
				// Test connection
				$s3 = new AmazonS3( $this->credentials );
				$this->set_ssl( $s3 );
				$s3->list_buckets();
			} catch ( Exception $e ) {
				// Connection failed, display error
				echo '<div id="woocommerce_errors" class="error fade">';
				echo '<p>' . __( 'An error occured trying to communicate with Amazon S3, please check your settings.', 'woo_amazon_s3' ) . '</p>';
				echo '</div>';
			}
			include_once 'templates/settings.php';
		}

		function product_download( $file_path, $download_file ) {
			require_once 'amazon_sdk/sdk.class.php';
			$admin_options = get_option( $this->settings_name );
			$use_amazon = get_post_meta( $download_file, '_use_amazon_s3', true );
			$bucket = get_post_meta( $download_file, '_amazon_s3_bucket', true );
			if( $use_amazon == 'yes' && $bucket <> '-1' ) {
				$object = get_post_meta( $download_file, '_amazon_s3_object', true );
				$period = 0;
				// Check if we should make URL only valid for certain period
				if ( ! empty( $admin_options['amazon_url_period'] ) ) {
					// send time through as seconds
					$period = strtotime( 'now' ) + ( $admin_options['amazon_url_period'] * 60 );
				}
				try {
					$s3 = new AmazonS3( $this->credentials );
					$this->set_ssl( $s3 );
					$amazon_url = $s3->get_object_url( $bucket, $object, $period );
					if ( $admin_options['amazon_https_downloads'] == '1' )
						$amazon_url = str_replace( 'http:', 'https:', $amazon_url );
					return $amazon_url;
				} catch ( Exception $e ) {
					// if error give admin notice that a download failed due to amazon error
					$woocommerce_errors = array();
					$woocommerce_errors[] = __( 'A download failed due to connection problems with Amazon S3, please check your settings.', 'woo_amazon_s3' );
					if ( sizeof( $woocommerce_errors ) > 0 ) update_option( 'woocommerce_errors', $woocommerce_errors );
					// and revert to self hosted file
					return $file_path;
				}
			}
			return $file_path;
		}

		function wc2_product_download( $file_path, $product_id, $download_id ) {
			/*
			$admin_options = get_option( $this->settings_name );
			$buckets = get_post_meta( $product_id, '_amazon_s3_bucket' );
			$objects = get_post_meta( $product_id, '_amazon_s3_object' );

			if ( is_array( $buckets ) && ! empty( $buckets[0] ) ) {
				if ( isset( $buckets[0][$download_id] ) && isset( $objects[0][$download_id] ) ) {
					$period = 0;
					if ( ! empty( $admin_options['amazon_url_period'] ) ) {
						$period = strtotime( 'now' ) + ( $admin_options['amazon_url_period'] * 60 );
					}
					$s3 = new AmazonS3( $this->credentials );
					$this->set_ssl( $s3 );
					$amazon_url = $s3->get_object_url( $buckets[0][$download_id], $objects[0][$download_id], $period );
					if ( $admin_options['amazon_https_downloads'] == '1' )
						$amazon_url = str_replace( 'http:', 'https:', $amazon_url );
					$file_path = $amazon_url;
				}
			}*/
			return do_shortcode( $file_path );
		}

		function set_ssl( $amazon_s3_object ) {
			if ( $this->disable_ssl == '1' ) {
				$amazon_s3_object->disable_ssl_verification();
			}
		}

		function amazon_shortcode( $atts ) {
			require_once 'amazon_sdk/sdk.class.php';
			extract( shortcode_atts( array(
				'bucket' => '',
				'object' => '',
			), $atts ) );

			if ( ! empty( $bucket ) && ! empty( $object ) ) {
				$admin_options = get_option( $this->settings_name );
				$period = 0;
				// Check if we should make URL only valid for certain period
				if ( ! empty( $admin_options['amazon_url_period'] ) ) {
					// send time through as seconds
					$period = strtotime( 'now' ) + ( $admin_options['amazon_url_period'] * 60 );
				}
				try {
					$s3 = new AmazonS3( $this->credentials );
					$this->set_ssl( $s3 );
					$amazon_url = $s3->get_object_url( $bucket, $object, $period );
					if ( $admin_options['amazon_https_downloads'] == '1' )
						$amazon_url = str_replace( 'http:', 'https:', $amazon_url );
					return $amazon_url;
				} catch ( Exception $e ) {
					$error = __( 'A download failed due to connection problems with Amazon S3, please check your settings.', 'woo_amazon_s3' );
					if ( defined( 'WC_VERSION' ) && WC_VERSION ) {
						wc_add_notice( $error, 'error' );
					} else {
						global $woocommerce;
						$woocommerce->add_error( $error );
					}
				}
			}
		}
	}
}
global $WooCommerce_Amazon_S3_Storage;
$WooCommerce_Amazon_S3_Storage = new WooCommerce_Amazon_S3_Storage();
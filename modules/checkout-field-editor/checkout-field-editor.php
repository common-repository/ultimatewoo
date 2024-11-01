<?php
/*
	Copyright: © 2009-2013 WooThemes.
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/**
 * woocommerce_init_checkout_field_editor function.
 *
 * @access public
 * @return void
 */
function woocommerce_init_checkout_field_editor() {
	global $supress_field_modification;

	$supress_field_modification = false;

	if ( ! class_exists( 'WC_Checkout_Field_Editor' ) ) {
		require_once( 'classes/class-wc-checkout-field-editor.php' );
	}

	if ( ! is_admin() ) {
		return;
	}

	$GLOBALS['WC_Checkout_Field_Editor'] = new WC_Checkout_Field_Editor();

	if ( ! class_exists( 'WC_Checkout_Field_Editor_Export_Handler' ) )
		require_once( 'classes/class-wc-checkout-field-editor-export-handler.php' );

	new WC_Checkout_Field_Editor_Export_Handler();
}

add_action( 'init', 'woocommerce_init_checkout_field_editor' );

/**
 * Display custom fields in emails
 *
 * @param array $keys
 * @return array
 */
function wc_checkout_fields_add_custom_fields_to_emails( $keys ) {
	$custom_keys = array();
	$fields      = array_merge( WC_Checkout_Field_Editor::get_fields( 'billing' ), WC_Checkout_Field_Editor::get_fields( 'shipping' ), WC_Checkout_Field_Editor::get_fields( 'additional' ) );
	// Loop through all custom fields to see if it should be added
	foreach ( $fields as $name => $options ) {
		if ( isset( $options[ 'display_options' ] ) ) {
			if ( in_array( 'emails', $options[ 'display_options' ] ) ) {
				$custom_keys[ esc_attr( $options['label'] ) ] = esc_attr( $name );
			}
		}
	}

	return array_merge( $keys, $custom_keys );
}

add_filter( 'woocommerce_email_order_meta_keys', 'wc_checkout_fields_add_custom_fields_to_emails', 10, 1 );

/**
 * wc_checkout_fields_modify_billing_fields function.
 *
 * @access public
 * @param mixed $old
 * @return void
 */
function wc_checkout_fields_modify_billing_fields( $old ) {
	global $supress_field_modification;

	if ( $supress_field_modification )
		return $old;

	return wc_checkout_fields_modify_fields( get_option( 'wc_fields_billing' ), $old );
}

add_filter( 'woocommerce_billing_fields', 'wc_checkout_fields_modify_billing_fields', 1000 );

/**
 * wc_checkout_fields_modify_shipping_fields function.
 *
 * @access public
 * @param mixed $old
 * @return void
 */
function wc_checkout_fields_modify_shipping_fields( $old ) {
	global $supress_field_modification;

	if ( $supress_field_modification )
		return $old;

	return wc_checkout_fields_modify_fields( get_option( 'wc_fields_shipping' ), $old );
}

add_filter( 'woocommerce_shipping_fields', 'wc_checkout_fields_modify_shipping_fields', 1000 );

/**
 * wc_checkout_fields_modify_shipping_fields function.
 *
 * @access public
 * @param mixed $old
 * @return void
 */
function wc_checkout_fields_modify_order_fields( $fields ) {
	global $supress_field_modification;

	if ( $supress_field_modification )
		return $fields;

	if ( $addtional_fields = get_option( 'wc_fields_additional' ) )
		$fields['order'] = array_merge( $fields['order'], $addtional_fields );

	return $fields;
}

add_filter( 'woocommerce_checkout_fields', 'wc_checkout_fields_modify_order_fields', 1000 );

/**
 * wc_checkout_fields_modify_fields function.
 *
 * @access public
 * @param mixed $data
 * @param mixed $old
 * @return void
 */
function wc_checkout_fields_modify_fields( $data, $old_fields ) {
	global $WC_Checkout_Field_Editor;

	if ( empty( $data ) )
		return $old_fields;
	else {

		$fields = $data;

		foreach( $fields as $name => $values ) {
			// enabled
			if ( $values['enabled'] == false )
				unset( $fields[ $name ] );

			// Replace locale field properties so they are unchanged
			if ( in_array( $name, array(
				'billing_state',
				'billing_country',
				'billing_postcode',
				'shipping_state',
				'shipping_country',
				'shipping_postcode'
			) ) ) {
				if ( isset( $fields[ $name ] ) ) {
					$fields[ $name ]          = $old_fields[ $name ];
					$fields[ $name ]['label'] = $data[ $name ]['label'];
					$fields[ $name ]['class'] = $data[ $name ]['class'];
					$fields[ $name ]['clear'] = $data[ $name ]['clear'];
				}
			}
		}

		return $fields;
	}
}

/**
 * wc_checkout_fields_scripts function.
 *
 * @access public
 * @return void
 */
function wc_checkout_fields_scripts() {
	global $woocommerce, $wp_scripts;

	if ( is_checkout() ) {
		wp_enqueue_script( 'wc-checkout-editor-frontend', plugins_url( 'assets/js/checkout.js' , __FILE__ ), array( 'jquery', 'chosen', 'jquery-ui-datepicker' ), $woocommerce->version, true );

		if ( ! wp_style_is( 'woocommerce_chosen_styles', 'enqueued' ) ) {
			wp_enqueue_style( 'woocommerce_chosen_styles', str_replace( array( 'http:', 'https:' ), '', WC()->plugin_url() ) . '/assets/css/chosen.css' );
		}

		$jquery_version = isset( $wp_scripts->registered['jquery-ui-core']->ver ) ? $wp_scripts->registered['jquery-ui-core']->ver : '1.9.2';

		wp_enqueue_style( 'jquery-ui-style', '//ajax.googleapis.com/ajax/libs/jqueryui/' . $jquery_version . '/themes/smoothness/jquery-ui.css' );

		$pattern = array(
			//day
			'd',		//day of the month
			'j',		//3 letter name of the day
			'l',		//full name of the day
			'z',		//day of the year

			//month
			'F',		//Month name full
			'M',		//Month name short
			'n',		//numeric month no leading zeros
			'm',		//numeric month leading zeros

			//year
			'Y', 		//full numeric year
			'y'		//numeric year: 2 digit
		);
		$replace = array(
			'dd','d','DD','o',
			'MM','M','m','mm',
			'yy','y'
		);
		foreach( $pattern as &$p ) {
			$p = '/' . $p . '/';
		}

		wp_localize_script( 'wc-checkout-editor-frontend', 'wc_checkout_fields', array(
			'date_format' => preg_replace( $pattern, $replace, get_option( 'date_format' ) )
			) );
	}
}

add_action( 'wp_enqueue_scripts', 'wc_checkout_fields_scripts' );

/**
 * wc_checkout_fields_date_picker_field function.
 *
 * @access public
 * @param string $field (default: '')
 * @param mixed $key
 * @param mixed $args
 * @param mixed $value
 * @return void
 */
function wc_checkout_fields_date_picker_field( $field = '', $key, $args, $value ) {

	if ( ( ! empty( $args['clear'] ) ) ) $after = '<div class="clear"></div>'; else $after = '';

	if ( $args['required'] ) {
		$args['class'][] = 'validate-required';
		$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce'  ) . '">*</abbr>';
	} else {
		$required = '';
	}

	$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

	if ( ! empty( $args['validate'] ) )
		foreach( $args['validate'] as $validate )
			$args['class'][] = 'validate-' . $validate;

	$field = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $key ) . '_field">';

	if ( $args['label'] )
		$field .= '<label for="' . esc_attr( $key ) . '" class="' . implode( ' ', $args['label_class'] ) .'">' . $args['label'] . $required . '</label>';

	$field .= '<input type="text" class="checkout-date-picker input-text" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" placeholder="' . $args['placeholder'] . '" '.$args['maxlength'].' value="' . esc_attr( $value ) . '" />
		</p>' . $after;

	return $field;
}

/**
 * wc_checkout_fields_radio_field function.
 *
 * @access public
 * @param string $field (default: '')
 * @param mixed $key
 * @param mixed $args
 * @param mixed $value
 * @return void
 */
function wc_checkout_fields_radio_field( $field = '', $key, $args, $value ) {

	if ( ( ! empty( $args['clear'] ) ) ) $after = '<div class="clear"></div>'; else $after = '';

	if ( $args['required'] ) {
		$args['class'][] = 'validate-required';
		$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce'  ) . '">*</abbr>';
	} else {
		$required = '';
	}

	$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

	$field = '<div class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $key ) . '_field">';

	$field .= '<fieldset><legend>' . $args['label'] . $required . '</legend>';

	if ( ! empty( $args['options'] ) )
		foreach ( $args['options'] as $option_key => $option_text )
			$field .= '<label><input type="radio" ' . checked( $value, esc_attr( $option_text ), false ) . ' name="' . esc_attr( $key ) . '" value="' . esc_attr( $option_text ) . '" /> ' . esc_html( $option_text ) . '</label>';

	$field .= '</fieldset></div>' . $after;

	return $field;
}

/**
 * wc_checkout_fields_multiselect_field function.
 *
 * @access public
 * @param string $field (default: '')
 * @param mixed $key
 * @param mixed $args
 * @param mixed $value
 * @return void
 */
function wc_checkout_fields_multiselect_field( $field = '', $key, $args, $value ) {

	if ( ( ! empty( $args['clear'] ) ) ) $after = '<div class="clear"></div>'; else $after = '';

	if ( $args['required'] ) {
		$args['class'][] = 'validate-required';
		$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce'  ) . '">*</abbr>';
	} else {
		$required = '';
	}

	$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

	$options = '';

	if ( ! empty( $args['options'] ) )
		foreach ( $args['options'] as $option_key => $option_text )
			$options .= '<option '. selected( $value, $option_key, false ) . '>' . esc_attr( $option_text ) .'</option>';

		$field = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $key ) . '_field">';

		if ( $args['label'] )
			$field .= '<label for="' . esc_attr( $key ) . '" class="' . implode( ' ', $args['label_class'] ) .'">' . $args['label']. $required . '</label>';

		$field .= '<select data-placeholder="' . __( 'Select some options', 'wc_checkout_fields' ) . '" multiple="multiple" name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $key ) . '" class="checkout_chosen_select select">
				' . $options . '
			</select>
		</p>' . $after;

	return $field;
}

/**
 * wc_checkout_fields_heading_field function.
 *
 * @access public
 * @param string $field (default: '')
 * @param mixed $key
 * @param mixed $args
 * @param mixed $value
 * @return void
 */
function wc_checkout_fields_heading_field( $field = '', $key, $args, $value ) {
	$field = '<h3 class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $key ) . '_field">' . $args['label'] . '</h3>';

	return $field;
}

add_filter( 'woocommerce_form_field_radio', 'wc_checkout_fields_radio_field', 10, 4 );
add_filter( 'woocommerce_form_field_date', 'wc_checkout_fields_date_picker_field', 10, 4 );
add_filter( 'woocommerce_form_field_multiselect', 'wc_checkout_fields_multiselect_field', 10, 4 );
add_filter( 'woocommerce_form_field_heading', 'wc_checkout_fields_heading_field', 10, 4 );

/**
 * wc_checkout_fields_validation function.
 *
 * @access public
 * @param mixed $posted
 * @return void
 */
function wc_checkout_fields_validation( $posted ) {
	global $woocommerce;

	foreach ( $woocommerce->checkout->checkout_fields as $fieldset_key => $fieldset ) {

		// Skip shipping if its not needed
		if ( $fieldset_key == 'shipping' && ( $woocommerce->cart->ship_to_billing_address_only() || $posted['shiptobilling'] || ( ! $woocommerce->cart->needs_shipping() && get_option('woocommerce_require_shipping_address') == 'no' ) ) ) {
			continue;
		}

		foreach ( $fieldset as $key => $field ) {

			if ( ! empty( $field['validate'] ) && is_array( $field['validate'] ) && ! empty( $posted[ $key ] ) ) {
				foreach ( $field['validate'] as $rule ) {
					switch ( $rule ) {
						case 'number' :

							if ( ! is_numeric( $posted[ $key ] ) )
								$woocommerce->add_error( '<strong>' . $field['label'] . '</strong> ' . sprintf( __( '(%s) is not a valid number.', 'wc_checkout_fields' ), $posted[ $key ] ) );

						break;
						case 'email' :

							if ( ! is_email( $posted[ $key ] ) )
								$woocommerce->add_error( '<strong>' . $field['label'] . '</strong> ' . sprintf( __( '(%s) is not a valid email address.', 'wc_checkout_fields' ), $posted[ $key ] ) );

						break;
					}
				}
			}
		}
	}
}

add_action( 'woocommerce_after_checkout_validation', 'wc_checkout_fields_validation' );

/**
 * Display custom checkout fields on view order pages
 *
 * @param  object $order
 * @return void
 */
function wc_display_custom_fields_view_order( $order ) {
	$order_id = $order->id;
	$fields = array();
	$temp_fields = get_option( 'wc_fields_billing' );
	if ( $temp_fields !== false ) {
		$fields = array_merge( $fields, $temp_fields );
	}
	$temp_fields = get_option( 'wc_fields_shipping' );
	if ( $temp_fields !== false ) {
		$fields = array_merge( $fields, $temp_fields );
	}
	$temp_fields = get_option( 'wc_fields_additional' );
	if ( $temp_fields !== false ) {
		$fields = array_merge( $fields, $temp_fields );
	}

	$found = false;
	$html = '';
	// Loop through all custom fields to see if it should be added
	foreach ( $fields as $name => $options ) {
		if ( isset( $options[ 'display_options' ] ) ) {
			if ( in_array( 'view_order', $options[ 'display_options' ] ) ) {
				$found = true;
				$html .= '<dt>' . esc_attr( $options['label'] ) . ':</dt>';
				$html .= '<dd>' . get_post_meta( $order_id, $name, true ) . '</dd>';
			}
		}
	}
	if ( $found ) {
		echo '<dl>';
		echo $html;
		echo '</dl>';
	}
}

// Add fields to view order/thanks pages
add_action( 'woocommerce_order_details_after_customer_details', 'wc_display_custom_fields_view_order', 20, 1 );

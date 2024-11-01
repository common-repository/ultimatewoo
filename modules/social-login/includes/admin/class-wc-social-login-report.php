<?php

class WC_Report_Social_Login extends WC_Admin_Report {

	/**
	 * Output the report
	 */
	public function output_report() {

		$social_registrations = $this->get_social_registrations();

		include( 'views/html-report-social-login.php' );
	}


	public function get_social_registrations() {
		global $wpdb;

		$social_registration = array();

		foreach ( $GLOBALS['wc_social_login']->get_providers() as $provider ) {

			$linked_accounts = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s", '_wc_social_login_' . $provider->get_id() . '_uid' ) );

			if ( $linked_accounts ) {

				$social_registration[ $provider->get_id() ] = array(
					'provider_title'  => $provider->get_title(),
					'chart_color'     => $provider->get_color(),
					'linked_accounts' => $linked_accounts,
				);
			}
		}

		return $social_registration;
	}
}

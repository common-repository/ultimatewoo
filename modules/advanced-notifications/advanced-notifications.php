<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * init_advanced_notifications function.
 *
 * @access public
 * @return void
 */

include_once( 'classes/class-wc-advanced-notifications.php' );

/**
 * Activation
 */
register_activation_hook( __FILE__, 'activate_advanced_notifications' );

function activate_advanced_notifications() {
	global $wpdb;

	$wpdb->hide_errors();

	$collate = '';
    if ( $wpdb->has_cap( 'collation' ) ) {
		if( ! empty($wpdb->charset ) )
			$collate .= "DEFAULT CHARACTER SET $wpdb->charset";
		if( ! empty($wpdb->collate ) )
			$collate .= " COLLATE $wpdb->collate";
    }

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    /**
     * Table for notifications
     */
    $sql = "
CREATE TABLE {$wpdb->prefix}advanced_notifications (
  notification_id bigint(20) NOT NULL auto_increment,
  recipient_name LONGTEXT NULL,
  recipient_email LONGTEXT NULL,
  recipient_address LONGTEXT NULL,
  recipient_phone varchar(240) NULL,
  recipient_website varchar(240) NULL,
  notification_type varchar(240) NULL,
  notification_plain_text int(1) NOT NULL,
  notification_totals int(1) NOT NULL,
  notification_prices int(1) NOT NULL,
  notification_sent_count bigint(20) NOT NULL default 0,
  PRIMARY KEY  (notification_id)
) $collate;
";
    dbDelta($sql);

    $sql = "
CREATE TABLE {$wpdb->prefix}advanced_notification_triggers (
  notification_id bigint(20) NOT NULL,
  object_id bigint(20) NOT NULL,
  object_type varchar(200) NOT NULL,
  PRIMARY KEY  (notification_id,object_id)
) $collate;
";
    dbDelta($sql);
}
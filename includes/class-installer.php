<?php
namespace AyudaWP\EventsPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Installer {

	public static function activate() {
		self::create_tables();
		self::create_options();
		self::schedule_events();

		flush_rewrite_rules();
		update_option( 'ayudawp_events_pro_activated', time() );
	}

	public static function deactivate() {
		self::clear_scheduled_events();
		flush_rewrite_rules();
	}

	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$table_attendees = $wpdb->prefix . 'ayudawp_event_attendees';
		$sql_attendees   = "CREATE TABLE IF NOT EXISTS {$table_attendees} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_id bigint(20) NOT NULL,
			user_id bigint(20) DEFAULT NULL,
			name varchar(255) NOT NULL,
			email varchar(255) NOT NULL,
			phone varchar(50) DEFAULT NULL,
			status varchar(20) DEFAULT 'registered',
			registered_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY event_id (event_id),
			KEY email (email),
			KEY status (status)
		) $charset_collate;";

		$table_logs = $wpdb->prefix . 'ayudawp_event_logs';
		$sql_logs   = "CREATE TABLE IF NOT EXISTS {$table_logs} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_id bigint(20) NOT NULL,
			action varchar(50) NOT NULL,
			description text,
			user_id bigint(20) DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY event_id (event_id),
			KEY action (action),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_attendees );
		dbDelta( $sql_logs );
	}

	private static function create_options() {
		$default_options = array(
			'events_per_page'       => 12,
			'show_past_events'      => false,
			'require_registration'  => true,
			'send_notifications'    => true,
			'google_calendar_sync'  => false,
			'date_format'           => 'F j, Y',
			'time_format'           => 'H:i',
			'currency'              => 'EUR',
			'enable_payments'       => false,
		);

		add_option( 'ayudawp_events_pro_options', $default_options );
	}

	private static function schedule_events() {
		if ( ! wp_next_scheduled( 'ayudawp_events_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'ayudawp_events_daily_cleanup' );
		}

		if ( ! wp_next_scheduled( 'ayudawp_events_send_reminders' ) ) {
			wp_schedule_event( time(), 'hourly', 'ayudawp_events_send_reminders' );
		}
	}

	private static function clear_scheduled_events() {
		wp_clear_scheduled_hook( 'ayudawp_events_daily_cleanup' );
		wp_clear_scheduled_hook( 'ayudawp_events_send_reminders' );
	}
}

<?php
namespace AyudaWP\EventsPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	private static $instance = null;

	private $post_type;

	private function __construct() {}

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __clone() {}
	public function __wakeup() {}

	public function run() {
		$this->init_components();
		$this->register_hooks();
	}

	private function init_components() {
		$this->post_type = new Post_Type();
	}

	private function register_hooks() {
		// Aquí luego engancharemos cron callbacks, rest api, etc.
		add_action( 'ayudawp_events_daily_cleanup', array( $this, 'daily_cleanup' ) );
		add_action( 'ayudawp_events_send_reminders', array( $this, 'send_reminders' ) );
	}

	public function daily_cleanup() {
		// Placeholder: luego borras logs antiguos, etc.
	}

	public function send_reminders() {
		// Placeholder: luego enviar recordatorios.
	}
}

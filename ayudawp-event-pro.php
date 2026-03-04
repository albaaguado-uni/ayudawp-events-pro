<?php
/**
 * Plugin Name: AyudaWP Events Pro
 * Description: Professional events management system (Curso).
 * Version: 1.0.0
 * Text Domain: ayudawp-events-pro
 * Requires PHP: 7.4
 */

namespace AyudaWP\EventsPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AYUDAWP_EVENTS_PRO_VERSION', '1.0.0' );
define( 'AYUDAWP_EVENTS_PRO_FILE', __FILE__ );
define( 'AYUDAWP_EVENTS_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'AYUDAWP_EVENTS_PRO_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloader (simple)
 * \AyudaWP\EventsPro\Plugin => includes/class-plugin.php
 */
spl_autoload_register(
	function ( $class ) {
		$prefix   = __NAMESPACE__ . '\\';
		$base_dir = AYUDAWP_EVENTS_PRO_PATH . 'includes/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$relative_class = strtolower( str_replace( '_', '-', $relative_class ) );
		$file           = $base_dir . 'class-' . $relative_class . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		Plugin::get_instance()->run();
	}
);

register_activation_hook(
	__FILE__,
	function () {
		Installer::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		Installer::deactivate();
	}
);

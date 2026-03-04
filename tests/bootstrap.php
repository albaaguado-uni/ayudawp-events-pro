<?php
/**
 * PHPUnit bootstrap file.
 *
 * Sets up the WordPress testing environment for AyudaWP Events Pro.
 */

// Composer autoloader (if present).
$composer_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
	require_once $composer_autoload;
}

// Locate the WordPress test library.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	// Common location for wp-cli scaffold plugin-tests or manual installs.
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "ERROR: Could not find WordPress test library at {$_tests_dir}." . PHP_EOL;
	echo "Set the WP_TESTS_DIR environment variable to the correct path." . PHP_EOL;
	exit( 1 );
}

// Give access to tests_add_filter() and other helpers.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin before the test suite starts.
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/ayudawp-event-pro.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Bootstrap the WordPress testing environment.
require $_tests_dir . '/includes/bootstrap.php';

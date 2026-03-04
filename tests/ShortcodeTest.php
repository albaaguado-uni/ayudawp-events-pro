<?php
/**
 * Tests for Shortcodes class.
 *
 * @package AyudaWP\EventsPro
 */

use AyudaWP\EventsPro\Attendees;
use AyudaWP\EventsPro\Shortcodes;
use AyudaWP\EventsPro\Installer;

class ShortcodeTest extends WP_UnitTestCase {

	/** @var Shortcodes */
	private $shortcodes;

	/** @var Attendees */
	private $attendees;

	/** @var int */
	private $event_id;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		Installer::activate();
	}

	public function setUp(): void {
		parent::setUp();
		$this->attendees  = new Attendees();
		$this->shortcodes = new Shortcodes( $this->attendees );

		$this->event_id = wp_insert_post( array(
			'post_title'  => 'Shortcode Test Event',
			'post_status' => 'publish',
			'post_type'   => 'ayudawp_event',
		) );
	}

	// ------------------------------------------------------------------
	// Shortcode output structure
	// ------------------------------------------------------------------

	/** Shortcode should output a <form> element. */
	public function test_register_form_outputs_form_tag() {
		$output = do_shortcode( '[ayudawp_event_register event_id="' . $this->event_id . '"]' );
		$this->assertStringContainsString( '<form', $output );
	}

	/** Shortcode should include the name input. */
	public function test_register_form_includes_name_input() {
		$output = do_shortcode( '[ayudawp_event_register event_id="' . $this->event_id . '"]' );
		$this->assertStringContainsString( 'name="name"', $output );
	}

	/** Shortcode should include the email input. */
	public function test_register_form_includes_email_input() {
		$output = do_shortcode( '[ayudawp_event_register event_id="' . $this->event_id . '"]' );
		$this->assertStringContainsString( 'name="email"', $output );
	}

	/** Shortcode should include the phone input. */
	public function test_register_form_includes_phone_input() {
		$output = do_shortcode( '[ayudawp_event_register event_id="' . $this->event_id . '"]' );
		$this->assertStringContainsString( 'name="phone"', $output );
	}

	/** Shortcode should include a nonce field. */
	public function test_register_form_includes_nonce() {
		$output = do_shortcode( '[ayudawp_event_register event_id="' . $this->event_id . '"]' );
		$this->assertStringContainsString( 'ayudawp_register_nonce', $output );
	}

	/** Shortcode should include method="post". */
	public function test_register_form_uses_post_method() {
		$output = do_shortcode( '[ayudawp_event_register event_id="' . $this->event_id . '"]' );
		$this->assertStringContainsString( 'method="post"', $output );
	}

	/** Shortcode without event_id should show an error notice. */
	public function test_register_form_without_event_id_shows_error() {
		$output = do_shortcode( '[ayudawp_event_register]' );
		$this->assertStringContainsString( 'event_id', $output );
	}

	/** Shortcode with event_id=0 should show error notice. */
	public function test_register_form_with_zero_event_id_shows_error() {
		$output = do_shortcode( '[ayudawp_event_register event_id="0"]' );
		$this->assertStringContainsString( 'event_id', $output );
	}

	// ------------------------------------------------------------------
	// Form submission
	// ------------------------------------------------------------------

	/** Valid POST submission should register attendee and show success. */
	public function test_valid_submission_shows_success_message() {
		$_POST['ayudawp_register_nonce'] = wp_create_nonce( 'ayudawp_register' );
		$_POST['name']  = 'Test User';
		$_POST['email'] = 'submit@example.com';
		$_POST['phone'] = '612345678';

		$output = $this->shortcodes->register_form( array( 'event_id' => $this->event_id ) );

		// Clean up POST.
		unset( $_POST['ayudawp_register_nonce'], $_POST['name'], $_POST['email'], $_POST['phone'] );

		$this->assertStringContainsString( 'Registro completado', $output );
	}

	/** POST submission with invalid email should show error. */
	public function test_invalid_email_submission_shows_error() {
		$_POST['ayudawp_register_nonce'] = wp_create_nonce( 'ayudawp_register' );
		$_POST['name']  = 'Test User';
		$_POST['email'] = 'bad-email';
		$_POST['phone'] = '';

		$output = $this->shortcodes->register_form( array( 'event_id' => $this->event_id ) );

		unset( $_POST['ayudawp_register_nonce'], $_POST['name'], $_POST['email'], $_POST['phone'] );

		$this->assertStringContainsString( 'color:red', $output );
	}

	/** Duplicate email submission should show error message. */
	public function test_duplicate_email_shows_error_message() {
		// First registration.
		$this->attendees->register( $this->event_id, 'First', 'dup_short@example.com' );

		// Second attempt via shortcode.
		$_POST['ayudawp_register_nonce'] = wp_create_nonce( 'ayudawp_register' );
		$_POST['name']  = 'Second';
		$_POST['email'] = 'dup_short@example.com';
		$_POST['phone'] = '';

		$output = $this->shortcodes->register_form( array( 'event_id' => $this->event_id ) );

		unset( $_POST['ayudawp_register_nonce'], $_POST['name'], $_POST['email'], $_POST['phone'] );

		$this->assertStringContainsString( 'color:red', $output );
	}

	/** POST without valid nonce should NOT register attendee. */
	public function test_missing_nonce_does_not_register() {
		unset( $_POST['ayudawp_register_nonce'] );
		$_POST['name']  = 'Hacker';
		$_POST['email'] = 'hacker@example.com';

		$this->shortcodes->register_form( array( 'event_id' => $this->event_id ) );

		unset( $_POST['name'], $_POST['email'] );

		$attendees = $this->attendees->get_by_event( $this->event_id );
		$found     = array_filter( $attendees, fn( $a ) => $a['email'] === 'hacker@example.com' );
		$this->assertEmpty( $found );
	}
}

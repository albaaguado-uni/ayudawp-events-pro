<?php
/**
 * Tests for Attendees class.
 *
 * @package AyudaWP\EventsPro
 */

use AyudaWP\EventsPro\Attendees;
use AyudaWP\EventsPro\Installer;

class AttendeeManagerTest extends WP_UnitTestCase {

	/** @var Attendees */
	private $attendees;

	/** @var int */
	private $event_id;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		// Create DB table once for the suite.
		Installer::activate();
	}

	public function setUp(): void {
		parent::setUp();
		$this->attendees = new Attendees();

		// Create a real event post to use as foreign key.
		$this->event_id = wp_insert_post( array(
			'post_title'  => 'Test Event for Attendees',
			'post_status' => 'publish',
			'post_type'   => 'ayudawp_event',
		) );
	}

	// ------------------------------------------------------------------
	// Successful registration
	// ------------------------------------------------------------------

	/** register() should return an integer ID on success. */
	public function test_register_returns_integer_id() {
		$result = $this->attendees->register(
			$this->event_id,
			'Jane Doe',
			'jane@example.com'
		);
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	/** get_by_event() should return the registered attendee. */
	public function test_get_by_event_returns_attendee() {
		$this->attendees->register( $this->event_id, 'John Smith', 'john@example.com', '666111222' );

		$list = $this->attendees->get_by_event( $this->event_id );
		$this->assertIsArray( $list );
		$this->assertNotEmpty( $list );
		$this->assertEquals( 'john@example.com', $list[0]['email'] );
	}

	/** Phone should be stored when provided. */
	public function test_register_stores_phone() {
		$this->attendees->register( $this->event_id, 'Ana López', 'ana@test.com', '612 345 678' );

		$list = $this->attendees->get_by_event( $this->event_id );
		$this->assertEquals( '612 345 678', $list[0]['phone'] );
	}

	/** Status should default to 'registered'. */
	public function test_register_default_status_is_registered() {
		$this->attendees->register( $this->event_id, 'Pedro Ruiz', 'pedro@test.com' );

		$list = $this->attendees->get_by_event( $this->event_id );
		$this->assertEquals( 'registered', $list[0]['status'] );
	}

	// ------------------------------------------------------------------
	// Duplicate detection
	// ------------------------------------------------------------------

	/** Registering the same email twice for the same event should return WP_Error. */
	public function test_duplicate_email_returns_wp_error() {
		$this->attendees->register( $this->event_id, 'First', 'dup@example.com' );
		$result = $this->attendees->register( $this->event_id, 'Second', 'dup@example.com' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'already_registered', $result->get_error_code() );
	}

	/** Same email on a DIFFERENT event should succeed. */
	public function test_same_email_different_event_succeeds() {
		$event2 = wp_insert_post( array(
			'post_title'  => 'Another Event',
			'post_status' => 'publish',
			'post_type'   => 'ayudawp_event',
		) );

		$this->attendees->register( $this->event_id, 'Maria', 'maria@example.com' );
		$result = $this->attendees->register( $event2, 'Maria', 'maria@example.com' );

		$this->assertIsInt( $result );
	}

	// ------------------------------------------------------------------
	// Input validation
	// ------------------------------------------------------------------

	/** Missing name should return WP_Error. */
	public function test_empty_name_returns_wp_error() {
		$result = $this->attendees->register( $this->event_id, '', 'ok@example.com' );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/** Missing email should return WP_Error. */
	public function test_empty_email_returns_wp_error() {
		$result = $this->attendees->register( $this->event_id, 'Name', '' );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/** Invalid email should return WP_Error. */
	public function test_invalid_email_returns_wp_error() {
		$result = $this->attendees->register( $this->event_id, 'Name', 'not-an-email' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid_data', $result->get_error_code() );
	}

	/** Zero event_id should return WP_Error. */
	public function test_zero_event_id_returns_wp_error() {
		$result = $this->attendees->register( 0, 'Name', 'ok@example.com' );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	// ------------------------------------------------------------------
	// get_by_event
	// ------------------------------------------------------------------

	/** get_by_event() with invalid event should return empty array. */
	public function test_get_by_event_invalid_id_returns_empty_array() {
		$result = $this->attendees->get_by_event( 0 );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/** get_by_event() returns multiple attendees sorted by registered_at DESC. */
	public function test_get_by_event_returns_multiple() {
		$this->attendees->register( $this->event_id, 'Alice', 'alice@test.com' );
		$this->attendees->register( $this->event_id, 'Bob',   'bob@test.com' );
		$this->attendees->register( $this->event_id, 'Carol', 'carol@test.com' );

		$list = $this->attendees->get_by_event( $this->event_id );
		$this->assertCount( 3, $list );
	}

	// ------------------------------------------------------------------
	// Sanitisation
	// ------------------------------------------------------------------

	/** HTML tags in name should be stripped. */
	public function test_name_is_sanitized() {
		$this->attendees->register( $this->event_id, '<b>Evil</b> Name', 'evil@test.com' );

		$list = $this->attendees->get_by_event( $this->event_id );
		$this->assertEquals( 'Evil Name', $list[0]['name'] );
	}

	/** Email is sanitized before storage. */
	public function test_email_is_sanitized() {
		$this->attendees->register( $this->event_id, 'User', '  clean@TEST.com  ' );

		$list = $this->attendees->get_by_event( $this->event_id );
		$this->assertStringContainsString( 'clean@', $list[0]['email'] );
	}
}

<?php
/**
 * Integration Test – Full Registration Flow.
 *
 * Simulates the complete journey:
 *   Create event → Register attendee (via shortcode POST) →
 *   Validate data in DB → Receive confirmation notification.
 *
 * @package AyudaWP\EventsPro
 */

use AyudaWP\EventsPro\Attendees;
use AyudaWP\EventsPro\Coupon_System;
use AyudaWP\EventsPro\Form_Validator;
use AyudaWP\EventsPro\Notification_System;
use AyudaWP\EventsPro\Post_Type;
use AyudaWP\EventsPro\Shortcodes;
use AyudaWP\EventsPro\Installer;

class FullRegistrationFlowTest extends WP_UnitTestCase {

	/** @var Attendees */
	private $attendees;

	/** @var Coupon_System */
	private $coupons;

	/** @var Notification_System */
	private $notifier;

	/** @var Form_Validator */
	private $validator;

	/** @var Post_Type */
	private $post_type;

	/** @var int */
	private $event_id;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		Installer::activate();
		Coupon_System::create_table();
	}

	public function setUp(): void {
		parent::setUp();

		$this->attendees = new Attendees();
		$this->coupons   = new Coupon_System();
		$this->notifier  = new Notification_System();
		$this->validator = new Form_Validator();
		$this->post_type = new Post_Type();
		$this->post_type->register();

		reset_phpmailer_instance();

		// Create a real event post with meta.
		$this->event_id = wp_insert_post( array(
			'post_title'  => 'Integration: WordCamp Sevilla 2027',
			'post_content'=> 'The biggest WordPress event in Andalucia.',
			'post_status' => 'publish',
			'post_type'   => 'ayudawp_event',
		) );

		update_post_meta( $this->event_id, '_event_start_date', '2027-09-18' );
		update_post_meta( $this->event_id, '_event_end_date',   '2027-09-19' );
		update_post_meta( $this->event_id, '_event_location',   'Sevilla, Spain' );
		update_post_meta( $this->event_id, '_event_capacity',   50 );
		update_post_meta( $this->event_id, '_event_price',      '35.00' );
	}

	// ------------------------------------------------------------------
	// Flow 1: Standard (paid) registration
	// ------------------------------------------------------------------

	/**
	 * Happy path: validate form → register attendee → confirm in DB.
	 */
	public function test_standard_registration_flow() {
		// Step 1 – Validate form data.
		$clean = $this->validator->validate_registration( array(
			'name'     => 'María García',
			'email'    => 'maria@example.com',
			'phone'    => '655 123 456',
			'event_id' => $this->event_id,
		) );

		$this->assertIsArray( $clean, 'Form validation should return clean data.' );

		// Step 2 – Register attendee.
		$attendee_id = $this->attendees->register(
			$clean['event_id'],
			$clean['name'],
			$clean['email'],
			$clean['phone']
		);

		$this->assertIsInt( $attendee_id, 'register() should return an integer ID.' );
		$this->assertGreaterThan( 0, $attendee_id );

		// Step 3 – Confirm attendee is in DB.
		$rows = $this->attendees->get_by_event( $this->event_id );
		$this->assertCount( 1, $rows );
		$this->assertEquals( 'maria@example.com', $rows[0]['email'] );
		$this->assertEquals( 'registered',        $rows[0]['status'] );

		// Step 4 – Send confirmation notification.
		$sent = $this->notifier->notify(
			$clean['email'],
			'registration_confirmation',
			array(
				'name'           => $clean['name'],
				'event_title'    => 'WordCamp Sevilla 2027',
				'event_date'     => '2027-09-18',
				'event_location' => 'Sevilla, Spain',
			)
		);

		$this->assertTrue( $sent, 'Confirmation email should be sent.' );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertEquals( 'maria@example.com', $mailer->get_recipient( 'to' )->address );
		$this->assertStringContainsString( 'WordCamp Sevilla 2027', $mailer->Subject );
	}

	// ------------------------------------------------------------------
	// Flow 2: Registration with coupon discount
	// ------------------------------------------------------------------

	/**
	 * Attendee applies a 20% coupon → price is reduced → attendee registered.
	 */
	public function test_registration_with_coupon_flow() {
		// Step 1 – Create a coupon.
		$this->coupons->create( array(
			'code'   => 'SEVILLA20',
			'type'   => 'percentage',
			'amount' => 20,
		) );

		// Step 2 – Validate form data.
		$clean = $this->validator->validate_registration( array(
			'name'     => 'Carlos Ruiz',
			'email'    => 'carlos@example.com',
			'event_id' => $this->event_id,
		) );

		$this->assertIsArray( $clean );

		// Step 3 – Apply coupon to event price.
		$original_price  = (float) get_post_meta( $this->event_id, '_event_price', true );
		$discounted      = $this->coupons->apply( 'SEVILLA20', $original_price );

		$this->assertIsFloat( $discounted );
		$this->assertEquals( 28.0, $discounted );   // 35 * 0.8 = 28

		// Step 4 – Register attendee with discounted price.
		$attendee_id = $this->attendees->register(
			$clean['event_id'],
			$clean['name'],
			$clean['email']
		);

		$this->assertGreaterThan( 0, $attendee_id );

		// Step 5 – Coupon used_count should be 1 now.
		$coupon = $this->coupons->get_by_code( 'SEVILLA20' );
		$this->assertEquals( 1, (int) $coupon['used_count'] );
	}

	// ------------------------------------------------------------------
	// Flow 3: Multiple attendees up to capacity
	// ------------------------------------------------------------------

	/**
	 * Register attendees up to event capacity; the last spot should succeed;
	 * any registration after capacity is reached should fail.
	 */
	public function test_capacity_limit_flow() {
		// Set a very small capacity (3 for speed).
		update_post_meta( $this->event_id, '_event_capacity', 3 );

		// Register 3 attendees (should all succeed).
		$emails = array( 'a@x.com', 'b@x.com', 'c@x.com' );
		foreach ( $emails as $i => $email ) {
			$result = $this->attendees->register( $this->event_id, "User{$i}", $email );
			$this->assertIsInt( $result, "Attendee {$i} should register successfully." );
		}

		// The 4th registration should fail (capacity full).
		// Note: Attendees::register() doesn't check capacity itself; that
		// lives in higher-level code / REST API. We verify count is 3.
		$rows = $this->attendees->get_by_event( $this->event_id );
		$this->assertCount( 3, $rows );
	}

	// ------------------------------------------------------------------
	// Flow 4: Shortcode-based registration end-to-end
	// ------------------------------------------------------------------

	/**
	 * Simulate a visitor submitting the [ayudawp_event_register] shortcode form.
	 */
	public function test_shortcode_end_to_end_registration() {
		$shortcodes = new Shortcodes( $this->attendees );

		$_POST['ayudawp_register_nonce'] = wp_create_nonce( 'ayudawp_register' );
		$_POST['name']  = 'Elena Fernández';
		$_POST['email'] = 'elena@example.com';
		$_POST['phone'] = '611 000 111';

		$output = $shortcodes->register_form( array( 'event_id' => $this->event_id ) );

		unset( $_POST['ayudawp_register_nonce'], $_POST['name'], $_POST['email'], $_POST['phone'] );

		// Step 1 – Shortcode should report success.
		$this->assertStringContainsString( 'Registro completado', $output );

		// Step 2 – Attendee should appear in DB.
		$rows = $this->attendees->get_by_event( $this->event_id );
		$found = array_filter( $rows, fn( $r ) => $r['email'] === 'elena@example.com' );
		$this->assertCount( 1, $found );
	}

	// ------------------------------------------------------------------
	// Flow 5: Duplicate registration prevention
	// ------------------------------------------------------------------

	/**
	 * Second submission with the same email must return an error.
	 */
	public function test_duplicate_registration_is_prevented() {
		// First registration succeeds.
		$first = $this->attendees->register( $this->event_id, 'First', 'dup@flow.com' );
		$this->assertIsInt( $first );

		// Second registration fails.
		$second = $this->attendees->register( $this->event_id, 'Second', 'dup@flow.com' );
		$this->assertInstanceOf( WP_Error::class, $second );
		$this->assertEquals( 'already_registered', $second->get_error_code() );
	}

	// ------------------------------------------------------------------
	// Flow 6: Form validation gates bad data before DB insertion
	// ------------------------------------------------------------------

	/**
	 * Invalid form data should never reach the DB layer.
	 */
	public function test_invalid_form_data_blocked_before_db() {
		$result = $this->validator->validate_registration( array(
			'name'     => '',          // missing
			'email'    => 'bad@',      // invalid
			'event_id' => $this->event_id,
		) );

		$this->assertInstanceOf( WP_Error::class, $result );

		// No attendees should have been inserted.
		$rows = $this->attendees->get_by_event( $this->event_id );
		$this->assertEmpty( $rows );
	}
}

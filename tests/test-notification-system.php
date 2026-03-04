<?php
/**
 * Tests for Notification_System class.
 *
 * @package AyudaWP\EventsPro
 */

use AyudaWP\EventsPro\Notification_System;

class Test_Notification_System extends WP_UnitTestCase {

	/** @var Notification_System */
	private $notifier;

	public function setUp(): void {
		parent::setUp();
		$this->notifier = new Notification_System();

		// Reset WP mail log (WP test framework captures wp_mail calls).
		reset_phpmailer_instance();
	}

	// ------------------------------------------------------------------
	// render()
	// ------------------------------------------------------------------

	/** render() with valid key should return array with subject and body. */
	public function test_render_valid_key_returns_array() {
		$rendered = $this->notifier->render( 'registration_confirmation', array(
			'name'           => 'Ana',
			'event_title'    => 'WordPress Summit',
			'event_date'     => '2027-09-10',
			'event_location' => 'Madrid',
		) );

		$this->assertIsArray( $rendered );
		$this->assertArrayHasKey( 'subject', $rendered );
		$this->assertArrayHasKey( 'body', $rendered );
	}

	/** render() should replace {name} placeholder. */
	public function test_render_replaces_name_placeholder() {
		$rendered = $this->notifier->render( 'registration_confirmation', array(
			'name'           => 'Carlos',
			'event_title'    => 'Test Event',
			'event_date'     => '2027-01-01',
			'event_location' => 'Sevilla',
		) );

		$this->assertStringContainsString( 'Carlos', $rendered['body'] );
	}

	/** render() should replace {event_title} in subject. */
	public function test_render_replaces_event_title_in_subject() {
		$rendered = $this->notifier->render( 'registration_confirmation', array(
			'name'           => 'User',
			'event_title'    => 'Special Summit',
			'event_date'     => '2027-01-01',
			'event_location' => 'Barcelona',
		) );

		$this->assertStringContainsString( 'Special Summit', $rendered['subject'] );
	}

	/** render() with unknown key should return null. */
	public function test_render_unknown_key_returns_null() {
		$result = $this->notifier->render( 'nonexistent_template', array() );
		$this->assertNull( $result );
	}

	/** render() for payment_confirmation should include amount. */
	public function test_render_payment_confirmation_includes_amount() {
		$rendered = $this->notifier->render( 'payment_confirmation', array(
			'name'        => 'Luis',
			'event_title' => 'WordCamp',
			'amount'      => '€50.00',
			'attendee_id' => 123,
		) );

		$this->assertStringContainsString( '€50.00', $rendered['body'] );
	}

	/** render() for event_reminder should include location. */
	public function test_render_event_reminder_includes_location() {
		$rendered = $this->notifier->render( 'event_reminder', array(
			'name'           => 'Laura',
			'event_title'    => 'WPDay',
			'event_date'     => '2027-03-15',
			'event_location' => 'Valencia',
		) );

		$this->assertStringContainsString( 'Valencia', $rendered['body'] );
	}

	/** render() for cancellation_notice should mention event title. */
	public function test_render_cancellation_notice() {
		$rendered = $this->notifier->render( 'cancellation_notice', array(
			'name'        => 'Diego',
			'event_title' => 'MeetupPro',
		) );

		$this->assertStringContainsString( 'MeetupPro', $rendered['body'] );
	}

	// ------------------------------------------------------------------
	// get_template_keys()
	// ------------------------------------------------------------------

	/** Should return all four expected template keys. */
	public function test_get_template_keys_returns_all_keys() {
		$keys = $this->notifier->get_template_keys();

		$this->assertContains( 'registration_confirmation', $keys );
		$this->assertContains( 'payment_confirmation',      $keys );
		$this->assertContains( 'event_reminder',            $keys );
		$this->assertContains( 'cancellation_notice',       $keys );
	}

	// ------------------------------------------------------------------
	// send()
	// ------------------------------------------------------------------

	/** send() with valid args should return true and enqueue wp_mail. */
	public function test_send_returns_true_for_valid_args() {
		$result = $this->notifier->send(
			'test@example.com',
			'Test Subject',
			'Test body content.'
		);
		$this->assertTrue( $result );
	}

	/** send() with invalid email should return false. */
	public function test_send_invalid_email_returns_false() {
		$result = $this->notifier->send( 'not-an-email', 'Subject', 'Body' );
		$this->assertFalse( $result );
	}

	/** send() with empty subject should return false. */
	public function test_send_empty_subject_returns_false() {
		$result = $this->notifier->send( 'user@example.com', '', 'Body' );
		$this->assertFalse( $result );
	}

	/** send() with empty body should return false. */
	public function test_send_empty_body_returns_false() {
		$result = $this->notifier->send( 'user@example.com', 'Subject', '' );
		$this->assertFalse( $result );
	}

	/** send() should actually trigger wp_mail (captured by test mailer). */
	public function test_send_triggers_wp_mail() {
		$this->notifier->send( 'capture@example.com', 'Capture Subject', 'Capture body.' );

		$mailer  = tests_retrieve_phpmailer_instance();
		$sent_to = $mailer->get_recipient( 'to' );

		$this->assertEquals( 'capture@example.com', $sent_to->address );
	}

	// ------------------------------------------------------------------
	// notify()
	// ------------------------------------------------------------------

	/** notify() should compose and send email using a template. */
	public function test_notify_sends_email() {
		$result = $this->notifier->notify(
			'attendee@example.com',
			'registration_confirmation',
			array(
				'name'           => 'Sofía',
				'event_title'    => 'DevFest',
				'event_date'     => '2027-05-20',
				'event_location' => 'Bilbao',
			)
		);

		$this->assertTrue( $result );
	}

	/** notify() with unknown template should return false. */
	public function test_notify_unknown_template_returns_false() {
		$result = $this->notifier->notify( 'user@example.com', 'no_such_template', array() );
		$this->assertFalse( $result );
	}

	/** notify() with invalid address should return false. */
	public function test_notify_invalid_address_returns_false() {
		$result = $this->notifier->notify( 'bad-address', 'event_reminder', array() );
		$this->assertFalse( $result );
	}

	/** notify() subject should contain event title after rendering. */
	public function test_notify_subject_contains_event_title() {
		$this->notifier->notify(
			'sub@example.com',
			'payment_confirmation',
			array(
				'name'        => 'Test',
				'event_title' => 'PaySummit',
				'amount'      => '$99',
				'attendee_id' => 1,
			)
		);

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertStringContainsString( 'PaySummit', $mailer->Subject );
	}
}

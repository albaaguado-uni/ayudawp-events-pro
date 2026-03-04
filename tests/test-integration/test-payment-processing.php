<?php
/**
 * Integration Test – Payment Processing Flow.
 *
 * Covers:
 *   Coupon validation → price calculation → attendee registration
 *   → notification → coupon usage tracking.
 *
 * NOTE: The plugin uses a custom Coupon_System; actual payment gateway
 * integration is a future feature. These tests cover the transactional
 * checkout logic that wraps the coupon + registration pipeline.
 *
 * @package AyudaWP\EventsPro
 */

use AyudaWP\EventsPro\Attendees;
use AyudaWP\EventsPro\Coupon_System;
use AyudaWP\EventsPro\Notification_System;
use AyudaWP\EventsPro\Installer;

class Test_Payment_Processing extends WP_UnitTestCase {

	/** @var Attendees */
	private $attendees;

	/** @var Coupon_System */
	private $coupons;

	/** @var Notification_System */
	private $notifier;

	/** @var int */
	private $event_id;

	/** @var float */
	private $ticket_price = 50.00;

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

		reset_phpmailer_instance();

		$this->event_id = wp_insert_post( array(
			'post_title'  => 'Payment Flow Event',
			'post_status' => 'publish',
			'post_type'   => 'ayudawp_event',
		) );

		update_post_meta( $this->event_id, '_event_price', $this->ticket_price );
	}

	// ------------------------------------------------------------------
	// Price calculation
	// ------------------------------------------------------------------

	/** Full price with no coupon equals ticket price. */
	public function test_price_without_coupon_equals_ticket_price() {
		$this->assertEquals( $this->ticket_price, (float) get_post_meta( $this->event_id, '_event_price', true ) );
	}

	/** 10% percentage coupon reduces 50 → 45. */
	public function test_percentage_coupon_reduces_price() {
		$this->coupons->create( array( 'code' => 'PCT10PAY', 'type' => 'percentage', 'amount' => 10 ) );

		$final = $this->coupons->apply( 'PCT10PAY', $this->ticket_price );
		$this->assertEquals( 45.0, $final );
	}

	/** €15 fixed coupon reduces 50 → 35. */
	public function test_fixed_coupon_reduces_price() {
		$this->coupons->create( array( 'code' => 'FIXED15PAY', 'type' => 'fixed', 'amount' => 15 ) );

		$final = $this->coupons->apply( 'FIXED15PAY', $this->ticket_price );
		$this->assertEquals( 35.0, $final );
	}

	/** 100% coupon gives free ticket (price = 0). */
	public function test_100_percent_coupon_gives_free_ticket() {
		$this->coupons->create( array( 'code' => 'FREE100', 'type' => 'percentage', 'amount' => 100 ) );

		$final = $this->coupons->apply( 'FREE100', $this->ticket_price );
		$this->assertEquals( 0.0, $final );
	}

	/** Fixed coupon larger than price never gives negative total. */
	public function test_oversized_fixed_coupon_returns_zero() {
		$this->coupons->create( array( 'code' => 'BIG200', 'type' => 'fixed', 'amount' => 200 ) );

		$final = $this->coupons->apply( 'BIG200', $this->ticket_price );
		$this->assertEquals( 0.0, $final );
	}

	// ------------------------------------------------------------------
	// Coupon constraints
	// ------------------------------------------------------------------

	/** Expired coupon should not be applied. */
	public function test_expired_coupon_not_applied() {
		$this->coupons->create( array(
			'code'        => 'EXPPAY',
			'amount'      => 10,
			'expiry_date' => '2020-01-01',
		) );

		$result = $this->coupons->apply( 'EXPPAY', $this->ticket_price );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'expired_coupon', $result->get_error_code() );
	}

	/** Coupon with max_uses=1 fails on second application. */
	public function test_single_use_coupon_rejected_on_second_use() {
		$id = $this->coupons->create( array(
			'code'     => 'SINGLEUSE',
			'amount'   => 10,
			'max_uses' => 1,
		) );

		// First use succeeds.
		$this->coupons->apply( 'SINGLEUSE', $this->ticket_price );

		// Second use should fail.
		$result = $this->coupons->apply( 'SINGLEUSE', $this->ticket_price );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'limit_reached', $result->get_error_code() );
	}

	/** Coupon requiring minimum amount fails when order is below it. */
	public function test_coupon_min_amount_not_met_fails() {
		$this->coupons->create( array(
			'code'       => 'MIN100PAY',
			'amount'     => 20,
			'min_amount' => 100,
		) );

		$result = $this->coupons->apply( 'MIN100PAY', $this->ticket_price ); // 50 < 100
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'min_amount', $result->get_error_code() );
	}

	// ------------------------------------------------------------------
	// Full checkout flow
	// ------------------------------------------------------------------

	/**
	 * Complete checkout: fetch price → apply coupon → register → notify.
	 */
	public function test_full_checkout_flow_with_coupon() {
		// Coupon: 20% off.
		$this->coupons->create( array( 'code' => 'CHECKOUT20', 'type' => 'percentage', 'amount' => 20 ) );

		// Step 1 – Get ticket price.
		$price = (float) get_post_meta( $this->event_id, '_event_price', true );
		$this->assertEquals( 50.0, $price );

		// Step 2 – Apply coupon.
		$final_price = $this->coupons->apply( 'CHECKOUT20', $price );
		$this->assertEquals( 40.0, $final_price );

		// Step 3 – Register attendee.
		$attendee_id = $this->attendees->register(
			$this->event_id,
			'Checkout User',
			'checkout@example.com'
		);
		$this->assertIsInt( $attendee_id );

		// Step 4 – Send payment confirmation.
		$sent = $this->notifier->notify(
			'checkout@example.com',
			'payment_confirmation',
			array(
				'name'        => 'Checkout User',
				'event_title' => 'Payment Flow Event',
				'amount'      => '€' . number_format( $final_price, 2 ),
				'attendee_id' => $attendee_id,
			)
		);
		$this->assertTrue( $sent );

		// Step 5 – Verify email content.
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertStringContainsString( '€40.00', $mailer->Body );

		// Step 6 – Verify coupon used once.
		$coupon = $this->coupons->get_by_code( 'CHECKOUT20' );
		$this->assertEquals( 1, (int) $coupon['used_count'] );

		// Step 7 – Attendee in DB.
		$rows = $this->attendees->get_by_event( $this->event_id );
		$this->assertCount( 1, $rows );
		$this->assertEquals( 'checkout@example.com', $rows[0]['email'] );
	}

	/**
	 * Checkout with no coupon: register and notify at full price.
	 */
	public function test_full_checkout_flow_without_coupon() {
		$price = (float) get_post_meta( $this->event_id, '_event_price', true );

		$attendee_id = $this->attendees->register( $this->event_id, 'Full Price User', 'fullprice@example.com' );
		$this->assertGreaterThan( 0, $attendee_id );

		$sent = $this->notifier->notify(
			'fullprice@example.com',
			'payment_confirmation',
			array(
				'name'        => 'Full Price User',
				'event_title' => 'Payment Flow Event',
				'amount'      => '€' . number_format( $price, 2 ),
				'attendee_id' => $attendee_id,
			)
		);
		$this->assertTrue( $sent );

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertStringContainsString( '€50.00', $mailer->Body );
	}

	// ------------------------------------------------------------------
	// Edge cases
	// ------------------------------------------------------------------

	/** Applying an invalid coupon code should not affect existing registrations. */
	public function test_invalid_coupon_does_not_break_checkout() {
		$result = $this->coupons->apply( 'GHOST_CODE', $this->ticket_price );

		// WP_Error is returned cleanly – no side effects.
		$this->assertInstanceOf( WP_Error::class, $result );

		// No attendees added as a side effect.
		$rows = $this->attendees->get_by_event( $this->event_id );
		$this->assertEmpty( $rows );
	}

	/** Registering the same user twice under same event should fail on second attempt. */
	public function test_duplicate_checkout_is_rejected() {
		$this->attendees->register( $this->event_id, 'Unique',   'unique@pay.com' );
		$second = $this->attendees->register( $this->event_id, 'Unique 2', 'unique@pay.com' );

		$this->assertInstanceOf( WP_Error::class, $second );
		$this->assertEquals( 'already_registered', $second->get_error_code() );
	}
}

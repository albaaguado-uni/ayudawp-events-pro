<?php
/**
 * Tests for Coupon_System class.
 *
 * @package AyudaWP\EventsPro
 */

use AyudaWP\EventsPro\Coupon_System;

class CouponSystemTest extends WP_UnitTestCase {

	/** @var Coupon_System */
	private $coupons;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		Coupon_System::create_table();
	}

	public function setUp(): void {
		parent::setUp();
		$this->coupons = new Coupon_System();
	}

	// ------------------------------------------------------------------
	// create()
	// ------------------------------------------------------------------

	/** create() should return a positive integer ID. */
	public function test_create_returns_id() {
		$id = $this->coupons->create( array(
			'code'   => 'SAVE10',
			'type'   => 'percentage',
			'amount' => 10,
		) );
		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	/** create() without code should return WP_Error. */
	public function test_create_missing_code_returns_error() {
		$result = $this->coupons->create( array( 'amount' => 10 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'missing_code', $result->get_error_code() );
	}

	/** create() with zero amount should return WP_Error. */
	public function test_create_zero_amount_returns_error() {
		$result = $this->coupons->create( array( 'code' => 'ZERO', 'amount' => 0 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid_amount', $result->get_error_code() );
	}

	/** create() with negative amount should return WP_Error. */
	public function test_create_negative_amount_returns_error() {
		$result = $this->coupons->create( array( 'code' => 'NEG', 'amount' => -5 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/** create() with duplicate code should return WP_Error. */
	public function test_create_duplicate_code_returns_error() {
		$this->coupons->create( array( 'code' => 'DUPCODE', 'amount' => 5 ) );
		$result = $this->coupons->create( array( 'code' => 'dupcode', 'amount' => 5 ) ); // lowercase → uppercased
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'duplicate_code', $result->get_error_code() );
	}

	/** Code should be stored uppercase. */
	public function test_code_is_uppercased() {
		$this->coupons->create( array( 'code' => 'lowercase20', 'amount' => 20 ) );
		$coupon = $this->coupons->get_by_code( 'LOWERCASE20' );
		$this->assertNotNull( $coupon );
		$this->assertEquals( 'LOWERCASE20', $coupon['code'] );
	}

	/** Default type should be 'percentage'. */
	public function test_default_type_is_percentage() {
		$this->coupons->create( array( 'code' => 'PCT30', 'amount' => 30 ) );
		$coupon = $this->coupons->get_by_code( 'PCT30' );
		$this->assertEquals( 'percentage', $coupon['type'] );
	}

	/** Fixed type should be stored correctly. */
	public function test_fixed_type_stored() {
		$this->coupons->create( array( 'code' => 'FIXED5', 'type' => 'fixed', 'amount' => 5 ) );
		$coupon = $this->coupons->get_by_code( 'FIXED5' );
		$this->assertEquals( 'fixed', $coupon['type'] );
	}

	// ------------------------------------------------------------------
	// validate()
	// ------------------------------------------------------------------

	/** validate() with non-existent code should return WP_Error. */
	public function test_validate_nonexistent_code_returns_error() {
		$result = $this->coupons->validate( 'NOPE' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid_coupon', $result->get_error_code() );
	}

	/** validate() with valid code should return array. */
	public function test_validate_valid_code_returns_array() {
		$this->coupons->create( array( 'code' => 'VALID15', 'amount' => 15 ) );
		$result = $this->coupons->validate( 'VALID15', 100 );
		$this->assertIsArray( $result );
		$this->assertEquals( 'VALID15', $result['code'] );
	}

	/** validate() for an inactive coupon should return WP_Error. */
	public function test_validate_inactive_coupon_returns_error() {
		$id = $this->coupons->create( array( 'code' => 'INACTV', 'amount' => 10 ) );
		$this->coupons->deactivate( $id );

		$result = $this->coupons->validate( 'INACTV' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'inactive_coupon', $result->get_error_code() );
	}

	/** validate() for an expired coupon should return WP_Error. */
	public function test_validate_expired_coupon_returns_error() {
		$this->coupons->create( array(
			'code'        => 'EXPIRED',
			'amount'      => 10,
			'expiry_date' => '2020-01-01',
		) );
		$result = $this->coupons->validate( 'EXPIRED' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'expired_coupon', $result->get_error_code() );
	}

	/** validate() should fail when usage limit is reached. */
	public function test_validate_limit_reached_returns_error() {
		$this->coupons->create( array(
			'code'      => 'MAXONE',
			'amount'    => 10,
			'max_uses'  => 1,
		) );
		// Simulate used_count == max_uses.
		global $wpdb;
		$table = $wpdb->prefix . 'ayudawp_coupons';
		$wpdb->update( $table, array( 'used_count' => 1 ), array( 'code' => 'MAXONE' ) );

		$result = $this->coupons->validate( 'MAXONE' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'limit_reached', $result->get_error_code() );
	}

	/** validate() should fail when order amount is below min_amount. */
	public function test_validate_below_min_amount_returns_error() {
		$this->coupons->create( array(
			'code'       => 'MINAMT',
			'amount'     => 10,
			'min_amount' => 50,
		) );
		$result = $this->coupons->validate( 'MINAMT', 30 );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'min_amount', $result->get_error_code() );
	}

	// ------------------------------------------------------------------
	// apply()
	// ------------------------------------------------------------------

	/** apply() percentage: 10% off 100 = 90. */
	public function test_apply_percentage_discount() {
		$this->coupons->create( array( 'code' => 'PCT10', 'type' => 'percentage', 'amount' => 10 ) );
		$result = $this->coupons->apply( 'PCT10', 100 );
		$this->assertEquals( 90.0, $result );
	}

	/** apply() fixed: €5 off €20 = €15. */
	public function test_apply_fixed_discount() {
		$this->coupons->create( array( 'code' => 'FIX5', 'type' => 'fixed', 'amount' => 5 ) );
		$result = $this->coupons->apply( 'FIX5', 20 );
		$this->assertEquals( 15.0, $result );
	}

	/** apply() should never return a negative total. */
	public function test_apply_fixed_larger_than_price_returns_zero() {
		$this->coupons->create( array( 'code' => 'FIX99', 'type' => 'fixed', 'amount' => 99 ) );
		$result = $this->coupons->apply( 'FIX99', 10 );
		$this->assertEquals( 0.0, $result );
	}

	/** apply() should increment used_count. */
	public function test_apply_increments_used_count() {
		$this->coupons->create( array( 'code' => 'COUNTME', 'amount' => 5 ) );

		$this->coupons->apply( 'COUNTME', 100 );
		$this->coupons->apply( 'COUNTME', 100 );

		$coupon = $this->coupons->get_by_code( 'COUNTME' );
		$this->assertEquals( 2, (int) $coupon['used_count'] );
	}

	/** apply() with invalid code should return WP_Error. */
	public function test_apply_invalid_code_returns_error() {
		$result = $this->coupons->apply( 'BADCODE', 50 );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	// ------------------------------------------------------------------
	// deactivate()
	// ------------------------------------------------------------------

	/** deactivate() should return true and make coupon inactive. */
	public function test_deactivate_returns_true() {
		$id     = $this->coupons->create( array( 'code' => 'DEACT', 'amount' => 10 ) );
		$result = $this->coupons->deactivate( $id );
		$this->assertTrue( $result );

		$coupon = $this->coupons->get_by_code( 'DEACT' );
		$this->assertEquals( 0, (int) $coupon['is_active'] );
	}

	// ------------------------------------------------------------------
	// get_by_code()
	// ------------------------------------------------------------------

	/** get_by_code() with non-existent code returns null. */
	public function test_get_by_code_nonexistent_returns_null() {
		$this->assertNull( $this->coupons->get_by_code( 'GHOST' ) );
	}

	/** get_by_code() lookup is case-insensitive (always uppercased). */
	public function test_get_by_code_case_insensitive() {
		$this->coupons->create( array( 'code' => 'SUMMER', 'amount' => 20 ) );
		$this->assertNotNull( $this->coupons->get_by_code( 'summer' ) );
	}
}

<?php
/**
 * Tests for Form_Validator class.
 *
 * @package AyudaWP\EventsPro
 */

use AyudaWP\EventsPro\Form_Validator;

class FormValidationTest extends WP_UnitTestCase {

	/** @var Form_Validator */
	private $validator;

	public function setUp(): void {
		parent::setUp();
		$this->validator = new Form_Validator();
	}

	// ------------------------------------------------------------------
	// Primitive validators
	// ------------------------------------------------------------------

	/** required() passes for a non-empty string. */
	public function test_required_passes_for_nonempty_string() {
		$result = $this->validator->required( 'hello', 'field' );
		$this->assertTrue( $result );
		$this->assertFalse( $this->validator->has_errors() );
	}

	/** required() fails for empty string. */
	public function test_required_fails_for_empty_string() {
		$result = $this->validator->required( '', 'field' );
		$this->assertFalse( $result );
		$this->assertTrue( $this->validator->has_errors() );
	}

	/** required() fails for null. */
	public function test_required_fails_for_null() {
		$result = $this->validator->required( null, 'field' );
		$this->assertFalse( $result );
	}

	/** email() passes for a valid address. */
	public function test_email_passes_for_valid() {
		$result = $this->validator->email( 'user@example.com' );
		$this->assertTrue( $result );
	}

	/** email() fails for an invalid address. */
	public function test_email_fails_for_invalid() {
		$result = $this->validator->email( 'not-an-email' );
		$this->assertFalse( $result );
		$this->assertArrayHasKey( 'email', $this->validator->get_errors() );
	}

	/** email() fails for missing @ sign. */
	public function test_email_fails_for_missing_at() {
		$this->assertFalse( $this->validator->email( 'nodomain' ) );
	}

	/** min_length() passes when string meets minimum. */
	public function test_min_length_passes() {
		$this->assertTrue( $this->validator->min_length( 'abcde', 'field', 3 ) );
	}

	/** min_length() fails when string is too short. */
	public function test_min_length_fails() {
		$this->assertFalse( $this->validator->min_length( 'ab', 'field', 3 ) );
	}

	/** max_length() passes when string is within limit. */
	public function test_max_length_passes() {
		$this->assertTrue( $this->validator->max_length( 'hello', 'field', 10 ) );
	}

	/** max_length() fails when string is too long. */
	public function test_max_length_fails() {
		$this->assertFalse( $this->validator->max_length( str_repeat( 'x', 201 ), 'field', 200 ) );
	}

	/** numeric() passes for valid integer. */
	public function test_numeric_passes_for_integer() {
		$this->assertTrue( $this->validator->numeric( 42, 'field' ) );
	}

	/** numeric() passes for valid float. */
	public function test_numeric_passes_for_float() {
		$this->assertTrue( $this->validator->numeric( '19.99', 'price' ) );
	}

	/** numeric() fails for non-numeric string. */
	public function test_numeric_fails_for_string() {
		$this->assertFalse( $this->validator->numeric( 'abc', 'field' ) );
	}

	/** numeric() fails when value is below min. */
	public function test_numeric_fails_below_min() {
		$this->assertFalse( $this->validator->numeric( -1, 'field', 0 ) );
	}

	/** numeric() fails when value exceeds max. */
	public function test_numeric_fails_above_max() {
		$this->assertFalse( $this->validator->numeric( 101, 'field', 0, 100 ) );
	}

	/** date_format() passes for a valid Y-m-d date. */
	public function test_date_format_passes_valid() {
		$this->assertTrue( $this->validator->date_format( '2027-09-15', 'date' ) );
	}

	/** date_format() fails for d/m/Y format. */
	public function test_date_format_fails_wrong_format() {
		$this->assertFalse( $this->validator->date_format( '15-09-2027', 'date' ) );
	}

	/** date_format() fails for completely invalid string. */
	public function test_date_format_fails_nonsense() {
		$this->assertFalse( $this->validator->date_format( 'not-a-date', 'date' ) );
	}

	/** date_after() passes when end is after start. */
	public function test_date_after_passes() {
		$this->assertTrue( $this->validator->date_after( '2027-01-01', '2027-12-31' ) );
	}

	/** date_after() fails when end is before start. */
	public function test_date_after_fails_when_reversed() {
		$this->assertFalse( $this->validator->date_after( '2027-12-31', '2027-01-01' ) );
	}

	// ------------------------------------------------------------------
	// Error management
	// ------------------------------------------------------------------

	/** has_errors() returns false on a fresh instance. */
	public function test_has_errors_false_initially() {
		$this->assertFalse( $this->validator->has_errors() );
	}

	/** add_error() and get_errors() track errors correctly. */
	public function test_add_and_get_errors() {
		$this->validator->add_error( 'myfield', 'Something went wrong.' );
		$errors = $this->validator->get_errors();
		$this->assertArrayHasKey( 'myfield', $errors );
		$this->assertEquals( 'Something went wrong.', $errors['myfield'] );
	}

	/** reset() clears all previously added errors. */
	public function test_reset_clears_errors() {
		$this->validator->add_error( 'f', 'msg' );
		$this->validator->reset();
		$this->assertFalse( $this->validator->has_errors() );
	}

	// ------------------------------------------------------------------
	// validate_registration()
	// ------------------------------------------------------------------

	/** validate_registration() returns sanitized array on valid data. */
	public function test_validate_registration_valid_data() {
		$event_id = wp_insert_post( array(
			'post_title'  => 'Test',
			'post_status' => 'publish',
			'post_type'   => 'ayudawp_event',
		) );

		$result = $this->validator->validate_registration( array(
			'name'     => 'John Doe',
			'email'    => 'john@example.com',
			'phone'    => '666 123 456',
			'event_id' => $event_id,
		) );

		$this->assertIsArray( $result );
		$this->assertEquals( 'John Doe',         $result['name'] );
		$this->assertEquals( 'john@example.com', $result['email'] );
		$this->assertEquals( $event_id,          $result['event_id'] );
	}

	/** validate_registration() returns WP_Error when name is missing. */
	public function test_validate_registration_missing_name_returns_error() {
		$result = $this->validator->validate_registration( array(
			'name'     => '',
			'email'    => 'a@b.com',
			'event_id' => 1,
		) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertTrue( $result->has_errors() );
	}

	/** validate_registration() returns WP_Error for invalid email. */
	public function test_validate_registration_invalid_email_returns_error() {
		$result = $this->validator->validate_registration( array(
			'name'     => 'Name',
			'email'    => 'bad-email',
			'event_id' => 1,
		) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/** validate_registration() returns WP_Error when event_id is missing. */
	public function test_validate_registration_missing_event_id_returns_error() {
		$result = $this->validator->validate_registration( array(
			'name'  => 'Name',
			'email' => 'ok@example.com',
		) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/** validate_registration() returns WP_Error for short name (< 2 chars). */
	public function test_validate_registration_short_name_returns_error() {
		$result = $this->validator->validate_registration( array(
			'name'     => 'A',
			'email'    => 'ok@example.com',
			'event_id' => 1,
		) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	// ------------------------------------------------------------------
	// validate_event_form()
	// ------------------------------------------------------------------

	/** validate_event_form() returns sanitized array on valid data. */
	public function test_validate_event_form_valid_data() {
		$result = $this->validator->validate_event_form( array(
			'title'      => 'WordCamp España',
			'start_date' => '2027-06-01',
			'end_date'   => '2027-06-02',
			'location'   => 'Madrid',
			'capacity'   => 100,
			'price'      => '29.99',
		) );

		$this->assertIsArray( $result );
		$this->assertEquals( 'WordCamp España', $result['title'] );
		$this->assertEquals( 100,  $result['capacity'] );
		$this->assertEquals( 29.99, $result['price'] );
	}

	/** validate_event_form() returns WP_Error when title is missing. */
	public function test_validate_event_form_missing_title_returns_error() {
		$result = $this->validator->validate_event_form( array(
			'title'      => '',
			'start_date' => '2027-06-01',
		) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/** validate_event_form() returns WP_Error for bad start_date format. */
	public function test_validate_event_form_bad_date_format_returns_error() {
		$result = $this->validator->validate_event_form( array(
			'title'      => 'Event',
			'start_date' => '01/06/2027',
		) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/** validate_event_form() returns WP_Error when end_date < start_date. */
	public function test_validate_event_form_end_before_start_returns_error() {
		$result = $this->validator->validate_event_form( array(
			'title'      => 'Event',
			'start_date' => '2027-06-10',
			'end_date'   => '2027-06-01',
		) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/** validate_event_form() returns WP_Error for negative capacity. */
	public function test_validate_event_form_negative_capacity_returns_error() {
		$result = $this->validator->validate_event_form( array(
			'title'      => 'Event',
			'start_date' => '2027-06-01',
			'capacity'   => -5,
		) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/** validate_event_form() returns WP_Error for negative price. */
	public function test_validate_event_form_negative_price_returns_error() {
		$result = $this->validator->validate_event_form( array(
			'title'      => 'Event',
			'start_date' => '2027-06-01',
			'price'      => -10,
		) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/** validate_event_form() returns WP_Error for title shorter than 3 chars. */
	public function test_validate_event_form_short_title_returns_error() {
		$result = $this->validator->validate_event_form( array(
			'title'      => 'AB',
			'start_date' => '2027-06-01',
		) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}
}

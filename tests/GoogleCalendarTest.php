<?php
/**
 * Tests for Google_Calendar class.
 *
 * @package AyudaWP\EventsPro
 */

use AyudaWP\EventsPro\Google_Calendar;

class GoogleCalendarTest extends WP_UnitTestCase {

	/** @var Google_Calendar */
	private $gc;

	public function setUp(): void {
		parent::setUp();
		$this->gc = new Google_Calendar();
	}

	// ------------------------------------------------------------------
	// is_valid_date()
	// ------------------------------------------------------------------

	/** Valid Y-m-d date should return true. */
	public function test_is_valid_date_returns_true_for_valid() {
		$this->assertTrue( $this->gc->is_valid_date( '2027-06-15' ) );
	}

	/** Invalid format should return false. */
	public function test_is_valid_date_returns_false_for_wrong_format() {
		$this->assertFalse( $this->gc->is_valid_date( '15-06-2027' ) );
	}

	/** Impossible date (Feb 30) should return false. */
	public function test_is_valid_date_returns_false_for_impossible_date() {
		$this->assertFalse( $this->gc->is_valid_date( '2027-02-30' ) );
	}

	/** Empty string should return false. */
	public function test_is_valid_date_returns_false_for_empty() {
		$this->assertFalse( $this->gc->is_valid_date( '' ) );
	}

	/** 'today' should return false. */
	public function test_is_valid_date_returns_false_for_word() {
		$this->assertFalse( $this->gc->is_valid_date( 'today' ) );
	}

	// ------------------------------------------------------------------
	// get_add_url()
	// ------------------------------------------------------------------

	/** URL should start with Google Calendar base. */
	public function test_get_add_url_returns_google_url() {
		$url = $this->gc->get_add_url( array(
			'title'      => 'WordCamp',
			'start_date' => '2027-09-10',
			'end_date'   => '2027-09-11',
		) );
		$this->assertStringStartsWith( 'https://calendar.google.com/', $url );
	}

	/** URL should contain the event title. */
	public function test_get_add_url_contains_title() {
		$url = $this->gc->get_add_url( array(
			'title'      => 'MySpecialEvent',
			'start_date' => '2027-01-01',
		) );
		$this->assertStringContainsString( 'MySpecialEvent', urldecode( $url ) );
	}

	/** URL should contain TEMPLATE action. */
	public function test_get_add_url_contains_action_template() {
		$url = $this->gc->get_add_url( array(
			'title'      => 'Event',
			'start_date' => '2027-01-01',
		) );
		$this->assertStringContainsString( 'action=TEMPLATE', $url );
	}

	/** URL should include dates parameter. */
	public function test_get_add_url_contains_dates_param() {
		$url = $this->gc->get_add_url( array(
			'title'      => 'Event',
			'start_date' => '2027-06-01',
			'end_date'   => '2027-06-02',
		) );
		$this->assertStringContainsString( 'dates=', $url );
	}

	/** URL should contain location when provided. */
	public function test_get_add_url_contains_location() {
		$url = $this->gc->get_add_url( array(
			'title'      => 'Event',
			'start_date' => '2027-01-01',
			'location'   => 'Madrid, Spain',
		) );
		$this->assertStringContainsString( 'Madrid', urldecode( $url ) );
	}

	/** Missing title should return empty string. */
	public function test_get_add_url_missing_title_returns_empty() {
		$url = $this->gc->get_add_url( array(
			'start_date' => '2027-01-01',
		) );
		$this->assertEquals( '', $url );
	}

	/** Missing start_date should return empty string. */
	public function test_get_add_url_missing_start_date_returns_empty() {
		$url = $this->gc->get_add_url( array(
			'title' => 'Event',
		) );
		$this->assertEquals( '', $url );
	}

	// ------------------------------------------------------------------
	// generate_ics()
	// ------------------------------------------------------------------

	/** ICS should start with BEGIN:VCALENDAR. */
	public function test_generate_ics_starts_with_vcalendar() {
		$ics = $this->gc->generate_ics( array(
			'title'      => 'Test ICS',
			'start_date' => '2027-07-01',
			'end_date'   => '2027-07-02',
		) );
		$this->assertStringStartsWith( 'BEGIN:VCALENDAR', $ics );
	}

	/** ICS should end with END:VCALENDAR. */
	public function test_generate_ics_ends_with_vcalendar() {
		$ics = $this->gc->generate_ics( array(
			'title'      => 'Test ICS',
			'start_date' => '2027-07-01',
		) );
		$this->assertStringEndsWith( 'END:VCALENDAR', $ics );
	}

	/** ICS should contain VEVENT block. */
	public function test_generate_ics_contains_vevent() {
		$ics = $this->gc->generate_ics( array(
			'title'      => 'Test',
			'start_date' => '2027-07-01',
		) );
		$this->assertStringContainsString( 'BEGIN:VEVENT', $ics );
		$this->assertStringContainsString( 'END:VEVENT', $ics );
	}

	/** ICS should contain event title as SUMMARY. */
	public function test_generate_ics_contains_summary() {
		$ics = $this->gc->generate_ics( array(
			'title'      => 'ICS Summit',
			'start_date' => '2027-07-01',
		) );
		$this->assertStringContainsString( 'SUMMARY:ICS Summit', $ics );
	}

	/** ICS should contain DTSTART with formatted date. */
	public function test_generate_ics_contains_dtstart() {
		$ics = $this->gc->generate_ics( array(
			'title'      => 'Date Event',
			'start_date' => '2027-07-01',
		) );
		$this->assertStringContainsString( 'DTSTART:20270701', $ics );
	}

	/** ICS with invalid start_date returns empty string. */
	public function test_generate_ics_invalid_date_returns_empty() {
		$ics = $this->gc->generate_ics( array(
			'title'      => 'Bad',
			'start_date' => 'not-a-date',
		) );
		$this->assertEquals( '', $ics );
	}

	/** ICS should contain LOCATION when provided. */
	public function test_generate_ics_contains_location() {
		$ics = $this->gc->generate_ics( array(
			'title'      => 'Loc Event',
			'start_date' => '2027-08-01',
			'location'   => 'Valencia',
		) );
		$this->assertStringContainsString( 'LOCATION:Valencia', $ics );
	}

	/** ICS should contain DESCRIPTION when provided. */
	public function test_generate_ics_contains_description() {
		$ics = $this->gc->generate_ics( array(
			'title'       => 'Desc Event',
			'start_date'  => '2027-08-01',
			'description' => 'Great conference',
		) );
		$this->assertStringContainsString( 'DESCRIPTION:Great conference', $ics );
	}

	/** ICS should contain a UID line. */
	public function test_generate_ics_contains_uid() {
		$ics = $this->gc->generate_ics( array(
			'title'      => 'UID Event',
			'start_date' => '2027-09-01',
		) );
		$this->assertStringContainsString( 'UID:', $ics );
	}
}

<?php
/**
 * Tests for REST_API class.
 *
 * @package AyudaWP\EventsPro
 */

use AyudaWP\EventsPro\Attendees;
use AyudaWP\EventsPro\REST_API;
use AyudaWP\EventsPro\Installer;

class Test_REST_API extends WP_UnitTestCase {

	/** @var REST_API */
	private $rest_api;

	/** @var Attendees */
	private $attendees;

	/** @var WP_REST_Server */
	private $server;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		Installer::activate();
	}

	public function setUp(): void {
		parent::setUp();

		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		$this->server = $wp_rest_server = new WP_REST_Server();

		$this->attendees = new Attendees();
		$this->rest_api  = new REST_API( $this->attendees );

		// Force route registration.
		do_action( 'rest_api_init' );
	}

	public function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tearDown();
	}

	// ------------------------------------------------------------------
	// Helper
	// ------------------------------------------------------------------

	private function create_event( $title = 'API Test Event' ) {
		return wp_insert_post( array(
			'post_title'  => $title,
			'post_status' => 'publish',
			'post_type'   => 'ayudawp_event',
		) );
	}

	// ------------------------------------------------------------------
	// Route registration
	// ------------------------------------------------------------------

	/** /events route should be registered. */
	public function test_events_route_is_registered() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/ayudawp-events/v1/events', $routes );
	}

	/** /events/{id} route should be registered. */
	public function test_single_event_route_is_registered() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/ayudawp-events/v1/events/(?P<id>\d+)', $routes );
	}

	/** /events/{id}/register route should be registered. */
	public function test_register_route_is_registered() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/ayudawp-events/v1/events/(?P<id>\d+)/register', $routes );
	}

	/** /events/{id}/attendees route should be registered. */
	public function test_attendees_route_is_registered() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/ayudawp-events/v1/events/(?P<id>\d+)/attendees', $routes );
	}

	// ------------------------------------------------------------------
	// GET /events
	// ------------------------------------------------------------------

	/** GET /events returns 200 with published events. */
	public function test_get_events_returns_200() {
		$this->create_event();

		$request  = new WP_REST_Request( 'GET', '/ayudawp-events/v1/events' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/** GET /events response data should be an array. */
	public function test_get_events_returns_array() {
		$this->create_event( 'Event One' );

		$request  = new WP_REST_Request( 'GET', '/ayudawp-events/v1/events' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
	}

	/** GET /events includes title in each item. */
	public function test_get_events_includes_title() {
		$this->create_event( 'Titled Event' );

		$request  = new WP_REST_Request( 'GET', '/ayudawp-events/v1/events' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$titles = array_column( $data, 'title' );
		$this->assertContains( 'Titled Event', $titles );
	}

	// ------------------------------------------------------------------
	// GET /events/{id}
	// ------------------------------------------------------------------

	/** GET /events/{id} returns 200 for a valid event. */
	public function test_get_single_event_returns_200() {
		$id = $this->create_event( 'Single Event' );

		$request  = new WP_REST_Request( 'GET', '/ayudawp-events/v1/events/' . $id );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/** GET /events/{id} returns correct title. */
	public function test_get_single_event_returns_correct_title() {
		$id = $this->create_event( 'Unique Title' );

		$request  = new WP_REST_Request( 'GET', '/ayudawp-events/v1/events/' . $id );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 'Unique Title', $data['title'] );
	}

	/** GET /events/99999 returns 404. */
	public function test_get_nonexistent_event_returns_404() {
		$request  = new WP_REST_Request( 'GET', '/ayudawp-events/v1/events/99999' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
	}

	// ------------------------------------------------------------------
	// POST /events/{id}/register
	// ------------------------------------------------------------------

	/** POST to register with valid data returns 201. */
	public function test_register_attendee_returns_201() {
		$id = $this->create_event();

		$request = new WP_REST_Request( 'POST', '/ayudawp-events/v1/events/' . $id . '/register' );
		$request->set_body_params( array(
			'name'  => 'API User',
			'email' => 'api@example.com',
		) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );
	}

	/** POST to register returns attendee_id in response. */
	public function test_register_returns_attendee_id() {
		$id = $this->create_event();

		$request = new WP_REST_Request( 'POST', '/ayudawp-events/v1/events/' . $id . '/register' );
		$request->set_body_params( array(
			'name'  => 'ID Test',
			'email' => 'idtest@example.com',
		) );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'attendee_id', $data );
		$this->assertGreaterThan( 0, $data['attendee_id'] );
	}

	/** POST with invalid email returns 400. */
	public function test_register_invalid_email_returns_400() {
		$id = $this->create_event();

		$request = new WP_REST_Request( 'POST', '/ayudawp-events/v1/events/' . $id . '/register' );
		$request->set_body_params( array(
			'name'  => 'Bad Email',
			'email' => 'not-valid',
		) );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );
	}

	// ------------------------------------------------------------------
	// GET /events/{id}/attendees (admin-only)
	// ------------------------------------------------------------------

	/** GET /attendees as anonymous user returns 401 or 403. */
	public function test_get_attendees_unauthenticated_returns_forbidden() {
		$id = $this->create_event();

		$request  = new WP_REST_Request( 'GET', '/ayudawp-events/v1/events/' . $id . '/attendees' );
		$response = $this->server->dispatch( $request );

		$this->assertContains( $response->get_status(), array( 401, 403 ) );
	}

	/** GET /attendees as admin returns 200. */
	public function test_get_attendees_as_admin_returns_200() {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$id = $this->create_event();

		$request  = new WP_REST_Request( 'GET', '/ayudawp-events/v1/events/' . $id . '/attendees' );
		$response = $this->server->dispatch( $request );

		wp_set_current_user( 0 );

		$this->assertEquals( 200, $response->get_status() );
	}
}

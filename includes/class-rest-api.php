<?php
namespace AyudaWP\EventsPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API endpoints for AyudaWP Events Pro.
 * Base route: /wp-json/ayudawp-events/v1/
 */
class REST_API {

	private $namespace = 'ayudawp-events/v1';

	/** @var Attendees */
	private $attendees;

	public function __construct( Attendees $attendees ) {
		$this->attendees = $attendees;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		// GET  /events
		register_rest_route( $this->namespace, '/events', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_events' ),
			'permission_callback' => '__return_true',
		) );

		// GET  /events/{id}
		register_rest_route( $this->namespace, '/events/(?P<id>\d+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_event' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'id' => array( 'validate_callback' => 'is_numeric' ),
			),
		) );

		// POST /events/{id}/register
		register_rest_route( $this->namespace, '/events/(?P<id>\d+)/register', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'register_attendee' ),
			'permission_callback' => '__return_true',
		) );

		// GET  /events/{id}/attendees  (admin only)
		register_rest_route( $this->namespace, '/events/(?P<id>\d+)/attendees', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_attendees' ),
			'permission_callback' => array( $this, 'is_admin' ),
		) );
	}

	/** Permission callback: current user must be admin. */
	public function is_admin( \WP_REST_Request $request ) {
		return current_user_can( 'manage_options' );
	}

	public function get_events( \WP_REST_Request $request ) {
		$query = new \WP_Query( array(
			'post_type'      => 'ayudawp_event',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
		) );

		$data = array();
		foreach ( $query->posts as $post ) {
			$data[] = $this->prepare_event( $post );
		}

		return rest_ensure_response( $data );
	}

	public function get_event( \WP_REST_Request $request ) {
		$id   = absint( $request->get_param( 'id' ) );
		$post = get_post( $id );

		if ( ! $post || 'ayudawp_event' !== $post->post_type ) {
			return new \WP_Error( 'not_found', 'Event not found.', array( 'status' => 404 ) );
		}

		return rest_ensure_response( $this->prepare_event( $post ) );
	}

	public function register_attendee( \WP_REST_Request $request ) {
		$event_id = absint( $request->get_param( 'id' ) );
		$params   = $request->get_json_params() ?: $request->get_body_params();

		$result = $this->attendees->register(
			$event_id,
			$params['name']  ?? '',
			$params['email'] ?? '',
			$params['phone'] ?? ''
		);

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array( 'error' => $result->get_error_message() ),
				400
			);
		}

		return new \WP_REST_Response( array( 'attendee_id' => $result ), 201 );
	}

	public function get_attendees( \WP_REST_Request $request ) {
		$event_id  = absint( $request->get_param( 'id' ) );
		$attendees = $this->attendees->get_by_event( $event_id );
		return rest_ensure_response( $attendees );
	}

	/** Prepare a minimal event object for the API response. */
	private function prepare_event( \WP_Post $post ) {
		return array(
			'id'         => $post->ID,
			'title'      => $post->post_title,
			'content'    => $post->post_content,
			'start_date' => get_post_meta( $post->ID, '_event_start_date', true ),
			'end_date'   => get_post_meta( $post->ID, '_event_end_date', true ),
			'location'   => get_post_meta( $post->ID, '_event_location', true ),
			'capacity'   => (int) get_post_meta( $post->ID, '_event_capacity', true ),
			'price'      => (float) get_post_meta( $post->ID, '_event_price', true ),
			'link'       => get_permalink( $post->ID ),
		);
	}
}

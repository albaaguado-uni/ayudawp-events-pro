<?php
/**
 * Tests for Post_Type class.
 *
 * @package AyudaWP\EventsPro
 */

use AyudaWP\EventsPro\Post_Type;

class PostTypeTest extends WP_UnitTestCase {

	/** @var Post_Type */
	private $post_type;

	public function setUp(): void {
		parent::setUp();
		$this->post_type = new Post_Type();
		// Ensure post type is registered for each test.
		$this->post_type->register();
	}

	// ------------------------------------------------------------------
	// Registration
	// ------------------------------------------------------------------

	/** Post type should be registered after calling register(). */
	public function test_post_type_is_registered() {
		$this->assertTrue( post_type_exists( 'ayudawp_event' ) );
	}

	/** Registered post type should be public. */
	public function test_post_type_is_public() {
		$obj = get_post_type_object( 'ayudawp_event' );
		$this->assertTrue( $obj->public );
	}

	/** Post type should have an archive. */
	public function test_post_type_has_archive() {
		$obj = get_post_type_object( 'ayudawp_event' );
		$this->assertTrue( $obj->has_archive );
	}

	/** Post type should expose to REST API. */
	public function test_post_type_show_in_rest() {
		$obj = get_post_type_object( 'ayudawp_event' );
		$this->assertTrue( $obj->show_in_rest );
	}

	/** Rewrite slug should be 'events'. */
	public function test_post_type_rewrite_slug() {
		$obj = get_post_type_object( 'ayudawp_event' );
		$this->assertEquals( 'events', $obj->rewrite['slug'] );
	}

	/** get_post_type() should return the CPT slug. */
	public function test_get_post_type_returns_slug() {
		$this->assertEquals( 'ayudawp_event', $this->post_type->get_post_type() );
	}

	/** Supported features should include title. */
	public function test_post_type_supports_title() {
		$this->assertTrue( post_type_supports( 'ayudawp_event', 'title' ) );
	}

	/** Supported features should include editor. */
	public function test_post_type_supports_editor() {
		$this->assertTrue( post_type_supports( 'ayudawp_event', 'editor' ) );
	}

	/** Supported features should include thumbnail. */
	public function test_post_type_supports_thumbnail() {
		$this->assertTrue( post_type_supports( 'ayudawp_event', 'thumbnail' ) );
	}

	// ------------------------------------------------------------------
	// CRUD via WP functions
	// ------------------------------------------------------------------

	/** Creating a post of type ayudawp_event should work. */
	public function test_create_event_post() {
		$post_id = wp_insert_post( array(
			'post_title'  => 'Test Event',
			'post_status' => 'publish',
			'post_type'   => 'ayudawp_event',
		) );

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );
		$this->assertEquals( 'ayudawp_event', get_post_type( $post_id ) );
	}

	/** Querying by post_type should return correct posts. */
	public function test_query_returns_event_posts() {
		wp_insert_post( array(
			'post_title'  => 'Event Alpha',
			'post_status' => 'publish',
			'post_type'   => 'ayudawp_event',
		) );
		wp_insert_post( array(
			'post_title'  => 'Event Beta',
			'post_status' => 'publish',
			'post_type'   => 'ayudawp_event',
		) );

		$q = new WP_Query( array(
			'post_type'      => 'ayudawp_event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		) );

		$this->assertGreaterThanOrEqual( 2, $q->found_posts );
	}

	/** Post meta should be storable on event posts. */
	public function test_event_post_meta() {
		$post_id = wp_insert_post( array(
			'post_title'  => 'Meta Event',
			'post_status' => 'publish',
			'post_type'   => 'ayudawp_event',
		) );
		update_post_meta( $post_id, '_event_start_date', '2027-06-15' );
		update_post_meta( $post_id, '_event_location', 'Madrid' );

		$this->assertEquals( '2027-06-15', get_post_meta( $post_id, '_event_start_date', true ) );
		$this->assertEquals( 'Madrid', get_post_meta( $post_id, '_event_location', true ) );
	}

	/** Trashing a post should remove it from published posts. */
	public function test_trash_event_post() {
		$post_id = wp_insert_post( array(
			'post_title'  => 'Trash Me',
			'post_status' => 'publish',
			'post_type'   => 'ayudawp_event',
		) );

		wp_trash_post( $post_id );
		$this->assertEquals( 'trash', get_post_status( $post_id ) );
	}
}

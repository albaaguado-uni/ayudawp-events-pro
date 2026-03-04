<?php
namespace AyudaWP\EventsPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Post_Type {

	private $post_type = 'ayudawp_event';

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register() {
		$labels = array(
			'name'          => __( 'Events', 'ayudawp-events-pro' ),
			'singular_name' => __( 'Event', 'ayudawp-events-pro' ),
		);

		$args = array(
			'labels'       => $labels,
			'public'       => true,
			'has_archive'  => true,
			'show_in_rest' => true,
			'rewrite'      => array( 'slug' => 'events' ),
			'menu_icon'    => 'dashicons-calendar-alt',
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		);

		register_post_type( $this->post_type, $args );
	}

	public function get_post_type() {
		return $this->post_type;
	}
}

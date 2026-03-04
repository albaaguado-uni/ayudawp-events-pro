<?php
namespace AyudaWP\EventsPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Attendees {

	/**
	 * Devuelve nombre de la tabla.
	 */
	private function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'ayudawp_event_attendees';
	}

	/**
	 * Registrar un asistente en un evento.
	 */
	public function register( $event_id, $name, $email, $phone = '' ) {
		global $wpdb;

		$event_id = absint( $event_id );
		$name     = sanitize_text_field( $name );
		$email    = sanitize_email( $email );
		$phone    = sanitize_text_field( $phone );

		if ( ! $event_id || empty( $name ) || empty( $email ) || ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_data', 'Datos inválidos.' );
		}

		// Evitar duplicados: mismo email en mismo evento.
		$table  = $this->table_name();
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE event_id = %d AND email = %s LIMIT 1",
				$event_id,
				$email
			)
		);

		if ( $exists ) {
			return new \WP_Error( 'already_registered', 'Este email ya está registrado en este evento.' );
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'event_id'       => $event_id,
				'user_id'        => get_current_user_id() ? get_current_user_id() : null,
				'name'           => $name,
				'email'          => $email,
				'phone'          => $phone,
				'status'         => 'registered',
				'registered_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new \WP_Error( 'db_error', 'No se pudo guardar el registro.' );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Obtener asistentes de un evento.
	 */
	public function get_by_event( $event_id ) {
		global $wpdb;

		$event_id = absint( $event_id );
		if ( ! $event_id ) {
			return array();
		}

		$table = $this->table_name();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE event_id = %d ORDER BY registered_at DESC",
				$event_id
			),
			ARRAY_A
		);
	}
}

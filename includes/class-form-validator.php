<?php
namespace AyudaWP\EventsPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Form_Validator {

	/** @var array<string,string> */
	private $errors = array();

	/** Reset collected errors. */
	public function reset() {
		$this->errors = array();
	}

	/** @return array<string,string> */
	public function get_errors() {
		return $this->errors;
	}

	/** @return bool */
	public function has_errors() {
		return ! empty( $this->errors );
	}

	/** @param string $field @param string $message */
	public function add_error( $field, $message ) {
		$this->errors[ $field ] = $message;
	}

	// ------------------------------------------------------------------ //
	//  Primitive validators (each returns bool and records its own error) //
	// ------------------------------------------------------------------ //

	public function required( $value, $field ) {
		if ( '' === (string) $value || null === $value ) {
			$this->add_error( $field, "{$field} is required." );
			return false;
		}
		return true;
	}

	public function email( $value, $field = 'email' ) {
		if ( ! is_email( $value ) ) {
			$this->add_error( $field, 'Please provide a valid email address.' );
			return false;
		}
		return true;
	}

	public function min_length( $value, $field, $min ) {
		if ( mb_strlen( (string) $value ) < $min ) {
			$this->add_error( $field, "{$field} must be at least {$min} characters." );
			return false;
		}
		return true;
	}

	public function max_length( $value, $field, $max ) {
		if ( mb_strlen( (string) $value ) > $max ) {
			$this->add_error( $field, "{$field} must not exceed {$max} characters." );
			return false;
		}
		return true;
	}

	public function numeric( $value, $field, $min = null, $max = null ) {
		if ( ! is_numeric( $value ) ) {
			$this->add_error( $field, "{$field} must be a number." );
			return false;
		}
		$val = floatval( $value );
		if ( null !== $min && $val < $min ) {
			$this->add_error( $field, "{$field} must be at least {$min}." );
			return false;
		}
		if ( null !== $max && $val > $max ) {
			$this->add_error( $field, "{$field} must not exceed {$max}." );
			return false;
		}
		return true;
	}

	public function date_format( $value, $field, $format = 'Y-m-d' ) {
		$d = \DateTime::createFromFormat( $format, $value );
		if ( ! $d || $d->format( $format ) !== $value ) {
			$this->add_error( $field, "{$field} must be a valid date ({$format})." );
			return false;
		}
		return true;
	}

	public function date_after( $start, $end, $field = 'end_date' ) {
		if ( strtotime( $end ) < strtotime( $start ) ) {
			$this->add_error( $field, 'End date must be after start date.' );
			return false;
		}
		return true;
	}

	// ------------------------------------------------------------------ //
	//  Composite validators                                                //
	// ------------------------------------------------------------------ //

	/**
	 * Validate the event registration form (shortcode POST).
	 *
	 * @param array $raw $_POST data.
	 * @return array|\WP_Error Sanitized data or WP_Error with all messages.
	 */
	public function validate_registration( array $raw ) {
		$this->reset();

		$name     = $raw['name']     ?? '';
		$email    = $raw['email']    ?? '';
		$phone    = $raw['phone']    ?? '';
		$event_id = $raw['event_id'] ?? '';

		$this->required( $name,     'name' );
		$this->required( $email,    'email' );
		$this->required( $event_id, 'event_id' );

		if ( $name )     { $this->min_length( $name, 'name', 2 ); }
		if ( $email )    { $this->email( $email, 'email' ); }
		if ( $event_id ) { $this->numeric( $event_id, 'event_id', 1 ); }

		if ( $this->has_errors() ) {
			$err = new \WP_Error();
			foreach ( $this->errors as $f => $m ) {
				$err->add( $f, $m );
			}
			return $err;
		}

		return array(
			'name'     => sanitize_text_field( $name ),
			'email'    => sanitize_email( $email ),
			'phone'    => sanitize_text_field( $phone ),
			'event_id' => absint( $event_id ),
		);
	}

	/**
	 * Validate the event creation / edit form.
	 *
	 * @param array $raw Raw data.
	 * @return array|\WP_Error
	 */
	public function validate_event_form( array $raw ) {
		$this->reset();

		$title      = $raw['title']      ?? '';
		$start_date = $raw['start_date'] ?? '';
		$end_date   = $raw['end_date']   ?? '';
		$capacity   = $raw['capacity']   ?? '';
		$price      = $raw['price']      ?? '';

		$this->required(  $title,      'title' );
		$this->required(  $start_date, 'start_date' );

		if ( $title )      { $this->min_length( $title, 'title', 3 ); }
		if ( $start_date ) { $this->date_format( $start_date, 'start_date' ); }
		if ( $end_date )   { $this->date_format( $end_date, 'end_date' ); }
		if ( $end_date && $start_date && ! $this->has_errors() ) {
			$this->date_after( $start_date, $end_date, 'end_date' );
		}
		if ( '' !== $capacity ) { $this->numeric( $capacity, 'capacity', 0 ); }
		if ( '' !== $price )    { $this->numeric( $price, 'price', 0 ); }

		if ( $this->has_errors() ) {
			$err = new \WP_Error();
			foreach ( $this->errors as $f => $m ) {
				$err->add( $f, $m );
			}
			return $err;
		}

		return array(
			'title'      => sanitize_text_field( $title ),
			'start_date' => $start_date,
			'end_date'   => $end_date,
			'location'   => sanitize_text_field( $raw['location'] ?? '' ),
			'capacity'   => '' !== $capacity ? absint( $capacity ) : 0,
			'price'      => '' !== $price ? floatval( $price ) : 0.0,
		);
	}
}

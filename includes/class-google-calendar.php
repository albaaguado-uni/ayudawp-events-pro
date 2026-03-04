<?php
namespace AyudaWP\EventsPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Calendar integration stub.
 * Creates .ics file content and generates "Add to Google Calendar" URLs.
 */
class Google_Calendar {

	/**
	 * Build a Google Calendar "Add event" URL.
	 *
	 * @param array $event_data Keys: title, start_date (Y-m-d), end_date (Y-m-d),
	 *                          description, location.
	 * @return string URL or empty string on invalid data.
	 */
	public function get_add_url( array $event_data ) {
		if ( empty( $event_data['title'] ) || empty( $event_data['start_date'] ) ) {
			return '';
		}

		$params = array(
			'action'   => 'TEMPLATE',
			'text'     => $event_data['title'],
			'dates'    => $this->format_dates( $event_data['start_date'], $event_data['end_date'] ?? $event_data['start_date'] ),
			'details'  => $event_data['description'] ?? '',
			'location' => $event_data['location'] ?? '',
		);

		return 'https://calendar.google.com/calendar/render?' . http_build_query( $params );
	}

	/**
	 * Generate ICS file content for an event.
	 *
	 * @param array $event_data Keys: title, start_date, end_date, description, location, url.
	 * @return string ICS content.
	 */
	public function generate_ics( array $event_data ) {
		$start = $this->to_ics_date( $event_data['start_date'] ?? '' );
		$end   = $this->to_ics_date( $event_data['end_date'] ?? ( $event_data['start_date'] ?? '' ) );

		if ( ! $start || ! $end ) {
			return '';
		}

		$uid     = wp_generate_uuid4() . '@ayudawp-events-pro';
		$now     = gmdate( 'Ymd\THis\Z' );
		$title   = $this->ics_escape( $event_data['title'] ?? 'Event' );
		$desc    = $this->ics_escape( $event_data['description'] ?? '' );
		$loc     = $this->ics_escape( $event_data['location'] ?? '' );
		$url     = $event_data['url'] ?? '';

		return implode( "\r\n", array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//AyudaWP Events Pro//EN',
			'BEGIN:VEVENT',
			"UID:{$uid}",
			"DTSTAMP:{$now}",
			"DTSTART:{$start}",
			"DTEND:{$end}",
			"SUMMARY:{$title}",
			"DESCRIPTION:{$desc}",
			"LOCATION:{$loc}",
			"URL:{$url}",
			'END:VEVENT',
			'END:VCALENDAR',
		) );
	}

	/**
	 * Validate that a date string is in Y-m-d format.
	 *
	 * @param string $date
	 * @return bool
	 */
	public function is_valid_date( $date ) {
		$d = \DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}

	// ------------------------------------------------------------------ //
	//  Private helpers                                                      //
	// ------------------------------------------------------------------ //

	private function format_dates( $start, $end ) {
		return str_replace( '-', '', $start ) . '/' . str_replace( '-', '', $end );
	}

	private function to_ics_date( $date ) {
		if ( ! $this->is_valid_date( $date ) ) {
			return '';
		}
		return str_replace( '-', '', $date );
	}

	private function ics_escape( $value ) {
		return str_replace( array( "\r\n", "\n", ',' ), array( '\n', '\n', '\,' ), $value );
	}
}

<?php
namespace AyudaWP\EventsPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notification_System {

	/** Email templates keyed by slug. */
	private $templates = array(
		'registration_confirmation' => array(
			'subject' => 'Registro confirmado: {event_title}',
			'body'    => "Hola {name},\n\nTe has registrado correctamente en {event_title}.\nFecha: {event_date}\nLugar: {event_location}\n\n¡Te esperamos!",
		),
		'payment_confirmation' => array(
			'subject' => 'Pago recibido: {event_title}',
			'body'    => "Hola {name},\n\nHemos recibido tu pago de {amount} para {event_title}.\nNúmero de entrada: #{attendee_id}\n\nGracias.",
		),
		'event_reminder' => array(
			'subject' => 'Recordatorio: {event_title}',
			'body'    => "Hola {name},\n\nTe recordamos que {event_title} es el {event_date} en {event_location}.\n\n¡Hasta pronto!",
		),
		'cancellation_notice' => array(
			'subject' => 'Cancelación: {event_title}',
			'body'    => "Hola {name},\n\nTu registro en {event_title} ha sido cancelado.\n\nSi crees que es un error contacta con nosotros.",
		),
	);

	/**
	 * Render a template replacing {placeholders} with values.
	 *
	 * @param string $template_key Template slug.
	 * @param array  $vars         Key => value replacements.
	 * @return array{subject:string,body:string}|null
	 */
	public function render( $template_key, array $vars ) {
		if ( ! isset( $this->templates[ $template_key ] ) ) {
			return null;
		}

		$tpl = $this->templates[ $template_key ];

		foreach ( $vars as $k => $v ) {
			$tpl['subject'] = str_replace( '{' . $k . '}', $v, $tpl['subject'] );
			$tpl['body']    = str_replace( '{' . $k . '}', $v, $tpl['body'] );
		}

		return $tpl;
	}

	/**
	 * Send an email notification.
	 *
	 * @param string $to      Recipient address.
	 * @param string $subject Email subject.
	 * @param string $body    Email body.
	 * @return bool
	 */
	public function send( $to, $subject, $body ) {
		if ( ! is_email( $to ) || empty( $subject ) || empty( $body ) ) {
			return false;
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		return wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Notify an attendee using a template.
	 *
	 * @param string $to           Recipient address.
	 * @param string $template_key Template slug.
	 * @param array  $vars         Placeholder values.
	 * @return bool
	 */
	public function notify( $to, $template_key, array $vars ) {
		$rendered = $this->render( $template_key, $vars );
		if ( ! $rendered ) {
			return false;
		}
		return $this->send( $to, $rendered['subject'], $rendered['body'] );
	}

	/**
	 * Get list of available template keys.
	 *
	 * @return string[]
	 */
	public function get_template_keys() {
		return array_keys( $this->templates );
	}
}

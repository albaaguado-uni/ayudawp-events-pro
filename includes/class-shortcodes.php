<?php
namespace AyudaWP\EventsPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcodes {

	private $attendees;

	public function __construct( Attendees $attendees ) {
		$this->attendees = $attendees;

		add_shortcode( 'ayudawp_event_register', array( $this, 'register_form' ) );
	}

	public function register_form( $atts ) {
		$atts = shortcode_atts(
			array(
				'event_id' => 0,
			),
			$atts
		);

		$event_id = absint( $atts['event_id'] );

		if ( ! $event_id ) {
			return '<p>❌ Falta event_id. Ejemplo: [ayudawp_event_register event_id="123"]</p>';
		}

		$message = '';

		// Procesar envío.
		if ( isset( $_POST['ayudawp_register_nonce'] ) && wp_verify_nonce( $_POST['ayudawp_register_nonce'], 'ayudawp_register' ) ) {

			$name  = isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '';
			$email = isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '';
			$phone = isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '';

			$result = $this->attendees->register( $event_id, $name, $email, $phone );

			if ( is_wp_error( $result ) ) {
				$message = '<p style="color:red;">❌ ' . esc_html( $result->get_error_message() ) . '</p>';
			} else {
				$message = '<p style="color:green;">✅ Registro completado. ID: ' . esc_html( $result ) . '</p>';
			}
		}

		ob_start();
		?>
		<div class="ayudawp-event-register">
			<?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<form method="post">
				<p>
					<label>Nombre</label><br>
					<input type="text" name="name" required>
				</p>
				<p>
					<label>Email</label><br>
					<input type="email" name="email" required>
				</p>
				<p>
					<label>Teléfono (opcional)</label><br>
					<input type="text" name="phone">
				</p>

				<?php wp_nonce_field( 'ayudawp_register', 'ayudawp_register_nonce' ); ?>

				<button type="submit">Registrarme</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
}

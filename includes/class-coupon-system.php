<?php
namespace AyudaWP\EventsPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Coupon_System {

	/** @var \wpdb */
	private $db;

	/** @var string */
	private $table;

	public function __construct() {
		global $wpdb;
		$this->db    = $wpdb;
		$this->table = $wpdb->prefix . 'ayudawp_coupons';
	}

	/**
	 * Create a coupon.
	 *
	 * @param array $data Keys: code, type (percentage|fixed), amount, min_amount, max_uses, expiry_date.
	 * @return int|\WP_Error  New coupon ID or error.
	 */
	public function create( array $data ) {
		if ( empty( $data['code'] ) ) {
			return new \WP_Error( 'missing_code', 'Coupon code is required.' );
		}
		if ( empty( $data['amount'] ) || floatval( $data['amount'] ) <= 0 ) {
			return new \WP_Error( 'invalid_amount', 'Amount must be greater than 0.' );
		}

		$code = strtoupper( sanitize_text_field( $data['code'] ) );

		if ( $this->get_by_code( $code ) ) {
			return new \WP_Error( 'duplicate_code', 'Coupon code already exists.' );
		}

		$result = $this->db->insert(
			$this->table,
			array(
				'code'        => $code,
				'type'        => ( isset( $data['type'] ) && 'fixed' === $data['type'] ) ? 'fixed' : 'percentage',
				'amount'      => floatval( $data['amount'] ),
				'min_amount'  => isset( $data['min_amount'] ) ? floatval( $data['min_amount'] ) : 0.00,
				'max_uses'    => isset( $data['max_uses'] ) ? absint( $data['max_uses'] ) : 0,
				'used_count'  => 0,
				'expiry_date' => ! empty( $data['expiry_date'] ) ? $data['expiry_date'] : null,
				'is_active'   => 1,
				'created_at'  => current_time( 'mysql' ),
			)
		);

		return false === $result
			? new \WP_Error( 'db_error', 'Failed to create coupon.' )
			: (int) $this->db->insert_id;
	}

	/**
	 * Validate coupon code and return coupon data or WP_Error.
	 *
	 * @param string $code     Coupon code.
	 * @param float  $amount   Order amount (for min_amount check).
	 * @return array|\WP_Error
	 */
	public function validate( $code, $amount = 0.0 ) {
		$coupon = $this->get_by_code( $code );

		if ( ! $coupon ) {
			return new \WP_Error( 'invalid_coupon', 'Coupon not found.' );
		}
		if ( ! $coupon['is_active'] ) {
			return new \WP_Error( 'inactive_coupon', 'Coupon is inactive.' );
		}
		if ( $coupon['expiry_date'] && strtotime( $coupon['expiry_date'] ) < strtotime( current_time( 'Y-m-d' ) ) ) {
			return new \WP_Error( 'expired_coupon', 'Coupon has expired.' );
		}
		if ( $coupon['max_uses'] > 0 && $coupon['used_count'] >= $coupon['max_uses'] ) {
			return new \WP_Error( 'limit_reached', 'Coupon usage limit reached.' );
		}
		if ( $coupon['min_amount'] > 0 && floatval( $amount ) < floatval( $coupon['min_amount'] ) ) {
			return new \WP_Error( 'min_amount', sprintf( 'Minimum amount is %.2f.', $coupon['min_amount'] ) );
		}

		return $coupon;
	}

	/**
	 * Apply coupon: validate and return discounted price; increments used_count.
	 *
	 * @param string $code   Coupon code.
	 * @param float  $amount Original amount.
	 * @return float|\WP_Error
	 */
	public function apply( $code, $amount ) {
		$coupon = $this->validate( $code, $amount );
		if ( is_wp_error( $coupon ) ) {
			return $coupon;
		}

		$discount = 'percentage' === $coupon['type']
			? $amount * ( $coupon['amount'] / 100 )
			: min( $coupon['amount'], $amount );

		$this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET used_count = used_count + 1 WHERE code = %s",
				strtoupper( sanitize_text_field( $code ) )
			)
		);

		return round( max( 0.0, $amount - $discount ), 2 );
	}

	/**
	 * Get coupon row as array by code.
	 *
	 * @param string $code
	 * @return array|null
	 */
	public function get_by_code( $code ) {
		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE code = %s",
				strtoupper( sanitize_text_field( $code ) )
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Deactivate a coupon.
	 *
	 * @param int $id Coupon row ID.
	 * @return bool
	 */
	public function deactivate( $id ) {
		return (bool) $this->db->update(
			$this->table,
			array( 'is_active' => 0 ),
			array( 'id' => absint( $id ) )
		);
	}

	/**
	 * Create the coupons DB table (called from Installer).
	 */
	public static function create_table() {
		global $wpdb;
		$table           = $wpdb->prefix . 'ayudawp_coupons';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			code         VARCHAR(100)        NOT NULL,
			type         VARCHAR(20)         NOT NULL DEFAULT 'percentage',
			amount       DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
			min_amount   DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
			max_uses     INT UNSIGNED        NOT NULL DEFAULT 0,
			used_count   INT UNSIGNED        NOT NULL DEFAULT 0,
			expiry_date  DATE                DEFAULT NULL,
			is_active    TINYINT(1)          NOT NULL DEFAULT 1,
			created_at   DATETIME            NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY code (code)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}

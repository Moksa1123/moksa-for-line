<?php
/**
 * Trigger rules for order notification templates.
 *
 * A template fires only when every one of its rules matches, so rules narrow
 * rather than widen. An empty rule set matches everything, which is what makes
 * "notify on this status" the simple default.
 *
 * The stored format is inherited from the plugin this replaces, so templates
 * created there keep working: a list of arrays with type, operator and value.
 *
 * @package Mofoline
 */

namespace Mofoline\Woo;

defined( 'ABSPATH' ) || exit;

class TriggerRules {

	/**
	 * Comparing money with == is how a 100.00 order stops matching a rule for
	 * 100. Amounts are compared to the nearest cent instead.
	 */
	const EPSILON = 0.0001;

	/**
	 * Whether every rule matches the order.
	 *
	 * @param mixed     $rules Stored rule set.
	 * @param \WC_Order $order Order under evaluation.
	 */
	public static function match( $rules, $order ): bool {
		if ( empty( $rules ) || ! is_array( $rules ) ) {
			return true;
		}

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			if ( ! self::match_one( $rule, $order ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether a single rule matches.
	 *
	 * An incomplete rule matches, so a half-filled row in the editor cannot
	 * silently stop a customer's notification going out.
	 *
	 * @param array     $rule  One rule.
	 * @param \WC_Order $order Order under evaluation.
	 */
	private static function match_one( array $rule, $order ): bool {
		$type     = isset( $rule['type'] ) ? (string) $rule['type'] : '';
		$operator = isset( $rule['operator'] ) ? (string) $rule['operator'] : '';
		$value    = isset( $rule['value'] ) ? $rule['value'] : '';

		if ( '' === $type || '' === $operator || '' === $value ) {
			return true;
		}

		switch ( $type ) {
			case 'payment_method':
				return self::compare_identity( (string) $order->get_payment_method(), $operator, (string) $value );

			case 'shipping_method':
				return self::compare_identity( self::shipping_method_id( $order ), $operator, (string) $value );

			case 'order_total':
				return self::compare_amount( (float) $order->get_total(), $operator, (float) $value );
		}

		/**
		 * Evaluate a rule type this plugin does not know about.
		 *
		 * @param bool|null $matched  Null when unhandled, which fails the rule.
		 * @param array     $rule     The rule.
		 * @param \WC_Order $order    Order under evaluation.
		 */
		$matched = apply_filters( 'mofoline_notify_rule_match', null, $rule, $order );

		// An unrecognised rule fails rather than matches: a template that
		// depends on a condition nothing can evaluate should stay quiet.
		return is_bool( $matched ) ? $matched : false;
	}

	/**
	 * is / is_not comparison.
	 *
	 * @param string $actual   Value from the order.
	 * @param string $operator is or is_not.
	 * @param string $expected Value from the rule.
	 */
	private static function compare_identity( string $actual, string $operator, string $expected ): bool {
		if ( 'is' === $operator ) {
			return $actual === $expected;
		}

		if ( 'is_not' === $operator ) {
			return $actual !== $expected;
		}

		return false;
	}

	/**
	 * Numeric comparison, tolerant of floating point representation.
	 *
	 * @param float  $actual   Order total.
	 * @param string $operator gt, gte, eq, lte or lt.
	 * @param float  $expected Rule value.
	 */
	private static function compare_amount( float $actual, string $operator, float $expected ): bool {
		switch ( $operator ) {
			case 'gt':
				return $actual > $expected + self::EPSILON;
			case 'gte':
				return $actual >= $expected - self::EPSILON;
			case 'eq':
				return abs( $actual - $expected ) < self::EPSILON;
			case 'lte':
				return $actual <= $expected + self::EPSILON;
			case 'lt':
				return $actual < $expected - self::EPSILON;
		}

		return false;
	}

	/**
	 * The shipping method id on an order, if it has one.
	 *
	 * @param \WC_Order $order Order.
	 */
	private static function shipping_method_id( $order ): string {
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			$data = $item->get_data();

			if ( ! empty( $data['method_id'] ) ) {
				return (string) $data['method_id'];
			}
		}

		return '';
	}

	/**
	 * Rule types offered in the editor.
	 *
	 * @return array<string,string>
	 */
	public static function types(): array {
		return array(
			'payment_method'  => __( 'Payment method', 'moksa-for-line' ),
			'shipping_method' => __( 'Shipping method', 'moksa-for-line' ),
			'order_total'     => __( 'Order total', 'moksa-for-line' ),
		);
	}

	/**
	 * Operators offered for each rule type.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function operators(): array {
		return array(
			'payment_method'  => array(
				'is'     => __( 'is', 'moksa-for-line' ),
				'is_not' => __( 'is not', 'moksa-for-line' ),
			),
			'shipping_method' => array(
				'is'     => __( 'is', 'moksa-for-line' ),
				'is_not' => __( 'is not', 'moksa-for-line' ),
			),
			'order_total'     => array(
				'gt'  => __( 'is more than', 'moksa-for-line' ),
				'gte' => __( 'is at least', 'moksa-for-line' ),
				'eq'  => __( 'equals', 'moksa-for-line' ),
				'lte' => __( 'is at most', 'moksa-for-line' ),
				'lt'  => __( 'is less than', 'moksa-for-line' ),
			),
		);
	}

	/**
	 * Clean a submitted rule set for storage.
	 *
	 * @param mixed $submitted Raw rules from the editor.
	 * @return array
	 */
	public static function sanitize( $submitted ): array {
		if ( ! is_array( $submitted ) ) {
			return array();
		}

		$types     = self::types();
		$operators = self::operators();
		$clean     = array();

		foreach ( $submitted as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$type = isset( $rule['type'] ) ? sanitize_key( $rule['type'] ) : '';

			if ( ! isset( $types[ $type ] ) ) {
				continue;
			}

			$operator = isset( $rule['operator'] ) ? sanitize_key( $rule['operator'] ) : '';

			if ( ! isset( $operators[ $type ][ $operator ] ) ) {
				continue;
			}

			$value = isset( $rule['value'] ) ? sanitize_text_field( (string) $rule['value'] ) : '';

			if ( '' === $value ) {
				continue;
			}

			$clean[] = array(
				'type'     => $type,
				'operator' => $operator,
				'value'    => $value,
			);
		}

		return $clean;
	}
}

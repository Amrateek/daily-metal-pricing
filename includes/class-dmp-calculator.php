<?php
/**
 * Pure price calculation. Has no WordPress dependencies so it can be unit tested on its own.
 *
 * @package DailyMetalPricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Calculates a product price from the metal price and the product's pricing inputs.
 *
 * Formula:
 *   metal value = price per gram (pure) × weight × purity / 1000
 *   subtotal    = metal value + making charge per gram × weight + fixed charge
 *   price       = subtotal × (1 + markup / 100), then rounded
 */
final class DMP_Calculator {

	/**
	 * Calculate the unrounded price and its parts.
	 *
	 * @param array $args {
	 *     @type float $metal_price    Price per gram of pure metal (999 / 24K).
	 *     @type float $weight         Product weight in grams.
	 *     @type float $purity         Purity in parts per thousand, e.g. 925 or 875.
	 *     @type float $labor_per_gram Making charge per gram.
	 *     @type float $fixed_charge   Fixed charge per item (stones, packaging…).
	 *     @type float $markup         Markup in percent.
	 * }
	 * @return array|null Breakdown with keys metal_value, labor, fixed_charge, subtotal, markup_amount, total.
	 *                    Null when the inputs are not enough to price the product.
	 */
	public static function breakdown( array $args ) {
		$args = array_merge(
			array(
				'metal_price'    => 0,
				'weight'         => 0,
				'purity'         => 0,
				'labor_per_gram' => 0,
				'fixed_charge'   => 0,
				'markup'         => 0,
			),
			$args
		);

		$metal_price = self::to_float( $args['metal_price'] );
		$weight      = self::to_float( $args['weight'] );
		$purity      = self::to_float( $args['purity'] );

		if ( $metal_price <= 0 || $weight <= 0 || $purity <= 0 ) {
			return null;
		}

		$purity         = min( $purity, 1000.0 );
		$labor_per_gram = max( 0.0, self::to_float( $args['labor_per_gram'] ) );
		$fixed_charge   = max( 0.0, self::to_float( $args['fixed_charge'] ) );
		$markup         = self::to_float( $args['markup'] );

		$metal_value   = $metal_price * $weight * ( $purity / 1000 );
		$labor         = $labor_per_gram * $weight;
		$subtotal      = $metal_value + $labor + $fixed_charge;
		$markup_amount = $subtotal * ( $markup / 100 );
		$total         = max( 0.0, $subtotal + $markup_amount );

		return array(
			'metal_value'   => $metal_value,
			'labor'         => $labor,
			'fixed_charge'  => $fixed_charge,
			'subtotal'      => $subtotal,
			'markup_amount' => $markup_amount,
			'total'         => $total,
		);
	}

	/**
	 * Calculate the unrounded price.
	 *
	 * @param array $args See breakdown().
	 * @return float|null
	 */
	public static function calculate( array $args ) {
		$parts = self::breakdown( $args );
		return null === $parts ? null : $parts['total'];
	}

	/**
	 * Round a price to a step, e.g. the nearest 5 or 10.
	 *
	 * @param float  $price Price.
	 * @param float  $step  Rounding step. 0 or less means no rounding.
	 * @param string $mode  'nearest' or 'up'.
	 * @return float
	 */
	public static function round_price( $price, $step, $mode = 'nearest' ) {
		$price = (float) $price;
		$step  = (float) $step;

		if ( $step <= 0 ) {
			return $price;
		}

		// Round the quotient first so float noise like 12.000000001 doesn't push ceil() up a step.
		$units = round( $price / $step, 6 );
		$units = ( 'up' === $mode ) ? ceil( $units ) : round( $units );

		return round( $units * $step, 6 );
	}

	/**
	 * Cast a stored value to float. Empty strings become 0.
	 *
	 * @param mixed $value Value.
	 * @return float
	 */
	private static function to_float( $value ) {
		return is_numeric( $value ) ? (float) $value : 0.0;
	}
}

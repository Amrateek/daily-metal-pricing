<?php
/**
 * Helper functions.
 *
 * @package DailyMetalPricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Metals the plugin can price. Filter `dmp_metals` to add more (e.g. palladium).
 *
 * @return array<string,string> Metal key => label.
 */
function dmp_get_metals() {
	$metals = array(
		'gold'     => __( 'Gold', 'daily-metal-pricing' ),
		'silver'   => __( 'Silver', 'daily-metal-pricing' ),
		'platinum' => __( 'Platinum', 'daily-metal-pricing' ),
	);

	return (array) apply_filters( 'dmp_metals', $metals );
}

/**
 * Common purities, shown as suggestions in the purity field.
 *
 * @return array<string,array<int,string>> Metal key => [ purity => label ].
 */
function dmp_get_purity_presets() {
	$presets = array(
		'gold'     => array(
			999 => __( '24K', 'daily-metal-pricing' ),
			916 => __( '22K', 'daily-metal-pricing' ),
			875 => __( '21K', 'daily-metal-pricing' ),
			750 => __( '18K', 'daily-metal-pricing' ),
			585 => __( '14K', 'daily-metal-pricing' ),
			417 => __( '10K', 'daily-metal-pricing' ),
		),
		'silver'   => array(
			999 => __( 'Fine silver', 'daily-metal-pricing' ),
			925 => __( 'Sterling silver', 'daily-metal-pricing' ),
			900 => __( 'Coin silver', 'daily-metal-pricing' ),
			800 => __( 'Silver 800', 'daily-metal-pricing' ),
		),
		'platinum' => array(
			950 => __( 'Platinum 950', 'daily-metal-pricing' ),
			900 => __( 'Platinum 900', 'daily-metal-pricing' ),
		),
	);

	return (array) apply_filters( 'dmp_purity_presets', $presets );
}

/**
 * Plugin settings merged with defaults.
 *
 * @return array
 */
function dmp_get_settings() {
	$defaults = array(
		'prices'        => array(),
		'updated'       => array(),
		'rounding_step' => 0,
		'rounding_mode' => 'nearest',
		'sale_prices'   => 'keep',
	);

	$settings = get_option( 'dmp_settings', array() );

	return wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );
}

/**
 * Today's price per gram of pure metal.
 *
 * @param string $metal Metal key.
 * @return float 0 when not set.
 */
function dmp_get_metal_price( $metal ) {
	$settings = dmp_get_settings();
	return isset( $settings['prices'][ $metal ] ) ? (float) $settings['prices'][ $metal ] : 0.0;
}

/**
 * Turn user input into a plain decimal string.
 *
 * Accepts Arabic-Indic (٠١٢…) and Persian (۰۱۲…) digits and the Arabic decimal and
 * thousands separators, so store owners can type prices in their own keyboard layout.
 *
 * @param mixed $value Raw input.
 * @return string Decimal string, or '' when empty or not a number.
 */
function dmp_normalize_number( $value ) {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	$map = array(
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
		'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
		'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing
		'٫' => '.', // Arabic decimal separator.
		'٬' => '',  // Arabic thousands separator.
	);

	$value = strtr( $value, $map );
	$value = wc_format_decimal( $value );

	return is_numeric( $value ) ? $value : '';
}

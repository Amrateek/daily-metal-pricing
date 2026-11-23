<?php
/**
 * WP-CLI commands.
 *
 * @package DailyMetalPricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage metal prices from the command line.
 */
class DMP_CLI {

	/**
	 * Show today's metal prices.
	 *
	 * ## EXAMPLES
	 *
	 *     wp metal-pricing prices
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function prices( $args, $assoc_args ) {
		$settings = dmp_get_settings();
		$rows     = array();

		foreach ( dmp_get_metals() as $key => $label ) {
			$rows[] = array(
				'metal'   => $key,
				'label'   => $label,
				'price'   => isset( $settings['prices'][ $key ] ) ? $settings['prices'][ $key ] : '',
				'updated' => isset( $settings['updated'][ $key ] ) ? wp_date( 'Y-m-d H:i', (int) $settings['updated'][ $key ] ) : '',
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'metal', 'label', 'price', 'updated' ) );
	}

	/**
	 * Set today's price per gram for a metal.
	 *
	 * ## OPTIONS
	 *
	 * <metal>
	 * : Metal key: gold, silver or platinum.
	 *
	 * <price>
	 * : Price per gram of pure metal, in the store currency. Use 0 to clear it.
	 *
	 * [--reprice]
	 * : Reprice every metal-priced product afterwards.
	 *
	 * ## EXAMPLES
	 *
	 *     wp metal-pricing set-price silver 52.4 --reprice
	 *
	 * @subcommand set-price
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function set_price( $args, $assoc_args ) {
		list( $metal, $price ) = $args;

		$metal = sanitize_key( $metal );

		if ( ! array_key_exists( $metal, dmp_get_metals() ) ) {
			WP_CLI::error( sprintf( 'Unknown metal "%s". Use one of: %s', $metal, implode( ', ', array_keys( dmp_get_metals() ) ) ) );
		}

		$price = dmp_normalize_number( $price );

		if ( '' === $price || (float) $price < 0 ) {
			WP_CLI::error( 'Price must be a number.' );
		}

		$changed = DMP_Settings::update_prices( array( $metal => (float) $price > 0 ? $price : '' ), get_current_user_id() );

		WP_CLI::success( $changed ? sprintf( '%s price set to %s.', $metal, $price ) : 'Price unchanged.' );

		if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'reprice', false ) ) {
			$this->reprice( array(), array() );
		}
	}

	/**
	 * Reprice every product that uses metal pricing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp metal-pricing reprice
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function reprice( $args, $assoc_args ) {
		$ids     = DMP_Repricer::get_target_ids();
		$updated = 0;

		if ( ! $ids ) {
			WP_CLI::warning( 'No products use metal pricing.' );
			return;
		}

		$progress = WP_CLI\Utils\make_progress_bar( 'Repricing products', count( $ids ) );

		foreach ( $ids as $id ) {
			$updated += DMP_Repricer::reprice_product_id( $id );
			$progress->tick();
		}

		$progress->finish();

		WP_CLI::success( sprintf( '%d prices changed across %d products.', $updated, count( $ids ) ) );
	}
}

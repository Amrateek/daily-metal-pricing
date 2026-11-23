<?php
/**
 * Applies calculated prices to products, one at a time or across the whole catalog.
 *
 * @package DailyMetalPricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Repricing engine.
 */
final class DMP_Repricer {

	/** Products repriced per background batch. */
	const BATCH_SIZE = 25;

	/** Catalogs up to this many products are repriced right away instead of in the background. */
	const INLINE_LIMIT = 200;

	/** Action Scheduler hook for background batches. */
	const HOOK = 'dmp_reprice_batch';

	/** Action Scheduler group. */
	const GROUP = 'daily-metal-pricing';

	/** Option holding the status of the last full repricing run. */
	const STATUS_OPTION = 'dmp_reprice_status';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run_batch' ), 10, 1 );
	}

	/**
	 * Pricing inputs for a product. Variations fall back to their parent product for any empty field.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return array<string,string>
	 */
	public static function get_inputs( WC_Product $product ) {
		$parent = null;

		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
		}

		$inputs = array();

		foreach ( DMP_Product_Fields::META_KEYS as $field => $meta_key ) {
			$value = (string) $product->get_meta( $meta_key, true, 'edit' );

			if ( '' === $value && $parent ) {
				$value = (string) $parent->get_meta( $meta_key, true, 'edit' );
			}

			$inputs[ $field ] = $value;
		}

		return $inputs;
	}

	/**
	 * Calculate a product's price from today's metal price.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return array|null Breakdown from DMP_Calculator plus 'price' (rounded) and 'metal', or null when the product isn't metal-priced.
	 */
	public static function calculate_for( WC_Product $product ) {
		$inputs = self::get_inputs( $product );
		$metal  = $inputs['metal'];

		if ( '' === $metal || ! array_key_exists( $metal, dmp_get_metals() ) ) {
			return null;
		}

		$parts = DMP_Calculator::breakdown(
			array(
				'metal_price'    => dmp_get_metal_price( $metal ),
				'weight'         => $inputs['weight'],
				'purity'         => $inputs['purity'],
				'labor_per_gram' => $inputs['labor_per_gram'],
				'fixed_charge'   => $inputs['fixed_charge'],
				'markup'         => $inputs['markup'],
			)
		);

		if ( null === $parts ) {
			return null;
		}

		$settings = dmp_get_settings();
		$price    = DMP_Calculator::round_price( $parts['total'], $settings['rounding_step'], $settings['rounding_mode'] );

		/**
		 * Filter the calculated price before it is saved.
		 *
		 * @param float      $price   Rounded price.
		 * @param WC_Product $product Product or variation.
		 * @param array      $inputs  Pricing inputs (metal, weight, purity, labor_per_gram, fixed_charge, markup).
		 * @param array      $parts   Unrounded breakdown.
		 */
		$price = (float) apply_filters( 'dmp_calculated_price', $price, $product, $inputs, $parts );

		$parts['metal'] = $metal;
		$parts['price'] = (float) wc_format_decimal( $price, wc_get_price_decimals() );

		return $parts;
	}

	/**
	 * Set the calculated price on a product object. Does not save it.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return bool True when the product was changed.
	 */
	public static function apply_to_product( WC_Product $product ) {
		if ( $product->is_type( array( 'variable', 'grouped' ) ) ) {
			return false;
		}

		$result = self::calculate_for( $product );

		if ( null === $result ) {
			return false;
		}

		$new_price = wc_format_decimal( $result['price'], wc_get_price_decimals() );
		$changed   = (string) $product->get_regular_price( 'edit' ) !== (string) $new_price;

		$product->set_regular_price( $new_price );

		$sale_price = $product->get_sale_price( 'edit' );

		if ( '' !== $sale_price ) {
			$settings = dmp_get_settings();

			// A fixed sale price can end up above the new regular price, so drop it rather than show a fake discount.
			if ( 'clear' === $settings['sale_prices'] || (float) $sale_price >= (float) $new_price ) {
				$product->set_sale_price( '' );
				$product->set_date_on_sale_from( '' );
				$product->set_date_on_sale_to( '' );
				$changed = true;
			}
		}

		return $changed;
	}

	/**
	 * Reprice and save one product. Variable products reprice every variation and resync.
	 *
	 * @param int $product_id Product ID (not a variation ID).
	 * @return int Number of products or variations whose price changed.
	 */
	public static function reprice_product_id( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product || 'trash' === $product->get_status() ) {
			return 0;
		}

		$updated = 0;

		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$variation = wc_get_product( $child_id );

				if ( $variation && self::apply_to_product( $variation ) ) {
					$variation->save();
					++$updated;
				}
			}

			if ( $updated ) {
				WC_Product_Variable::sync( $product_id );
			}

			return $updated;
		}

		if ( self::apply_to_product( $product ) ) {
			$product->save();
			++$updated;
		}

		return $updated;
	}

	/**
	 * IDs of every product that uses metal pricing, including parents of metal-priced variations.
	 *
	 * @return int[]
	 */
	public static function get_target_ids() {
		global $wpdb;

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT DISTINCT CASE WHEN p.post_type = 'product_variation' THEN p.post_parent ELSE p.ID END AS target_id
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				WHERE pm.meta_key = %s
					AND pm.meta_value <> ''
					AND p.post_type IN ( 'product', 'product_variation' )
					AND p.post_status NOT IN ( 'trash', 'auto-draft' )
				ORDER BY target_id ASC",
				DMP_Product_Fields::META_KEYS['metal']
			)
		);

		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/**
	 * Reprice the whole catalog. Small catalogs run now; large ones run in background batches.
	 *
	 * @param string $source What started the run, for the status display.
	 * @return array{mode:string,products:int,updated:int}
	 */
	public static function reprice_all( $source = 'manual' ) {
		$ids   = self::get_target_ids();
		$total = count( $ids );

		$use_background = $total > self::INLINE_LIMIT && function_exists( 'as_enqueue_async_action' );

		self::set_status(
			array(
				'state'    => 'running',
				'mode'     => $use_background ? 'background' : 'inline',
				'source'   => $source,
				'total'    => $total,
				'done'     => 0,
				'updated'  => 0,
				'started'  => time(),
				'finished' => 0,
			)
		);

		if ( $use_background ) {
			as_unschedule_all_actions( self::HOOK, array(), self::GROUP );
			as_enqueue_async_action( self::HOOK, array( 'offset' => 0 ), self::GROUP );

			return array(
				'mode'     => 'background',
				'products' => $total,
				'updated'  => 0,
			);
		}

		if ( function_exists( 'wc_set_time_limit' ) ) {
			wc_set_time_limit( 0 );
		}

		$updated = 0;

		foreach ( $ids as $id ) {
			$updated += self::reprice_product_id( $id );
		}

		$status             = self::get_status();
		$status['state']    = 'done';
		$status['done']     = $total;
		$status['updated']  = $updated;
		$status['finished'] = time();
		self::set_status( $status );

		return array(
			'mode'     => 'inline',
			'products' => $total,
			'updated'  => $updated,
		);
	}

	/**
	 * Background batch handler.
	 *
	 * @param int $offset Position in the product list.
	 */
	public static function run_batch( $offset ) {
		$offset = absint( $offset );
		$ids    = self::get_target_ids();
		$slice  = array_slice( $ids, $offset, self::BATCH_SIZE );
		$status = self::get_status();

		$updated = 0;

		foreach ( $slice as $id ) {
			$updated += self::reprice_product_id( $id );
		}

		$status['done']    = min( count( $ids ), $offset + count( $slice ) );
		$status['updated'] = (int) $status['updated'] + $updated;

		if ( $offset + self::BATCH_SIZE < count( $ids ) ) {
			as_enqueue_async_action( self::HOOK, array( 'offset' => $offset + self::BATCH_SIZE ), self::GROUP );
		} else {
			$status['state']    = 'done';
			$status['finished'] = time();
		}

		self::set_status( $status );
	}

	/**
	 * Status of the last full run.
	 *
	 * @return array
	 */
	public static function get_status() {
		$status = get_option( self::STATUS_OPTION, array() );

		return wp_parse_args(
			is_array( $status ) ? $status : array(),
			array(
				'state'    => 'never',
				'mode'     => '',
				'source'   => '',
				'total'    => 0,
				'done'     => 0,
				'updated'  => 0,
				'started'  => 0,
				'finished' => 0,
			)
		);
	}

	/**
	 * Save run status.
	 *
	 * @param array $status Status.
	 */
	private static function set_status( array $status ) {
		update_option( self::STATUS_OPTION, $status, false );
	}
}

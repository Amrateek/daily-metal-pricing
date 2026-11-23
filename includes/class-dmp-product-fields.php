<?php
/**
 * Metal pricing fields on the product and variation edit screens.
 *
 * @package DailyMetalPricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product edit screen integration.
 */
final class DMP_Product_Fields {

	/** Field name => meta key. */
	const META_KEYS = array(
		'metal'          => '_dmp_metal',
		'weight'         => '_dmp_weight',
		'purity'         => '_dmp_purity',
		'labor_per_gram' => '_dmp_labor_per_gram',
		'fixed_charge'   => '_dmp_fixed_charge',
		'markup'         => '_dmp_markup',
	);

	/**
	 * Hook into WooCommerce.
	 */
	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'reprice_variations_after_save' ), 20 );

		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'render_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_admin_process_variation_object', array( __CLASS__, 'save_variation' ), 10, 2 );
	}

	/**
	 * Add the "Metal pricing" tab.
	 *
	 * @param array $tabs Product data tabs.
	 * @return array
	 */
	public static function add_tab( $tabs ) {
		$tabs['dmp_metal_pricing'] = array(
			'label'    => __( 'Metal pricing', 'daily-metal-pricing' ),
			'target'   => 'dmp_metal_pricing_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 15,
		);

		return $tabs;
	}

	/**
	 * Render the tab panel.
	 */
	public static function render_panel() {
		global $product_object;

		$product = $product_object instanceof WC_Product ? $product_object : null;

		echo '<div id="dmp_metal_pricing_data" class="panel woocommerce_options_panel hidden">';
		echo '<div class="options_group">';

		echo '<p class="dmp-panel-intro show_if_simple">' . esc_html__( 'Fill these in and the regular price is set for you from today\'s metal price. Leave Metal empty to price this product by hand.', 'daily-metal-pricing' ) . '</p>';
		echo '<p class="dmp-panel-intro show_if_variable">' . esc_html__( 'These are the defaults for every variation. Each variation can override any field, for example its own weight.', 'daily-metal-pricing' ) . '</p>';

		self::render_fields( $product, '', null );

		echo '</div>';

		if ( $product && ! $product->is_type( 'variable' ) ) {
			self::render_preview( $product );
		}

		echo '</div>';

		self::render_purity_datalist();
	}

	/**
	 * Render fields for a variation.
	 *
	 * @param int     $loop           Variation index.
	 * @param array   $variation_data Unused.
	 * @param WP_Post $variation_post Variation post.
	 */
	public static function render_variation_fields( $loop, $variation_data, $variation_post ) {
		$variation = wc_get_product( $variation_post->ID );

		if ( ! $variation ) {
			return;
		}

		$parent = wc_get_product( $variation->get_parent_id() );

		echo '<div class="dmp-variation-fields">';
		echo '<p class="form-row form-row-full dmp-variation-heading"><strong>' . esc_html__( 'Metal pricing', 'daily-metal-pricing' ) . '</strong> ';
		echo '<span class="description">' . esc_html__( 'Empty fields use the product\'s Metal pricing tab.', 'daily-metal-pricing' ) . '</span></p>';

		self::render_fields( $variation, (string) $loop, $parent );

		$result = DMP_Repricer::calculate_for( $variation );

		if ( null !== $result ) {
			echo '<p class="form-row form-row-full dmp-variation-price">';
			/* translators: %s: formatted price. */
			echo wp_kses_post( sprintf( __( 'Price from today\'s metal price: %s', 'daily-metal-pricing' ), wc_price( $result['price'] ) ) );
			echo '</p>';
		}

		echo '</div>';
	}

	/**
	 * Render the six fields.
	 *
	 * @param WC_Product|null $product Product or variation being edited.
	 * @param string          $loop    Variation index, or '' for the parent product.
	 * @param WC_Product|null $parent  Parent product when rendering a variation.
	 */
	private static function render_fields( $product, $loop, $parent ) {
		$is_variation = '' !== $loop;
		$currency     = get_woocommerce_currency_symbol();

		$value = static function ( $field ) use ( $product ) {
			return $product ? (string) $product->get_meta( self::META_KEYS[ $field ], true, 'edit' ) : '';
		};

		$inherited = static function ( $field ) use ( $parent ) {
			return $parent ? (string) $parent->get_meta( self::META_KEYS[ $field ], true, 'edit' ) : '';
		};

		$name = static function ( $field ) use ( $loop, $is_variation ) {
			return $is_variation ? "dmp_{$field}[{$loop}]" : "dmp_{$field}";
		};

		$id = static function ( $field ) use ( $loop, $is_variation ) {
			return $is_variation ? "dmp_{$field}_{$loop}" : "dmp_{$field}";
		};

		$metals = dmp_get_metals();

		if ( $is_variation ) {
			$parent_metal = $inherited( 'metal' );
			$empty_label  = '' !== $parent_metal && isset( $metals[ $parent_metal ] )
				/* translators: %s: metal name, e.g. Gold. */
				? sprintf( __( 'Same as product (%s)', 'daily-metal-pricing' ), $metals[ $parent_metal ] )
				: __( 'Same as product', 'daily-metal-pricing' );
		} else {
			$empty_label = __( 'Not priced by metal', 'daily-metal-pricing' );
		}

		woocommerce_wp_select(
			array(
				'id'            => $id( 'metal' ),
				'name'          => $name( 'metal' ),
				'label'         => __( 'Metal', 'daily-metal-pricing' ),
				'value'         => $value( 'metal' ),
				'options'       => array( '' => $empty_label ) + $metals,
				'wrapper_class' => $is_variation ? 'form-row form-row-first' : '',
			)
		);

		$number_fields = array(
			'weight'         => array(
				'label'       => __( 'Weight (g)', 'daily-metal-pricing' ),
				'description' => __( 'Weight of the metal in grams.', 'daily-metal-pricing' ),
			),
			'purity'         => array(
				'label'       => __( 'Purity (per 1000)', 'daily-metal-pricing' ),
				'description' => __( 'Parts per thousand, e.g. 875 for 21K gold or 925 for sterling silver.', 'daily-metal-pricing' ),
				'list'        => 'dmp-purity-presets',
			),
			'labor_per_gram' => array(
				/* translators: %s: currency symbol. */
				'label'       => sprintf( __( 'Making charge per gram (%s)', 'daily-metal-pricing' ), $currency ),
				'description' => __( 'Workmanship cost added for each gram.', 'daily-metal-pricing' ),
			),
			'fixed_charge'   => array(
				/* translators: %s: currency symbol. */
				'label'       => sprintf( __( 'Fixed charge (%s)', 'daily-metal-pricing' ), $currency ),
				'description' => __( 'Added once per item, e.g. for stones, chain or packaging.', 'daily-metal-pricing' ),
			),
			'markup'         => array(
				'label'       => __( 'Markup (%)', 'daily-metal-pricing' ),
				'description' => __( 'Percentage added on top of everything else.', 'daily-metal-pricing' ),
			),
		);

		$i = 1;

		foreach ( $number_fields as $field => $config ) {
			$attributes = array(
				'inputmode'    => 'decimal',
				'autocomplete' => 'off',
				'dir'          => 'ltr',
			);

			if ( ! empty( $config['list'] ) ) {
				$attributes['list'] = $config['list'];
			}

			$wrapper = '';

			if ( $is_variation ) {
				// Metal took the first slot, so weight goes on the right and the rest alternate.
				$wrapper = ( 0 === $i % 2 ) ? 'form-row form-row-first' : 'form-row form-row-last';
			}

			woocommerce_wp_text_input(
				array(
					'id'                => $id( $field ),
					'name'              => $name( $field ),
					'label'             => $config['label'],
					'value'             => $value( $field ),
					'placeholder'       => $is_variation ? $inherited( $field ) : '',
					'description'       => $config['description'],
					'desc_tip'          => true,
					'type'              => 'text',
					'class'             => 'short dmp-number',
					'wrapper_class'     => $wrapper,
					'custom_attributes' => $attributes,
				)
			);

			++$i;
		}
	}

	/**
	 * Show the price breakdown for a simple product.
	 *
	 * @param WC_Product $product Product.
	 */
	private static function render_preview( WC_Product $product ) {
		$result = DMP_Repricer::calculate_for( $product );

		echo '<div class="options_group dmp-preview show_if_simple">';

		if ( null === $result ) {
			$inputs = DMP_Repricer::get_inputs( $product );

			if ( '' !== $inputs['metal'] && dmp_get_metal_price( $inputs['metal'] ) <= 0 ) {
				$url = admin_url( 'admin.php?page=' . DMP_Settings::PAGE );
				/* translators: %s: link to the metal prices settings page. */
				$message = sprintf( __( 'Set today\'s price for this metal on the <a href="%s">Metal prices</a> page to see the calculated price.', 'daily-metal-pricing' ), esc_url( $url ) );
				echo '<p class="dmp-preview-empty">' . wp_kses_post( $message ) . '</p>';
			}

			echo '</div>';
			return;
		}

		$rows = array(
			__( 'Metal value', 'daily-metal-pricing' )   => $result['metal_value'],
			__( 'Making charge', 'daily-metal-pricing' ) => $result['labor'],
			__( 'Fixed charge', 'daily-metal-pricing' )  => $result['fixed_charge'],
			__( 'Markup', 'daily-metal-pricing' )        => $result['markup_amount'],
		);

		echo '<table class="dmp-breakdown"><tbody>';

		foreach ( $rows as $label => $amount ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . wp_kses_post( wc_price( $amount ) ) . '</td></tr>';
		}

		echo '<tr class="dmp-breakdown-total"><th scope="row">' . esc_html__( 'Price (after rounding)', 'daily-metal-pricing' ) . '</th><td>' . wp_kses_post( wc_price( $result['price'] ) ) . '</td></tr>';
		echo '</tbody></table>';
		echo '<p class="description dmp-breakdown-note">' . esc_html__( 'This is saved as the regular price every time you update the product or change the metal price.', 'daily-metal-pricing' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Purity suggestions for the purity field.
	 */
	private static function render_purity_datalist() {
		static $printed = false;

		if ( $printed ) {
			return;
		}

		$printed = true;
		$metals  = dmp_get_metals();

		echo '<datalist id="dmp-purity-presets">';

		foreach ( dmp_get_purity_presets() as $metal => $presets ) {
			$metal_label = isset( $metals[ $metal ] ) ? $metals[ $metal ] : $metal;

			foreach ( $presets as $purity => $label ) {
				echo '<option value="' . esc_attr( $purity ) . '" label="' . esc_attr( $metal_label . ' — ' . $label ) . '"></option>';
			}
		}

		echo '</datalist>';
	}

	/**
	 * Save fields on a simple or variable product, then set the price for simple products.
	 *
	 * WooCommerce has already checked the nonce and permissions before this runs.
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public static function save_product( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['dmp_metal'] ) ) {
			return; // Our fields weren't on the form, so leave existing values alone.
		}

		foreach ( array_keys( self::META_KEYS ) as $field ) {
			$raw = isset( $_POST[ "dmp_{$field}" ] ) ? wp_unslash( $_POST[ "dmp_{$field}" ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			self::set_field( $product, $field, $raw );
		}
		// phpcs:enable

		DMP_Repricer::apply_to_product( $product );
	}

	/**
	 * After a variable product is saved, reprice its variations so new defaults take effect.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function reprice_variations_after_save( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( $product && $product->is_type( 'variable' ) ) {
			DMP_Repricer::reprice_product_id( $product_id );
		}
	}

	/**
	 * Save fields on a variation, then set its price.
	 *
	 * @param WC_Product_Variation $variation Variation being saved.
	 * @param int                  $i         Variation index in the posted form.
	 */
	public static function save_variation( $variation, $i ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['dmp_metal'][ $i ] ) ) {
			return;
		}

		foreach ( array_keys( self::META_KEYS ) as $field ) {
			$raw = isset( $_POST[ "dmp_{$field}" ][ $i ] ) ? wp_unslash( $_POST[ "dmp_{$field}" ][ $i ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			self::set_field( $variation, $field, $raw );
		}
		// phpcs:enable

		DMP_Repricer::apply_to_product( $variation );
	}

	/**
	 * Validate and store one field on a product object.
	 *
	 * @param WC_Product $product Product or variation.
	 * @param string     $field   Field name.
	 * @param mixed      $raw     Unslashed posted value.
	 */
	private static function set_field( WC_Product $product, $field, $raw ) {
		if ( is_array( $raw ) ) {
			$raw = '';
		}

		if ( 'metal' === $field ) {
			$value = sanitize_key( $raw );

			if ( ! array_key_exists( $value, dmp_get_metals() ) ) {
				$value = '';
			}
		} else {
			$value = dmp_normalize_number( sanitize_text_field( $raw ) );

			if ( '' !== $value && 'markup' !== $field && (float) $value < 0 ) {
				$value = '';
			}

			if ( 'purity' === $field && '' !== $value && (float) $value > 1000 ) {
				$value = '1000';
			}
		}

		if ( '' === $value ) {
			$product->delete_meta_data( self::META_KEYS[ $field ] );
		} else {
			$product->update_meta_data( self::META_KEYS[ $field ], $value );
		}
	}
}

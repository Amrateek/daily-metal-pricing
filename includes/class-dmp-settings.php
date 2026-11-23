<?php
/**
 * WooCommerce → Metal prices admin page.
 *
 * @package DailyMetalPricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings page, price history and run status.
 */
final class DMP_Settings {

	const OPTION         = 'dmp_settings';
	const HISTORY_OPTION = 'dmp_price_history';
	const HISTORY_LIMIT  = 50;
	const PAGE           = 'dmp-metal-prices';
	const CAPABILITY     = 'manage_woocommerce';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 60 );
		add_action( 'admin_post_dmp_save_prices', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DMP_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Register the submenu page under WooCommerce.
	 */
	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Metal prices', 'daily-metal-pricing' ),
			__( 'Metal prices', 'daily-metal-pricing' ),
			self::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Add a "Metal prices" link on the Plugins screen.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Metal prices', 'daily-metal-pricing' ) . '</a>' );
		return $links;
	}

	/**
	 * Admin styles on our page and the product editor.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		$screen  = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_ours = false !== strpos( (string) $hook_suffix, self::PAGE );
		$is_edit = $screen && 'product' === $screen->id;

		if ( $is_ours || $is_edit ) {
			wp_enqueue_style( 'dmp-admin', DMP_URL . 'assets/css/admin.css', array(), DMP_VERSION );
		}
	}

	/**
	 * Save new metal prices and log the ones that changed.
	 *
	 * @param array $prices  Metal key => price per gram (numeric string or '').
	 * @param int   $user_id User making the change.
	 * @return string[] Metals whose price changed.
	 */
	public static function update_prices( array $prices, $user_id = 0 ) {
		$settings = dmp_get_settings();
		$metals   = dmp_get_metals();
		$changed  = array();
		$history  = get_option( self::HISTORY_OPTION, array() );
		$history  = is_array( $history ) ? $history : array();
		$now      = time();

		foreach ( $prices as $metal => $price ) {
			if ( ! isset( $metals[ $metal ] ) ) {
				continue;
			}

			$old = isset( $settings['prices'][ $metal ] ) ? (string) $settings['prices'][ $metal ] : '';
			$new = (string) $price;

			if ( '' !== $new && (float) $new <= 0 ) {
				$new = '';
			}

			if ( $old === $new ) {
				continue;
			}

			if ( '' === $new ) {
				unset( $settings['prices'][ $metal ], $settings['updated'][ $metal ] );
			} else {
				$settings['prices'][ $metal ]  = $new;
				$settings['updated'][ $metal ] = $now;
			}

			$changed[] = $metal;

			array_unshift(
				$history,
				array(
					'time'  => $now,
					'metal' => $metal,
					'old'   => $old,
					'new'   => $new,
					'user'  => (int) $user_id,
				)
			);
		}

		if ( $changed ) {
			update_option( self::OPTION, $settings, false );
			update_option( self::HISTORY_OPTION, array_slice( $history, 0, self::HISTORY_LIMIT ), false );

			/**
			 * Fires after metal prices change.
			 *
			 * @param string[] $changed  Metals whose price changed.
			 * @param array    $settings New settings.
			 */
			do_action( 'dmp_metal_prices_updated', $changed, $settings );
		}

		return $changed;
	}

	/**
	 * Handle the settings form.
	 */
	public static function handle_save() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change metal prices.', 'daily-metal-pricing' ), 403 );
		}

		check_admin_referer( 'dmp_save_prices' );

		$posted_prices = isset( $_POST['dmp_price'] ) && is_array( $_POST['dmp_price'] ) ? wp_unslash( $_POST['dmp_price'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$prices        = array();

		foreach ( $posted_prices as $metal => $raw ) {
			$prices[ sanitize_key( $metal ) ] = is_array( $raw ) ? '' : dmp_normalize_number( sanitize_text_field( $raw ) );
		}

		$changed = self::update_prices( $prices, get_current_user_id() );

		// Display settings.
		$settings     = dmp_get_settings();
		$old_rounding = array( $settings['rounding_step'], $settings['rounding_mode'], $settings['sale_prices'] );

		$step = isset( $_POST['dmp_rounding_step'] ) ? dmp_normalize_number( sanitize_text_field( wp_unslash( $_POST['dmp_rounding_step'] ) ) ) : '';
		$mode = isset( $_POST['dmp_rounding_mode'] ) ? sanitize_key( wp_unslash( $_POST['dmp_rounding_mode'] ) ) : 'nearest';
		$sale = isset( $_POST['dmp_sale_prices'] ) ? sanitize_key( wp_unslash( $_POST['dmp_sale_prices'] ) ) : 'keep';

		$settings['rounding_step'] = ( '' === $step || (float) $step < 0 ) ? 0 : (float) $step;
		$settings['rounding_mode'] = in_array( $mode, array( 'nearest', 'up' ), true ) ? $mode : 'nearest';
		$settings['sale_prices']   = in_array( $sale, array( 'keep', 'clear' ), true ) ? $sale : 'keep';

		update_option( self::OPTION, $settings, false );

		$rules_changed = array( $settings['rounding_step'], $settings['rounding_mode'], $settings['sale_prices'] ) !== $old_rounding;
		$reprice       = isset( $_POST['dmp_reprice'] ) || ( ! isset( $_POST['dmp_save_only'] ) && ( $changed || $rules_changed ) );

		$notice = array(
			'changed' => count( $changed ),
			'result'  => null,
		);

		if ( $reprice ) {
			$notice['result'] = DMP_Repricer::reprice_all( 'settings' );
		}

		set_transient( 'dmp_notice_' . get_current_user_id(), $notice, MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&dmp_saved=1' ) );
		exit;
	}

	/**
	 * Render the page.
	 */
	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$settings = dmp_get_settings();
		$metals   = dmp_get_metals();
		$currency = get_woocommerce_currency_symbol();
		$format   = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		echo '<div class="wrap dmp-wrap">';
		echo '<h1>' . esc_html__( 'Metal prices', 'daily-metal-pricing' ) . '</h1>';

		self::render_notice();

		echo '<p class="dmp-lead">' . esc_html__( 'Enter today\'s price per gram of pure metal. Every product with Metal pricing filled in is repriced from it.', 'daily-metal-pricing' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="dmp_save_prices" />';
		wp_nonce_field( 'dmp_save_prices' );

		echo '<div class="dmp-card">';
		echo '<h2>' . esc_html__( 'Today\'s prices', 'daily-metal-pricing' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ( $metals as $key => $label ) {
			$price   = isset( $settings['prices'][ $key ] ) ? $settings['prices'][ $key ] : '';
			$updated = isset( $settings['updated'][ $key ] ) ? (int) $settings['updated'][ $key ] : 0;
			$field   = 'dmp_price_' . $key;

			echo '<tr><th scope="row"><label for="' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></th><td>';
			echo '<span class="dmp-price-input"><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="regular-text dmp-number" id="' . esc_attr( $field ) . '" name="dmp_price[' . esc_attr( $key ) . ']" value="' . esc_attr( $price ) . '" /> ';
			/* translators: %s: currency symbol. */
			echo '<span class="dmp-unit">' . esc_html( sprintf( __( '%s per gram', 'daily-metal-pricing' ), $currency ) ) . '</span></span>';

			if ( $updated ) {
				/* translators: %s: date and time. */
				echo '<p class="description">' . esc_html( sprintf( __( 'Last changed %s', 'daily-metal-pricing' ), wp_date( $format, $updated ) ) ) . '</p>';
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Use the price of pure metal (999 / 24K). Purity is applied per product. You can type numbers with Arabic digits.', 'daily-metal-pricing' ) . '</p>';
		echo '</div>';

		echo '<div class="dmp-card">';
		echo '<h2>' . esc_html__( 'Pricing rules', 'daily-metal-pricing' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="dmp_rounding_step">' . esc_html__( 'Round prices to', 'daily-metal-pricing' ) . '</label></th><td>';
		echo '<input type="text" inputmode="decimal" dir="ltr" class="small-text dmp-number" id="dmp_rounding_step" name="dmp_rounding_step" value="' . esc_attr( $settings['rounding_step'] ? $settings['rounding_step'] : '' ) . '" placeholder="0" /> ';
		echo '<select name="dmp_rounding_mode" aria-label="' . esc_attr__( 'Rounding direction', 'daily-metal-pricing' ) . '">';
		echo '<option value="nearest"' . selected( $settings['rounding_mode'], 'nearest', false ) . '>' . esc_html__( 'nearest', 'daily-metal-pricing' ) . '</option>';
		echo '<option value="up"' . selected( $settings['rounding_mode'], 'up', false ) . '>' . esc_html__( 'always up', 'daily-metal-pricing' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'For example 5 rounds 1,237 to 1,235 (or up to 1,240). Leave empty for no rounding.', 'daily-metal-pricing' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="dmp_sale_prices">' . esc_html__( 'Sale prices', 'daily-metal-pricing' ) . '</label></th><td>';
		echo '<select id="dmp_sale_prices" name="dmp_sale_prices">';
		echo '<option value="keep"' . selected( $settings['sale_prices'], 'keep', false ) . '>' . esc_html__( 'Keep them when repricing', 'daily-metal-pricing' ) . '</option>';
		echo '<option value="clear"' . selected( $settings['sale_prices'], 'clear', false ) . '>' . esc_html__( 'Remove them when repricing', 'daily-metal-pricing' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'A sale price that ends up at or above the new regular price is always removed.', 'daily-metal-pricing' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';
		echo '</div>';

		echo '<p class="submit dmp-actions">';
		submit_button( __( 'Save and update product prices', 'daily-metal-pricing' ), 'primary', 'dmp_reprice', false );
		echo ' ';
		submit_button( __( 'Save without updating products', 'daily-metal-pricing' ), 'secondary', 'dmp_save_only', false );
		echo '</p>';
		echo '</form>';

		self::render_status( $format );
		self::render_formula();
		self::render_history( $format, $metals );

		echo '</div>';
	}

	/**
	 * Show the result of the last save.
	 */
	private static function render_notice() {
		$key    = 'dmp_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) ) {
			return;
		}

		delete_transient( $key );

		$result = $notice['result'];

		if ( ! is_array( $result ) ) {
			$message = __( 'Settings saved. Product prices were not changed.', 'daily-metal-pricing' );
		} elseif ( 'background' === $result['mode'] ) {
			$message = sprintf(
				/* translators: %d: number of products. */
				_n( 'Settings saved. %d product is being repriced in the background.', 'Settings saved. %d products are being repriced in the background.', $result['products'], 'daily-metal-pricing' ),
				$result['products']
			);
		} elseif ( 0 === $result['products'] ) {
			$message = __( 'Settings saved. No products use metal pricing yet. Open a product and fill in its Metal pricing tab.', 'daily-metal-pricing' );
		} else {
			$message = sprintf(
				/* translators: 1: number of prices that changed, 2: number of products checked. */
				_n( 'Settings saved. %1$d price changed (%2$d products checked).', 'Settings saved. %1$d prices changed (%2$d products checked).', $result['updated'], 'daily-metal-pricing' ),
				$result['updated'],
				$result['products']
			);
		}

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Status of the last full repricing run.
	 *
	 * @param string $format Date format.
	 */
	private static function render_status( $format ) {
		$status = DMP_Repricer::get_status();

		if ( 'never' === $status['state'] ) {
			return;
		}

		echo '<div class="dmp-card dmp-status">';
		echo '<h2>' . esc_html__( 'Last update', 'daily-metal-pricing' ) . '</h2><p>';

		if ( 'running' === $status['state'] ) {
			echo esc_html(
				sprintf(
					/* translators: 1: products done, 2: total products. */
					__( 'Repricing in the background: %1$d of %2$d products done. Refresh this page to check progress.', 'daily-metal-pricing' ),
					$status['done'],
					$status['total']
				)
			);
		} else {
			echo esc_html(
				sprintf(
					/* translators: 1: date and time, 2: number of prices that changed, 3: number of products checked. */
					_n( '%1$s — %2$d price changed (%3$d products checked).', '%1$s — %2$d prices changed (%3$d products checked).', (int) $status['updated'], 'daily-metal-pricing' ),
					wp_date( $format, (int) $status['finished'] ),
					$status['updated'],
					$status['total']
				)
			);
		}

		echo '</p></div>';
	}

	/**
	 * Explain the formula.
	 */
	private static function render_formula() {
		echo '<div class="dmp-card dmp-formula">';
		echo '<h2>' . esc_html__( 'How prices are calculated', 'daily-metal-pricing' ) . '</h2>';
		echo '<ol>';
		echo '<li>' . esc_html__( 'Metal value = price per gram × weight × purity ÷ 1000', 'daily-metal-pricing' ) . '</li>';
		echo '<li>' . esc_html__( 'Add the making charge (per gram × weight) and the fixed charge', 'daily-metal-pricing' ) . '</li>';
		echo '<li>' . esc_html__( 'Add the markup percentage', 'daily-metal-pricing' ) . '</li>';
		echo '<li>' . esc_html__( 'Round, then save as the regular price', 'daily-metal-pricing' ) . '</li>';
		echo '</ol>';
		echo '<p class="description">' . esc_html__( 'Example: 5 g of 21K gold (purity 875) at 4,000 per gram is 17,500. With a making charge of 150 per gram (750) and 10% markup, the price is 20,075.', 'daily-metal-pricing' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Recent price changes.
	 *
	 * @param string $format Date format.
	 * @param array  $metals Metal labels.
	 */
	private static function render_history( $format, $metals ) {
		$history = get_option( self::HISTORY_OPTION, array() );

		if ( ! is_array( $history ) || ! $history ) {
			return;
		}

		echo '<div class="dmp-card dmp-history">';
		echo '<h2>' . esc_html__( 'Price history', 'daily-metal-pricing' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Date', 'daily-metal-pricing' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Metal', 'daily-metal-pricing' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Old price', 'daily-metal-pricing' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'New price', 'daily-metal-pricing' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Changed by', 'daily-metal-pricing' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( array_slice( $history, 0, 20 ) as $row ) {
			$user  = ! empty( $row['user'] ) ? get_userdata( (int) $row['user'] ) : false;
			$who   = $user ? $user->display_name : __( 'System', 'daily-metal-pricing' );
			$metal = isset( $metals[ $row['metal'] ] ) ? $metals[ $row['metal'] ] : $row['metal'];

			echo '<tr>';
			echo '<td>' . esc_html( wp_date( $format, (int) $row['time'] ) ) . '</td>';
			echo '<td>' . esc_html( $metal ) . '</td>';
			echo '<td>' . ( '' === $row['old'] ? '—' : wp_kses_post( wc_price( $row['old'] ) ) ) . '</td>';
			echo '<td>' . ( '' === $row['new'] ? '—' : wp_kses_post( wc_price( $row['new'] ) ) ) . '</td>';
			echo '<td>' . esc_html( $who ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}
}

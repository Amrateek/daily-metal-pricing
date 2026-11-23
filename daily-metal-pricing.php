<?php
/**
 * Plugin Name:          Daily Metal Pricing for WooCommerce
 * Plugin URI:           https://github.com/Amrateek/daily-metal-pricing
 * Description:          Price gold, silver and platinum products from today's metal price. Enter the price per gram once, and every product is repriced from its weight, purity and making charges.
 * Version:              1.0.0
 * Requires at least:    6.5
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               Amr Ateek
 * Author URI:           https://github.com/Amrateek
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          daily-metal-pricing
 * Domain Path:          /languages
 * WC requires at least: 8.0
 * WC tested up to:      11.3
 *
 * @package DailyMetalPricing
 */

defined( 'ABSPATH' ) || exit;

define( 'DMP_VERSION', '1.0.0' );
define( 'DMP_FILE', __FILE__ );
define( 'DMP_PATH', plugin_dir_path( __FILE__ ) );
define( 'DMP_URL', plugin_dir_url( __FILE__ ) );

require_once DMP_PATH . 'includes/class-dmp-calculator.php';
require_once DMP_PATH . 'includes/functions.php';
require_once DMP_PATH . 'includes/class-dmp-repricer.php';
require_once DMP_PATH . 'includes/class-dmp-product-fields.php';
require_once DMP_PATH . 'includes/class-dmp-settings.php';

/**
 * Declare compatibility with WooCommerce features.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DMP_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', DMP_FILE, true );
		}
	}
);

/**
 * Load translations for installs that don't get them from WordPress.org.
 */
add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'daily-metal-pricing', false, dirname( plugin_basename( DMP_FILE ) ) . '/languages' );
	}
);

/**
 * Boot the plugin once WooCommerce is available.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', 'dmp_missing_woocommerce_notice' );
			return;
		}

		DMP_Repricer::init();
		DMP_Product_Fields::init();
		DMP_Settings::init();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once DMP_PATH . 'includes/class-dmp-cli.php';
			WP_CLI::add_command( 'metal-pricing', 'DMP_CLI' );
		}
	}
);

/**
 * Admin notice shown when WooCommerce is not active.
 */
function dmp_missing_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>' . esc_html__( 'Daily Metal Pricing needs WooCommerce to be installed and active.', 'daily-metal-pricing' ) . '</p></div>';
}

<?php
/**
 * Remove plugin options on uninstall.
 *
 * Product pricing fields (_dmp_* meta) are kept on purpose, so reinstalling
 * the plugin restores every product's weight, purity and charges.
 *
 * @package DailyMetalPricing
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'dmp_settings' );
delete_option( 'dmp_price_history' );
delete_option( 'dmp_reprice_status' );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'dmp_reprice_batch', array(), 'daily-metal-pricing' );
}

=== Daily Metal Pricing for WooCommerce ===
Contributors: amrateek
Tags: gold price, silver price, jewelry, woocommerce, dynamic pricing
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Price gold, silver and platinum products from today's metal price, by weight, purity and making charges.

== Description ==

Jewelry prices follow the metal market. Updating every product by hand each time gold or silver moves isn't realistic.

With Daily Metal Pricing, you enter today's price per gram of pure metal once. Every product that has Metal pricing filled in is repriced from its own weight, purity, making charge, fixed charge and markup.

= Features =

* Set prices for gold, silver and platinum on WooCommerce → Metal prices.
* A Metal pricing tab on simple and variable products: weight, purity (parts per thousand), making charge per gram, fixed charge and markup.
* Variations inherit the product's values, so you only fill in what differs, such as weight.
* A price breakdown in the product editor.
* Rounding to any step (nearest or always up).
* Keep or remove sale prices when repricing. A sale price at or above the new regular price is always removed.
* Large catalogs are repriced in the background with Action Scheduler.
* History of the last 50 metal price changes.
* WP-CLI: `wp metal-pricing set-price gold 4000 --reprice`.
* Full Arabic translation, RTL-safe admin, and Arabic-Indic digit input.

= How prices are calculated =

1. Metal value = price per gram × weight × purity ÷ 1000
2. Add the making charge (per gram × weight) and the fixed charge
3. Add the markup percentage
4. Round, then save as the regular price

Example: 5 g of 21K gold (purity 875) at 4,000 per gram is 17,500. With a making charge of 150 per gram (750) and a 10% markup, the price is 20,075.

== Installation ==

1. Upload the plugin and activate it. WooCommerce must be active.
2. Go to WooCommerce → Metal prices and enter today's prices.
3. Open a product, go to the Metal pricing tab, choose the metal and fill in weight and purity, then update the product.
4. Whenever the market moves, change the price and click "Save and update product prices".

== Frequently Asked Questions ==

= Does it fetch live prices automatically? =

Not in this version. You stay in control of the price you sell at. You can automate it with WP-CLI and your own price source: `wp metal-pricing set-price silver 52.4 --reprice`.

= Which price do I enter, 24K or 21K? =

Always the price of pure metal (999 / 24K). Purity is applied per product.

= Will it touch products I price by hand? =

No. Only products with a metal selected on their Metal pricing tab are repriced.

= What happens to the fields if I uninstall? =

Settings and history are removed. Product fields are kept, so reinstalling restores them.

== Changelog ==

= 1.0.0 =
* First public release.

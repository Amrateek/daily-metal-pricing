# Daily Metal Pricing for WooCommerce

Price gold, silver and platinum jewelry from **today's metal price**. Enter the price per gram once, and every product in your store is repriced from its weight, purity and making charges.

It started as a small snippet for a silver jewelry store with 160+ products, where updating prices by hand every time the silver market moved wasn't realistic. This is the cleaned-up, general version.

**عربي:** الإضافة مترجمة بالكامل للعربية، وتعمل مع لوحة تحكم ووردبريس من اليمين لليسار، وتقبل كتابة الأسعار بالأرقام العربية (٠١٢٣…).

## Features

- **One price, whole catalog.** Set the price per gram of pure gold, silver or platinum on WooCommerce → Metal prices. Every metal-priced product updates.
- **Per-product inputs.** Weight, purity, making charge per gram, fixed charge and markup, on a "Metal pricing" tab in the product editor.
- **Variable products.** Set defaults on the product (say, 21K gold, 150 per gram making charge), then give each variation its own weight. Empty variation fields inherit from the product.
- **Price breakdown.** The product editor shows metal value, making charge, fixed charge, markup and the final price.
- **Rounding.** Round to the nearest 1, 5, 10 or any step, or always round up.
- **Sale prices handled.** Keep or remove them on repricing. A sale price that would end up above the new regular price is always removed, so customers never see a fake discount.
- **Large catalogs.** Up to 200 products are repriced right away. Bigger stores are repriced in background batches using WooCommerce's Action Scheduler.
- **Price history.** The last 50 metal price changes, with who made them.
- **Arabic and RTL.** Full Arabic translation, RTL-safe admin styles, and Arabic-Indic digit input.
- **WP-CLI.** Set prices and reprice from the command line or a cron job.

## How prices are calculated

```
metal value = price per gram × weight × purity ÷ 1000
subtotal    = metal value + (making charge per gram × weight) + fixed charge
price       = subtotal × (1 + markup ÷ 100), then rounded
```

The metal price you enter is for **pure** metal (999 / 24K). Purity is set per product in parts per thousand:

| Purity | Value |
|---|---|
| 24K gold | 999 |
| 22K gold | 916 |
| 21K gold | 875 |
| 18K gold | 750 |
| Sterling silver | 925 |
| Platinum 950 | 950 |

**Example:** 5 g of 21K gold at 4,000 per gram → 4,000 × 5 × 0.875 = 17,500. Add a making charge of 150 per gram (750) → 18,250. Add a 10% markup → **20,075**.

The result is saved as the product's regular price, so sorting, filters, feeds and caching all keep working.

## Installation

1. Download the latest release zip (or clone this repo into `wp-content/plugins/daily-metal-pricing`).
2. In WordPress, go to Plugins → Add New → Upload Plugin, upload the zip and activate it. WooCommerce must be active.
3. Go to **WooCommerce → Metal prices** and enter today's prices.
4. Open a product, go to the **Metal pricing** tab, choose the metal and fill in the weight and purity. Update the product.

From then on, change the metal price on the Metal prices page and click **Save and update product prices**.

**Requirements:** WordPress 6.5+, WooCommerce 8.0+, PHP 7.4+.

## WP-CLI

```bash
wp metal-pricing prices                        # show today's prices
wp metal-pricing set-price gold 4000 --reprice # set a price and reprice everything
wp metal-pricing reprice                       # reprice all metal-priced products
```

You can pull a price from your own source and run `set-price --reprice` from a server cron job.

## For developers

| Hook | Type | Use it to |
|---|---|---|
| `dmp_metals` | filter | Add metals, e.g. `$metals['palladium'] = 'Palladium';` |
| `dmp_purity_presets` | filter | Change the purity suggestions shown in the editor |
| `dmp_calculated_price` | filter | Adjust the final price (args: `$price, $product, $inputs, $parts`) |
| `dmp_metal_prices_updated` | action | React to a price change, e.g. clear a page cache (args: `$changed_metals, $settings`) |

Product fields are stored as post meta: `_dmp_metal`, `_dmp_weight`, `_dmp_purity`, `_dmp_labor_per_gram`, `_dmp_fixed_charge`, `_dmp_markup`. Uninstalling removes the plugin's settings but keeps these fields, so reinstalling restores every product.

## Translations

The template is in [`languages/daily-metal-pricing.pot`](languages/daily-metal-pricing.pot). Arabic is included. To add a language, translate the `.pot` with [Poedit](https://poedit.net/) and open a pull request with the `.po` and `.mo` files.

## Contributing

Issues and pull requests are welcome, especially:

- translations into more languages
- testing with other currencies and store setups
- a live price feed option (opt-in, with a source you can choose)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

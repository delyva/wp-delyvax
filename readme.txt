=== delyvax ===
Contributors: suhaimihz, delyva
Tags: delyva, shipping, delivery, courier
Requires at least: 5.4
Tested up to: 5.7
Stable tag: 1.1.23
Requires PHP: 7.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

The official Delyva plugin helps store owners to integrate WooCommerce store with [Delyva](https://delyva.com) delivery management platform for seamless service comparison and order processing.

== Description ==

The official Delyva plugin helps store owners to integrate WooCommerce store with [Delyva](https://delyva.com) delivery management platform for seamless service comparison and order processing.

Delyva WooCommerce plugin
- Create shipment automatically on 'payment completed' or manual click to fulfil order.
- Fulfil shipment with 'Preparing' button click.
- Edit order > Fulfil order
- Edit order > Print label
- Edit order > Track shipment
- Auto-status updates with Web-hook for preparing, start-collecting, collected, failed-collection, start-delivery, delivered, and failed-delivery.

== Installation ==

1. Get your Company id, User id, Customer id, and API Key from your delivery service provider's Customer web app > settings > API Integrations.
2. In your Woocommerce store admin,  go to Woocommerce > Settings > Shipping > DelyvaX
3. Insert your delivery service provider's Company code, Company id, User id, Customer id, and API Key.
4. Configure the settings as per your requirements.

== Changelog ==

= 1.1.23 =
*Release Date - 24 Dec 2021*

* Fixed checkout weight to include inventory quantity.

= 1.1.22 =
*Release Date - 24 Nov 2021*

* Fixed Dokan vendor's email address for delivery order creation.

= 1.1.21 =
*Release Date - 8 Nov 2021*

* Option for Multi-vendor Dokan and WCFM. After updates, Dokan and WCFM users need to set 'Multi-vendor system' to Dokan or WCFM in the plugin setting.

= 1.1.20 =
*Release Date - 7 Oct 2021*

* Fixed hidden print label button and service name.

= 1.1.19 =
*Release Date - 7 Sep 2021*

* Remove min product weight validation.

= 1.1.18 =
*Release Date - 29 Aug 2021*

* Add supports for multisite.

= 1.1.17 =
*Release Date - 27 Aug 2021*

* Fix tracking not update for delivery order fulfilled in customer web app.

= 1.1.16 =
*Release Date - 26 Aug 2021*

* Added processing time for auto schedule delivery order creation next X day(s) at YY time.

= 1.1.15 =
*Release Date - 18 Aug 2021*

* Product variant handling.

= 1.1.14 =
*Release Date - 16 Aug 2021*

* Bug fixes for order from WCFM vendor information.

= 1.1.13 =
*Release Date - 08 Aug 2021*

* Bug fixes for webhook delivery order status updates with tracking no

= 1.1.12 =
*Release Date - 27 Jul 2021*

* Bug fixes for vendor address multi-vendor Dokan and WCFM. Currently only supports 1 order 1 vendor.

= 1.1.11 =
*Release Date - 18 June 2021*

* Source of referral to identify delivery orders comes from woocommerce or web design agency

For older changelog entries, please see the [additional changelog.txt file](https://plugins.svn.wordpress.org/delyvax/trunk/changelog.txt) delivered with the plugin.

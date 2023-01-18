=== delyvax ===
Contributors: suhaimihz, delyva
Tags: delyva, shipping, delivery, courier
Requires at least: 5.4
Tested up to: 6.1
Stable tag: 1.1.39
Requires PHP: 7.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

The official Delyva plugin helps store owners to integrate WooCommerce store with [Delyva](https://delyva.com) delivery management platform for seamless service comparison and order processing.

== Description ==

The official Delyva plugin helps store owners to integrate WooCommerce store with [Delyva](https://delyva.com) delivery management platform for seamless service comparison and order processing.

Delyva WooCommerce plugin
- Create delivery order automatically after order has been paid or manually fulfil order by changing the status to Preparing.
- Order list > Fulfil order with change order status to 'Preparing'.
- Edit order > Fulfil order or change status to Preparing.
- Edit order > Print label,
- Edit order > Track shipment.
- Auto-status updates with Web-hook for preparing, start-collecting, collected, failed-collection, start-delivery, delivered, and failed-delivery.

== Installation ==

1. Get your Company id, User id, Customer id, and API Key from your delivery service provider's Customer web app > settings > API Integrations.
2. In your Woocommerce store admin,  go to Woocommerce > Settings > Shipping > DelyvaX
3. Insert your delivery service provider's Company code, Company id, User id, Customer id, and API Key.
4. Configure the settings as per your requirements.
5. Check Settings > General > Timezone. Make sure your timezone is set to city name instead of UTC+X.  e.g. Kuala Lumpur, instead of UTC+8.

== Changelog ==

= 1.1.41 =
*Release Date - 18th January 2023*

* Bug-fixes for non-numeric or empty dimension or weight.

= 1.1.40 =
*Release Date - 16th January 2023*

* Bug-fixes for shipping and billing address.

= 1.1.39 =
*Release Date - 14th December 2022*

* Bug-fixes for checkout rates.

= 1.1.38 =
*Release Date - 29th November 2022*

* Supports PHP 8.0, 8.1

= 1.1.37 =
*Release Date - 3rd November 2022*

* Bug-fixes.

= 1.1.36 =
*Release Date - 3rd November 2022*

* Limit number of delivery service options at the checkout page.

= 1.1.35 =
*Release Date - 31st October 2022*

* Better support for COD & Insurance Cover.

For older changelog entries, please see the [additional changelog.txt file](https://plugins.svn.wordpress.org/delyvax/trunk/changelog.txt) delivered with the plugin.

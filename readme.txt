=== delyvax ===
Contributors: suhaimihz, delyva
Tags: delyva, shipping, delivery, courier
Requires at least: 5.4
Tested up to: 5.7
Stable tag: 1.1.28
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

= 1.1.28 =
*Release Date - 28th March 2022*

* Can exclude description in the note.

= 1.1.27 =
*Release Date - 8th March 2022*

* Change order status to Package is Ready after delivery order has been created.

= 1.1.26 =
*Release Date - 3rd February 2022*

* Rename settings labels for After order has been paid and after order marked as preparing.

= 1.1.25 =
*Release Date - 29 Dec 2021*

* Fixed weight and dimension unit. Default to kg.

= 1.1.24 =
*Release Date - 27 Dec 2021*

* Fixed weight and dimension unit.

= 1.1.23 =
*Release Date - 24 Dec 2021*

* Fixed checkout weight to include inventory quantity.

= 1.1.22 =
*Release Date - 24 Nov 2021*

* Fixed Dokan vendor's email address for delivery order creation.

For older changelog entries, please see the [additional changelog.txt file](https://plugins.svn.wordpress.org/delyvax/trunk/changelog.txt) delivered with the plugin.

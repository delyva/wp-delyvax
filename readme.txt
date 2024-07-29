=== delyvax ===
Contributors: suhaimihz, delyva
Tags: delyva, shipping, delivery, courier
Requires at least: 5.4
Tested up to: 6.1
Stable tag: 1.1.55
Requires PHP: 7.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

The official Delyva plugin helps store owners to integrate WooCommerce store with [Delyva](https://delyva.com) delivery management platform for seamless service comparison and order processing.

== Description ==

The official Delyva plugin helps store owners to integrate WooCommerce store with [Delyva](https://delyva.com) delivery management platform for seamless service comparison and order processing.

Delyva WooCommerce plugin
- Create delivery order automatically after order has been paid or manually fulfil order by changing the status to Preparing.
- Order list > Fulfil order with change order status to 'Preparing' and tracking number will be displayed.
- Edit order > Select service and fulfil order or change status to Preparing.
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

= 1.1.55 =
*Release Date - 30th July 2024*

* Fix missing fulfil button. Ignore only virtual orders.

= 1.1.54 =
*Release Date - 28th July 2024*

* Fix missing orders after automated status updates.

= 1.1.53 =
*Release Date - 4th February 2024*

* Another fixes for missing orders on ready to collect status.

= 1.1.52 =
*Release Date - 30th January 2024*

* Fixes for missing orders on ready to collect status.

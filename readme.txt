=== delyvax ===
Contributors: suhaimihz, delyva
Tags: delyva, shipping, delivery, courier
Requires at least: 5.4
Tested up to: 6.1
Stable tag: 1.1.52
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

= 1.1.52 =
*Release Date - 30th January 2024*

* Fixes for missing orders on ready to collect status.

= 1.1.51 =
*Release Date - 12th July 2023*

* Fixes regarding non-kg product weight and product variances.

= 1.1.50 =
*Release Date - 29th May 2023*

* Ignore localpickup shipping method and fix free shipping conditions.

= 1.1.49 =
*Release Date - 31st March 2023*

* Bug fixes for empty weight, default to 1kg.

= 1.1.48 =
*Release Date - 21st March 2023*

* Bug fixes for some use case.

= 1.1.47 =
*Release Date - 17th March 2023*

* Bug fixes regarding cancel order and cancel delivery.

= 1.1.46 =
*Release Date - 13th March 2023*

* Select courier on view order page. Ignore create order for payment method 'pos_cash'.

= 1.1.45 =
*Release Date - 2nd March 2023*

* Hot fix shipping phone functions.

= 1.1.44 =
*Release Date - 27th February 2023*

* Display tracking number at my orders page, orders page. 
* Option to enable shipping phone number at the checkout page.
* Option to cancel the order if the delivery is cancelled.
* Option to cancel the delivery if the order is cancelled.
* Free shipping take into account total after discount amount.

= 1.1.43 =
*Release Date - 25th January 2023*

* Send WooCommerce order id as reference no.

= 1.1.42 =
*Release Date - 19th January 2023*

* Add currency conversion rate for live checkout shipping rates.

= 1.1.41 =
*Release Date - 18th January 2023*

* Bug-fixes for non-numeric or empty dimension or weight.

= 1.1.40 =
*Release Date - 16th January 2023*

* Bug-fixes for shipping and billing address.

For older changelog entries, please see the [additional changelog.txt file](https://plugins.svn.wordpress.org/delyvax/trunk/changelog.txt) delivered with the plugin.

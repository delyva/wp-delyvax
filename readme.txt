=== delyvax ===
Contributors: suhaimihz, delyva
Tags: delyva, shipping, delivery, courier
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.2.1
Requires PHP: 7.4
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

== Requirements ==

* WordPress 6.0 or higher
* WooCommerce 7.0 or higher
* PHP 7.4 or higher

== Changelog ==

= 1.2.1 =
*Release Date - 15 Jul 2025*

* Fix order display in WCFM

= 1.2.0 =
*Release Date - 2nd April 2025*

* New: Bulk Label Print. You can now bulk label print!
* Optimized compatibility with High-Performance Order Storage (HPOS)
* Fix phone number validation for block-based checkout
* Display error directly in order lists for easier visibility
* Remove deprecated DelyvaX API method
* Optimize webhook subscription mechanism

For older changelog entries, please see the [additional changelog.txt file](https://plugins.svn.wordpress.org/delyvax/trunk/changelog.txt) delivered with the plugin.

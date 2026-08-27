=== Plugin Name ===
PlugnPay API Credit Card Payment Gateway For WooCommerce
Contributors: PlugnPay
Site link: https://www.plugnpay.com
Tags: woocommerce, plugnpay, payment, gateway, API, CC, credit card, debit card
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 1.2.1
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

Plugin extends the functionality of WooCommerce to accept payments from credit/debit cards on your checkout page, using PlugnPay's API payment method.

== Description ==

PlugnPay API Credit Card Payment Gateway for WooCommerce makes your website ready to use PlugnPay's API payment method, to accept credit/debit cards on your ecommerce store checkout page.

PlugnPay is a widely used payment gateway to process payments online and accepts Visa, MasterCard, Discover and other variants payment options.

Requires WordPress 6.0+, WooCommerce 8.0+, PHP 8.2+, and HTTPS on the storefront. Collecting cards onsite increases PCI scope versus hosted Smart Screens.

= Features =
Few features of this plugin:

1. Accept cards on your website (classic checkout)
2. No redirecting to other URL
3. Easy to install and configure
4. Option to configure success & failure message
5. HTTPS required; Authorization Verification Hash (SHA-256) required
6. Remote Client Password and hash keys stored encrypted at rest
7. Optional Giftcard split payment ability
8. Optional divert payments based upon currency selected

== Installation ==
Easy steps to install the plugin:

To install the plugin:

1. Login to your WordPress admin area
2. Go to Plugins => Add new
3. Click on the 'Upload Plugin' button
4. Click on the 'Browse' button & select this payment module's zip file
5. Click on the 'Install Now' button.
6. Once installed, click on the 'Activate Button'

To configure this checkout option:

7. Go to WooCommerce => Settings
8. On the 'Settings' page, select 'Checkout' tab.
9. Under 'Payment Gateways' you will find all the available gateways, select 'PlugnPay API CC' option
10. On this page you will find options to configure the plugin for use with WooCommerce
11. Modify the configurable elements accordingly
[* NOTE: At minimum, enable the gateway, enter Gateway Account, Remote Client Password, Authorization Hash, and Authorization Hash Key. Matching settings must be enabled in PlugnPay Merchant Admin.]

---------------------------------------------
Enable/Disable: Used to enable/disable this payment ability, once the plug-in itself has been activated.

Title: This will appear on checkout page as name for this payment gateway

Description: This will appear on checkout page as description for this payment gateway

Gateway Account: This is the username provided to you by PlugnPay. (Note: This is the same username used to login to the PlugnPay Merchant Administration area.)

Remote Client Password: This is an API password you explicitly set within your PlugnPay account's Security Administration area. (Note: This is NOT the same password used to login to the PlugnPay Merchant Administration area.)

Transaction Success Message: This message will appear upon successful transaction. You can customize this message as per your need.

Transaction Failed Message: This message will appear when transaction will get failed/declined at payment gateway.

Transaction Settlement: Allows you to specify if approved transactions should be automatically marked for settlement.


Authorization Hash: Required. Enable Authorization Verification Hash (SHA-256) in both this module and your PlugnPay account.

Authorization Hash Key: Required. Enter the Verification Key from PlugnPay Security Administration. Leave blank to keep the current key. If using Divert Currency, list each currency with its associated key [i.e. USD:key1,BBD:key2,CAD:key3].

Authorization Hash Fields: Fieldset to use with authhash validation. (NOTE: This must match the fields selected with your PlugnPay account.)

Giftcard Acceptance: Allows you to accept Giftcards at time of checkout & process it as a split-payment. (Note: You must have Giftcard ability enabled in your PlugnPay account to use this.)

Giftcard Description: This is what appears on checkout page as the description for the giftcard option.

Giftcard Note: This is what appears on checkout page below the giftcard fields.

Divert Currency: Use to enable/disable ability to redirect payments to a different gateway account for specific currency types.

Divert Accounts: List currency code, username & Remote Client Password to divert specific payments to. [i.e. USD:username1:abcd1234,BBD:username2:efgh2345,CAD:username3:ijkl3456] Currency codes not listed use default Gateway Account.
---------------------------------------------

12. once completed. click on the 'Save Changes' button to make those adjustments active immediately.


== Frequently Asked Questions ==
= Is SSL Required to use this plugin? =
Yes, SSL is required.

= Can anyone enable/process giftcards? =
No, a merchant must have a giftcard account setup with a supported merchant processor & our gateway before enabling this ability.

== Screenshots ==

== Changelog ==
= 1.2.1 - 2026-08-27 =
* Require PHP 8.2+ (PCI DSS 6.3.3; PHP 8.1 is past vendor security support)
* Group gateway settings into labeled sections; hide authorization hash, giftcard, and divert fields until enabled

= 1.2.0 =
* PCI DSS: require Authorization Hash (SHA-256) and block checkout if it is not configured
* PCI DSS: require PHP 8.1+ and HTTPS on the storefront (local/development excepted)
* PCI DSS: encrypt Remote Client Password and hash keys at rest; do not echo secrets in admin password fields
* PCI DSS: restrict cURL to HTTPS; require WooCommerce 8.0+
* Add automated tests for hash and secret storage

= 1.1.9 =
* Modernize WooCommerce order API usage (wc_get_order, getters, HPOS compatibility)
* Fix auth hash amount mismatch with card-amount field
* Enable SSL certificate verification for gateway requests
* Fix validate_fields and process_payment return handling
* Sanitize posted payment fields; improve expiry validation
* Show card fields even when description is empty; make gift card fields optional
* Use password field for remote client password; use WC_Geolocation for IP detection
* Add cURL timeout and connection error handling

= 1.1.8 = 
* Added Account Code Field

= 1.1.7 = 
* Extended Divert Currency to set Remote Client Password per account listed
* Extended Authorization Verification Hash Key to store different keys for each currency supported with Divert Currency
* Realigned how input fields are presented to users

= 1.1.6 = 
* Fixed Authorization Hash Fields order

= 1.1.5 =
* Added Authorization Verification Hash ability

= 1.1.4 =
* Added Divert Currency ability
* Minor bug fixes & code clean-up
* Added dynamic cards type display code

= 1.1.3 =
* Enhanced currency support, to work with multi-currency plug-ins
* Cleaned up some code & documentation
* Added customer's IP address to gateway API call

= 1.1.2 =
* Added optional Giftcard split payment ability
* Add missing Configurable Elements to above installation info

= 1.1.1 =
* Checkout return URL adjustment

= 1.1.0 =
* Initial Public Version

= 1.0.0 =
* First Version

== Upgrade Notice ==
* Upgrade is required for versions below 1.2.0. Authorization Hash (SHA-256) and HTTPS are now required.
* 1.2.1 (2026-08-27) requires PHP 8.2 or higher.

== Arbitrary section ==


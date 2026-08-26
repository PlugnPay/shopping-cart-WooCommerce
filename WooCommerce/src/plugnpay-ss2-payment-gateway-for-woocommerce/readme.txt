=== Plugin Name ===
PlugnPay SSv2 Payment Gateway For WooCommerce
Contributors: PlugnPay
Site link: https://www.plugnpay.com
Tags: woocommerce plugnpay.com, plugnpay.com, payment gateway, woocommerce, woocommerce payment gateway
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.1.10
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments on your WooCommerce website with PlugnPay’s secure hosted Smart Screens.

== Description ==

PlugnPay SSv2 Payment Gateway for WooCommerce redirects customers to PlugnPay hosted Smart Screens. WooCommerce does not collect card data on the storefront.

Requires WordPress 6.0+, WooCommerce 8.0+, PHP 8.1+, and HTTPS on the storefront.

= Features =

1. HTTPS required for checkout and payment return URLs
2. Card data is collected on PlugnPay’s servers, not WooCommerce
3. Authorization Verification Hash (SHA-256) required
4. Payment callbacks accepted only from PlugnPay callback server IPs
5. Optional Response Verification Hash on callbacks
6. Easy to install and configure
7. Option to configure success & failure message
8. 3D Secure checkout capable for approved merchants
9. Optional Giftcard split payment ability
10. Optional divert payments based upon currency selected

== Installation ==

To install the plugin:

1. Login to your WordPress admin area
2. Go to Plugins => Add new
3. Click on the 'Upload Plugin' button
4. Click on the 'Browse' button & select this payment module's zip file
5. Click on the 'Install Now' button.
6. Once installed, click on the 'Activate Button'

To configure this checkout option:

7. Go to WooCommerce => Settings
8. On the 'Settings' page, select 'Payments'
9. Select 'PlugnPay SSv2 Payment Gateway For WooCommerce'
10. Configure the plugin. At minimum: enable the gateway, enter Gateway Username, enable Authorization Hash, and enter the Authorization Hash Key. Matching settings must be enabled in PlugnPay Merchant Admin.

---------------------------------------------
Enable/Disable: Used to enable/disable this payment ability, once the plug-in itself has been activated.

Title: This will appear on checkout page as name for this payment gateway

Description: This will appear on checkout page as description for this payment gateway

Gateway Username: This is the username provided to you by PlugnPay. (Note: This is the same username you use to login to the PlugnPay Merchant Administration area.)

Cards Allowed: This controls which card types are presented to the customer as a payment options. (Note: To prevent issues, only list cards types you actually have obtained a merchant account for.)

Transaction Success Message: This message will appear upon the transaction is successful. You can customize this message as per your needs.

Transaction Failed Message: This message will appear upon the transaction is declined/failed. You can customize this message as per your needs.

Transaction Settlement: Select if you'd like the cart to mark approved payments for settlement for you.

Authorization Hash: Required. Enable Authorization Verification Hash (SHA-256) in both this module and your PlugnPay account.

Authorization Hash Key: Required. Enter the Verification Key from PlugnPay Security Administration. Leave blank to keep the current key. If using Divert Currency, list each currency with its associated key [i.e. USD:key1,BBD:key2,CAD:key3].

Authorization Hash Fields: Select a fieldset to validate upon and configure your PlugnPay account to match.

Response Verification Hash Key: Recommended. Enter the Response Verification Hash key from PlugnPay Security Administration. Leave blank to keep the current key.

Giftcard Acceptance: Allows you to accept Giftcards at time of checkout & process it as a split-payment. (Note: You must have Giftcard ability enabled in your PlugnPay account to use this.)

Divert Currency: Use to enable/disable ability to redirect payments to a different gateway account for specific currency types.

Divert Accounts: List currency code & username to divert specific payments to. [i.e. USD:username1,BBD:username2,CAD:username3]  Currency codes not listed will use default Gateway Account.
---------------------------------------------

12. once completed. click on the 'Save Changes' button to make those adjustments active immediately.


== Frequently Asked Questions ==
= Is SSL Required to use this plugin? =
Yes. HTTPS is required for checkout and payment return URLs.

== Screenshots ==
* None Available

== Changelog ==
= 1.1.10 =
* Fix Smart Screens response page theme: hidden callbacks return 200 with no WordPress HTML
* Send shopper browser returns to a themed WooCommerce page instead of a blank 403
* Add continue URL on the hosted receipt to the WooCommerce order-received page

= 1.1.9 =
* PCI DSS: require Authorization Hash (SHA-256) and block checkout if it is not configured
* PCI DSS: require PHP 8.1+ and HTTPS on the storefront (local/development excepted)
* PCI DSS: encrypt hash keys at rest; do not echo secrets in admin password fields
* PCI DSS: validate callback payment method, amount, and currency
* PCI DSS: optional Response Verification Hash on callbacks
* Accept payment callbacks only from PlugnPay callback server IPs
* Add automated tests for hash, IP allowlist, amounts, and secret storage
* Require HTTPS documentation; remove “SSL is not required”

= 1.1.8.6 =
* Use SHA-256 (not MD5) for Authorization Verification Hash (pt_transaction_hash)
* PCI DSS: replace weak hashing with SHA-256 per PlugnPay-supported algorithms
* Accept payment callbacks only from PlugnPay callback server IPs

= 1.1.8.5 =
* Security hardening: remove global init callback handler, sanitize inputs, escape outputs
* Fix order status logic for already-completed orders and failed payment redirects
* Require WooCommerce 8.0+; declare HPOS compatibility
* Validate gateway configuration before checkout redirect
* Align checkout card icons and title display with API CC module pattern

= 1.1.8.4 =
* Added separate Authorization Hash key ability for Divert Currency
* Minor bug fixes
* Code formatting clean-up

= 1.1.8.3 =
* Removed 3D Secure Checkout option (it's gateway set now)

= 1.1.8.2 =
* Fixed Authorization Hash Fields order

= 1.1.8.1 =
* Fixed currency code sending bug

= 1.1.8 =
* Added optional Giftcard split payment ability

= 1.1.7 =
* Added Divert Currency ability
* Minor bug fixes & code clean-up

= 1.1.6 =
* Added Authorization Verification Hash setting
* Minor code clean-up.

= 1.1.5 =
* Added Cards Allowed setting

= 1.1.4 =
* Enhanced currency support, to work with multi-currency plug-ins
* Cleaned up some code & documentation

= 1.1.3 = 
* Minor syntax issue correction
* Added 3D Secure checkout option
* Minor code formatting & documentation tweaks

= 1.1.2 =
* WooCommerce v3.9.1 tweaks
* Additional bugs fixed
* Minor code optimizations

= 1.1.1 =
* Bugs fixed
* Added 3D secure checkout setting

= 1.1.0 =
* Bug fixes
* Minor code clean-up
* Tested WooCommerce v2.6.13 Compatible

= 1.0.2 =
* First Production Version
* Tested WooCommerce v2.2.11 compatible

= 1.0.1 =
* Beta Version

= 1.0.0 =
* Alpha Version

== Upgrade Notice ==
* Upgrade is required for versions below 1.1.9. Authorization Hash and HTTPS are now required. PHP 8.1 or higher is required.
* 1.1.10 restores the Smart Screens hosted response theme after payment.

== Arbitrary section ==
* WooCommerce Blocks Compatibility

With WooCommerce v8.3+, Cart and Checkout Blocks are now used by default for installations and themes.
Presently our offered WooCommerce payment modules do not support this newer single page checkout process. We hope to offer this in a future release of our modules.
If you're having issues with the cart’s checkout, ensure you disable the new WooCommerce Blocks option, to make the cart use the original multi-page checkout process.
Refer to the below URL for how you would make this adjustment:

WooCommerce Documentation - Cart and Checkout Blocks
https://woocommerce.com/document/cart-checkout-blocks-status/

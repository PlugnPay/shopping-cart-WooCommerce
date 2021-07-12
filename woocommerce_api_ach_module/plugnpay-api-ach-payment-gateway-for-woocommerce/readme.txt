=== Plugin Name ===
PlugnPay API ACH/eCheck Payment Gateway For WooCommerce
Contributors: PlugnPay
Site link: http://www.plugnpay.com
Tags: woocommerce, plugnpay, payment, gateway, API, CC, credit card, debit card
Requires at least: 3.0.1
Tested up to: 4.3
Stable tag: 3.1
License: GPL2
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Plugin extends the functionality of WooCommerce to accept payments from checking accounts on your checkout page, using PlugnPay's API payment method.

== Description ==

PlugnPay API ACH/eCheck Payment Gateway for WooCommerce makes your website ready to use PlugnPay's API payment method, to accept checking payments on your ecommerce store checkout page.

PlugnPay is a widely used payment gateway to process payments online and accepts Visa, MasterCard, Discover and other variants payment options; such as ACH/eCheck payments.

WooCommerce 4.3.x Compatible

= Features =
Few features of this plugin:

1. Accept Checks right on your website.
2. No redirecting to other URL.
3. Easy to install and configure
4. Option to configure success & failure message
5. Safe way to process ACH/eCheck payments on WooCommerce using PlugnPay API
6. This plugin uses internal card processing, so faster and more reliable
7. Divert payments to alternative gateway accounts, based upon currency selected

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
9. Under 'Payment Gateways' you will find all the available gateways, select 'PlugnPay API ACH' option
10. On this page you will find options to configure the plugin for use with WooCommerce
11. Modify the configurable elements accordingly
[* NOTE: At minimum, check the Enable checkbox & enter your username into the Gateway Username field.  All other fields are optional.]

---------------------------------------------
Enable/Disable: Used to enable/disable this payment ability, once the plug-in itself has been activated.

Title: This will appear on checkout page as name for this payment gateway

Description: This will appear on checkout page as description for this payment gateway

Gateway Account: This is the username provided to you by PlugnPay. (Note: This is the same username used to login to the PlugnPay Merchant Administration area.)

Remote Client Password: This is an API password you explicitly set within your PlugnPay account's Security Administration area. (Note: This is NOT the same password used to login to the PlugnPay Merchant Administration area.)

Transaction Success Message: This message will appear upon successful transaction. You can customize this message as per your need.

Transaction Failed Message: This message will appear when transaction will get failed/declined at payment gateway.

Divert Currency: Used to enable/disable ability to redirect payments to a different gateway account for specific currency types.

Divert Accounts: List currency code & username to divert specific payments to. [i.e. USD:username1,BBD:username2,CAD:username3]  Currency codes not listed will use default Gateway Account.
---------------------------------------------

12. once completed. click on the 'Save Changes' button to make those adjustments active immediately.

== Frequently Asked Questions ==
= Is SSL Required to use this plugin? =
Yes, SSL is required.

== Screenshots ==

== Changelog ==
= 1.1.3 =
* Added Divert Currency ability
* Minor bug fixes & code clean-up

= 1.1.2 =
* Enhanced currency support, to work with multi-currency plug-ins
* Cleaned up some code & documentation
* Added customer's IP address to gateway API call

= 1.1.1 =
* Checkout return URL adjustment

= 1.1.0 =
* Initial Public Version

= 1.0.0 =
* First Version

== Upgrade Notice ==
* No Upgrade Required

== Arbitrary section ==


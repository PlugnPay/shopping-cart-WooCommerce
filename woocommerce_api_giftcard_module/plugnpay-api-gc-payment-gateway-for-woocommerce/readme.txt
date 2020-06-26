=== Plugin Name ===
PlugnPay API Gift Card Payment Gateway For WooCommerce
Contributors: PlugnPay
Site link: http://www.plugnpay.com
Tags: woocommerce, plugnpay, payment, gateway, API, GC, gift card, MP, mercury payments
Requires at least: 3.0.1
Tested up to: 4.1
Stable tag: 3.1
License: GPL2
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Plugin extends the functionality of WooCommerce to accept stand-alone payments of Mercury Payment gift cards on your checkout page, using PlugnPay's API payment method.

== Description ==

<h3>PlugnPay API Gift Card Payment Gateway for WooCommerce</h3> makes your website ready to use PlugnPay's API payment method, to accept Mercury Payment gift cards on your ecommerce store checkout page.

PlugnPay is a widely used payment gateway to process payments online and accepts Visa, MasterCard, Discover and other variants payment options.

<h3>WooCommerce 2.2.11 Compatible</h3>

= Features =
Few features of this plugin:

1. Accept MP Gift Cards right on your website.
2. No redirecting on other url.
3. Easy to install and configure
4. Option to configure success & failure message
5. Safe way to process stand-alone MP gift cards on WooCommerce using PlugnPay API
6. This plugin uses internal card processing, so faster and more reliable.

== Installation ==

Easy steps to install the plugin:

1. Upload `plugnpay-api-gc-payment-gateway-for-woocommerce` folder/directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to WooCommerce => Settings
4. On the "Settings" page, select "Payment Gateways" tab.
5. Under "Payment Gateways" you will find all the available gateways, select "PlugnPay API GC" option
6. On this page you will find option to configure the plugin for use with WooCommerce
7. Enter the API details (Gateway Username, Remote Client Password)
8. Configurable elements:

Title: This will appear on checkout page as name for this payment gateway

Description: This will appear on checkout page as description for this payment gateway

Gateway Username: This is the username provided to you by PlugnPay. (Note: This is the same username used to login to the PlugnPay Merchant Administration area.)

Remote Client Password: This is an API password you explicitly set within your PlugnPay account's Security Administration area. (Note: This is NOT the same password used to login to the PlugnPay Merchant Administration area.)

Transaction Success Message: This message will appear upon successful transaction. You can customize this message as per your need.

Transaction Failed Message: This message will appear when transaction will get failed/declined at payment gateway.

GiftCard Format: This willl allow you to select if your MP Giftcard numbers as LUHN-10 or not.

== Frequently Asked Questions ==
= Is SSL Required to use this plugin? =
Yes, SSL is required.

= Is a giftcard specific merchant account required to use this plugin? =
Yes, you must be setup to accept giftcards through Mercury Payments & our payment gateway, before using this plugin.

= Does this use a stand-alone or split-payment checkout process? =
This does stand-alone MP giftcard proessing only.  For split-payments (part giftcard, part credit card), use our Smart Screens payment method plugin.

= What happens if the MP giftcard used does not have sufficent balance? =
The payment will be declined, because no alternate payment type is availble to cover the remaining balance.  Consider using our Smart Screens plugin for a split-payments, if this becomes an issue for your company.

== Screenshots ==

== Changelog ==
= 1.0.0 =
* First Version

= 1.1.0 =
* Initial Public Version

- 1.1.1 =
* Checkout return URL adjustment

== Upgrade Notice ==
* No Upgrade Required

== Arbitrary section ==


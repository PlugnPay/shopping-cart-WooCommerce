=== Plugin Name ===
PlugnPay SSv2 Payment Gateway For WooCommerce
Contributors: PlugnPay
Site link: http://www.plugnpay.com
Tags: woocommerce plugnpay.com, plugnpay.com, payment gateway, woocommerce, woocommerce payment gateway
Requires at least: 3.0.1
Tested up to: 4.1
Stable tag: 3.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Accept payments on your WooCommerce website with PlugnPay’s secure payment modules.

== Description ==

PlugnPay SSv2 Payment Gateway for WooCommerce makes your website ready to use PlugnPay payment gateway to accept payments on your ecommerce store in a safe way.

PlugnPay is a widely used payment gateway to process payments online and accepts Visa, MasterCard, Discover and other variants payment options.

WooCommerce 4.x & 5.x Compatible

= Features =
Few features of this plugin:

1. No SSL required
2. No extra PCI overhead
3. Easy to install and configure
4. Option to configure success & failure message
5. A safe way to process credit/debit cards on WooCommerce using PlugnPay's Smart Screens v2 payment method.
6. Payment data is collected on PlugnPay’s secured servers.
7. 3D Secure checkout capable for approved merchants
8. [Optional] Authorization Verification Hash ability
9. [Optional] Giftcard split payment ability
10. [optional] Divert payments based upon currency selected

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
8. On the 'Settings' page, select 'Checkout' tab.
9. Under 'Payment Gateways' you will find all the available gateways, select 'PlugnPay SSv2 Payment Gateway For WooCommerce' option
10. On this page you will find options to configure the plugin for use with WooCommerce
11. Modify the configurable elements accordingly
[* NOTE: At minimum, check the Enable checkbox & enter your username into the Gateway Username field.  All other fields are optional.]

---------------------------------------------
Enable/Disable: Used to enable/disable this payment ability, once the plug-in itself has been activated.

Title: This will appear on checkout page as name for this payment gateway

Description: This will appear on checkout page as description for this payment gateway

Gateway Username: This is the username provided to you by PlugnPay. (Note: This is the same username you use to login to the PlugnPay Merchant Administration area.)

Cards Allowed: This controls which card types are presented to the customer as a payment options. (Note: To prevent issues, only list cards types you actually have obtained a merchant account for.)

Transaction Success Message: This message will appear upon the transaction is successful. You can customize this message as per your needs.

Transaction Failed Message: This message will appear upon the transaction is declined/failed. You can customize this message as per your needs.

Transaction Settlement: Select if you'd like the cart to mark approved payments for settlement for you.

3D Secure Checkout: Select only if you require 3D secure checkout functionality. (Note: Merchants must configure their 3D secure program with us before activating.)

Authorization Hash: Select only if you require Authorization Verification Hash functionality. (Note: Merchant must configure these related settings to match their PlugnPay account before activating.)

Authorization Hash Key: If using this ability, enter the corresponding verification key from your PlugnPay account.

Authorization Hash Fields: If using this ability, select a fieldset to validate upon & configure your PlugnPay account to match.

Giftcard Acceptance: Allows you to accept Giftcards at time of checkout & process it as a split-payment. (Note: You must have Giftcard ability enabled in your PlugnPay account to use this.)

Divert Currency: Use to enable/disable ability to redirect payments to a different gateway account for specific currency types.

Divert Accounts: List currency code & username to divert specific payments to. [i.e. USD:username1,BBD:username2,CAD:username3]  Currency codes not listed will use default Gateway Account.
---------------------------------------------

12. once completed. click on the 'Save Changes' button to make those adjustments active immediately.


== Frequently Asked Questions ==
= Is SSL Required to use this plugin? =
SSL is not required

= Is 3D Secure available for all merchants? =
No, merchants must have a supported 3D secure account pre-configured with us, before enabling this ability

== Screenshots ==
* None Available

== Changelog ==
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
* Upgrade is required, if your module version is below v1.1.2

== Arbitrary section ==


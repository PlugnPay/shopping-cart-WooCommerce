=== Plugin Name ===
PlugnPay API CC Tokenization Payment Gateway For WooCommerce
Contributors: PlugnPay
Site link: https://www.plugnpay.com
Tags: woocommerce, plugnpay, payment, gateway, API, CC, credit card, tokenization, card on file
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.1.1
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

Plugin extends WooCommerce to accept PlugnPay API credit card payments with card-on-file tokenization (authprev).

== Description ==

PlugnPay API CC Tokenization Payment Gateway for WooCommerce accepts credit/debit cards on your checkout page using PlugnPay's Remote API, and lets logged-in customers save cards for later purchases (checkout save checkbox or My Account → Add payment method).

Saved cards use PlugnPay card-on-file authprev. Each token is bound to the PlugnPay gateway account that created it and cannot be reused on a different account (including divert-currency alternate accounts).

Requires WordPress 6.0+, WooCommerce 8.0+, PHP 8.1+, and HTTPS on the storefront. Collecting cards onsite increases PCI scope versus hosted Smart Screens.

Add payment method registers a card with a $0.00 auth (`transflags=init,cit,recurring,avsonly`). The PlugnPay account must allow AVS-only transactions.

= Features =

1. Accept cards on your website (no redirect)
2. Save card on file for logged-in customers (checkout or My Account → Add payment method)
3. Charge saved cards via authprev (COF)
4. Tokens bound per PlugnPay account (strict enforcement with divert currency)
5. Token unusable after 24 months on prevorderid or card expiration
6. Optional require CVV on saved-card (token) checkouts (sent as card-cvv with authprev)
7. Add payment method via $0.00 AVS-only init auth (account must allow AVS-only)
8. HTTPS required; Authorization Verification Hash (SHA-256) recommended
9. Optional Giftcard split payment on new-card checkouts
10. Optional Divert Currency (separate token registration per account)

== Installation ==

1. Login to your WordPress admin area
2. Go to Plugins => Add new
3. Upload this payment module's zip file and install
4. Activate the plugin
5. WooCommerce => Settings => Payments => configure "PlugnPay API CC Tokenization"

At minimum: enable the gateway, set Gateway Account and Remote Client Password. Authorization Hash (SHA-256) is recommended; enable it here and in PlugnPay Merchant Admin.

== Frequently Asked Questions ==
= Is SSL Required? =
Yes.

= Does this require WooCommerce Subscriptions? =
No. This module only provides tokenization / card-on-file for customer-initiated checkouts.

= Can a token created on account A be used on account B? =
No. Tokens are strictly bound to the PlugnPay account that processed the original init transaction.

= How long do tokens last? =
Until the stored prevorderid is 24 months old, or the card expiration date is reached — whichever comes first. Successful charges refresh prevorderid.

= Why does Add payment method fail with an AVS-related error? =
The PlugnPay gateway account must allow AVS-only transactions. Add payment method sends a $0.00 auth with transflags init,cit,recurring,avsonly. Enable AVS-only on the account, then retry.

== Changelog ==
= 1.1.1 - 2026-08-27 =
* Allow activating alongside API Credit Card and ACH (shared PCI helpers defined once)
* Authorization Hash (SHA-256) is recommended, not required; checkout proceeds if it is disabled

= 1.1.0 =
* PCI DSS: require Authorization Hash (SHA-256) and block checkout if it is not configured
* PCI DSS: require PHP 8.1+ and HTTPS on the storefront (local/development excepted)
* PCI DSS: encrypt Remote Client Password and hash keys at rest; do not echo secrets in admin password fields
* PCI DSS: restrict cURL to HTTPS; require WooCommerce 8.0+
* Add automated tests for hash and secret storage

= 1.0.3 =
* Add payment method uses $0.00 auth with transflags init,cit,recurring,avsonly (PnP account must allow AVS-only)

= 1.0.2 =
* My Account Add payment method: new-card fields only (same layout as checkout); no saved-token picker
* Register card via init COF auth and save WC payment token from Add payment method

= 1.0.1 =
* Hide PlugnPay gateway username from saved-card labels at checkout
* Add optional Saved Card CVV setting; when enabled, require CVV for token authprev and send card-cvv

= 1.0.0 =
* Initial tokenization module based on API CC gateway
* Card-on-file authprev with origorderid / prevorderid
* Account-bound tokens with divert-currency enforcement
* My Account saved payment methods via WooCommerce tokenization API

== Upgrade Notice ==
= 1.1.1 =
1.1.1 (2026-08-27) is required if API Credit Card or ACH is also active. Authorization Hash is recommended, not required.

= 1.1.0 =
Upgrade is required. Authorization Hash (SHA-256) and HTTPS are now required. PHP 8.1 or higher is required.

= 1.0.3 =
Add payment method now uses AVS-only $0.00 auth. Enable AVS-only on the PlugnPay account.

= 1.0.2 =
Adds My Account Add payment method support with new-card-only form matching checkout.

= 1.0.1 =
Optional Saved Card CVV setting and cleaner saved-card display labels.

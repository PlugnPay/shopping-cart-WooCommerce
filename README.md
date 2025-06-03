# Shopping Cart - WooCommerce Payment Modules

Easy to install payment modules for the WooCommerce shopping cart.
Multiple payment styles are supported, each covering a different checkout need.

* API (Remote Auth)
  - [Download - Credit Card](./woocommerce_api_cc_module.zip) v1.1.7
  - [Download - ACH/eCheck](./woocommerce_api_ach_module.zip) v1.1.6
  - [Download - Gift Card](./woocommerce_api_gitcard_module.zip) v1.1.2
* Smart Screens v2 (Gateway Hosted Solution)
  - [Download](./woocommerce_ss2_module.zip) v1.1.8.4
  
## Installation

For complete instructions on how to install/setup any of our WooCommerce payment modules, please refer to the README file within the zip file of that module.

However the basic process is:
* download the zip file of the module you want to install
* unzip it & refer to the README file
* upload the given file via WordPress Plugin section
* activate the module in the WordPress Extensions section
* configure the module in the WooCommerce Settings Payments section

:warning: Protect Your Site From Carding Attacks :warning:

WooCommerce is a very popular shopping cart and is subject to carding attacks by bad actors.
Always use fraud protection/detection add-ons with WooCommerce, to pre-filter/block fraudlent transaction activity.

We also HIGHLY recommend you:
* apply CAPTCHA or Two-factor authentication to your checkout process, BEFORE customer is able to make each payment.
* enable PlugnPay's Authentication Hash Verification ability within your cart, to restrict authorizations to only those that originate from your cart.

Contact PlugnPay support, if you need more info on either of these recommendations.

## Usage

The modules are for 1-time authorizations, where payment data is collected at time of checkout.
They DO NOT support WooCommerce's subscription and tokenization features at this time.

Here is a break down of what each payment module offers/does:

API
* This method permits WooCommerce to handle the entire checkout process.
* We offer separate API based modules for Credit Card & ACH/eCheck options.
* This module directly requires WooCommerce to collect all payment information.
* Customer never leaves the given site during the checkout process.
* Customer never sees our payment gateway during the checkout process.
* This module requires the given website to be properly SSL secured.
* Authorization Verification Hash ability
* Divert payments to alternative gateway accounts, based upon currency selected

Smart Screens v2
* This is the most current version of our Smart Screens payment method.
* Supports Credit Card, ACH/eCheck & other payment options configured with us.
* WooCommerce will NOT collect sensitive payment info from customer at checkout.
* Customer is redirected to our gateway's secure billing pages to complete payment.
* Our payment gateway directly collects payment data via our secure billing pages.
* After payment is submitted & approved, we direct customer back to WooCommerce.
* This module DOES NOT require site to be SSL secured, but is HIGHLY recommended.
* Authorization Verification Hash ability
* Divert payments to alternative gateway accounts, based upon currency selected

Smart Screens v2 (CardX Build)
* This is a modified version of Smart Screens v2, but for CardX specific clients.
* If you’re a CardX client, use this WooCommerce module instead of our generic one.

## WooCommerce Blocks Compatibility

With WooCommerce v8.3+, Cart and Checkout Blocks are now used by default for installations and themes.

Presently our offered WooCommerce payment modules do not support this newer single page checkout process.  We hope to offer this in a future release of our modules.

If you're having issues with the cart’s checkout, ensure you disable the new WooCommerce Blocks option, to make the cart use the original multi-page checkout process.

Refer to the below URL for how you would make this adjustment:

[WooCommerce Documentation - Cart and Checkout Blocks](https://woocommerce.com/document/cart-checkout-blocks-status/)


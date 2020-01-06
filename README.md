# Shopping Cart - WooCommerce Payment Modules

Easy to install payment modules for the WooCommerce shopping cart.
Multiple payment styles are supported, each covering a different checkout need.

* API [Remote Auth]
* Smart Screens v2 [Gateway Hosted Solution]
* Smart Screens v1 [Legacy Gateway Hosted Solution]

## Installation

For complete instructions on how to install/setup any of our WooCommerce payment modules, please refer to the README file within the zip file of that module.

However the basic process is:
* download the zip file of the module you want to install
* unzip it & refer to the README file
* upload the given files to their respective places in WooCommerce
* activate the module in the WordPress Extensions section
* configure the module in the WooCommerce Settings Payments section

## Usage

Here is a break down of what each payment module offers/does:

API
* This method permits WooCommerce to handle the entire checkout process.
* We offer separate API based modules for Credit Card & ACH/eCheck options.
* This module directly requires WooCommerce to collect all payment information.
* Customer never leaves the given site during the checkout process.
* Customer never sees our payment gateway during the checkout process.
* This module requires the given website to be properly SSL secured.

Smart Screens v2
* This is the most current version of our Smart Screens payment method.
* Supports Credit Card, ACH/eCheck &/or any other payment options configured with us.
* WooCommerce will NOT collect any sensitive payment info from the customer at checkout.
* Customer will be redirected to our gateway's secure billing pages to complete their payment.
* Our payment gateway will directly collect the payment data via our secure billing pages.
* After the payment info is submitted & approved, we'll redirect the customer back to WooCommerce.
* This module does NOT require the given site to be SSL secured, but its still HIGHLY recommended.

Smart Screens v2 (CardX Build)
* This is a modified version of our normal Smart Screens v2 module, but for CardX specific clients.
* If you have a CardX account, you should use this WooCommerce module instead of our generic one.

Smart Screens v1 (Legacy)
* TO PREVENT ISSUES, DO NOT USE THIS MODULE, UNLESS TOLD TO BY PLUGNPAY STAFF!
* This is a legacy version of our Smart Screens payment method, for older/custom PnP accounts.
* Supports Credit Card &/or ACH/eCheck payment options configured with us.
* WooCommerce will NOT collect any sensitive payment info from the customer at checkout.
* Customer will be redirected to our gateway's secure billing pages to complete their payment.
* Our payment gateway will directly collect the payment data via our secure billing pages.
* After the payment info is submitted & approved, we'll redirect the customer back to WooCommerce.
* This module does NOT require the given site to be SSL secured, but its still HIGHLY recommended.

## History

API
* Remote Auth
* Seperate modules for Credit Card & ACH/eCheck

Smart Screens v2
* Gateway Hosted Solution
* Generic version for all PlugnPay clients
* Customized version for CardX specific clients

Smart Screens v1
* Legacy Gateway Hosted Solution
* Generic version for all PlugnPay clients with Legacy payment needs
* USE THE SMART SCREENS V2 MODULE INSTEAD FOR BEST RESULTS.


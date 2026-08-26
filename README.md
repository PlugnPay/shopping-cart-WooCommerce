# shopping-cart-WooCommerce

PlugnPay payment modules for WooCommerce.

## Packages

| Package | Method | Status |
|---|---|---|
| [WooCommerce](./WooCommerce/) | Remote API (Credit Card + ACH + Tokenization) and Smart Screens v2 | Current |

## Choose a module

### API Credit Card

| | |
|---|---|
| Download | [woocommerce_api_cc_module.zip](./WooCommerce/woocommerce_api_cc_module.zip) |
| Source | [src/plugnpay-api-cc-…](./WooCommerce/src/plugnpay-api-cc-payment-gateway-for-woocommerce/) |
| Version | v1.2.0 |
| Checkout | Onsite card fields |
| Card/bank data on your server | Yes |
| PCI scope | Higher |

### API ACH/eCheck

| | |
|---|---|
| Download | [woocommerce_api_ach_module.zip](./WooCommerce/woocommerce_api_ach_module.zip) |
| Source | [src/plugnpay-api-ach-…](./WooCommerce/src/plugnpay-api-ach-payment-gateway-for-woocommerce/) |
| Version | v1.2.0 |
| Checkout | Onsite ACH fields |
| Card/bank data on your server | Yes |
| PCI scope | Higher |

### API Credit Card Tokenization (card on file)

| | |
|---|---|
| Download | [woocommerce_api_cc_tokenization_module.zip](./WooCommerce/woocommerce_api_cc_tokenization_module.zip) |
| Source | [src/plugnpay-api-cc-tokenization-…](./WooCommerce/src/plugnpay-api-cc-tokenization-payment-gateway-for-woocommerce/) |
| Version | v1.1.0 |
| Checkout | Onsite card fields + saved cards |
| Card/bank data on your server | Yes (new card); saved cards use authprev |
| PCI scope | Higher |

### Smart Screens v2

| | |
|---|---|
| Download | [woocommerce_ss2_module.zip](./WooCommerce/woocommerce_ss2_module.zip) |
| Source | [src/plugnpay-ss2-…](./WooCommerce/src/plugnpay-ss2-payment-gateway-for-woocommerce/) |
| Version | v1.1.11 |
| Checkout | Redirect → `https://pay1.plugnpay.com/pay/` |
| Card/bank data on your server | No |
| PCI scope | Lower |

Docs and install:

- [WooCommerce/README.md](./WooCommerce/README.md)
- API CC: [INSTALL.txt](./WooCommerce/INSTALL.txt)
- API ACH: [INSTALL_ACH.txt](./WooCommerce/INSTALL_ACH.txt)
- API CC Tokenization: [INSTALL_TOKENIZATION.txt](./WooCommerce/INSTALL_TOKENIZATION.txt)
- SS2: [INSTALL_SS2.txt](./WooCommerce/INSTALL_SS2.txt)

## Installation (summary)

1. Download the zip for the module you want.
2. In WordPress → Plugins → Add New → Upload Plugin, upload the zip (or copy the plugin folder from `WooCommerce/src/` into `wp-content/plugins/`).
3. Activate the plugin.
4. Configure under WooCommerce → Settings → Payments.

## Usage

Most modules are for one-time authorizations where payment data is collected at checkout.
They do **not** support WooCommerce Subscriptions.

The **API Credit Card Tokenization** module adds card-on-file (saved cards) for logged-in customers via PlugnPay `authprev`. It does **not** require WooCommerce Subscriptions.

- Checkout save: new card with `mode=auth` and `transflags=init,cit,recurring`
- Later charges: `mode=authprev` with immutable `origorderid`, rotating `prevorderid`, and `currency`
- My Account → Add payment method: `$0.00` auth with `transflags=init,cit,recurring,avsonly` (PnP account **must** allow AVS-only)
- Tokens are bound to the PlugnPay account that created them and cannot be reused across divert-currency accounts
- Optional setting can require CVV on saved-card checkouts (`card-cvv` with authprev)
- Token usable while `prevorderid` is within 24 months and the card is not expired

### API (Remote Auth)

- WooCommerce handles the full checkout on your site.
- Separate modules for Credit Card, ACH/eCheck, and Credit Card Tokenization.
- Customer never leaves your site or sees the PlugnPay billing pages.
- Storefront HTTPS is required.
- Authorization Verification Hash (SHA-256) is required. Currency divert options available.

### Smart Screens v2

- Hosted checkout at PlugnPay Smart Screens.
- Supports Credit Card, ACH/eCheck, and other options configured on your PlugnPay account.
- WooCommerce does **not** collect sensitive payment data at checkout.
- Customer is redirected to PlugnPay, then returned after approval or decline.
- HTTPS on your store is required.
- Authorization Verification Hash (SHA-256) is required.

## WooCommerce Blocks Compatibility

With WooCommerce v8.3+, Cart and Checkout Blocks are the default for many installs.

These modules do not support Blocks checkout yet. If checkout fails, disable Cart/Checkout Blocks and use classic checkout.

[WooCommerce Documentation — Cart and Checkout Blocks](https://woocommerce.com/document/cart-checkout-blocks-status/)

## Security

WooCommerce is a common target for carding attacks. Use fraud protection add-ons, CAPTCHA or 2FA before payment, and keep PlugnPay Authorization Hash Verification (SHA-256) enabled.

Contact PlugnPay support if you need help with these recommendations.

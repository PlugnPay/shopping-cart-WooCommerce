# WooCommerce — PlugnPay Payment Modules

Payment modules for WooCommerce (classic checkout). Current versions: API Credit Card **v1.2.1 (2026-08-27)**, API ACH **v1.2.0**, API CC Tokenization **v1.1.0**, Smart Screens v2 **v1.1.11**.

Install through WordPress → Plugins → Upload Plugin, then configure under WooCommerce → Settings → Payments.

## Choose a module

### API Credit Card

| | |
|---|---|
| Package folder | `plugnpay-api-cc-payment-gateway-for-woocommerce` |
| Download | [woocommerce_api_cc_module_1.2.1_20260827.zip](./woocommerce_api_cc_module_1.2.1_20260827.zip) |
| Checkout | Onsite card fields → Remote API |
| Card/bank data on your server | Yes |
| PCI scope | Higher |
| Public demo account | No — merchant credentials only |

### API ACH/eCheck

| | |
|---|---|
| Package folder | `plugnpay-api-ach-payment-gateway-for-woocommerce` |
| Download | [woocommerce_api_ach_module.zip](./woocommerce_api_ach_module.zip) |
| Checkout | Onsite ACH fields → Remote API |
| Card/bank data on your server | Yes |
| PCI scope | Higher |
| Public demo account | No — merchant credentials only |

### API Credit Card Tokenization (card on file)

| | |
|---|---|
| Package folder | `plugnpay-api-cc-tokenization-payment-gateway-for-woocommerce` |
| Download | [woocommerce_api_cc_tokenization_module.zip](./woocommerce_api_cc_tokenization_module.zip) |
| Checkout | Onsite card fields + saved cards → Remote API / authprev |
| Card/bank data on your server | Yes (new card); saved cards use authprev |
| PCI scope | Higher |
| Public demo account | No — merchant credentials only |

### Smart Screens v2

| | |
|---|---|
| Package folder | `plugnpay-ss2-payment-gateway-for-woocommerce` |
| Download | [woocommerce_ss2_module.zip](./woocommerce_ss2_module.zip) |
| Checkout | Redirect → `https://pay1.plugnpay.com/pay/` |
| Card/bank data on your server | No |
| PCI scope | Lower |
| Public demo account | No — merchant credentials only |

You may install more than one module; enable only the payment method(s) you need under WooCommerce → Settings → Payments.

## API Credit Card (onsite)

- Source: [src/plugnpay-api-cc-payment-gateway-for-woocommerce/](./src/plugnpay-api-cc-payment-gateway-for-woocommerce/)
- Quick install: [INSTALL.txt](./INSTALL.txt)

Collects card data on your storefront and posts from the server to PlugnPay Remote API. HTTPS and Authorization Verification Hash (SHA-256) are required. Optional gift card split-payment fields when enabled in settings.

## API ACH/eCheck (onsite)

- Source: [src/plugnpay-api-ach-payment-gateway-for-woocommerce/](./src/plugnpay-api-ach-payment-gateway-for-woocommerce/)
- Quick install: [INSTALL_ACH.txt](./INSTALL_ACH.txt)

Collects ACH/eCheck data on your storefront and posts from the server to PlugnPay Remote API. HTTPS and Authorization Verification Hash (SHA-256) are required.

## API Credit Card Tokenization (onsite + card on file)

- Source: [src/plugnpay-api-cc-tokenization-payment-gateway-for-woocommerce/](./src/plugnpay-api-cc-tokenization-payment-gateway-for-woocommerce/)
- Quick install: [INSTALL_TOKENIZATION.txt](./INSTALL_TOKENIZATION.txt)
- Version: **v1.1.0**

Collects card data on your storefront for new cards. HTTPS and Authorization Verification Hash (SHA-256) are required. Behavior:

| Flow | Remote API |
|---|---|
| Checkout — new card (optional save) | `mode=auth`; when saving, `transflags=init,cit,recurring` |
| Checkout — saved card | `mode=authprev` with `origorderid`, `prevorderid`, `currency`, `transflags=cit,recurring` (optional `card-cvv`) |
| My Account — Add payment method | `$0.00` `mode=auth` with `transflags=init,cit,recurring,avsonly`; on success, save WC token |

**PnP account requirement:** AVS-only must be enabled for Add payment method registration to succeed.

Tokens are strictly bound to the PlugnPay account that created them (including when Divert Currency is enabled). Token lifetime: `prevorderid` within 24 months and card not expired. Does not require WooCommerce Subscriptions.

## Smart Screens v2 (hosted)

- Source: [src/plugnpay-ss2-payment-gateway-for-woocommerce/](./src/plugnpay-ss2-payment-gateway-for-woocommerce/)
- Quick install: [INSTALL_SS2.txt](./INSTALL_SS2.txt)

Redirects customers to PlugnPay hosted Smart Screens. WooCommerce does not collect sensitive payment data. HTTPS and Authorization Verification Hash (SHA-256) are required. Capture / void / refund are done in PlugnPay Merchant Admin when applicable.

## Common install steps (all)

1. In WordPress → **Plugins** → Add New → **Upload Plugin**, upload the matching `.zip`.
   Alternatively, copy the plugin folder from `src/` into `wp-content/plugins/`.
2. Activate the plugin.
3. WooCommerce → **Settings** → **Payments** → configure credentials and options.
4. Ensure storefront HTTPS is enabled (required for all modules).

## WooCommerce Blocks

These modules support classic checkout only. See the [root README](../README.md#woocommerce-blocks-compatibility) for Blocks notes.

## Development layout

```
WooCommerce/
  INSTALL.txt                 # API Credit Card quick install
  INSTALL_ACH.txt             # API ACH quick install
  INSTALL_TOKENIZATION.txt    # API CC Tokenization quick install
  INSTALL_SS2.txt             # Smart Screens v2 quick install
  woocommerce_api_cc_module_1.2.1_20260827.zip
  woocommerce_api_ach_module.zip
  woocommerce_api_cc_tokenization_module.zip
  woocommerce_ss2_module.zip
  README.md
  src/
    plugnpay-api-cc-payment-gateway-for-woocommerce/
    plugnpay-api-ach-payment-gateway-for-woocommerce/
    plugnpay-api-cc-tokenization-payment-gateway-for-woocommerce/
    plugnpay-ss2-payment-gateway-for-woocommerce/
```

Rebuild a merchant zip from source (run from `WooCommerce/src/`):

```bash
zip -r ../woocommerce_api_cc_module_1.2.1_20260827.zip plugnpay-api-cc-payment-gateway-for-woocommerce
zip -r ../woocommerce_api_ach_module.zip plugnpay-api-ach-payment-gateway-for-woocommerce
zip -r ../woocommerce_api_cc_tokenization_module.zip plugnpay-api-cc-tokenization-payment-gateway-for-woocommerce
zip -r ../woocommerce_ss2_module.zip plugnpay-ss2-payment-gateway-for-woocommerce
```

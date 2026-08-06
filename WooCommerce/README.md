# WooCommerce — PlugnPay Payment Modules

Payment modules for WooCommerce (classic checkout). Current versions: API Credit Card **v1.1.9**, API ACH **v1.1.9**, Smart Screens v2 **v1.1.8.5**.

Install through WordPress → Plugins → Upload Plugin, then configure under WooCommerce → Settings → Payments.

## Choose a module

| | API Credit Card | API ACH/eCheck | Smart Screens v2 |
|---|---|---|---|
| Package folder | `plugnpay-api-cc-payment-gateway-for-woocommerce` | `plugnpay-api-ach-payment-gateway-for-woocommerce` | `plugnpay-ss2-payment-gateway-for-woocommerce` |
| Download | [woocommerce_api_cc_module.zip](./woocommerce_api_cc_module.zip) | [woocommerce_api_ach_module.zip](./woocommerce_api_ach_module.zip) | [woocommerce_ss2_module.zip](./woocommerce_ss2_module.zip) |
| Checkout | Onsite card fields → Remote API | Onsite ACH fields → Remote API | Redirect → `https://pay1.plugnpay.com/pay/` |
| Card/bank data on your server | Yes | Yes | No |
| PCI scope | Higher | Higher | Lower |
| Public demo account | No — merchant credentials only | No — merchant credentials only | No — merchant credentials only |

You may install more than one module; enable only the payment method(s) you need under WooCommerce → Settings → Payments.

## API Credit Card (onsite)

- Source: [src/plugnpay-api-cc-payment-gateway-for-woocommerce/](./src/plugnpay-api-cc-payment-gateway-for-woocommerce/)
- Quick install: [INSTALL.txt](./INSTALL.txt)

Collects card data on your storefront and posts from the server to PlugnPay Remote API. Optional gift card split-payment fields when enabled in settings.

## API ACH/eCheck (onsite)

- Source: [src/plugnpay-api-ach-payment-gateway-for-woocommerce/](./src/plugnpay-api-ach-payment-gateway-for-woocommerce/)
- Quick install: [INSTALL_ACH.txt](./INSTALL_ACH.txt)

Collects ACH/eCheck data on your storefront and posts from the server to PlugnPay Remote API.

## Smart Screens v2 (hosted)

- Source: [src/plugnpay-ss2-payment-gateway-for-woocommerce/](./src/plugnpay-ss2-payment-gateway-for-woocommerce/)
- Quick install: [INSTALL_SS2.txt](./INSTALL_SS2.txt)

Redirects customers to PlugnPay hosted Smart Screens. WooCommerce does not collect sensitive payment data. Capture / void / refund are done in PlugnPay Merchant Admin when applicable.

## Common install steps (all)

1. In WordPress → **Plugins** → Add New → **Upload Plugin**, upload the matching `.zip`.
   Alternatively, copy the plugin folder from `src/` into `wp-content/plugins/`.
2. Activate the plugin.
3. WooCommerce → **Settings** → **Payments** → configure credentials and options.
4. Ensure storefront HTTPS is enabled (required for API modules; strongly recommended for SS2).

## WooCommerce Blocks

These modules support classic checkout only. See the [root README](../README.md#woocommerce-blocks-compatibility) for Blocks notes.

## Development layout

```
WooCommerce/
  INSTALL.txt                 # API Credit Card quick install
  INSTALL_ACH.txt             # API ACH quick install
  INSTALL_SS2.txt             # Smart Screens v2 quick install
  woocommerce_api_cc_module.zip
  woocommerce_api_ach_module.zip
  woocommerce_ss2_module.zip
  README.md
  src/
    plugnpay-api-cc-payment-gateway-for-woocommerce/
    plugnpay-api-ach-payment-gateway-for-woocommerce/
    plugnpay-ss2-payment-gateway-for-woocommerce/
```

Rebuild a merchant zip from source (run from `WooCommerce/src/`):

```bash
zip -r ../woocommerce_api_cc_module.zip plugnpay-api-cc-payment-gateway-for-woocommerce
zip -r ../woocommerce_api_ach_module.zip plugnpay-api-ach-payment-gateway-for-woocommerce
zip -r ../woocommerce_ss2_module.zip plugnpay-ss2-payment-gateway-for-woocommerce
```

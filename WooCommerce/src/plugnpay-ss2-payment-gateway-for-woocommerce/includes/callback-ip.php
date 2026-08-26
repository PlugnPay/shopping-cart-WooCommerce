<?php
/**
 * PlugnPay Smart Screens callback IP allowlist helpers.
 */

/**
 * PlugnPay Smart Screens callback server IPs.
 *
 * @return string[]
 */
function plugnpay_ss2_allowed_callback_ips() {
  $ips = array(
    '18.214.78.64',
    '52.3.174.161',
    '54.91.210.62',
    '69.18.198.4',
    '107.22.32.178',
    '3.210.249.25',
  );

  if (function_exists('apply_filters')) {
    $ips = apply_filters('woocommerce_plugnpay_ss2_allowed_callback_ips', $ips);
  }

  return is_array($ips) ? $ips : array();
}

/**
 * Normalize a connecting IP for allowlist comparison.
 *
 * @param string $ip
 * @return string
 */
function plugnpay_ss2_normalize_ip($ip) {
  $ip = trim((string) $ip);

  if (stripos($ip, '::ffff:') === 0) {
    $ip = substr($ip, 7);
  }

  if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $ip, $matches)) {
    $ip = $matches[1];
  }

  return $ip;
}

/**
 * Whether $ip is a PlugnPay callback server address.
 *
 * @param string $ip
 * @return bool
 */
function plugnpay_ss2_is_allowed_callback_ip($ip) {
  $ip = plugnpay_ss2_normalize_ip($ip);

  if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
    return false;
  }

  $allowed = array_map('plugnpay_ss2_normalize_ip', plugnpay_ss2_allowed_callback_ips());

  return in_array($ip, $allowed, true);
}

/**
 * WooCommerce checkout endpoint whose HTML PlugnPay should capture and echo.
 *
 * Hidden POSTs must receive a 200 storefront document. Redirects and empty
 * bodies are what the shopper sees after PlugnPay echoes the response.
 *
 * @param bool $payment_success
 * @return string
 */
function plugnpay_ss2_hidden_post_endpoint($payment_success) {
  return $payment_success ? 'order-received' : 'order-pay';
}

<?php
/**
 * Amount and currency comparison helpers.
 */

/**
 * Format an amount to two decimal places for gateway comparison.
 *
 * @param mixed $amount
 * @return string
 */
function plugnpay_ss2_format_amount($amount) {
  return number_format((float) $amount, 2, '.', '');
}

/**
 * Constant-time compare of posted vs expected amounts.
 *
 * @param mixed $posted
 * @param mixed $expected
 * @return bool
 */
function plugnpay_ss2_amounts_match($posted, $expected) {
  return hash_equals(plugnpay_ss2_format_amount($expected), plugnpay_ss2_format_amount($posted));
}

/**
 * Constant-time compare of ISO currency codes.
 *
 * @param string $posted
 * @param string $expected
 * @return bool
 */
function plugnpay_ss2_currencies_match($posted, $expected) {
  $posted = strtoupper(trim((string) $posted));
  $expected = strtoupper(trim((string) $expected));

  if ($posted === '' || $expected === '') {
    return false;
  }

  return hash_equals($expected, $posted);
}

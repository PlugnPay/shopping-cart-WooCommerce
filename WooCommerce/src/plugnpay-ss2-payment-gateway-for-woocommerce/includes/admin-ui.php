<?php
/**
 * Admin settings UI helpers.
 */

/**
 * Gateway settings that should stay hidden until a checkbox is enabled.
 *
 * Keys are checkbox field names. Values are field names whose table rows
 * are shown only when that checkbox is checked.
 *
 * @return array<string, array<int, string>>
 */
function plugnpay_ss2_admin_dependent_fields() {
  return array(
    'authhash'        => array('authhash_key', 'authhash_fields'),
    'response_hash'   => array('response_hash_key', 'response_hash_fields'),
    'divert_currency' => array('divert_accounts'),
  );
}

/**
 * Minimum PHP version required to load the gateway.
 *
 * @return string
 */
function plugnpay_ss2_minimum_php_version() {
  return '8.2';
}

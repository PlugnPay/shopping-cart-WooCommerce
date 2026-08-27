<?php
/**
 * Admin settings UI helpers.
 */

/**
 * Gateway settings that should stay hidden until a checkbox is enabled.
 *
 * @return array<string, array<int, string>>
 */
function plugnpay_api_cc_admin_dependent_fields() {
  return array(
    'authhash'        => array('authhash_key', 'authhash_fields'),
    'giftcard_allow'  => array('giftcard_descr', 'giftcard_note'),
    'divert_currency' => array('divert_accounts'),
  );
}

/**
 * Minimum PHP version required to load the gateway.
 *
 * @return string
 */
function plugnpay_api_cc_minimum_php_version() {
  return '8.2';
}

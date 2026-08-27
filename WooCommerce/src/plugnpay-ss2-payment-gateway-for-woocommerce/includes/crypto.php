<?php
/**
 * Authorization hash and secret-storage helpers.
 */

/**
 * Build the field substring used in the inbound authorization hash.
 *
 * @param string $fieldset 1, 2, or 3
 * @param string|int $order_id
 * @param string $order_amount
 * @param string $gateway_account
 * @return string
 */
function plugnpay_ss2_authhash_string_fields($fieldset, $order_id, $order_amount, $gateway_account) {
  $account = strtolower((string) $gateway_account);

  if ((string) $fieldset === '3') {
    return (string) $order_id . (string) $order_amount . $account;
  }

  if ((string) $fieldset === '2') {
    return (string) $order_amount . $account;
  }

  return $account;
}

/**
 * SHA-256 hex digest for the Smart Screens transaction hash.
 *
 * @param string $hash_string
 * @return string
 */
function plugnpay_ss2_transaction_hash($hash_string) {
  return hash('sha256', (string) $hash_string);
}

/**
 * Whether stored secret uses this module's encryption prefix.
 *
 * @param string $stored
 * @return bool
 */
function plugnpay_ss2_is_encrypted_secret($stored) {
  return strpos((string) $stored, 'ss2aes:') === 0;
}

/**
 * Key material for AES-256 secret encryption (WordPress salts when available).
 *
 * @return string Binary 32-byte key, or empty if unavailable
 */
function plugnpay_ss2_secret_key_material() {
  $parts = array();

  foreach (array('AUTH_KEY', 'SECURE_AUTH_KEY', 'AUTH_SALT') as $constant) {
    if (!defined($constant)) {
      continue;
    }

    $value = constant($constant);
    if (!is_string($value) || $value === '' || stripos($value, 'unique phrase') !== false) {
      continue;
    }

    $parts[] = $value;
  }

  if ($parts === array()) {
    return '';
  }

  return hash('sha256', implode('|', $parts), true);
}

/**
 * Encrypt a gateway secret for storage.
 *
 * @param string $plain
 * @return string
 */
function plugnpay_ss2_encrypt_secret($plain) {
  $plain = (string) $plain;

  if ($plain === '' || plugnpay_ss2_is_encrypted_secret($plain)) {
    return $plain;
  }

  $key = plugnpay_ss2_secret_key_material();
  if ($key === '' || !function_exists('openssl_encrypt')) {
    return $plain;
  }

  $iv = random_bytes(16);
  $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
  if ($cipher === false) {
    return $plain;
  }

  return 'ss2aes:' . base64_encode($iv . $cipher);
}

/**
 * Decrypt a stored gateway secret. Plaintext values pass through.
 *
 * @param string $stored
 * @return string
 */
function plugnpay_ss2_decrypt_secret($stored) {
  $stored = (string) $stored;

  if ($stored === '' || !plugnpay_ss2_is_encrypted_secret($stored)) {
    return $stored;
  }

  $key = plugnpay_ss2_secret_key_material();
  $raw = base64_decode(substr($stored, 7), true);
  if ($key === '' || $raw === false || strlen($raw) < 17 || !function_exists('openssl_decrypt')) {
    return '';
  }

  $plain = openssl_decrypt(substr($raw, 16), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, substr($raw, 0, 16));

  return $plain === false ? '' : $plain;
}

/**
 * Response hash field names mapped to callback POST keys.
 *
 * @return array<string, string>
 */
function plugnpay_ss2_response_hash_field_map() {
  return array(
    'FinalStatus'    => 'pi_response_status',
    'card-amount'    => 'pt_transaction_amount',
    'currency'       => 'pt_currency',
    'orderID'        => 'pt_order_id',
    'publisher-name' => 'pt_gateway_account',
  );
}

/**
 * Resolve configured response hash fields for a fieldset preset.
 *
 * @param string $fieldset
 * @return string Comma-separated field names in alphabetical order
 */
function plugnpay_ss2_response_hash_fields_for_fieldset($fieldset) {
  $presets = array(
    '1' => 'publisher-name',
    '2' => 'card-amount,publisher-name',
    '3' => 'FinalStatus,card-amount,publisher-name',
    '4' => 'FinalStatus,card-amount,currency,orderID,publisher-name',
    '5' => 'FinalStatus,orderID,card-amount,publisher-name',
  );

  $fieldset = (string) $fieldset;

  return isset($presets[$fieldset]) ? $presets[$fieldset] : $presets['3'];
}

/**
 * Format a callback value for response hash string construction.
 *
 * @param string $field_name
 * @param mixed  $value
 * @return string
 */
function plugnpay_ss2_response_hash_format_value($field_name, $value) {
  if ($field_name === 'card-amount') {
    return plugnpay_ss2_format_amount($value);
  }

  if ($field_name === 'currency') {
    return strtoupper(trim((string) $value));
  }

  return (string) $value;
}

/**
 * Build the field substring used in the response verification hash.
 *
 * Selected fields are concatenated in alphabetical order by field name.
 *
 * @param string $field_list Comma-separated field names
 * @param array  $posted     Callback POST values keyed by POST field name
 * @return string
 */
function plugnpay_ss2_response_hash_string_fields($field_list, array $posted) {
  $map = plugnpay_ss2_response_hash_field_map();
  $fields = array_filter(array_map('trim', explode(',', (string) $field_list)));
  sort($fields, SORT_STRING);

  $parts = '';
  foreach ($fields as $field_name) {
    if (!isset($map[$field_name])) {
      continue;
    }

    $post_key = $map[$field_name];
    $value = isset($posted[$post_key]) ? $posted[$post_key] : '';
    $parts .= plugnpay_ss2_response_hash_format_value($field_name, $value);
  }

  return $parts;
}

/**
 * Verify a Smart Screens response hash using MD5.
 *
 * PlugnPay returns pt_transaction_response_hash as MD5(key + selected fields).
 *
 * @param string $posted_hash
 * @param string $key
 * @param string $field_list Comma-separated field names or fieldset preset id
 * @param array  $posted     Callback POST values keyed by POST field name
 * @return bool
 */
function plugnpay_ss2_response_hash_valid($posted_hash, $key, $field_list, array $posted) {
  $posted_hash = strtolower(trim((string) $posted_hash));
  $key = (string) $key;

  if ($posted_hash === '' || $key === '') {
    return false;
  }

  if (strpos((string) $field_list, ',') === false && ctype_digit((string) $field_list)) {
    $field_list = plugnpay_ss2_response_hash_fields_for_fieldset($field_list);
  }

  $source = $key . plugnpay_ss2_response_hash_string_fields($field_list, $posted);

  return hash_equals(md5($source), $posted_hash);
}

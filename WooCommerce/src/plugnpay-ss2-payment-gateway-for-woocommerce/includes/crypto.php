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
 * Verify a Smart Screens response hash using SHA-256, with MD5 fallback.
 *
 * @param string $posted_hash
 * @param string $key
 * @param string $publisher
 * @param string $order_id
 * @param string $amount
 * @return bool
 */
function plugnpay_ss2_response_hash_valid($posted_hash, $key, $publisher, $order_id, $amount) {
  $posted_hash = strtolower(trim((string) $posted_hash));
  $key = (string) $key;

  if ($posted_hash === '' || $key === '') {
    return false;
  }

  $source = $key . strtolower((string) $publisher) . (string) $order_id . (string) $amount;

  return hash_equals(hash('sha256', $source), $posted_hash)
    || hash_equals(md5($source), $posted_hash);
}

<?php
/**
 * CLI tests for PlugnPay API PCI helpers. Run: php tests/run.php
 */

$base = dirname(__DIR__);
require $base . '/includes/pci.php';

$failed = 0;

function plugnpay_pci_assert($condition, $message) {
  global $failed;
  if ($condition) {
    echo "PASS  {$message}\n";
    return;
  }
  $failed++;
  echo "FAIL  {$message}\n";
}

plugnpay_pci_assert(
  plugnpay_pci_authhash_string_fields('3', '42', '10.00', 'DemoAcct') === '4210.00demoacct',
  'authhash fieldset 3'
);
plugnpay_pci_assert(
  plugnpay_pci_authhash_string_fields('2', '42', '10.00', 'DemoAcct') === '10.00demoacct',
  'authhash fieldset 2'
);
plugnpay_pci_assert(
  plugnpay_pci_authhash_string_fields('1', '42', '10.00', 'DemoAcct') === 'demoacct',
  'authhash fieldset 1'
);

$hash_string = 'secretkey' . '20260826120000' . '4210.00demoacct';
$digest = plugnpay_pci_authhash($hash_string);
plugnpay_pci_assert(strlen($digest) === 64, 'SHA-256 digest length');
plugnpay_pci_assert($digest === hash('sha256', $hash_string), 'SHA-256 digest value');
plugnpay_pci_assert($digest !== md5($hash_string), 'digest is not MD5');

$resolved = plugnpay_pci_resolve_authhash_key('USD:key1,CAD:key2', 'cad', true);
plugnpay_pci_assert($resolved === 'key2', 'divert authhash key');
plugnpay_pci_assert(plugnpay_pci_resolve_authhash_key('onlykey', 'USD', true) === 'onlykey', 'single authhash key');

if (!defined('AUTH_KEY')) {
  define('AUTH_KEY', 'api-test-auth-key-' . str_repeat('a', 32));
}
if (!defined('SECURE_AUTH_KEY')) {
  define('SECURE_AUTH_KEY', 'api-test-secure-key-' . str_repeat('b', 32));
}
if (!defined('AUTH_SALT')) {
  define('AUTH_SALT', 'api-test-auth-salt-' . str_repeat('c', 32));
}

$encrypted = plugnpay_pci_encrypt_secret('plain-secret');
plugnpay_pci_assert(plugnpay_pci_is_encrypted_secret($encrypted), 'secret is prefixed ciphertext');
plugnpay_pci_assert($encrypted !== 'plain-secret', 'secret is not stored plaintext');
plugnpay_pci_assert(plugnpay_pci_decrypt_secret($encrypted) === 'plain-secret', 'secret round-trip');
plugnpay_pci_assert(plugnpay_pci_decrypt_secret('legacy-plaintext') === 'legacy-plaintext', 'legacy plaintext passthrough');
plugnpay_pci_assert(plugnpay_pci_encrypt_secret($encrypted) === $encrypted, 'do not double-encrypt');
plugnpay_pci_assert(plugnpay_pci_persist_encrypted_secret($encrypted, '') === $encrypted, 'blank submit keeps ciphertext');
plugnpay_pci_assert(plugnpay_pci_is_encrypted_secret(plugnpay_pci_persist_encrypted_secret('legacy', '')), 'blank submit migrates plaintext');

echo $failed === 0 ? "\nAll tests passed.\n" : "\n{$failed} test(s) failed.\n";
exit($failed === 0 ? 0 : 1);

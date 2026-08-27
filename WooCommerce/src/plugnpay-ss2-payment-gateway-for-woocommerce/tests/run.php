<?php
/**
 * CLI tests for Smart Screens v2 PCI helpers. Run: php tests/run.php
 */

$base = dirname(__DIR__);
require $base . '/includes/callback-ip.php';
require $base . '/includes/crypto.php';
require $base . '/includes/amounts.php';
require $base . '/includes/admin-ui.php';

$failed = 0;

function plugnpay_ss2_assert($condition, $message) {
  global $failed;
  if ($condition) {
    echo "PASS  {$message}\n";
    return;
  }

  $failed++;
  echo "FAIL  {$message}\n";
}

$expected_ips = array(
  '18.214.78.64',
  '52.3.174.161',
  '54.91.210.62',
  '69.18.198.4',
  '107.22.32.178',
  '3.210.249.25',
);
plugnpay_ss2_assert(plugnpay_ss2_allowed_callback_ips() === $expected_ips, 'callback IP allowlist');

foreach ($expected_ips as $ip) {
  plugnpay_ss2_assert(plugnpay_ss2_is_allowed_callback_ip($ip), "allow {$ip}");
}

plugnpay_ss2_assert(plugnpay_ss2_is_allowed_callback_ip('::ffff:18.214.78.64'), 'allow IPv4-mapped IPv6');
plugnpay_ss2_assert(plugnpay_ss2_is_allowed_callback_ip('18.214.78.64:443'), 'allow IPv4 with port');
plugnpay_ss2_assert(!plugnpay_ss2_is_allowed_callback_ip('1.2.3.4'), 'reject unknown IPv4');
plugnpay_ss2_assert(!plugnpay_ss2_is_allowed_callback_ip('127.0.0.1'), 'reject loopback');
plugnpay_ss2_assert(!plugnpay_ss2_is_allowed_callback_ip(''), 'reject empty IP');
plugnpay_ss2_assert(!plugnpay_ss2_is_allowed_callback_ip('not-an-ip'), 'reject non-IP');

plugnpay_ss2_assert(plugnpay_ss2_hidden_post_endpoint(true) === 'order-received', 'success capture endpoint');
plugnpay_ss2_assert(plugnpay_ss2_hidden_post_endpoint(false) === 'order-pay', 'failure capture endpoint');

plugnpay_ss2_assert(
  plugnpay_ss2_authhash_string_fields('3', '42', '10.00', 'DemoAcct') === '4210.00demoacct',
  'authhash fieldset 3'
);
plugnpay_ss2_assert(
  plugnpay_ss2_authhash_string_fields('2', '42', '10.00', 'DemoAcct') === '10.00demoacct',
  'authhash fieldset 2'
);
plugnpay_ss2_assert(
  plugnpay_ss2_authhash_string_fields('1', '42', '10.00', 'DemoAcct') === 'demoacct',
  'authhash fieldset 1'
);

$hash_string = 'secretkey' . '20260826120000' . '4210.00demoacct';
$digest = plugnpay_ss2_transaction_hash($hash_string);
plugnpay_ss2_assert(strlen($digest) === 64, 'SHA-256 digest length');
plugnpay_ss2_assert($digest === hash('sha256', $hash_string), 'SHA-256 digest value');
plugnpay_ss2_assert($digest !== md5($hash_string), 'digest is not MD5');

plugnpay_ss2_assert(plugnpay_ss2_amounts_match('10.00', '10'), 'amount 10 vs 10.00');
plugnpay_ss2_assert(plugnpay_ss2_amounts_match('10.5', 10.50), 'amount 10.5 vs 10.50');
plugnpay_ss2_assert(!plugnpay_ss2_amounts_match('10.00', '10.01'), 'amount mismatch');
plugnpay_ss2_assert(plugnpay_ss2_currencies_match('usd', 'USD'), 'currency case-insensitive');
plugnpay_ss2_assert(!plugnpay_ss2_currencies_match('USD', 'CAD'), 'currency mismatch');
plugnpay_ss2_assert(!plugnpay_ss2_currencies_match('', 'USD'), 'empty posted currency');

if (!defined('AUTH_KEY')) {
  define('AUTH_KEY', 'ss2-test-auth-key-' . str_repeat('a', 32));
}
if (!defined('SECURE_AUTH_KEY')) {
  define('SECURE_AUTH_KEY', 'ss2-test-secure-key-' . str_repeat('b', 32));
}
if (!defined('AUTH_SALT')) {
  define('AUTH_SALT', 'ss2-test-auth-salt-' . str_repeat('c', 32));
}

$encrypted = plugnpay_ss2_encrypt_secret('plain-secret');
plugnpay_ss2_assert(plugnpay_ss2_is_encrypted_secret($encrypted), 'secret is prefixed ciphertext');
plugnpay_ss2_assert($encrypted !== 'plain-secret', 'secret is not stored plaintext');
plugnpay_ss2_assert(plugnpay_ss2_decrypt_secret($encrypted) === 'plain-secret', 'secret round-trip');
plugnpay_ss2_assert(plugnpay_ss2_decrypt_secret('legacy-plaintext') === 'legacy-plaintext', 'legacy plaintext passthrough');
plugnpay_ss2_assert(plugnpay_ss2_encrypt_secret($encrypted) === $encrypted, 'do not double-encrypt');

plugnpay_ss2_assert(
  plugnpay_ss2_response_hash_fields_for_fieldset('3') === 'FinalStatus,card-amount,publisher-name',
  'response hash fieldset 3'
);
plugnpay_ss2_assert(
  plugnpay_ss2_response_hash_string_fields(
    'FinalStatus,card-amount,publisher-name',
    array(
      'pi_response_status' => 'success',
      'pt_transaction_amount' => '10',
      'pt_gateway_account' => 'pnpdemo',
    )
  ) === 'success10.00pnpdemo',
  'response hash string fields alphabetical order'
);

$resp_source_key = 'respkey';
$resp_posted = array(
  'pi_response_status' => 'success',
  'pt_transaction_amount' => '10.00',
  'pt_gateway_account' => 'pnpdemo',
);
$resp_ok = md5('respkey' . 'success10.00pnpdemo');
plugnpay_ss2_assert(
  plugnpay_ss2_response_hash_valid($resp_ok, $resp_source_key, '3', $resp_posted),
  'response hash MD5 fieldset preset'
);
plugnpay_ss2_assert(
  plugnpay_ss2_response_hash_valid($resp_ok, $resp_source_key, 'FinalStatus,card-amount,publisher-name', $resp_posted),
  'response hash MD5 explicit field list'
);
plugnpay_ss2_assert(
  !plugnpay_ss2_response_hash_valid(hash('sha256', 'respkeysuccess10.00pnpdemo'), $resp_source_key, '3', $resp_posted),
  'response hash rejects SHA-256'
);
plugnpay_ss2_assert(
  !plugnpay_ss2_response_hash_valid($resp_ok, 'wrong', '3', $resp_posted),
  'response hash rejects wrong key'
);

$all_fields_posted = array(
  'pi_response_status' => 'success',
  'pt_transaction_amount' => '10.5',
  'pt_currency' => 'usd',
  'pt_order_id' => '2008120816235912345',
  'pt_gateway_account' => 'pnpdemo',
);
plugnpay_ss2_assert(
  plugnpay_ss2_response_hash_string_fields(plugnpay_ss2_response_hash_fields_for_fieldset('4'), $all_fields_posted) === 'success10.50USD2008120816235912345pnpdemo',
  'response hash all fields with formatting'
);

$dependent = plugnpay_ss2_admin_dependent_fields();
plugnpay_ss2_assert(
  isset($dependent['authhash']) && $dependent['authhash'] === array('authhash_key', 'authhash_fields'),
  'auth hash fields depend on enable checkbox'
);
plugnpay_ss2_assert(
  isset($dependent['response_hash']) && $dependent['response_hash'] === array('response_hash_key', 'response_hash_fields'),
  'response hash fields depend on enable checkbox'
);
plugnpay_ss2_assert(
  isset($dependent['divert_currency']) && $dependent['divert_currency'] === array('divert_accounts'),
  'divert accounts depend on divert checkbox'
);
plugnpay_ss2_assert(plugnpay_ss2_minimum_php_version() === '8.2', 'minimum PHP is 8.2');

echo $failed === 0 ? "\nAll tests passed.\n" : "\n{$failed} test(s) failed.\n";
exit($failed === 0 ? 0 : 1);

<?php
/**
 * Shared PCI helpers for PlugnPay WooCommerce API modules.
 *
 * Each API plugin ships a copy of this file. Define helpers only once so
 * Credit Card, ACH, and Tokenization can be active together.
 */

if (!function_exists('plugnpay_pci_authhash_string_fields')) :

/**
 * @param string $fieldset
 * @param string|int $order_id
 * @param string $order_amount
 * @param string $gateway_account
 * @return string
 */
function plugnpay_pci_authhash_string_fields($fieldset, $order_id, $order_amount, $gateway_account) {
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
 * @param string $hash_string
 * @return string
 */
function plugnpay_pci_authhash($hash_string) {
  return hash('sha256', (string) $hash_string);
}

/**
 * @param string $authhash_key
 * @param string $currency_code
 * @param bool   $divert_enabled
 * @return string
 */
function plugnpay_pci_resolve_authhash_key($authhash_key, $currency_code, $divert_enabled) {
  $authhash_key = (string) $authhash_key;

  if (!$divert_enabled || strpos($authhash_key, ',') === false) {
    return $authhash_key;
  }

  foreach (array_map('trim', explode(',', $authhash_key)) as $entry) {
    if ($entry === '') {
      continue;
    }

    $parts = explode(':', $entry, 2);
    if (count($parts) !== 2) {
      continue;
    }

    if (strtoupper(trim($parts[0])) === strtoupper((string) $currency_code)) {
      return trim($parts[1]);
    }
  }

  return $authhash_key;
}

/**
 * @param string $stored
 * @return bool
 */
function plugnpay_pci_is_encrypted_secret($stored) {
  return strpos((string) $stored, 'pnpenc:') === 0;
}

/**
 * @return string
 */
function plugnpay_pci_secret_key_material() {
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
 * @param string $plain
 * @return string
 */
function plugnpay_pci_encrypt_secret($plain) {
  $plain = (string) $plain;

  if ($plain === '' || plugnpay_pci_is_encrypted_secret($plain)) {
    return $plain;
  }

  $key = plugnpay_pci_secret_key_material();
  if ($key === '' || !function_exists('openssl_encrypt')) {
    return $plain;
  }

  $iv = random_bytes(16);
  $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
  if ($cipher === false) {
    return $plain;
  }

  return 'pnpenc:' . base64_encode($iv . $cipher);
}

/**
 * @param string $stored
 * @return string
 */
function plugnpay_pci_decrypt_secret($stored) {
  $stored = (string) $stored;

  if ($stored === '' || !plugnpay_pci_is_encrypted_secret($stored)) {
    return $stored;
  }

  $key = plugnpay_pci_secret_key_material();
  $raw = base64_decode(substr($stored, 7), true);
  if ($key === '' || $raw === false || strlen($raw) < 17 || !function_exists('openssl_decrypt')) {
    return '';
  }

  $plain = openssl_decrypt(substr($raw, 16), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, substr($raw, 0, 16));

  return $plain === false ? '' : $plain;
}

/**
 * @param string $existing
 * @param mixed  $submitted
 * @return string
 */
function plugnpay_pci_persist_encrypted_secret($existing, $submitted) {
  $submitted = is_string($submitted) ? trim($submitted) : '';
  if (function_exists('wp_unslash')) {
    $submitted = trim(wp_unslash($submitted));
  }

  if ($submitted !== '') {
    return plugnpay_pci_encrypt_secret($submitted);
  }

  $existing = (string) $existing;
  if ($existing === '') {
    return '';
  }

  if (plugnpay_pci_is_encrypted_secret($existing)) {
    return $existing;
  }

  return plugnpay_pci_encrypt_secret($existing);
}

/**
 * @return bool
 */
function plugnpay_pci_storefront_is_secure() {
  if (function_exists('is_ssl') && is_ssl()) {
    return true;
  }

  if (function_exists('wp_get_environment_type') && in_array(wp_get_environment_type(), array('local', 'development'), true)) {
    return true;
  }

  return false;
}

/**
 * Password input that never echoes the stored secret.
 *
 * @param WC_Settings_API $gateway
 * @param string          $key
 * @param array           $data
 * @param string          $text_domain
 * @return string
 */
function plugnpay_pci_generate_password_html($gateway, $key, $data, $text_domain) {
  $field_key = $gateway->get_field_key($key);
  $data = wp_parse_args(
    $data,
    array(
      'title'             => '',
      'disabled'          => false,
      'class'             => '',
      'css'               => '',
      'placeholder'       => '',
      'desc_tip'          => false,
      'description'       => '',
      'custom_attributes' => array(),
    )
  );

  $custom_attributes = array();
  if (!empty($data['custom_attributes']) && is_array($data['custom_attributes'])) {
    foreach ($data['custom_attributes'] as $attribute => $attribute_value) {
      $custom_attributes[] = esc_attr($attribute) . '="' . esc_attr($attribute_value) . '"';
    }
  }

  $has_value = $gateway->get_option($key) !== '';
  $placeholder = $has_value ? esc_attr__('(unchanged)', $text_domain) : '';

  ob_start();
  ?>
  <tr valign="top">
    <th scope="row" class="titledesc">
      <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?></label>
    </th>
    <td class="forminp">
      <fieldset>
        <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
        <input
          class="input-text regular-input <?php echo esc_attr($data['class']); ?>"
          type="password"
          name="<?php echo esc_attr($field_key); ?>"
          id="<?php echo esc_attr($field_key); ?>"
          style="<?php echo esc_attr($data['css']); ?>"
          value=""
          placeholder="<?php echo $placeholder; ?>"
          <?php echo implode(' ', $custom_attributes); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          <?php disabled($data['disabled']); ?>
        />
        <?php echo $gateway->get_description_html($data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      </fieldset>
    </td>
  </tr>
  <?php
  return ob_get_clean();
}

endif;

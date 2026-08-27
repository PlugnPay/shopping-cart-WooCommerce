<?php
/*
 * Plugin Name: PlugnPay API ACH/eCheck Payment Gateway For WooCommerce
 * Plugin URI: https://github.com/PlugnPay/shopping-cart-WooCommerce
 * Description: Extends WooCommerce to Process API ACH/eCheck Payments with PlugnPay gateway.
 * Version: 1.2.1
 * Author: PlugnPay
 * Author URI: https://www.plugnpay.com
 * Text Domain: woocommerce_plugnpay_api_ach
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
*/

defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/pci.php';
require_once __DIR__ . '/includes/admin-ui.php';

add_action('before_woocommerce_init', 'woocommerce_plugnpay_api_ach_declare_compatibility');

function woocommerce_plugnpay_api_ach_declare_compatibility() {
  if (!class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
    return;
  }

  \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
}

add_action('plugins_loaded', 'woocommerce_plugnpay_api_ach_init', 0);

function woocommerce_plugnpay_api_ach_init() {
  if (!class_exists('WC_Payment_Gateway')) {
    return;
  }

  if (version_compare(PHP_VERSION, plugnpay_api_ach_minimum_php_version(), '<')) {
    add_action('admin_notices', 'woocommerce_plugnpay_api_ach_php_notice');
    return;
  }

  if (!function_exists('WC') || version_compare(WC()->version, '8.0', '<')) {
    add_action('admin_notices', 'woocommerce_plugnpay_api_ach_version_notice');
    return;
  }

  load_plugin_textdomain('woocommerce_plugnpay_api_ach', false, dirname(plugin_basename(__FILE__)) . '/languages');

  class WC_Plugnpay_API_ACH_Gateway extends WC_Payment_Gateway {

    public function __construct() {
      $this->id                 = 'plugnpay_api_ach';
      $this->method_title       = __('PlugnPay API ACH', 'woocommerce_plugnpay_api_ach');
      $this->method_description = __('Accept ACH/eCheck payments via API payment method, directly in WooCommerce.', 'woocommerce_plugnpay_api_ach');
      $this->icon               = '';
      $this->has_fields         = true;
      $this->supports           = array('products');
      $this->init_form_fields();
      $this->init_settings();
      $this->title              = $this->settings['title'];
      $this->description        = $this->settings['description'];

      $wc_version = defined('WC_VERSION') ? WC_VERSION : (defined('WOOCOMMERCE_VERSION') ? WOOCOMMERCE_VERSION : '0');
      if (version_compare($wc_version, '2.0.0', '>=')) {
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
      }
      else {
        add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
      }

      add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_styles'));
      add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
      add_action('admin_notices', array($this, 'admin_security_notices'));
    }

    /**
    * Checkout styles for payment fields.
    **/
    public function enqueue_checkout_styles() {
      if (!is_checkout() && !is_wc_endpoint_url('order-pay')) {
        return;
      }

      wp_enqueue_style(
        'plugnpay-api-ach-checkout',
        plugins_url('assets/css/checkout.css', __FILE__),
        array(),
        '1.2.1'
      );
    }

    /**
     * Section headings and show/hide for dependent gateway settings.
     *
     * @param string $hook
     */
    public function enqueue_admin_assets($hook) {
      if ($hook !== 'woocommerce_page_wc-settings') {
        return;
      }

      $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
      $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';
      if ($tab !== 'checkout' || $section !== $this->id) {
        return;
      }

      wp_enqueue_style(
        'plugnpay-api-ach-admin-settings',
        plugins_url('assets/css/admin-settings.css', __FILE__),
        array(),
        '1.2.1'
      );
      wp_enqueue_script(
        'plugnpay-api-ach-admin-settings',
        plugins_url('assets/js/admin-settings.js', __FILE__),
        array('jquery'),
        '1.2.1',
        true
      );
      wp_localize_script(
        'plugnpay-api-ach-admin-settings',
        'plugnpayApiAchAdmin',
        array(
          'gatewayId'       => $this->id,
          'dependentFields' => plugnpay_api_ach_admin_dependent_fields(),
        )
      );
    }

    function init_form_fields() {
      $this->form_fields = array(
        'section_checkout' => array(
          'title'       => __('Checkout display', 'woocommerce_plugnpay_api_ach'),
          'type'        => 'title',
          'class'       => 'plugnpay-api-ach-settings-section plugnpay-api-ach-settings-section-first',
          'description' => __('How this payment method appears on the checkout page.', 'woocommerce_plugnpay_api_ach'),
        ),
        'enabled' => array(
          'title'   => __('Enable/Disable', 'woocommerce_plugnpay_api_ach'),
          'type'    => 'checkbox',
          'label'   => __('Enable PlugnPay API ACH Payment Module.', 'woocommerce_plugnpay_api_ach'),
          'default' => 'no',
        ),
        'title' => array(
          'title'       => __('Title:', 'woocommerce_plugnpay_api_ach'),
          'type'        => 'text',
          'description' => __('This controls the title which the user sees during checkout.', 'woocommerce_plugnpay_api_ach'),
          'default'     => __('Checking Account', 'woocommerce_plugnpay_api_ach'),
          'desc_tip'    => true,
        ),
        'description' => array(
          'title'       => __('Description:', 'woocommerce_plugnpay_api_ach'),
          'type'        => 'textarea',
          'description' => __('This controls the description which the user sees during checkout.', 'woocommerce_plugnpay_api_ach'),
          'default'     => __('Pay securely by Checking Account through PlugnPay Secure Servers.', 'woocommerce_plugnpay_api_ach'),
          'desc_tip'    => true,
        ),
        'section_gateway' => array(
          'title'       => __('Gateway account', 'woocommerce_plugnpay_api_ach'),
          'type'        => 'title',
          'class'       => 'plugnpay-api-ach-settings-section',
          'description' => __('PlugnPay account used for this store, Remote Client Password, and how approved payments are captured.', 'woocommerce_plugnpay_api_ach'),
        ),
        'gateway_account' => array(
          'title'       => __('Gateway Account', 'woocommerce_plugnpay_api_ach'),
          'type'        => 'text',
          'description' => __('Username issued by PlugnPay at time of sign up.', 'woocommerce_plugnpay_api_ach'),
        ),
        'remote_password' => array(
          'title'       => __('Remote Client Password', 'woocommerce_plugnpay_api_ach'),
          'type'        => 'password',
          'description' => __('Required. Leave blank to keep the current password. Created in PlugnPay Security Administration.', 'woocommerce_plugnpay_api_ach'),
          'custom_attributes' => array(
            'autocomplete' => 'new-password',
          ),
        ),
        'post_auth' => array(
          'title'       => __('Transaction Settlement', 'woocommerce_plugnpay_api_ach'),
          'type'        => 'select',
          'options'     => array(
            'yes' => __('Authorize and Settle', 'woocommerce_plugnpay_api_ach'),
            'no'  => __('Authorize Only', 'woocommerce_plugnpay_api_ach'),
          ),
          'description' => __('If you are not sure what to use, set to Authorize and Settle.', 'woocommerce_plugnpay_api_ach'),
          'desc_tip'    => true,
        ),
        'section_messages' => array(
          'title'       => __('Customer messages', 'woocommerce_plugnpay_api_ach'),
          'type'        => 'title',
          'class'       => 'plugnpay-api-ach-settings-section',
          'description' => __('Shown to the shopper after payment succeeds or fails.', 'woocommerce_plugnpay_api_ach'),
        ),
        'success_message' => array(
          'title'       => __('Transaction Success Message', 'woocommerce_plugnpay_api_ach'),
          'type'        => 'textarea',
          'description' => __('Message to be displayed on successful transaction.', 'woocommerce_plugnpay_api_ach'),
          'default'     => __('Your payment has been processed successfully.', 'woocommerce_plugnpay_api_ach'),
          'desc_tip'    => true,
        ),
        'failed_message' => array(
          'title'       => __('Transaction Failed Message', 'woocommerce_plugnpay_api_ach'),
          'type'        => 'textarea',
          'description' => __('Message to be displayed on failed transaction.', 'woocommerce_plugnpay_api_ach'),
          'default'     => __('Your transaction has been declined.', 'woocommerce_plugnpay_api_ach'),
          'desc_tip'    => true,
        ),
        'section_authhash' => array(
          'title'       => __('Authorization Verification Hash (SHA-256)', 'woocommerce_plugnpay_api_ach'),
          'type'        => 'title',
          'class'       => 'plugnpay-api-ach-settings-section',
          'description' => __('Recommended. Enable this in both the module and PlugnPay Merchant Admin → Security Administration. The key and fieldset must match.', 'woocommerce_plugnpay_api_ach'),
        ),
        'authhash' => array(
          'title'       => __('Authorization Hash', 'woocommerce_plugnpay_api_ach'),
          'type'        => 'checkbox',
          'label'       => __('Enable Authorization Verification Hash (SHA-256). Recommended.', 'woocommerce_plugnpay_api_ach'),
          'default'     => 'yes',
        ),
        'authhash_key' => array(
          'title'       => __('Authorization Hash Key', 'woocommerce_plugnpay_api_ach'),
          'type'        => 'password',
          'description' => __('Leave blank to keep the current key. If using Divert Currency, list each currency with its associated key [i.e. USD:key1,BBD:key2,CAD:key3].', 'woocommerce_plugnpay_api_ach'),
          'default'     => '',
          'custom_attributes' => array(
            'autocomplete' => 'new-password',
          ),
        ),
        'authhash_fields' => array(
          'title'       => __('Authorization Hash Fields', 'woocommerce_plugnpay_api_ach'),
          'type'        => 'select',
          'options'     => array(
            '1' => 'publisher-name',
            '2' => 'card-amount,publisher-name',
            '3' => 'acct_code,card-amount,publisher-name',
          ),
          'description' => __('Fieldset to use with authhash validation. Must configure your PlugnPay account to match.', 'woocommerce_plugnpay_api_ach'),
          'default'     => '3',
          'desc_tip'    => true,
        ),
        'section_optional' => array(
          'title'       => __('Optional features', 'woocommerce_plugnpay_api_ach'),
          'type'        => 'title',
          'class'       => 'plugnpay-api-ach-settings-section',
          'description' => __('Currency diversion requires matching PlugnPay account features. Multiple gateway accounts are required, each set up for a different currency.', 'woocommerce_plugnpay_api_ach'),
        ),
        'divert_currency' => array(
          'title'   => __('Divert Currency', 'woocommerce_plugnpay_api_ach'),
          'type'    => 'checkbox',
          'label'   => __('Enable to divert payments for specific currencies to another gateway account.', 'woocommerce_plugnpay_api_ach'),
          'default' => 'no',
        ),
        'divert_accounts' => array(
          'title'       => __('Diverted Accounts', 'woocommerce_plugnpay_api_ach'),
          'type'        => 'text',
          'description' => __('List currency code, username & Remote Client Password to divert specific payments to.<br>[i.e. USD:username1:abcd1234,BBD:username2:efgh2345,CAD:username3:ijkl3456]<br>Legacy two-part entries (USD:username) use the default Remote Client Password.<br>Currency codes not listed will use default Gateway Account.', 'woocommerce_plugnpay_api_ach'),
        ),
      );
    }

    public function process_admin_options() {
      $saved_password = $this->get_option('remote_password');
      $saved_hash = $this->get_option('authhash_key');
      parent::process_admin_options();
      if ('' === $this->get_option('remote_password') && '' !== $saved_password) {
        $this->update_option('remote_password', $saved_password);
      }
      if ('' === $this->get_option('authhash_key') && '' !== $saved_hash) {
        $this->update_option('authhash_key', $saved_hash);
      }
    }

    public function generate_password_html($key, $data) {
      return plugnpay_pci_generate_password_html($this, $key, $data, 'woocommerce_plugnpay_api_ach');
    }

    public function validate_remote_password_field($key, $value) {
      return plugnpay_pci_persist_encrypted_secret($this->get_option($key), $value);
    }

    public function validate_authhash_key_field($key, $value) {
      return plugnpay_pci_persist_encrypted_secret($this->get_option($key), $value);
    }

    private function get_plain_secret($setting_key) {
      $stored = isset($this->settings[$setting_key]) ? $this->settings[$setting_key] : '';
      return plugnpay_pci_decrypt_secret($stored);
    }

    private function is_authhash_configured() {
      return isset($this->settings['authhash'])
        && $this->settings['authhash'] === 'yes'
        && $this->get_plain_secret('authhash_key') !== '';
    }

    public function admin_security_notices() {
      if ($this->get_option('enabled') !== 'yes' || !current_user_can('manage_woocommerce')) {
        return;
      }

      if ($this->get_plain_secret('remote_password') === '') {
        echo '<div class="error"><p>' . esc_html__('PlugnPay API ACH: Remote Client Password is required.', 'woocommerce_plugnpay_api_ach') . '</p></div>';
      }

      if (!$this->is_authhash_configured()) {
        echo '<div class="notice notice-warning"><p>' . esc_html__('PlugnPay API ACH: Authorization Verification Hash (SHA-256) is recommended. Enable it here and in PlugnPay Security Administration with a matching key.', 'woocommerce_plugnpay_api_ach') . '</p></div>';
      }

      if (!plugnpay_pci_storefront_is_secure()) {
        echo '<div class="error"><p>' . esc_html__('PlugnPay API ACH: HTTPS is required on the storefront because bank account data is collected onsite.', 'woocommerce_plugnpay_api_ach') . '</p></div>';
      }

      if (version_compare(PHP_VERSION, '8.3', '<')) {
        echo '<div class="notice notice-warning"><p>' . esc_html__('PlugnPay API ACH: PHP 8.3 or higher is recommended. PHP 8.2 security support ends 31 Dec 2026.', 'woocommerce_plugnpay_api_ach') . '</p></div>';
      }
    }

    public function get_icon() {
      $icon_url = plugins_url('images/echeck.png', __FILE__);
      $icons = '<span class="plugnpay-ach-icons"><img src="' . esc_url($icon_url) . '" alt="' . esc_attr__('eCheck', 'woocommerce_plugnpay_api_ach') . '" /></span>';

      return apply_filters('woocommerce_gateway_icon', $icons, $this->id);
    }

    public function admin_options() {
      echo '<h3>'.esc_html__('PlugnPay API ACH Payment Gateway', 'woocommerce_plugnpay_api_ach').'</h3>';
      echo '<p>'.esc_html__('PlugnPay is a popular payment gateway for online payment processing', 'woocommerce_plugnpay_api_ach').'</p>';
      echo '<table class="form-table">';
      $this->generate_settings_html();
      echo '</table>';
    }

    function payment_fields() {
      if ($this->description) {
        echo '<div class="plugnpay-ach-description">' . wp_kses_post(wpautop(wptexturize($this->description))) . '</div>';
      }

      echo '<fieldset class="plugnpay-ach-fields wc-payment-form">';
      echo '<legend class="screen-reader-text">' . esc_html__('Bank account details', 'woocommerce_plugnpay_api_ach') . '</legend>';

      $this->render_account_option_fields();
      $this->render_bank_account_fields();
      $this->render_check_number_field();

      echo '</fieldset>';
    }

    /**
    * Render account type and classification side by side.
    **/
    private function render_account_option_fields() {
      $required_mark = ' <abbr class="required" title="' . esc_attr__('required', 'woocommerce_plugnpay_api_ach') . '">*</abbr>';

      echo '<p class="form-row form-row-wide plugnpay-ach-options-row">';
      echo '<span class="plugnpay-ach-inline-fields">';

      echo '<span class="plugnpay-ach-inline-field">';
      echo '<label for="pnp_accttype">' . esc_html__('Account type', 'woocommerce_plugnpay_api_ach') . $required_mark . '</label>';
      echo '<select name="pnp_accttype" id="pnp_accttype" class="input-text" autocomplete="off" required>';
      echo '<option value="checking">' . esc_html__('Checking', 'woocommerce_plugnpay_api_ach') . '</option>';
      echo '<option value="savings">' . esc_html__('Savings', 'woocommerce_plugnpay_api_ach') . '</option>';
      echo '</select>';
      echo '</span>';

      echo '<span class="plugnpay-ach-inline-field">';
      echo '<label for="pnp_acctclass">' . esc_html__('Classification', 'woocommerce_plugnpay_api_ach') . $required_mark . '</label>';
      echo '<select name="pnp_acctclass" id="pnp_acctclass" class="input-text" autocomplete="off" required>';
      echo '<option value="personal">' . esc_html__('Personal', 'woocommerce_plugnpay_api_ach') . '</option>';
      echo '<option value="business">' . esc_html__('Business', 'woocommerce_plugnpay_api_ach') . '</option>';
      echo '</select>';
      echo '</span>';

      echo '</span></p>';
    }

    /**
    * Render routing and account number side by side.
    **/
    private function render_bank_account_fields() {
      $required_mark = ' <abbr class="required" title="' . esc_attr__('required', 'woocommerce_plugnpay_api_ach') . '">*</abbr>';

      echo '<p class="form-row form-row-wide plugnpay-ach-bank-row">';
      echo '<span class="plugnpay-ach-inline-fields">';

      echo '<span class="plugnpay-ach-inline-field plugnpay-ach-field-routing">';
      echo '<label for="pnp_routingnum">' . esc_html__('Routing number', 'woocommerce_plugnpay_api_ach') . $required_mark . '</label>';
      echo '<input type="tel" class="input-text" name="pnp_routingnum" id="pnp_routingnum" maxlength="9" minlength="9" pattern="[0-9]{9}" inputmode="numeric" autocomplete="off" required />';
      echo '<span class="plugnpay-ach-field-hint">' . esc_html__('9 digits, bottom left of your check', 'woocommerce_plugnpay_api_ach') . '</span>';
      echo '</span>';

      echo '<span class="plugnpay-ach-inline-field plugnpay-ach-field-account">';
      echo '<label for="pnp_accountnum">' . esc_html__('Account number', 'woocommerce_plugnpay_api_ach') . $required_mark . '</label>';
      echo '<input type="tel" class="input-text" name="pnp_accountnum" id="pnp_accountnum" maxlength="20" inputmode="numeric" autocomplete="off" required />';
      echo '</span>';

      echo '</span></p>';
    }

    /**
    * Render optional check number field.
    **/
    private function render_check_number_field() {
      echo '<p class="form-row form-row-wide plugnpay-ach-check-row">';
      echo '<span class="plugnpay-ach-inline-fields">';

      echo '<span class="plugnpay-ach-inline-field plugnpay-ach-field-check">';
      echo '<label for="pnp_checknum">' . esc_html__('Check number', 'woocommerce_plugnpay_api_ach') . '</label>';
      echo '<input type="tel" class="input-text" name="pnp_checknum" id="pnp_checknum" maxlength="20" inputmode="numeric" autocomplete="off" />';
      echo '<span class="plugnpay-ach-field-hint">' . esc_html__('Optional', 'woocommerce_plugnpay_api_ach') . '</span>';
      echo '</span>';

      echo '</span></p>';
    }

    public function validate_fields() {
      $valid = true;
      $routingnum = $this->get_posted_field('pnp_routingnum');
      $accountnum = $this->get_digits_only('pnp_accountnum');
      $checknum = $this->get_posted_field('pnp_checknum');
      $accttype = $this->get_posted_field('pnp_accttype');
      $acctclass = $this->get_posted_field('pnp_acctclass');

      if (!$this->isRoutingNumber($routingnum)) {
        wc_add_notice(__('(Routing Number) is not valid.', 'woocommerce_plugnpay_api_ach'), 'error');
        $valid = false;
      }
      if (!$this->isAccountNumber($accountnum)) {
        wc_add_notice(__('(Account Number) is not valid.', 'woocommerce_plugnpay_api_ach'), 'error');
        $valid = false;
      }
      if ($checknum !== '' && !$this->isCheckNumber($checknum)) {
        wc_add_notice(__('(Check Number) is not valid.', 'woocommerce_plugnpay_api_ach'), 'error');
        $valid = false;
      }
      if (!in_array($accttype, array('checking', 'savings'), true)) {
        wc_add_notice(__('(Account Type) is not valid.', 'woocommerce_plugnpay_api_ach'), 'error');
        $valid = false;
      }
      if (!in_array($acctclass, array('personal', 'business'), true)) {
        wc_add_notice(__('(Classification) is not valid.', 'woocommerce_plugnpay_api_ach'), 'error');
        $valid = false;
      }

      return $valid;
    }

    private function get_posted_field($key) {
      if (!isset($_POST[$key])) {
        return '';
      }
      return sanitize_text_field(wp_unslash($_POST[$key]));
    }

    private function get_digits_only($key) {
      return preg_replace('/[^0-9]+/', '', $this->get_posted_field($key));
    }

    private function get_order_amount($order) {
      return wc_format_decimal($order->get_total(), 2);
    }

    private function resolve_gateway_credentials($order) {
      $gatewayAccount = trim($this->settings['gateway_account']);
      $currencyCode = $order->get_currency();
      $remotePassword = $this->get_plain_secret('remote_password');

      if ($this->settings['divert_currency'] !== 'yes' || empty($this->settings['divert_accounts'])) {
        return array($gatewayAccount, $currencyCode, $remotePassword);
      }

      $divert_list = array_map('trim', explode(',', $this->settings['divert_accounts']));
      foreach ($divert_list as $entry) {
        if ($entry === '') {
          continue;
        }

        $parts = explode(':', $entry, 3);
        $altCurrency = isset($parts[0]) ? trim($parts[0]) : '';
        $altMerchant = isset($parts[1]) ? trim($parts[1]) : '';
        $altPassword = isset($parts[2]) ? trim($parts[2]) : '';

        if ($altCurrency === '' || $altMerchant === '') {
          $this->log_gateway_event('Invalid divert account entry skipped');
          continue;
        }

        if (strtolower($altCurrency) !== strtolower($order->get_currency())) {
          continue;
        }

        if ($altPassword === '') {
          $altPassword = $remotePassword;
          $this->log_gateway_event('Divert entry using default remote password for currency: ' . $altCurrency);
        }

        return array($altMerchant, $altCurrency, $altPassword);
      }

      return array($gatewayAccount, $currencyCode, $remotePassword);
    }

    private function resolve_authhash_key($currencyCode) {
      return plugnpay_pci_resolve_authhash_key(
        $this->get_plain_secret('authhash_key'),
        $currencyCode,
        $this->settings['divert_currency'] === 'yes'
      );
    }

    private function log_gateway_event($message, $level = 'info') {
      if (!function_exists('wc_get_logger')) {
        return;
      }

      $logger = wc_get_logger();
      $context = array('source' => 'plugnpay_api_ach');
      $logger->log($level, $message, $context);
    }

    private function isRoutingNumber($toCheck) {
      $number = preg_replace('/[^0-9]+/', '', $toCheck);

      if (strlen($number) != 9) {
        return false;
      }

      $one   = $number[0] * 3;
      $two   = $number[1] * 7;
      $three = $number[2] * 1;
      $four  = $number[3] * 3;
      $five  = $number[4] * 7;
      $six   = $number[5] * 1;
      $seven = $number[6] * 3;
      $eight = $number[7] * 7;
      $nine  = $number[8] * 1;

      $sum = $one + $two + $three + $four + $five + $six + $seven + $eight + $nine;

      return ($sum % 10 == 0);
    }

    private function isAccountNumber($toCheck) {
      $length = strlen($toCheck);
      return ($length >= 1 && $length < 21 && ctype_digit($toCheck));
    }

    private function isCheckNumber($toCheck) {
      $digits = preg_replace('/[^0-9]+/', '', $toCheck);
      $length = strlen($digits);
      return ($length >= 1 && $length < 21);
    }

    function process_payment($order_id) {
      $order = wc_get_order($order_id);

      if (!$order) {
        wc_add_notice(__('(Transaction Error) Invalid order.', 'woocommerce_plugnpay_api_ach'), 'error');
        return array('result' => 'failure');
      }

      if (empty($this->settings['gateway_account']) || $this->get_plain_secret('remote_password') === '') {
        wc_add_notice(__('(Transaction Error) Payment gateway is not configured.', 'woocommerce_plugnpay_api_ach'), 'error');
        return array('result' => 'failure');
      }

      if (!plugnpay_pci_storefront_is_secure()) {
        wc_add_notice(__('(Transaction Error) Secure HTTPS checkout is required.', 'woocommerce_plugnpay_api_ach'), 'error');
        return array('result' => 'failure');
      }

      $params = $this->generate_plugnpay_api_ach_params($order);

      $post_string = '';
      foreach ($params as $key => $value) {
        $post_string .= "$key=" . urlencode($value) . '&';
      }
      $post_string = rtrim($post_string, '&');

      $this->log_gateway_event('Sending payment request for order ' . $order->get_id());

      $request = curl_init('https://pay1.plugnpay.com/payment/pnpremote.cgi');
      curl_setopt($request, CURLOPT_HEADER, 0);
      curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($request, CURLOPT_POSTFIELDS, $post_string);
      curl_setopt($request, CURLOPT_SSL_VERIFYPEER, true);
      curl_setopt($request, CURLOPT_SSL_VERIFYHOST, 2);
      if (defined('CURLPROTO_HTTPS')) {
        curl_setopt($request, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
      }
      curl_setopt($request, CURLOPT_TIMEOUT, 60);
      curl_setopt($request, CURLOPT_CONNECTTIMEOUT, 30);
      $post_response = curl_exec($request);
      $curl_error = curl_error($request);
      curl_close($request);

      if ($post_response === false) {
        $this->log_gateway_event('cURL error for order ' . $order->get_id() . ': ' . $curl_error, 'error');
        $order->add_order_note($this->settings['failed_message'] . ' cURL error: ' . $curl_error);
        $order->update_status('failed');
        wc_add_notice(__('(Transaction Error) Error connecting to payment gateway.', 'woocommerce_plugnpay_api_ach'), 'error');
        return array('result' => 'failure');
      }

      parse_str($post_response, $response);
      $final_status = isset($response['FinalStatus']) ? $response['FinalStatus'] : '';
      $merr_msg = isset($response['MErrMsg']) ? $response['MErrMsg'] : '';
      $transaction_id = isset($response['orderID']) ? $response['orderID'] : '';

      $this->log_gateway_event(
        'Gateway response for order ' . $order->get_id() . ': FinalStatus=' . $final_status . ' MErrMsg=' . $merr_msg . ' orderID=' . $transaction_id
      );

      if ($final_status === '') {
        $order->add_order_note($this->settings['failed_message']);
        $order->update_status('failed');
        wc_add_notice(__('(Transaction Error) Error processing payment.', 'woocommerce_plugnpay_api_ach'), 'error');
        return array('result' => 'failure');
      }

      if ($final_status === 'success') {
        if ($order->get_status() !== 'completed') {
          $order->payment_complete($transaction_id);
          WC()->cart->empty_cart();
          $order->add_order_note($this->settings['success_message'] . $merr_msg . ' Transaction ID: ' . $transaction_id);
        }
        return array(
          'result'   => 'success',
          'redirect' => $this->get_return_url($order)
        );
      }

      if ($final_status === 'pending') {
        if ($order->get_status() !== 'on-hold' && $order->get_status() !== 'completed') {
          $order->update_status(
            'on-hold',
            $this->settings['success_message'] . $merr_msg . ' Transaction ID: ' . $transaction_id
          );
          if ($transaction_id !== '') {
            $order->set_transaction_id($transaction_id);
            $order->save();
          }
          WC()->cart->empty_cart();
        }
        return array(
          'result'   => 'success',
          'redirect' => $this->get_return_url($order)
        );
      }

      $order->add_order_note($this->settings['failed_message'] . $merr_msg);
      $order->update_status('failed');
      wc_add_notice(sprintf(__('(Transaction Error) %s', 'woocommerce_plugnpay_api_ach'), $merr_msg), 'error');
      return array('result' => 'failure');
    }

    public function generate_plugnpay_api_ach_params($order) {
      list($gatewayAccount, $currencyCode, $remotePassword) = $this->resolve_gateway_credentials($order);
      $order_amount = $this->get_order_amount($order);
      $order_id = $order->get_id();
      $acctclass = $this->get_posted_field('pnp_acctclass');

      $plugnpayapi_args = array(
        'publisher-name'        => strtolower($gatewayAccount),
        'publisher-password'    => $remotePassword,
        'client'                => 'WooCommerce_API_ACH',
        'mode'                  => 'auth',

        'acct_code'             => $order_id,
        'order-id'              => $order_id,
        'card-amount'           => $order_amount,
        'currency'              => strtoupper($currencyCode),

        'paymethod'             => 'onlinecheck',
        'checktype'             => 'WEB',
        'accttype'              => $this->get_posted_field('pnp_accttype'),
        'routingnum'            => $this->get_digits_only('pnp_routingnum'),
        'accountnum'            => $this->get_digits_only('pnp_accountnum'),
        'acctclass'             => $acctclass,

        'card-name'             => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        'card-company'          => $order->get_billing_company(),
        'card-address1'         => $order->get_billing_address_1(),
        'card-address2'         => $order->get_billing_address_2(),
        'card-city'             => $order->get_billing_city(),
        'card-state'            => $order->get_billing_state(),
        'card-zip'              => $order->get_billing_postcode(),
        'card-country'          => $order->get_billing_country(),
        'phone'                 => $order->get_billing_phone(),
        'email'                 => $order->get_billing_email(),

        'shipinfo'              => '0',
        'shipname'              => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
        'company'               => $order->get_shipping_company(),
        'address1'              => $order->get_shipping_address_1(),
        'address2'              => $order->get_shipping_address_2(),
        'city'                  => $order->get_shipping_city(),

        'state'                 => $order->get_shipping_state(),
        'zip'                   => $order->get_shipping_postcode(),
        'country'               => $order->get_shipping_country(),
      );

      $checknum = preg_replace('/[^0-9]+/', '', $this->get_posted_field('pnp_checknum'));
      if ($checknum !== '') {
        $plugnpayapi_args['checknum'] = $checknum;
      }

      $plugnpayapi_args['ipaddress'] = plugnpay_ach_getUserIP();

      if ($acctclass === 'business') {
        $plugnpayapi_args['commcardtype'] = 'business';
      }

      if ($this->settings['post_auth'] == 'yes') {
        $plugnpayapi_args['authtype'] = 'authpostauth';
      }
      else {
        $plugnpayapi_args['authtype'] = 'authonly';
      }

      if ($this->is_authhash_configured()) {
        $timestamp = gmdate('YmdHis', time());
        $authhash_key = $this->resolve_authhash_key($currencyCode);
        $string_fields = plugnpay_pci_authhash_string_fields(
          $this->settings['authhash_fields'],
          $order_id,
          $order_amount,
          $gatewayAccount
        );
        $plugnpayapi_args['authhash'] = plugnpay_pci_authhash($authhash_key . $timestamp . $string_fields);
        $plugnpayapi_args['transacttime'] = $timestamp;
      }

      return $plugnpayapi_args;
    }
  }

  function woocommerce_add_plugnpay_api_ach_gateway($methods) {
    $methods[] = 'WC_Plugnpay_API_ACH_Gateway';
    return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'woocommerce_add_plugnpay_api_ach_gateway');
}

function woocommerce_plugnpay_api_ach_php_notice() {
  echo '<div class="error"><p>' . esc_html__('PlugnPay API ACH requires PHP 8.2 or higher.', 'woocommerce_plugnpay_api_ach') . '</p></div>';
}

function woocommerce_plugnpay_api_ach_version_notice() {
  echo '<div class="error"><p>' . esc_html__('PlugnPay API ACH requires WooCommerce 8.0 or higher.', 'woocommerce_plugnpay_api_ach') . '</p></div>';
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'plugnpay_ach_action_links');

function plugnpay_ach_action_links ($links) {
  $gateway_links = array(
    '<a href="https://www.gatewaystatus.com/" target="_blank" rel="noopener noreferrer">Gateway Status</a>',
    '<a href="https://helpdesk.plugnpay.com/" target="_blank" rel="noopener noreferrer">Online Helpdesk</a>',
    '<a href="https://pay1.plugnpay.com/admin/" target="_blank" rel="noopener noreferrer">Merchant Admin</a>'
  );
  return array_merge($links, $gateway_links);
}

function plugnpay_ach_getUserIP() {
  if (class_exists('WC_Geolocation')) {
    return WC_Geolocation::get_ip_address();
  }

  if (!empty($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
    return $_SERVER['REMOTE_ADDR'];
  }

  return '';
}

?>

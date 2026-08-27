<?php
/*
 * Plugin Name: PlugnPay API Credit Card Payment Gateway For WooCommerce
 * Plugin URI: https://github.com/PlugnPay/shopping-cart-WooCommerce
 * Description: Extends WooCommerce to Process API Credit Card Payments with PlugnPay gateway.
 * Version: 1.2.1
 * Author: PlugnPay
 * Author URI: https://www.plugnpay.com
 * Text Domain: woocommerce_plugnpay_api_cc
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
*/

defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/pci.php';
require_once __DIR__ . '/includes/admin-ui.php';

add_action('before_woocommerce_init', 'woocommerce_plugnpay_api_cc_declare_hpos_compatibility');

function woocommerce_plugnpay_api_cc_declare_hpos_compatibility() {
  if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
  }
}

add_action('plugins_loaded', 'woocommerce_plugnpay_api_cc_init', 0);

function woocommerce_plugnpay_api_cc_init() {
  if (!class_exists('WC_Payment_Gateway')) {
    return;
  }

  if (version_compare(PHP_VERSION, plugnpay_api_cc_minimum_php_version(), '<')) {
    add_action('admin_notices', 'woocommerce_plugnpay_api_cc_php_notice');
    return;
  }

  if (!function_exists('WC') || version_compare(WC()->version, '8.0', '<')) {
    add_action('admin_notices', 'woocommerce_plugnpay_api_cc_version_notice');
    return;
  }

  load_plugin_textdomain('woocommerce_plugnpay_api_cc', false, dirname(plugin_basename(__FILE__)) . '/languages');

  /**
  * PlugnPay API Credit Card Payment Gateway class
  **/
  class WC_Plugnpay_API_CC_Gateway extends WC_Payment_Gateway {
    protected $msg = array();

    public function __construct() {
      $this->id                 = 'plugnpay_api_cc';
      $this->method_title       = __('PlugnPay API CC', 'woocommerce_plugnpay_api_cc');
      $this->method_description = __('Accept Credit Card payments via API payment method, directly in WooCommerce.', 'woocommerce_plugnpay_api_cc');
      $this->icon               = '';
      $this->has_fields         = true;
      $this->supports           = array('products');
      $this->init_form_fields();
      $this->init_settings();
      $this->title              = $this->settings['title'];
      $this->description        = $this->settings['description'];
      $this->msg['message']     = '';
      $this->msg['class']       = '';

      $wc_version = defined('WC_VERSION') ? WC_VERSION : (defined('WOOCOMMERCE_VERSION') ? WOOCOMMERCE_VERSION : '0');
      if (version_compare($wc_version, '2.0.0', '>=')) {
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
      }
      else {
        add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
      }

      add_action('woocommerce_receipt_plugnpay_api_cc', array($this, 'receipt_page'));
      add_action('woocommerce_thankyou_plugnpay_api_cc', array($this, 'thankyou_page'));
      add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_assets'));
      add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
      add_action('admin_notices', array($this, 'admin_security_notices'));
    }

    /**
    * Checkout assets for payment fields.
    **/
    public function enqueue_checkout_assets() {
      if (!is_checkout() && !is_wc_endpoint_url('order-pay')) {
        return;
      }

      wp_enqueue_style(
        'plugnpay-api-cc-checkout',
        plugins_url('assets/css/checkout.css', __FILE__),
        array(),
        '1.2.1'
      );

      wp_enqueue_script(
        'plugnpay-api-cc-checkout',
        plugins_url('assets/js/checkout.js', __FILE__),
        array('jquery'),
        '1.2.1',
        true
      );

      wp_localize_script(
        'plugnpay-api-cc-checkout',
        'plugnpayApiCcCheckout',
        array(
          'i18n' => array(
            'cardNumberInvalid'            => esc_html__('(Credit Card Number) is not valid.', 'woocommerce_plugnpay_api_cc'),
            'securityCodeInvalid'            => esc_html__('(Card Verification Number) is not valid.', 'woocommerce_plugnpay_api_cc'),
            'giftCardNumberInvalid'          => esc_html__('(Gift Card Number) is not valid.', 'woocommerce_plugnpay_api_cc'),
            'giftCardSecurityCodeInvalid'    => esc_html__('(Gift Card Verification Number) is not valid.', 'woocommerce_plugnpay_api_cc'),
          ),
        )
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
        'plugnpay-api-cc-admin-settings',
        plugins_url('assets/css/admin-settings.css', __FILE__),
        array(),
        '1.2.1'
      );
      wp_enqueue_script(
        'plugnpay-api-cc-admin-settings',
        plugins_url('assets/js/admin-settings.js', __FILE__),
        array('jquery'),
        '1.2.1',
        true
      );
      wp_localize_script(
        'plugnpay-api-cc-admin-settings',
        'plugnpayApiCcAdmin',
        array(
          'gatewayId'       => $this->id,
          'dependentFields' => plugnpay_api_cc_admin_dependent_fields(),
        )
      );
    }

    function init_form_fields() {
      $this->form_fields = array(
        'section_checkout' => array(
          'title'       => __('Checkout display', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'title',
          'class'       => 'plugnpay-api-cc-settings-section plugnpay-api-cc-settings-section-first',
          'description' => __('How this payment method appears on the checkout page.', 'woocommerce_plugnpay_api_cc'),
        ),
        'enabled' => array(
          'title'   => __('Enable/Disable', 'woocommerce_plugnpay_api_cc'),
          'type'    => 'checkbox',
          'label'   => __('Enable PlugnPay API CC Payment Module.', 'woocommerce_plugnpay_api_cc'),
          'default' => 'no',
        ),
        'title' => array(
          'title'       => __('Title:', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'text',
          'description' => __('This controls the title which the user sees during checkout.', 'woocommerce_plugnpay_api_cc'),
          'default'     => __('Credit Card', 'woocommerce_plugnpay_api_cc'),
          'desc_tip'    => true,
        ),
        'description' => array(
          'title'       => __('Description:', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'textarea',
          'description' => __('This controls the description which the user sees during checkout.', 'woocommerce_plugnpay_api_cc'),
          'default'     => __('Pay securely by Credit or Debit Card through PlugnPay Secure Servers.', 'woocommerce_plugnpay_api_cc'),
          'desc_tip'    => true,
        ),
        'section_gateway' => array(
          'title'       => __('Gateway account', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'title',
          'class'       => 'plugnpay-api-cc-settings-section',
          'description' => __('PlugnPay account used for this store, Remote Client Password, and how approved payments are captured.', 'woocommerce_plugnpay_api_cc'),
        ),
        'gateway_account' => array(
          'title'       => __('Gateway Account', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'text',
          'description' => __('Username issued by PlugnPay at time of sign up.', 'woocommerce_plugnpay_api_cc'),
        ),
        'remote_password' => array(
          'title'       => __('Remote Client Password', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'password',
          'description' => __('Required. Leave blank to keep the current password. Created in PlugnPay Security Administration.', 'woocommerce_plugnpay_api_cc'),
          'custom_attributes' => array(
            'autocomplete' => 'new-password',
          ),
        ),
        'cards_allowed' => array(
          'title'       => __('Card Types Allowed', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'text',
          'description' => __('Card types you are allowed to accept. Refer to the payment method specifications for possible values.', 'woocommerce_plugnpay_api_cc'),
          'default'     => __('Visa,Mastercard,Amex,Discover', 'woocommerce_plugnpay_api_cc'),
          'desc_tip'    => true,
        ),
        'post_auth' => array(
          'title'       => __('Transaction Settlement', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'select',
          'options'     => array(
            'yes' => __('Authorize and Settle', 'woocommerce_plugnpay_api_cc'),
            'no'  => __('Authorize Only', 'woocommerce_plugnpay_api_cc'),
          ),
          'description' => __('If you are not sure what to use, set to Authorize and Settle.', 'woocommerce_plugnpay_api_cc'),
          'desc_tip'    => true,
        ),
        'section_messages' => array(
          'title'       => __('Customer messages', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'title',
          'class'       => 'plugnpay-api-cc-settings-section',
          'description' => __('Shown to the shopper after payment succeeds or fails.', 'woocommerce_plugnpay_api_cc'),
        ),
        'success_message' => array(
          'title'       => __('Transaction Success Message', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'textarea',
          'description' => __('Message to be displayed on successful transaction.', 'woocommerce_plugnpay_api_cc'),
          'default'     => __('Your payment has been processed successfully.', 'woocommerce_plugnpay_api_cc'),
          'desc_tip'    => true,
        ),
        'failed_message' => array(
          'title'       => __('Transaction Failed Message', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'textarea',
          'description' => __('Message to be displayed on failed transaction.', 'woocommerce_plugnpay_api_cc'),
          'default'     => __('Your transaction has been declined.', 'woocommerce_plugnpay_api_cc'),
          'desc_tip'    => true,
        ),
        'section_authhash' => array(
          'title'       => __('Authorization Verification Hash (SHA-256)', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'title',
          'class'       => 'plugnpay-api-cc-settings-section',
          'description' => __('Required. Enable this in both the module and PlugnPay Merchant Admin → Security Administration. The key and fieldset must match.', 'woocommerce_plugnpay_api_cc'),
        ),
        'authhash' => array(
          'title'       => __('Authorization Hash', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'checkbox',
          'label'       => __('Enable Authorization Verification Hash (SHA-256). Required.', 'woocommerce_plugnpay_api_cc'),
          'default'     => 'yes',
        ),
        'authhash_key' => array(
          'title'       => __('Authorization Hash Key', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'password',
          'description' => __('Required. Leave blank to keep the current key. If using Divert Currency, list each currency with its associated key [i.e. USD:key1,BBD:key2,CAD:key3].', 'woocommerce_plugnpay_api_cc'),
          'default'     => '',
          'custom_attributes' => array(
            'autocomplete' => 'new-password',
          ),
        ),
        'authhash_fields' => array(
          'title'       => __('Authorization Hash Fields', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'select',
          'options'     => array(
            '1' => 'publisher-name',
            '2' => 'card-amount,publisher-name',
            '3' => 'acct_code,card-amount,publisher-name',
          ),
          'description' => __('Fieldset to use with authhash validation. Must configure your PlugnPay account to match.', 'woocommerce_plugnpay_api_cc'),
          'default'     => '3',
          'desc_tip'    => true,
        ),
        'section_optional' => array(
          'title'       => __('Optional features', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'title',
          'class'       => 'plugnpay-api-cc-settings-section',
          'description' => __('Giftcard split payments and currency diversion require matching PlugnPay account features.', 'woocommerce_plugnpay_api_cc'),
        ),
        'giftcard_allow' => array(
          'title'   => __('Giftcard Acceptance', 'woocommerce_plugnpay_api_cc'),
          'type'    => 'checkbox',
          'label'   => __('Enable to allow Giftcard Split Payments. Merchant Processor Giftcard ability required.', 'woocommerce_plugnpay_api_cc'),
          'default' => 'no',
        ),
        'giftcard_descr' => array(
          'title'       => __('Giftcard Description:', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'textarea',
          'description' => __('This controls the giftcard description which the user sees during checkout.', 'woocommerce_plugnpay_api_cc'),
          'default'     => __('[optional] Enter your gift card details below.', 'woocommerce_plugnpay_api_cc'),
          'desc_tip'    => true,
        ),
        'giftcard_note' => array(
          'title'       => __('Giftcard Note:', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'textarea',
          'description' => __('This controls the usage note under the giftcard fields, which the user sees during checkout.', 'woocommerce_plugnpay_api_cc'),
          'default'     => __('If gift card has an insufficient balance, the remainder will be automatically applied to credit card supplied.', 'woocommerce_plugnpay_api_cc'),
          'desc_tip'    => true,
        ),
        'divert_currency' => array(
          'title'   => __('Divert Currency', 'woocommerce_plugnpay_api_cc'),
          'type'    => 'checkbox',
          'label'   => __('Enable to divert payments for specific currencies to another gateway account.', 'woocommerce_plugnpay_api_cc'),
          'default' => 'no',
        ),
        'divert_accounts' => array(
          'title'       => __('Diverted Accounts', 'woocommerce_plugnpay_api_cc'),
          'type'        => 'text',
          'description' => __('List currency code, username & Remote Client Password to divert specific payments to. [i.e. USD:username1:abcd1234,BBD:username2:efgh2345,CAD:username3:ijkl3456] Currency codes not listed will use default Gateway Account.', 'woocommerce_plugnpay_api_cc'),
        ),
      );
    }

    /**
    * Preserve remote password when the masked field is left blank on save.
    **/
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
      return plugnpay_pci_generate_password_html($this, $key, $data, 'woocommerce_plugnpay_api_cc');
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
        echo '<div class="error"><p>' . esc_html__('PlugnPay API CC: Remote Client Password is required.', 'woocommerce_plugnpay_api_cc') . '</p></div>';
      }

      if (!$this->is_authhash_configured()) {
        echo '<div class="error"><p>' . esc_html__('PlugnPay API CC: Authorization Verification Hash and key are required before checkout can proceed.', 'woocommerce_plugnpay_api_cc') . '</p></div>';
      }

      if (!plugnpay_pci_storefront_is_secure()) {
        echo '<div class="error"><p>' . esc_html__('PlugnPay API CC: HTTPS is required on the storefront because card data is collected onsite.', 'woocommerce_plugnpay_api_cc') . '</p></div>';
      }

      if (version_compare(PHP_VERSION, '8.3', '<')) {
        echo '<div class="notice notice-warning"><p>' . esc_html__('PlugnPay API CC: PHP 8.3 or higher is recommended. PHP 8.2 security support ends 31 Dec 2026.', 'woocommerce_plugnpay_api_cc') . '</p></div>';
      }
    }

    /**
    * Card type logos for checkout, based on the cards_allowed setting.
    **/
    public function get_icon() {
      $icons = '';
      $cards_allowed = isset($this->settings['cards_allowed']) ? $this->settings['cards_allowed'] : '';

      if (!empty($cards_allowed)) {
        $icon_path = plugins_url('images/', __FILE__);
        $cards_list = explode(',', $cards_allowed);

        foreach ($cards_list as $card) {
          $card_slug = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', trim($card)));
          if ($card_slug === '') {
            continue;
          }

          $img_url = $icon_path . $card_slug . '.png';
          $icons .= '<img src="' . esc_url($img_url) . '" alt="' . esc_attr(ucwords($card_slug)) . '" />';
        }

        if ($icons !== '') {
          $icons = '<span class="plugnpay-cc-card-icons">' . $icons . '</span>';
        }
      }

      return apply_filters('woocommerce_gateway_icon', $icons, $this->id);
    }

    /**
    * Admin Panel Options
    **/
    public function admin_options() {
      echo '<h3>'.esc_html__('PlugnPay API CC Payment Gateway', 'woocommerce_plugnpay_api_cc').'</h3>';
      echo '<p>'.esc_html__('PlugnPay is a popular payment gateway for online payment processing', 'woocommerce_plugnpay_api_cc').'</p>';
      echo '<table class="form-table">';
      $this->generate_settings_html();
      echo '</table>';
    }

    /**
    * Fields for PlugnPay API CC
    **/
    function payment_fields() {
      if ($this->description) {
        echo '<div class="plugnpay-cc-description">' . wp_kses_post(wpautop(wptexturize($this->description))) . '</div>';
      }

      echo '<fieldset class="plugnpay-cc-fields wc-payment-form">';
      echo '<legend class="screen-reader-text">' . esc_html__('Credit card details', 'woocommerce_plugnpay_api_cc') . '</legend>';

      $this->render_checkout_field(
        'pnp_cardnumber',
        __('Card number', 'woocommerce_plugnpay_api_cc'),
        array(
          'class'       => 'form-row-wide',
          'type'        => 'tel',
          'required'    => true,
          'maxlength'   => '20',
          'minlength'   => '13',
          'pattern'     => '[0-9]{13,20}',
          'inputmode'   => 'numeric',
          'autocomplete'=> 'cc-number',
          'placeholder' => '•••• •••• •••• ••••',
        )
      );

      $this->render_expiry_fields();

      $this->render_checkout_field(
        'pnp_cardcvv',
        __('Security code', 'woocommerce_plugnpay_api_cc'),
        array(
          'class'       => 'form-row-last',
          'type'        => 'tel',
          'required'    => true,
          'maxlength'   => '4',
          'minlength'   => '3',
          'pattern'     => '[0-9]{3,4}',
          'inputmode'   => 'numeric',
          'autocomplete'=> 'cc-csc',
          'placeholder' => 'CVV',
        )
      );

      if ($this->settings['giftcard_allow'] == 'yes') {
        echo '<div class="plugnpay-cc-giftcard">';

        if (!empty($this->settings['giftcard_descr'])) {
          echo '<div class="plugnpay-cc-giftcard-heading">' . wp_kses_post(wpautop(wptexturize($this->settings['giftcard_descr']))) . '</div>';
        }

        $this->render_giftcard_fields();

        if (!empty($this->settings['giftcard_note'])) {
          echo '<div class="plugnpay-cc-giftcard-note">' . wp_kses_post(wpautop(wptexturize($this->settings['giftcard_note']))) . '</div>';
        }

        echo '</div>';
      }

      echo '</fieldset>';
    }

    /**
    * Render a single checkout field using WooCommerce form-row markup.
    **/
    private function render_checkout_field($name, $label, $args = array()) {
      $defaults = array(
        'class'        => 'form-row-wide',
        'type'         => 'text',
        'required'     => false,
        'maxlength'    => '',
        'minlength'    => '',
        'pattern'      => '',
        'inputmode'    => '',
        'autocomplete' => 'off',
        'placeholder'  => '',
      );
      $args = wp_parse_args($args, $defaults);

      $required_attr = $args['required'] ? ' required' : '';
      $required_mark = $args['required'] ? ' <abbr class="required" title="' . esc_attr__('required', 'woocommerce_plugnpay_api_cc') . '">*</abbr>' : '';

      $attributes = array(
        'type'         => $args['type'],
        'class'        => 'input-text',
        'name'         => $name,
        'id'           => $name,
        'autocomplete' => $args['autocomplete'],
      );

      if ($args['maxlength'] !== '') {
        $attributes['maxlength'] = $args['maxlength'];
      }
      if ($args['minlength'] !== '') {
        $attributes['minlength'] = $args['minlength'];
      }
      if ($args['pattern'] !== '') {
        $attributes['pattern'] = $args['pattern'];
      }
      if ($args['inputmode'] !== '') {
        $attributes['inputmode'] = $args['inputmode'];
      }
      if ($args['placeholder'] !== '') {
        $attributes['placeholder'] = $args['placeholder'];
      }

      $attribute_string = '';
      foreach ($attributes as $key => $value) {
        $attribute_string .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
      }

      printf(
        '<p class="form-row %1$s"><label for="%2$s">%3$s%4$s</label><input%5$s%6$s /></p>',
        esc_attr($args['class']),
        esc_attr($name),
        esc_html($label),
        $required_mark,
        $attribute_string,
        $required_attr
      );
    }

    /**
    * Render month/year expiry dropdowns.
    **/
    private function render_expiry_fields() {
      $required_mark = ' <abbr class="required" title="' . esc_attr__('required', 'woocommerce_plugnpay_api_cc') . '">*</abbr>';

      echo '<p class="form-row form-row-first plugnpay-cc-expiry">';
      echo '<span class="plugnpay-cc-expiry-fields">';

      echo '<span class="plugnpay-cc-expiry-field">';
      echo '<label for="pnp_cardexp_month">' . esc_html__('Month', 'woocommerce_plugnpay_api_cc') . $required_mark . '</label>';
      echo '<select name="pnp_cardexp_month" id="pnp_cardexp_month" class="input-text" autocomplete="cc-exp-month" required>';
      echo '<option value="">' . esc_html__('Month', 'woocommerce_plugnpay_api_cc') . '</option>';
      foreach ($this->get_expiry_month_options() as $value => $label) {
        printf(
          '<option value="%1$s">%2$s</option>',
          esc_attr($value),
          esc_html($label)
        );
      }
      echo '</select>';
      echo '</span>';

      echo '<span class="plugnpay-cc-expiry-field">';
      echo '<label for="pnp_cardexp_year">' . esc_html__('Year', 'woocommerce_plugnpay_api_cc') . $required_mark . '</label>';
      echo '<select name="pnp_cardexp_year" id="pnp_cardexp_year" class="input-text" autocomplete="cc-exp-year" required>';
      echo '<option value="">' . esc_html__('Year', 'woocommerce_plugnpay_api_cc') . '</option>';
      foreach ($this->get_expiry_year_options() as $value => $label) {
        printf(
          '<option value="%1$s">%2$s</option>',
          esc_attr($value),
          esc_html($label)
        );
      }
      echo '</select>';
      echo '</span>';

      echo '</span></p>';
    }

    /**
    * Render gift card number and security code on one row.
    **/
    private function render_giftcard_fields() {
      echo '<p class="form-row form-row-wide plugnpay-cc-giftcard-row">';
      echo '<span class="plugnpay-cc-giftcard-fields">';

      echo '<span class="plugnpay-cc-giftcard-field plugnpay-cc-giftcard-field-number">';
      echo '<label for="pnp_mpgiftcard">' . esc_html__('Gift card number', 'woocommerce_plugnpay_api_cc') . '</label>';
      echo '<input type="tel" class="input-text" name="pnp_mpgiftcard" id="pnp_mpgiftcard" maxlength="20" minlength="1" pattern="[0-9]{1,20}" inputmode="numeric" autocomplete="off" />';
      echo '</span>';

      echo '<span class="plugnpay-cc-giftcard-field plugnpay-cc-giftcard-field-cvv">';
      echo '<label for="pnp_mpcvv">' . esc_html__('Gift card security code', 'woocommerce_plugnpay_api_cc') . '</label>';
      echo '<input type="tel" class="input-text" name="pnp_mpcvv" id="pnp_mpcvv" maxlength="4" minlength="3" pattern="[0-9]{3,4}" inputmode="numeric" autocomplete="off" placeholder="CVV" />';
      echo '</span>';

      echo '</span></p>';
    }

    /**
    * Month options for the expiry dropdown.
    **/
    private function get_expiry_month_options() {
      $options = array();

      for ($month = 1; $month <= 12; $month++) {
        $month_name = date_i18n('F', mktime(0, 0, 0, $month, 1));
        $options[sprintf('%02d', $month)] = sprintf('%s (%d)', $month_name, $month);
      }

      return $options;
    }

    /**
    * Year options for the expiry dropdown.
    **/
    private function get_expiry_year_options() {
      $options = array();
      $current_year = (int) gmdate('Y');
      $max_years_ahead = 15;

      for ($year = $current_year; $year <= ($current_year + $max_years_ahead); $year++) {
        $options[(string) $year] = (string) $year;
      }

      return $options;
    }

    /**
    * Build card expiry in MM/YY format for the PlugnPay API.
    **/
    private function get_posted_card_exp() {
      $month = $this->get_posted_field('pnp_cardexp_month');
      $year = $this->get_posted_field('pnp_cardexp_year');

      if ($month === '' || $year === '') {
        return '';
      }

      return sprintf('%02d/%02d', (int) $month, ((int) $year) % 100);
    }

    /**
    * Basic Card validation
    **/
    public function validate_fields() {
      $valid = true;
      $cardnumber = $this->get_posted_field('pnp_cardnumber');
      $cardexp_month = $this->get_posted_field('pnp_cardexp_month');
      $cardexp_year = $this->get_posted_field('pnp_cardexp_year');
      $cardcvv = $this->get_posted_field('pnp_cardcvv');

      if (!$this->isCreditCardNumber($cardnumber)) {
        wc_add_notice(__('(Credit Card Number) is not valid.', 'woocommerce_plugnpay_api_cc'), 'error');
        $valid = false;
      }
      if ($cardexp_month === '' || $cardexp_year === '') {
        wc_add_notice(__('(Card Expiry Date) is required.', 'woocommerce_plugnpay_api_cc'), 'error');
        $valid = false;
      }
      elseif (!$this->isCorrectExpireDateParts($cardexp_month, $cardexp_year)) {
        wc_add_notice(__('(Card Expiry Date) is not valid.', 'woocommerce_plugnpay_api_cc'), 'error');
        $valid = false;
      }
      if (!$this->isCCVNumber($cardcvv)) {
        wc_add_notice(__('(Card Verification Number) is not valid.', 'woocommerce_plugnpay_api_cc'), 'error');
        $valid = false;
      }

      if ($this->settings['giftcard_allow'] == 'yes') {
        $mpgiftcard = $this->get_posted_field('pnp_mpgiftcard');
        $mpcvv = $this->get_posted_field('pnp_mpcvv');

        if (!empty($mpgiftcard) || !empty($mpcvv)) {
          if (!empty($mpgiftcard) && !$this->isNumericField($mpgiftcard, 1, 20)) {
            wc_add_notice(__('(Gift Card Number) is not valid.', 'woocommerce_plugnpay_api_cc'), 'error');
            $valid = false;
          }
          if (!empty($mpcvv) && !$this->isCCVNumber($mpcvv)) {
            wc_add_notice(__('(Gift Card Verification Number) is not valid.', 'woocommerce_plugnpay_api_cc'), 'error');
            $valid = false;
          }
        }
      }

      return $valid;
    }

    private function get_posted_field($key) {
      if (!isset($_POST[$key])) {
        return '';
      }
      return sanitize_text_field(wp_unslash($_POST[$key]));
    }

    private function get_order_amount($order) {
      return wc_format_decimal($order->get_total(), 2);
    }

    /**
    * Check credit card
    **/
    private function isCreditCardNumber($toCheck) {
      if (!ctype_digit($toCheck)) {
        return false;
      }

      $number = $toCheck;
      $strlen = strlen($number);
      $sum    = 0;

      if ($strlen < 13) {
        return false;
      }

      for ($i=0; $i < $strlen; $i++) {
        $digit = substr($number, $strlen - $i - 1, 1);
        if ($i % 2 == 1) {
          $sub_total = $digit * 2;
          if ($sub_total > 9) {
            $sub_total = 1 + ($sub_total - 10);
          }
        }
        else {
          $sub_total = $digit;
        }
        $sum += $sub_total;
      }

      return ($sum > 0 && $sum % 10 == 0);
    }

    private function isCCVNumber($toCheck) {
      return (bool) preg_match('/^\d{3,4}$/', $toCheck);
    }

    private function isNumericField($toCheck, $min_length, $max_length) {
      if (!ctype_digit($toCheck)) {
        return false;
      }

      $length = strlen($toCheck);
      return ($length >= $min_length && $length <= $max_length);
    }

    /**
    * Check expiry date from month/year parts.
    **/
    private function isCorrectExpireDateParts($month, $year) {
      $month = (int) $month;
      $year = (int) $year;

      if ($month < 1 || $month > 12 || $year < 0) {
        return false;
      }

      if ($year < 100) {
        $year = 2000 + $year;
      }

      $current_year = (int) gmdate('Y');
      $max_years_ahead = 15;

      if ($year < $current_year || $year > ($current_year + $max_years_ahead)) {
        return false;
      }

      $expiry_end = mktime(23, 59, 59, $month + 1, 0, $year);
      return $expiry_end >= time();
    }

    public function thankyou_page($order_id) {
      /* nothing to do here... */
    }

    /**
    * Receipt Page
    **/
    function receipt_page($order) {
      echo '<p>'.__('Thank you for your order.', 'woocommerce_plugnpay_api_cc').'</p>';
    }

    /**
    * Process the payment and return the result
    **/
    function process_payment($order_id) {
      $order = wc_get_order($order_id);

      if (!$order) {
        wc_add_notice(__('(Transaction Error) Invalid order.', 'woocommerce_plugnpay_api_cc'), 'error');
        return array('result' => 'failure');
      }

      if (empty($this->settings['gateway_account']) || $this->get_plain_secret('remote_password') === '') {
        wc_add_notice(__('(Transaction Error) Payment gateway is not configured.', 'woocommerce_plugnpay_api_cc'), 'error');
        return array('result' => 'failure');
      }

      if (!$this->is_authhash_configured()) {
        wc_add_notice(__('(Transaction Error) Payment gateway is not configured for Authorization Hash.', 'woocommerce_plugnpay_api_cc'), 'error');
        return array('result' => 'failure');
      }

      if (!plugnpay_pci_storefront_is_secure()) {
        wc_add_notice(__('(Transaction Error) Secure HTTPS checkout is required.', 'woocommerce_plugnpay_api_cc'), 'error');
        return array('result' => 'failure');
      }

      $params = $this->generate_plugnpay_api_cc_params($order);

      $post_string = '';
      foreach ($params as $key => $value) {
        $post_string .= "$key=" . urlencode($value) . '&';
      }
      $post_string = rtrim($post_string, '&');

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
        $order->add_order_note($this->settings['failed_message'] . ' cURL error: ' . $curl_error);
        $order->update_status('failed');
        wc_add_notice(__('(Transaction Error) Error connecting to payment gateway.', 'woocommerce_plugnpay_api_cc'), 'error');
        return array('result' => 'failure');
      }

      parse_str($post_response, $response);

      if (!empty($response['FinalStatus'])) {
        if (($response['FinalStatus'] == 'success') || ($response['FinalStatus'] == 'pending')) {
          if ($order->get_status() != 'completed') {
            $transaction_id = isset($response['orderID']) ? $response['orderID'] : '';
            $order->payment_complete($transaction_id);
            WC()->cart->empty_cart();
            $merr_msg = isset($response['MErrMsg']) ? $response['MErrMsg'] : '';
            $order->add_order_note($this->settings['success_message'] . $merr_msg . ' Transaction ID: ' . $transaction_id);
          }
          return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order)
          );
        }

        $merr_msg = isset($response['MErrMsg']) ? $response['MErrMsg'] : '';
        $order->add_order_note($this->settings['failed_message'] . $merr_msg);
        $order->update_status('failed');
        wc_add_notice(sprintf(__('(Transaction Error) %s', 'woocommerce_plugnpay_api_cc'), $merr_msg), 'error');
        return array('result' => 'failure');
      }

      $order->add_order_note($this->settings['failed_message']);
      $order->update_status('failed');
      wc_add_notice(__('(Transaction Error) Error processing payment.', 'woocommerce_plugnpay_api_cc'), 'error');
      return array('result' => 'failure');
    }

    /**
    * Generate PlugnPay API CC button link
    **/
    public function generate_plugnpay_api_cc_params($order) {
      $gatewayAccount = $this->settings['gateway_account'];
      $currencyCode = $order->get_currency();
      $remotePassword = $this->get_plain_secret('remote_password');
      $order_amount = $this->get_order_amount($order);
      $order_id = $order->get_id();

      if ($this->settings['divert_currency'] == 'yes') {
        $divert_list = explode(',', $this->settings['divert_accounts']);
        foreach ($divert_list as $i) {
          list($altCurrency,$altMerchant,$altPassword) = explode(':', $i, 3);
          if (strtolower($altCurrency) == strtolower($order->get_currency())) {
            $currencyCode = $altCurrency;
            $gatewayAccount = $altMerchant;
            $remotePassword = $altPassword;
            break 1;
          }
        }
      }

      $plugnpayapi_args = array(
        'publisher-name'        => strtolower($gatewayAccount),
        'publisher-password'    => $remotePassword,
        'client'                => 'WooCommerce_API_CC',
        'mode'                  => 'auth',

        'acct_code'             => $order_id,
        'order-id'              => $order_id,
        'card-amount'           => $order_amount,
        'currency'              => strtoupper($currencyCode),

        'paymethod'             => 'credit',
        'card-number'           => $this->get_posted_field('pnp_cardnumber'),
        'card-exp'              => $this->get_posted_card_exp(),
        'card-cvv'              => $this->get_posted_field('pnp_cardcvv'),

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

      $plugnpayapi_args['ipaddress'] = plugnpay_cc_getUserIP();

      if ($this->settings['post_auth'] == 'yes') {
        $plugnpayapi_args['authtype'] = 'authpostauth';
      }
      else {
        $plugnpayapi_args['authtype'] = 'authonly';
      }

      $timestamp = gmdate("YmdHis", time());
      $authhash_key = plugnpay_pci_resolve_authhash_key(
        $this->get_plain_secret('authhash_key'),
        $currencyCode,
        $this->settings['divert_currency'] == 'yes'
      );
      $string_fields = plugnpay_pci_authhash_string_fields(
        $this->settings['authhash_fields'],
        $order_id,
        $order_amount,
        $gatewayAccount
      );
      $plugnpayapi_args['authhash'] = plugnpay_pci_authhash($authhash_key . $timestamp . $string_fields);
      $plugnpayapi_args['transacttime'] = $timestamp;

      if ($this->settings['giftcard_allow'] == 'yes') {
        $mpgiftcard = $this->get_posted_field('pnp_mpgiftcard');
        $mpcvv = $this->get_posted_field('pnp_mpcvv');
        if (!empty($mpgiftcard)) {
          $plugnpayapi_args['mpgiftcard'] = $mpgiftcard;
        }
        if (!empty($mpcvv)) {
          $plugnpayapi_args['mpcvv'] = $mpcvv;
        }
      }

      return $plugnpayapi_args;
    }
  }

  /**
  * Add this Gateway to WooCommerce
  **/
  function woocommerce_add_plugnpay_api_cc_gateway($methods) {
    $methods[] = 'WC_Plugnpay_API_CC_Gateway';
    return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'woocommerce_add_plugnpay_api_cc_gateway');
}

function woocommerce_plugnpay_api_cc_php_notice() {
  echo '<div class="error"><p>' . esc_html__('PlugnPay API CC requires PHP 8.2 or higher.', 'woocommerce_plugnpay_api_cc') . '</p></div>';
}

function woocommerce_plugnpay_api_cc_version_notice() {
  echo '<div class="error"><p>' . esc_html__('PlugnPay API CC requires WooCommerce 8.0 or higher.', 'woocommerce_plugnpay_api_cc') . '</p></div>';
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'plugnpay_cc_action_links');

function plugnpay_cc_action_links ($links) {
  $gateway_links = array(
    '<a href="https://www.gatewaystatus.com/" target="_blank" rel="noopener noreferrer">Gateway Status</a>',
    '<a href="https://helpdesk.plugnpay.com/" target="_blank" rel="noopener noreferrer">Online Helpdesk</a>',
    '<a href="https://pay1.plugnpay.com/admin/" target="_blank" rel="noopener noreferrer">Merchant Admin</a>'
  );
  return array_merge($links, $gateway_links);
}

function plugnpay_cc_getUserIP() {
  if (class_exists('WC_Geolocation')) {
    return WC_Geolocation::get_ip_address();
  }

  if (!empty($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
    return $_SERVER['REMOTE_ADDR'];
  }

  return '';
}

?>

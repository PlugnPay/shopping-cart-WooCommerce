<?php
/*
 * Plugin Name: PlugnPay API CC Tokenization Payment Gateway For WooCommerce
 * Plugin URI: https://github.com/PlugnPay/shopping-cart-WooCommerce
 * Description: Extends WooCommerce to process API credit card payments with PlugnPay card-on-file tokenization (authprev).
 * Version: 1.1.0
 * Author: PlugnPay
 * Author URI: https://www.plugnpay.com
 * Text Domain: woocommerce_plugnpay_api_cc_tokenization
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
*/

defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/pci.php';

add_action('before_woocommerce_init', 'woocommerce_plugnpay_api_cc_tokenization_declare_hpos_compatibility');

function woocommerce_plugnpay_api_cc_tokenization_declare_hpos_compatibility() {
  if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
  }
}

add_action('plugins_loaded', 'woocommerce_plugnpay_api_cc_tokenization_init', 0);

function woocommerce_plugnpay_api_cc_tokenization_init() {
  if (!class_exists('WC_Payment_Gateway')) {
    return;
  }

  if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', 'woocommerce_plugnpay_api_cc_tokenization_php_notice');
    return;
  }

  if (!function_exists('WC') || version_compare(WC()->version, '8.0', '<')) {
    add_action('admin_notices', 'woocommerce_plugnpay_api_cc_tokenization_version_notice');
    return;
  }

  load_plugin_textdomain('woocommerce_plugnpay_api_cc_tokenization', false, dirname(plugin_basename(__FILE__)) . '/languages');

  /**
  * PlugnPay API CC Tokenization Payment Gateway
  **/
  class WC_Plugnpay_API_CC_Tokenization_Gateway extends WC_Payment_Gateway {
    const TOKEN_META_ORIGORDERID      = 'pnp_origorderid';
    const TOKEN_META_PREVORDERID      = 'pnp_prevorderid';
    const TOKEN_META_PREVORDERID_TS   = 'pnp_prevorderid_ts';
    const TOKEN_META_GATEWAY_ACCOUNT  = 'pnp_gateway_account';
    const PREVORDERID_MAX_AGE_SECONDS = 63072000; // 24 months (730 days)

    protected $msg = array();

    public function __construct() {
      $this->id                 = 'plugnpay_api_cc_tokenization';
      $this->method_title       = __('PlugnPay API CC Tokenization', 'woocommerce_plugnpay_api_cc_tokenization');
      $this->method_description = __('Accept credit card payments via PlugnPay API with card-on-file tokenization (authprev). Tokens are bound to the PlugnPay account that created them.', 'woocommerce_plugnpay_api_cc_tokenization');
      $this->icon               = '';
      $this->has_fields         = true;
      $this->supports           = array(
        'products',
        'tokenization',
        'add_payment_method',
      );
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

      add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
      add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
      add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_assets'));
      add_action('admin_notices', array($this, 'admin_security_notices'));
    }

    public function enqueue_checkout_assets() {
      $is_add_payment_method = function_exists('is_add_payment_method_page') && is_add_payment_method_page();

      if (!is_checkout() && !is_wc_endpoint_url('order-pay') && !$is_add_payment_method) {
        return;
      }

      wp_enqueue_style(
        'plugnpay-api-cc-tokenization-checkout',
        plugins_url('assets/css/checkout.css', __FILE__),
        array(),
        '1.1.0'
      );

      wp_enqueue_script(
        'plugnpay-api-cc-tokenization-checkout',
        plugins_url('assets/js/checkout.js', __FILE__),
        array('jquery'),
        '1.1.0',
        true
      );

      wp_localize_script(
        'plugnpay-api-cc-tokenization-checkout',
        'plugnpayApiCcTokenizationCheckout',
        array(
          'gatewayId'       => $this->id,
          'requireTokenCvv' => $this->is_token_cvv_required(),
          'i18n'            => array(
            'cardNumberInvalid'           => esc_html__('(Credit Card Number) is not valid.', 'woocommerce_plugnpay_api_cc_tokenization'),
            'securityCodeInvalid'         => esc_html__('(Card Verification Number) is not valid.', 'woocommerce_plugnpay_api_cc_tokenization'),
            'giftCardNumberInvalid'       => esc_html__('(Gift Card Number) is not valid.', 'woocommerce_plugnpay_api_cc_tokenization'),
            'giftCardSecurityCodeInvalid' => esc_html__('(Gift Card Verification Number) is not valid.', 'woocommerce_plugnpay_api_cc_tokenization'),
          ),
        )
      );
    }

    function init_form_fields() {
      $this->form_fields = array(
          'enabled'         => array(
              'title'          => __('Enable/Disable', 'woocommerce_plugnpay_api_cc_tokenization'),
              'type'           => 'checkbox',
              'label'          => __('Enable PlugnPay API CC Tokenization Payment Module.', 'woocommerce_plugnpay_api_cc_tokenization'),
              'default'        => 'no'),
          'title'           => array(
              'title'          => __('Title:', 'woocommerce_plugnpay_api_cc_tokenization'),
              'type'           => 'text',
              'description'    => __('This controls the title which the user sees during checkout.', 'woocommerce_plugnpay_api_cc_tokenization'),
              'default'        => __('Credit Card', 'woocommerce_plugnpay_api_cc_tokenization')),
          'description'     => array(
              'title'          => __('Description:', 'woocommerce_plugnpay_api_cc_tokenization'),
              'type'           => 'textarea',
              'description'    => __('This controls the description which the user sees during checkout.', 'woocommerce_plugnpay_api_cc_tokenization'),
              'default'        => __('Pay securely by Credit or Debit Card through PlugnPay Secure Servers. Logged-in customers can save cards for later use.', 'woocommerce_plugnpay_api_cc_tokenization')),
          'gateway_account' => array(
              'title'          => __('Gateway Account', 'woocommerce_plugnpay_api_cc_tokenization'),
              'type'           => 'text',
              'description'    => __('Username issued by PlugnPay at time of sign up.')),
          'remote_password' => array(
              'title'          => __('Remote Client Password', 'woocommerce_plugnpay_api_cc_tokenization'),
              'type'           => 'password',
              'description'    => __('Leave blank to keep the current password. Created in PlugnPay Security Administration.', 'woocommerce_plugnpay_api_cc_tokenization'),
              'custom_attributes' => array('autocomplete' => 'new-password')),
          'token_require_cvv' => array(
              'title'          => __('Saved Card CVV', 'woocommerce_plugnpay_api_cc_tokenization'),
              'type'           => 'checkbox',
              'label'          => __('Require CVV for token-based (card-on-file) transactions.', 'woocommerce_plugnpay_api_cc_tokenization'),
              'description'    => __('When enabled, customers must enter the security code when paying with a saved card. The value is sent as card-cvv with authprev.', 'woocommerce_plugnpay_api_cc_tokenization'),
              'default'        => 'no'),
          'cards_allowed'   => array(
             'title'           => __('Card Types Allowed', 'woocommerce_plugnpay_api_cc_tokenization'),
             'type'            => 'text',
             'description'     => __('Card types you are allowed to accept. Refer to the payment method specifications for possible values.', 'woocommerce_plugnpay_api_cc_tokenization'),
             'default'         => __('Visa,Mastercard,Amex,Discover', 'woocommerce_plugnpay_api_cc_tokenization')),
          'success_message' => array(
              'title'          => __('Transaction Success Message', 'woocommerce_plugnpay_api_cc_tokenization'),
              'type'           => 'textarea',
              'description'    => __('Message to be displayed on successful transaction.', 'woocommerce_plugnpay_api_cc_tokenization'),
              'default'        => __('Your payment has been processed successfully.', 'woocommerce_plugnpay_api_cc_tokenization')),
          'failed_message'  => array(
              'title'          => __('Transaction Failed Message', 'woocommerce_plugnpay_api_cc_tokenization'),
              'type'           => 'textarea',
              'description'    => __('Message to be displayed on failed transaction.', 'woocommerce_plugnpay_api_cc_tokenization'),
              'default'        => __('Your transaction has been declined.', 'woocommerce_plugnpay_api_cc_tokenization')),
          'post_auth'       => array(
              'title'          => __('Transaction Settlement', 'woocommerce_plugnpay_api_cc_tokenization'),
              'type'           => 'select',
              'options'        => array('yes'=>'Authorize and Settle', 'no'=>'Authorize Only'),
              'description'    => __('Transaction Settlement. If you are not sure what to use set to Authorize and Settle.', 'woocommerce_plugnpay_api_cc_tokenization')),
          'authhash'        => array(
              'title'          => __('Authorization Hash', 'woocommerce_plugnpay_api_cc_tokenization'),
              'type'           => 'checkbox',
              'label'          => __('Required. Enable Authorization Verification Hash (SHA-256). Must be enabled in your PlugnPay account with a matching key.', 'woocommerce_plugnpay_api_cc_tokenization'),
              'default'        => 'yes'),
          'authhash_key'    => array(
             'title'           => __('Authorization Hash Key', 'woocommerce_plugnpay_api_cc_tokenization'),
             'type'            => 'password',
             'description'     => __('Required. Leave blank to keep the current key. If using Divert Currency, list each currency with its associated key [i.e. USD:key1,BBD:key2,CAD:key3]. Must match your PlugnPay account.', 'woocommerce_plugnpay_api_cc_tokenization'),
             'default'         => '',
             'custom_attributes' => array('autocomplete' => 'new-password')),
          'authhash_fields' => array(
             'title'           => __('Authorization Hash Fields', 'woocommerce_plugnpay_api_cc_tokenization'),
             'type'            => 'select',
             'options'         => array( '1'=>'publisher-name', '2'=>'card-amount,publisher-name', '3'=>'acct_code,card-amount,publisher-name'),
             'description'     => __('Fieldset to use with authhash validation. [Must configure your PlugnPay account to match]', 'woocommerce_plugnpay_api_cc_tokenization'),
             'default'         => '3'),
          'giftcard_allow'  => array(
              'title'          => __('Giftcard Acceptance', 'woocommerce_plugnpay_api_cc_tokenization'),
              'type'           => 'checkbox',
              'label'          => __('Enable to allow Giftcard Split Payments on new-card checkouts. [Merchant Processor Giftcard ability required]', 'woocommerce_plugnpay_api_cc_tokenization'),
              'default'        => 'no'),
          'giftcard_descr'  => array(
              'title'          => __('Giftcard Description:', 'woocommerce_plugnpay_api_cc_tokenization'),
              'type'           => 'textarea',
              'description'    => __('This controls the giftcard description which the user sees during checkout.', 'woocommerce_plugnpay_api_cc_tokenization'),
              'default'        => __('[optional] Enter your gift card details below.', 'woocommerce_plugnpay_api_cc_tokenization')),
          'giftcard_note'   => array(
              'title'          => __('Giftcard Note:', 'woocommerce_plugnpay_api_cc_tokenization'),
              'type'           => 'textarea',
              'description'    => __('This controls the usage note under the giftcard fields, which the user sees during checkout.', 'woocommerce_plugnpay_api_cc_tokenization'),
              'default'        => __('If gift card has an insufficient balance, the remainder will be automatically applied to credit card supplied.', 'woocommerce_plugnpay_api_cc_tokenization')),
           'divert_currency' => array(
              'title'          => __('Divert Currency', 'woocommerce_plugnpay_api_cc_tokenization'),
              'type'           => 'checkbox',
              'description'    => __('Enable to divert currency to alt account. Tokens are bound to the PlugnPay account that created them and cannot be reused on a different account.', 'woocommerce_plugnpay_api_cc_tokenization'),
             'default'         => 'no'),
           'divert_accounts'  => array(
             'title'           => __('Diverted Accounts', 'woocommerce_plugnpay_api_cc_tokenization'),
             'type'            => 'text',
             'description'     => __('List currency code, username & Remote Client Password to divert specific payments to.<br>[i.e. USD:username1:abcd1234,BBD:username2:efgh2345,CAD:username3:ijkl3456]<br>Currency codes not listed will use default Gateway Account.','woocommerce_plugnpay_api_cc_tokenization')),
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
      return plugnpay_pci_generate_password_html($this, $key, $data, 'woocommerce_plugnpay_api_cc_tokenization');
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
        echo '<div class="error"><p>' . esc_html__('PlugnPay API CC Tokenization: Remote Client Password is required.', 'woocommerce_plugnpay_api_cc_tokenization') . '</p></div>';
      }

      if (!$this->is_authhash_configured()) {
        echo '<div class="error"><p>' . esc_html__('PlugnPay API CC Tokenization: Authorization Verification Hash and key are required before checkout can proceed.', 'woocommerce_plugnpay_api_cc_tokenization') . '</p></div>';
      }

      if (!plugnpay_pci_storefront_is_secure()) {
        echo '<div class="error"><p>' . esc_html__('PlugnPay API CC Tokenization: HTTPS is required on the storefront because card data is collected onsite.', 'woocommerce_plugnpay_api_cc_tokenization') . '</p></div>';
      }

      if (version_compare(PHP_VERSION, '8.2', '<')) {
        echo '<div class="notice notice-warning"><p>' . esc_html__('PlugnPay API CC Tokenization: PHP 8.2 or higher is recommended.', 'woocommerce_plugnpay_api_cc_tokenization') . '</p></div>';
      }
    }

    public function get_icon() {
      $icons = '';
      $cards_allowed = isset($this->settings['cards_allowed']) ? $this->settings['cards_allowed'] : '';

      if (!empty($cards_allowed)) {
        $icon_path = plugins_url('images/', __FILE__);
        $cards_list = explode(',', $cards_allowed);

        foreach ($cards_list as $card) {
          $card = trim($card);
          if ($card === '') {
            continue;
          }

          $img_url = $icon_path . strtolower($card) . '.png';
          $icons .= '<img src="' . esc_url($img_url) . '" alt="' . esc_attr(ucwords($card)) . '" />';
        }

        if ($icons !== '') {
          $icons = '<span class="plugnpay-cc-card-icons">' . $icons . '</span>';
        }
      }

      return apply_filters('woocommerce_gateway_icon', $icons, $this->id);
    }

    public function admin_options() {
      echo '<h3>'.__('PlugnPay API CC Tokenization Payment Gateway', 'woocommerce_plugnpay_api_cc_tokenization').'</h3>';
      echo '<p>'.__('Accept credit cards via PlugnPay Remote API with card-on-file tokenization. Saved cards are bound to the PlugnPay gateway account used at signup and expire after 24 months of inactivity on prevorderid or when the card expiration date is reached.', 'woocommerce_plugnpay_api_cc_tokenization').'</p>';
      echo '<table class="form-table">';
      $this->generate_settings_html();
      echo '</table>';
    }

    function payment_fields() {
      if ($this->description) {
        echo '<div class="plugnpay-cc-description">' . wp_kses_post(wpautop(wptexturize($this->description))) . '</div>';
      }

      $is_add_payment_method = function_exists('is_add_payment_method_page') && is_add_payment_method_page();

      // Add Payment Method screen: only new-card fields (never list existing tokens).
      if ($is_add_payment_method) {
        echo '<input type="hidden" name="wc-' . esc_attr($this->id) . '-payment-token" value="new" />';
        $this->render_new_card_fields(array(
          'show_save_checkbox' => false,
          'show_giftcard'      => false,
          'collapsible'        => false,
          'hidden'             => false,
        ));
        return;
      }

      $order = $this->get_checkout_order_context();
      $credentials = $this->resolve_gateway_credentials($order);
      $account = $credentials['gateway_account'];
      $tokens = array();

      if (is_user_logged_in()) {
        $tokens = $this->get_usable_tokens_for_account(get_current_user_id(), $account);
      }

      $has_tokens = !empty($tokens);
      $checked_token_id = $has_tokens ? (string) $tokens[0]->get_id() : 'new';

      if ($has_tokens) {
        echo '<fieldset class="plugnpay-cc-saved-methods">';
        echo '<legend class="screen-reader-text">' . esc_html__('Saved cards', 'woocommerce_plugnpay_api_cc_tokenization') . '</legend>';

        foreach ($tokens as $token) {
          $token_id = (string) $token->get_id();
          $input_id = 'wc-' . $this->id . '-payment-token-' . $token_id;
          printf(
            '<p class="form-row form-row-wide plugnpay-cc-saved-method"><label for="%1$s"><input id="%1$s" type="radio" name="wc-%2$s-payment-token" value="%3$s"%4$s /> %5$s</label></p>',
            esc_attr($input_id),
            esc_attr($this->id),
            esc_attr($token_id),
            checked($checked_token_id, $token_id, false),
            esc_html($this->get_token_display_name($token))
          );
        }

        printf(
          '<p class="form-row form-row-wide plugnpay-cc-saved-method"><label for="wc-%1$s-payment-token-new"><input id="wc-%1$s-payment-token-new" type="radio" name="wc-%1$s-payment-token" value="new"%2$s /> %3$s</label></p>',
          esc_attr($this->id),
          checked($checked_token_id, 'new', false),
          esc_html__('Use a new card', 'woocommerce_plugnpay_api_cc_tokenization')
        );
        echo '</fieldset>';

        if ($this->is_token_cvv_required()) {
          $token_cvv_class = 'plugnpay-cc-token-cvv-fields';
          if ($checked_token_id === 'new') {
            $token_cvv_class .= ' plugnpay-cc-token-cvv-fields--hidden';
          }

          echo '<fieldset class="' . esc_attr($token_cvv_class) . '">';
          echo '<legend class="screen-reader-text">' . esc_html__('Saved card security code', 'woocommerce_plugnpay_api_cc_tokenization') . '</legend>';
          $this->render_checkout_field(
            'pnp_token_cvv',
            __('Security code', 'woocommerce_plugnpay_api_cc_tokenization'),
            array(
              'class'        => 'form-row-first',
              'type'         => 'tel',
              'required'     => true,
              'maxlength'    => '4',
              'minlength'    => '3',
              'pattern'      => '[0-9]{3,4}',
              'inputmode'    => 'numeric',
              'autocomplete' => 'cc-csc',
              'placeholder'  => 'CVV',
            )
          );
          echo '</fieldset>';
        }
      }
      else {
        echo '<input type="hidden" name="wc-' . esc_attr($this->id) . '-payment-token" value="new" />';
      }

      $this->render_new_card_fields(array(
        'show_save_checkbox' => is_user_logged_in(),
        'show_giftcard'      => true,
        'collapsible'        => $has_tokens,
        'hidden'             => ($has_tokens && $checked_token_id !== 'new'),
      ));
    }

    /**
    * Shared new-card fieldset used by checkout and Add Payment Method.
    **/
    private function render_new_card_fields($args = array()) {
      $defaults = array(
        'show_save_checkbox' => false,
        'show_giftcard'      => false,
        'collapsible'        => false,
        'hidden'             => false,
      );
      $args = wp_parse_args($args, $defaults);

      $new_card_class = 'plugnpay-cc-new-card-fields';
      if ($args['collapsible']) {
        $new_card_class .= ' plugnpay-cc-new-card-fields--collapsible';
      }
      if ($args['hidden']) {
        $new_card_class .= ' plugnpay-cc-new-card-fields--hidden';
      }

      echo '<fieldset class="' . esc_attr($new_card_class) . ' wc-payment-form">';
      echo '<legend class="screen-reader-text">' . esc_html__('Credit card details', 'woocommerce_plugnpay_api_cc_tokenization') . '</legend>';

      $this->render_checkout_field(
        'pnp_cardnumber',
        __('Card number', 'woocommerce_plugnpay_api_cc_tokenization'),
        array(
          'class'        => 'form-row-wide',
          'type'         => 'tel',
          'required'     => true,
          'maxlength'    => '20',
          'minlength'    => '13',
          'pattern'      => '[0-9]{13,20}',
          'inputmode'    => 'numeric',
          'autocomplete' => 'cc-number',
          'placeholder'  => '•••• •••• •••• ••••',
        )
      );

      $this->render_expiry_fields();

      $this->render_checkout_field(
        'pnp_cardcvv',
        __('Security code', 'woocommerce_plugnpay_api_cc_tokenization'),
        array(
          'class'        => 'form-row-last',
          'type'         => 'tel',
          'required'     => true,
          'maxlength'    => '4',
          'minlength'    => '3',
          'pattern'      => '[0-9]{3,4}',
          'inputmode'    => 'numeric',
          'autocomplete' => 'cc-csc',
          'placeholder'  => 'CVV',
        )
      );

      if ($args['show_save_checkbox']) {
        echo '<p class="form-row form-row-wide plugnpay-cc-save-card">';
        echo '<label for="wc-' . esc_attr($this->id) . '-new-payment-method">';
        echo '<input id="wc-' . esc_attr($this->id) . '-new-payment-method" name="wc-' . esc_attr($this->id) . '-new-payment-method" type="checkbox" value="true" /> ';
        echo esc_html__('Save payment information to my account for future purchases.', 'woocommerce_plugnpay_api_cc_tokenization');
        echo '</label></p>';
      }

      if ($args['show_giftcard'] && isset($this->settings['giftcard_allow']) && $this->settings['giftcard_allow'] == 'yes') {
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
      $required_mark = $args['required'] ? ' <abbr class="required" title="' . esc_attr__('required', 'woocommerce_plugnpay_api_cc_tokenization') . '">*</abbr>' : '';

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

    private function render_expiry_fields() {
      $required_mark = ' <abbr class="required" title="' . esc_attr__('required', 'woocommerce_plugnpay_api_cc_tokenization') . '">*</abbr>';

      echo '<p class="form-row form-row-first plugnpay-cc-expiry">';
      echo '<span class="plugnpay-cc-expiry-fields">';

      echo '<span class="plugnpay-cc-expiry-field">';
      echo '<label for="pnp_cardexp_month">' . esc_html__('Month', 'woocommerce_plugnpay_api_cc_tokenization') . $required_mark . '</label>';
      echo '<select name="pnp_cardexp_month" id="pnp_cardexp_month" class="input-text" autocomplete="cc-exp-month" required>';
      echo '<option value="">' . esc_html__('Month', 'woocommerce_plugnpay_api_cc_tokenization') . '</option>';
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
      echo '<label for="pnp_cardexp_year">' . esc_html__('Year', 'woocommerce_plugnpay_api_cc_tokenization') . $required_mark . '</label>';
      echo '<select name="pnp_cardexp_year" id="pnp_cardexp_year" class="input-text" autocomplete="cc-exp-year" required>';
      echo '<option value="">' . esc_html__('Year', 'woocommerce_plugnpay_api_cc_tokenization') . '</option>';
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

    private function render_giftcard_fields() {
      echo '<p class="form-row form-row-wide plugnpay-cc-giftcard-row">';
      echo '<span class="plugnpay-cc-giftcard-fields">';

      echo '<span class="plugnpay-cc-giftcard-field plugnpay-cc-giftcard-field-number">';
      echo '<label for="pnp_mpgiftcard">' . esc_html__('Gift card number', 'woocommerce_plugnpay_api_cc_tokenization') . '</label>';
      echo '<input type="tel" class="input-text" name="pnp_mpgiftcard" id="pnp_mpgiftcard" maxlength="20" minlength="1" pattern="[0-9]{1,20}" inputmode="numeric" autocomplete="off" />';
      echo '</span>';

      echo '<span class="plugnpay-cc-giftcard-field plugnpay-cc-giftcard-field-cvv">';
      echo '<label for="pnp_mpcvv">' . esc_html__('Gift card security code', 'woocommerce_plugnpay_api_cc_tokenization') . '</label>';
      echo '<input type="tel" class="input-text" name="pnp_mpcvv" id="pnp_mpcvv" maxlength="4" minlength="3" pattern="[0-9]{3,4}" inputmode="numeric" autocomplete="off" placeholder="CVV" />';
      echo '</span>';

      echo '</span></p>';
    }

    private function get_expiry_month_options() {
      $options = array();

      for ($month = 1; $month <= 12; $month++) {
        $month_name = date_i18n('F', mktime(0, 0, 0, $month, 1));
        $options[sprintf('%02d', $month)] = sprintf('%s (%d)', $month_name, $month);
      }

      return $options;
    }

    private function get_expiry_year_options() {
      $options = array();
      $current_year = (int) gmdate('Y');
      $max_years_ahead = 15;

      for ($year = $current_year; $year <= ($current_year + $max_years_ahead); $year++) {
        $options[(string) $year] = (string) $year;
      }

      return $options;
    }

    private function get_posted_card_exp() {
      $month = $this->get_posted_field('pnp_cardexp_month');
      $year = $this->get_posted_field('pnp_cardexp_year');

      if ($month === '' || $year === '') {
        return '';
      }

      return sprintf('%02d/%02d', (int) $month, ((int) $year) % 100);
    }

    public function validate_fields() {
      $is_add_payment_method = function_exists('is_add_payment_method_page') && is_add_payment_method_page();
      $token_id = $is_add_payment_method ? 'new' : $this->get_posted_payment_token_id();

      if ($token_id !== 'new' && $token_id !== '') {
        $token = $this->get_validated_payment_token($token_id, $this->get_checkout_order_context());
        if (!$token) {
          return false;
        }

        if ($this->is_token_cvv_required()) {
          $token_cvv = $this->get_posted_field('pnp_token_cvv');
          if (!$this->isCCVNumber($token_cvv)) {
            wc_add_notice(__('(Card Verification Number) is not valid.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
            return false;
          }
        }

        return true;
      }

      $valid = true;
      $cardnumber = $this->get_posted_field('pnp_cardnumber');
      $cardexp_month = $this->get_posted_field('pnp_cardexp_month');
      $cardexp_year = $this->get_posted_field('pnp_cardexp_year');
      $cardcvv = $this->get_posted_field('pnp_cardcvv');

      if (!$this->isCreditCardNumber($cardnumber)) {
        wc_add_notice(__('(Credit Card Number) is not valid.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        $valid = false;
      }
      if ($cardexp_month === '' || $cardexp_year === '') {
        wc_add_notice(__('(Card Expiry Date) is required.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        $valid = false;
      }
      elseif (!$this->isCorrectExpireDateParts($cardexp_month, $cardexp_year)) {
        wc_add_notice(__('(Card Expiry Date) is not valid.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        $valid = false;
      }
      if (!$this->isCCVNumber($cardcvv)) {
        wc_add_notice(__('(Card Verification Number) is not valid.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        $valid = false;
      }

      if (isset($this->settings['giftcard_allow']) && $this->settings['giftcard_allow'] == 'yes') {
        $mpgiftcard = $this->get_posted_field('pnp_mpgiftcard');
        $mpcvv = $this->get_posted_field('pnp_mpcvv');

        if (!empty($mpgiftcard) || !empty($mpcvv)) {
          if (!empty($mpgiftcard) && !$this->isNumericField($mpgiftcard, 1, 20)) {
            wc_add_notice(__('(Gift Card Number) is not valid.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
            $valid = false;
          }
          if (!empty($mpcvv) && !$this->isCCVNumber($mpcvv)) {
            wc_add_notice(__('(Gift Card Verification Number) is not valid.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
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

    private function get_posted_payment_token_id() {
      $key = 'wc-' . $this->id . '-payment-token';
      if (!isset($_POST[$key])) {
        return 'new';
      }
      $value = sanitize_text_field(wp_unslash($_POST[$key]));
      return $value === '' ? 'new' : $value;
    }

    private function should_save_payment_method() {
      if (!is_user_logged_in()) {
        return false;
      }
      $key = 'wc-' . $this->id . '-new-payment-method';
      return isset($_POST[$key]) && wc_string_to_bool(wp_unslash($_POST[$key]));
    }

    private function get_order_amount($order) {
      return wc_format_decimal($order->get_total(), 2);
    }

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

      if ($sum > 0 && $sum % 10 == 0) {
        return true;
      }

      return false;
    }

    private function isCCVNumber($toCheck) {
      $length = strlen($toCheck);
      return is_numeric($toCheck) && $length > 2 && $length < 5;
    }

    private function isNumericField($toCheck, $minLength, $maxLength) {
      $length = strlen($toCheck);
      return ctype_digit($toCheck) && $length >= $minLength && $length <= $maxLength;
    }

    private function isCorrectExpireDateParts($month, $year) {
      if (!ctype_digit((string) $month) || !ctype_digit((string) $year)) {
        return false;
      }

      $month = (int) $month;
      $year = (int) $year;

      if ($month < 1 || $month > 12) {
        return false;
      }

      if ($year < 100) {
        $year += 2000;
      }

      $current_year = (int) gmdate('Y');
      $current_month = (int) gmdate('n');

      if ($year < $current_year) {
        return false;
      }

      if ($year === $current_year && $month < $current_month) {
        return false;
      }

      return true;
    }

    function thankyou_page($order_id) {
    }

    function receipt_page($order) {
      echo '<p>'.__('Thank you for your order.', 'woocommerce_plugnpay_api_cc_tokenization').'</p>';
    }

    /**
    * My Account → Add payment method: validate card, init COF auth, store token.
    **/
    public function add_payment_method() {
      if (!is_user_logged_in()) {
        wc_add_notice(__('(Transaction Error) You must be logged in to add a payment method.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        return array('result' => 'failure');
      }

      if (empty($this->settings['gateway_account']) || $this->get_plain_secret('remote_password') === '') {
        wc_add_notice(__('(Transaction Error) Payment gateway is not configured.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        return array('result' => 'failure');
      }

      if (!$this->is_authhash_configured()) {
        wc_add_notice(__('(Transaction Error) Payment gateway is not configured for Authorization Hash.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        return array('result' => 'failure');
      }

      if (!plugnpay_pci_storefront_is_secure()) {
        wc_add_notice(__('(Transaction Error) Secure HTTPS checkout is required.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        return array('result' => 'failure');
      }

      if (!$this->validate_fields()) {
        return array('result' => 'failure');
      }

      $params = $this->generate_add_payment_method_params();
      $response = $this->post_to_plugnpay($params);

      if ($response === false || !empty($response['curl_error'])) {
        wc_add_notice(__('(Transaction Error) Error connecting to payment gateway.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        return array('result' => 'failure');
      }

      if (empty($response['FinalStatus']) || (($response['FinalStatus'] != 'success') && ($response['FinalStatus'] != 'pending'))) {
        $merr_msg = isset($response['MErrMsg']) ? $response['MErrMsg'] : '';
        if ($merr_msg === '') {
          $merr_msg = __('Error processing payment method.', 'woocommerce_plugnpay_api_cc_tokenization');
        }
        wc_add_notice(sprintf(__('(Transaction Error) %s', 'woocommerce_plugnpay_api_cc_tokenization'), $merr_msg), 'error');
        return array('result' => 'failure');
      }

      $transaction_id = isset($response['orderID']) ? $response['orderID'] : '';
      if ($transaction_id === '') {
        wc_add_notice(__('(Transaction Error) Payment method was approved but no transaction ID was returned.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        return array('result' => 'failure');
      }

      $credentials = $this->resolve_gateway_credentials(null);
      $new_token = $this->create_payment_token(null, $transaction_id, $credentials['gateway_account']);

      if (!$new_token) {
        wc_add_notice(__('(Transaction Error) Payment was approved but the card could not be saved to your account.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        return array('result' => 'failure');
      }

      return array(
        'result'   => 'success',
        'redirect' => function_exists('wc_get_account_endpoint_url')
          ? wc_get_account_endpoint_url('payment-methods')
          : wc_get_endpoint_url('payment-methods'),
      );
    }

    function process_payment($order_id) {
      $order = wc_get_order($order_id);

      if (!$order) {
        wc_add_notice(__('(Transaction Error) Invalid order.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        return array('result' => 'failure');
      }

      if (empty($this->settings['gateway_account']) || $this->get_plain_secret('remote_password') === '') {
        wc_add_notice(__('(Transaction Error) Payment gateway is not configured.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        return array('result' => 'failure');
      }

      if (!$this->is_authhash_configured()) {
        wc_add_notice(__('(Transaction Error) Payment gateway is not configured for Authorization Hash.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        return array('result' => 'failure');
      }

      if (!plugnpay_pci_storefront_is_secure()) {
        wc_add_notice(__('(Transaction Error) Secure HTTPS checkout is required.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        return array('result' => 'failure');
      }

      $token_id = $this->get_posted_payment_token_id();
      $payment_token = null;
      $save_card = false;

      if ($token_id !== 'new' && $token_id !== '') {
        $payment_token = $this->get_validated_payment_token($token_id, $order);
        if (!$payment_token) {
          return array('result' => 'failure');
        }
        $params = $this->generate_authprev_params($order, $payment_token);
      }
      else {
        $save_card = $this->should_save_payment_method();
        $params = $this->generate_auth_params($order, $save_card);
      }

      $response = $this->post_to_plugnpay($params);

      if ($response === false) {
        $order->add_order_note($this->settings['failed_message'] . ' cURL error while contacting payment gateway.');
        $order->update_status('failed');
        wc_add_notice(__('(Transaction Error) Error connecting to payment gateway.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        return array('result' => 'failure');
      }

      if (!empty($response['curl_error'])) {
        $order->add_order_note($this->settings['failed_message'] . ' cURL error: ' . $response['curl_error']);
        $order->update_status('failed');
        wc_add_notice(__('(Transaction Error) Error connecting to payment gateway.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        return array('result' => 'failure');
      }

      if (!empty($response['FinalStatus'])) {
        if (($response['FinalStatus'] == 'success') || ($response['FinalStatus'] == 'pending')) {
          if ($order->get_status() != 'completed') {
            $transaction_id = isset($response['orderID']) ? $response['orderID'] : '';
            $order->payment_complete($transaction_id);
            WC()->cart->empty_cart();
            $merr_msg = isset($response['MErrMsg']) ? $response['MErrMsg'] : '';
            $order->add_order_note($this->settings['success_message'] . $merr_msg . ' Transaction ID: ' . $transaction_id);

            $credentials = $this->resolve_gateway_credentials($order);

            if ($payment_token) {
              $this->update_token_prevorderid($payment_token, $transaction_id);
              $order->add_order_note(sprintf(
                __('Charged saved card token #%1$s via authprev (account: %2$s).', 'woocommerce_plugnpay_api_cc_tokenization'),
                $payment_token->get_id(),
                $credentials['gateway_account']
              ));
            }
            elseif ($save_card && $transaction_id !== '') {
              $new_token = $this->create_payment_token(
                $order,
                $transaction_id,
                $credentials['gateway_account']
              );
              if ($new_token) {
                $order->add_order_note(sprintf(
                  __('Saved card-on-file token #%1$s for account %2$s (origorderid: %3$s).', 'woocommerce_plugnpay_api_cc_tokenization'),
                  $new_token->get_id(),
                  $credentials['gateway_account'],
                  $transaction_id
                ));
              }
            }
          }
          return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order)
          );
        }

        $merr_msg = isset($response['MErrMsg']) ? $response['MErrMsg'] : '';
        $order->add_order_note($this->settings['failed_message'] . $merr_msg);
        $order->update_status('failed');
        wc_add_notice(sprintf(__('(Transaction Error) %s', 'woocommerce_plugnpay_api_cc_tokenization'), $merr_msg), 'error');
        return array('result' => 'failure');
      }

      $order->add_order_note($this->settings['failed_message']);
      $order->update_status('failed');
      wc_add_notice(__('(Transaction Error) Error processing payment.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
      return array('result' => 'failure');
    }

    /**
    * Resolve gateway account/password/currency for an order (supports divert currency).
    **/
    private function resolve_gateway_credentials($order = null) {
      $gatewayAccount = isset($this->settings['gateway_account']) ? $this->settings['gateway_account'] : '';
      $remotePassword = $this->get_plain_secret('remote_password');
      $currencyCode = 'USD';

      if ($order) {
        $currencyCode = $order->get_currency();
      }
      elseif (function_exists('get_woocommerce_currency')) {
        $currencyCode = get_woocommerce_currency();
      }

      if (isset($this->settings['divert_currency']) && $this->settings['divert_currency'] == 'yes' && !empty($this->settings['divert_accounts'])) {
        $divert_list = explode(',', $this->settings['divert_accounts']);
        foreach ($divert_list as $i) {
          $parts = explode(':', $i, 3);
          if (count($parts) < 3) {
            continue;
          }
          list($altCurrency, $altMerchant, $altPassword) = $parts;
          if (strtolower($altCurrency) == strtolower($currencyCode)) {
            $currencyCode = $altCurrency;
            $gatewayAccount = $altMerchant;
            $remotePassword = $altPassword;
            break;
          }
        }
      }

      return array(
        'gateway_account' => strtolower(trim($gatewayAccount)),
        'remote_password' => $remotePassword,
        'currency'        => strtoupper($currencyCode),
      );
    }

    private function get_checkout_order_context() {
      global $wp;

      if (is_wc_endpoint_url('order-pay') && isset($wp->query_vars['order-pay'])) {
        $order = wc_get_order(absint($wp->query_vars['order-pay']));
        if ($order) {
          return $order;
        }
      }

      return null;
    }

    private function get_token_display_name($token) {
      return sprintf(
        '%1$s ending in %2$s (expires %3$s/%4$s)',
        wc_get_credit_card_type_label($token->get_card_type()),
        $token->get_last4(),
        $token->get_expiry_month(),
        $token->get_expiry_year()
      );
    }

    private function is_token_cvv_required() {
      return isset($this->settings['token_require_cvv']) && $this->settings['token_require_cvv'] === 'yes';
    }

    /**
    * Tokens usable for the resolved PlugnPay account only.
    **/
    private function get_usable_tokens_for_account($user_id, $gateway_account) {
      $usable = array();
      $gateway_account = strtolower(trim((string) $gateway_account));

      if ($user_id <= 0 || $gateway_account === '' || !class_exists('WC_Payment_Tokens')) {
        return $usable;
      }

      $tokens = WC_Payment_Tokens::get_customer_tokens($user_id, $this->id);
      foreach ($tokens as $token) {
        if (!$this->token_matches_gateway_account($token, $gateway_account)) {
          continue;
        }
        if (!$this->is_token_usable($token)) {
          continue;
        }
        $usable[] = $token;
      }

      return $usable;
    }

    private function token_matches_gateway_account($token, $gateway_account) {
      $token_account = strtolower(trim((string) $token->get_meta(self::TOKEN_META_GATEWAY_ACCOUNT)));
      return $token_account !== '' && $token_account === strtolower(trim((string) $gateway_account));
    }

    private function is_token_usable($token) {
      $orig = (string) $token->get_meta(self::TOKEN_META_ORIGORDERID);
      $prev = (string) $token->get_meta(self::TOKEN_META_PREVORDERID);
      $prev_ts = (int) $token->get_meta(self::TOKEN_META_PREVORDERID_TS);
      $account = (string) $token->get_meta(self::TOKEN_META_GATEWAY_ACCOUNT);

      if ($orig === '' || $prev === '' || $account === '') {
        return false;
      }

      if ($prev_ts <= 0) {
        return false;
      }

      if ((time() - $prev_ts) > self::PREVORDERID_MAX_AGE_SECONDS) {
        return false;
      }

      $exp_month = (int) $token->get_expiry_month();
      $exp_year = (int) $token->get_expiry_year();
      if ($exp_month < 1 || $exp_month > 12 || $exp_year < 1) {
        return false;
      }

      $current_year = (int) gmdate('Y');
      $current_month = (int) gmdate('n');
      if ($exp_year < $current_year) {
        return false;
      }
      if ($exp_year === $current_year && $exp_month < $current_month) {
        return false;
      }

      return true;
    }

    private function get_validated_payment_token($token_id, $order = null) {
      if (!class_exists('WC_Payment_Tokens') || !is_user_logged_in()) {
        wc_add_notice(__('(Transaction Error) Saved cards require a logged-in account.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        return null;
      }

      $token = WC_Payment_Tokens::get($token_id);
      if (!$token || (int) $token->get_user_id() !== (int) get_current_user_id() || $token->get_gateway_id() !== $this->id) {
        wc_add_notice(__('(Transaction Error) Invalid saved card selection.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        return null;
      }

      $credentials = $this->resolve_gateway_credentials($order);
      if (!$this->token_matches_gateway_account($token, $credentials['gateway_account'])) {
        wc_add_notice(__('(Transaction Error) This saved card cannot be used with the PlugnPay account for this order currency. Please enter a new card for this account.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        return null;
      }

      if (!$this->is_token_usable($token)) {
        wc_add_notice(__('(Transaction Error) This saved card is no longer usable (older than 24 months or expired). Please enter a new card.', 'woocommerce_plugnpay_api_cc_tokenization'), 'error');
        return null;
      }

      return $token;
    }

    private function create_payment_token($order, $order_id_from_gateway, $gateway_account) {
      if (!is_user_logged_in() || !class_exists('WC_Payment_Token_CC')) {
        return null;
      }

      $cardnumber = $this->get_posted_field('pnp_cardnumber');
      $exp_month = $this->get_posted_field('pnp_cardexp_month');
      $exp_year = $this->get_posted_field('pnp_cardexp_year');

      if ($cardnumber === '' || $exp_month === '' || $exp_year === '' || $order_id_from_gateway === '') {
        return null;
      }

      $token = new WC_Payment_Token_CC();
      $token->set_token($order_id_from_gateway);
      $token->set_gateway_id($this->id);
      $token->set_user_id(get_current_user_id());
      $token->set_card_type($this->detect_card_type($cardnumber));
      $token->set_last4(substr($cardnumber, -4));
      $token->set_expiry_month(sprintf('%02d', (int) $exp_month));
      $token->set_expiry_year((string) ((int) $exp_year < 100 ? 2000 + (int) $exp_year : (int) $exp_year));
      $token->add_meta_data(self::TOKEN_META_ORIGORDERID, $order_id_from_gateway, true);
      $token->add_meta_data(self::TOKEN_META_PREVORDERID, $order_id_from_gateway, true);
      $token->add_meta_data(self::TOKEN_META_PREVORDERID_TS, (string) time(), true);
      $token->add_meta_data(self::TOKEN_META_GATEWAY_ACCOUNT, strtolower(trim($gateway_account)), true);

      if (!$token->save()) {
        return null;
      }

      return $token;
    }

    private function update_token_prevorderid($token, $new_order_id) {
      if (!$token || $new_order_id === '') {
        return;
      }

      $token->update_meta_data(self::TOKEN_META_PREVORDERID, $new_order_id);
      $token->update_meta_data(self::TOKEN_META_PREVORDERID_TS, (string) time());
      $token->set_token($new_order_id);
      $token->save();
    }

    private function detect_card_type($cardnumber) {
      $number = preg_replace('/\D/', '', $cardnumber);

      if (preg_match('/^4/', $number)) {
        return 'visa';
      }
      if (preg_match('/^5[1-5]/', $number) || preg_match('/^2(2[2-9]|[3-6]|7[01]|720)/', $number)) {
        return 'mastercard';
      }
      if (preg_match('/^3[47]/', $number)) {
        return 'amex';
      }
      if (preg_match('/^6(?:011|5)/', $number)) {
        return 'discover';
      }
      if (preg_match('/^35/', $number)) {
        return 'jcb';
      }
      if (preg_match('/^3(?:0[0-5]|[68])/', $number)) {
        return 'diners';
      }

      return 'card';
    }

    private function post_to_plugnpay($params) {
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
        return array('curl_error' => $curl_error);
      }

      parse_str($post_response, $response);
      return $response;
    }

    /**
    * Card-on-file registration from My Account (no WooCommerce order).
    * Uses a $0.00 auth with init,cit,recurring,avsonly so origorderid is established.
    * The PlugnPay account must allow AVS-only transactions.
    **/
    public function generate_add_payment_method_params() {
      $credentials = $this->resolve_gateway_credentials(null);
      $gatewayAccount = $credentials['gateway_account'];
      $remotePassword = $credentials['remote_password'];
      $currencyCode = $credentials['currency'];
      $user_id = get_current_user_id();
      $tracking_id = 'apm' . $user_id . gmdate('YmdHis');

      $customer = null;
      if (class_exists('WC_Customer')) {
        $customer = new WC_Customer($user_id);
      }

      $first_name = $customer ? $customer->get_billing_first_name() : '';
      $last_name = $customer ? $customer->get_billing_last_name() : '';
      if ($first_name === '' && $last_name === '') {
        $user = wp_get_current_user();
        $first_name = $user ? $user->first_name : '';
        $last_name = $user ? $user->last_name : '';
        if ($first_name === '' && $user) {
          $first_name = $user->display_name;
        }
      }

      $plugnpayapi_args = array(
        'publisher-name'        => $gatewayAccount,
        'publisher-password'    => $remotePassword,
        'client'                => 'WooCommerce_API_CC_Token',
        'mode'                  => 'auth',
        'transflags'            => 'init,cit,recurring,avsonly',
        'authtype'              => 'authonly',

        'acct_code'             => $tracking_id,
        'order-id'              => $tracking_id,
        'card-amount'           => '0.00',
        'currency'              => $currencyCode,

        'paymethod'             => 'credit',
        'card-number'           => $this->get_posted_field('pnp_cardnumber'),
        'card-exp'              => $this->get_posted_card_exp(),
        'card-cvv'              => $this->get_posted_field('pnp_cardcvv'),

        'card-name'             => trim($first_name . ' ' . $last_name),
        'card-company'          => $customer ? $customer->get_billing_company() : '',
        'card-address1'         => $customer ? $customer->get_billing_address_1() : '',
        'card-address2'         => $customer ? $customer->get_billing_address_2() : '',
        'card-city'             => $customer ? $customer->get_billing_city() : '',
        'card-state'            => $customer ? $customer->get_billing_state() : '',
        'card-zip'              => $customer ? $customer->get_billing_postcode() : '',
        'card-country'          => $customer ? $customer->get_billing_country() : '',
        'phone'                 => $customer ? $customer->get_billing_phone() : '',
        'email'                 => $customer ? $customer->get_billing_email() : '',

        'shipinfo'              => '0',
        'ipaddress'             => plugnpay_cc_tokenization_getUserIP(),
      );

      $this->apply_authhash($plugnpayapi_args, $gatewayAccount, '0.00', $tracking_id, $currencyCode);

      return $plugnpayapi_args;
    }

    /**
    * New-card authorization. When saving, send init,cit,recurring transflags.
    **/
    public function generate_auth_params($order, $save_card = false) {
      $credentials = $this->resolve_gateway_credentials($order);
      $gatewayAccount = $credentials['gateway_account'];
      $remotePassword = $credentials['remote_password'];
      $currencyCode = $credentials['currency'];
      $order_amount = $this->get_order_amount($order);
      $order_id = $order->get_id();

      $plugnpayapi_args = array(
        'publisher-name'        => $gatewayAccount,
        'publisher-password'    => $remotePassword,
        'client'                => 'WooCommerce_API_CC_Token',
        'mode'                  => 'auth',

        'acct_code'             => $order_id,
        'order-id'              => $order_id,
        'card-amount'           => $order_amount,
        'currency'              => $currencyCode,

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

      $plugnpayapi_args['ipaddress'] = plugnpay_cc_tokenization_getUserIP();

      if ($save_card) {
        // Initial card-on-file registration flags.
        $plugnpayapi_args['transflags'] = 'init,cit,recurring';
      }

      if ($this->settings['post_auth'] == 'yes') {
        $plugnpayapi_args['authtype'] = 'authpostauth';
      }
      else {
        $plugnpayapi_args['authtype'] = 'authonly';
      }

      $this->apply_authhash($plugnpayapi_args, $gatewayAccount, $order_amount, $order_id, $currencyCode);

      if (isset($this->settings['giftcard_allow']) && $this->settings['giftcard_allow'] == 'yes') {
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

    /**
    * Card-on-file charge via authprev.
    **/
    public function generate_authprev_params($order, $token) {
      $credentials = $this->resolve_gateway_credentials($order);
      $gatewayAccount = $credentials['gateway_account'];
      $remotePassword = $credentials['remote_password'];
      $currencyCode = $credentials['currency'];
      $order_amount = $this->get_order_amount($order);
      $order_id = $order->get_id();

      $origorderid = (string) $token->get_meta(self::TOKEN_META_ORIGORDERID);
      $prevorderid = (string) $token->get_meta(self::TOKEN_META_PREVORDERID);

      $plugnpayapi_args = array(
        'publisher-name'        => $gatewayAccount,
        'publisher-password'    => $remotePassword,
        'client'                => 'WooCommerce_API_CC_Token',
        'mode'                  => 'authprev',
        'transflags'            => 'cit,recurring',
        'card-amount'           => $order_amount,
        'currency'              => $currencyCode,
        'origorderid'           => $origorderid,
        'prevorderid'           => $prevorderid,
        'acct_code'             => $order_id,
        'order-id'              => $order_id,
        'card-name'             => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        'email'                 => $order->get_billing_email(),
        'ipaddress'             => plugnpay_cc_tokenization_getUserIP(),
      );

      if ($this->settings['post_auth'] == 'yes') {
        $plugnpayapi_args['authtype'] = 'authpostauth';
      }
      else {
        $plugnpayapi_args['authtype'] = 'authonly';
      }

      if ($this->is_token_cvv_required()) {
        $token_cvv = $this->get_posted_field('pnp_token_cvv');
        if ($token_cvv !== '') {
          $plugnpayapi_args['card-cvv'] = $token_cvv;
        }
      }

      $this->apply_authhash($plugnpayapi_args, $gatewayAccount, $order_amount, $order_id, $currencyCode);

      return $plugnpayapi_args;
    }

    private function apply_authhash(&$plugnpayapi_args, $gatewayAccount, $order_amount, $order_id, $currencyCode) {
      $timestamp = gmdate('YmdHis', time());
      $authhash_key = plugnpay_pci_resolve_authhash_key(
        $this->get_plain_secret('authhash_key'),
        $currencyCode,
        isset($this->settings['divert_currency']) && $this->settings['divert_currency'] === 'yes'
      );
      $string_fields = plugnpay_pci_authhash_string_fields(
        isset($this->settings['authhash_fields']) ? $this->settings['authhash_fields'] : '3',
        $order_id,
        $order_amount,
        $gatewayAccount
      );
      $plugnpayapi_args['authhash'] = plugnpay_pci_authhash($authhash_key . $timestamp . $string_fields);
      $plugnpayapi_args['transacttime'] = $timestamp;
    }
  }

  function woocommerce_add_plugnpay_api_cc_tokenization_gateway($methods) {
    $methods[] = 'WC_Plugnpay_API_CC_Tokenization_Gateway';
    return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'woocommerce_add_plugnpay_api_cc_tokenization_gateway');
}

function woocommerce_plugnpay_api_cc_tokenization_php_notice() {
  echo '<div class="error"><p>' . esc_html__('PlugnPay API CC Tokenization requires PHP 8.1 or higher.', 'woocommerce_plugnpay_api_cc_tokenization') . '</p></div>';
}

function woocommerce_plugnpay_api_cc_tokenization_version_notice() {
  echo '<div class="error"><p>' . esc_html__('PlugnPay API CC Tokenization requires WooCommerce 8.0 or higher.', 'woocommerce_plugnpay_api_cc_tokenization') . '</p></div>';
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'plugnpay_cc_tokenization_action_links');

function plugnpay_cc_tokenization_action_links ($links) {
  $gateway_links = array(
    '<a href="https://www.gatewaystatus.com/" target="_blank" rel="noopener noreferrer">Gateway Status</a>',
    '<a href="https://helpdesk.plugnpay.com/" target="_blank" rel="noopener noreferrer">Online Helpdesk</a>',
    '<a href="https://pay1.plugnpay.com/admin/" target="_blank" rel="noopener noreferrer">Merchant Admin</a>'
  );
  return array_merge($links, $gateway_links);
}

function plugnpay_cc_tokenization_getUserIP() {
  if (class_exists('WC_Geolocation')) {
    return WC_Geolocation::get_ip_address();
  }

  if (!empty($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
    return $_SERVER['REMOTE_ADDR'];
  }

  return '';
}

?>

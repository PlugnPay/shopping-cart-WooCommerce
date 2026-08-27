<?php
/**
 * Plugin Name: PlugnPay SSv2 Payment Gateway For WooCommerce
 * Plugin URI: https://github.com/PlugnPay/shopping-cart-WooCommerce
 * Description: Extends WooCommerce to Process Smart Screens v2 Payments with PlugnPay gateway.
 * Version: 1.1.13
 * Author: PlugnPay
 * Author URI: https://www.plugnpay.com
 * Text Domain: woocommerce_plugnpay_ss2
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/callback-ip.php';
require_once __DIR__ . '/includes/crypto.php';
require_once __DIR__ . '/includes/amounts.php';
require_once __DIR__ . '/includes/admin-ui.php';

add_action('before_woocommerce_init', 'woocommerce_plugnpay_ss2_declare_hpos_compatibility');

function woocommerce_plugnpay_ss2_declare_hpos_compatibility() {
  if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
  }
}

add_action('plugins_loaded', 'woocommerce_plugnpay_ss2_init', 0);

function woocommerce_plugnpay_ss2_init() {
  if (!class_exists('WC_Payment_Gateway')) {
    return;
  }

  if (version_compare(PHP_VERSION, plugnpay_ss2_minimum_php_version(), '<')) {
    add_action('admin_notices', 'woocommerce_plugnpay_ss2_php_notice');
    return;
  }

  if (!function_exists('WC') || version_compare(WC()->version, '8.0', '<')) {
    add_action('admin_notices', 'woocommerce_plugnpay_ss2_version_notice');
    return;
  }

  load_plugin_textdomain('woocommerce_plugnpay_ss2', false, dirname(plugin_basename(__FILE__)) . '/languages');

  class WC_Tech_Autho extends WC_Payment_Gateway {
    protected $msg = array();

    public function __construct() {
      $this->id                 = 'plugnpay';
      $this->method_title       = __('PlugnPay SSv2', 'woocommerce_plugnpay_ss2');
      $this->method_description = __('Smart Screens v2 payment method redirects customers to PlugnPay to enter their payment information.', 'woocommerce_plugnpay_ss2');
      $this->icon               = '';
      $this->has_fields         = false;
      $this->supports           = array('products');
      $this->init_form_fields();
      $this->init_settings();
      $this->title              = $this->settings['title'];
      $this->description        = $this->settings['description'];
      $this->msg['message']     = '';
      $this->msg['class']       = '';

      add_action('woocommerce_api_wc_tech_autho', array($this, 'check_plugnpay_response'));
      add_action('template_redirect', array($this, 'handle_plugnpay_hidden_post'), 0);
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
      add_action('woocommerce_receipt_plugnpay', array($this, 'receipt_page'));
      add_action('woocommerce_thankyou_plugnpay', array($this, 'thankyou_page'));
      add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_assets'));
      add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
      add_action('admin_notices', array($this, 'admin_security_notices'));
    }

    /**
     * Checkout assets for payment method display.
     */
    public function enqueue_checkout_assets() {
      if (!is_checkout() && !is_wc_endpoint_url('order-pay')) {
        return;
      }

      wp_enqueue_style(
        'plugnpay-ss2-checkout',
        plugins_url('assets/css/checkout.css', __FILE__),
        array(),
        '1.1.13'
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
        'plugnpay-ss2-admin-settings',
        plugins_url('assets/css/admin-settings.css', __FILE__),
        array(),
        '1.1.13'
      );
      wp_enqueue_script(
        'plugnpay-ss2-admin-settings',
        plugins_url('assets/js/admin-settings.js', __FILE__),
        array('jquery'),
        '1.1.13',
        true
      );
      wp_localize_script(
        'plugnpay-ss2-admin-settings',
        'plugnpaySs2Admin',
        array(
          'gatewayId'        => $this->id,
          'dependentFields'  => plugnpay_ss2_admin_dependent_fields(),
        )
      );
    }

    /**
     * Card type logos for checkout, based on the cards_allowed setting.
     */
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
          $icons = '<span class="plugnpay-ss2-card-icons">' . $icons . '</span>';
        }
      }

      return apply_filters('woocommerce_gateway_icon', $icons, $this->id);
    }

    public function init_form_fields() {
      $this->form_fields = array(
        'section_checkout' => array(
          'title'       => __('Checkout display', 'woocommerce_plugnpay_ss2'),
          'type'        => 'title',
          'class'       => 'plugnpay-ss2-settings-section plugnpay-ss2-settings-section-first',
          'description' => __('How this payment method appears on the checkout page.', 'woocommerce_plugnpay_ss2'),
        ),
        'enabled' => array(
          'title'   => __('Enable/Disable', 'woocommerce_plugnpay_ss2'),
          'type'    => 'checkbox',
          'label'   => __('Enable PlugnPay Payment Module.', 'woocommerce_plugnpay_ss2'),
          'default' => 'no',
        ),
        'title' => array(
          'title'       => __('Title:', 'woocommerce_plugnpay_ss2'),
          'type'        => 'text',
          'description' => __('This controls the title which the user sees during checkout.', 'woocommerce_plugnpay_ss2'),
          'default'     => __('Credit Card', 'woocommerce_plugnpay_ss2'),
          'desc_tip'    => true,
        ),
        'description' => array(
          'title'       => __('Description:', 'woocommerce_plugnpay_ss2'),
          'type'        => 'textarea',
          'description' => __('This controls the description which the user sees during checkout.', 'woocommerce_plugnpay_ss2'),
          'default'     => __('Pay securely online through PlugnPay Secure Servers.', 'woocommerce_plugnpay_ss2'),
          'desc_tip'    => true,
        ),
        'section_gateway' => array(
          'title'       => __('Gateway account', 'woocommerce_plugnpay_ss2'),
          'type'        => 'title',
          'class'       => 'plugnpay-ss2-settings-section',
          'description' => __('PlugnPay account used for this store, and how approved payments are captured.', 'woocommerce_plugnpay_ss2'),
        ),
        'gateway_account' => array(
          'title'       => __('Gateway Username', 'woocommerce_plugnpay_ss2'),
          'type'        => 'text',
          'description' => __('Username issued by PlugnPay at time of sign up.', 'woocommerce_plugnpay_ss2'),
        ),
        'cards_allowed' => array(
          'title'       => __('Card Types Allowed', 'woocommerce_plugnpay_ss2'),
          'type'        => 'text',
          'description' => __('Card types you are allowed to accept. Refer to the payment method specifications for possible values.', 'woocommerce_plugnpay_ss2'),
          'default'     => __('Visa,Mastercard,Amex,Discover', 'woocommerce_plugnpay_ss2'),
          'desc_tip'    => true,
        ),
        'post_auth' => array(
          'title'       => __('Transaction Settlement', 'woocommerce_plugnpay_ss2'),
          'type'        => 'select',
          'options'     => array(
            'yes' => __('Authorize and Settle', 'woocommerce_plugnpay_ss2'),
            'no'  => __('Authorize Only', 'woocommerce_plugnpay_ss2'),
          ),
          'description' => __("If you are not sure what to use, set to 'Authorize and Settle'.", 'woocommerce_plugnpay_ss2'),
          'desc_tip'    => true,
        ),
        'section_messages' => array(
          'title'       => __('Customer messages', 'woocommerce_plugnpay_ss2'),
          'type'        => 'title',
          'class'       => 'plugnpay-ss2-settings-section',
          'description' => __('Shown to the shopper after payment succeeds or fails.', 'woocommerce_plugnpay_ss2'),
        ),
        'success_message' => array(
          'title'       => __('Transaction Success Message', 'woocommerce_plugnpay_ss2'),
          'type'        => 'textarea',
          'description' => __('Message to be displayed on successful transaction.', 'woocommerce_plugnpay_ss2'),
          'default'     => __('Your payment has been processed successfully.', 'woocommerce_plugnpay_ss2'),
          'desc_tip'    => true,
        ),
        'failed_message' => array(
          'title'       => __('Transaction Failed Message', 'woocommerce_plugnpay_ss2'),
          'type'        => 'textarea',
          'description' => __('Message to be displayed on failed transaction.', 'woocommerce_plugnpay_ss2'),
          'default'     => __('Your transaction has been declined.', 'woocommerce_plugnpay_ss2'),
          'desc_tip'    => true,
        ),
        'section_authhash' => array(
          'title'       => __('Authorization Verification Hash (SHA-256)', 'woocommerce_plugnpay_ss2'),
          'type'        => 'title',
          'class'       => 'plugnpay-ss2-settings-section',
          'description' => __('Required. Enable this in both the module and PlugnPay Merchant Admin → Security Administration. The key and fieldset must match.', 'woocommerce_plugnpay_ss2'),
        ),
        'authhash' => array(
          'title'       => __('Authorization Hash', 'woocommerce_plugnpay_ss2'),
          'type'        => 'checkbox',
          'label'       => __('Enable Authorization Verification Hash (SHA-256). Required.', 'woocommerce_plugnpay_ss2'),
          'default'     => 'yes',
        ),
        'authhash_key' => array(
          'title'       => __('Authorization Hash Key', 'woocommerce_plugnpay_ss2'),
          'type'        => 'password',
          'description' => __('Required. Leave blank to keep the current key. If using Divert Currency, list each currency with its associated key [i.e. USD:key1,BBD:key2,CAD:key3].', 'woocommerce_plugnpay_ss2'),
          'default'     => '',
          'custom_attributes' => array(
            'autocomplete' => 'new-password',
          ),
        ),
        'authhash_fields' => array(
          'title'       => __('Authorization Hash Fields', 'woocommerce_plugnpay_ss2'),
          'type'        => 'select',
          'options'     => array(
            '1' => 'publisher-name',
            '2' => 'card-amount,publisher-name',
            '3' => 'acct_code,card-amount,publisher-name',
          ),
          'description' => __('Fieldset to use with authhash validation. Must configure your PlugnPay account to match.', 'woocommerce_plugnpay_ss2'),
          'default'     => '3',
          'desc_tip'    => true,
        ),
        'section_response_hash' => array(
          'title'       => __('Response Verification Hash (MD5)', 'woocommerce_plugnpay_ss2'),
          'type'        => 'title',
          'class'       => 'plugnpay-ss2-settings-section',
          'description' => __('Optional. Enable in both the module and PlugnPay Security Administration. Selected fields are hashed in alphabetical order.', 'woocommerce_plugnpay_ss2'),
        ),
        'response_hash' => array(
          'title'       => __('Response Verification Hash', 'woocommerce_plugnpay_ss2'),
          'type'        => 'checkbox',
          'label'       => __('Enable Response Verification Hash (MD5).', 'woocommerce_plugnpay_ss2'),
          'default'     => 'no',
        ),
        'response_hash_key' => array(
          'title'       => __('Response Verification Hash Key', 'woocommerce_plugnpay_ss2'),
          'type'        => 'password',
          'description' => __('Required when enabled. Leave blank to keep the current key.', 'woocommerce_plugnpay_ss2'),
          'default'     => '',
          'custom_attributes' => array(
            'autocomplete' => 'new-password',
          ),
        ),
        'response_hash_fields' => array(
          'title'       => __('Response Verification Hash Fields', 'woocommerce_plugnpay_ss2'),
          'type'        => 'select',
          'options'     => array(
            '1' => 'publisher-name',
            '2' => 'card-amount, publisher-name',
            '3' => 'FinalStatus, card-amount, publisher-name',
            '4' => 'FinalStatus, card-amount, currency, orderID, publisher-name',
            '5' => 'FinalStatus, orderID, card-amount, publisher-name',
          ),
          'description' => __('Fieldset to use with response hash validation. Selected fields are hashed in alphabetical order. Must configure your PlugnPay account to match.', 'woocommerce_plugnpay_ss2'),
          'default'     => '3',
          'desc_tip'    => true,
        ),
        'section_optional' => array(
          'title'       => __('Optional features', 'woocommerce_plugnpay_ss2'),
          'type'        => 'title',
          'class'       => 'plugnpay-ss2-settings-section',
          'description' => __('Giftcard split payments and currency diversion require matching PlugnPay account features.', 'woocommerce_plugnpay_ss2'),
        ),
        'giftcard_allow' => array(
          'title'   => __('Giftcard Acceptance', 'woocommerce_plugnpay_ss2'),
          'type'    => 'checkbox',
          'label'   => __('Enable to allow Giftcard Split Payments. Merchant Processor Giftcard ability required.', 'woocommerce_plugnpay_ss2'),
          'default' => 'no',
        ),
        'divert_currency' => array(
          'title'       => __('Divert Currency', 'woocommerce_plugnpay_ss2'),
          'type'        => 'checkbox',
          'label'       => __('Enable to divert payments for specific currencies to another gateway account.', 'woocommerce_plugnpay_ss2'),
          'default'     => 'no',
        ),
        'divert_accounts' => array(
          'title'       => __('Diverted Accounts', 'woocommerce_plugnpay_ss2'),
          'type'        => 'text',
          'description' => __('List currency code and username to divert specific payments to. [i.e. USD:username1,BBD:username2,CAD:username3] Currency codes not listed will use default Gateway Account.', 'woocommerce_plugnpay_ss2'),
        ),
      );
    }

    /**
     * Do not echo stored secrets back into the admin HTML.
     */
    public function generate_password_html($key, $data) {
      $field_key = $this->get_field_key($key);
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

      $has_value = $this->get_option($key) !== '';

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
              placeholder="<?php echo $has_value ? esc_attr__('(unchanged)', 'woocommerce_plugnpay_ss2') : ''; ?>"
              <?php echo implode(' ', $custom_attributes); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
              <?php disabled($data['disabled']); ?>
            />
            <?php echo $this->get_description_html($data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          </fieldset>
        </td>
      </tr>
      <?php
      return ob_get_clean();
    }

    public function validate_authhash_key_field($key, $value) {
      return $this->persist_encrypted_secret($key, $value);
    }

    public function validate_response_hash_key_field($key, $value) {
      return $this->persist_encrypted_secret($key, $value);
    }

    /**
     * Keep the stored secret when the password field is submitted empty.
     * Encrypt new values. Migrate legacy plaintext on save.
     *
     * @param string $key
     * @param mixed  $value
     * @return string
     */
    private function persist_encrypted_secret($key, $value) {
      $value = is_string($value) ? trim(wp_unslash($value)) : '';

      if ($value !== '') {
        return plugnpay_ss2_encrypt_secret($value);
      }

      $existing = $this->get_option($key);
      if ($existing === '') {
        return '';
      }

      if (plugnpay_ss2_is_encrypted_secret($existing)) {
        return $existing;
      }

      return plugnpay_ss2_encrypt_secret($existing);
    }

    public function admin_options() {
      echo '<h3>' . esc_html__('PlugnPay SSv2 Payment Gateway', 'woocommerce_plugnpay_ss2') . '</h3>';
      echo '<p>' . esc_html__('PlugnPay is a popular payment gateway for online payment processing.', 'woocommerce_plugnpay_ss2') . '</p>';
      echo '<table class="form-table">';
      $this->generate_settings_html();
      echo '</table>';
    }

    public function admin_security_notices() {
      if ($this->get_option('enabled') !== 'yes' || !current_user_can('manage_woocommerce')) {
        return;
      }

      if (!$this->is_authhash_configured()) {
        echo '<div class="error"><p>';
        echo esc_html__('PlugnPay SSv2: Authorization Verification Hash and key are required before checkout can proceed. Enable the matching settings in PlugnPay Merchant Admin → Security Administration.', 'woocommerce_plugnpay_ss2');
        echo '</p></div>';
      }

      if ($this->get_option('response_hash') === 'yes' && !$this->is_response_hash_configured()) {
        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('PlugnPay SSv2: Response Verification Hash is enabled but the key is not set. Callback hash validation will fail until a key is configured in PlugnPay Merchant Admin → Security Administration.', 'woocommerce_plugnpay_ss2');
        echo '</p></div>';
      }

      if (!$this->storefront_is_secure()) {
        echo '<div class="error"><p>';
        echo esc_html__('PlugnPay SSv2: HTTPS is required on the storefront for checkout and payment return URLs.', 'woocommerce_plugnpay_ss2');
        echo '</p></div>';
      }

      if (version_compare(PHP_VERSION, '8.3', '<')) {
        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('PlugnPay SSv2: PHP 8.3 or higher is recommended. PHP 8.2 security support ends 31 Dec 2026.', 'woocommerce_plugnpay_ss2');
        echo '</p></div>';
      }
    }

    public function payment_fields() {
      if ($this->description) {
        echo '<div class="plugnpay-ss2-description">' . wp_kses_post(wpautop(wptexturize($this->description))) . '</div>';
      }
    }

    public function thankyou_page($order_id) {
      // Intentionally empty.
    }

    public function receipt_page($order_id) {
      $order = wc_get_order($order_id);

      if (!$order) {
        echo '<p>' . esc_html__('Invalid order.', 'woocommerce_plugnpay_ss2') . '</p>';
        return;
      }

      echo '<p>' . esc_html__('Thank you for your order, please click the button below to pay with PlugnPay.', 'woocommerce_plugnpay_ss2') . '</p>';
      echo $this->generate_plugnpay_form($order); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function process_payment($order_id) {
      $order = wc_get_order($order_id);

      if (!$order) {
        wc_add_notice(__('Invalid order.', 'woocommerce_plugnpay_ss2'), 'error');
        return array('result' => 'failure');
      }

      if (empty($this->settings['gateway_account'])) {
        wc_add_notice(__('Payment gateway is not configured.', 'woocommerce_plugnpay_ss2'), 'error');
        return array('result' => 'failure');
      }

      if (!$this->is_authhash_configured()) {
        wc_add_notice(__('Payment gateway is not configured for Authorization Hash.', 'woocommerce_plugnpay_ss2'), 'error');
        return array('result' => 'failure');
      }

      if (!$this->storefront_is_secure()) {
        wc_add_notice(__('Secure HTTPS checkout is required.', 'woocommerce_plugnpay_ss2'), 'error');
        return array('result' => 'failure');
      }

      return array(
        'result'   => 'success',
        'redirect' => $order->get_checkout_payment_url(true),
      );
    }

    /**
     * Apply a hidden PlugnPay POST on a real WooCommerce page, then let the
     * theme render. PlugnPay captures that HTML and echoes it to the shopper.
     */
    public function handle_plugnpay_hidden_post() {
      if (!$this->is_plugnpay_hidden_post()) {
        return;
      }

      if (!function_exists('is_checkout') || !is_checkout()) {
        return;
      }

      $order_id = absint(wp_unslash($_POST['pt_order_classifier']));
      $order = $order_id ? wc_get_order($order_id) : false;
      if (!$order) {
        return;
      }

      $source_ip = $this->get_callback_source_ip();
      if (!plugnpay_ss2_is_allowed_callback_ip($source_ip)) {
        $this->log_callback_event(
          sprintf('Ignored hidden POST from unauthorized IP: %s', $source_ip === '' ? 'unknown' : $source_ip)
        );
        return;
      }

      $success = $this->apply_plugnpay_callback($order);
      if (!$success) {
        wc_add_notice($this->settings['failed_message'], 'error');
      }
    }

    /**
     * wc-api fallback for in-flight checkouts that still post here.
     *
     * Must return a 200 storefront HTML document. PlugnPay captures the body
     * and echoes it in the shopper's browser; redirects and empty bodies
     * become a blank or unthemed response page.
     */
    public function check_plugnpay_response() {
      $order_id = isset($_POST['pt_order_classifier']) ? absint(wp_unslash($_POST['pt_order_classifier'])) : 0;
      $order = $order_id ? wc_get_order($order_id) : false;
      $success = false;

      $source_ip = $this->get_callback_source_ip();
      if ($order && plugnpay_ss2_is_allowed_callback_ip($source_ip)) {
        $success = $this->apply_plugnpay_callback($order);
      }
      elseif ($order) {
        $this->log_callback_event(
          sprintf('Ignored wc-api POST from unauthorized IP: %s', $source_ip === '' ? 'unknown' : $source_ip)
        );
        $success = in_array($order->get_status(), array('processing', 'completed'), true);
      }

      if ($order && !$success) {
        wc_add_notice($this->settings['failed_message'], 'error');
      }

      if ($order) {
        $this->render_storefront_capture($order, $success);
      }

      status_header(200);
      echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>';
      echo '<p>' . esc_html__('Unable to locate the order.', 'woocommerce_plugnpay_ss2') . '</p>';
      echo '</body></html>';
      exit;
    }

    /**
     * @return bool
     */
    private function is_plugnpay_hidden_post() {
      return isset($_POST['pt_order_classifier'], $_POST['pi_response_status']);
    }

    /**
     * Render the WooCommerce checkout thank-you or pay page so PlugnPay can
     * capture a full themed document.
     *
     * @param WC_Order $order
     * @param bool     $success
     */
    private function render_storefront_capture($order, $success) {
      global $wp, $wp_query, $post;

      $checkout_page_id = function_exists('wc_get_page_id') ? wc_get_page_id('checkout') : 0;
      if ($checkout_page_id < 1) {
        status_header(200);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>';
        echo '<p>' . esc_html($success ? $this->settings['success_message'] : $this->settings['failed_message']) . '</p>';
        echo '</body></html>';
        exit;
      }

      status_header(200);
      nocache_headers();

      if (isset($wp->query_vars['wc-api'])) {
        unset($wp->query_vars['wc-api']);
      }

      $endpoint = plugnpay_ss2_hidden_post_endpoint($success);
      unset($wp->query_vars['order-received'], $wp->query_vars['order-pay']);
      $wp->query_vars['page_id'] = $checkout_page_id;
      $wp->query_vars[$endpoint] = $order->get_id();
      $wp->query_vars['key'] = $order->get_order_key();

      $checkout_page = get_post($checkout_page_id);
      $wp_query->queried_object = $checkout_page;
      $wp_query->queried_object_id = $checkout_page_id;
      $wp_query->posts = $checkout_page ? array($checkout_page) : array();
      $wp_query->post_count = $checkout_page ? 1 : 0;
      $wp_query->found_posts = $wp_query->post_count;
      $wp_query->is_page = true;
      $wp_query->is_singular = true;
      $wp_query->is_home = false;
      $wp_query->is_404 = false;
      $wp_query->query_vars = array_merge($wp_query->query_vars, $wp->query_vars);

      $post = $checkout_page;
      if ($post) {
        setup_postdata($post);
      }

      add_filter('woocommerce_is_checkout', '__return_true', 20);
      if ($success) {
        add_filter('woocommerce_is_order_received_page', '__return_true', 20);
      }

      $template = get_page_template();
      if (!is_string($template) || $template === '' || !file_exists($template)) {
        get_header('shop');
        echo '<div class="woocommerce">';
        if ($success) {
          wc_get_template('checkout/thankyou.php', array('order' => $order));
        }
        else {
          wc_print_notices();
          echo '<p>' . esc_html($this->settings['failed_message']) . '</p>';
        }
        echo '</div>';
        get_footer('shop');
        exit;
      }

      include $template;
      exit;
    }

    /**
     * Apply a verified PlugnPay hidden callback to the order.
     *
     * @param WC_Order $order
     * @return bool
     */
    private function apply_plugnpay_callback($order) {
      $response_code   = isset($_POST['pi_response_code']) ? sanitize_text_field(wp_unslash($_POST['pi_response_code'])) : '';
      $response_status = isset($_POST['pi_response_status']) ? sanitize_text_field(wp_unslash($_POST['pi_response_status'])) : '';
      $transaction_id  = isset($_POST['pt_order_id']) ? sanitize_text_field(wp_unslash($_POST['pt_order_id'])) : '';

      if ($response_code === '' || $response_status !== 'success') {
        $this->fail_unpaid_order($order);
        return false;
      }

      $order_status = $order->get_status();

      if (in_array($order_status, array('processing', 'completed'), true)) {
        return true;
      }

      if (!in_array($order_status, array('pending', 'on-hold', 'failed'), true)) {
        return false;
      }

      if (!$this->callback_matches_order($order, $transaction_id)) {
        $this->fail_unpaid_order($order);
        return false;
      }

      $order->payment_complete($transaction_id);
      $order->add_order_note(
        sprintf(
          /* translators: %s: PlugnPay transaction reference */
          __('PlugnPay payment successful. Ref Number/Transaction ID: %s', 'woocommerce_plugnpay_ss2'),
          $transaction_id
        )
      );
      $order->add_order_note($this->settings['success_message']);

      if (WC()->cart) {
        WC()->cart->empty_cart();
      }

      return true;
    }

    /**
     * Connecting IP for callback allowlisting.
     *
     * Uses REMOTE_ADDR only. Do not trust client-supplied forwarding headers.
     *
     * @return string
     */
    private function get_callback_source_ip() {
      $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

      return apply_filters('woocommerce_plugnpay_ss2_callback_source_ip', $ip);
    }

    /**
     * @param string $message
     */
    private function log_callback_event($message) {
      if (!function_exists('wc_get_logger')) {
        return;
      }

      wc_get_logger()->warning($message, array('source' => 'plugnpay-ss2'));
    }

    /**
     * Validate payment method, amount, currency, and optional response hash.
     *
     * @param WC_Order $order
     * @param string   $transaction_id
     * @return bool
     */
    private function callback_matches_order($order, $transaction_id) {
      if ($order->get_payment_method() !== $this->id) {
        $this->log_callback_event('Rejected callback: payment method mismatch for order ' . $order->get_id());
        return false;
      }

      $posted_amount = isset($_POST['pt_transaction_amount']) ? sanitize_text_field(wp_unslash($_POST['pt_transaction_amount'])) : '';
      if (!plugnpay_ss2_amounts_match($posted_amount, $this->get_order_amount($order))) {
        $this->log_callback_event('Rejected callback: amount mismatch for order ' . $order->get_id());
        return false;
      }

      $posted_currency = isset($_POST['pt_currency']) ? sanitize_text_field(wp_unslash($_POST['pt_currency'])) : '';
      if (!plugnpay_ss2_currencies_match($posted_currency, $order->get_currency())) {
        $this->log_callback_event('Rejected callback: currency mismatch for order ' . $order->get_id());
        return false;
      }

      $response_key = $this->get_plain_secret('response_hash_key');
      if ($this->is_response_hash_configured()) {
        $posted_hash = isset($_POST['pt_transaction_response_hash']) ? sanitize_text_field(wp_unslash($_POST['pt_transaction_response_hash'])) : '';
        $posted_fields = array(
          'pi_response_status'   => isset($_POST['pi_response_status']) ? sanitize_text_field(wp_unslash($_POST['pi_response_status'])) : '',
          'pt_transaction_amount' => $posted_amount,
          'pt_currency'          => $posted_currency,
          'pt_order_id'          => $transaction_id,
          'pt_gateway_account'   => isset($_POST['pt_gateway_account']) ? sanitize_text_field(wp_unslash($_POST['pt_gateway_account'])) : '',
        );
        $response_fields = isset($this->settings['response_hash_fields']) ? $this->settings['response_hash_fields'] : '3';

        if (!plugnpay_ss2_response_hash_valid($posted_hash, $response_key, $response_fields, $posted_fields)) {
          $this->log_callback_event('Rejected callback: response hash mismatch for order ' . $order->get_id());
          return false;
        }
      }

      return true;
    }

    /**
     * @param WC_Order $order
     */
    private function fail_unpaid_order($order) {
      if (!in_array($order->get_status(), array('processing', 'completed'), true)) {
        $order->update_status('failed', $this->settings['failed_message']);
      }
    }

    /**
     * Format the order total for gateway submission.
     */
    private function get_order_amount($order) {
      return plugnpay_ss2_format_amount($order->get_total());
    }

    /**
     * @param string $setting_key
     * @return string
     */
    private function get_plain_secret($setting_key) {
      $stored = isset($this->settings[$setting_key]) ? $this->settings[$setting_key] : '';
      return plugnpay_ss2_decrypt_secret($stored);
    }

    /**
     * @return bool
     */
    private function is_authhash_configured() {
      return isset($this->settings['authhash'])
        && $this->settings['authhash'] === 'yes'
        && $this->get_plain_secret('authhash_key') !== '';
    }

    /**
     * @return bool
     */
    private function is_response_hash_configured() {
      return isset($this->settings['response_hash'])
        && $this->settings['response_hash'] === 'yes'
        && $this->get_plain_secret('response_hash_key') !== '';
    }

    /**
     * HTTPS is required except on local/development environments.
     *
     * @return bool
     */
    private function storefront_is_secure() {
      if (is_ssl()) {
        return true;
      }

      if (function_exists('wp_get_environment_type') && in_array(wp_get_environment_type(), array('local', 'development'), true)) {
        return true;
      }

      return false;
    }

    /**
     * Resolve the authhash key for the current order currency.
     */
    private function get_authhash_key_for_order($order, $gateway_account) {
      $authhash_key = $this->get_plain_secret('authhash_key');

      if ($this->settings['divert_currency'] !== 'yes' || strpos($authhash_key, ',') === false) {
        return $authhash_key;
      }

      $hash_list = explode(',', $authhash_key);
      foreach ($hash_list as $entry) {
        $parts = explode(':', $entry, 2);
        if (count($parts) !== 2) {
          continue;
        }

        list($alt_currency, $alt_hash_key) = $parts;
        if (strtolower($alt_currency) === strtolower($order->get_currency())) {
          return $alt_hash_key;
        }
      }

      return $authhash_key;
    }

    /**
     * Generate the redirect form posted to PlugnPay Smart Screens.
     */
    public function generate_plugnpay_form($order) {
      if (!$order instanceof WC_Order) {
        $order = wc_get_order($order);
      }

      if (!$order) {
        return '<p>' . esc_html__('Invalid order.', 'woocommerce_plugnpay_ss2') . '</p>';
      }

      if (!$this->is_authhash_configured()) {
        return '<p>' . esc_html__('Payment gateway is not configured for Authorization Hash.', 'woocommerce_plugnpay_ss2') . '</p>';
      }

      $order_id = $order->get_id();
      $gateway_account = $this->settings['gateway_account'];
      $currency_code = $order->get_currency();
      $order_amount = $this->get_order_amount($order);

      if ($this->settings['divert_currency'] === 'yes') {
        $divert_list = explode(',', $this->settings['divert_accounts']);
        foreach ($divert_list as $entry) {
          $parts = explode(':', $entry, 2);
          if (count($parts) !== 2) {
            continue;
          }

          list($alt_currency, $alt_merchant) = $parts;
          if (strtolower($alt_currency) === strtolower($order->get_currency())) {
            $currency_code = $alt_currency;
            $gateway_account = $alt_merchant;
            break;
          }
        }
      }

      $plugnpay_args = array(
        'pt_client_identifier'            => 'woocommerce_ss2',
        'pt_gateway_account'              => strtolower($gateway_account),
        'pb_cards_allowed'                => $this->settings['cards_allowed'],
        'pt_transaction_amount'           => $order_amount,
        'pt_currency'                     => strtoupper($currency_code),
        'pt_order_classifier'             => $order_id,
        'pt_account_code_1'               => $order_id,
        'pb_transition_type'              => 'hidden',
        'pb_success_url'                  => $order->get_checkout_order_received_url(),
        'pb_bad_card_url'                 => $order->get_checkout_payment_url(true),
        'pb_problem_url'                  => $order->get_checkout_payment_url(true),
        'pd_collect_company'              => 'yes',
        'pt_billing_name'                 => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
        'pt_billing_company'              => $order->get_billing_company(),
        'pt_billing_address_1'            => $order->get_billing_address_1(),
        'pt_billing_address_2'            => $order->get_billing_address_2(),
        'pt_billing_country'              => $order->get_billing_country(),
        'pt_billing_state'                => $order->get_billing_state(),
        'pt_billing_city'                 => $order->get_billing_city(),
        'pt_billing_postal_code'          => $order->get_billing_postcode(),
        'pt_billing_phone_number'         => $order->get_billing_phone(),
        'pt_billing_email_address'        => $order->get_billing_email(),
        'pd_collect_shipping_information' => 'no',
        'pt_shipping_name'                => trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()),
        'pt_shipping_company'             => $order->get_shipping_company(),
        'pt_shipping_address_1'           => $order->get_shipping_address_1(),
        'pt_shipping_address_2'           => $order->get_shipping_address_2(),
        'pt_shipping_country'             => $order->get_shipping_country(),
        'pt_shipping_state'               => $order->get_shipping_state(),
        'pt_shipping_city'                => $order->get_shipping_city(),
        'pt_shipping_postal_code'         => $order->get_shipping_postcode(),
      );

      $plugnpay_args['pb_post_auth'] = ($this->settings['post_auth'] === 'yes') ? 'yes' : 'no';

      $string_fields = plugnpay_ss2_authhash_string_fields(
        $this->settings['authhash_fields'],
        $order_id,
        $order_amount,
        $gateway_account
      );
      $timestamp = gmdate('YmdHis', time());
      $authhash_key = $this->get_authhash_key_for_order($order, $gateway_account);
      $hash_string = $authhash_key . $timestamp . $string_fields;
      $plugnpay_args['pt_transaction_hash'] = plugnpay_ss2_transaction_hash($hash_string);
      $plugnpay_args['pt_transaction_time'] = $timestamp;

      if ($this->settings['giftcard_allow'] === 'yes') {
        $plugnpay_args['pd_transaction_payment_type'] = 'mpgiftcard';
      }

      $hidden_fields = array();
      foreach ($plugnpay_args as $key => $value) {
        $hidden_fields[] = sprintf(
          '<input type="hidden" name="%s" value="%s" />',
          esc_attr($key),
          esc_attr($value)
        );
      }

      $loader_url = esc_url(WC()->plugin_url() . '/assets/images/ajax-loader.gif');
      $redirect_message = esc_js(__('Thank you for your order. We are now redirecting you to PlugnPay to make payment.', 'woocommerce_plugnpay_ss2'));

      return '<form action="https://pay1.plugnpay.com/pay/" method="post" id="plugnpay_payment_form">'
        . implode('', $hidden_fields)
        . '<input type="submit" class="button" id="submit_plugnpay_payment_form" value="' . esc_attr__('Pay via PlugnPay', 'woocommerce_plugnpay_ss2') . '" /> '
        . '<a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . esc_html__('Cancel order & restore cart', 'woocommerce_plugnpay_ss2') . '</a>'
        . '<script type="text/javascript">
          jQuery(function($) {
            $("body").block({
              message: "<img src=\"' . $loader_url . '\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />' . $redirect_message . '",
              overlayCSS: {
                background: "#ccc",
                opacity: 0.6
              },
              css: {
                padding: 20,
                textAlign: "center",
                color: "#555",
                border: "3px solid #aaa",
                backgroundColor: "#fff",
                cursor: "wait",
                lineHeight: "32px"
              }
            });
            $("#submit_plugnpay_payment_form").click();
          });
        </script>'
        . '</form>';
    }
  }

  function woocommerce_add_plugnpay_ss2_gateway($methods) {
    $methods[] = 'WC_Tech_Autho';
    return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'woocommerce_add_plugnpay_ss2_gateway');
}

function woocommerce_plugnpay_ss2_version_notice() {
  echo '<div class="error"><p>';
  echo esc_html__('PlugnPay SSv2 requires WooCommerce 8.0 or higher.', 'woocommerce_plugnpay_ss2');
  echo '</p></div>';
}

function woocommerce_plugnpay_ss2_php_notice() {
  echo '<div class="error"><p>';
  echo esc_html__('PlugnPay SSv2 requires PHP 8.2 or higher.', 'woocommerce_plugnpay_ss2');
  echo '</p></div>';
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'plugnpay_ss2_action_links');

function plugnpay_ss2_action_links($links) {
  $gateway_links = array(
    '<a href="https://www.gatewaystatus.com/" target="_blank" rel="noopener noreferrer">' . esc_html__('Gateway Status', 'woocommerce_plugnpay_ss2') . '</a>',
    '<a href="https://helpdesk.plugnpay.com/" target="_blank" rel="noopener noreferrer">' . esc_html__('Online Helpdesk', 'woocommerce_plugnpay_ss2') . '</a>',
    '<a href="https://pay1.plugnpay.com/admin/" target="_blank" rel="noopener noreferrer">' . esc_html__('Merchant Admin', 'woocommerce_plugnpay_ss2') . '</a>',
  );

  return array_merge($links, $gateway_links);
}

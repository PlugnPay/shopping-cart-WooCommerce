<?php
/**
 * Plugin Name: PlugnPay SSv2 Payment Gateway For WooCommerce
 * Plugin URI: https://github.com/PlugnPay/shopping-cart-WooCommerce
 * Description: Extends WooCommerce to Process Smart Screens v2 Payments with PlugnPay gateway.
 * Version: 1.1.8.5
 * Author: PlugnPay
 * Author URI: http://www.plugnpay.com
 * Text Domain: woocommerce_plugnpay_ss2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * License: GPL2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

defined('ABSPATH') || exit;

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
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
      add_action('woocommerce_receipt_plugnpay', array($this, 'receipt_page'));
      add_action('woocommerce_thankyou_plugnpay', array($this, 'thankyou_page'));
      add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_assets'));
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
        '1.1.8.5'
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
          $card = trim($card);
          if ($card === '') {
            continue;
          }

          $img_url = $icon_path . strtolower($card) . '.png';
          $icons .= '<img src="' . esc_url($img_url) . '" alt="' . esc_attr(ucwords($card)) . '" />';
        }

        if ($icons !== '') {
          $icons = '<span class="plugnpay-ss2-card-icons">' . $icons . '</span>';
        }
      }

      return apply_filters('woocommerce_gateway_icon', $icons, $this->id);
    }

    public function init_form_fields() {
      $this->form_fields = array(
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
        ),
        'description' => array(
          'title'       => __('Description:', 'woocommerce_plugnpay_ss2'),
          'type'        => 'textarea',
          'description' => __('This controls the description which the user sees during checkout.', 'woocommerce_plugnpay_ss2'),
          'default'     => __('Pay securely online through PlugnPay Secure Servers.', 'woocommerce_plugnpay_ss2'),
        ),
        'gateway_account' => array(
          'title'       => __('Gateway Username', 'woocommerce_plugnpay_ss2'),
          'type'        => 'text',
          'description' => __('Username issued by PlugnPay at time of sign up.'),
        ),
        'cards_allowed' => array(
          'title'       => __('Card Types Allowed', 'woocommerce_plugnpay_ss2'),
          'type'        => 'text',
          'description' => __('Card types you are allowed to accept. Refer to the payment method specifications for possible values.'),
          'default'     => __('Visa,Mastercard,Amex,Discover', 'woocommerce_plugnpay_ss2'),
        ),
        'success_message' => array(
          'title'       => __('Transaction Success Message', 'woocommerce_plugnpay_ss2'),
          'type'        => 'textarea',
          'description' => __('Message to be displayed on successful transaction.', 'woocommerce_plugnpay_ss2'),
          'default'     => __('Your payment has been processed successfully.', 'woocommerce_plugnpay_ss2'),
        ),
        'failed_message' => array(
          'title'       => __('Transaction Failed Message', 'woocommerce_plugnpay_ss2'),
          'type'        => 'textarea',
          'description' => __('Message to be displayed on failed transaction.', 'woocommerce_plugnpay_ss2'),
          'default'     => __('Your transaction has been declined.', 'woocommerce_plugnpay_ss2'),
        ),
        'post_auth' => array(
          'title'       => __('Transaction Settlement', 'woocommerce_plugnpay_ss2'),
          'type'        => 'select',
          'options'     => array(
            'yes' => __('Authorize and Settle', 'woocommerce_plugnpay_ss2'),
            'no'  => __('Authorize Only', 'woocommerce_plugnpay_ss2'),
          ),
          'description' => __("Transaction Settlement. If you are not sure what to use set to 'Authorize and Settle'", 'woocommerce_plugnpay_ss2'),
        ),
        'authhash' => array(
          'title'       => __('Authorization Hash', 'woocommerce_plugnpay_ss2'),
          'type'        => 'checkbox',
          'label'       => __('Enable Authorization Verification Hash. Strongly recommended for security. Must match your PlugnPay account settings.', 'woocommerce_plugnpay_ss2'),
          'default'     => 'no',
        ),
        'authhash_key' => array(
          'title'       => __('Authorization Hash Key', 'woocommerce_plugnpay_ss2'),
          'type'        => 'text',
          'description' => __('If using Divert Currency, list each currency with its associated key [i.e. USD:key1,BBD:key2,CAD:key3]. Must configure your PlugnPay account to match.', 'woocommerce_plugnpay_ss2'),
          'default'     => '',
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
          'description' => __('Enable to divert currency to alt account. Multiple gateway accounts required, each setup for a different currency.', 'woocommerce_plugnpay_ss2'),
          'default'     => 'no',
        ),
        'divert_accounts' => array(
          'title'       => __('Diverted Accounts', 'woocommerce_plugnpay_ss2'),
          'type'        => 'text',
          'description' => __('List currency code and username to divert specific payments to. [i.e. USD:username1,BBD:username2,CAD:username3] Currency codes not listed will use default Gateway Account.', 'woocommerce_plugnpay_ss2'),
        ),
      );
    }

    public function admin_options() {
      echo '<h3>' . esc_html__('PlugnPay SSv2 Payment Gateway', 'woocommerce_plugnpay_ss2') . '</h3>';
      echo '<p>' . esc_html__('PlugnPay is a popular payment gateway for online payment processing.', 'woocommerce_plugnpay_ss2') . '</p>';
      echo '<table class="form-table">';
      $this->generate_settings_html();
      echo '</table>';
    }

    public function payment_fields() {
      if ($this->description) {
        echo '<div class="plugnpay-ss2-description">' . wpautop(wptexturize($this->description)) . '</div>';
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
      echo $this->generate_plugnpay_form($order);
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

      return array(
        'result'   => 'success',
        'redirect' => $order->get_checkout_payment_url(true),
      );
    }

    /**
     * Handle the customer return from PlugnPay Smart Screens.
     *
     * Callback signature verification is intentionally deferred to a future release.
     */
    public function check_plugnpay_response() {
      if (empty($_POST)) {
        wp_safe_redirect(wc_get_checkout_url());
        exit;
      }

      $order_id = isset($_POST['pt_order_classifier']) ? absint(wp_unslash($_POST['pt_order_classifier'])) : 0;
      $order = wc_get_order($order_id);

      if (!$order) {
        wp_safe_redirect(wc_get_checkout_url());
        exit;
      }

      $response_code   = isset($_POST['pi_response_code']) ? sanitize_text_field(wp_unslash($_POST['pi_response_code'])) : '';
      $response_status = isset($_POST['pi_response_status']) ? sanitize_text_field(wp_unslash($_POST['pi_response_status'])) : '';
      $transaction_id  = isset($_REQUEST['pt_order_id']) ? sanitize_text_field(wp_unslash($_REQUEST['pt_order_id'])) : '';

      $this->msg['class']   = 'error';
      $this->msg['message'] = $this->settings['failed_message'];
      $payment_success      = false;

      if ($response_code !== '' && $response_status === 'success') {
        $order_status = $order->get_status();

        if (in_array($order_status, array('processing', 'completed'), true)) {
          $payment_success      = true;
          $this->msg['class']   = 'success';
          $this->msg['message'] = $this->settings['success_message'];
        }
        elseif (in_array($order_status, array('pending', 'on-hold', 'failed'), true)) {
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

          $payment_success      = true;
          $this->msg['class']   = 'success';
          $this->msg['message'] = $this->settings['success_message'];
        }
      }
      else {
        if (!in_array($order->get_status(), array('processing', 'completed'), true)) {
          $order->update_status('failed', $this->settings['failed_message']);
        }
      }

      if ($payment_success) {
        wp_safe_redirect($order->get_checkout_order_received_url());
      }
      else {
        wc_add_notice($this->settings['failed_message'], 'error');
        wp_safe_redirect($order->get_checkout_payment_url(true));
      }

      exit;
    }

    /**
     * Format the order total for gateway submission.
     */
    private function get_order_amount($order) {
      return wc_format_decimal($order->get_total(), 2);
    }

    /**
     * Resolve the authhash key for the current order currency.
     */
    private function get_authhash_key_for_order($order, $gateway_account) {
      $authhash_key = $this->settings['authhash_key'];

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

      $success_url = add_query_arg(
        array(),
        WC()->api_request_url(get_class($this))
      );

      $plugnpay_args = array(
        'pt_client_identifier'            => 'woocommerce_ss2',
        'pt_gateway_account'              => strtolower($gateway_account),
        'pb_cards_allowed'                => $this->settings['cards_allowed'],
        'pt_transaction_amount'           => $order_amount,
        'pt_currency'                     => strtoupper($currency_code),
        'pt_order_classifier'             => $order_id,
        'pt_account_code_1'               => $order_id,
        'pb_transition_type'              => 'hidden',
        'pb_success_url'                  => $success_url,
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

      if ($this->settings['authhash'] === 'yes') {
        if ($this->settings['authhash_fields'] === '3') {
          $string_fields = $order_id . $order_amount . strtolower($gateway_account);
        }
        elseif ($this->settings['authhash_fields'] === '2') {
          $string_fields = $order_amount . strtolower($gateway_account);
        }
        else {
          $string_fields = strtolower($gateway_account);
        }

        $timestamp = gmdate('YmdHis', time());
        $authhash_key = $this->get_authhash_key_for_order($order, $gateway_account);
        $hash_string = $authhash_key . $timestamp . $string_fields;

        $plugnpay_args['pt_transaction_hash'] = md5($hash_string);
        $plugnpay_args['pt_transaction_time'] = $timestamp;
      }

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

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'plugnpay_ss2_action_links');

function plugnpay_ss2_action_links($links) {
  $gateway_links = array(
    '<a href="http://www.gatewaystatus.com/" target="_blank" rel="noopener noreferrer">' . esc_html__('Gateway Status', 'woocommerce_plugnpay_ss2') . '</a>',
    '<a href="https://helpdesk.plugnpay.com/" target="_blank" rel="noopener noreferrer">' . esc_html__('Online Helpdesk', 'woocommerce_plugnpay_ss2') . '</a>',
    '<a href="https://pay1.plugnpay.com/admin/" target="_blank" rel="noopener noreferrer">' . esc_html__('Merchant Admin', 'woocommerce_plugnpay_ss2') . '</a>',
  );

  return array_merge($links, $gateway_links);
}

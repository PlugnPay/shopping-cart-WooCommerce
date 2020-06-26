<?php
/*
 * Plugin Name: PlugnPay API Gift Card Payment Gateway For WooCommerce
 * Plugin URI: http://www.plugnpay.com
 * Description: Extends WooCommerce to Process API Gift Card Payments with PlugnPay gateway.
 * Version: 1.1.1
 * Author: PlugnPay
 * Author URI: http://www.plugnpay.com
 * Text Domain: woocommerce_plugnpay_api_gc
 * License: GPL2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

add_action('plugins_loaded', 'woocommerce_plugnpay_api_gc_init', 0);

function woocommerce_plugnpay_api_gc_init() {
  if (!class_exists('WC_Payment_Gateway'))
    return;

  /**
  * Localization
  **/
  load_plugin_textdomain('woocommerce_plugnpay_api_gc', false, dirname(plugin_basename(__FILE__)) . '/languages');

  /**
  * PlugnPay API Gift Card Payment Gateway class
  **/
  class WC_Plugnpay_API_GC_Gateway extends WC_Payment_Gateway {
    protected $msg = array();

    public function __construct() {
      $this->id               = 'plugnpay_api_gc';
      $this->method_title     = __('PlugnPay API GC', 'tech');
      $this->icon             = WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)) . '/images/logo.png';
      $this->has_fields       = true;
      $this->init_form_fields();
      $this->init_settings();
      $this->title            = $this->settings['title'];
      $this->description      = $this->settings['description'];
      $this->gateway_account  = $this->settings['gateway_account'];
      $this->remote_password  = $this->settings['remote_password'];
      $this->post_auth        = $this->settings['post_auth'];
      $this->success_message  = $this->settings['success_message'];
      $this->failed_message   = $this->settings['failed_message'];
      $this->msg['message']   = '';
      $this->msg['class']     = '';

      if (version_compare(WOOCOMMERCE_VERSION,'2.0.0','>=')) {
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this,'process_admin_options'));
      }
      else {
        add_action('woocommerce_update_options_payment_gateways', array(&$this,'process_admin_options'));
      }

      add_action('woocommerce_receipt_plugnpay_api_gc', array(&$this, 'receipt_page'));
      add_action('woocommerce_thankyou_plugnpay_api_gc', array(&$this, 'thankyou_page'));
    }

    function init_form_fields() {
      $this->form_fields = array(
          'enabled'         => array(
              'title'          => __('Enable/Disable', 'tech'),
              'type'           => 'checkbox',
              'label'          => __('Enable PlugnPay API GC Payment Module.', 'tech'),
              'default'        => 'no'),
          'title'           => array(
              'title'          => __('Title:', 'tech'),
              'type'           => 'text',
              'description'    => __('This controls the title which the user sees during checkout.', 'tech'),
              'default'        => __('Pay By Gift Card', 'tech')),
          'description'     => array(
              'title'          => __('Description:', 'tech'),
              'type'           => 'textarea',
              'description'    => __('This controls the description which the user sees during checkout.', 'tech'),
              'default'        => __('Pay securely with Mercury Gift Card through PlugnPay Secure Servers.', 'tech')),
          'gateway_account' => array(
              'title'          => __('Gateway Account', 'tech'),
              'type'           => 'text',
              'description'    => __('Username issued by PlugnPay at time of sign up.')),
          'remote_password' => array(
              'title'          => __('Remote Client Password', 'tech'),
              'type'           => 'text',
              'description'    =>  __('Remote Client Password is created within your PlugnPay Security Administration area.', 'tech')),
          'success_message' => array(
              'title'          => __('Transaction Success Message', 'tech'),
              'type'           => 'textarea',
              'description'    => __('Message to be displayed on successful transaction.', 'tech'),
              'default'        => __('Your payment has been processed successfully.', 'tech')),
          'failed_message'  => array(
              'title'          => __('Transaction Failed Message', 'tech'),
              'type'           => 'textarea',
              'description'    =>  __('Message to be displayed on failed transaction.', 'tech'),
              'default'        => __('Your transaction has been declined.', 'tech')),
          'post_auth'       => array(
              'title'          => __('Transaction Settlement'),
              'type'           => 'select',
              'options'        => array('yes'=>'Authorize and Settle', 'no'=>'Authorize Only'),
              'description'    => "Transaction Settlement. If you are not sure what to use set to 'Authorize and Settle'"),
          'giftcard_format' => array(
              'title'          => __('Giftcard Format', 'tech'),
              'type'           => 'select',
              'options'        => array('luhn10'=>'Perform LUHN-10-Check', 'skip'=>'Skip LUHN-10 Check'),
              'description'    => __('Specify if your MP giftcard numbers are LUHN-10 or not.', 'tech')),
       );
    }

    /**
    * Admin Panel Options
    **/
    public function admin_options() {
      echo '<h3>'.__('PlugnPay API GC Payment Gateway', 'tech').'</h3>';
      echo '<p>'.__('PlugnPay is a popular payment gateway for online payment processing').'</p>';
      echo '<table class="form-table">';
      $this->generate_settings_html();
      echo '</table>';
    }

    /**
    * Fields for PlugnPay API GC
    **/

    function payment_fields() {
      if ($this->description) {
        echo wpautop(wptexturize($this->description));
        echo '<label style="margin-right:46px; line-height:40px;">Gift Card #:</label> <input type="text" name="pnp_mpgiftcard" size="21" maxlength="20" autocomplete="off" required /><br/>';
        echo '<label style="margin-right:89px; line-height:40px;">CVV :</label> <input type="text" style="min-width:55px;" name="pnp_mpcvv" size="5" maxlength="4" autocomplete="off" required /><br/>';
      }
    }

    /**
    * Basic Card validation
    **/
    public function validate_fields() {
      global $woocommerce;

      if ($this->settings['giftcard_format'] == 'luhn10') {
        if (!$this->isGiftCardNumber($_POST['pnp_mpgiftcard'])) {
          wc_add_notice(sprintf(__('(Gift Card Number) is not valid.')), 'error');
        }
      }
      else {
        if (!is_numeric($_POST['pnp_mpgiftcard'])) {
          wc_add_notice(sprintf(__('(Gift Card Number) is not valid.')), 'error');
        }
      }


      if (!$this->isCCVNumber($_POST['pnp_mpcvv'])) {
        wc_add_notice(sprintf(__('(Gift Card Verification Number) is not valid.')), 'error');
      }
    }

    /**
    * Check gift card
    **/
    private function isGiftCardNumber($toCheck) {
      if (!is_numeric($toCheck)) {
        return false;
      }

      $number = preg_replace('/[^0-9]+/', '', $toCheck);
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

      if ($sum > 0 AND $sum % 10 == 0) {
        return true;
      }

      return false;
    }

    private function isCCVNumber($toCheck) {
      $length = strlen($toCheck);
      return is_numeric($toCheck) AND $length > 2 AND $length < 5;
    }

    public function thankyou_page($order_id) {
      /* nothing to do here... */
    }

    /**
    * Receipt Page
    **/
    function receipt_page($order) {
      echo '<p>'.__('Thank you for your order.', 'tech').'</p>';
    }

    /**
    * Process the payment and return the result
    **/
    function process_payment($order_id) {
      global $woocommerce;

      $order = new WC_Order($order_id);

      $params = $this->generate_plugnpay_api_gc_params($order);

      $post_string = '';
      foreach ($params as $key => $value) {
        $post_string .= "$key=" . urlencode($value) . '&';
      }
      $post_string = rtrim($post_string, '&');

      $request = curl_init('https://pay1.plugnpay.com/payment/pnpremote.cgi'); // initiate curl object
      curl_setopt($request, CURLOPT_HEADER, 0); // set to 0 to eliminate header info from response
      curl_setopt($request, CURLOPT_RETURNTRANSFER, 1); // Returns response data instead of TRUE(1)
      curl_setopt($request, CURLOPT_POSTFIELDS, $post_string); // use HTTP POST to send form data
      curl_setopt($request, CURLOPT_SSL_VERIFYPEER, FALSE); // address possible gateway response issues.
      $post_response = curl_exec($request); // execute curl post and store results in $post_response
      curl_close ($request);

      parse_str($post_response, $response);

      if ($response['FinalStatus'] != '') {
        if (($response['FinalStatus'] == 'success') || ($response['FinalStatus'] == 'pending')) {
          if ($order->status != 'completed') {
            $order->payment_complete($response['orderID']);
            $woocommerce->cart->empty_cart();
            $order->add_order_note($this->success_message. $response['MErrMsg'] . 'Transaction ID: '. $response['orderID']);
            unset($_SESSION['order_awaiting_payment']);
          }
          return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order)
          );
        }
        else{
          $order->add_order_note($this->failed_message . $response['MErrMsg']);
          wc_add_notice(sprintf(__('(Transaction Error) '. $response['MErrMsg'])), 'error');
        }
      }
      else {
        $order->add_order_note($this->failed_message);
        $order->update_status('failed');
        wc_add_notice(sprintf(__('(Transaction Error) Error processing payment.')), 'error');
      }
    }

    /**
    * Generate PlugnPay API GC button link
    **/
    public function generate_plugnpay_api_gc_params($order) {
      $plugnpayapi_args = array(
        'publisher-name'        => $this->gateway_account,
        'publisher-password'    => $this->remote_password,
        'client'                => 'WooCommerce_API_GC',
        'mode'                  => 'auth',

        'order-id'              => $order->id,
        'card-amount'           => $order->order_total,

        'paymethod'             => 'credit',
        'mpgiftcard'            => $_POST['pnp_mpgiftcard'],
        'mpcvv'                 => $_POST['pnp_mpcvv'],

        'card-name'             => $order->billing_first_name .' '. $order->billing_last_name,
        'card-company'          => $order->billing_company,
        'card-address1'         => $order->billing_address_1,
        'card-address2'         => $order->billing_address_2,
        'card-city'             => $order->billing_city,
        'card-state'            => $order->billing_state,
        'card-zip'              => $order->billing_postcode,
        'card-country'          => $order->billing_country,
        'phone'                 => $order->billing_phone,
        'email'                 => $order->billing_email,

        'shipinfo'              => '1',
        'shipname'              => $order->shipping_first_name .' '. $order->shipping_last_name,
        'company'               => $order->shipping_company,
        'address1'              => $order->shipping_address_1,
        'address2'              => $order->shipping_address_2,
        'city'                  => $order->shipping_city,
        'state'                 => $order->shipping_state,
        'zip'                   => $order->shipping_postcode,
        'country'               => $order->shipping_country,
      );

      if ($this->post_auth == 'yes') {
        $plugnpayapi_args['authtype'] = 'authpostauth';
      }
      else {
        $plugnpayapi_args['authtype'] = 'authonly';
      }

      return $plugnpayapi_args;
    }
  }

  /**
  * Add this Gateway to WooCommerce
  **/
  function woocommerce_add_plugnpay_api_gc_gateway($methods) {
    $methods[] = 'WC_Plugnpay_API_GC_Gateway';
    return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'woocommerce_add_plugnpay_api_gc_gateway');
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'plugnpay_gc_action_links');

function plugnpay_gc_action_links ($links) {
  $gateway_links = array(
    '<a href="http://www.gatewaystatus.com/" target="_blank">Gateway Status</a>',
    '<a href="https://helpdesk.plugnpay.com/" target="_blank">Online Helpdesk</a>',
    '<a href="https://pay1.plugnpay.com/admin/" target="_blank">Merchant Admin</a>'
  );
  return array_merge($links, $gateway_links);
}

?>

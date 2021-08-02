<?php
/*
 * Plugin Name: PlugnPay API Credit Card Payment Gateway For WooCommerce
 * Plugin URI: https://github.com/PlugnPay/shopping-cart-WooCommerce
 * Description: Extends WooCommerce to Process API Credit Card Payments with PlugnPay gateway.
 * Version: 1.1.5
 * Author: PlugnPay
 * Author URI: http://www.plugnpay.com
 * Text Domain: woocommerce_plugnpay_api_cc
 * License: GPL2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

add_action('plugins_loaded', 'woocommerce_plugnpay_api_cc_init', 0);

function woocommerce_plugnpay_api_cc_init() {
  if (!class_exists('WC_Payment_Gateway'))
    return;

  /**
  * Localization
  **/
  load_plugin_textdomain('woocommerce_plugnpay_api_cc', false, dirname(plugin_basename(__FILE__)) . '/languages');

  /**
  * PlugnPay API Credit Card Payment Gateway class
  **/
  class WC_Plugnpay_API_CC_Gateway extends WC_Payment_Gateway {
    protected $msg = array();

    public function __construct() {
      $this->id                 = 'plugnpay_api_cc';
      $this->method_title       = __('PlugnPay API CC', 'tech');
      $this->method_description = __('Accept Credit Card payments via API payment method, directly in WooCommerce.', 'tech');
      $this->icon               = '';
      $this->icon_path          = WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)) . '/images/';
      $this->has_fields         = true;
      $this->init_form_fields();
      $this->init_settings();
      $this->title              = $this->settings['title'];
      $this->description        = $this->settings['description'];
      $this->cards_allowed      = $this->settings['cards_allowed'];
      $this->msg['message']     = '';
      $this->msg['class']       = '';

      // shoehorn the cardtype icons into place. Not clean, but works...
      $icon = '">';
      $cards_list = explode(',', $this->cards_allowed);
      foreach ($cards_list as $i) {
        $icon .= '<img src="' . $this->icon_path . strtolower($i) . '.png" style="border:1px #999 solid; margin-left:1px;" alt="' . ucwords($i) . '">';
      }
      $icon .= '<span "';
      $this->icon = $icon;

      if (version_compare(WOOCOMMERCE_VERSION,'2.0.0','>=')) {
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this,'process_admin_options'));
      }
      else {
        add_action('woocommerce_update_options_payment_gateways', array(&$this,'process_admin_options'));
      }

      add_action('woocommerce_receipt_plugnpay_api_cc', array(&$this, 'receipt_page'));
      add_action('woocommerce_thankyou_plugnpay_api_cc', array(&$this, 'thankyou_page'));
    }

    function init_form_fields() {
      $this->form_fields = array(
          'enabled'         => array(
              'title'          => __('Enable/Disable', 'tech'),
              'type'           => 'checkbox',
              'label'          => __('Enable PlugnPay API CC Payment Module.', 'tech'),
              'default'        => 'no'),
          'title'           => array(
              'title'          => __('Title:', 'tech'),
              'type'           => 'text',
              'description'    => __('This controls the title which the user sees during checkout.', 'tech'),
              'default'        => __('Credit Card', 'tech')),
          'description'     => array(
              'title'          => __('Description:', 'tech'),
              'type'           => 'textarea',
              'description'    => __('This controls the description which the user sees during checkout.', 'tech'),
              'default'        => __('Pay securely by Credit or Debit Card through PlugnPay Secure Servers.', 'tech')),
          'gateway_account' => array(
              'title'          => __('Gateway Account', 'tech'),
              'type'           => 'text',
              'description'    => __('Username issued by PlugnPay at time of sign up.')),
          'remote_password' => array(
              'title'          => __('Remote Client Password', 'tech'),
              'type'           => 'text',
              'description'    =>  __('Remote Client Password is created within your PlugnPay Security Administration area.', 'tech')),
          'cards_allowed'   => array(
             'title'           => __('Card Types Allowed', 'tech'),
             'type'            => 'text',
             'description'     => __('Card types your are allowed to accept. Refer to the payment method specifications for possible values.'),
             'default'         => __('Visa,Mastercard,Amex,Discover', 'tech')),
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
          'authhash'        => array(
              'title'          => __('Authorization Hash', 'tech'),
              'type'           => 'checkbox',
              'label'          => __('Enable Authorization Verification Hash ability. [MUST configure and match the settings in your PlugnPay account.]', 'tech'),
              'default'        => 'no'),
          'authhash_key'    => array(
             'title'           => __('Authorization Hash Key', 'tech'),
             'type'            => 'text',
             'description'     => __('AuthHash Verification Key', 'tech'),
             'default'         => __('', 'tech')),
          'authhash_fields' => array(
             'title'           => __('Authorization Hash Fields', 'tech'),
             'type'            => 'select',
             'options'         => array( '1'=>'publisher-name', '2'=>'publisher-name,card-amount', '3'=>'publisher-name,card-amount,acct_code'),
             'description'     => __('Fieldset to use with authhash validation. [Must configure your PlugnPay account to match]', 'tech'),
             'default'         => __('3', 'tech')),
          'giftcard_allow'  => array(
              'title'          => __('Giftcard Acceptance', 'tech'),
              'type'           => 'checkbox',
              'label'          => __('Enable to allow Giftcard Split Payments. [Merchant Processor Giftcard ability required]', 'tech'),
              'default'        => 'no'),
          'giftcard_descr'  => array(
              'title'          => __('Giftcard Description:', 'tech'),
              'type'           => 'textarea',
              'description'    => __('This controls the giftcard description which the user sees during checkout.', 'tech'),
              'default'        => __('[optional] Enter your gift card details below.', 'tech')),
          'giftcard_note'   => array(
              'title'          => __('Giftcard Note:', 'tech'),
              'type'           => 'textarea',
              'description'    => __('This controls the usage note under the giftcard fields, which the user sees during checkout.', 'tech'),
              'default'        => __('If gift card has an insufficient balance, the remainder will be automatically applied to credit card supplied.', 'tech')),
           'divert_currency' => array(
              'title'          => __('Divert Currency'),
              'type'           => 'checkbox',
              'description'    => __('Enable to divert currency to alt account. [Multiple gateway accounts required, each setup for a different currency.]', 'tech'),
             'default'         => __('no', 'tech')),
           'divert_accounts'  => array(
             'title'           => __('Diverted Accounts', 'tech'),
             'type'            => 'text',
             'description'     => __('List currency code & username to divert specific payments to. [i.e. USD:username1,BBD:username2,CAD:username3]  Currency codes not listed will use default Gateway Account.')),
       );
    }

    /**
    * Admin Panel Options
    **/
    public function admin_options() {
      echo '<h3>'.__('PlugnPay API CC Payment Gateway', 'tech').'</h3>';
      echo '<p>'.__('PlugnPay is a popular payment gateway for online payment processing').'</p>';
      echo '<table class="form-table">';
      $this->generate_settings_html();
      echo '</table>';
    }

    /**
    * Fields for PlugnPay API CC
    **/

    function payment_fields() {
      if ($this->description) {
        echo wpautop(wptexturize($this->description));
        echo '<label style="margin-right:46px; line-height:40px;">Credit Card :</label> <input type="text" name="pnp_cardnumber" size="21" maxlength="20" autocomplete="off" required /><br/>';
        echo '<label style="margin-right:30px; line-height:40px;">Expiry (MMYY) :</label> <input type="text" style="min-width:55px;" name="pnp_cardexp" size="5" maxlength="4" autocomplete="off" required /><br/>';
        echo '<label style="margin-right:89px; line-height:40px;">CVV :</label> <input type="text" style="min-width:55px;" name="pnp_cardcvv" size="5" maxlength="4" autocomplete="off" required /><br/>';
      }
      if ($this->settings['giftcard_allow'] == 'yes') {
        echo '<div style="font-weight:normal;">&nbsp;<br/>' . wpautop(wptexturize($this->settings['giftcard_descr'])) . '</div/>';
        echo '<label style="margin-right:46px; line-height:40px;">Gift Card :</label> <input type="text" name="pnp_mpgiftcard" size="21" maxlength="20" autocomplete="off" required /><br/>';
        echo '<label style="margin-right:89px; line-height:40px;">CVV :</label> <input type="text" style="min-width:55px;" name="pnp_mpcvv" size="5" maxlength="4" autocomplete="off" required /><br/>';
        echo '<div style="font-style:italic;">' . wpautop(wptexturize($this->settings['giftcard_note'])) . '</div/>';
      }
    }

    /**
    * Basic Card validation
    **/
    public function validate_fields() {
      global $woocommerce;

      if (!$this->isCreditCardNumber($_POST['pnp_cardnumber'])) {
        wc_add_notice(sprintf(__('(Credit Card Number) is not valid.')), 'error');
      }
      if (!$this->isCorrectExpireDate($_POST['pnp_cardexp'])) {
        wc_add_notice(sprintf(__('(Card Expiry Date) is not valid.')), 'error');
      }
      if (!$this->isCCVNumber($_POST['pnp_cardcvv'])) {
        wc_add_notice(sprintf(__('(Card Verification Number) is not valid.')), 'error');
      }

      if ($this->settings['giftcard_allow'] == 'yes') {
        $mpgiftcard = preg_replace('/[^0-9]+/', '', $_POST['pnp_mpgiftcard']);
        $mpcvv = preg_replace('/[^0-9]+/', '', $_POST['pnp_mpcvv']);

        if (!empty($mpgiftcard) || !empty($mpcvv)) {
          if ($mpgiftcard != $_POST['pnp_mpgiftcard']) {
            wc_add_notice(sprintf(__('(Gift Card Number) is not valid.')), 'error');
          }
          if ($mpcvv != $_POST['pnp_mpcvv']) {
            wc_add_notice(sprintf(__('(Gift Card Verification Number) is not valid.')), 'error');
          }
        }
      }
    }

    /**
    * Check credit card
    **/
    private function isCreditCardNumber($toCheck) {
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

    /**
    * Check expiry date
    **/
    private function isCorrectExpireDate($date) {
      if (is_numeric($date) && (strlen($date) == 4)) {
        return true;
      }
      return false;
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

      $params = $this->generate_plugnpay_api_cc_params($order);

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
            $order->add_order_note($this->settings['success_message'] . $response['MErrMsg'] . 'Transaction ID: '. $response['orderID']);
            unset($_SESSION['order_awaiting_payment']);
          }
          return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order)
          );
        }
        else{
          $order->add_order_note($this->settings['failed_message'] . $response['MErrMsg']);
          wc_add_notice(sprintf(__('(Transaction Error) '. $response['MErrMsg'])), 'error');
        }
      }
      else {
        $order->add_order_note($this->settings['failed_message']);
        $order->update_status('failed');
        wc_add_notice(sprintf(__('(Transaction Error) Error processing payment.')), 'error');
      }
    }

    /**
    * Generate PlugnPay API CC button link
    **/
    public function generate_plugnpay_api_cc_params($order) {

       $gatewayAccount = $this->settings['gateway_account'];
       $currencyCode = $order->get_currency();

       if ($this->settings['divert_currency'] == 'yes') {
         $divert_list = explode(',', $this->settings['divert_accounts']);
         foreach ($divert_list as $i) {
           list($altCurrency,$altMerchant) = explode(':', $i, 2);
           if (strtolower($altCurrency) == strtolower($order->get_currency())) {
             $gatewayAccount = $altMerchant;
             $currentCode = $altCurrency;
             break 1;
           }
         }
       }

      $plugnpayapi_args = array(
        'publisher-name'        => strtolower($gatewayAccount),
        'publisher-password'    => $this->settings['remote_password'],
        'client'                => 'WooCommerce_API_CC',
        'mode'                  => 'auth',

        'order-id'              => $order->id,
        'card-amount'           => $order->order_total,
        'currency'              => strtoupper($currentCode),

        'paymethod'             => 'credit',
        'card-number'           => $_POST['pnp_cardnumber'],
        'card-exp'              => $_POST['pnp_cardexp'],
        'card-cvv'              => $_POST['pnp_cardcvv'],

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

        'shipinfo'              => '0',
        'shipname'              => $order->shipping_first_name .' '. $order->shipping_last_name,
        'company'               => $order->shipping_company,
        'address1'              => $order->shipping_address_1,
        'address2'              => $order->shipping_address_2,
        'city'                  => $order->shipping_city,

        'state'                 => $order->shipping_state,
        'zip'                   => $order->shipping_postcode,
        'country'               => $order->shipping_country,
      );

      $plugnpayapi_args['ipaddress'] = plugnpay_cc_getUserIP();
      
      if ($this->settings['post_auth'] == 'yes') {
        $plugnpayapi_args['authtype'] = 'authpostauth';
      }
      else {
        $plugnpayapi_args['authtype'] = 'authonly';
      }

      if ($this->settings['authhash'] == 'yes') {
         $string_fields = ''; 
         if ($this->settings['authhash_fields'] == '3') {
            $string_fields = $order_id . $order->get_total() . strtolower($gatewayAccount);
         }
         else if ($this->settings['authhash_fields'] == '2') {
            $string_fields = $order->get_total() . strtolower($gatewayAccount);
         }
         else { # $this->settings['authhash_fields'] == '1'
            $string_fields = strtolower($gatewayAccount);
         }
         $timestamp = gmdate("YmdHis", time());
         $hash_string = $this->settings['authhash_key'] .  $timestamp . $string_fields;

         $plugnpayapi_args['authhash'] = md5($hash_string);
         $plugnpayapi_args['transacttime'] = $timestamp;
      }

      if ($this->settings['giftcard_allow'] == 'yes') {
        $plugnpayapi_args['mpgiftcard'] = $_POST['pnp_mpgiftcard'];
        $plugnpayapi_args['mpcvv'] = $_POST['pnp_mpcvv'];
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

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'plugnpay_cc_action_links');

function plugnpay_cc_action_links ($links) {
  $gateway_links = array(
    '<a href="http://www.gatewaystatus.com/" target="_blank">Gateway Status</a>',
    '<a href="https://helpdesk.plugnpay.com/" target="_blank">Online Helpdesk</a>',
    '<a href="https://pay1.plugnpay.com/admin/" target="_blank">Merchant Admin</a>'
  );
  return array_merge($links, $gateway_links);
}

function plugnpay_cc_getUserIP() {
  // Get real visitor IP behind CloudFlare network
  if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    $_SERVER['HTTP_CLIENT_IP'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
  }
  $client  = @$_SERVER['HTTP_CLIENT_IP'];
  $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
  $remote  = $_SERVER['REMOTE_ADDR'];

  if (filter_var($client, FILTER_VALIDATE_IP)) {
    $ip = $client;
  }
  elseif (filter_var($forward, FILTER_VALIDATE_IP)) {
    $ip = $forward;
  }
  else {
    $ip = $remote;
  }

  return $ip;
}

?>

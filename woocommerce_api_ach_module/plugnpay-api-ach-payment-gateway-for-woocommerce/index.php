<?php
/*
 * Plugin Name: PlugnPay API ACH/eCheck Payment Gateway For WooCommerce
 * Plugin URI: http://www.plugnpay.com
 * Description: Extends WooCommerce to Process API ACH/eCheck Payments with PlugnPay gateway.
 * Version: 1.1.1
 * Author: PlugnPay
 * Author URI: http://www.plugnpay.com
 * Text Domain: woocommerce_plugnpay_api_ach
 * License: GPL2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

add_action('plugins_loaded', 'woocommerce_plugnpay_api_ach_init', 0);

function woocommerce_plugnpay_api_ach_init() {
  if (!class_exists('WC_Payment_Gateway'))
    return;

  /**
  * Localization
  **/
  load_plugin_textdomain('woocommerce_plugnpay_api_ach', false, dirname(plugin_basename(__FILE__)) . '/languages');

  /**
  * PlugnPay API ACH/eCheck Payment Gateway class
  **/
  class WC_Plugnpay_API_ACH_Gateway extends WC_Payment_Gateway {
    protected $msg = array();

    public function __construct() {
      $this->id               = 'plugnpay_api_ach';
      $this->method_title     = __('PlugnPay API ACH', 'tech');
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

      add_action('woocommerce_receipt_plugnpay_api_ach', array(&$this, 'receipt_page'));
      add_action('woocommerce_thankyou_plugnpay_api_ach', array(&$this, 'thankyou_page'));
    }

    function init_form_fields() {
      $this->form_fields = array(
          'enabled'         => array(
              'title'          => __('Enable/Disable', 'tech'),
              'type'           => 'checkbox',
              'label'          => __('Enable PlugnPay API ACH Payment Module.', 'tech'),
              'default'        => 'no'),
          'title'           => array(
              'title'          => __('Title:', 'tech'),
              'type'           => 'text',
              'description'    => __('This controls the title which the user sees during checkout.', 'tech'),
              'default'        => __('Checking Account', 'tech')),
          'description'     => array(
              'title'          => __('Description:', 'tech'),
              'type'           => 'textarea',
              'description'    => __('This controls the description which the user sees during checkout.', 'tech'),
              'default'        => __('Pay securely by Checking Account through PlugnPay Secure Servers.', 'tech')),
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
              'description'    => "Transaction Settlement. If you are not sure what to use set to 'Authorize and Settle'")
       );
    }

    /**
    * Admin Panel Options
    **/
    public function admin_options() {
      echo '<h3>'.__('PlugnPay API ACH Payment Gateway', 'tech').'</h3>';
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
        echo '<label style="margin-right:89px; line-height:40px;">Account Type :</label> <select name="pnp_accttype"><option value="checking">Checking</option><option value="savings">Savings</option></select /><br/>';
        echo '<label style="margin-right:89px; line-height:40px;">Routing Number :</label> <input type="text" name="pnp_routingnum" size="10" maxlength="9" autocomplete="off" required /><br/>';
        echo '<label style="margin-right:89px; line-height:40px;">Account Number :</label> <input type="text" name="pnp_accountnum" size="21" maxlength="20" autocomplete="off" required /><br/>';
        echo '<label style="margin-right:89px; line-height:40px;">Check Number :</label> <input type="text" name="pnp_checknum" size="11" maxlength="20" autocomplete="off" required /><br/>';
        echo '<label style="margin-right:89px; line-height:40px;">Classification :</label> <select name="pnp_acctclass"><option value="personal">Personal</option><option value="business">Business</option></select /><br/>';
      }
    }

    /**
    * Basic Card validation
    **/
    public function validate_fields() {
      global $woocommerce;

      if (!$this->isRoutingNumber($_POST['pnp_routingnum'])) {
        wc_add_notice(sprintf(__('(Routing Number) is not valid.')), 'error');
      }
      if (!$this->isAccountNumber($_POST['pnp_accountnum'])) {
        wc_add_notice(sprintf(__('(Account Number) is not valid.')), 'error');
      }
      if (!$this->isCheckNumber($_POST['pnp_checknum'])) {
        wc_add_notice(sprintf(__('(Check Number) is not valid.')), 'error');
      }
    }

    /**
    * Check ACH info
    **/
    private function isRoutingNumber($toCheck) {
      if (!is_numeric($toCheck)) {
        return false;
      }

      $number = preg_replace('/[^0-9]+/', '', $toCheck);
      $strlen = strlen($number);
      $sum    = 0;

      if ($strlen != 9) {
        return false;
      }
  
      // perform a checksum on the number
      $one = $number[0] * 3;   // first digit X 3
      $two = $number[1] * 7;   // second digit X 7
      $three = $number[2] * 1; // third digit X 1
      $four = $number[3] * 3;  // fourth digit X 3
      $five = $number[4] * 7;  // fifth digit X 7
      $six = $number[5] * 1;   // sixth digit X 1
      $seven = $number[6] * 3; // seventh digit X 3
      $eight = $number[7] * 7; // eighth digit X 7
      $nine = $number[8] * 1;  // last digit X 1

      // sum of all the above should be equal to a multiple of 10
      // ex. 150,160,170 etc.
      $sum = $one + $two + $three + $four + $five + $six + $seven + $eight + $nine;

      // check if its a multiple of 10
      if($sum % 10 == 0){
        return true;
      }
      return false;
    }

    private function isAccountNumber($toCheck) {
      $length = strlen($toCheck);
      return is_numeric($toCheck) AND $length >= 1 AND $length < 21;
    }

    private function isCheckNumber($toCheck) {
      $length = strlen($toCheck);
      return is_numeric($toCheck) AND $length >= 1 AND $length < 21;
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
    * Generate PlugnPay API CC button link
    **/
    public function generate_plugnpay_api_cc_params($order) {
      $plugnpayapi_args = array(
        'publisher-name'        => $this->gateway_account,
        'publisher-password'    => $this->remote_password,
        'client'                => 'WooCommerce_API_ACH',
        'mode'                  => 'auth',

        'order-id'              => $order->id,
        'card-amount'           => $order->order_total,

        'paymethod'             => 'onlinecheck',
        'checktype'             => 'WEB',
        'accttype'              => $_POST['pnp_accttype'],
        'routingnum'            => $_POST['pnp_routingnum'],
        'accountnum'            => $_POST['pnp_accountnum'],
        'checknum'              => $_POST['pnp_checknum'],
        'acctclass'             => $_POST['pnp_acctclass'],

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

      if ($plugnpayapi_args['acctclass'] == 'business') {
        $plugnpayapi_args['commcardtype'] = 'business';
      }

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
  function woocommerce_add_plugnpay_api_ach_gateway($methods) {
    $methods[] = 'WC_Plugnpay_API_ACH_Gateway';
    return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'woocommerce_add_plugnpay_api_ach_gateway');
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'plugnpay_ach_action_links');

function plugnpay_ach_action_links ($links) {
  $gateway_links = array(
    '<a href="http://www.gatewaystatus.com/" target="_blank">Gateway Status</a>',
    '<a href="https://helpdesk.plugnpay.com/" target="_blank">Online Helpdesk</a>',
    '<a href="https://pay1.plugnpay.com/admin/" target="_blank">Merchant Admin</a>'
  );
  return array_merge($links, $gateway_links);
}

?>

<?php
/*
 * Plugin Name: PlugnPay SSv2 Payment Gateway For WooCommerce
 * Plugin URI: https://github.com/PlugnPay/shopping-cart-WooCommerce
 * Description: Extends WooCommerce to Process Smart Screens v2 Payments with PlugnPay gateway.
 * Version: 1.1.8.1
 * Author: PlugnPay
 * Author URI: http://www.plugnpay.com
 * Text Domain: woocommerce_plugnpay_ss1
 * License: GPL2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/
add_action('plugins_loaded', 'woocommerce_tech_autho_init', 0);

function woocommerce_tech_autho_init() {

   if (!class_exists('WC_Payment_Gateway'))
      return;

   /**
    * Localisation
   **/
   load_plugin_textdomain('wc-tech-autho', false, dirname( plugin_basename(__FILE__)) . '/languages');

   /**
    * PlugnPay Payment Gateway class
   **/
   class WC_Tech_Autho extends WC_Payment_Gateway {
      protected $msg = array();

      public function __construct() {
         $this->id                 = 'plugnpay';
         $this->method_title       = __('PlugnPay SSv2', 'tech');
         $this->method_description = __('Smart Screens v2 payment method redirects customers to PlugnPay to enter their payment information.', 'tech');
         $this->icon               = '';
         $this->icon_path          = WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)) . '/images/';
         $this->has_fields         = false;
         $this->init_form_fields();
         $this->init_settings();
         $this->title              = $this->settings['title'];
         $this->description        = $this->settings['description'];
         $this->cards_allowed      = $this->settings['cards_allowed'];
         $this->msg['message']     = '';
         $this->msg['class']       = '';

         // shoehorn the cardtype icons into place. Not clean, but works...
         $icon = WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)) . '/images/logo.png"><br/>';
         $cards_list = explode(',', $this->cards_allowed);
         foreach ($cards_list as $i) {
           $icon .= '<img src="' . $this->icon_path . strtolower($i) . '.png" style="border:1px #999 solid; margin-left:1px;" alt="' . ucwords($i) . '">';
         }
         $icon .= '<span "';
         $this->icon = $icon;

         add_action('init', array(&$this, 'check_plugnpay_response'));
         //update for woocommerce >2.0
         add_action('woocommerce_api_wc_tech_autho', array($this, 'check_plugnpay_response'));
         add_action('valid-plugnpay-request', array(&$this, 'successful_request'));

         if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
         }
         else {
            add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
         }

         add_action('woocommerce_receipt_plugnpay', array(&$this, 'receipt_page'));
         add_action('woocommerce_thankyou_plugnpay',array(&$this, 'thankyou_page'));
      }

      function init_form_fields()  {
         $this->form_fields = array(
           'enabled'         => array(
             'title'           => __('Enable/Disable', 'tech'),
             'type'            => 'checkbox',
             'label'           => __('Enable PlugnPay Payment Module.', 'tech'),
             'default'         => 'no'),
           'title'           => array(
             'title'           => __('Title:', 'tech'),
             'type'            => 'text',
             'description'     => __('This controls the title which the user sees during checkout.', 'tech'),
             'default'         => __('Online Payment', 'tech')),
           'description'     => array(
             'title'           => __('Description:', 'tech'),
             'type'            => 'textarea',
             'description'     => __('This controls the description which the user sees during checkout.', 'tech'),
             'default'         => __('Pay securely online through PlugnPay Secure Servers.', 'tech')),
           'gateway_account' => array(
             'title'           => __('Gateway Username', 'tech'),
             'type'            => 'text',
             'description'     => __('Username issued by PlugnPay at time of sign up.')),
           'cards_allowed'   => array(
             'title'           => __('Card Types Allowed', 'tech'),
             'type'            => 'text',
             'description'     => __('Card types your are allowed to accept. Refer to the payment method specifications for possible values.'),
             'default'         => __('Visa,Mastercard,Amex,Discover', 'tech')),
           'success_message' => array(
             'title'           => __('Transaction Success Message', 'tech'),
             'type'            => 'textarea',
             'description'     => __('Message to be displayed on successful transaction.', 'tech'),
             'default'         => __('Your payment has been processed successfully.', 'tech')),
           'failed_message'  => array(
             'title'           => __('Transaction Failed Message', 'tech'),
             'type'            => 'textarea',
             'description'     => __('Message to be displayed on failed transaction.', 'tech'),
             'default'         => __('Your transaction has been declined.', 'tech')),
           'post_auth'       => array(
             'title'           => __('Transaction Settlement'),
             'type'            => 'select',
             'options'         => array( 'yes'=>'Authorize and Settle', 'no'=>'Authorize Only'),
             'description'     => "Transaction Settlement. If you are not sure what to use set to 'Authorize and Settle'"),
           'tdsflag'         => array(
             'title'           => __('3D Secure Checkout'),
             'type'            => 'select',
             'options'         => array( 'yes'=>'Enable', 'no'=>'Disable'),
             'default'         => __('no', 'tech'),
             'description'     => "3D Secure Checkout. * Merchant MUST be subscribed to an authorized 3D secure program.  Contact technical support for details."),
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
           'divert_currency' => array(
              'title'          => __('Divert Currency'),
              'type'           => 'checkbox',
              'description'    => __('Enable to divert currency to alt account. [Multiple gateway accounts required, each setup for a different currency.]', 'tech'),
              'default'        => __('no', 'tech')),
           'divert_accounts'  => array(
             'title'           => __('Diverted Accounts', 'tech'),
             'type'            => 'text',
             'description'     => __('List currency code & username to divert specific payments to. [i.e. USD:username1,BBD:username2,CAD:username3]  Currency codes not listed will use default Gateway Account.')),
         );
      }


      /**
       * Admin Panel Options
       * - Options for bits like 'title' and availability on a country-by-country basis
      **/
      public function admin_options() {
         echo '<h3>'.__('PlugnPay Payment Gateway', 'tech').'</h3>';
         echo '<p>'.__('PlugnPay is most popular payment gateway for online payment processing').'</p>';
         echo '<table class="form-table">';
         $this->generate_settings_html();
         echo '</table>';
      }

      /**
       * There are no payment fields for PlugnPay, but want to show the description if set.
      **/
      function payment_fields() {
         if ($this->description)
            echo wpautop(wptexturize($this->description));
      }

      public function thankyou_page($order_id) {
        // do nothing...
      }

      /**
       * Receipt Page
      **/
      function receipt_page($order) {
         echo '<p>'.__('Thank you for your order, please click the button below to pay with PlugnPay.', 'tech').'</p>';
         echo $this->generate_plugnpay_form($order);
      }

      /**
       * Process the payment and return the result
      **/
      function process_payment($order_id) {
         $order = new WC_Order($order_id);
         return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
         );
      }

      /**
       * Check for valid PlugnPay server callback to validate the transaction response.
      **/
      function check_plugnpay_response() {
         global $woocommerce;
         $temp_order            = new WC_Order();

         if (count($_POST)) {
            $redirect_url = '';
            $this->msg['class']     = 'error';
            $this->msg['message']   = $this->settings['failed_message'];
            $order                  = new WC_Order($_POST['pt_order_classifier']);
            if (($_POST['pi_response_code'] != '') && ($_POST['pi_response_status'] ==  'success')) {
               try{
                  $transauthorised  = false;

                  if ($order->get_status() != 'completed') {
                     if ($_POST['pi_response_status'] == 'success') {
                        $transauthorised        = true;
                        $this->msg['message']   = $this->settings['success_message'];
                        $this->msg['class']     = 'success';

                        if ($order->get_status() == 'processing') {
                           // do nothing...
                        }
                        else{
                            $order->payment_complete($_REQUEST['pt_order_id']);
                            $order->add_order_note('PlugnPay payment successful<br/>Ref Number/Transaction ID: '.$_REQUEST['pt_order_id']);
                            $order->add_order_note($this->msg['message']);
						    /**
						     * NOTE: By default, WooCommerce changed the order's status from 'Pending Payment' to 'Processing'.
						     *       For merchants wishing to bypass the 'Processing' status stage, uncomment the below line of code.
						     *       This will force the order's status to 'Completed' within WooCommerce's Orders section for you.
						     **/
                            // $order->update_status('completed');
						    $woocommerce->cart->empty_cart();
                        }
                     }
                     else{
                        $this->msg['class'] = 'error';
                        $this->msg['message'] = $this->failed_message;
                        $order->add_order_note($this->msg['message']);
                        $order->update_status('failed');
                        //extra code can be added here such as sending an email to customer on transaction fail
                     }
                  }
                  if ($transauthorised == false) {
                    $order->update_status('failed');
                    $order->add_order_note($this->msg['message']);
                  }
               }
               catch(Exception $e) {
                   // $errorOccurred = true;
                   $msg = 'Error';
               }
            }
            $redirect_url = $order->get_checkout_order_received_url();
            $this->web_redirect( $redirect_url);
            exit;
         }
         else{
            $redirect_url = $temp_order->get_checkout_order_received_url();
            $this->web_redirect($redirect_url.'?msg=Unknown_error_occured');
            exit;
         }
      }


      public function web_redirect($url) {
        echo "<html><head><script language=\"javascript\">
              <!--
              window.location=\"{$url}\";
              //-->
              </script>
              </head><body><noscript><meta http-equiv=\"refresh\" content=\"0;url={$url}\"></noscript></body></html>";
      }
      /**
      * Generate PlugnPay button link
      **/
      public function generate_plugnpay_form($order_id) {
         global $woocommerce;

         $order = new WC_Order($order_id);

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

         $success_url = get_site_url().'/wc-api/'.get_class($this);

         $plugnpay_args = array(
            'pt_client_identifier'     => 'woocommerce_ss2',
            'pt_gateway_account'       => strtolower($gatewayAccount),
            'pb_cards_allowed'         => $this->settings['cards_allowed'],
            'pt_transaction_amount'    => $order->get_total(),
            'pt_currency'              => strtoupper($currencyCode),
            'pt_order_classifier'      => $order_id,
            'pt_account_code_1'        => $order_id,
            'pb_transition_type'       => 'hidden',
            'pb_success_url'           => $success_url,
            'pd_collect_company'       => 'yes',
            'pt_billing_name'          => $order->get_billing_first_name() . ' '. $order->get_billing_last_name(),
            'pt_billing_company'       => $order->get_billing_company(),
            'pt_billing_address_1'     => $order->get_billing_address_1(),
            'pt_billing_address_2'     => $order->get_billing_address_2(),
            'pt_billing_country'       => $order->get_billing_country(),
            'pt_billing_state'         => $order->get_billing_state(),
            'pt_billing_city'          => $order->get_billing_city(),
            'pt_billing_postal_code'   => $order->get_billing_postcode(),
            'pt_billing_phone_number'  => $order->get_billing_phone(),
            'pt_billing_email_address' => $order->get_billing_email(),
            'pd_collect_shipping_information' => 'no',
            'pt_shipping_name'         => $order->get_shipping_first_name() .' '. $order->get_shipping_last_name(),
            'pt_shipping_company'      => $order->get_shipping_company(),
            'pt_shipping_address_1'    => $order->get_shipping_address_1(),
            'pt_shipping_address_2'    => $order->get_shipping_address_2(),
            'pt_shipping_country'      => $order->get_shipping_country(),
            'pt_shipping_state'        => $order->get_shipping_state(),
            'pt_shipping_city'         => $order->get_shipping_city(),
            'pt_shipping_postal_code'  => $order->get_shipping_postcode(),
         );

         if ($this->settings['post_auth'] == 'yes') {
            $plugnpay_args['pb_post_auth'] = 'yes';
         }
         else {
            $plugnpay_args['pb_post_auth'] = 'no';
         }

         if ($this->settings['tdsflag'] == 'yes') {
            $plugnpay_args['pb_tds'] = 'yes';
         }

         if ($this->settings['authhash'] == 'yes') {
            $string_fields = ''; 
            if ($this->settings['authhash_fields'] == '3') {
               $string_fields = $order_id . $order->get_total() . $this->settings['gateway_account'];
            }
            else if ($this->settings['authhash_fields'] == '2') {
               $string_fields = $order->get_total() . $this->settings['gateway_account'];
            }
            else { # $this->settings['authhash_fields'] == '1'
               $string_fields = $this->settings['gateway_account'];
            }
            $timestamp = gmdate("YmdHis", time());
            $hash_string = $this->settings['authhash_key'] .  $timestamp . $string_fields;

            $plugnpay_args['pt_transaction_hash'] = md5($hash_string);
            $plugnpay_args['pt_transaction_time'] = $timestamp;
         }

         if ($this->settings['giftcard_allow'] == 'yes') {
           $plugnpay_args['pd_transaction_payment_type'] = 'mpgiftcard';
         }

         $plugnpay_args_array = array();

         foreach($plugnpay_args as $key => $value) {
            $plugnpay_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
         }

         $html_form = '<form action="https://pay1.plugnpay.com/pay/" method="post" id="plugnpay_payment_form">'
               . implode('', $plugnpay_args_array)
               . '<input type="submit" class="button" id="submit_plugnpay_payment_form" value="'.__('Pay via PlugnPay', 'tech').'" /> '
               . '<a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'tech').'</a>'
               . '<script type="text/javascript">
               jQuery(function() {
                  jQuery("body").block({
                     message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to PlugnPay to make payment.', 'tech').'",
                     overlayCSS: {
                       background: "#ccc",
                       opacity:    0.6,
                       "z-index":  "99999999999999999999999999999999"
                     },
                     css: {
                       padding:          20,
                       textAlign:        "center",
                       color:            "#555",
                       border:           "3px solid #aaa",
                       backgroundColor:  "#fff",
                       cursor:           "wait",
                       lineHeight:       "32px",
                       "z-index": "999999999999999999999999999999999"
                     }
                  });
               jQuery("#submit_plugnpay_payment_form").click();
            });
            </script>
            </form>';

         return $html_form;
      }
   }

   /**
    * Add this Gateway to WooCommerce
   **/
   function woocommerce_add_tech_autho_gateway($methods) {
      $methods[] = 'WC_Tech_Autho';
      return $methods;
   }

   add_filter('woocommerce_payment_gateways', 'woocommerce_add_tech_autho_gateway');
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'plugnpay_ss1_action_links');

function plugnpay_ss1_action_links ($links) {
  $gateway_links = array(
    '<a href="http://www.gatewaystatus.com/" target="_blank">Gateway Status</a>',
    '<a href="https://helpdesk.plugnpay.com/" target="_blank">Online Helpdesk</a>',
    '<a href="https://pay1.plugnpay.com/admin/" target="_blank">Merchant Admin</a>'
  );
  return array_merge($links, $gateway_links);
}


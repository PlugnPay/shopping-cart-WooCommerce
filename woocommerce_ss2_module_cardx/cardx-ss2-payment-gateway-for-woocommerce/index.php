<?php
/*
   Plugin Name: CardX SSv2 Payment Gateway For WooCommerce
   Description: Extends WooCommerce to Process Smart Screens v2 Payments with CardX gateway.
   Version: 1.1.6
   Plugin URI: http://www.cardx.com
   Author: CardX
   Author URI: http://www.cardx.com
   License: Under GPL2
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
    * CardX Payment Gateway class
   **/
   class WC_Tech_Autho extends WC_Payment_Gateway {
      protected $msg = array();

      public function __construct() {
         $this->id               = 'cardx';
         $this->method_title     = __('CardX SSv2', 'tech');
         $this->method_description = __('Smart Screens v2 payment method redirects customers to CardX to enter their payment information.', 'tech');
         $this->icon             = WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)) . '/images/logo.png';
         $this->has_fields       = false;
         $this->init_form_fields();
         $this->init_settings();
         $this->title            = $this->settings['title'];
         $this->description      = $this->settings['description'];
         $this->msg['message']   = '';
         $this->msg['class']     = '';

         add_action('init', array(&$this, 'check_cardx_response'));
         //update for woocommerce >2.0
         add_action('woocommerce_api_wc_tech_autho', array($this, 'check_cardx_response'));
         add_action('valid-cardx-request', array(&$this, 'successful_request'));

         if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
         }
         else {
            add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
         }

         add_action('woocommerce_receipt_cardx', array(&$this, 'receipt_page'));
         add_action('woocommerce_thankyou_cardx',array(&$this, 'thankyou_page'));
      }

      function init_form_fields()  {
         $this->form_fields = array(
           'enabled'         => array(
             'title'           => __('Enable/Disable', 'tech'),
             'type'            => 'checkbox',
             'label'           => __('Enable CardX Payment Module.', 'tech'),
             'default'         => 'no'),
           'title'           => array(
             'title'           => __('Title:', 'tech'),
             'type'            => 'text',
             'description'     => __('This controls the title which the user sees during checkout.', 'tech'),
             'default'         => __('Pay Securely Online via CardX', 'tech')),
           'description'     => array(
             'title'           => __('Description:', 'tech'),
             'type'            => 'textarea',
             'description'     => __('This controls the description which the user sees during checkout.', 'tech'),
             'default'         => __('Pay securely payment through CardX Secure Servers.', 'tech')),
           'gateway_account' => array(
             'title'           => __('Gateway Username', 'tech'),
             'type'            => 'text',
             'description'     => __('Username issued by CardX at time of sign up.')),
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
             'title'           => __('Authorization Hash'),
             'type'            => 'select',
             'options'         => array( 'yes'=>'Enable', 'no'=>'Disable'),
             'default'         => __('no', 'tech'),
             'description'     => "Authorization Hash. * Merchant MUST enable & configure the settings to match their CardX account.  Contact technical support for details."),
           'authhash_key'    => array(
             'title'           => __('Authorization Hash Key', 'tech'),
             'type'            => 'text',
             'description'     => __('AuthHash Verification Key', 'tech'),
             'default'         => __('', 'tech')),
           'authhash_fields' => array(
             'title'           => __('Authorization Hash Fields', 'tech'),
             'type'            => 'select',
             'options'         => array( '1'=>'publisher-name', '2'=>'publisher-name,card-amount', '3'=>'publisher-name,card-amount,acct_code'),
             'description'     => __('Fieldset to use with authhash validation. [Must configure your CardX account to match]', 'tech'),
             'default'         => __('3', 'tech')),
         );
      }


      /**
       * Admin Panel Options
       * - Options for bits like 'title' and availability on a country-by-country basis
      **/
      public function admin_options() {
         echo '<h3>'.__('CardX Payment Gateway', 'tech').'</h3>';
         echo '<p>'.__('CardX is most popular payment gateway for online payment processing').'</p>';
         echo '<table class="form-table">';
         $this->generate_settings_html();
         echo '</table>';
      }

      /**
       * There are no payment fields for CardX, but want to show the description if set.
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
         echo '<p>'.__('Thank you for your order, please click the button below to pay with CardX.', 'tech').'</p>';
         echo $this->generate_cardx_form($order);
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
       * Check for valid CardX server callback to validate the transaction response.
      **/
      function check_cardx_response() {
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
                            $order->add_order_note('CardX payment successful<br/>Ref Number/Transaction ID: '.$_REQUEST['pt_order_id']);
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
      * Generate CardX button link
      **/
      public function generate_cardx_form($order_id) {
         global $woocommerce;

         $order = new WC_Order($order_id);

         $success_url = get_site_url().'/wc-api/'.get_class($this);

         $cardx_args = array(
            'pt_client_identifier'     => 'woocommerce_ss2',
            'pt_gateway_account'       => $this->settings['gateway_account'],
            'pb_cards_allowed'         => $this->settings['cards_allowed'],
            'pt_transaction_amount'    => $order->get_total(),
            'pt_currency'              => $order->get_currency(),
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
            $cardx_args['pb_post_auth'] = 'yes';
         }
         else {
            $cardx_args['pb_post_auth'] = 'no';
         }

         if ($this->settings['tdsflag'] == 'yes') {
            $cardx_args['pb_tds'] = 'yes';
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

            $cardx_args['pt_transaction_hash'] = md5($hash_string);
            $cardx_args['pt_transaction_time'] = $timestamp;
         }

         $cardx_args_array = array();

         foreach($cardx_args as $key => $value) {
            $cardx_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
         }

         $html_form = '<form action="https://paywithcardx.com/pay/" method="post" id="cardx_payment_form">'
               . implode('', $cardx_args_array)
               . '<input type="submit" class="button" id="submit_cardx_payment_form" value="'.__('Pay via CardX', 'tech').'" /> '
               . '<a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'tech').'</a>'
               . '<script type="text/javascript">
               jQuery(function() {
                  jQuery("body").block({
                     message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to CardX to make payment.', 'tech').'",
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
               jQuery("#submit_cardx_payment_form").click();
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


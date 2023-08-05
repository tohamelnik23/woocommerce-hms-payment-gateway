<?php
/*
Plugin Name: WooCommerce Host Merchant Services Gateway
Plugin URI: https://www.hostmerchantservices.com/
Description: Allows payments into the Host Merchant Services payment gateway.
Version: 1.4.3
Author: Host Merchant Services
Author URI: https://www.hostmerchantservices.com/
*/

//error_reporting(E_ALL);

require_once('includes/TXPAPI.class.php');
require_once('includes/debug.php');

add_action('plugins_loaded', 'woocommerce_hms_init', 0);

function woocommerce_hms_init() {

	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	
	/**
 	 * Gateway class
 	 */
	class WC_HMS extends  WC_Payment_Gateway_CC 
	{	
		var $avaiable_countries = array(
			'GB' => array(
				'Visa',
				'MasterCard',
				'Discover',
				'American Express'
			),
			'US' => array(
				'Visa',
				'MasterCard',
				'Discover',
				'American Express'
			),
			'CA' => array(
				'Visa',
				'MasterCard',
				'Discover',
				'American Express'
			)
		);
		var $gatewayId;
		var $regKey;
		var $testMode;
		
		function __construct()
		{
			global $woocommerce;
			$this->id = "hms"; //Unique ID for your gateway. e.g. ‘your_gateway’
			$this->has_fields = true; //Bool. Can be set to true if you want payment fields to show on the checkout (if doing a direct integration).
			$this->method_title = "Host Merchant Services"; //Title of the payment method shown on the admin page.
			$this->method_description = "Host Merchant Services Woo Commerce integration plug-in."; //Description for the payment method shown on the admin page.
			$this->supports = array( 'products', 'refunds', 'default_credit_card_form');
				
				//'default_credit_card_form',
				//'pre-orders'
			
 
			// initiate admin form fields
			$this->init_form_fields();
			
			// initiate settings
			$this->init_settings();
			
			
			$this->title = (empty($this->settings['displayTitle']))? 'Credit Card' : $this->settings['displayTitle'];
			$this->displayCardLogos = ($this->settings['displayCardLogos'] == 'yes' ? true : false );
			$this->icon = ($this->displayCardLogos)? WP_PLUGIN_URL . '/woocommerce-hms-payment-gateway/images/card-logos.png' : '';  // If you want to show an image next to the gateway’s name on the frontend, enter a URL to an image.
			
			
			// load some settings
			$this->testMode = ($this->settings['testMode'] == 'yes' ? true : false );
			$this->regKey = '';
			$this->gatewayId = '';
            $this->authOnly = ($this->settings['authOnly'] == 'yes' ? true : false );

			if($this->testMode)
			{
				$this->gatewayId = $this->settings['testGatewayId'];
				$this->regKey = $this->settings['testRegKey'];
			}
			else
			{
				$this->gatewayId = $this->settings['liveGatewayId'];
				$this->regKey = $this->settings['liveRegKey'];
			}
			
			// Hooks
			add_action( 'admin_notices', array( &$this, 'ssl_check') );
			
			add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
			
			// load admin options
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
		
		/**
	 	* Check if SSL is enabled and notify the user if SSL is not enabled
	 	**/
		function ssl_check() 
		{
			if (get_option('woocommerce_force_ssl_checkout')=='no' && $this->enabled=='yes') :
			
				echo '<div class="error"><p>'.sprintf(__('Host Merchant Services Payment plugin is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate - Host Merchant Services Payment plugin will only work in test mode.', 'woothemes'), admin_url('admin.php?page=woocommerce')).'</p></div>';
			
			endif;
		}
		
		/**
	     * Initialize Gateway Settings Form Fields
	     */
	    function init_form_fields() 
		{
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'woocommerce' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Credit Card Payment', 'woocommerce' ),
					'default' => 'yes'
				),
				'displayTitle' => array(
					'title' => __( 'Display Title', 'woocommerce' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default' => __( 'Credit Card Payment', 'woocommerce' ),
					'desc_tip'      => true,
				),
				
				'testMode' => array(
					'title' => __( 'Test Mode', 'woocommerce' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Test Gateway Mode', 'woocommerce' ),
					'default' => 'no'
				),
				'testGatewayId' => array(
					'title' => __( 'Test Gateway ID', 'woocommerce' ),
					'type' => 'text',
					'label' => __( 'Test Gateway Id', 'woocommerce' ),
					'default' => ''
				),
				'testRegKey' => array(
					'title' => __( 'Test Registration Key', 'woocommerce' ),
					'type' => 'text',
					'label' => __( 'Test Registration Key', 'woocommerce' ),
					'default' => ''
				),
				'liveGatewayId' => array(
					'title' => __( 'Live Gateway ID', 'woocommerce' ),
					'type' => 'text',
					'label' => __( 'Live Gateway Id', 'woocommerce' ),
					'default' => ''
				),
				'liveRegKey' => array(
					'title' => __( 'Live Registration Key', 'woocommerce' ),
					'type' => 'text',
					'label' => __( 'Live Registration Key', 'woocommerce' ),
					'default' => ''
				),
                'authOnly' => array(
                    'title' => __( 'Authorize Transaction Only', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable Authorize Transaction Only (The transaction will be authorized but, will need to be settled within Transaction Express)', 'woocommerce' ),
                    'default' => ''
                ),
				'displayCardLogos' => array(
                    'title' => __( 'Display Credit Card Logos', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( '', 'woocommerce' ),
                    'default' => 'yes'
                ),
				'description' => array(
					'title' => __( 'Customer Message', 'woocommerce' ),
					'type' => 'textarea',
					'default' => ''
				)
			);
		}
			
		function process_payment( $order_id ) 
		{
			global $woocommerce;
			$order = new WC_Order( $order_id );
			
			// Validate plugin settings
			if (!$this->validate_settings()) :
				$cancelNote = __('Order was canceled due to invalid settings (check your API credentials and make sure your currency is supported).', 'woothemes');
				$order->add_order_note( $cancelNote );
		
				wc_add_notice(__('Payment was rejected due to configuration error.', 'woothemes'));
				return false;
			endif;

			$response = "";
			
			try
			{
				// gateway connection
				$txp = new TXPAPI( $this->gatewayId, $this->regKey, $this->testMode );
			
				$amount = $order->get_total() * 100;
				$full_name = $order->get_billing_first_name() .' '. $order ->get_billing_last_name();
				$company_name = $order->get_billing_company();

				$order_number = $order->get_order_number();
				$billing_address_1 = $order->get_billing_address_1();
				$billing_address_2 = $order->get_billing_address_2();
				$billing_state = $order->get_billing_state();
                $billing_city = $order->get_billing_city();
				$billing_postcode = explode('-', $order->get_billing_postcode())[0]; // remove extended zip
				$billing_country = $order->get_billing_country();

				$billing_email = $order->get_billing_email();
				$billing_phone = $order->get_billing_phone();

				// validate phone number
				$billing_phone = preg_replace('/\D+/', '', $billing_phone);
				
				if(strlen($billing_phone) < 10 || strlen($billing_phone) > 12)
				{
					//assume us and add a 1
					if(strlen($billing_phone) == 9)
					{
						$billing_phone = '1'.$billing_phone;
					}
					else
					{
						$billing_phone = "";
					}
				}
				
				
				$card_number 		= isset($_POST['hms-card-number']) ? $_POST['hms-card-number'] : '';
				$card_cvc 			= isset($_POST['hms-card-cvc']) ? $_POST['hms-card-cvc'] : '';
				$card_exp			= isset($_POST['hms-card-expiry']) ? $_POST['hms-card-expiry'] : '';
		
				// Format card number
				$card_number 	= str_replace(array(' ', '-'), '', $card_number);
				$card_exp 		= str_replace(array(' ', '/'), '', $card_exp);
				
				$card_exp_year 	=	substr($card_exp, -2);
				$card_exp_month =	substr($card_exp, 0, 2);
				$card_exp 		= $card_exp_year.$card_exp_month;

				// test for auth only
                if( $this->authOnly )
                {
                    $response = $txp->authorize($full_name, $card_number, $card_exp, $card_cvc, $amount, $order_number, $billing_address_1, $billing_address_2, $billing_city, $billing_state, $billing_postcode, $billing_country, $billing_email, $billing_phone, $company_name);
                }
                else
                {
                    // auth and settle
                    $response = $txp->authAndSettle($full_name, $card_number, $card_exp, $card_cvc, $amount, $order_number, $billing_address_1, $billing_address_2, $billing_city, $billing_state, $billing_postcode, $billing_country, $billing_email, $billing_phone, $company_name);
                }
			}
			catch(Exception $e)
			{
				wc_add_notice(__('There was a connection error', 'woothemes') . ': "' . $e->getMessage() . '"');
				return;
			}
	
			if ($response['status'] == 'success')
			{
			    if($this->authOnly)
                {
                    $order->add_order_note( __('Payment Authorized', 'woothemes') . ' (Transaction ID: ' . $response['transid'] . ')' );
                    $order->payment_complete();
                }
                else
                {
                    $order->add_order_note( __('Payment Authorized and Settled', 'woothemes') . ' (Transaction ID: ' . $response['transid'] . ')' );
                    $order->payment_complete();
                }


				//$order->remove_order_items();
				//$order->reduce_order_stock(); // depricated
				
				$woocommerce->cart->empty_cart();
					
				// Return thank you page redirect
				return array(
					'result' 	=> 'success',
					//'redirect'	=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, $this->get_return_url($order)))
                    'redirect'  => $this->get_return_url( $order )
				);
			}
			else
			{
				$cancelNote = __('Payment failed', 'woothemes') . ' (Transaction ID: ' . $response['transid'] . '). ' . __('Payment was rejected due to an error', 'woothemes') . ': "' . $response['message'] . '". ';
	
				$order->add_order_note( $cancelNote );
				
				wc_add_notice( sprintf( __( "Payment error - ".$response['message'] , "woothemes" ) ) ,'error' );
			}
			
		}
	
		function validate_fields() {
			global $woocommerce;
												
			$billing_country 	= isset($_POST['billing_country']) ? $_POST['billing_country'] : '';
			
			$card_number 		= isset($_POST['hms-card-number']) ? $_POST['hms-card-number'] : '';
			$card_cvc 			= isset($_POST['hms-card-cvc']) ? $_POST['hms-card-cvc'] : '';
			$card_exp			= isset($_POST['hms-card-expiry']) ? $_POST['hms-card-expiry'] : '';
			
			$card_exp 			= str_replace(array(' ', '/'), '', $card_exp);
			
			$card_exp_year 		=	substr($card_exp, -2);
			$card_exp_month 	=	substr($card_exp, 0, 2);
			$card_exp 			= $card_exp_year.$card_exp_month;
			
			// Check if payment is available for given country and card
			//if (!isset($this->avaiable_countries[$billing_country])) {
			//	wc_add_notice( sprintf( __( "Payment method is not available for your billing country" , "woothemes" ) ) ,'error' );
			//	return false;
			//}
	
			// Check card security code
			if(!ctype_digit($card_cvc)) {
				wc_add_notice( sprintf( __( "Card security code is invalid (only digits are allowed)" , "woothemes" ) ) ,'error' );
				return false;
			}
	
			if(strlen($card_cvc) != 3 && strlen($card_cvc) != 4 ) 
			{
				wc_add_notice( sprintf( __( "Card security code is invalid (wrong length)" , "woothemes" ) ) ,'error' );
				return false;
			}
	
			// Check card expiration data
			if(!ctype_digit($card_exp_month) || !ctype_digit($card_exp_year) ||
				 $card_exp_month > 12 ||
				 $card_exp_month < 1 ||
				 $card_exp_year < date('y') ||
				 $card_exp_year > date('y') + 20
			) {
				wc_add_notice( sprintf( __( "Card expiration date is invalid" , "woothemes" ) ) ,'error' );
				return false;
			}
	
			// Check card number
			$card_number = str_replace(array(' ', '-'), '', $card_number);
	
			if(empty($card_number) || !ctype_digit($card_number)) {
				wc_add_notice( sprintf( __( "Card number is invalid" , "woothemes" ) ) ,'error' );
				return false;
			}
	
			return true;
		}
		
		/**
	     * Validate plugin settings
	     */
		function validate_settings() {
			$currency = get_option('woocommerce_currency');
	
			if (!in_array($currency, array('USD'))) {
				return false;
			}
	
			if (!$this->regKey || !$this->gatewayId) {
				return false;
			}
	
			return true;
		}
		
		/**
	     * Check if this gateway is enabled and available in the user's country
	     */
		function is_available() 
		{	
			if ($this->enabled=="yes")
			{
				if (get_option('woocommerce_force_ssl_checkout')=='no' && $this->settings['testMode'] == 'no') return false;
				
				//$user_country = $this->get_country_code();
				//if(empty($user_country)) {
				//	return false;
				//}
				//return isset($this->avaiable_countries[$user_country]);
				
				return true;
			}
			else
			{
				return false;
			}
		}
		
		/**
	     * Get the users country either from their order, or from their customer data
	     */
		 /*
		function get_country_code() {
			global $woocommerce;
			
			if(isset($_GET['order_id'])) {

			    $order = new WC_Order($_GET['order_id']);
	
				//return $order->billing_country;
				return WC()->get_countries()[ $order->get_shipping_country() ];
				
			}
			
			return true;
		}
		*/
		
		/**
	     * Get user's IP address
	     */
		function get_user_ip() {
			if (!empty($_SERVER['HTTP_X_FORWARD_FOR'])) {
				return $_SERVER['HTTP_X_FORWARD_FOR'];
			} else {
				return $_SERVER['REMOTE_ADDR'];
			}
		}
		
		/**
		* Register as a WooCommerce gateway.
		*/
		public function register_gateway($methods) {
			$methods[] = $this;
			return $methods;
		}
	}
		
	/**
 	* Add the Gateway to WooCommerce
 	**/
	function woocommerce_add_hms_gateway($methods) {
		$methods[] = 'WC_HMS';
		return $methods;
	}
	

	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_hms_gateway' );
}
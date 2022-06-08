<?php
/**
 * Plugin Name: WPJobster Iyzipay Gateway
 * Plugin URI: http://wpjobster.com/
 * Description: This plugin extends Jobster Theme to accept payments with iyzipay.
 * Author: Senthil
 * Author URI: 
 * Version: 1.0
 *
 * Copyright (c) 2016 WPJobster
 *
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums
 */
define( 'WPJOBSTER_IYZIPAY_MIN_PHP_VER', '5.4.0' );


class WPJobster_Iyzipay_Loader {

	/**
	 * @var Singleton The reference the *Singleton* instance of this class
	 */
	private static $instance;
	public $priority, $unique_slug;


	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return Singleton The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * Notices (array)
	 * @var array
	 */
	public $notices = array();


	/**
	 * Protected constructor to prevent creating a new instance of the
	 * *Singleton* via the `new` operator from outside of this class.
	 */
	protected function __construct() {
		$this->priority = 1117;           // 100, 200, 300 [...] are reserved
		$this->unique_slug = 'iyzipay';    // this needs to be unique


		add_action( 'admin_notices',    array( $this, 'admin_notices' ), 15 );
		add_action( 'plugins_loaded',   array( $this, 'init_gateways' ), 0 );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ),
										array( $this, 'plugin_action_links' ) );
		//written by salvatore
		add_filter( 'wpjobster_take_allowed_currency_' . $this->unique_slug,
										array( $this,'get_gateway_currency' ) );

		add_action( 'wpjobster_taketo_' . $this->unique_slug . '_gateway',
										array( $this, 'taketogateway_function' ), 10,2);
										
		add_action( 'wpjobster_processafter_' . $this->unique_slug . '_gateway',
										array( $this, 'processgateway_function' ), 10,2);

		//written by salvatore
		// add_action( 'wpjobster_taketo_' . $this->unique_slug . '_gateway',
		// 								array( $this, 'taketogateway_function' ), 10, 2 );
		// add_action( 'wpjobster_processafter_' . $this->unique_slug . '_gateway',
		// 								array( $this, 'processgateway_function' ), 10, 2 );


		if ( isset( $_POST[ 'wpjobster_save_' . $this->unique_slug ] ) ) {
			add_action( 'wpjobster_payment_methods_action', array( $this, 'save_gateway' ), 11 );
		}
	}
	//written by salvatore
	function get_gateway_currency( $currency ) {
		// if the gateway requires a specific currency you can declare it there
		// currency conversions are done automatically
		$currency = 'USD'; // delete this line if the gateway works with any currency
		return $currency;
	}

	/**
	 * Initialize the gateway. Called very early - in the context of the plugins_loaded action
	 *
	 * @since 1.0.0
	 */
	public function init_gateways() {
		load_plugin_textdomain( 'wpjobster-iyzipay', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );
		add_filter( 'wpjobster_payment_gateways', array( $this, 'add_gateways' ) );
	}


	/**
	 * Add the gateways to WPJobster
	 *
	 * 'action' is called when user resuest to send payment to gateway
	 * 'response_action' is called when any response comes from gateway after payment
	 *
	 * @since 1.0.0
	 */
	public function add_gateways( $methods ) {
		$methods[$this->priority] =
			array(
				'label'           => __( 'Iyzipay', 'wpjobster-iyzipay' ),
				'unique_id'       => $this->unique_slug,
				'action'          => 'wpjobster_taketo_' . $this->unique_slug . '_gateway',
				'response_action' => 'wpjobster_processafter_' . $this->unique_slug . '_gateway',
			);
		add_action( 'wpjobster_show_paymentgateway_forms', array( $this, 'show_gateways' ), $this->priority, 3 );

		return $methods;
	}


	/**
	 * Save the gateway settings in admin
	 *
	 * @since 1.0.0
	 */
	public function save_gateway() {
		global $payment_type_enable_arr;		
		if ( isset( $_POST['wpjobster_save_' . $this->unique_slug] ) ) {
			

			// _enable and _button_caption are mandatory
			update_option( 'wpjobster_' . $this->unique_slug . '_enable',
							trim( $_POST['wpjobster_' . $this->unique_slug . '_enable'] ) );
			update_option( 'wpjobster_' . $this->unique_slug . '_button_caption',
							trim( $_POST['wpjobster_' . $this->unique_slug . '_button_caption'] ) );
							

							
			foreach( $payment_type_enable_arr as $payment_type_enable_key => $payment_type_enable ) {
			
							if(isset($_POST['wpjobster_'.$this->unique_slug.'_enable_'.$payment_type_enable_key]))
								update_option('wpjobster_'.$this->unique_slug.'_enable_'.$payment_type_enable_key, trim($_POST['wpjobster_' . $this->unique_slug . '_enable_'.$payment_type_enable_key]));
			
					}							

			// you can add here any other information that you need from the user
			update_option( 'wpjobster_iyzipay_enablesandbox', trim( $_POST['wpjobster_iyzipay_enablesandbox'] ) );
			update_option( 'wpjobster_iyzipay_apikey',            trim( $_POST['wpjobster_iyzipay_apikey'] ) );
			update_option( 'wpjobster_iyzipay_secretkey',           trim( $_POST['wpjobster_iyzipay_secretkey'] ) );
			update_option( 'wpjobster_iyzipay_submkey',           trim( $_POST['wpjobster_iyzipay_submkey'] ) );			

			update_option( 'wpjobster_iyzipay_success_page',  trim( $_POST['wpjobster_iyzipay_success_page'] ) );
			update_option( 'wpjobster_iyzipay_failure_page',  trim( $_POST['wpjobster_iyzipay_failure_page'] ) );

			echo '<div class="updated fade"><p>' . __( 'Settings saved!', 'wpjobster-iyzipay' ) . '</p></div>';
		}
	}


	/**
	 * Display the gateway settings in admin
	 *
	 * @since 1.0.0
	 */
	public function show_gateways( $wpjobster_payment_gateways, $arr, $arr_pages ) {
		global $payment_type_enable_arr;		
		$tab_id = get_tab_id( $wpjobster_payment_gateways );

		?>
		<div id="tabs<?php echo $tab_id?>">
			<form method="post" action="<?php bloginfo( 'url' ); ?>/wp-admin/admin.php?page=payment-methods&active_tab=tabs<?php echo $tab_id; ?>">
			<table width="100%" class="sitemile-table">


				<tr>
					<?php // _enable and _button_caption are mandatory ?>
					<td valign=top width="22"><?php wpjobster_theme_bullet( __( 'Enable/Disable Iyzipay payment gateway', 'wpjobster-iyzipay') ); ?></td>
					<td width="200"><?php _e( 'Enable:', 'wpjobster-iyzipay' ); ?></td>
					<td><?php echo wpjobster_get_option_drop_down( $arr, 'wpjobster_' . $this->unique_slug . '_enable', 'no' ); ?></td>
				</tr>
			<?php foreach( $payment_type_enable_arr as $payment_type_enable_key => $payment_type_enable ) {
				?>
				  <tr>
					<td valign=top width="22"><?php wpjobster_theme_bullet($payment_type_enable['hint_label']); ?></td>
					<td width="200"><?php echo $payment_type_enable['enable_label']; ?></td>
					<td><?php echo wpjobster_get_option_drop_down($arr, 'wpjobster_'.$this->unique_slug.'_enable_'.$payment_type_enable_key); ?></td>
				  </tr>
			<?php 
			} // end foreach ?>                
				<tr>
					<td valign=top width="22"><?php wpjobster_theme_bullet( __( 'Enable/Disable Iyzipay test mode.', 'wpjobster-iyzipay' ) ); ?></td>
					<td width="200"><?php _e( 'Enable Test Mode:', 'wpjobster-iyzipay' ); ?></td>
					<td><?php echo wpjobster_get_option_drop_down( $arr, 'wpjobster_' . $this->unique_slug . '_enablesandbox', 'no' ); ?></td>
				</tr>



				<tr>
					<?php // _enable and _button_caption are mandatory ?>
					<td valign=top width="22"><?php wpjobster_theme_bullet( __( 'Put the Iyzipay button caption you want user to see on purchase page', 'wpjobster-iyzipay' ) ); ?></td>
					<td><?php _e( 'Iyzipay Button Caption:', 'wpjobster-iyzipay' ); ?></td>
					<td><input type="text" size="45" name="wpjobster_<?php echo $this->unique_slug; ?>_button_caption" value="<?php echo get_option( 'wpjobster_' . $this->unique_slug . '_button_caption' ); ?>" /></td>
				</tr>
				<tr>
					<td valign=top width="22"><?php wpjobster_theme_bullet( __( 'Your Iyzipay API Key', 'wpjobster-iyzipay' ) ); ?></td>
					<td ><?php _e( 'Iyzipay API Key:', 'wpjobster-iyzipay' ); ?></td>
					<td><input type="text" size="45" name="wpjobster_iyzipay_apikey" value="<?php echo get_option( 'wpjobster_iyzipay_apikey' ); ?>" /></td>
				</tr>
				<tr>
					<td valign=top width="22"><?php wpjobster_theme_bullet( __( 'Your Iyzipay Secret Key', 'wpjobster-iyzipay' ) ); ?></td>
					<td ><?php _e( 'Iyzipay Secret Key:', 'wpjobster-iyzipay' ); ?></td>
					<td><input type="text" size="45" name="wpjobster_iyzipay_secretkey" value="<?php echo get_option( 'wpjobster_iyzipay_secretkey' ); ?>" /></td>
				</tr>
				<tr>
					<td valign=top width="22"><?php wpjobster_theme_bullet( __( 'Your Iyzipay Sub Merchant Key', 'wpjobster-iyzipay' ) ); ?></td>
					<td ><?php _e( 'Iyzipay Sub Merchant Key:', 'wpjobster-iyzipay' ); ?></td>
					<td><input type="text" size="45" name="wpjobster_iyzipay_submkey" value="<?php echo get_option( 'wpjobster_iyzipay_submkey' ); ?>" /></td>
				</tr>                
				 <tr>
					<td valign=top width="22"><?php wpjobster_theme_bullet("Please select a page to show when iyzipay payment successful."); ?></td>
					<td><?php _e('Trasaction success page:','wpjobster'); ?></td>
					<td><?php
					echo wpjobster_get_option_drop_down($arr_pages, 'wpjobster_iyzipay_success_page','', ' class="select2" '); ?>
						</td>
				  </tr>
				  <tr>
					<td valign=top width="22"><?php wpjobster_theme_bullet(); ?></td>
					<td><?php _e('Transaction failure page:','wpjobster'); ?></td>
					<td><?php
					echo wpjobster_get_option_drop_down($arr_pages, 'wpjobster_iyzipay_failure_page','', ' class="select2" '); ?> </td>
				  </tr>
				<tr>
					<td></td>
					<td></td>
					<td><input type="submit" name="wpjobster_save_<?php echo $this->unique_slug; ?>" value="<?php _e( 'Save Options', 'wpjobster-iyzipay' ); ?>" /></td>
				</tr>
				</table>
			</form>
		</div>
		<?php
	}


	/**
	 * This function is not required, but it helps making the code a bit cleaner.
	 *
	 * @since 1.0.0
	 */
	public function get_gateway_credentials() {

		$wpjobster_iyzipay_enablesandbox = get_option( 'wpjobster_iyzipay_enablesandbox' );

		if ( $wpjobster_iyzipay_enablesandbox == 'no' ) {
			$iyzipay_payment_url = 'https://api.iyzipay.com/';
		} else {
			$iyzipay_payment_url = 'https://sandbox-api.iyzipay.com/';
		}

		$iyzipay_apikey = get_option( 'wpjobster_iyzipay_apikey' );
		$iyzipay_secretkey = get_option( 'wpjobster_iyzipay_secretkey' );
		$iyzipay_submkey = get_option( 'wpjobster_iyzipay_submkey' );		


		$credentials = array(
			'iyzipay_apikey'      => $iyzipay_apikey,
			'iyzipay_secretkey'       => $iyzipay_secretkey,
			'iyzipay_submkey'       => $iyzipay_submkey,			
			'iyzipay_payment_url' => $iyzipay_payment_url,
		);
		return $credentials;
	}


	/**
	 * Collect all the info that we need and forward to the gateway
	 *
	 * @since 1.0.0
	 */
	public function taketogateway_function( $payment_type, $common_details ) {
		$credentials = $this->get_gateway_credentials();
    
		require_once 'IyzipayBootstrap.php';
 		IyzipayBootstrap::init();
		


		

		$uid                            = $common_details['uid'];
		$wpjobster_final_payable_amount = $common_details['wpjobster_final_payable_amount'];
//		$currency                       = trim($common_details['selected']);
		$currency                       = 'TRY';
		$order_id                       = $common_details['order_id'];
		
		
		



		$order = new ArrayObject();

		$order->total = $wpjobster_final_payable_amount;
		$order->id = $order_id;
		$order->returl = get_bloginfo( 'url' ) . '/?payment_response=iyzipay&payment_type=' . $payment_type;

		$order->billing_email = user( $uid, 'user_email' );
		$order->billing_full_name = user( $uid, 'first_name' ).' '.user( $uid, 'last_name' );
		$order->billing_full_name = trim($order->billing_full_name);
		$order->billing_first_name = user( $uid, 'first_name' );
		$order->billing_last_name = user( $uid, 'last_name' );				
		$order->billing_address_1 = user( $uid, 'address' );						
		$order->billing_city = user( $uid, 'city' );						
		$order->billing_postcode = user( $uid, 'zip' );								
		$order->billing_country = user( $uid, 'country_code' );	
		$order->billing_phone = user( $uid, 'cell_number' );												
				
		//start
     $options = new \Iyzipay\Options();
     $options->setApiKey($credentials['iyzipay_apikey']);
     $options->setSecretKey($credentials['iyzipay_secretkey']);
     $options->setBaseUrl($credentials['iyzipay_payment_url']);



     $order_amount = $order->total;
     $cart_total = $order_amount;

     $return_url = $order->returl;
              

 
	$request = new \Iyzipay\Request\CreateCheckoutFormInitializeRequest();
    $siteLang = explode('_', get_locale());
    $locale = ($siteLang[0] == "tr") ? Iyzipay\Model\Locale::TR : Iyzipay\Model\Locale::EN;	 

	$request->setLocale($locale);
	$request->setConversationId(uniqid());
	$request->setPrice(round($order_amount,2));
	$request->setPaidPrice(round($order_amount,2));

	$request->setCurrency($currency);
	$request->setBasketId($order_id);
	$request->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);	
	$request->setCallbackUrl($return_url);	 

        
        // billing
     $full_name = !empty($order->billing_full_name) ? $order->billing_full_name : 'NOT PROVIDED';
//	 echo "fname".$full_name;
	 if ($full_name == 'NOT PROVIDED') $full_name = !empty($order->billing_first_name) ? $order->billing_first_name : 'NOT PROVIDED';
     $first_name = !empty($order->billing_first_name) ? $order->billing_first_name : 'NOT PROVIDED';
     $last_name = !empty($order->billing_last_name) ? $order->billing_last_name : 'NOT PROVIDED';
     $last_name = !empty($order->billing_last_name) ? $order->billing_last_name : 'NOT PROVIDED';	 
     $phone = !empty($order->billing_phone) ? $order->billing_phone : 'NOT PROVIDED';
     $email = !empty($order->billing_email) ? $order->billing_email : 'NOT PROVIDED';
     $order_date = !empty($order->order_date) ? $order->order_date : 'NOT PROVIDED';
     $modified_date = !empty($order->modified_date) ? $order->modified_date : 'NOT PROVIDED';
     $city = !empty($order->billing_city) ? $order->billing_city : 'NOT PROVIDED';
     $country = !empty($order->billing_country) ? $order->billing_country : 'NOT PROVIDED';
     $postcode = !empty($order->billing_postcode) ? $order->billing_postcode : 'NOT PROVIDED';
     $billing_address = !empty($order->billing_address_1) ? $order->billing_address_1 : 'NOT PROVIDED';	 

        
        //shipping
     $shipping_city = !empty($order->billing_city) ? $order->billing_city : 'NOT PROVIDED';
     $shipping_country = !empty($order->billing_country) ? $order->billing_country : 'NOT PROVIDED';
     $shipping_postcode = !empty($order->billing_postcode) ? $order->billing_postcode : 'NOT PROVIDED';
        
        # create payment buyer dto
	 $buyer = new \Iyzipay\Model\Buyer();	 
     $buyer->setId(uniqid()); 
     $buyer->setName($first_name);	 
     $buyer->setSurname($last_name); 	 
     $buyer->setGsmNumber($phone);	 
     $buyer->setEmail($email); 
     $buyer->setIdentityNumber(uniqid());
//        $buyer->setLastLoginDate($order_date);
//        $buyer->setRegistrationDate($modified_date);
     $buyer->setRegistrationAddress($billing_address);
     $buyer->setIp($_SERVER['REMOTE_ADDR']);
     $buyer->setCity($city);
     $buyer->setCountry($country);
     $buyer->setZipCode($postcode);
     $request->setBuyer($buyer);
      # create billing address dto
     $billingAddress = new \Iyzipay\Model\Address();
     $billingAddress->setContactName($full_name);
     $billingAddress->setCity($city);
     $billingAddress->setCountry($country);
     $billingAddress->setAddress($billing_address);
     $billingAddress->setZipCode($postcode);
     $request->setBillingAddress($billingAddress);
	 

        # create shipping address dto
     $shippingAddress = new \Iyzipay\Model\Address();
     $shippingAddress->setContactName($order->shipping_full_name);
     $shippingAddress->setCity($shipping_city);
     $shippingAddress->setCountry($shipping_country);
     $shippingAddress->setAddress($billing_address);
     $shippingAddress->setZipCode($shipping_postcode);
     $request->setShippingAddress($shippingAddress);
     
     # create payment basket items
      $product_final_price = $order_amount;
            
      $category =  'NOT PROVIDED';
	  
	// added by nancy 
	$SubMerchantKey1 = user($common_details['post']->post_author,'SubMerchantKey');
	if($SubMerchantKey1=="")
	   $SubMerchantKey1 = $credentials['iyzipay_submkey'];
	
  if($payment_type=="topup" || $payment_type=="feature")
    $SubMerchantKey = $credentials['iyzipay_submkey'];
  else
    $SubMerchantKey = $SubMerchantKey1;
    
  //echo "<hr>".$SubMerchantKey."<hr>".$payment_type;exit();
     
	$comm = get_option('wpjobster_percent_fee_taken');
  $comm_pp = $product_final_price * ($comm / 100);
	$comm_pp_final = round($product_final_price, 2) - round($comm_pp, 2);
  //end   
  $basketItems = array();
	$firstBasketItem = new \Iyzipay\Model\BasketItem();
	$firstBasketItem->setId($order->id);
//	$firstBasketItem->setName("Order ID:".$order->id);
	$firstBasketItem->setName($common_details["job_title"]);
	$firstBasketItem->setCategory1("NOT PROVIDED");
	$firstBasketItem->setCategory2("NOT PROVIDED");
	$firstBasketItem->setSubMerchantKey($SubMerchantKey);	//chanegd by nancy
	$firstBasketItem->setSubMerchantPrice($comm_pp_final); //added by nancy
  //$firstBasketItem->setSubMerchantPrice(round($product_final_price, 2));;//commented by nancy		
//	$firstBasketItem->setItemType($request_type);
	$firstBasketItem->setItemType(\Iyzipay\Model\BasketItemType::VIRTUAL);
	$firstBasketItem->setPrice(round($product_final_price, 2));
	$basketItems[0] = $firstBasketItem;
	

	$request->setBasketItems($basketItems);	 
        # make request
		
	$checkoutFormInitialize = \Iyzipay\Model\CheckoutFormInitialize::create($request, $options);


	$response = json_decode($checkoutFormInitialize->getRawResult(), true);
//echo '<pre>';print_r($response);



    $payurl = $response['paymentPageUrl'];

    //header("Location: $payurl");
    echo '<div id="iyzipay-checkout-form" class="popup">'.print_r($checkoutFormInitialize->getCheckoutFormContent()).'</div>';			
	exit;

	}


	/**
	 * Process the response from the gateway and mark the order as completed or failed
	 *
	 * @since 1.0.0
	 */
	function processgateway_function( $payment_type, $details ) {

		$credentials        = $this->get_gateway_credentials();
		$token = $_POST['token'];
         if (empty($token)) {
			$status="fail";
            $err_msg = "Token not found";
         }
						
		require_once 'IyzipayBootstrap.php';
 		IyzipayBootstrap::init();
	    $options = new \Iyzipay\Options();
    	$options->setApiKey($credentials['iyzipay_apikey']);
     	$options->setSecretKey($credentials['iyzipay_secretkey']);
     	$options->setBaseUrl($credentials['iyzipay_payment_url']);
		
        $siteLang = explode('_', get_locale());
        $locale = ($siteLang[0] == "tr") ? Iyzipay\Model\Locale::TR : Iyzipay\Model\Locale::EN;

        $request = new \Iyzipay\Request\RetrieveCheckoutFormRequest();
        $request->setLocale($locale);
        $request->setToken($token);

        $response = \Iyzipay\Model\CheckoutForm::retrieve($request, $options);	
		$api_response = $response->getPaymentStatus();		
		$responseJson = $response->getRawResult();
		$responseAr = json_decode($responseJson, true);			



		if ($api_response) $status = $api_response; 
		$order_id = $response->getBasketId();

		if ( strtolower($status) == 'success' ) {
			$payment_details = "success. Payment ID:".$responseAr['paymentId']; 
			do_action( "wpjobster_" . $payment_type . "_payment_success",
				$order_id,
				$this->unique_slug,
				$payment_details,
				$responseJson
			);
			die();
		} else {
			$payment_details = "Failed - ".$response->getErrorMessage();
			do_action( "wpjobster_" . $payment_type . "_payment_failed",
				$order_id,
				$this->unique_slug,
				$payment_details,
				$responseJson
			);
			die();
		}
	}


	/**
	 * Allow this class and other classes to add slug keyed notices (to avoid duplication)
	 */
	public function add_admin_notice( $slug, $class, $message ) {
		$this->notices[ $slug ] = array(
			'class' => $class,
			'message' => $message
		);
	}


	/**
	 * The primary sanity check, automatically disable the plugin on activation if it doesn't
	 * meet minimum requirements.
	 *
	 * Based on http://wptavern.com/how-to-prevent-wordpress-plugins-from-activating-on-sites-with-incompatible-hosting-environments
	 */
	public static function activation_check() {
		$environment_warning = self::get_environment_warning( true );
		if ( $environment_warning ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( $environment_warning );
		}
	}





	/**
	 * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
	 * found or false if the environment has no problems.
	 */
	static function get_environment_warning( $during_activation = false ) {
		if ( version_compare( phpversion(), WPJOBSTER_IYZIPAY_MIN_PHP_VER, '<' ) ) {
			if ( $during_activation ) {
				$message = __( 'The plugin could not be activated. The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'wpjobster-iyzipay' );
			} else {
				$message = __( 'The Iyzipay Powered by wpjobster plugin has been deactivated. The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'wpjobster-iyzipay' );
			}
			return sprintf( $message, WPJOBSTER_IYZIPAY_MIN_PHP_VER, phpversion() );
		}
		return false;
	}


	/**
	 * Adds plugin action links
	 *
	 * @since 1.0.0
	 */
	public function plugin_action_links( $links ) {
		$setting_link = $this->get_setting_link();
		$plugin_links = array(
			'<a href="' . $setting_link . '">' . __( 'Settings', 'wpjobster-iyzipay' ) . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}


	/**
	 * Get setting link.
	 *
	 * @return string Braintree checkout setting link
	 */
	public function get_setting_link() {
		$section_slug = $this->unique_slug;
		return admin_url( 'admin.php?page=payment-methods&active_tab=tabs' . $section_slug );
	}


	/**
	 * Display any notices we've collected thus far (e.g. for connection, disconnection)
	 */
	public function admin_notices() {
		foreach ( (array) $this->notices as $notice_key => $notice ) {
			echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
			echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
			echo "</p></div>";
		}
	}
}

$GLOBALS['WPJobster_Iyzipay_Loader'] = WPJobster_Iyzipay_Loader::get_instance();
register_activation_hook( __FILE__, array( 'WPJobster_Iyzipay_Loader') );

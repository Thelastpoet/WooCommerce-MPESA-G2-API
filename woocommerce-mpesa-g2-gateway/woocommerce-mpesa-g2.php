<?php
/* Mpesa G2 Payment Gateway Class */
class SPYR_MPESA_G2 extends WC_Payment_Gateway {

	// Setup our Gateway's id, description and other values
	function __construct() {

		// The global ID for this Payment method
		$this->id = "SPYR_MPESA_G2";

		// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		$this->method_title = __( "Mpesa G2", 'spyr-mpesa-G2' );

		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
		$this->method_description = __( "Mpesa G2 Payment Gateway Plug-in for WooCommerce", 'spyr-mpesa-G2' );

		// The title to be used for the vertical tabs that can be ordered top to bottom
		$this->title = __( "Mpesa G2", 'spyr-mpesa-G2' );

		// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
		$this->icon = null;

		// Bool. Can be set to true if you want payment fields to show on the checkout 
		// if doing a direct integration, which we are doing in this case
		$this->has_fields = true;

		// Supports the default credit card form
		$this->supports = array( 'default_credit_card_form' );

		// This basically defines your settings which are then loaded with init_settings()
		$this->init_form_fields();

		// After init_settings() is called, you can get the settings and load them into variables, e.g:
		// $this->title = $this->get_option( 'title' );
		$this->init_settings();
		
		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}
		
		// Lets check for SSL
		add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );
		
		// Save settings
		if ( is_admin() ) {
			// Versions over 2.0
			// Save our administration options. Since we are not going to be doing anything special
			// we have not defined 'process_admin_options' in this class so the method in the parent
			// class will be used instead
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}		
	} // End __construct()

	// Build the administration fields for this specific Gateway
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'spyr-mpesa-G2' ),
				'label'		=> __( 'Enable this payment gateway', 'spyr-mpesa-G2' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'spyr-mpesa-G2' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'spyr-mpesa-G2' ),
				'default'	=> __( 'Credit card', 'spyr-mpesa-G2' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'spyr-mpesa-G2' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'spyr-mpesa-G2' ),
				'default'	=> __( 'Pay securely using your credit card.', 'spyr-mpesa-G2' ),
				'css'		=> 'max-width:350px;'
			),
			'api_login' => array(
				'title'		=> __( 'Mpesa API Login', 'spyr-mpesa-G2' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the API Login provided by Mpesa when you signed up for an account.', 'spyr-mpesa-G2' ),
			),
			'trans_key' => array(
				'title'		=> __( 'Mpesa Transaction Key', 'spyr-mpesa-G2' ),
				'type'		=> 'password',
				'desc_tip'	=> __( 'This is the Transaction Key provided by Mpesa when you signed up for an account.', 'spyr-mpesa-G2' ),
			),
			'environment' => array(
				'title'		=> __( 'Mpesa Test Mode', 'spyr-mpesa-G2' ),
				'label'		=> __( 'Enable Test Mode', 'spyr-mpesa-G2' ),
				'type'		=> 'checkbox',
				'description' => __( 'Place the payment gateway in test mode.', 'spyr-mpesa-G2' ),
				'default'	=> 'no',
			)
		);		
	}
	
	// Submit payment and handle response
	public function process_payment( $order_id ) {
		global $woocommerce;
		
		// Get this Order's information so that we know
		// who to charge and how much
		$customer_order = new WC_Order( $order_id );
		
		// Are we testing right now or is it a real transaction
		$environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';

		// Decide which URL to post to
		$environment_url = ( "FALSE" == $environment ) 
						   ? 'https://secure.Mpesa/gateway/transact.dll'
						   : 'https://test.Mpesa/gateway/transact.dll';

		// This is where the fun stuff begins
		$payload = array(
			// Mpesa Credentials and API Info
			"x_tran_key"           	=> $this->trans_key,
			"x_login"              	=> $this->api_login,
			"x_version"            	=> "3.1",
			
			// Order total
			"x_amount"             	=> $customer_order->order_total,
			
			// Credit Card Information
			"x_card_num"           	=> str_replace( array(' ', '-' ), '', $_POST['spyr-mpesa_G2-card-number'] ),
			"x_card_code"          	=> ( isset( $_POST['spyr-mpesa_G2-card-cvc'] ) ) ? $_POST['spyr-mpesa_G2-card-cvc'] : '',
			"x_exp_date"           	=> str_replace( array( '/', ' '), '', $_POST['spyr-mpesa_G2-card-expiry'] ),
			
			"x_type"               	=> 'AUTH_CAPTURE',
			"x_invoice_num"        	=> str_replace( "#", "", $customer_order->get_order_number() ),
			"x_test_request"       	=> $environment,
			"x_delim_char"         	=> '|',
			"x_encap_char"         	=> '',
			"x_delim_data"         	=> "TRUE",
			"x_relay_response"     	=> "FALSE",
			"x_method"             	=> "CC",
			
			// Billing Information
			"x_first_name"         	=> $customer_order->billing_first_name,
			"x_last_name"          	=> $customer_order->billing_last_name,
			"x_address"            	=> $customer_order->billing_address_1,
			"x_city"              	=> $customer_order->billing_city,
			"x_state"              	=> $customer_order->billing_state,
			"x_zip"                	=> $customer_order->billing_postcode,
			"x_country"            	=> $customer_order->billing_country,
			"x_phone"              	=> $customer_order->billing_phone,
			"x_email"              	=> $customer_order->billing_email,
			
			// Shipping Information
			"x_ship_to_first_name" 	=> $customer_order->shipping_first_name,
			"x_ship_to_last_name"  	=> $customer_order->shipping_last_name,
			"x_ship_to_company"    	=> $customer_order->shipping_company,
			"x_ship_to_address"    	=> $customer_order->shipping_address_1,
			"x_ship_to_city"       	=> $customer_order->shipping_city,
			"x_ship_to_country"    	=> $customer_order->shipping_country,
			"x_ship_to_state"      	=> $customer_order->shipping_state,
			"x_ship_to_zip"        	=> $customer_order->shipping_postcode,
			
			// Some Customer Information
			"x_cust_id"            	=> $customer_order->user_id,
			"x_customer_ip"        	=> $_SERVER['REMOTE_ADDR'],
			
		);
	
		// Send this payload to Mpesa for processing
		$response = wp_remote_post( $environment_url, array(
			'method'    => 'POST',
			'body'      => http_build_query( $payload ),
			'timeout'   => 90,
			'sslverify' => false,
		) );

		if ( is_wp_error( $response ) ) 
			throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'spyr-mpesa-G2' ) );

		if ( empty( $response['body'] ) )
			throw new Exception( __( 'Mpesa\'s Response was empty.', 'spyr-mpesa-G2' ) );
			
		// Retrieve the body's resopnse if no errors found
		$response_body = wp_remote_retrieve_body( $response );

		// Parse the response into something we can read
		foreach ( preg_split( "/\r?\n/", $response_body ) as $line ) {
			$resp = explode( "|", $line );
		}

		// Get the values we need
		$r['response_code']             = $resp[0];
		$r['response_sub_code']         = $resp[1];
		$r['response_reason_code']      = $resp[2];
		$r['response_reason_text']      = $resp[3];

		// Test the code to know if the transaction went through or not.
		// 1 or 4 means the transaction was a success
		if ( ( $r['response_code'] == 1 ) || ( $r['response_code'] == 4 ) ) {
			// Payment has been successful
			$customer_order->add_order_note( __( 'Mpesa payment completed.', 'spyr-mpesa-G2' ) );
												 
			// Mark order as Paid
			$customer_order->payment_complete();

			// Empty the cart (Very important step)
			$woocommerce->cart->empty_cart();

			// Redirect to thank you page
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $customer_order ),
			);
		} else {
			// Transaction was not succesful
			// Add notice to the cart
			wc_add_notice( $r['response_reason_text'], 'error' );
			// Add note to the order for your reference
			$customer_order->add_order_note( 'Error: '. $r['response_reason_text'] );
		}

	}
	
	// Validate fields
	public function validate_fields() {
		return true;
	}
	
	// Check if we are forcing SSL on checkout pages
	// Custom function not required by the Gateway
	public function do_ssl_check() {
		if( $this->enabled == "yes" ) {
			if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
				echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";	
			}
		}		
	}

} // End of spyr-mpesa_G2
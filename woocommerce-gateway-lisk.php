<?php
/**
 * Plugin Name: WooCommerce Lisk Gateway
 * Plugin URI: https://www.Crypto_embassy.com/?p=3343
 * Description: Clones the "Cheque" gateway to create another manual / lisk payment method; can be used for testing as well.
 * Author: Crypto_embassy
 * Author URI: http://www.crypto-embassy.com/
 * Version: 1.0.2
 * Text Domain: wc-gateway-lisk
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2015-2020 Crypto_embassy, Inc. (contact@crypto-embassy.com) and WooCommerce
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-Lisk
 * @author    Crypto_embassy
 * @category  Admin
 * @copyright Copyright (c) 2015-2016, Crypto_embassy, Inc. and WooCommerce
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 * This lisk gateway forks the WooCommerce core "Cheque" payment gateway to create another lisk payment method.
 */
 
defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}


/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + lisk gateway
 */
function wc_lisk_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_Lisk';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_lisk_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_lisk_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=lisk_gateway' ) . '">' . __( 'Configure', 'wc-gateway-lisk' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_lisk_gateway_plugin_links' );


/**
 * Lisk Payment Gateway
 *
 * Provides an Lisk Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_Lisk
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		Crypto_embassy
 */
add_action( 'plugins_loaded', 'wc_lisk_gateway_init', 11 );

function wc_lisk_gateway_init() {

	class WC_Gateway_Lisk extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'lisk_gateway';
			$this->icon               = apply_filters('woocommerce_lisk_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Lisk', 'wc-gateway-lisk' );
			$this->method_description = __( 'Allows lisk payments. Orders are marked as "on-hold" when received.', 'wc-gateway-lisk' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
			$this->storePubKey = $this->get_option( 'storePubKey', $this->storePubKey );
		  
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		  
			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}
	
	
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_lisk_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'wc-gateway-lisk' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Lisk Payment', 'wc-gateway-lisk' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'wc-gateway-lisk' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-lisk' ),
					'default'     => __( 'Lisk Payment', 'wc-gateway-lisk' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'wc-gateway-lisk' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-lisk' ),
					'default'     => __( 'Please pay LSK using the indications below.', 'wc-gateway-lisk' ),
					'desc_tip'    => true,
				),

				'storePubKey' => array(
					'title'       => __( 'Store Public Key', 'wc-gateway-lisk' ),
					'type'        => 'text',
					'description' => __( 'Please insert your lisk public key for receiving client payments.', 'wc-gateway-lisk' ),
					'default'     => __( '', 'wc-gateway-lisk' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-gateway-lisk' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-gateway-lisk' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			) );
		}
	
	
		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}

		public function payment_scripts() {
 
			// // we need JavaScript to process a token only on cart/checkout pages, right?
			// if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			// 	return;
			// }
		 
			// if our payment gateway is disabled, we do not have to enqueue JS too
			// if ( 'no' === $this->enabled ) {
			// 	return;
			// }
		 
		 
			// // do not work with card detailes without SSL unless your website is in a test mode
			// if ( ! $this->testmode && ! is_ssl() ) {
			// 	return;
			// }
		 
		 
			// and this is our custom JS in your plugin directory that works with token.js
			wp_register_script( 'woocommerce_lisk', plugins_url( '/assets/js/jquery-qrcode-0.17.0.min.js', __FILE__ ), array( 'jquery' ) );
		 
			// // in most payment processors you have to use PUBLIC KEY to obtain a token
			// wp_localize_script( 'woocommerce_misha', 'misha_params', array(
			// 	'publishableKey' => $this->publishable_key
			// ) );
		 
			wp_enqueue_script( 'woocommerce_lisk' );
		 
		}
	
	
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}

		public function payment_fields() {
 
			// ok, let's display some description before the payment form
			if ( $this->description ) {
				// you can instructions for test mode, I mean test card numbers etc.
				if ( $this->testmode ) {
					$this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#" target="_blank" rel="noopener noreferrer">documentation</a>.';
					$this->description  = trim( $this->description );
				}
				// display the description with <p> tags etc.
				echo wpautop( wp_kses_post( $this->description ) );
			}

			global $post;
			global $woocommerce;
			global $order;
			$total = $woocommerce->cart->get_cart_total();
			$totalNumber = (float) preg_replace( '/[^0-9\.]/', '', $woocommerce->cart->get_cart_total()  );
			
			ob_start();
			var_dump($woocommerce->cart);
			$obj = ob_get_contents();
			ob_end_clean();

			
			$parts = explode(' ', $obj);
			$id = str_replace('object(WC_Cart)#', '', $parts[0]);
			$reference = "woo#o-" . $id . "-u"  . get_current_user_id();

			$amount = $totalNumber; // Convert EUR TO LSK here


			ob_start(); 
			?>
			<script>
				jQuery(document).ready(function($){
					$( document ).on( "LiskPayEvent", {}, function( event, liskPayAction ) {
						console.log( "Running event", event, liskPayAction );
						if(liskPayAction === "hidePaymentConfirm") {
							jQuery('#place_order').hide();
						} else if(liskPayAction === "PaymentConfirm") {
							jQuery('#place_order').trigger('click');
						}
					});
				});
			</script>
			<script type="text/x-template" id="app-template">
				<div>
					<a href="#" @click.stop="startPayment" :class="btnClasses">{{ btnLabel }}</a>
					<div class="lisk-pay-modal" v-show="showModal">
						<div class="inside-modal">
							<span @click.stop="cancelPayment" class="close-icon-btn"></span>
							<div class="modal-content">
								<div class="l-row modal-title">
									<div class="payment-meta">
										<div class="meta-row exchange-rate">
											1 LSK = {{ unitValue }}
										</div>
										<div class="meta-row ref">

										</div>
									</div>
									<h3><img class="liskLogo" :src="logo" alt="" /> <span>Pay</span></h3>
								</div>
								<div class="l-row progress" :style="progressStateStyle">
									<span class="progress__state">{{ paymentStatusLabel }}</span>
									<div class="progress__track-position">
										<div class="progress__track"></div>
										<div :style="progressStyle" class="progress__bar"></div>
									</div>
									<div class="progress__label">{{ progress }}s</div>
								</div>

								
								<nav class="l-row tab-selector-row">
									<div class="tab-selector-position">
										<ul class="tab-selector">
											<template v-for="tab in tabs">
												<li :class="{ 'active': isCurrentTab(tab) }"><a href="#" @click="selectTab(tab)">{{ tab.label }}</a></li>
											</template>
										</ul>
									</div>
								</nav>

								
								<!-- <input class="app__range" type="range" min="0" max="100" step="1" v-model="progress"> -->
								<div v-show="isCurrentTab(tabs.order)" class="l-tab l-row order">
									<div class="modal-order-info">
										<h3 class="payment-warning">To complete your payment please send exactly<br>
										<b>{{ liskPayData.amount }} LSK</b> using the methods bellow</h3>
										<h4 class="payment-info-ref">Your order reference is : {{ liskPayData.ref }}</h4>
										<div class='payment-info'>
											<div class='option-1 payment-option'>
												<b>Pay with LiskHub :</b> <a :class="liskBtnDefaultClasses" :href="liskPayData.paiementLink">Pay {{ liskPayData.amount }} LSK with liskHub Now</a><br><br>
											</div>
											<div class='option-2 payment-option'>
												<b>Manual payment</b><h4>Send <b>{{ liskPayData.amount }}</b> LSK payment to the following address : </h4>
												<ul class='payment-fields'>
													<li class="input-w-to"><label><span class='l-input-label'>PayTo adress : </span><input disabled='disabled' type='text' :value="liskPayData.payToKey"></label></li>
													<li><label><span class='l-input-label'>Reference : </span><input disabled='disabled' type='text' :value="liskPayData.ref"></label></li>
												</ul>
											</div>
										</div>
									</div>
									<qrcode class="lsk-qr" :value="payLink" :options="qrOptions"></qrcode>
								</div>
								
								<div v-show="isCurrentTab(tabs.markets)"  class="l-tab l-row markets">
									<div class="markets-wrap">
										<div class="flex-row flex-middle">
											<div class="dropdown">
												<div class="form-input text-nowrap shadow-box">▼ {{ limit }}</div>
												<ul>
												<li @click="setLimit( 0 )"><span class="text-faded">Show:</span> All</li>
												<li @click="setLimit( 10 )"><span class="text-faded">Show:</span> 10</li>
												<li @click="setLimit( 20 )"><span class="text-faded">Show:</span> 20</li>
												<li @click="setLimit( 50 )"><span class="text-faded">Show:</span> 50</li>
												<li @click="setLimit( 100 )"><span class="text-faded">Show:</span> 100</li>
												</ul>
											</div>
											<div class="dropdown">
												<div class="form-input text-nowrap shadow-box">▼ {{ sortLabel }}</div>
												<ul>
												<li @click="sortBy( 'token', 'asc' )"><span class="text-faded">Sort:</span> Token</li>
												<li @click="sortBy( 'close', 'desc' )"><span class="text-faded">Sort:</span> Price</li>
												<li @click="sortBy( 'assetVolume', 'desc' )"><span class="text-faded">Sort:</span> Volume</li>
												<li @click="sortBy( 'percent', 'desc' )"><span class="text-faded">Sort:</span> Percent</li>
												<li @click="sortBy( 'change', 'desc' )"><span class="text-faded">Sort:</span> Change</li>
												<li @click="sortBy( 'trades', 'desc' )"><span class="text-faded">Sort:</span> Trades</li>
												</ul>
											</div>
											<div class="dropdown">
												<div class="form-input text-nowrap shadow-box">▼ {{ asset }}</div>
												<ul>
												<li @click="filterAsset( 'BTC' )"><span class="text-faded">Asset:</span> BTC</li>
												<li @click="filterAsset( 'ETH' )"><span class="text-faded">Asset:</span> ETH</li>
												<li @click="filterAsset( 'BNB' )"><span class="text-faded">Asset:</span> BNB</li>
												<li @click="filterAsset( 'USDT' )"><span class="text-faded">Asset:</span> USDT</li>
												</ul>
											</div>
										</div>
										<div class="main-grid-list">
											<template v-for="c in coinsList" :key="c.symbol">
												<template v-if="c.symbol.includes('LSK')">
													<div class="main-grid-item" :class="c.style">
														<div class="main-grid-info flex-row flex-top flex-stretch">
															<div class="push-right">
															<img :src="c.icon" :alt="c.pair" />
															</div>
															<div class="flex-1 shadow-text">
															<div class="flex-row flex-top flex-space">
																<div class="text-left text-clip push-right">
																<h1 class="text-primary text-clip">{{ c.token }}<small class="text-faded text-small text-condense">/{{ c.asset }}</small></h1>
																<h2 class="text-bright text-clip">{{ c.close | toFixed( asset ) }}</h2>
																</div>
																<div class="text-right">
																<div class="color text-big text-clip">{{ c.arrow }} {{ c.sign }}{{ c.percent | toFixed( 2 ) }}%</div>
																<div class="text-clip">{{ c.sign }}{{ c.change | toFixed( asset ) }} <small class="text-faded">24h</small></div>
																<div class="text-clip">{{ c.assetVolume | toMoney }} <small class="text-faded">Vol</small></div>
																</div>
															</div>
															</div>
														</div>
														<div class="main-grid-chart">
															<linechart :width="600" :height="40" :values="c.history"></linechart>
														</div>
													</div>
												</template>
											</template>
										</div>
									</div>
								</div>
								<!-- socket loader -->
								<div class="loader-wrap" :class="{ 'visible': loaderVisible }">
								<div class="loader-content">
									<div v-if="status === 0"><i>📡</i> <br /> Connecting to Socket API ...</div>
									<div v-else-if="status === 1"><i>💬</i> <br /> Waiting for data from Socket API ...</div>
									<div v-else-if="status === 2"><i>😃</i> <br /> Connected to the Socket API</div>
									<div v-else-if="status === -1"><i>😡</i> <br /> Error connecting to the Socket API</div>
								</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</script>
			<script>
				var liskPayData = {
					ref: '<?php echo $reference; ?>',
					pid: '<?php echo $id; ?>',
					amount: '<?php echo $amount; ?>',
					payToKey: '<?php echo $this->storePubKey; ?>',
					liskLogo: '<?php echo plugins_url( '/assets/img/logo.svg' , __FILE__ ); ?>',
					defaultCurrency: 'eur',
					defaultAsset: 'USDT',
				};
			</script>
			<?php $tpl = ob_get_contents();
			ob_end_clean();
			echo $tpl;

			echo "<div id='lisk-pay'><app-component></app-component></div>";

			echo "<script type='text/javascript' src='" . plugins_url( '/assets/js/qr-vue.js' , __FILE__ ) . "'></script>";
			echo "<script type='text/javascript' src='" . plugins_url( '/assets/js/lisk-pay.js?p=' . mktime(), __FILE__ ) . "'></script>";
			
			?>
			<?php

			
		 
			// I will echo() the form, but you can close PHP tags and print it directly in HTML
			// echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
		 
			// Add this action hook if you want your custom payment gateway to support it
			// do_action( 'woocommerce_credit_card_form_start', $this->id );
		 
			// I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
			// echo '<div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
			// 	<input id="misha_ccNo" type="text" autocomplete="off">
			// 	</div>
			// 	<div class="form-row form-row-first">
			// 		<label>Expiry Date <span class="required">*</span></label>
			// 		<input id="misha_expdate" type="text" autocomplete="off" placeholder="MM / YY">
			// 	</div>
			// 	<div class="form-row form-row-last">
			// 		<label>Card Code (CVC) <span class="required">*</span></label>
			// 		<input id="misha_cvv" type="password" autocomplete="off" placeholder="CVC">
			// 	</div>';
			echo '<div class="clear"></div>';
		 
			// do_action( 'woocommerce_credit_card_form_end', $this->id );
		 
		 
		}
	
	
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
	
			$order = wc_get_order( $order_id );
			
			// Mark as on-hold (we're awaiting the payment)
			$order->update_status( 'on-hold', __( 'Awaiting lisk payment', 'wc-gateway-lisk' ) );
			
			// Reduce stock levels
			$order->reduce_order_stock();
			
			// Remove cart
			WC()->cart->empty_cart();
			
			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}
	
  } // end \WC_Gateway_Lisk class
}
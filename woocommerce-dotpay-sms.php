<?php
/*
  Plugin Name: WooCommerce Dotpay SMS Gateway
  Plugin URI: https://nicc0.pl/dotpay-sms-gateway-to-woocommerce/
  Description: Fast and secure SMS payment gateway for Dotpay (Poland) to WooCommerce
  Version: 1.0.0
  Author:  Daniel "Nicc0" Tęcza (kontakt@nicc0.pl)
  Author URI: mailto:kontakt@nicc0.pl
  Text Domain: dotpay-sms-payment-gateway
  Last modified: 2016-10-11 by kontakt@nicc0.pl
 */

if (!defined('ABSPATH')) {
    exit;
}

function init_dotpay_sms_defines() {
    define('DOTPAY_SMS_STATUS_PTITLE', __("Checking payment status...", 'dotpay-sms-payment-gateway'));
    define('DOTPAY_SMS_STATUS_PNAME', "dotpay_sms_order_status");

    define('DOTPAY_SMS_PAYINFO_PTITLE', __("Details of your payment", 'dotpay-sms-payment-gateway'));
    define('DOTPAY_SMS_PAYINFO_PNAME', "dotpay_sms_payment_info");

    define('DOTPAY_SMS_GATEWAY_ONECLICK_TAB_NAME', 'dotpay_sms_oneclick_cards');
    define('DOTPAY_SMS_GATEWAY_INSTRUCTIONS_TAB_NAME', 'dotpay_sms_instructions');

    define('WOOCOMMERCE_DOTPAY_SMS_GATEWAY_DIR', plugin_dir_path(__FILE__));
    define('WOOCOMMERCE_DOTPAY_SMS_GATEWAY_URL', plugin_dir_url(__FILE__));
}

function init_woocommerce_dotpay_sms() {
    init_dotpay_sms_defines();
}

function dotpay_sms_admin_enqueue_scripts($hook) {
    if($hook != 'woocommerce_page_wc-settings') {
        return;
    }
    wp_enqueue_script( 'admin-script', plugin_dir_url( __FILE__ ) . 'resources/js/admin.js' );
}

function init_dotpay_sms_gateway() {
    //$plugin_dir = basename( dirname(__FILE__) ).'/langs';
    //load_plugin_textdomain( 'dotpay-sms-payment-gateway', false, $plugin_dir );
    init_dotpay_sms_defines();
    include_once(plugin_dir_path(__FILE__).'/includes/Functions.class.php');
    include_once(plugin_dir_path(__FILE__).'/includes/Curl.class.php');
    include_once(plugin_dir_path(__FILE__).'/gateway/SMS.php');
    
    new DotPay_SMS_Functions();
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action( 'plugins_loaded', 'init_dotpay_sms_gateway' );
    add_action( 'init', 'init_woocommerce_dotpay_sms' );

    function add_dotpay_sms_payment_class($methods) {
        $methods[] = 'DotPay_SMS_Payment_Gateway';
        return $methods;
    }

    add_filter( 'woocommerce_payment_gateways', 'add_dotpay_sms_payment_class' );
    add_action( 'admin_enqueue_scripts', 'dotpay_sms_admin_enqueue_scripts' );
}

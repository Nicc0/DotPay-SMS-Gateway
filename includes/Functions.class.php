<?php
/**
 * 
 * @author    Daniel "Nicc0" Tęcza <kontakt@nicc0.pl>
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License (GPL 3.0)
 * 
 */

class DotPay_SMS_Functions {
    function __construct() {
        add_action( 'woocommerce_product_options_general_product_data', array($this, 'dotpay_sms_custom_general_field') );
        add_action( 'woocommerce_process_product_meta',                 array($this, 'dotpay_sms_custom_general_field_save') );
        add_action( 'woocommerce_review_order_after_cart_contents',     array($this, 'dotpay_sms_add_hidden_cart') );
        add_action('woocommerce_checkout_process',                      array($this, 'dotpay_sms_checkout_field_process') );
        add_action( 'woocommerce_checkout_update_order_meta',           array($this, 'dotpay_sms_checkout_field_update_order_meta') );
        add_filter( 'woocommerce_available_payment_gateways',           array($this, 'dotpay_sms_filter_gateways') );
        add_filter( 'woocommerce_cart_item_class',                      array($this, 'dotpay_sms_cart_item_class'), 30, 3 );
        add_filter( 'woocommerce_checkout_fields' ,                     array($this, 'dotpay_sms_checkout_field_process') );
    }
    
    /**
     * Remove SMS Gateway when products in cart is greater then one
     * 
     * @global type $woocommerce
     * @param type $gateways
     * @return type
     */
    public function dotpay_sms_filter_gateways($gateways) {
        global $woocommerce;

        if (count($woocommerce->cart->cart_contents) == 1) {
            $product = array_shift(array_values($woocommerce->cart->cart_contents));
            $post_meta = get_post_meta($product['product_id']);
            $services = get_option('dotpay_sms_services');
            $service = $post_meta['_dotpay_sms_service'][0];

            if (array_key_exists('_dotpay_sms_service', $post_meta) && array_key_exists($service, $services)) {
                return $gateways;
            }
        }

        unset($gateways['dotpay_sms']);
        return $gateways;
    }

    public function dotpay_sms_checkout_field_update_order_meta( $order_id ) {
        if ( !empty( $_POST['dotpay_sms_code'] ) && $_POST['payment_method'] == 'dotpay_sms' ) {
            update_post_meta( $order_id, 'dotpay_sms_code', sanitize_text_field( $_POST['dotpay_sms_code'] ) );
        }
    }
    
    /**
     * 
     */
    public function dotpay_sms_checkout_field_process() {
        if ( empty( $_POST['dotpay_sms_code'] ) && $_POST['payment_method'] == 'dotpay_sms' ) {
            wc_add_notice( __( 'Please enter the custom field.' ), 'error' );
        }
    }
    
    /**
     * 
     */
    public function dotpay_sms_checkout_fields($fields) {
        $fields['dotpay'] = array(
            "code" => array(
                "type" => "text",
                "required" => 0,
                "placeholder" => "Kod SMS"
            )
        );
        
        return $fields;
    }
    
    /**
     * 
     * @param string $output
     * @param type $item
     * @param type $item_key
     * @return string
     */
    public function dotpay_sms_cart_item_class($output, $item, $item_key) {
        $post_meta = get_post_meta($item['product_id']);
        $services = get_option('dotpay_sms_services');
        $service = $post_meta['_dotpay_sms_service'][0];

        if (array_key_exists('_dotpay_sms_service', $post_meta) && array_key_exists($service, $services)) {
            $output .= ' dotpay-sms-product';
        }

        return $output;
    }

    /**
     * 
     */
    public function dotpay_sms_add_hidden_cart() {
        if (count(WC()->cart->get_cart()) == 1) {
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                $post_meta = get_post_meta($cart_item['product_id']);
                $services = get_option('dotpay_sms_services');
                $service = $post_meta['_dotpay_sms_service'][0];
                if (array_key_exists('_dotpay_sms_service', $post_meta) && array_key_exists($service, $services)) {
                    wp_enqueue_script('dotpay-sms-javascript', WOOCOMMERCE_DOTPAY_SMS_GATEWAY_URL . 'resources/js/checkout.js', array('jquery'), '1.0.0', true);
                    $price = $services[$service]['price'];
                    $vat = $price * 23 / 100;
                    ?>
                                    <tr id="dotpay-sms-price" style="display: none;">
                                        <td class="product-name">
                    <?php echo apply_filters('woocommerce_cart_item_name', $_product->get_title(), $cart_item, $cart_item_key) . '&nbsp;'; ?>
                    <?php echo apply_filters('woocommerce_checkout_cart_item_quantity', ' <strong class="product-quantity">' . sprintf('&times; %s', $cart_item['quantity']) . '</strong>', $cart_item, $cart_item_key); ?>
                    <?php echo WC()->cart->get_item_data($cart_item); ?>
                                        </td>
                                        <td class="product-total">
                    <?php echo apply_filters('woocommerce_cart_item_subtotal', wc_price($price + $vat), $cart_item, $cart_item_key); ?>
                                        </td>
                                    </tr>
                    <?php
                }
            }
        }
    }
    
    /**
     * Add custom product field to woocommerce
     * 
     * @global type $post
     */
    function dotpay_sms_custom_general_field() {
        global $post;
        error_log('dotpay_sms_custom_general_field' . PHP_EOL, 3, '/home/minecraft/err.log');
        echo '<div class="options_group">';

        $post_meta = get_post_meta($post->ID, '_dotpay_sms_service');
        $fields = array(
            'id' => 'dotpay_sms_service',
            'label' => __('SMS Payment Service', 'dotpay-sms-payment-gateway'),
            'clear' => true,
            'placeholder' => $post_meta,
            'options' => array()
        );

        $services = get_option('dotpay_sms_services');
        $fields['options']['null'] = __('Not Selected', 'dotpay-sms-payment-gateway');

        foreach ($services as $key => $value) {
            $fields['options'][$key] = $value['name'];
        }

        woocommerce_wp_select($fields);

        echo '</div>';
    }

    /**
     * Save Custom fields in general option
     * 
     * @param type $post_id
     */
    function dotpay_sms_custom_general_field_save($post_id) {
        $dotpay_sms_service = $_POST['dotpay_sms_service'];
        if (!empty($dotpay_sms_service) && esc_attr($dotpay_sms_service) != 'null') {
            update_post_meta($post_id, '_dotpay_sms_service', esc_attr($dotpay_sms_service));
        }
    }
}

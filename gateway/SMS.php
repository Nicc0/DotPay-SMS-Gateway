<?php
/**
 * 
 * @author    Daniel "Nicc0" Tęcza <kontakt@nicc0.pl>
 * @license   https://www.gnu.org/licenses/gpl-3.0.txt GNU General Public License (GPL 3.0)
 * 
 */

class DotPay_SMS_Payment_Gateway extends WC_Payment_Gateway {
    const DOTPAY_URL = 'https://ssl.dotpay.pl/check_code.php';
    
    const STATUS_COMPLETED = 'completed';
    const STATUS_REJECTED = 'failed';
    const STATUS_DEFAULT = 'pending';
    
    public $dotpayAgreements = true;
    
    /**
     * Prepare gateway
     */
    public function __construct() {
        $this->id = 'dotpay_sms';
        $this->title = 'Dotpay SMS';
        $this->icon = $this->getIcon();
        $this->description = $this->render('admin_header.phtml');
        $this->order_button_text  = __( 'Wpisałem kod', 'dotpay-sms-payment-gateway' );
        $this->has_fields = true;
        $this->init_form_fields();
        $this->init_settings();
        $this->enabled = ($this->isEnabled()) ? 'yes' : 'no';
        $this->method_title = 'Dotpay SMS';
        //$this->method_description = $this->render('admin_header.phtml');
        $this->addActions();
        $this->addOptions();
    }

    /**
     * Add actions
     */
    protected function addActions() {
        add_action( 'woocommerce_api_'.strtolower($this->id).'_form', array($this, 'getRedirectForm') );
        add_action( 'woocommerce_api_'.strtolower($this->id).'_confirm', array($this, 'confirmPayment') );
        add_action( 'woocommerce_api_'.strtolower($this->id).'_status', array($this, 'checkStatus') );
        add_action( 'woocommerce_update_options_payment_gateways_'.$this->id, array( $this, 'save_sms_services' ) );
    }
    
    /**
     * Add custom options
     */
    protected function addOptions() {
        $this->sms_services = get_option( 'dotpay_sms_services',
            array(
                array(
                    'name'   => $this->get_option( 'name' ),
                    'short'  => $this->get_option( 'short' ),
                    'code'   => $this->get_option( 'code' ),
                    'number' => $this->get_option( 'number' ),
                    'price'  => $this->get_option( 'price' ),
                    'type'   => $this->get_option( 'type' ),
                    'single' => $this->get_option( 'single' )
                )
            )
        );
    }
    
    public function getPaymentInstruction() {
        $service = $this->getService();
        $sms = $service['services'][$service['service']];
        $code = $sms['code'];
        $number = $sms['number'];
        printf( __( 'Wyślij <strong>SMS</strong> o treści <strong>AP.%s</strong> na numer <strong>%d</strong>. Otrzymany kod wpisz w poniższe pole!', 'dotpay-sms-payment-gateway' ), $code, $number );
    }
    
    public function payment_fields() {
        include($this->getTemplatesPath()."standard_form.phtml");
    }
    
    /**
     * Return url to image with admin settings logo
     * @return string
     */
    protected function getAdminSettingsLogo() {
        return WOOCOMMERCE_DOTPAY_SMS_GATEWAY_URL . 'resources/images/Dotpay_logo_desc_pl.png';
    }
    
    /**
     * Return url to icon file
     * @return string
     */
    protected function getIcon() {
        return WOOCOMMERCE_DOTPAY_GATEWAY_URL . 'resources/images/dotpay.png';
    }
    
    /**
     * 
     * @return type
     */
    public function getFullFormPath() {
        return $_SERVER['HTTP_ORIGIN'].WOOCOMMERCE_DOTPAY_SMS_GATEWAY_DIR . 'form/'.str_replace('Dotpay_', '', $this->id).'.phtml';
    }
    
    /**
     * Return path to template dir
     * @return string
     */
    public function getTemplatesPath() {
        return WOOCOMMERCE_DOTPAY_SMS_GATEWAY_DIR . 'templates/';
    }
    
    /**
     * Init plugin settings and add save options action
     */
    public function init_settings() {
        parent::init_settings();
        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
        } else {
            add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
        }
    }
    
    /**
     * Return option key for Dotpay plugin
     * @return string
     */
    public function get_option_key() {
        return $this->plugin_id . $this->id . '_settings';
    }
    
    /**
     * Return rendered HTML from tamplate file
     * @param string $file name of template file
     * @return string
     */
    public function render($file) {
        ob_start();
        include($this->getTemplatesPath().$file);
        return ob_get_clean();
    }
    
    /**
     * Return flag, if this channel is enabled
     * @return bool
     */
    protected function isEnabled() {
        $result = false;
        if ('yes' === $this->get_option('enabled')) {
            $result = true;
        }
        
        return $result;
    }
    
    /**
     * Return admin prompt class name
     * @return string
     */
    protected function getAdminPromptClass() {
        $result = 'error';
        if ('yes' === $this->get_option('enabled')) {
            $result = 'updated';
        }
        
        return $result;
    }
    
    function getService() {
        if (count(WC()->cart->get_cart()) == 1) {
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $post_meta = get_post_meta($cart_item['product_id']);
                $services = get_option('dotpay_sms_services');
                $service = $post_meta['_dotpay_sms_service'][0];
                return array('services' => $services, 'service' => $service, 'meta' => $post_meta);
            }
        } 
        return null;
    }
    
    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) {
        // get the order informtion
        $order = wc_get_order($order_id);
        
        // get the items from order
        $items = $order->get_items();
        
        // check to make sure there is not more then one product
        if( count( $items ) > 1 ) {
            wc_add_notice(__('<strong>Payment error:</strong>', 'woothemes') . ' You can not purchase more products then one by SMS.', 'error');
            return;
        }

        // get the product from items
        $product = array_shift(array_values($items));
        $post_meta = get_post_meta($product['product_id']);
        $services = get_option('dotpay_sms_services');
        $service = $post_meta['_dotpay_sms_service'][0];
        $_id = $this->get_option('id');
        $check = @$_POST['dotpay_sms_code'];
        
        // check to make sure there product has the ability to purchase by sms
        if( !array_key_exists( '_dotpay_sms_service', $post_meta ) || !array_key_exists( $service, $services ) ) {
            wc_add_notice(__('<strong>Payment error:</strong>', 'woothemes') . ' Something went wrong. Please contact with site administrator.', 'error');
            return;
        }
        
        // get current sms service
        $sms = $services[$service];

        // create post array with DotPay data 
        $array = array(
            "check" => $check,
            "code"  => $sms['code'],
            "id"    => $_id,
            "type"  => $sms['type'],
            "del"   => $sms['single'] == "yes" ? 1 : 0
        );
        
        // create new curl object and add all important options
        $this->curl = new Dotpay_SMS_Curl();
        $this->curl->addOption(CURLOPT_URL, self::DOTPAY_URL);
        $this->curl->addOption(CURLOPT_SSL_VERIFYPEER, FALSE);
        $this->curl->addOption(CURLOPT_SSL_VERIFYHOST, 2);
        $this->curl->addOption(CURLOPT_FOLLOWLOCATION, 1);
        $this->curl->addOption(CURLOPT_RETURNTRANSFER, 1);
        $this->curl->addOption(CURLOPT_TIMEOUT, 100);
        $this->curl->addOption(CURLOPT_POST, 1);
        $this->curl->addOption(CURLOPT_POSTFIELDS, $array);
        
        // get response from Dotpay
        $response = $this->curl->exec();

        $this->curl->close();

        $explode = explode("\n", $response);
        $status = $explode[0];
        
        // check if code is correct
        if($status == 1) {
            // update the order status
            $order->update_status('completed', __('Payment completed use Dotpay SMS', 'woocommerce'));

            // reduce stock levels
            $order->reduce_order_stock();

            // empty the cart
            WC()->cart->empty_cart();

            // send to the thankyou page
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }

        wc_add_notice(__('<strong>Błąd:</strong>', 'woothemes').' Kod jest nie prawidłowy. Spróbuj jeszcze raz!', 'error');
        return;
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable', 'dotpay-payment-gateway'),
                'label' => __('You can enable Dotpay SMS payments', 'dotpay-payment-gateway'),
                'type' => 'checkbox',
                'default' => 'yes'
            ),
            'title' => array(
		'title'       => __( 'Title', 'woocommerce' ),
		'type'        => 'text',
		'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
		'default'     => __( 'SMS', 'dotpay-payment-gateway' ),
		'desc_tip'    => true,
            ),
            'description' => array(
                    'title'       => __( 'Description', 'woocommerce' ),
                    'type'        => 'text',
                    'desc_tip'    => true,
                    'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
                    'default'     => __( 'Fast and secure payment via Dotpays', 'dotpay-payment-gateway' )
            ),
            'id' => array(
                'title' => __('Dotpay customer ID', 'dotpay-payment-gateway'),
                'type' => 'text',
                'default' => '',
		'description' => __('ID number is a 6-digit string after # in a "Shop" line. You can find it at the Dotpay panel in Settings in the top bar.', 'dotpay-payment-gateway'),
                'desc_tip' => true,
            ),
            'sms_services' => array(
                'type' => 'sms_services'
            ),
        );
    }

    /**
     * Generate sms services html.
     *
     * @return string
     */
    public function generate_sms_services_html() {
        ob_start();
    ?>
        		<tr valign="top">
        			<th scope="row" class="titledesc"><?php _e('SMS Services', 'dotpay-sms-payment-gateway'); ?>:</th>
        			<td class="forminp" id="dotpay_sms_services">
        				<table class="widefat wc_input_table sortable" cellspacing="0">
        					<thead>
        						<tr>
        							<th class="sort">&nbsp;</th>
        							<th><?php _e('Service Name', 'dotpay-sms-payment-gateway'); ?></th>
                                                                <th><?php _e('Short Name', 'dotpay-sms-payment-gateway'); ?></th>
        							<th><?php _e('Code', 'dotpay-sms-payment-gateway'); ?></th>
        							<th><?php _e('Number', 'dotpay-sms-payment-gateway'); ?></th>
                                                                <th><?php _e('Price', 'dotpay-sms-payment-gateway'); ?></th>
                                                                <th><?php _e('Type', 'dotpay-sms-payment-gateway'); ?></th>
                                                                <th><?php _e('Single', 'dotpay-sms-payment-gateway'); ?></th>
        						</tr>
        					</thead>
        					<tbody class="accounts">
        <?php
        $i = -1;
        if ($this->sms_services) {
            foreach ($this->sms_services as $service) {
                $i++;

                echo '<tr class="account">
									<td class="sort"></td>
									<td><input type="text" value="' . $service['name'] . '" name="dotpay_sms_service[' . $i . '][name]" /></td>
									<td><input type="text" value="' . $service['short'] . '" name="dotpay_sms_service[' . $i . '][short]" /></td>
									<td><input type="text" value="' . $service['code'] . '" name="dotpay_sms_service[' . $i . '][code]" /></td>
									<td><input type="text" value="' . $service['number'] . '" name="dotpay_sms_service[' . $i . '][number]" /></td>
									<td><input type="text" value="' . $service['price'] . '" name="dotpay_sms_service[' . $i . '][price]" /></td>
									<td><input type="text" value="' . $service['type'] . '" name="dotpay_sms_service[' . $i . '][type]" /></td>
									<td><input type="text" value="' . $service['single'] . '" name="dotpay_sms_service[' . $i . '][single]" /></td>
								</tr>';
            }
        }
        ?>
        					</tbody>
        					<tfoot>
        						<tr>
        							<th colspan="7"><a href="#" class="add button"><?php _e('+ Add Account', 'woocommerce'); ?></a> <a href="#" class="remove_rows button"><?php _e('Remove selected account(s)', 'woocommerce'); ?></a></th>
        						</tr>
        					</tfoot>
        				</table>
        				<script type="text/javascript">
        					jQuery(function() {
        						jQuery('#dotpay_sms_services').on( 'click', 'a.add', function(){

        							var size = jQuery('#dotpay_sms_services').find('tbody .account').length;

        							jQuery('<tr class="service">\
        									<td class="sort"></td>\\n\
        									<td><input type="text" name="dotpay_sms_service[' + size + '][name]"></td>\
        									<td><input type="text" name="dotpay_sms_service[' + size + '][short]"></td>\
        									<td><input type="text" name="dotpay_sms_service[' + size + '][code]"></td>\
        									<td><input type="text" name="dotpay_sms_service[' + size + '][number]"></td>\
        									<td><input type="text" name="dotpay_sms_service[' + size + '][price]"></td>\
        									<td><input type="text" name="dotpay_sms_service[' + size + '][type]"></td>\
        									<td><input type="text" name="dotpay_sms_service[' + size + '][single]"></td>\
                                                                        </tr>').appendTo('#dotpay_sms_services table tbody');

        							return false;
        						});
        					});
        				</script>
        			</td>
        		</tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Save sms services table.
     */
    public function save_sms_services() {
        $services = array();
        if (isset($_POST['dotpay_sms_service'])) {
            foreach ($_POST['dotpay_sms_service'] as $i => $service) {
                if(count($service) == 7) {
                    $services[$service["short"]] = array_map('wc_clean', $service);
                }
            }
        }

        update_option('dotpay_sms_services', $services);
    }
}
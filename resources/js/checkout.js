/**
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to tech@dotpay.pl so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade WooCommerce to newer
* versions in the future. If you wish to customize WooCommerce for your
* needs please refer to http://www.dotpay.pl for more information.
*
*  @author    Daniel "Nicc0" Tęcza <kontakt@nicc0.pl>
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*
*/

jQuery(function(){
    jQuery('body').on( 'updated_checkout', function() {
        usingGateway();
        jQuery('input[name="payment_method"]').change(function(){
            usingGateway();
        });
    });
});

function usingGateway(){
    console.log(jQuery("input[name='payment_method']:checked").val());
    if(jQuery('form[name="checkout"] input[name="payment_method"]:checked').val() === 'dotpay_sms'){
        jQuery('#dotpay-sms-price').show();
        jQuery('.dotpay-sms-product').hide();
    } else {
        jQuery('#dotpay-sms-price').hide();
        jQuery('.dotpay-sms-product').show();
    }
}   
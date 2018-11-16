/**
 * 2007-2018 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2018 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
$(document).ready(function(){
    $('#carrier_form p:last').after('<p style="display:none;"><label for="custom_shipping_price">Custom shipping price: </label><input class="cs_input_field" type="text" name="custom_shipping_price" id="custom_shipping_price"><a href="#" class="btn btn-default" id="custom_shipping_price_set"><i class="icon-check"></i> Update</p>');

    var display_custom_price_field = function(e) {
        if ($('#delivery_option').val() == customshipping_carrier_id + ',') {
            $('#shipping_price').parent('p').hide();
            $('#custom_shipping_price').val($('#shipping_price').text());
            $('#custom_shipping_price').parent('p').show();
            $('#free_shipping').parent('p').hide();
        } else {
            $('#shipping_price').parent('p').show();
            $('#custom_shipping_price').parent('p').hide();
            $('#free_shipping').parent('p').show();
        }
    };

    $('#delivery_option').bind('change', display_custom_price_field);

    setTimeout(function() {
        if ($('#carriers_part:visible').length)
            return display_custom_price_field();
        setTimeout(arguments.callee, 300)
    }, 300);

    $('#custom_shipping_price_set').bind('click', function(e) {
        e.preventDefault();
        $.ajax({
            type: 'POST',
            url: customshipping_ajax_url,
            dataType: 'json',
            data: {
                'ajax': true,
                'token': customshipping_token,
                'id_cart': id_cart,
                'id_customer': id_customer,
                'value': $('#custom_shipping_price').val(),
            },
            success: function(res) {
                updateDeliveryOption();
            },
        });
        return false;
    });
});

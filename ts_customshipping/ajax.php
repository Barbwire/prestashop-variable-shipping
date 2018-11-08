<?php
include_once('../../config/config.inc.php');
include_once('../../init.php');
include_once('../../modules/ts_customshipping/ts_customshipping.php');

if (!Tools::getValue('ajax') || Tools::getValue('token') != sha1(_COOKIE_KEY_ . 'ts_customshipping')) {
    die;
}

// Set shipping for this customer and cart
Configuration::updateValue('TS_CUSTOM_SHIPPING_CARRIER_VALUE', round(floatval(Tools::getValue('value')), 2));
?>

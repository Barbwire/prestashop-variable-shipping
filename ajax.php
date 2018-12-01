<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @author     Barbara Leth
 * @copyright  2018 TecServe UG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

include_once '../../config/config.inc.php';
include_once '../../init.php';
include_once '../../modules/ts_customshipping/ts_customshipping.php';

if (!Tools::getValue('ajax') || Tools::getValue('token') !== sha1(_COOKIE_KEY_.'ts_customshipping')) {
    die;
}

// Set shipping for this customer and cart
Configuration::updateValue('TS_CUSTOM_SHIPPING_CARRIER_VALUE', round((float) Tools::getValue('value'), 2));

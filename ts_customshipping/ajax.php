<?php

/**
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

include_once '../../config/config.inc.php';
include_once '../../init.php';
include_once '../../modules/ts_customshipping/ts_customshipping.php';

if (!Tools::getValue('ajax') || Tools::getValue('token') !== sha1(_COOKIE_KEY_.'ts_customshipping')) {
    die;
}

// Set shipping for this customer and cart
Configuration::updateValue('TS_CUSTOM_SHIPPING_CARRIER_VALUE', round((float) Tools::getValue('value'), 2));

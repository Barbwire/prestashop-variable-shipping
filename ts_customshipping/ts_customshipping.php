<?php
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

use PrestaShop\PrestaShop\Core\Addon\Module\ModuleManagerBuilder;


if (!defined('_PS_VERSION_')) {
    exit;
}

class Ts_Customshipping extends CarrierModule
{
    public $id_carrier;

    public function __construct()
    {
        $this->name = 'ts_customshipping';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'Barbara Leth';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('TS Custom Shipping');
        $this->description = $this->l('Allows a custom shipping price to be set in the backend (for manual orders)');

        $moduleManagerBuilder = ModuleManagerBuilder::getInstance();
        $moduleManager = $moduleManagerBuilder->build();

        if ($moduleManager->isInstalled($this->name)) {
            // Getting carrier list
            global $cookie;
            $carriers = Carrier::getCarriers($cookie->id_lang, true, false, false, NULL, PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);

            // Saving id carrier list
            $id_carrier_list = array();
            foreach ($carriers as $carrier) {
                $id_carrier_list[] .= $carrier['id_carrier'];
            }

            // Testing if Carrier ID exists
            $warning = array();
            if (!in_array((int)(Configuration::get('TS_CUSTOM_SHIPPING_CARRIER_ID')), $id_carrier_list)) {
                $warning[] .= $this->l('TS Custom Shipping Carrier') . ' ';
            }
            if (count($warning)) {
                $this->warning .= implode(' , ', $warning) . $this->l('must be configured to use this module correctly') . ' ';
            }
        }
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        $carrierConfig = array(
            0 => array('name' => 'Custom Shipping',
                'active' => true,
                'deleted' => 0,
                'id_tax_rule_group' => '2',
                'shipping_handling' => false,
                'range_behavior' => 0,
                'delay' => array('fr' => 'Custom', 'en' => 'Custom', Language::getIsoById(Configuration::get('PS_LANG_DEFAULT')) => 'Custom'),
                'is_module' => true,
                'shipping_external' => true,
                'external_module_name' => 'ts_customshipping',
                'need_range' => true
            ),
        );

        try {
            $id_carrier1 = $this->installExternalCarrier($carrierConfig[0]);
            $Carrier = new Carrier((int)$id_carrier1);
            $Carrier->setTaxRulesGroup((int)$this->getTaxRulesGroupMostUsed());
            Configuration::updateValue('TS_CUSTOM_SHIPPING_CARRIER_ID',  (int)$id_carrier1);
        } catch (PrestaShopDatabaseException $e) {
        } catch (PrestaShopException $e) {
        }

        return parent::install() && $this->registerHook('displayBackOfficeHeader');
    }

    public function uninstall()
    {
        // Delete External Carrier
        $Carrier1 = new Carrier((int)(Configuration::get('TS_CUSTOM_SHIPPING_CARRIER_ID')));
        // If external carrier is default set other one as default
        if (Configuration::get('PS_CARRIER_DEFAULT') == (int)($Carrier1->id)) {
            global $cookie;
            $carriersD = Carrier::getCarriers($cookie->id_lang, true, false, false, NULL, PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);
            foreach ($carriersD as $carrierD)
                if ($carrierD['active'] AND !$carrierD['deleted'] AND ($carrierD['name'] != $this->name))
                    Configuration::updateValue('PS_CARRIER_DEFAULT', $carrierD['id_carrier']);
        }

        // Then delete Carrier
        if (!$Carrier1->delete())
            return false;
        Configuration::deleteByName('TS_CUSTOM_SHIPPING_CARRIER_ID');

        // Uninstall
        return parent::uninstall() && $this->unregisterHook('displayBackOfficeHeader');
    }


    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookDisplayBackOfficeHeader()
    {

        $this->context->controller->addJS($this->_path . 'views/js/ts_customshipping.js');
        $this->context->controller->addCSS($this->_path . 'views/css/ts_customshipping.css');

        $out =  '<script>var customshipping_carrier_id = ' . Configuration::get('TS_CUSTOM_SHIPPING_CARRIER_ID') . ';</script>';
        $out .= '<script>var customshipping_token = "' . sha1(_COOKIE_KEY_ . 'ts_customshipping') . '";</script>';
        $out .= '<script>var customshipping_ajax_url = "' . $this->_path . 'ajax.php' . '";</script>';

        return $out;
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        return $this->getOrderShippingCostExternal($params);
    }

    public function getOrderShippingCostExternal($params)
    {
        $context = Context::getContext();
        if (!$context->employee || !$context->employee->id)
            return false;

        $value = Configuration::get('TS_CUSTOM_SHIPPING_CARRIER_VALUE');
        return $value ? round(floatval($value), 2) : 0.00;
    }

    /**
     * @return false|null|string ID Tax rule group most used for existing active carriers
     */
    public static function getTaxRulesGroupMostUsed()
    {
        return Db::getInstance()->getValue('
                    SELECT id_tax_rules_group 
                    FROM (
                        SELECT COUNT(*) n, ctrg.id_tax_rules_group 
                        FROM ' . _DB_PREFIX_ . 'carrier c 
                        JOIN ' . _DB_PREFIX_ . 'carrier_tax_rules_group_shop ctrg ON (c.id_carrier = ctrg.id_carrier) 
                        JOIN ' . _DB_PREFIX_ . 'tax_rules_group trg ON (trg.id_tax_rules_group = ctrg.id_tax_rules_group)
                        WHERE c.active = 1 AND c.deleted = 0 AND trg.active = 1 AND trg.deleted = 0
                        GROUP BY ctrg.id_tax_rules_group
                        ORDER BY n DESC
                        LIMIT 1
                    ) most_used'
        );
    }

    /**
     * @param $config
     * @return bool|int
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function installExternalCarrier($config)
    {
        $carrier = new Carrier();
        $carrier->name = $config['name'];
        $carrier->active = $config['active'];
        $carrier->deleted = $config['deleted'];
        $carrier->delay = $config['delay'];
        $carrier->shipping_handling = $config['shipping_handling'];
        $carrier->range_behavior = $config['range_behavior'];
        $carrier->is_module = $config['is_module'];
        $carrier->shipping_external = $config['shipping_external'];
        $carrier->external_module_name = $config['external_module_name'];
        $carrier->need_range = $config['need_range'];

        $languages = Language::getLanguages(true);
        foreach ($languages as $language) {
            if ($language['iso_code'] == 'fr')
                $carrier->delay[(int)$language['id_lang']] = $config['delay'][$language['iso_code']];
            if ($language['iso_code'] == 'en')
                $carrier->delay[(int)$language['id_lang']] = $config['delay'][$language['iso_code']];
            if ($language['iso_code'] == Language::getIsoById(Configuration::get('PS_LANG_DEFAULT')))
                $carrier->delay[(int)$language['id_lang']] = $config['delay'][$language['iso_code']];
        }

        if ($carrier->add()) {
            $groups = Group::getGroups(true);
            $data = array();
            foreach ($groups as $group) {
                $data[] = array(
                    'id_carrier' => (int)($carrier->id_reference),
                    'id_group' => (int)($group['id_group']),
                );
            }
            Db::getInstance()->insert('carrier_group', $data, false, false, Db::INSERT);


            $rangePrice = new RangePrice();
            $rangePrice->id_carrier = $carrier->id_reference;
            $rangePrice->delimiter1 = '0';
            $rangePrice->delimiter2 = '10000';
            $rangePrice->add();

            $rangeWeight = new RangeWeight();
            $rangeWeight->id_carrier = $carrier->id_reference;
            $rangeWeight->delimiter1 = '0';
            $rangeWeight->delimiter2 = '10000';
            $rangeWeight->add();

            $zones = Zone::getZones(true);
            foreach ($zones as $zone) {
                Db::getInstance()->insert('carrier_zone', array('id_carrier' => (int)($carrier->id), 'id_zone' => (int)($zone['id_zone'])), false, false, Db::INSERT);
                Db::getInstance()->insert('delivery', array('id_carrier' => (int)($carrier->id), 'id_range_price' => (int)($rangePrice->id), 'id_range_weight' => NULL, 'id_zone' => (int)($zone['id_zone']), 'price' => '0'), false, false, Db::INSERT);
                Db::getInstance()->insert('delivery', array('id_carrier' => (int)($carrier->id), 'id_range_price' => NULL, 'id_range_weight' => (int)($rangeWeight->id), 'id_zone' => (int)($zone['id_zone']), 'price' => '0'), false, false, Db::INSERT);
            }

            // Copy Logo
            if (!copy(dirname(__FILE__) . '/carrier.jpg', _PS_SHIP_IMG_DIR_ . '/' . (int)$carrier->id . '.jpg'))
                return false;

            // Return ID Carrier
            return (int)($carrier->id_reference);
        }

        return false;
    }

}

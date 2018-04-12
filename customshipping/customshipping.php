<?php

// Avoid direct access to the file
if (!defined('_PS_VERSION_'))
	exit;

class customshipping extends CarrierModule
{
    public $id_carrier;

    public function __construct()
    {
        $this->name = 'customshipping';
        $this->tab = 'shipping_logistics';
        $this->version = '2.0';
        $this->author = 'Barbara Leth';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = array('min' => '1.7.0', 'max' => _PS_VERSION_);

        parent::__construct();

        $this->displayName = $this->l('Custom Shipping');
        $this->description = $this->l('Allows a custom shipping price to be set in the backend (for manual orders)');


        if (self::isInstalled($this->name)) {
            // Getting carrier list
            global $cookie;
            $carriers = Carrier::getCarriers($cookie->id_lang, true, false, false, NULL, PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);

            // Saving id carrier list
            $id_carrier_list = array();
            foreach ($carriers as $carrier)
                $id_carrier_list[] .= $carrier['id_carrier'];

            // Testing if Carrier Id exists
            $warning = array();
            if (!in_array((int)(Configuration::get('CUSTOM_SHIPPING_CARRIER_ID')), $id_carrier_list))
                $warning[] .= $this->l('"Custom Shipping Carrier"') . ' ';
            if (count($warning))
                $this->warning .= implode(' , ', $warning) . $this->l('must be configured to use this module correctly') . ' ';
        }
    }

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
                'external_module_name' => 'customshipping',
                'need_range' => true
            ),
        );

        $id_carrier1 = $this->installExternalCarrier($carrierConfig[0]);
        $Carrier = new Carrier((int)$id_carrier1);
        $Carrier->setTaxRulesGroup((int)$this->getTaxRulesGroupMostUsed());
        Configuration::updateValue('CUSTOM_SHIPPING_CARRIER_ID', (int)$id_carrier1);
        if (!parent::install() || !$this->registerHook('displayBackOfficeHeader'))
            return false;
        return true;
    }

    public function uninstall()
    {
        // Uninstall
        if (!parent::uninstall() || !$this->unregisterHook('displayBackOfficeHeader'))
            return false;

        // Delete External Carrier
        $Carrier1 = new Carrier((int)(Configuration::get('CUSTOM_SHIPPING_CARRIER_ID')));

        // If external carrier is default set other one as default
        if (Configuration::get('PS_CARRIER_DEFAULT') == (int)($Carrier1->id)) {
            global $cookie;
            $carriersD = Carrier::getCarriers($cookie->id_lang, true, false, false, NULL, PS_CARRIERS_AND_CARRIER_MODULES_NEED_RANGE);
            foreach ($carriersD as $carrierD)
                if ($carrierD['active'] AND !$carrierD['deleted'] AND ($carrierD['name'] != $this->_config['name']))
                    Configuration::updateValue('PS_CARRIER_DEFAULT', $carrierD['id_carrier']);
        }

        // Then delete Carrier
        $Carrier1->deleted = 1;
        if (!$Carrier1->update())
            return false;

        return true;
    }

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
                    'id_carrier' => (int)($carrier->id),
                    'id_group' => (int)($group['id_group']),
                );
            }
            Db::getInstance()->insert('carrier_group', $data, false, false, Db::INSERT);


            $rangePrice = new RangePrice();
            $rangePrice->id_carrier = $carrier->id;
            $rangePrice->delimiter1 = '0';
            $rangePrice->delimiter2 = '10000';
            $rangePrice->add();

            $rangeWeight = new RangeWeight();
            $rangeWeight->id_carrier = $carrier->id;
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
            return (int)($carrier->id);
        }

        return false;
    }

    public function hookDisplayBackOfficeHeader()
    {
        $out = '<script type="text/javascript" src="' . $this->_path . 'customshipping.js' . '"></script>';
        $out .= '<link type="text/css" rel="stylesheet" href="' . $this->_path . 'customshipping.css' . '" />';
        $out .= '<script>var customshipping_carrier_id = ' . Configuration::get('CUSTOM_SHIPPING_CARRIER_ID') . ';</script>';
        $out .= '<script>var customshipping_token = "' . sha1(_COOKIE_KEY_ . 'customshipping') . '";</script>';
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

        $value = file_get_contents(sys_get_temp_dir() . '/psvs-' . _DB_NAME_ . '-' . $params->id . '-' . $params->id_customer);
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

}

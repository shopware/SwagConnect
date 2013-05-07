<?php
/**
 * Shopware 4.0
 * Copyright © 2013 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

/**
 * @category  Shopware
 * @package   Shopware\Plugins\SwagBepado
 * @copyright Copyright (c) 2013, shopware AG (http://www.shopware.de)
 * @author    Heiner Lohaus
 */
final class Shopware_Plugins_Backend_SwagBepado_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * Returns the current version of the plugin.
     *
     * @return string
     */
    public function getVersion()
    {
        return '1.1.4';
    }

    /**
     * Returns a nice name for plugin manager list
     *
     * @return string
     */
    public function getLabel()
    {
        return 'Bepado';
    }

    /**
     * @return array
     */
    public function getInfo()
    {
        return array(
            'version' => $this->getVersion(),
            'label' => $this->getLabel(),
            //'description' => file_get_contents($this->Path() . 'info.txt'),
            'link' => 'http://www.shopware.de/',
        );
    }

	/**
	 * Install plugin method
	 *
	 * @return bool
	 */
	public function install()
	{	
        $this->createMyMenu();
        $this->createMyForm();
        $this->createMyEvents();
        $this->createMyTables();
        $this->createMyAttributes();

	 	return true;
	}

    private function createMyAttributes()
    {
        $this->Application()->Models()->addAttribute(
            's_articles_attributes',
            'bepado', 'shop_id',
            'varchar(255)'
        );
        $this->Application()->Models()->addAttribute(
            's_articles_attributes',
            'bepado', 'source_id',
            'varchar(255)'
        );
        $this->Application()->Models()->addAttribute(
            's_articles_attributes',
            'bepado', 'export_status',
            'text'
        );
        $this->Application()->Models()->addAttribute(
            's_articles_attributes',
            'bepado', 'export_message',
            'text'
        );

        $this->Application()->Models()->addAttribute(
            's_order_details_attributes',
            'bepado', 'reservation_id',
            'text'
        );
        $this->Application()->Models()->addAttribute(
            's_order_basket_attributes',
            'bepado', 'reservation_id',
            'text'
        );
        //$this->Application()->Models()->addAttribute(
        //    's_order_basket_attributes',
        //    'bepado', 'order_item',
        //    'text'
        //);

        $this->Application()->Models()->addAttribute(
            's_articles_attributes',
            'bepado', 'categories',
            'text'
        );
        $this->Application()->Models()->addAttribute(
           's_categories_attributes',
           'bepado', 'mapping',
           'text'
        );

        $this->Application()->Models()->generateAttributeModels(array(
            's_articles_attributes',
            's_categories_attributes',
            's_order_details_attributes',
            's_order_basket_attributes',
        ));
    }

    private function createMyMenu()
    {
        $parent = $this->Menu()->findOneBy(array('label' => 'Einstellungen'));
        $this->createMenuItem(array(
            'label' => $this->getLabel(),
            'controller' => 'Bepado',
            'action' => 'Index',
            'class' => 'sprite-ui-combo-box-edit',
            'active' => 1,
            'parent' => $parent
        ));
    }

    private function createMyForm()
    {
        $form = $this->Form();

        $form->setElement('text', 'apiKey', array(
            'label' => 'API Key',
            'description' => '',
            'required' => true,
            'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
            'uniqueId' => 'apiKey'
        ));
        $form->setElement('button', 'verifyApiKey', array(
            'label' => '<strong>API Key testen</strong>',
            'handler' => "function(btn) {
                var apiField = btn.up('form').down('textfield[uniqueId=apiKey]'),
                    apiKey = apiField.getValue(),
                    title = btn.up('window').title;
                Ext.Ajax.request({
                    scope: this,
                    url: window.location.pathname + 'bepado/verifyApiKey',
                    success: function(result, request) {
                        var response = Ext.JSON.decode(result.responseText);
                        apiField.setFieldStyle('background-color', response.success ? 'green' : 'red');
                        if(response.message) {
                            Shopware.Notification.createGrowlMessage(
                                btn.title,
                                response.message,
                                title
                            );
                        }
                    },
                    failure: function() { },
                    params: { apiKey: apiKey }
                });
            }"
        ));
        $form->setElement('select', 'exportPriceGroup', array(
            'label' => 'Export-Preisgruppe',
            'description' => '',
            'store' => 'base.CustomerGroup'
        ));
    }

    private function createMyEvents()
    {
        $this->subscribeEvent(
            'Enlight_Bootstrap_InitResource_BepadoSDK',
            'onInitResourceSDK'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_Bepado',
            'onGetControllerPathBackend'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_BepadoGateway',
            'onGetControllerPathGateway'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Frontend_Bepado',
            'onGetControllerPathFrontend'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch_Frontend_Checkout',
            'onPostDispatchFrontendCheckout'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch_Frontend_Detail',
            'onPostDispatchFrontendDetail'
        );

        //$this->subscribeEvent(
        //    'sBasket::sAddArticle::after',
        //    'onAfterAddArticle'
        //);
    }

    private function createMyTables()
    {
        $queries = array("
            CREATE TABLE IF NOT EXISTS `bepado_change` (
              `c_source_id` varchar(64) NOT NULL,
              `c_operation` char(8) NOT NULL,
              `c_revision` decimal(20,10) NOT NULL,
              `c_product` longblob,
              `changed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY `c_revision` (`c_revision`),
              KEY `c_source_id` (`c_source_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;", "
           CREATE TABLE IF NOT EXISTS `bepado_data` (
              `d_key` varchar(32) NOT NULL,
              `d_value` varchar(256) NOT NULL,
              `changed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`d_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;", "
            CREATE TABLE IF NOT EXISTS `bepado_product` (
              `p_source_id` varchar(64) NOT NULL,
              `p_hash` varchar(64) NOT NULL,
              `changed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`p_source_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;", "
            CREATE TABLE IF NOT EXISTS `bepado_reservations` (
              `r_id` varchar(32) NOT NULL,
              `r_state` varchar(12) NOT NULL,
              `r_order` longblob NOT NULL,
              `changed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`r_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;","
            CREATE TABLE IF NOT EXISTS `bepado_shop_config` (
              `s_shop` varchar(32) NOT NULL,
              `s_config` mediumblob NOT NULL,
              `changed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`s_shop`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

        foreach ($queries as $query) {
            Shopware()->Db()->exec($query);
        }
    }
	
	/**
	 * Uninstall plugin method
	 *
	 * @return bool
	 */
	public function uninstall()
	{
        return true;
	}

    /**
     * @return bool
     */
    public function update()
    {
        $this->createMyEvents();
        $this->createMyTables();
        return true;
    }

    private function removeMyAttributes()
    {
        try {
            $this->Application()->Models()->removeAttribute(
                's_articles_attributes',
                'bepado', 'shop_id'
            );
            $this->Application()->Models()->removeAttribute(
                's_articles_attributes',
                'bepado', 'source_id'
            );
            $this->Application()->Models()->removeAttribute(
                's_articles_attributes',
                'bepado', 'export_status'
            );
            $this->Application()->Models()->removeAttribute(
                's_articles_attributes',
                'bepado', 'export_message'
            );

            $this->Application()->Models()->removeAttribute(
                's_order_details_attributes',
                'bepado', 'reservation_id'
            );
            $this->Application()->Models()->removeAttribute(
                's_order_basket_attributes',
                'bepado', 'reservation_id'
            );

            $this->Application()->Models()->removeAttribute(
                's_articles_attributes',
                'bepado', 'categories'
            );
            $this->Application()->Models()->removeAttribute(
                's_categories_attributes',
                'bepado', 'mapping'
            );

            $this->Application()->Models()->generateAttributeModels(array(
                's_articles_attributes',
                's_categories_attributes',
                's_order_details_attributes',
                's_order_basket_attributes',
            ));
        } catch(Exception $e) { }

    }

    private function registerMyTemplateDir()
    {
        $this->Application()->Template()->addTemplateDir(
            $this->Path() . 'Views/', 'bepado'
        );
    }

    private function registerMyLibrary()
    {
        $this->Application()->Loader()->registerNamespace(
            'Bepado',
            $this->Path() . 'Library/Bepado/'
        );
        $this->Application()->Loader()->registerNamespace(
            'Shopware\\Bepado',
            $this->Path() . 'Library/Shopware/Bepado/'
        );
    }

    /**
     * @param   Enlight_Event_EventArgs $args
     * @return  Bepado\SDK\SDK
     */
    public function onInitResourceSDK(Enlight_Event_EventArgs $args)
    {
        $this->registerMyLibrary();
        /** @var $connection PDO */
        $connection = $this->Application()->Db()->getConnection();
        $manager = $this->Application()->Models();
        $front = $this->Application()->Front();
        $helper = new \Shopware\Bepado\Helper($manager);
        $apiKey = $this->Config()->get('apiKey');

        return new Bepado\SDK\SDK(
            $apiKey,
            $front->Router()->assemble(array(
                'module' => 'backend',
                'controller' => 'bepado_gateway',
                'fullPath' => true
            )),
            new \Bepado\SDK\Gateway\PDO($connection),
            new \Shopware\Bepado\ProductToShop(
                $helper,
                $manager
            ),
            new \Shopware\Bepado\ProductFromShop(
                $helper,
                $manager
            )
        );
    }

    private $helper, $sdk;

    /**
     * @return Bepado\SDK\SDK
     */
    private function getSDK()
    {
        if($this->sdk === null) {
            $this->sdk = $this->Application()->Bootstrap()->getResource('BepadoSDK');
        }
        return $this->sdk;
    }

    /**
     * @return \Shopware\Bepado\Helper
     */
    public function getHelper()
    {
        if($this->helper === null) {
            $this->helper = new \Shopware\Bepado\Helper(
                $this->Application()->Models()
            );
        }
        return $this->helper;
    }

    /**
     * @param   Enlight_Event_EventArgs $args
     * @return  string
     * @Enlight\Event Enlight_Controller_Dispatcher_ControllerPath_Backend_Bepado
     */
    public function onGetControllerPathBackend(Enlight_Event_EventArgs $args)
    {
        $this->registerMyTemplateDir();
        $this->Application()->Snippets()->addConfigDir(
            $this->Path() . 'Snippets/'
        );
        return $this->Path() . 'Controllers/Backend/Bepado.php';
    }

    /**
     * @param   Enlight_Event_EventArgs $args
     * @return  string
     */
    public function onGetControllerPathGateway(Enlight_Event_EventArgs $args)
    {
        return $this->Path() . 'Controllers/Backend/BepadoGateway.php';
    }

    /**
     * @param   Enlight_Event_EventArgs $args
     * @return  string
     */
    public function onGetControllerPathFrontend(Enlight_Event_EventArgs $args)
    {
        $this->registerMyTemplateDir();
        return $this->Path() . 'Controllers/Frontend/Bepado.php';
    }

    /**
     * @param Enlight_Hook_HookArgs $args
     */
    public function onAfterAddArticle(Enlight_Hook_HookArgs $args)
    {
        $id = $args->getId();
    }

    private function getDeliveryAddress($userData)
    {
        //$userData = $view->sUserData;
        $shippingData = $userData['shippingaddress'];
        $address = new Bepado\SDK\Struct\Address();
        $address->zip = $shippingData['zipcode'];
        $address->city = $shippingData['city'];
        $address->country = $userData['additional']['countryShipping']['iso3'];
        if(!empty($userData['additional']['stateShipping']['shortcode'])) {
            $address->state = $userData['additional']['stateShipping']['shortcode'];
        }
        $address->name = $shippingData['firstname'] . ' ' . $shippingData['lastname'];
        if(!empty($shippingData['company'])) {
            $address->name = $shippingData['company'] . ' - ' . $address->name;
        }
        $address->line1 = $shippingData['street'] . ' ' . $shippingData['streetnumber'];
        return $address;
    }

    /**
     * Event listener method
     *
     * @param Enlight_Event_EventArgs $args
     * @return void
     */
    public function onPostDispatchFrontendCheckout(Enlight_Event_EventArgs $args)
    {
        /** @var $action Enlight_Controller_Action */
        $action = $args->getSubject();
        $view = $action->View();

        //$this->registerCustomizing();
        //$view->extendsTemplate('frontend/plugins/swag_customizing/checkout.tpl');
        //$view->assign('customizingThumbnailSize', $this->Config()->get('imageSelectThumbnailSize'));

        if(empty($view->sBasket)) {
            return;
        }

        $this->registerMyTemplateDir();
        $view->extendsTemplate('frontend/bepado/checkout.tpl');

        $sdk = $this->getSDK();
        $helper = $this->getHelper();

        $bepadoContent = array();
        $bepadoProducts = array();
        $bepadoShops = array();
        $bepadoCheckResults = array();
        $bepadoOrders = array();

        $basket = $view->sBasket;
        foreach ($basket['content'] as $key => $row) {
            if(!empty($row['mode'])) {
                continue;
            }
            $product = $helper->getProductById($row['articleID']);
            if($product === null || $product->shopId === null) {
                continue;
            }
            $bepadoProducts[$product->shopId][$product->sourceId] = $product;
            $bepadoContent[$product->shopId][$product->sourceId] = $row;
            unset($basket['content'][$key]);
        }

        foreach($bepadoContent as $shopId => $items) {
            $order = new Bepado\SDK\Struct\Order();
            $order->products = array();
            $order->deliveryAddress = $this->getDeliveryAddress($view->sUserData);

            foreach($items as $sourceId => $item) {
                $product = $bepadoProducts[$shopId][$sourceId];

                $orderItem = new Bepado\SDK\Struct\OrderItem();
                $orderItem->product = $product;
                $orderItem->count = (int)$item['quantity'];

                $order->products[] = $orderItem;
            }
            try {
                /** @var $reservation Bepado\SDK\Struct\Reservation */
                $reservation = $sdk->reserveProducts($order);
                $message = $reservation->messages[$shopId];
            } catch(Exception $e) {
                $message = new Bepado\SDK\Struct\Message();
                $message->message = $e->getMessage();
            }
            $bepadoCheckResults[$shopId] = $message;
            $bepadoShops[$shopId] = $sdk->getShopConfigurationById($shopId);
        }

        if(empty($basket['content'])) {
            reset($bepadoContent);
            $shopId = current(array_keys($bepadoContent));
            $basket['content'] = $bepadoContent[$shopId];
            $view->shopId = $shopId;
            unset($bepadoContent[$shopId]);
        }

        $view->assign(array(
            'bepadoContent' => $bepadoContent,
            'bepadoShops' => $bepadoShops,
            'bepadoOrders' => $bepadoOrders,
            'bepadoCheckResults' => $bepadoCheckResults
        ));

        $view->sBasket = $basket;
    }

    /**
     * Event listener method
     *
     * @param Enlight_Event_EventArgs $args
     */
    public function onPostDispatchFrontendDetail(Enlight_Event_EventArgs $args)
    {
        /** @var $action Enlight_Controller_Action */
        $action = $args->getSubject();
        $view = $action->View();
        $helper = $this->getHelper();
        $sdk = $this->getSDK();

        $this->registerMyTemplateDir();
        $view->extendsTemplate('frontend/bepado/detail.tpl');

        $articleData = $view->getAssign('sArticle');
        if(empty($articleData['articleID'])) {
            return;
        }

        $product = $helper->getProductById($articleData['articleID']);
        if(empty($product->shopId)) {
            return;
        }
        $shop = $sdk->getShopConfigurationById($product->shopId);

        $view->assign(array(
            'bepadoProduct' => $product,
            'bepadoShop' => $shop
        ));
    }
}
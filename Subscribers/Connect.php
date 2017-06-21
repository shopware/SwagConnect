<?php
/**
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ShopwarePlugins\Connect\Subscribers;

use Shopware;
use Shopware\Connect\SDK;
use ShopwarePlugins\Connect\Components\Config;

/**
 * Class Connect
 */
class Connect extends BaseSubscriber
{
    const MARKETPLACE_NAME = 'Shopware Connect';
    // todo@sb: change it to production shopware connect domain
    const MARKETPLACE_SOCIAL_NETWORK_URL = 'http://sn.connect.shopware.com';
    const MARKETPLACE_ICON = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH4QQDDRkwQ/hhTgAAAqZJREFUOMtdkktIVFEYx3/n3Htn7sxYc9WK7OEj7UEQtLLAcGNTLdJo0cNiLKOooKIEW0brWrQpEWqhiBHuEnppGb0zWqSRQhmUIYr2mJsyjd7HaaEzTp3Vd875/3/n+77ziWi8hf9WOXAUiAHLAQGMAI+A68DrbLHMihcC7UAvcAwoBgxAB4qAI8AzoA3ISZv0LHM3UI5moAIRhB6ciUbMe6WLw/dj6/P6azcVTBQvijhPB0adS7f73eeDY/MAKUSzD+XKzAUjRFCXHw9uXnbyyv5174EkMA140VPdyr4aE5XrC7DqWgEQ0XhLOUL0qvAi0AJoUkxc3FW29UxV0WD0eKcj3D/gOaA8UHMdgdkY0A1NHp8JWKAFAFiZZ147U1X0wTrc7gnfTZe6E9gLWChGgDvAXcDXfanHMEKZTuZHtC7rUJuH8tNHJUBn1tsAJ4B3QJ30jHBB9reMT/y0s8yULV04IwzTUaFcVDgfFbJANwE2Am8kWvCfIfhhT63J3r+9tHuscuPq+oCZM4xughGZBUWWIHTDEHmnu7+5PivSBuPPeJfrONszBCFJtMZDiaSzeF9zX2xgdGrPZMqtUgpdl4yIwsbH7YmkeyCjT/4AN9UAXMlA9CDKtLCbdoSBnGs9w4VXe77GHc8PidjlV9t6v0w9yIjd1CwEWoBG4Ps8yEQFwtjN1drc8AmhlAoWnX/SkUg6NZksUjbMTAFMzs1/E/A5uywCEZSQSKv+5vSF6tJzIUP2pe+VGUWFckHIBUADMAQ8BGoAgfJhehKRstHMDTV0nK20V+abPc8+JQpTjr8WAM2AQA5IifBdUGoVUDtX0ptMMtF4CwhBorVOAvnxG/0VL4Z+7bOT7hZPqaVKIXUpxhYY3suKkvCt9lNbuq261t9pwF+Aq+yGcIEN2wAAAABJRU5ErkJggg==';
    const MARKETPLACE_GREEN_ICON = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAA3XAAAN1wFCKJt4AAAAB3RJTUUH3wkeDSo6M9XATgAAAblJREFUOMtt0z1oVEEUBeBv11URiSIIViKRRQi2EUUQ3CpPxMI02oqFDyy0VIRRHioELCxHBEuDhX9YZAolipAqVmpECYpgk2qNiUiMZm3eyvO5t5mZe+ecmXvmTAOyvJBiUM43YzeycvyFN3iKLymGlbG80MRUDBo18Bgu4ZDB8QA3Uwwv+4lWBXwe17GpApjDM7zHV2zEUJWxUYKP4n65AZZxDo+wnGL4WQUdyQtT5cGNLC+GylP2lfUVnEoxTPYBWV5Aq1z+TjH0+vkW9lbAMJNimOxrk+XFBozjAJqYy/JiBq9TDL0WjtWEuldbZ5is5RZwJ8uLiRbateJ3/H0ZfBvwGjtwEW+b6NWK+2u9v8BZLGGttnd9I8uLy7hSSXYxkmJY+KePvNiG0ziOPdiOThNPaqzbcDfLi53Vm6QYuimGG6XJTuAaPqxrj3YWcRDDFZJhjLdHOz/mZ6dfzc9OV4l687PTn9qjnedY6hvpMB5jywDBupjAbXRTDL2q/RuVa57B1bK3QbGGiAsphqV+slkqLcVwCyfxsHRjPRaxtdTov7/QJ1FaexdGynEVH/EOn1MMq9UW/gDS6KGkt/RrSwAAAABJRU5ErkJggg==';
    const MARKETPLACE_LOGO = 'data:image/png;base64,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';

    /** @var \ShopwarePlugins\Connect\Components\Config */
    private $configComponent;

    public function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Backend_Index' => 'backendIndexEvent',
        ];
    }

    public function backendIndexEvent(\Enlight_Event_EventArgs $args)
    {
        if ($args->getRequest()->getActionName() === 'load') {
            $this->checkPluginVersion($args);
        }

        $this->injectBackendConnectMenuEntry($args);
    }

    private function checkPluginVersion(\Enlight_Event_EventArgs $args)
    {
        /** @var $action \Enlight_Controller_Action */
        $action = $args->getSubject();
        $request = $action->Request();
        $response = $action->Response();
        $snippets = Shopware()->Snippets()->getNamespace('backend/connect/view/main');
        $view = $action->View();
        $info = null;

        if (!$request->isDispatched() || $response->isException() || !$view->hasTemplate()) {
            return;
        }

        $info = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'plugin.json'), true);

        // URL: https://api.shopware.com/pluginStore/updates?pluginNames%5B0%5D=SwagBepado&shopwareVersion=5.0.0
        //This url will return plugin version information only if shopware version is 5.2.* or higher
        $baseUrl = 'https://api.shopware.com/pluginStore/updates';
        $shopVersion = Shopware::VERSION;
        $shopVersion = $shopVersion === '___VERSION___' ? '5.0.0' : $shopVersion;
        $pluginName = 'SwagConnect';
        $apiResponse = json_decode(
            file_get_contents($baseUrl . '?pluginNames[0]=' . $pluginName . '&shopwareVersion=' . $shopVersion)
        )[0];

        if (version_compare($apiResponse->highestVersion, $info['currentVersion'], '>')) {
            $view->falseVersionTitle = $snippets->get('info/new_version_header');
            $view->falseVersionMessage = $snippets->get('info/new_version_text');
            $view->extendsTemplate('backend/index/view/connect_menu.js');
        }
    }

    /**
     * Callback method for the Backend/Index postDispatch event.
     * Will add the connect sprite to the menu
     *
     * @event Enlight_Controller_Action_PostDispatch_Backend_Index
     *
     * @param \Enlight_Event_EventArgs $args
     * @returns boolean|void
     */
    private function injectBackendConnectMenuEntry(\Enlight_Event_EventArgs $args)
    {
        /** @var $action \Enlight_Controller_Action */
        $action = $args->getSubject();
        $request = $action->Request();
        $response = $action->Response();
        $view = $action->View();

        if (!$request->isDispatched() || $response->isException() || !$view->hasTemplate()) {
            return;
        }

        $marketplaceIcon = $this->getConfigComponent()->getConfig('marketplaceIcon', self::MARKETPLACE_ICON);
        $marketplaceName = $this->getConfigComponent()->getConfig('marketplaceName', self::MARKETPLACE_NAME);

        $view->marketplaceName = $marketplaceName;
        $view->marketplaceNetworkUrl = $this->getConfigComponent()->getConfig('marketplaceNetworkUrl', self::MARKETPLACE_SOCIAL_NETWORK_URL);
        $view->marketplaceIcon = $marketplaceIcon;
        $view->defaultMarketplace = $this->getConfigComponent()->getConfig('isDefault', true);
        $isFixedPriceAllowed = 0;
        $priceType = Shopware()->Container()->get('ConnectSDK')->getPriceType();
        if ($priceType === SDK::PRICE_TYPE_BOTH ||
            $priceType === SDK::PRICE_TYPE_RETAIL) {
            $isFixedPriceAllowed = 1;
        }
        $view->isFixedPriceAllowed = $isFixedPriceAllowed;

        // if the marketplace is connect we have green icon
        // if not marketplace icon should be used in both places
        $view->marketplaceIncomingIcon = ($marketplaceName == self::MARKETPLACE_NAME ? self::MARKETPLACE_GREEN_ICON : $marketplaceIcon);
        $view->marketplaceLogo = $this->getConfigComponent()->getConfig('marketplaceLogo', self::MARKETPLACE_LOGO);
        $view->purchasePriceInDetail = method_exists('Shopware\Models\Article\Detail', 'setPurchasePrice') ? 1 : 0;

        $view->addTemplateDir($this->Path() . 'Views/');
        $view->extendsTemplate('backend/connect/menu_entry.tpl');
    }

    private function getConfigComponent()
    {
        if (!$this->configComponent) {
            $this->configComponent = new Config(Shopware()->Models());
        }

        return $this->configComponent;
    }
}

<?php
/**
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ShopwarePlugins\Connect\Components\Marketplace;

use Shopware\Components\Model\ModelManager;
use ShopwarePlugins\Connect\Components\Config;

class MarketplaceSettingsApplier
{
    /**
     * @var \ShopwarePlugins\Connect\Components\Config
     */
    private $configComponent;

    /**
     * @var \Shopware\Components\Model\ModelManager
     */
    private $modelsManager;

    /**
     * @var \Enlight_Components_Db_Adapter_Pdo_Mysql
     */
    private $db;

    public function __construct(
        Config $config,
        ModelManager $modelsManager,
        \Enlight_Components_Db_Adapter_Pdo_Mysql $db
    ) {
        $this->configComponent = $config;
        $this->modelsManager = $modelsManager;
        $this->db = $db;
    }

    /**
     * Applies marketplace configuration to connect plugin
     *
     * @param MarketplaceSettings $settings
     */
    public function apply(MarketplaceSettings $settings)
    {
        if (!$settings->isDefault) {
            $this->db->executeUpdate('UPDATE `s_core_config_forms` SET `label`=? WHERE name="SwagConnect"', [$settings->marketplaceName]);
            $this->db->executeUpdate('UPDATE `s_core_menu` SET `name`=? WHERE name="connect"', [$settings->marketplaceName]);
            $this->db->executeUpdate('UPDATE `s_core_snippets` SET `value`=? WHERE name="Connect"', [$settings->marketplaceName]);
            $this->db->executeUpdate('UPDATE `s_core_snippets` SET `value`=? WHERE name="Connect/Export"', ['Export']);
            $this->db->executeUpdate('UPDATE `s_core_snippets` SET `value`=? WHERE name="Connect/Import"', ['Import']);
            $this->db->executeUpdate('UPDATE `s_core_plugins` SET `label`=? WHERE name="SwagConnect"', [$settings->marketplaceName]);
            $this->configComponent->setMarketplaceSettings($settings);
            $this->cleanUpMarketplaceSnippets();
        } else {
            $this->configComponent->setConfig('isDefault', $settings->isDefault, null, 'marketplace');
        }
    }

    public function cleanUpMarketplaceSnippets()
    {
        $marketplaceSnippetsArray = [
            'config/api_key_description',
            'config/noindex_label',
            'config/connect_attribute_label',
            'config/log_description',
            'config/api_key_description',
            'config/noindex_label',
            'config/connect_attribute_label',
            'config/log_description',
            'window/connect_tab',
            'import/products/description',
            'window/title',
            'window/title_template',
            'mapping/options/importCategories',
            'connectFixedPriceMessage',
            'payment/connectAllowed',
            'order/fromRemote',
            'mapping/columns/connect-category',
            'config/export/product_description_field_help',
            'config/export/auto_product_sync_label',
            'config/export/changes_auto_played_label',
            'config/export/default_category_help',
            'config/help/debug_host',
            'config/help/connect_attribute',
            'config/marketplace/connect_attribute_header',
            'mapping/message/export/description',
            'config/shipping_groups/shipping_group_empty_text',
            'config/synchronization_bar_description',
            'config/export/exportLanguagesHelpText',
            'detail/price/connectPrice',
            'config/export/label/price_description',
            'config/log_label',
            'text/home/page',
            'text/home_page',
        ];

        $marketplaceSnippets = "'" . implode("','", $marketplaceSnippetsArray) . "'";
        $sql = "DELETE FROM `s_core_snippets` WHERE `name` IN ($marketplaceSnippets)";

        Shopware()->Db()->exec($sql);
    }
}

<?php
/**
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ShopwarePlugins\Connect\Subscribers;

use Enlight\Event\SubscriberInterface;
use Shopware\Connect\SDK;
use Shopware\Connect\Struct\PaymentStatus;
use Shopware\Components\Model\ModelManager;
use Shopware\Connect\Struct\Tracking;
use Shopware\CustomModels\Connect\Attribute;
use ShopwarePlugins\Connect\Components\Config;
use ShopwarePlugins\Connect\Components\Helper;
use ShopwarePlugins\Connect\Components\Utils;
use ShopwarePlugins\Connect\Components\ConnectExport;
use Shopware\Models\Order\Order;
use Symfony\Component\Config\Definition\Exception\Exception;

/**
 * Handles article lifecycle events in order to automatically update/delete products to/from connect
 *
 * Class Lifecycle
 * @package ShopwarePlugins\Connect\Subscribers
 */
class Lifecycle implements SubscriberInterface
{
    /**
     * @var \Shopware\Components\Model\ModelManager
     */
    private $manager;

    /**
     * @var Helper
     */
    private $helper;
    /**
     * @var SDK
     */
    private $sdk;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ConnectExport
     */
    private $connectExport;

    /**
     * @var int
     */
    private $autoUpdateProducts;

    /**
     * Lifecycle constructor.
     *
     * @param ModelManager $modelManager
     * @param Helper $helper
     * @param SDK $sdk
     * @param Config $config
     * @param ConnectExport $connectExport
     */
    public function __construct(
        ModelManager $modelManager,
        Helper $helper,
        SDK $sdk,
        Config $config,
        ConnectExport $connectExport
    ) {
        $this->manager = $modelManager;
        $this->helper = $helper;
        $this->sdk = $sdk;
        $this->config = $config;
        $this->connectExport = $connectExport;
        $this->autoUpdateProducts = $this->config->getConfig('autoUpdateProducts', 1);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware\Models\Article\Article::preUpdate' => 'onPreUpdate',
            'Shopware\Models\Article\Article::postPersist' => 'onUpdateArticle',
            'Shopware\Models\Article\Detail::postPersist' => 'onPersistDetail',
            'Shopware\Models\Article\Article::preRemove' => 'onDeleteArticle',
            'Shopware\Models\Article\Detail::preRemove' => 'onDeleteDetail',
            'Shopware\Models\Order\Order::postUpdate' => 'onUpdateOrder',
            'Shopware\Models\Shop\Shop::preRemove' => 'onDeleteShop',
            'Shopware\Models\Category\Category::preRemove' => 'onPreDeleteCategory',
            'Shopware\Models\Category\Category::postRemove' => 'onPostDeleteCategory'
        ];
    }

    public function onPreUpdate(\Enlight_Event_EventArgs $eventArgs)
    {
        /** @var \Shopware\Models\Article\Article $entity */
        $entity = $eventArgs->get('entity');
        $db = Shopware()->Db();

        // Check if entity is a connect product
        $attribute = $this->helper->getConnectAttributeByModel($entity);
        if (!$attribute) {
            return;
        }

        // if article is not exported to Connect
        // don't need to generate changes
        if (!$this->helper->isProductExported($attribute) || !empty($attribute->getShopId())) {
            return;
        }

        if (!SDK::isPriceTypeValid($this->sdk->getPriceType())) {
            return;
        }

        $changeSet = $eventArgs->get('entityManager')->getUnitOfWork()->getEntityChangeSet($entity);

        // If product propertyGroup is changed we need to store the old one,
        // because product property value are still not changed and
        // this will generate wrong Connect changes
        if ($changeSet['propertyGroup']) {
            $filterGroupId = $db->fetchOne(
                'SELECT filtergroupID FROM s_articles WHERE id = ?',
                [$entity->getId()]
            );

            $db->executeUpdate(
                'UPDATE `s_articles_attributes` SET `connect_property_group` = ? WHERE `articledetailsID` = ?',
                [$filterGroupId, $entity->getMainDetail()->getId()]
            );
        }
    }

    /**
     * @param \Enlight_Event_EventArgs $eventArgs
     */
    public function onUpdateOrder(\Enlight_Event_EventArgs $eventArgs)
    {
        /** @var \Shopware\Models\Order\Order $order */
        $order = $eventArgs->get('entity');

        // Compute the changeset and return, if orderStatus did not change
        $changeSet = $eventArgs->get('entityManager')->getUnitOfWork()->getEntityChangeSet($order);

        if (isset($changeSet['paymentStatus'])) {
            $this->updatePaymentStatus($order);
        }

        if (isset($changeSet['orderStatus']) || isset($changeSet['trackingCode'])) {
            $this->updateOrderStatus($order);
        }
    }

    /**
     * Callback function to delete an product from connect
     * after it is going to be deleted locally
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     */
    public function onDeleteArticle(\Enlight_Event_EventArgs $eventArgs)
    {
        $entity = $eventArgs->get('entity');
        $this->connectExport->setDeleteStatusForVariants($entity);
    }

    /**
     * Callback function to delete an product from connect
     * after it is going to be deleted locally
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     */
    public function onPreDeleteCategory(\Enlight_Event_EventArgs $eventArgs)
    {
        if ($this->autoUpdateProducts===Config::UPDATE_MANUAL) {
            return;
        }
        $category = $eventArgs->get('entity');
        $this->connectExport->markProductsInToBeDeletedCategories($category);
    }

    /**
     * Callback function to delete an product from connect
     * after it is going to be deleted locally
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     */
    public function onPostDeleteCategory(\Enlight_Event_EventArgs $eventArgs)
    {
        // if update is set to auto we have to export all marked products
        // else cron_job will do it or user does it manually
        if ($this->autoUpdateProducts===Config::UPDATE_AUTO) {
            $this->connectExport->handleMarkedProducts();
        }
    }

    /**
     * Callback function to delete product detail from connect
     * after it is going to be deleted locally
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     */
    public function onDeleteDetail(\Enlight_Event_EventArgs $eventArgs)
    {
        /** @var \Shopware\Models\Article\Detail $entity */
        $entity = $eventArgs->get('entity');
        if ($entity->getKind() !== 1) {
            $attribute = $this->helper->getConnectAttributeByModel($entity);

            if (!$attribute) {
                return;
            }

            if (!$this->helper->isProductExported($attribute)) {
                return;
            }
            $this->sdk->recordDelete($attribute->getSourceId());
            $this->connectExport->updateConnectItemsStatus([$attribute->getSourceId()], Attribute::STATUS_DELETE);
        }
    }

    /**
     * Callback method to update changed connect products
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     */
    public function onUpdateArticle(\Enlight_Event_EventArgs $eventArgs)
    {
        $entity = $eventArgs->get('entity');

        $this->handleChange($entity);
    }

    /**
     * Generate changes for Article or Detail if necessary
     *
     * @param \Shopware\Models\Article\Article | \Shopware\Models\Article\Detail $entity
     */
    public function handleChange($entity)
    {
        if (!$entity instanceof \Shopware\Models\Article\Article
            && !$entity instanceof \Shopware\Models\Article\Detail
        ) {
            return;
        }

        $id = $entity->getId();
        $className = get_class($entity);
        $model = $this->manager->getRepository($className)->find($id);
        // Check if we have a valid model
        if (!$model) {
            return;
        }

        // Check if entity is a connect product
        $attribute = $this->helper->getConnectAttributeByModel($model);
        if (!$attribute) {
            return;
        }

        // if article is not exported to Connect
        // or at least one article detail from same article is not exported
        // don't need to generate changes
        if (!$this->helper->isProductExported($attribute) || !empty($attribute->getShopId())) {
            if (!$this->helper->hasExportedVariants($attribute)) {
                return;
            }
        }

        if (!SDK::isPriceTypeValid($this->sdk->getPriceType())) {
            return;
        }

        $forceExport = false;
        if ($entity instanceof \Shopware\Models\Article\Detail) {
            $changeSet = $this->manager->getUnitOfWork()->getEntityChangeSet($entity);
            // if detail number has been changed
            // sc plugin must generate & sync the change immediately
            if (array_key_exists('number', $changeSet)) {
                $forceExport = true;
            }
        }

        // Mark the product for connect update
        try {
            if ($model instanceof \Shopware\Models\Article\Detail) {
                $this->generateChangesForDetail($model, $forceExport);
            } elseif ($model instanceof \Shopware\Models\Article\Article) {
                $this->generateChangesForArticle($model, $forceExport);
            }
        } catch (\Exception $e) {
            // If the update fails due to missing requirements
            // (e.g. category assignment), continue without error
        }
    }

    /**
     * Callback method to insert new article details in Connect system
     * Used when article is exported and after that variants are generated
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     */
    public function onPersistDetail(\Enlight_Event_EventArgs $eventArgs)
    {
        /** @var \Shopware\Models\Article\Detail $detail */
        $detail = $eventArgs->get('entity');

        /** @var \Shopware\Models\Article\Article $article */
        $article = $detail->getArticle();
        $articleAttribute = $this->helper->getConnectAttributeByModel($article);
        if (!$articleAttribute) {
            return;
        }

        // if article is not exported to Connect
        // don't need to generate changes
        if (!$this->helper->isProductExported($articleAttribute) || !empty($articleAttribute->getShopId())) {
            return;
        }

        if (!SDK::isPriceTypeValid($this->sdk->getPriceType())) {
            return;
        }

        // Mark the article detail for connect export
        try {
            $attribute = $this->helper->getOrCreateConnectAttributeByModel($detail);
            $forceExport = false;
            $changeSet = $eventArgs->get('entityManager')->getUnitOfWork()->getEntityChangeSet($detail);
            // if detail number has been changed
            // sc plugin must generate & sync the change immediately
            if (array_key_exists('number', $changeSet)) {
                $forceExport = true;
            }

            // if the variant is assigned to a new Article we have to delete the old variant
            // and insert a new ConnectAttribute with the right articleId
            if (array_key_exists('articleId', $changeSet) && $attribute->getArticleId() !== $detail->getArticleId()) {
                $this->sdk->recordDelete($attribute->getSourceId());
                $this->manager->delete($attribute);
                $this->manager->flush();

                $this->helper->getOrCreateConnectAttributeByModel($detail);
            }

            $this->generateChangesForDetail($detail, $forceExport);
        } catch (\Exception $e) {
            // If the update fails due to missing requirements
            // (e.g. category assignment), continue without error
        }
    }

    /**
     * Callback function to shop from export languages
     *
     * @param \Enlight_Event_EventArgs $eventArgs
     */
    public function onDeleteShop(\Enlight_Event_EventArgs $eventArgs)
    {
        /** @var \Shopware\Models\Shop\Shop $shop */
        $shop = $eventArgs->get('entity');
        $shopId = $shop->getId();

        $exportLanguages = $this->config->getConfig('exportLanguages');
        $exportLanguages = $exportLanguages ?: [];

        $shopIndex = array_search($shopId, $exportLanguages);
        if (false === $shopIndex) {
            return;
        }

        array_splice($exportLanguages, $shopIndex, 1);
        $this->config->setConfig('exportLanguages', $exportLanguages, null, 'export');
    }

    /**
     * @param \Shopware\Models\Article\Detail $detail
     * @param bool $force
     */
    private function generateChangesForDetail(\Shopware\Models\Article\Detail $detail, $force = false)
    {
        $attribute = $this->helper->getConnectAttributeByModel($detail);
        if (!$detail->getActive() && $this->config->getConfig('excludeInactiveProducts')) {
            $this->connectExport->syncDeleteDetail($detail);

            return;
        }

        if ($this->isAutoUpdateEnabled($force)) {
            $this->connectExport->export(
                [$attribute->getSourceId()],
                null
            );
        } elseif ($this->autoUpdateProducts == Config::UPDATE_CRON_JOB) {
            $this->manager->getConnection()->update(
                's_plugin_connect_items',
                ['cron_update' => 1],
                ['article_detail_id' => $detail->getId()]
            );
        }
    }

    /**
     * @param \Shopware\Models\Article\Article $article
     * @param bool $force
     */
    private function generateChangesForArticle(\Shopware\Models\Article\Article $article, $force = false)
    {
        if (!$article->getActive() && $this->config->getConfig('excludeInactiveProducts')) {
            $this->connectExport->setDeleteStatusForVariants($article);

            return;
        }

        if ($this->isAutoUpdateEnabled($force)) {
            $sourceIds = $this->helper->getSourceIdsFromArticleId($article->getId());

            $this->connectExport->export($sourceIds, null);
        } elseif ($this->autoUpdateProducts == Config::UPDATE_CRON_JOB) {
            $this->manager->getConnection()->update(
                's_plugin_connect_items',
                ['cron_update' => 1],
                ['article_id' => $article->getId()]
            );
        }
    }

    /**
     * @param bool $force
     * @return bool
     */
    private function isAutoUpdateEnabled($force)
    {
        return $this->autoUpdateProducts == Config::UPDATE_AUTO || $force === true;
    }

    /**
     * Sends the new order status when supplier change it
     *
     * @param Order $order
     */
    private function updateOrderStatus(Order $order)
    {
        $attribute = $order->getAttribute();
        if (!$attribute || !$attribute->getConnectShopId()) {
            return;
        }

        $orderStatusMapper = new Utils\OrderStatusMapper();
        $orderStatus = $orderStatusMapper->getOrderStatusStructFromOrder($order);
        $tracking = new Tracking();
        $tracking->id = $order->getTrackingCode();
        $orderStatus->tracking = $tracking;
        try {
            $this->sdk->updateOrderStatus($orderStatus);
        } catch (\Exception $e) {
            // if sn is not available, proceed without exception
        }
    }

    /**
     * Sends the new payment status when merchant change it
     *
     * @param Order $order
     */
    private function updatePaymentStatus(Order $order)
    {
        $orderUtil = new Utils\ConnectOrderUtil();
        if (!$orderUtil->hasLocalOrderConnectProducts($order->getId())) {
            return;
        }

        $paymentStatusMapper = new Utils\OrderPaymentStatusMapper();
        $paymentStatus = $paymentStatusMapper->getPaymentStatus($order);

        $this->generateChangeForPaymentStatus($paymentStatus);
    }

    /**
     * @param PaymentStatus $paymentStatus
     */
    private function generateChangeForPaymentStatus(PaymentStatus $paymentStatus)
    {
        try {
            $this->sdk->updatePaymentStatus($paymentStatus);
        } catch (\Exception $e) {
            // if sn is not available, proceed without exception
        }
    }
}

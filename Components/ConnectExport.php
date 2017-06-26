<?php

namespace ShopwarePlugins\Connect\Components;

use Doctrine\DBAL\DBALException;
use Shopware\Components\ContainerAwareEventManager;
use Shopware\Connect\SDK;
use Shopware\CustomModels\Connect\Attribute;
use ShopwarePlugins\Connect\Components\Marketplace\MarketplaceGateway;
use ShopwarePlugins\Connect\Components\Validator\ProductAttributesValidator;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use ShopwarePlugins\Connect\Components\ProductStream\ProductStreamsAssignments;
use ShopwarePlugins\Connect\Components\ErrorHandler;
use ShopwarePlugins\Connect\Struct\ExportList;
use ShopwarePlugins\Connect\Struct\SearchCriteria;
use Enlight_Event_EventManager;

class ConnectExport
{
    const BATCH_SIZE = 200;

    /** @var
     * Helper
     */
    protected $helper;

    /** @var
     * SDK
     */
    protected $sdk;

    /** @var
     * ModelManager
     */
    protected $manager;

    /**
     * @var ProductAttributesValidator
     */
    protected $productAttributesValidator;

    /** @var
     * MarketplaceGateway
     */
    protected $marketplaceGateway;

    /**
     * @var ErrorHandler
     */
    protected $errorHandler;

    /**
     * @var Config
     */
    protected $configComponent;

    /**
     * @var Enlight_Event_EventManager
     */
    private $eventManager;

    /**
     * ConnectExport constructor.
     * @param Helper $helper
     * @param SDK $sdk
     * @param ModelManager $manager
     * @param ProductAttributesValidator $productAttributesValidator
     * @param Config $configComponent
     * @param \ShopwarePlugins\Connect\Components\ErrorHandler $errorHandler
     * @param Enlight_Event_EventManager $eventManager
     */
    public function __construct(
        Helper $helper,
        SDK $sdk,
        ModelManager $manager,
        ProductAttributesValidator $productAttributesValidator,
        Config $configComponent,
        ErrorHandler $errorHandler,
        Enlight_Event_EventManager $eventManager
    )
    {
        $this->helper = $helper;
        $this->sdk = $sdk;
        $this->manager = $manager;
        $this->productAttributesValidator = $productAttributesValidator;
        $this->configComponent = $configComponent;
        $this->errorHandler = $errorHandler;
        $this->eventManager = $eventManager;
    }

    /**
     * Load article entity
     *
     * @param $id
     * @return null|\Shopware\Models\Article\Article
     */
    public function getArticleModelById($id)
    {
        return $this->manager->getRepository('Shopware\Models\Article\Article')->find($id);
    }

    /**
     * Load article detail entity
     *
     * @param $id
     * @return null|\Shopware\Models\Article\Detail
     */
    public function getArticleDetailById($id)
    {
        return $this->manager->getRepository('Shopware\Models\Article\Detail')->find($id);
    }

    /**
     * Helper function to mark a given array of source ids for connect update
     *
     * There is a problem with flush when is called from life cycle event in php7,
     * this flag '$isEvent' is preventing the flush
     *
     * @param array $ids
     * @param ProductStreamsAssignments|null $streamsAssignments
     * @return array
     */
    public function export(array $ids, ProductStreamsAssignments $streamsAssignments = null)
    {
        set_time_limit(120);
        p_open('filter');
        $ids = $this->eventManager->filter(
            'Connect_Supplier_Get_Products_Filter_Source_IDS',
            $ids,
            [
                'subject' => $this
            ]
        );
        p_close('filter');
        p_open('fetchConnectItems');
        $connectItems = $this->fetchConnectItems($ids);

        p_close('fetchConnectItems');
        $this->eventManager->notify(
            'Connect_Supplier_Get_All_Products_Before',
            [
                'subject' => $this,
                'products' => $connectItems
            ]
        );

        $this->manager->beginTransaction();
        foreach ($connectItems as &$item) {
            p_open('getArticleDetailById');
            $model = $this->getArticleDetailById($item['articleDetailId']);
            p_close('getArticleDetailById');
            if($model === null) {
                continue;
            }
            p_open('getOrCreateConnectAttributeByModel');
            $connectAttribute = $this->helper->getOrCreateConnectAttributeByModel($model);
            p_close('getOrCreateConnectAttributeByModel');
            $excludeInactiveProducts = $this->configComponent->getConfig('excludeInactiveProducts');
            if ($excludeInactiveProducts && !$model->getActive()) {
                p_open('excludeInactive');
                $this->updateLocalConnectItem(
                    $item['articleDetailId'],
                    array(
                        'export_status' => Attribute::STATUS_INACTIVE,
                        'exported' => false,
                        'export_message' =>  Shopware()->Snippets()->getNamespace('backend/connect/view/main')->get(
                            'export/message/error_product_is_not_active',
                            'Produkt ist inaktiv',
                            true
                        ),
                    )
                );
                $this->manager->refresh($connectAttribute);
                p_close('excludeInactive');
                continue;
            }

            if (!$this->helper->isProductExported($connectAttribute)) {
                $status = Attribute::STATUS_INSERT;
            } else {
                $status = Attribute::STATUS_UPDATE;
            }
            p_open('getConnectCategoryForProduct');
            $categories = $this->helper->getConnectCategoryForProduct($item['articleId']);
            if (is_string($categories)) {
                $categories = array($categories);
            }
            $categories = json_encode($categories);
            p_close('getConnectCategoryForProduct');
            p_open('updateLocalConnectItem');
            $this->updateLocalConnectItem(
                $item['articleDetailId'],
                array(
                    'export_status' => $status,
                    'export_message' => null,
                    'exported' => true,
                    'category' => $categories,
                )
            );
            p_close('updateLocalConnectItem');
            try {
                p_open('validate');
                $this->productAttributesValidator->validate($this->extractProductAttributes($model));
                p_close('validate');
                if ($status == Attribute::STATUS_INSERT) {
                    p_open('recordInsert');
                    $this->sdk->recordInsert($item['sourceId']);
                    p_close('recordInsert');
                } else {
                    p_open('recordUpdate');
                    $this->sdk->recordUpdate($item['sourceId']);
                    p_close('recordUpdate');
                }

                p_open('getStreams');
                if ($this->helper->isMainVariant($item['sourceId']) &&
                    $streamsAssignments !== null &&
                    $streamsAssignments->getStreamsByArticleId($item['articleId']) !== null
                ) {
                    $this->sdk->recordStreamAssignment(
                        $item['sourceId'],
                        $streamsAssignments->getStreamsByArticleId($item['articleId']),
                        $item['groupId']
                    );
                }
                p_close('getStreams');
            } catch (\Exception $e) {
                p_open('handleException');
                if ($this->errorHandler->isPriceError($e)) {
                    $this->updateLocalConnectItem(
                        $item['articleDetailId'],
                        array(
                            'export_status' => Attribute::STATUS_ERROR_PRICE,
                            'export_message' => Shopware()->Snippets()->getNamespace('backend/connect/view/main')->get(
                                'export/message/error_price_status',
                                'There is an empty price field',
                                true
                            ),
                        )
                    );
                } else {
                    $this->updateLocalConnectItem(
                        $item['articleDetailId'],
                        array(
                            'export_status' => Attribute::STATUS_ERROR,
                            'export_message' => $e->getMessage() . "\n" . $e->getTraceAsString(),
                        )
                    );
                }
                $this->errorHandler->handle($e);
                p_close('handleException');
            }
            $this->manager->refresh($connectAttribute);
        }

        try {
            p_open('commit');
            $this->manager->commit();
            p_close('commit');
        } catch (\Exception $e) {
            p_open('rollback');
            $this->manager->rollback();
            $this->errorHandler->handle($e);
            p_close('rollback');
        }

        file_put_contents('/tmp/connect_cron', print_r(p_dump(), 1), FILE_APPEND);
        return $this->errorHandler->getMessages();
    }

    /**
     * Update connect attribute data
     *
     * @param int $detailId
     * @param array $params
     */
    private function updateLocalConnectItem($detailId, $params = array())
    {
        if (empty($params)) {
            return;
        }
        $possibleValues = array(
            Attribute::STATUS_DELETE,
            Attribute::STATUS_INSERT,
            Attribute::STATUS_UPDATE,
            Attribute::STATUS_ERROR,
            Attribute::STATUS_ERROR_PRICE,
            Attribute::STATUS_INACTIVE,
            Attribute::STATUS_SYNCED,
            null,
        );

        if (isset($params['export_status']) && !in_array($params['export_status'], $possibleValues)) {
            throw new \InvalidArgumentException('Invalid export status');
        }

        if (isset($params['exported']) && !is_bool($params['exported'])) {
            throw new \InvalidArgumentException('Parameter $exported must be boolean.');
        }

        $builder = $this->manager->getConnection()->createQueryBuilder();
        $builder->update('s_plugin_connect_items', 'ci');
        array_walk($params, function($param, $name) use ($builder) {
            $builder->set('ci.' . $name, ':' . $name)
                    ->setParameter($name, $param);
        });

        $builder->where('article_detail_id = :articleDetailId')
                ->setParameter('articleDetailId', $detailId)
                ->andWhere('shop_id IS NULL')
                ->execute();
    }

    /**
     * Fetch connect items
     * Default order is main variant first, after that regular variants.
     * This is needed, because first received variant with an unknown groupId in Connect
     * will be selected as main variant.
     *
     * @param array $sourceIds
     * @param boolean $orderByMainVariants
     * @return array
     */
    public function fetchConnectItems(array $sourceIds, $orderByMainVariants = true)
    {
        if (count($sourceIds) == 0) {
            return array();
        }

        $implodedIds = '"' . implode('","', $sourceIds) . '"';
        $query = "SELECT bi.article_id as articleId,
            bi.export_message as exportMessage,
            bi.article_detail_id as articleDetailId,
                    bi.export_status as exportStatus,
                    bi.source_id as sourceId,
                    a.name as title,
                    IF (a.configurator_set_id IS NOT NULL, a.id, NULL) as groupId,
                    d.ordernumber as number
            FROM s_plugin_connect_items bi
            LEFT JOIN s_articles a ON bi.article_id = a.id
            LEFT JOIN s_articles_details d ON bi.article_detail_id = d.id
            WHERE bi.source_id IN ($implodedIds) ORDER BY d.kind";

        if ($orderByMainVariants === false) {
            $query .= ';';
            return Shopware()->Db()->fetchAll($query);
        }

        return Shopware()->Db()->fetchAll($query);
//        $query .= 'AND d.kind = ?;';
//        $mainVariants = Shopware()->Db()->fetchAll($query, array(1));
//        $regularVariants = Shopware()->Db()->fetchAll($query, array(2));

//        return array_merge($mainVariants, $regularVariants);
    }

    /**
     * Helper function to return export product ids
     * @return array
     */
    public function getExportArticlesIds()
    {
        $builder = $this->manager->createQueryBuilder();
        $builder->from('Shopware\CustomModels\Connect\Attribute', 'at');
        $builder->join('at.article', 'a');
        $builder->join('a.mainDetail', 'd');
        $builder->leftJoin('d.prices', 'p', 'with', "p.from = 1 AND p.customerGroupKey = 'EK'");
        $builder->leftJoin('a.supplier', 's');
        $builder->leftJoin('a.tax', 't');

        $builder->select(array('a.id'));

        $builder->where("at.exportStatus = 'update' OR at.exportStatus = 'insert' OR at.exportStatus = 'error'");
        $builder->andWhere('at.shopId IS NULL');

        $query = $builder->getQuery();
        $articles = $query->getArrayResult();

        $ids = array();
        foreach ($articles as $article) {
            $ids[] = $article['id'];
        }

        return $ids;
    }

    /**
     * Helper function to count how many changes
     * are waiting to be synchronized
     *
     * @return int
     */
    public function getChangesCount()
    {
        $sql = 'SELECT COUNT(*) FROM `sw_connect_change`';

        return (int)Shopware()->Db()->fetchOne($sql);
    }

    /**
     * Mark single connect product detail for delete
     *
     * @param \Shopware\Models\Article\Detail $detail
     */
    public function syncDeleteDetail(Detail $detail)
    {
        $attribute = $this->helper->getConnectAttributeByModel($detail);
        if (!$this->helper->isProductExported($attribute)) {
            return;
        }
        $this->sdk->recordDelete($attribute->getSourceId());
        $attribute->setExportStatus(Attribute::STATUS_DELETE);
        $attribute->setExported(false);
        $this->manager->persist($attribute);
        $this->manager->flush($attribute);
    }

    /**
     * Mark all product variants for delete
     *
     * @param Article $article
     */
    public function setDeleteStatusForVariants(Article $article)
    {
        $builder = $this->manager->createQueryBuilder();
        $builder->select(array('at.sourceId'))
            ->from('Shopware\CustomModels\Connect\Attribute', 'at')
            ->where('at.articleId = :articleId')
            ->andWhere('at.exported = 1')
            ->setParameter(':articleId', $article->getId());
        $connectItems = $builder->getQuery()->getArrayResult();

        foreach($connectItems as $item) {
            $this->sdk->recordDelete($item['sourceId']);
        }

        $builder = $this->manager->createQueryBuilder();
        $builder->update('Shopware\CustomModels\Connect\Attribute', 'at')
            ->set('at.exportStatus', $builder->expr()->literal(Attribute::STATUS_DELETE))
            ->set('at.exported', 0)
            ->where('at.articleId = :articleId')
            ->setParameter(':articleId', $article->getId());

        $builder->getQuery()->execute();
    }

    /**
     * @param array $sourceIds
     * @param $status
     */
    public function updateConnectItemsStatus(array $sourceIds, $status)
    {
        if (empty($sourceIds)) {
            return;
        }

        $chunks = array_chunk($sourceIds, self::BATCH_SIZE);

        foreach ($chunks as $chunk) {
            $builder = $this->manager->getConnection()->createQueryBuilder();
            $builder->update('s_plugin_connect_items', 'ci')
                ->set('ci.export_status', ':status')
                ->where('source_id IN (:sourceIds)')
                ->setParameter('sourceIds', $chunk, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
                ->setParameter('status', $status)
                ->execute();
        }
    }

    /**
     * @param SearchCriteria $criteria
     * @return ExportList
     */
    public function getExportList(SearchCriteria $criteria)
    {
        $customProductsTableExists = false;
        try {
            $builder = $this->manager->getConnection()->createQueryBuilder();
            $builder->select('id');
            $builder->from('s_plugin_custom_products_template');
            $builder->setMaxResults(1);
            $builder->execute()->fetch();

            $customProductsTableExists = true;
        } catch (DBALException $e) {
            // ignore it
            // custom products is not installed
        }

        $builder = $this->manager->getConnection()->createQueryBuilder();
        $builder->select(array(
            'a.id',
            'd.ordernumber as number',
            'd.inStock as inStock',
            'a.name as name',
            's.name as supplier',
            'a.active as active',
            't.tax as tax',
            'p.price * (100 + t.tax) / 100 as price',
            'i.category',
            'i.export_status as exportStatus',
            'i.export_message as exportMessage',
            'i.cron_update as cronUpdate'
        ))
            ->from('s_plugin_connect_items', 'i')
            ->innerJoin('i', 's_articles', 'a', 'a.id = i.article_id')
            ->innerJoin('a', 's_articles_details', 'd', 'a.main_detail_id = d.id')
            ->leftJoin('d', 's_articles_prices', 'p', 'd.id = p.articledetailsID')
            ->leftJoin('a', 's_core_tax', 't', 'a.taxID = t.id')
            ->leftJoin('a', 's_articles_supplier', 's', 'a.supplierID = s.id')
            ->groupBy('i.article_id')
            ->where('i.shop_id IS NULL');

        if ($customProductsTableExists) {
            $builder->addSelect("IF(spcptpr.template_id > 0, 1, 0) as customProduct")
                    ->leftJoin('a', 's_plugin_custom_products_template_product_relation', 'spcptpr', 'a.id = spcptpr.article_id');
        }

        if ($criteria->search) {
            $builder->andWhere('d.ordernumber LIKE :search OR a.name LIKE :search OR s.name LIKE :search')
                ->setParameter('search', $criteria->search);
        }

        if ($criteria->categoryId) {

            // Get all children categories
            $qBuilder = $this->manager->getConnection()->createQueryBuilder();
            $qBuilder->select('c.id');
            $qBuilder->from('s_categories', 'c');
            $qBuilder->where('c.path LIKE :categoryIdSearch');
            $qBuilder->orWhere('c.id = :categoryId');
            $qBuilder->setParameter(':categoryId', $criteria->categoryId);
            $qBuilder->setParameter(':categoryIdSearch', "%|$criteria->categoryId|%");

            $categoryIds = $qBuilder->execute()->fetchAll(\PDO::FETCH_COLUMN);

            if (count($categoryIds) === 0) {
                $categoryIds = array($criteria->categoryId);
            }

            $builder->innerJoin('a', 's_articles_categories', 'sac', 'a.id = sac.articleID')
                ->andWhere('sac.categoryID IN (:categoryIds)')
                ->setParameter('categoryIds', $categoryIds, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
        }

        if ($criteria->supplierId) {
            $builder->andWhere('a.supplierID = :supplierId')
                ->setParameter('supplierId', $criteria->supplierId);
        }

        if ($criteria->exportStatus) {
            $errorStatuses = [Attribute::STATUS_ERROR, Attribute::STATUS_ERROR_PRICE];

            if (in_array($criteria->exportStatus, $errorStatuses)) {
                $builder->andWhere('i.export_status IN (:status)')
                    ->setParameter('status', $errorStatuses, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
            } elseif ($criteria->exportStatus == Attribute::STATUS_INACTIVE) {
                $builder->andWhere('a.active = :status')
                    ->setParameter('status', false);
            } else {
                $builder->andWhere('i.export_status LIKE :status')
                    ->setParameter('status', $criteria->exportStatus);
            }
        }

        if ($criteria->active) {
            $builder->andWhere('a.active = :active')
                ->setParameter('active', $criteria->active);
        }

        if ($criteria->orderBy) {
            $builder->orderBy($criteria->orderBy, $criteria->orderByDirection);
        }

        $total = $builder->execute()->rowCount();

        $builder->setFirstResult($criteria->offset);
        $builder->setMaxResults($criteria->limit);

        $data = $builder->execute()->fetchAll();

        return new ExportList(array(
            'articles' => $data,
            'count' => $total,
        ));
    }

    public function clearConnectItems()
    {
        $this->deleteAllConnectProducts();
        $this->resetConnectItemsStatus();
    }

    /**
     * Deletes products hash
     */
    private function deleteAllConnectProducts()
    {
        $builder = $this->manager->getConnection()->createQueryBuilder();
        $builder->delete('sw_connect_product');
        $builder->execute();
    }

    /**
     * Resets all item status
     */
    private function resetConnectItemsStatus()
    {
        $builder = $this->manager->getConnection()->createQueryBuilder();
        $builder->update('s_plugin_connect_items', 'ci')
            ->set('export_status', ':exportStatus')
            ->set('revision', ':revision')
            ->set('exported', 0)
            ->setParameter('exportStatus', null)
            ->setParameter('revision', null);

        $builder->execute();
    }

    private function getMarketplaceGateway()
    {
        //todo@fixme: Implement better way to get MarketplaceGateway
        if (!$this->marketplaceGateway) {
            $this->marketplaceGateway = new MarketplaceGateway($this->manager);
        }

        return $this->marketplaceGateway;
    }

    /**
     * Extracts all marketplaces attributes from product
     *
     * @param Detail $detail
     * @return array
     */
    private function extractProductAttributes(Detail $detail)
    {
        $marketplaceAttributes = array();
        $marketplaceAttributes['purchaseUnit'] = $detail->getPurchaseUnit();
        $marketplaceAttributes['referenceUnit'] = $detail->getReferenceUnit();

        // marketplace attributes are available only for SEM shops
        if ($this->configComponent->getConfig('isDefault', true)) {
            return $marketplaceAttributes;
        }

        foreach ($this->getMarketplaceGateway()->getMappings() as $mapping) {
            $shopwareAttribute = $mapping['shopwareAttributeKey'];
            $getter = 'get' . ucfirst($shopwareAttribute);

            if (method_exists($detail->getAttribute(), $getter)) {
                $marketplaceAttributes[$shopwareAttribute] = $detail->getAttribute()->{$getter}();
            }
        }

        return $marketplaceAttributes;
    }
}

function p_open($flag) {
    global $p_times;
    if (null === $p_times)
        $p_times = [];
    if (! array_key_exists($flag, $p_times))
        $p_times[$flag] = [ 'total' => 0, 'open' => 0 ];
    $p_times[$flag]['open'] = microtime(true);
}

function p_close($flag)
{
    global $p_times;
    if (isset($p_times[$flag]['open'])) {
        $p_times[$flag]['total'] += (microtime(true) - $p_times[$flag]['open']);
        unset($p_times[$flag]['open']);
    }
}

function p_dump()
{
    global $p_times;
    $dump = [];
    $sum  = 0;
    foreach ($p_times as $flag => $info) {
        $dump[$flag]['elapsed'] = $info['total'];
        $sum += $info['total'];
    }
    foreach ($dump as $flag => $info) {
        $dump[$flag]['percent'] = $dump[$flag]['elapsed']/$sum;
    }
    return $dump;
}
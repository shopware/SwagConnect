<?php
/**
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ShopwarePlugins\Connect\Subscribers;

use Doctrine\DBAL\Connection;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\ProductStream\ProductStream;
use ShopwarePlugins\Connect\Components\ProductStream\ProductStreamService;

class Category extends BaseSubscriber
{
    /** @var Connection $connection */
    private $connection;

    private $parentCollection = [];

    public function __construct(ModelManager $modelManager)
    {
        parent::__construct();
        $this->connection = $modelManager->getConnection();
    }

    public function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Backend_Category' => 'extendBackendCategory',
        ];
    }

    /**
     * @return ProductStreamService;
     */
    public function getProductStreamService()
    {
        return $this->Application()->Container()->get('swagconnect.product_stream_service');
    }

    /**
     * @event Enlight_Controller_Action_PostDispatch_Backend_Article
     * @param \Enlight_Event_EventArgs $args
     */
    public function extendBackendCategory(\Enlight_Event_EventArgs $args)
    {
        /** @var $subject \Enlight_Controller_Action */
        $subject = $args->getSubject();
        $request = $subject->Request();
        $expandedCategories = $request->getParam('expandedCategories', []);

        $scQuery = $request->getParam('localCategoriesQuery', '');

        switch ($request->getActionName()) {
            case 'getList':
                if (trim($scQuery) !== '') {
                    $parentId = $request->getParam('id', null);
                    $subject->View()->data = $this->getCategoriesByQuery($scQuery, $parentId);
                }

                $subject->View()->data = $this->extendTreeNodes(
                    $subject->View()->data
                );

                $subject->View()->data = $this->expandCategories(
                    $subject->View()->data,
                    $expandedCategories
                );
                break;
            case 'updateDetail':
                $streamId = $request->getParam('streamId');
                if ($streamId) {
                    $this->activateConnectProducts($streamId);
                }
                break;
        }
    }

    /**
     * @param $streamId
     */
    private function activateConnectProducts($streamId)
    {
        $streamService = $this->getProductStreamService();

        /** @var ProductStream $stream */
        try {
            $stream = $streamService->findStream($streamId);
        } catch (\Exception $e) {
            return;
        }

        if ($streamService->isConnectStream($stream) && $streamService->isStatic($stream)) {
            $streamService->activateConnectProductsByStream($stream);
        }
    }

    private function expandCategories(array $nodes, $expandedCategories)
    {
        if (count($expandedCategories) === 0) {
            return $nodes;
        }

        foreach ($nodes as $index => $node) {
            if (in_array($node['id'], $expandedCategories)) {
                $nodes[$index]['expanded'] = true;
            }
        }

        return $nodes;
    }

    /**
     * @param array $nodes
     * @return array
     */
    private function extendTreeNodes(array $nodes)
    {
        if (count($nodes) === 0) {
            return $nodes;
        }
        $categoryIds = array_map(function ($node) {
            return (int) $node['id'];
        }, $nodes);

        $builder = $this->connection->createQueryBuilder();
        $builder->select('categoryID')
            ->from('s_categories_attributes', 'ca')
            ->where('ca.categoryID IN (:categoryIds)')
            ->andWhere('ca.connect_imported_category = 1')
            ->setParameter(':categoryIds', $categoryIds, Connection::PARAM_STR_ARRAY);

        $rows = [];
        $stmt = $builder->execute();
        while ($row = $stmt->fetch()) {
            $rows[] = $row['categoryID'];
        }

        if (!$rows) {
            return $nodes;
        }

        foreach ($nodes as $index => $node) {
            if (in_array($node['id'], $rows)) {
                $nodes[$index]['cls'] = 'sc-tree-node';
                $nodes[$index]['iconCls'] = 'sc-icon';
            }

            if (!$node['active']) {
                $nodes[$index]['cls'] .= ' inactive';
            }
        }

        return $nodes;
    }

    public function getCategoriesByQuery($query, $parentId)
    {
        $builder = $this->connection->createQueryBuilder();
        $builder->select('ca.*')
            ->from('s_categories', 'ca')
            ->where('ca.description LIKE :query')
            ->setParameter(':query', '%' . $query . '%');

        $statement = $builder->execute();
        $rows = $statement->fetchAll();

        $parents = [];
        foreach ($rows as $row) {
            $maxParent = $this->getMaxRootCategories($row, $parentId);
            if (!in_array($maxParent, $parents) && $maxParent !== null) {
                $parents[] = $maxParent;
            }
        }

        $nodes = [];
        foreach ($parents as $parent) {
            $nodes[] = $this->createTreeNode(
                $parent['id'],
                $parent['description'],
                $parent['parent'],
                '',
                $this->isLeaf($parent['id']),
                true,
                true
            );
        }

        return $nodes;
    }

    public function createTreeNode($id, $name, $parentId, $class, $leaf, $allowDrag, $expanded)
    {
        return [
            'id' => $id,
            'active' => true,
            'name' => $name,
            'position' => null,
            'parentId' => $parentId,
            'text' => $name,
            'cls' => $class,
            'leaf' => (bool) $leaf,
            'allowDrag' => $allowDrag,
            'expanded' => $expanded
        ];
    }

    public function getMaxRootCategories($category, $parent)
    {
        if (in_array($category['parent'], $this->parentCollection)) {
            return null;
        }

        if ($category['parent'] == 1 && $parent != 'NaN') {
            return null;
        }

        if ($category['parent'] == 1 && $parent == 'NaN') {
            return $category;
        }

        if ($category['parent'] == $parent) {
            return $category;
        }

        $builder = $this->connection->createQueryBuilder();
        $builder->select('ca.*')
            ->from('s_categories', 'ca')
            ->where('ca.id = :parentId')
            ->setParameter(':parentId', $category['parent']);

        $parentCategory = $builder->execute()->fetch();

        $this->parentCollection[] = $category['parent'];

        return $this->getMaxRootCategories($parentCategory, $parent);
    }

    public function isLeaf($categoryId)
    {
        $builder = $this->connection->createQueryBuilder();
        $builder->select('ca.id')
            ->from('s_categories', 'ca')
            ->where('ca.parent = :parentId')
            ->setParameter(':parentId', $categoryId);

        $count = $builder->execute()->rowCount();

        return $count == 0;
    }
}

<?php
/**
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ShopwarePlugins\Connect\Components;

use Doctrine\DBAL\Connection;
use Shopware\Bundle\AttributeBundle\Service\DataPersister;
use Shopware\Components\Model\CategoryDenormalization;
use Shopware\Connect\Struct\Product;
use ShopwarePlugins\Connect\Components\CategoryResolver\AutoCategoryResolver;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\MultiEdit\Resource\ResourceInterface;
use Shopware\CustomModels\Connect\ProductToRemoteCategoryRepository;
use Shopware\CustomModels\Connect\RemoteCategoryRepository;
use Shopware\Models\Article\Repository as ArticleRepository;
use Shopware\Models\Category\Repository as CategoryRepository;

class ImportService
{
    /**
     * @var ModelManager
     */
    private $manager;

    /**
     * @var \Shopware\Components\MultiEdit\Resource\ResourceInterface
     */
    private $productResource;

    /**
     * @var \Shopware\Models\Category\Repository
     */
    private $categoryRepository;

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var RemoteCategoryRepository
     */
    private $remoteCategoryRepository;

    /**
     * @var ProductToRemoteCategoryRepository
     */
    private $productToRemoteCategoryRepository;

    /**
     * @var CategoryResolver
     */
    private $autoCategoryResolver;

    /**
     * @var CategoryExtractor
     */
    private $categoryExtractor;

    /**
     * @var CategoryDenormalization
     */
    private $categoryDenormalization;

    /**
     * @var DataPersister
     */
    private $dataPersister;

    public function __construct(
        ModelManager $manager,
        ResourceInterface $productResource,
        CategoryRepository $categoryRepository,
        ArticleRepository$articleRepository,
        RemoteCategoryRepository $remoteCategoryRepository,
        ProductToRemoteCategoryRepository $productToRemoteCategoryRepository,
        AutoCategoryResolver $categoryResolver,
        CategoryExtractor $categoryExtractor,
        CategoryDenormalization $categoryDenormalization,
        DataPersister $dataPersister
    ) {
        $this->manager = $manager;
        $this->productResource = $productResource;
        $this->categoryRepository = $categoryRepository;
        $this->articleRepository = $articleRepository;
        $this->remoteCategoryRepository = $remoteCategoryRepository;
        $this->productToRemoteCategoryRepository = $productToRemoteCategoryRepository;
        $this->autoCategoryResolver = $categoryResolver;
        $this->categoryExtractor = $categoryExtractor;
        $this->categoryDenormalization = $categoryDenormalization;
        $this->dataPersister = $dataPersister;
    }

    public function findBothArticlesType($categoryId, $query = '', $showOnlyConnectArticles = true, $limit = 10, $offset = 0)
    {
        if ($categoryId == 0) {
            return [];
        }

        return $this->productResource->filter($this->getAst($categoryId, $query, $showOnlyConnectArticles), $offset, $limit);
    }

    /**
     * @param $categoryId
     * @return bool
     */
    public function hasCategoryChildren($categoryId)
    {
        return (bool) $this->categoryRepository->getChildrenCountList($categoryId);
    }

    public function assignCategoryToArticles($categoryId, array $articleIds)
    {
        $articles = $this->articleRepository->findBy(['id' => $articleIds]);

        if (empty($articles)) {
            throw new \RuntimeException('Invalid article ids');
        }

        /** @var \Shopware\Models\Category\Category $category */
        $category = $this->categoryRepository->find($categoryId);
        if (!$category) {
            throw new \RuntimeException('Invalid category id');
        }

        /** @var \Shopware\Models\Article\Article $article */
        foreach ($articles as $article) {
            $article->addCategory($category);
            $this->manager->persist($article);
            /** @var \Shopware\Models\Article\Detail $detail */
            foreach ($article->getDetails() as $detail) {
                $attribute = $detail->getAttribute();
                $attribute->setConnectMappedCategory(true);
                $this->manager->persist($attribute);
            }
        }

        $this->manager->flush();
    }

    /**
     * Unassign categories from given article ids
     * for the given categoryId and all childcategories
     * or for all categories if $categoryId is null
     * Set connect_mapped_category flag in article
     * attributes to NULL
     *
     * @param array $articleIds
     * @param int|null $categoryId
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Exception
     */
    public function unAssignArticleCategories(array $articleIds, $categoryId = null)
    {
        if (!empty($articleIds)) {
            // cast all items in $articleIds to int
            // before use them in WHERE IN clause
            foreach ($articleIds as $key => $articleId) {
                $articleIds[$key] = (int) $articleId;
            }

            $connection = $this->manager->getConnection();
            $connection->beginTransaction();

            try {
                if ($categoryId !== null) {
                    $this->unAssignArticlesFromCategory($articleIds, $categoryId);
                } else {
                    $this->unAssignArticlesFromAllCategories($articleIds);
                }

                $connection->commit();
            } catch (\Exception $e) {
                $connection->rollBack();
                throw new \Exception($e->getMessage());
            }
        }
    }

    /**
     * Collect remote article ids by given category id
     *
     * @param int $localCategoryId
     * @return array
     */
    public function findRemoteArticleIdsByCategoryId($localCategoryId)
    {
        $connection = $this->manager->getConnection();
        $sql = 'SELECT sac.articleID
            FROM s_articles_categories sac
            LEFT JOIN s_articles_attributes saa ON sac.articleID = saa.articleID
            WHERE sac.categoryID = :categoryId AND saa.connect_mapped_category = 1';
        $rows = $connection->fetchAll($sql, [':categoryId' => $localCategoryId]);

        return array_map(function ($row) {
            return $row['articleID'];
        }, $rows);
    }

    /**
     * @param array $categoryIds
     * @return int
     */
    public function deactivateLocalCategoriesByIds(array $categoryIds)
    {
        $builder = $this->manager->getConnection()->createQueryBuilder();
        $rowCount = $builder->update('s_categories', 'c')
            ->set('c.active', 0)
            ->where('c.id IN (:categoryIds)')
            ->setParameter('categoryIds', $categoryIds, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
            ->execute();

        return $rowCount;
    }

    /**
     * Collect all child categories by given
     * remote category key and create same
     * categories structure as Shopware Connect structure.
     * Find all remote products which belong to these categories
     * and assign them.
     *
     * @param int $localCategoryId
     * @param string $remoteCategoryKey
     * @param string $remoteCategoryLabel
     * @param int|null $shopId
     * @param string|null $stream
     * @return array
     */
    public function importRemoteCategoryCreateLocalCategories($localCategoryId, $remoteCategoryKey, $remoteCategoryLabel, $shopId = null, $stream = null)
    {
        /** @var \Shopware\Models\Category\Category $localCategory */
        $localCategory = $this->categoryRepository->find((int) $localCategoryId);
        if (!$localCategory) {
            throw new \RuntimeException('Local category not found!');
        }

        /** @var \Shopware\CustomModels\Connect\RemoteCategory $remoteCategory */
        $remoteCategory = $this->remoteCategoryRepository->findOneBy(['categoryKey' => $remoteCategoryKey]);
        if (!$remoteCategory) {
            throw new \RuntimeException('Remote category not found!');
        }

        // collect his child categories and
        // generate remote category tree by given remote category
        $remoteCategoryChildren = $this->categoryExtractor->getRemoteCategoriesTree(
            $remoteCategoryKey,
            true,
            false,
            $shopId,
            $stream
        );
        $remoteCategoryNodes = [
            [
                'name' => $remoteCategoryLabel,
                'categoryId' => $remoteCategoryKey,
                'leaf' => empty($remoteCategoryChildren) ? true : false,
                'children' => $remoteCategoryChildren,
            ]
        ];

        // create same category structure as Shopware Connect structure
        return $this->autoCategoryResolver->convertTreeToKeys($remoteCategoryNodes, $localCategory->getId(), $shopId, $stream, false);
    }

    /**
     * @param array $articleIds
     */
    public function activateArticles(array $articleIds)
    {
        $articleBuilder = $this->manager->createQueryBuilder();
        $articleBuilder->update('\Shopware\Models\Article\Article', 'a')
            ->set('a.active', 1)
            ->where('a.id IN (:articleIds)')
            ->setParameter(':articleIds', $articleIds, Connection::PARAM_STR_ARRAY);

        $articleBuilder->getQuery()->execute();

        $detailBuilder = $this->manager->createQueryBuilder();
        $detailBuilder->update('\Shopware\Models\Article\Detail', 'd')
            ->set('d.active', 1)
            ->where('d.articleId IN (:articleIds)')
            ->setParameter(':articleIds', $articleIds, Connection::PARAM_STR_ARRAY);

        $detailBuilder->getQuery()->execute();
    }

    /**
     * Store remote categories in Connect tables
     * and add relations between categories and products.
     *
     * @param array $remoteItems
     *
     * @throws \Exception
     */
    public function storeRemoteCategories(array $remoteItems)
    {
        $connection = $this->manager->getConnection();

        $connection->beginTransaction();
        try {
            /** @var Product $product */
            foreach ($remoteItems as $articleId => $product) {
                $this->autoCategoryResolver->storeRemoteCategories($product->categories, $articleId, $product->shopId);
            }
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    /**
     * Fetch remote (Connect) categories by given article ids
     * @param array $articleIds
     * @return array
     */
    public function fetchRemoteCategoriesByArticleIds(array $articleIds)
    {
        $remoteCategoryIds = [];
        while ($currentIdBatch = array_splice($articleIds, 0, 500)) {
            $sql = 'SELECT sac.categoryID
            FROM s_articles_categories sac
            LEFT JOIN s_categories_attributes attr ON sac.categoryID = attr.categoryID
            WHERE attr.connect_imported_category = 1 AND sac.articleID IN (' . implode(', ', $currentIdBatch) . ') GROUP BY sac.categoryID';
            $rows = $this->manager->getConnection()->fetchAll($sql);

            $remoteCategoryIds = array_merge($remoteCategoryIds, array_map(function ($row) {
                return $row['categoryID'];
            }, $rows));
        }

        return array_unique($remoteCategoryIds);
    }

    /**
     * Fetch all articles where categories are auto imported
     * and there isn't record in s_plugin_connect_product_to_categories for them.
     * Returned array contains key = articleId and value = array of categories
     *
     * @return Product[]
     */
    public function getArticlesWithAutoImportedCategories()
    {
        $statement = $this->manager->getConnection()->prepare(
            'SELECT b.article_id, b.category, b.shop_id
            FROM s_plugin_connect_items b
            LEFT JOIN s_plugin_connect_product_to_categories a ON b.article_id = a.articleID
            WHERE b.shop_id > 0 AND a.connect_category_id IS NULL GROUP BY b.article_id'
        );
        $statement->execute();

        $remoteItems = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $item) {
            $categories = json_decode($item['category'], true);
            if (is_array($categories) && count($categories) > 0) {
                $product = new Product();
                $product->shopId = $item['shop_id'];
                $product->categories = $categories;
                $remoteItems[$item['article_id']] = $product;
            }
        }

        return $remoteItems;
    }

    /**
     * Helper function to create filter values
     * @param int $categoryId
     * @param bool $showOnlyConnectArticles
     * @param string $query
     * @return array
     */
    private function getAst($categoryId, $query = '', $showOnlyConnectArticles = true)
    {
        $ast = [
            [
                'type' => 'nullaryOperators',
                'token' => 'ISMAIN',
            ]
        ];

        if (trim($query) !== '') {
            $queryArray = [
                [
                    'type' => 'boolOperators',
                    'token' => 'AND',
                ],
                [
                    'type' => 'subOperators',
                    'token' => '(',
                ],
                [
                    'type' => 'attribute',
                    'token' => 'ARTICLE.NAME'
                ],
                [
                    'type' => 'binaryOperator',
                    'token' => '~'
                ],
                [
                    'type' => 'values',
                    'token' => '"' . $query . '"'
                ],
                [
                    'type' => 'boolOperators',
                    'token' => 'OR',
                ],
                [
                    'type' => 'attribute',
                    'token' => 'SUPPLIER.NAME'
                ],
                [
                    'type' => 'binaryOperator',
                    'token' => '~'
                ],
                [
                    'type' => 'values',
                    'token' => '"' . $query . '"'
                ],
                [
                    'type' => 'boolOperators',
                    'token' => 'OR',
                ],
                [
                    'type' => 'attribute',
                    'token' => 'DETAIL.NUMBER'
                ],
                [
                    'type' => 'binaryOperator',
                    'token' => '~'
                ],
                [
                    'type' => 'values',
                    'token' => '"' . $query . '"'
                ],
                [
                    'type' => 'subOperators',
                    'token' => ')',
                ]
            ];
            $ast = array_merge($ast, $queryArray);
        }

        $categoryArray = [
            [
            'type' => 'boolOperators',
            'token' => 'AND',
            ],
            [
                'type' => 'subOperators',
                'token' => '(',
            ],
            [
                'type' => 'attribute',
                'token' => 'CATEGORY.PATH',
            ],
            [
                'type' => 'binaryOperators',
                'token' => '=',
            ],
            [
                'type' => 'values',
                'token' => '"%|' . $categoryId . '|%"',
            ],
            [
                'type' => 'boolOperators',
                'token' => 'OR',
            ],
            [
                'type' => 'attribute',
                'token' => 'CATEGORY.ID',
            ],
            [
                'type' => 'binaryOperators',
                'token' => '=',
            ],
            [
                'type' => 'values',
                'token' => $categoryId,
            ],
            [
                'type' => 'subOperators',
                'token' => ')',
            ]
        ];

        $ast = array_merge($ast, $categoryArray);

        if ($showOnlyConnectArticles === true) {
            $ast = array_merge($ast, [
                [
                    'type' => 'boolOperators',
                    'token' => 'AND',
                ],
                [
                    'type' => 'attribute',
                    'token' => 'ATTRIBUTE.CONNECTMAPPEDCATEGORY',
                ],
                [
                    'type' => 'binaryOperators',
                    'token' => '!=',
                ],
                [
                    'type' => 'values',
                    'token' => 'NULL',
                ],
            ]);
        }

        return $ast;
    }

    /**
     * @param array $articleIds
     * @param $categoryId
     */
    private function unAssignArticlesFromCategory(array $articleIds, $categoryId)
    {
        $categories = [];
        $categories[] = $categoryId;
        $childCategories = $this->manager->getConnection()->executeQuery(
            'SELECT id FROM s_categories WHERE path LIKE ?',
            ["%|$categoryId|%"]
        );

        while ($childCategory = $childCategories->fetchColumn()) {
            $categories[] = (int) $childCategory;
        }

        $categoriesStatement = $this->manager->getConnection()->prepare('DELETE FROM s_articles_categories WHERE articleID IN (' . implode(', ', $articleIds) . ') AND categoryID IN (' . implode(', ', $categories) . ')');
        $categoriesStatement->execute();

        $categoriesStatement = $this->manager->getConnection()->prepare('DELETE FROM s_articles_categories_ro WHERE articleID IN (' . implode(', ', $articleIds) . ') AND parentCategoryID IN (' . implode(', ', $categories) . ')');
        $categoriesStatement->execute();

        $attributeStatement = $this->manager->getConnection()->prepare('
                    UPDATE s_articles_attributes 
                    SET connect_mapped_category = 0 
                    WHERE articleID IN (' . implode(', ', $articleIds) . ') 
                      AND 
                        (SELECT COUNT(*) FROM s_articles_categories 
                            INNER JOIN s_categories_attributes ON s_articles_categories.categoryID = s_categories_attributes.categoryID
                            WHERE s_articles_categories.articleID = s_articles_attributes.articleID 
                                AND s_categories_attributes.connect_imported_category = 1
                        ) = 0
                ');
        $attributeStatement->execute();
    }

    /**
     * Unassign all categories from given article ids
     * @param array $articleIds
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Exception
     */
    private function unAssignArticlesFromAllCategories(array $articleIds)
    {
        $attributeStatement = $this->manager->getConnection()->prepare(
            'UPDATE s_articles_attributes SET connect_mapped_category = 0 WHERE articleID IN (' . implode(', ', $articleIds) . ')'
        );
        $attributeStatement->execute();
        $categoriesStatement = $this->manager->getConnection()->prepare('DELETE FROM s_articles_categories WHERE articleID IN (' . implode(', ', $articleIds) . ')');
        $categoriesStatement->execute();
        $categoryLogStatement = $this->manager->getConnection()->prepare('DELETE FROM s_articles_categories_ro WHERE articleID IN (' . implode(', ', $articleIds) . ')');
        $categoryLogStatement->execute();
    }

    /**
     * @param int $shopId
     * @param string $remoteCategoryKey
     * @param string $stream
     * @param int $localCategoryId
     * @param int $localParentId
     * @param int $offset
     * @param int $limit
     */
    public function importRemoteCategoryAssignArticles($shopId, $remoteCategoryKey, $stream, $localCategoryId, $localParentId, $offset, $limit)
    {
        $articleIds = $this->productToRemoteCategoryRepository->findArticleIdsByRemoteCategoryAndStream($remoteCategoryKey, $shopId, $stream, $offset, $limit);
        foreach ($articleIds as $articleId) {
            $this->categoryDenormalization->addAssignment($articleId, $localCategoryId);
            $this->categoryDenormalization->removeAssignment($articleId, $localParentId);
            $this->manager->getConnection()->executeQuery(
                'INSERT IGNORE INTO `s_articles_categories` (`articleID`, `categoryID`) VALUES (?, ?)',
                [$articleId, $localCategoryId]
            );
            $this->manager->getConnection()->executeQuery(
                'DELETE FROM `s_articles_categories` WHERE `articleID` = :articleID AND `categoryID` = :categoryID',
                [
                    ':articleID' => $articleId,
                    ':categoryID' => $localParentId
                ]
            );
            $detailId = $this->manager->getConnection()->fetchColumn(
                'SELECT main_detail_id FROM `s_articles` WHERE `id` = :articleID',
                ['articleID' => $articleId]
            );
            $this->manager->getConnection()->executeQuery(
                'INSERT  INTO `s_articles_attributes` (`articleID`, `articledetailsID`, `connect_mapped_category`) 
                    VALUES (?, ?, 1)
                    ON DUPLICATE KEY UPDATE `connect_mapped_category` = 1
                ',
                [$articleId, $detailId]
            );
        }
    }

    /**
     * @param int $shopId
     * @param string $remoteCategoryKey
     * @param string $stream
     * @return int
     */
    public function importRemoteCategoryGetArticleCountForCategory($shopId, $remoteCategoryKey, $stream)
    {
        return $this->productToRemoteCategoryRepository->getArticleCountByRemoteCategoryAndStream($remoteCategoryKey, $shopId, $stream);
    }
}

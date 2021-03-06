<?php
/**
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ShopwarePlugins\Connect\Tests\Integration\Components;

use Shopware\Connect\Struct\Product;
use Shopware\Models\Article\Unit;
use Shopware\Models\Article\Article;
use Shopware\Models\Category\Category;
use Shopware\CustomModels\Connect\RemoteCategory;
use Shopware\CustomModels\Connect\ProductToRemoteCategory;
use ShopwarePlugins\Connect\Components\ConfigFactory;
use ShopwarePlugins\Connect\Components\Helper;
use ShopwarePlugins\Connect\Components\CategoryResolver\AutoCategoryResolver;
use ShopwarePlugins\Connect\Tests\ConnectTestHelperTrait;
use ShopwarePlugins\Connect\Tests\DatabaseTestCaseTrait;

class HelperTest extends \PHPUnit_Framework_TestCase
{
    use DatabaseTestCaseTrait;
    use ConnectTestHelperTrait;

    public function testGetDefaultCustomerGroup()
    {
        $group = $this->getHelper()->getDefaultCustomerGroup();
        $this->assertInstanceOf('\Shopware\Models\Customer\Group', $group);
    }

    public function testHasBasketConnectProductsIsFalse()
    {
        $result = $this->getHelper()->hasBasketConnectProducts(333);
        $this->assertFalse($result);
    }

    public function testGetConnectAttributeByModel()
    {
        $attributes = Shopware()->Models()->getRepository('Shopware\CustomModels\Connect\Attribute')->findBy(['articleId' => 14]);
        foreach ($attributes as $attribute) {
            Shopware()->Models()->remove($attribute);
        }
        Shopware()->Models()->flush();

        /** @var \Shopware\Models\Article\Article $model */
        $model = Shopware()->Models()->getRepository('Shopware\Models\Article\Article')->find(14);

        $connectAttribute = $this->getHelper()->getConnectAttributeByModel($model);
        $this->assertEmpty($connectAttribute);
    }

    public function testGetOrCreateConnectAttributeByModel()
    {
        /** @var \Shopware\Models\Article\Article $model */
        $model = Shopware()->Models()->getRepository('Shopware\Models\Article\Article')->find(14);

        $connectAttribute = $this->getHelper()->getOrCreateConnectAttributeByModel($model);
        $this->assertNotEmpty($connectAttribute->getId());
    }

    public function testUpdateUnitInRelatedProducts()
    {
        $localUnit = new Unit();
        $localUnit->setName('Yard');
        $localUnit->setUnit('lyrd');
        Shopware()->Models()->persist($localUnit);
        Shopware()->Models()->flush($localUnit);

        $remoteUnit = 'yrd';

        $manager = $this->getMockBuilder('\\Shopware\\Components\\Model\\ModelManager')
            ->disableOriginalConstructor()
            ->getMock();

        $connection = $this->getMockBuilder('\\Doctrine\\DBAL\\Connection')
            ->disableOriginalConstructor()
            ->getMock();
        $manager->expects($this->any())->method('getConnection')->willReturn($connection);

        $statement = $this->getMockBuilder('\\Doctrine\\DBAL\\Statement')
        ->disableOriginalConstructor()
        ->getMock();

        $connection->expects($this->any())->method('prepare')->with(
            'UPDATE s_articles_details sad
            LEFT JOIN s_articles_attributes saa ON sad.id = saa.articledetailsID
            SET sad.unitID = :unitId
            WHERE saa.connect_remote_unit = :remoteUnit'
        )->willReturn($statement);

        $statement->expects($this->at(0))->method('bindValue')->with(':unitId', $localUnit->getId(), \PDO::PARAM_INT);
        $statement->expects($this->at(1))->method('bindValue')->with(':remoteUnit', $remoteUnit, \PDO::PARAM_STR);

        $statement->expects($this->atLeast(1))->method('execute');

        $helper = new Helper(
            $manager,
            $this->getMockBuilder('\\ShopwarePlugins\\Connect\\Components\\CategoryQuery\\SwQuery')->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder('\\ShopwarePlugins\\Connect\\Components\\ProductQuery')->disableOriginalConstructor()->getMock()
        );

        $helper->updateUnitInRelatedProducts($localUnit, $remoteUnit);
        Shopware()->Models()->remove($localUnit);
        Shopware()->Models()->flush($localUnit);
    }

    public function testIsMainVariant()
    {
        $article = Shopware()->Models()->getRepository('Shopware\Models\Article\Article')->findOneBy(['id' => 5]);
        $this->getHelper()->getOrCreateConnectAttributeByModel($article);
        $detail = Shopware()->Models()->getRepository('Shopware\Models\Article\Detail')->findOneBy(['id' => 253]);
        $this->getHelper()->getOrCreateConnectAttributeByModel($detail);

        $this->assertTrue($this->getHelper()->isMainVariant('5'));
        $this->assertFalse($this->getHelper()->isMainVariant('5-253'));
    }

    public function testGenerateSourceId()
    {
        /** @var \Shopware\Models\Article\Detail $detail */
        $detail = Shopware()->Models()->getRepository('Shopware\Models\Article\Detail')->findOneBy(['kind' => 2]);

        $detailSourceId = sprintf(
            '%s-%s',
            $detail->getArticle()->getId(),
            $detail->getId()
        );
        $this->assertEquals($this->getHelper()->generateSourceId($detail), $detailSourceId);
        $articleSourceId = (string) $detail->getArticle()->getId();

        $this->assertEquals($this->getHelper()->generateSourceId($detail->getArticle()->getMainDetail()), $articleSourceId);
    }

    public function testGetConnectCategoriesForProductAndAutResolveCategories()
    {
        $manager = Shopware()->Models();
        $article = $manager->getRepository(Article::class)->findOneBy(['id' => 5]);
        $manager->getConnection()->executeQuery(
            'DELETE FROM `s_articles_categories` WHERE `articleID` = :articleID',
            [':articleID' => $article->getId()]
        );
        //test with leaf category and not leafcategory
        //ids come from default fixture they shouldn't change
        $notLeafCategoryId = 9;
        $count = $manager->getConnection()->executeQuery(
            'SELECT COUNT(*) FROM `s_categories` WHERE `parent` = :parentID',
            [':parentID' => $notLeafCategoryId]
        )->fetchColumn();
        $this->assertGreaterThan(0, $count);
        $leafCategoryId = 14;
        $count = $manager->getConnection()->executeQuery(
            'SELECT COUNT(*) FROM `s_categories` WHERE `parent` = :parentID',
            [':parentID' => $leafCategoryId]
        )->fetchColumn();
        $this->assertEquals(0, $count);
        $manager->getConnection()->executeQuery(
            'INSERT INTO `s_articles_categories` (`articleID`, `categoryID`) VALUES (?, ?)',
            [$article->getId(), $notLeafCategoryId]
        );
        $manager->getConnection()->executeQuery(
            'INSERT INTO `s_articles_categories` (`articleID`, `categoryID`) VALUES (?, ?)',
            [$article->getId(), $leafCategoryId]
        );

        $categories = $this->getHelper()->getConnectCategoryForProduct($article->getId());

        $autoCategoryResolver = new AutoCategoryResolver(
            $manager,
            $manager->getRepository(Category::class),
            $manager->getRepository(RemoteCategory::class),
            ConfigFactory::getConfigInstance(),
            Shopware()->Container()->get('CategoryDenormalization'),
            $manager->getRepository(ProductToRemoteCategory::class)
        );

        $autoCategoryResolver->storeRemoteCategories($categories, $article->getId(), 1234);
        $categoryKeys = $autoCategoryResolver->resolve($categories, 1234, 'test');

        $this->assertCount(2, $categoryKeys);
        $this->assertGreaterThan(count($categoryKeys), count($categories));

        $name = $manager->getConnection()->executeQuery(
            'SELECT `description` FROM `s_categories` WHERE `id` = :categoryID',
            [':categoryID' => $categoryKeys[0]]
        )->fetchColumn();
        $this->assertEquals('Freizeitwelten', $name);

        $name = $manager->getConnection()->executeQuery(
            'SELECT `description` FROM `s_categories` WHERE `id` = :categoryID',
            [':categoryID' => $categoryKeys[1]]
        )->fetchColumn();
        $this->assertEquals('Edelbrände', $name);
    }

    public function testRecreateConnectCategoriesRestoresAllCategories()
    {
        $manager = Shopware()->Models();
        $manager->getConnection()->executeQuery('DELETE FROM s_plugin_connect_categories');
        $manager->getConnection()->executeQuery('DELETE FROM s_plugin_connect_product_to_categories');
        $manager->getConnection()->executeQuery('DELETE FROM s_plugin_connect_items');

        $categories = [
            '/deutsch' => 'Deutsch',
            '/deutsch/test1' => 'Test 1',
            '/deutsch/test1/test11' => 'Test 1.1',
            '/deutsch/test2' => 'Test 2',
        ];
        $categoriesJson = json_encode($categories);

        $manager->getConnection()->executeQuery(
            'INSERT INTO s_plugin_connect_items (article_id, shop_id, category) VALUES (?, ?, ?)',
            [3, 1, $categoriesJson]
        );

        $this->getHelper()->recreateConnectCategories(0, 50);

        $result = $manager->getConnection()->executeQuery('SELECT COUNT(*) FROM s_plugin_connect_categories')->fetchColumn();
        $this->assertEquals('4', $result);

        $result = $manager->getConnection()->executeQuery('SELECT COUNT(*) FROM s_plugin_connect_product_to_categories WHERE articleID = 3')->fetchColumn();
        $this->assertEquals('4', $result);
    }

    public function testRecreateConnectCategoriesRestoresAllCategoriesWithMultipleProducts()
    {
        $manager = Shopware()->Models();
        $manager->getConnection()->executeQuery('DELETE FROM s_plugin_connect_categories');
        $manager->getConnection()->executeQuery('DELETE FROM s_plugin_connect_product_to_categories');
        $manager->getConnection()->executeQuery('DELETE FROM s_plugin_connect_items');

        $categories = [
            '/deutsch' => 'Deutsch',
            '/deutsch/test1' => 'Test 1',
            '/deutsch/test1/test11' => 'Test 1.1',
            '/deutsch/test2' => 'Test 2',
        ];
        $categoriesJson = json_encode($categories);

        $manager->getConnection()->executeQuery(
            'INSERT INTO s_plugin_connect_items (article_id, shop_id, category) VALUES (?, ?, ?)',
            [3, 1, $categoriesJson]
        );
        $manager->getConnection()->executeQuery(
            'INSERT INTO s_plugin_connect_items (article_id, shop_id, category) VALUES (?, ?, ?)',
            [4, 1, $categoriesJson]
        );

        $this->getHelper()->recreateConnectCategories(0, 50);

        $result = $manager->getConnection()->executeQuery('SELECT COUNT(*) FROM s_plugin_connect_categories')->fetchColumn();
        $this->assertEquals('4', $result);

        $result = $manager->getConnection()->executeQuery('SELECT COUNT(*) FROM s_plugin_connect_product_to_categories WHERE articleID = 3 OR articleID = 4')->fetchColumn();
        $this->assertEquals('8', $result);
    }

    public function testRecreateConnectCategoriesRestoresAllCategoriesInMultipleBatches()
    {
        $manager = Shopware()->Models();
        $manager->getConnection()->executeQuery('DELETE FROM s_plugin_connect_categories');
        $manager->getConnection()->executeQuery('DELETE FROM s_plugin_connect_product_to_categories');
        $manager->getConnection()->executeQuery('DELETE FROM s_plugin_connect_items');

        $categories = [
            '/deutsch' => 'Deutsch',
        ];
        $categoriesJson = json_encode($categories);

        for ($i = 1; $i < 110; ++$i) {
            $manager->getConnection()->executeQuery(
                'INSERT INTO s_plugin_connect_items (article_id, shop_id, category) VALUES (?, ?, ?)',
                [$i, 1, $categoriesJson]
            );
        }

        $this->getHelper()->recreateConnectCategories(0, 50);
        $this->getHelper()->recreateConnectCategories(50, 50);
        $this->getHelper()->recreateConnectCategories(100, 50);

        $result = $manager->getConnection()->executeQuery('SELECT COUNT(*) FROM s_plugin_connect_categories')->fetchColumn();
        $this->assertEquals('1', $result);

        $result = $manager->getConnection()->executeQuery('SELECT COUNT(*) FROM s_plugin_connect_product_to_categories')->fetchColumn();
        $this->assertEquals('109', $result);
    }

    public function testRecreateConnectCategoriesAssignesAllCategories()
    {
        $manager = Shopware()->Models();
        $manager->getConnection()->executeQuery('DELETE FROM s_plugin_connect_categories');
        $manager->getConnection()->executeQuery('DELETE FROM s_plugin_connect_product_to_categories');
        $manager->getConnection()->executeQuery('DELETE FROM s_plugin_connect_items');

        $categories = [
            '/deutsch' => 'Deutsch',
            '/deutsch/test1' => 'Test 1',
            '/deutsch/test1/test11' => 'Test 1.1',
            '/deutsch/test2' => 'Test 2',
        ];

        foreach ($categories as $categoryKey => $category) {
            $manager->getConnection()->executeQuery(
                'INSERT INTO s_plugin_connect_categories (category_key, label, shop_id) VALUES (?, ?, ?)',
                [$categoryKey, $category, 1]
            );
        }

        $categoriesJson = json_encode($categories);

        $manager->getConnection()->executeQuery(
            'INSERT INTO s_plugin_connect_items (article_id, shop_id, category) VALUES (?, ?, ?)',
            [3, 1, $categoriesJson]
        );

        $this->getHelper()->recreateConnectCategories(0, 50);

        $result = $manager->getConnection()->executeQuery('SELECT COUNT(*) FROM s_plugin_connect_categories')->fetchColumn();
        $this->assertEquals('4', $result);

        $result = $manager->getConnection()->executeQuery('SELECT COUNT(*) FROM s_plugin_connect_product_to_categories WHERE articleID = 3')->fetchColumn();
        $this->assertEquals('4', $result);
    }

    public function testCheckIfConnectCategoriesHaveToBeRecreatedReturnsTrue()
    {
        $manager = Shopware()->Models();
        $manager->getConnection()->executeQuery('INSERT INTO `s_plugin_connect_config` (`name`, `value`) VALUES ("recreateConnectCategories", "0")');

        $result = $this->getHelper()->checkIfConnectCategoriesHaveToBeRecreated();
        $this->assertTrue($result);
    }

    public function testCheckIfConnectCategoriesHaveToBeRecreatedReturnsFalseIfValueIsOne()
    {
        $manager = Shopware()->Models();
        $manager->getConnection()->executeQuery('INSERT INTO `s_plugin_connect_config` (`name`, `value`) VALUES ("recreateConnectCategories", "1")');

        $result = $this->getHelper()->checkIfConnectCategoriesHaveToBeRecreated();
        $this->assertFalse($result);
    }

    public function testCheckIfConnectCategoriesHaveToBeRecreatedReturnsFalseIfValueIsNegative()
    {
        $manager = Shopware()->Models();
        $manager->getConnection()->executeQuery('INSERT INTO `s_plugin_connect_config` (`name`, `value`) VALUES ("recreateConnectCategories", "-1")');

        $result = $this->getHelper()->checkIfConnectCategoriesHaveToBeRecreated();
        $this->assertFalse($result);
    }

    public function testCheckIfConnectCategoriesHaveToBeRecreatedReturnsFalseIfEntryDoesNotExist()
    {
        $result = $this->getHelper()->checkIfConnectCategoriesHaveToBeRecreated();
        $this->assertFalse($result);
    }

    public function testGetProductCountForCategoryRecovery()
    {
        $manager = Shopware()->Models();
        $manager->getConnection()->executeQuery('DELETE FROM s_plugin_connect_items');

        $manager->getConnection()->executeQuery('INSERT INTO s_plugin_connect_items (article_id, shop_id) VALUES (4, 1)');
        $manager->getConnection()->executeQuery('INSERT INTO s_plugin_connect_items (article_id, shop_id) VALUES (3, 1)');
        $manager->getConnection()->executeQuery('INSERT INTO s_plugin_connect_items (article_id, shop_id) VALUES (5, null)');

        $count = $this->getHelper()->getProductCountForCategoryRecovery();
        $this->assertEquals(2, $count);
    }

    public function testGetProductCountForCategoryRecoveryReturnsZero()
    {
        $manager = Shopware()->Models();
        $manager->getConnection()->executeQuery('DELETE FROM s_plugin_connect_items');

        $manager->getConnection()->executeQuery('INSERT INTO s_plugin_connect_items (article_id, shop_id) VALUES (5, null)');

        $count = $this->getHelper()->getProductCountForCategoryRecovery();
        $this->assertEquals(0, $count);
    }

    public function testAddShopIdToConnectCategoriesWithMultipleSupplier()
    {
        $manager = Shopware()->Models();
        $this->importFixtures(__DIR__ . '/../_fixtures/connect_items_categories_multiple_supplier.sql');

        $this->getHelper()->addShopIdToConnectCategories(0, 50);

        //assert that each category exists for both suppliers
        $deutschCategoryForShop1111 = $manager->getConnection()->fetchColumn('SELECT id FROM s_plugin_connect_categories WHERE label = "Deutsch" AND shop_id = 1111');
        $this->assertGreaterThan(0, $deutschCategoryForShop1111);
        $testCategoryForShop1111 = $manager->getConnection()->fetchColumn('SELECT id FROM s_plugin_connect_categories WHERE label = "Test" AND shop_id = 1111');
        $this->assertGreaterThan(0, $testCategoryForShop1111);
        $test1CategoryForShop1111 = $manager->getConnection()->fetchColumn('SELECT id FROM s_plugin_connect_categories WHERE label = "Test1" AND shop_id = 1111');
        $this->assertGreaterThan(0, $test1CategoryForShop1111);
        $test2CategoryForShop1111 = $manager->getConnection()->fetchColumn('SELECT id FROM s_plugin_connect_categories WHERE label = "Test2" AND shop_id = 1111');
        $this->assertGreaterThan(0, $test2CategoryForShop1111);

        $deutschCategoryForShop1222 = $manager->getConnection()->fetchColumn('SELECT id FROM s_plugin_connect_categories WHERE label = "Deutsch" AND shop_id = 1222');
        $this->assertGreaterThan(0, $deutschCategoryForShop1222);
        $testCategoryForShop1222 = $manager->getConnection()->fetchColumn('SELECT id FROM s_plugin_connect_categories WHERE label = "Test" AND shop_id = 1222');
        $this->assertGreaterThan(0, $testCategoryForShop1222);
        $test1CategoryForShop1222 = $manager->getConnection()->fetchColumn('SELECT id FROM s_plugin_connect_categories WHERE label = "Test1" AND shop_id = 1222');
        $this->assertGreaterThan(0, $test1CategoryForShop1222);
        $test2CategoryForShop1222 = $manager->getConnection()->fetchColumn('SELECT id FROM s_plugin_connect_categories WHERE label = "Test2" AND shop_id = 1222');
        $this->assertGreaterThan(0, $test2CategoryForShop1222);

        //assert that old categories got updated
        $deutschCategory = $manager->getConnection()->fetchColumn('SELECT id FROM s_plugin_connect_categories WHERE label = "Deutsch" AND shop_id = NULL');
        $this->assertFalse($deutschCategory);

        $testCategory= $manager->getConnection()->fetchColumn('SELECT id FROM s_plugin_connect_categories WHERE label = "Test" AND shop_id = NULL');
        $this->assertFalse($testCategory);

        $test1Category = $manager->getConnection()->fetchColumn('SELECT id FROM s_plugin_connect_categories WHERE label = "Test1" AND shop_id = NULL');
        $this->assertFalse($test1Category);

        $test2Category = $manager->getConnection()->fetchColumn('SELECT id FROM s_plugin_connect_categories WHERE label = "Test2" AND shop_id = NULL');
        $this->assertFalse($test2Category);

        //assert that product to categories gets updated
        $n = $manager->getConnection()->fetchColumn("SELECT id FROM s_plugin_connect_product_to_categories WHERE connect_category_id = $deutschCategoryForShop1111 AND articleID = 3");
        $this->assertGreaterThan(0, $n);
        $n = $manager->getConnection()->fetchColumn("SELECT id FROM s_plugin_connect_product_to_categories WHERE connect_category_id = $deutschCategoryForShop1111 AND articleID = 4");
        $this->assertGreaterThan(0, $n);
        $n = $manager->getConnection()->fetchColumn("SELECT id FROM s_plugin_connect_product_to_categories WHERE connect_category_id = $testCategoryForShop1111 AND articleID = 3");
        $this->assertGreaterThan(0, $n);
        $n = $manager->getConnection()->fetchColumn("SELECT id FROM s_plugin_connect_product_to_categories WHERE connect_category_id = $testCategoryForShop1111 AND articleID = 4");
        $this->assertGreaterThan(0, $n);
        $n = $manager->getConnection()->fetchColumn("SELECT id FROM s_plugin_connect_product_to_categories WHERE connect_category_id = $test1CategoryForShop1111 AND articleID = 3");
        $this->assertGreaterThan(0, $n);
        $n = $manager->getConnection()->fetchColumn("SELECT id FROM s_plugin_connect_product_to_categories WHERE connect_category_id = $test2CategoryForShop1111 AND articleID = 4");
        $this->assertGreaterThan(0, $n);

        $n = $manager->getConnection()->fetchColumn("SELECT id FROM s_plugin_connect_product_to_categories WHERE connect_category_id = $deutschCategoryForShop1222 AND articleID = 5");
        $this->assertGreaterThan(0, $n);
        $n = $manager->getConnection()->fetchColumn("SELECT id FROM s_plugin_connect_product_to_categories WHERE connect_category_id = $deutschCategoryForShop1222 AND articleID = 6");
        $this->assertGreaterThan(0, $n);
        $n = $manager->getConnection()->fetchColumn("SELECT id FROM s_plugin_connect_product_to_categories WHERE connect_category_id = $testCategoryForShop1222 AND articleID = 5");
        $this->assertGreaterThan(0, $n);
        $n = $manager->getConnection()->fetchColumn("SELECT id FROM s_plugin_connect_product_to_categories WHERE connect_category_id = $testCategoryForShop1222 AND articleID = 6");
        $this->assertGreaterThan(0, $n);
        $n = $manager->getConnection()->fetchColumn("SELECT id FROM s_plugin_connect_product_to_categories WHERE connect_category_id = $test1CategoryForShop1222 AND articleID = 6");
        $this->assertGreaterThan(0, $n);
        $n = $manager->getConnection()->fetchColumn("SELECT id FROM s_plugin_connect_product_to_categories WHERE connect_category_id = $test2CategoryForShop1222 AND articleID = 5");
        $this->assertGreaterThan(0, $n);

        //assert that product to local categories gets updated
        $n = $manager->getConnection()->fetchAll("SELECT * FROM s_plugin_connect_categories_to_local_categories WHERE remote_category_id = $test1CategoryForShop1111 AND local_category_id= 1885");
        $this->assertInternalType('array', $n);
        $n = $manager->getConnection()->fetchAll("SELECT * FROM s_plugin_connect_categories_to_local_categories WHERE remote_category_id = $test1CategoryForShop1222 AND local_category_id= 1885");
        $this->assertInternalType('array', $n);

        $n = $manager->getConnection()->fetchAll("SELECT * FROM s_plugin_connect_categories_to_local_categories WHERE remote_category_id = $test2CategoryForShop1111 AND local_category_id= 1886");
        $this->assertInternalType('array', $n);
        $n = $manager->getConnection()->fetchAll("SELECT * FROM s_plugin_connect_categories_to_local_categories WHERE remote_category_id = $test2CategoryForShop1222 AND local_category_id= 1886");
        $this->assertInternalType('array', $n);
    }
}

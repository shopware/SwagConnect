<?php

namespace Tests\Shopware\Bepado;

use Bepado\SDK\Struct\Translation;
use Shopware\Bepado\Components\BepadoExport;
use Shopware\Bepado\Components\ImageImport;
use Shopware\Bepado\Components\Logger;
use Shopware\Bepado\Components\Validator\ProductAttributesValidator\ProductsAttributesValidator;
use Shopware\CustomModels\Bepado\Attribute;
use Shopware\Bepado\Components\Config;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Article\Price;
use Shopware\Models\Tax\Tax;

class BepadoTestHelper extends \Enlight_Components_Test_Plugin_TestCase
{

    /**
     * @return \Bepado\SDK\SDK
     */
    public function getSDK()
    {
        return Shopware()->Bootstrap()->getResource('BepadoSDK');
    }

    /**
     * @return int
     */
    public function getBepadoProductArticleId($sourceId, $shopId=3)
    {
        $id = Shopware()->Db()->fetchOne(
            'SELECT article_id FROM s_plugin_bepado_items WHERE source_id = ? and shop_id =  ? LIMIT 1',
            array($sourceId, $shopId)
        );
        return $id;
    }

    public function getExternalProductSourceId()
    {
        $sql = 'SELECT source_id FROM s_plugin_bepado_items WHERE shop_id IS NOT NULL';
        $sourceId = Shopware()->Db()->fetchOne($sql);

        return $sourceId;
    }

    /**
     * @return \Shopware\Bepado\Components\Helper
     */
    public function getHelper()
    {
        return Shopware()->Plugins()->Backend()->SwagBepado()->getHelper();
    }


    public function callPrivate($class, $method, $args)
    {
        $method = new \ReflectionMethod(
            $class, $method
        );

        $method->setAccessible(true);

        return call_user_func(array($method, 'invoke', $args));
    }

    /**
     * @return BepadoExport
     */
    public function getBepadoExport()
    {
        return new BepadoExport(
            $this->getHelper(),
            $this->getSDK(),
            Shopware()->Models(),
            new ProductsAttributesValidator(),
            new Config(Shopware()->Models())
        );
    }


    /**
     * @return ImageImport
     */
    public function getImageImport()
    {
        return new ImageImport(
            Shopware()->Models(),
            $this->getHelper(),
            new Logger(Shopware()->Db())
        );
    }

    public function changeCategoryBepadoMappingForCategoryTo($categoryId, $mapping)
    {
        $modelManager = Shopware()->Models();
        $categoryRepository = $modelManager->getRepository('Shopware\Models\Category\Category');
        $category = $categoryRepository->find($categoryId);

        if (!$category) {
            $this->fail('Could not find category with ID ' . $categoryId);
        }

        $attribute = $category->getAttribute() ?: new \Shopware\Models\Attribute\Category();
        $attribute->setBepadoImportMapping($mapping);
        $attribute->setBepadoExportMapping($mapping);
        $category->setAttribute($attribute);

        $modelManager->flush();
    }

    public static function dispatchRpcCall($service, $command, array $args)
    {
        $sdk = Shopware()->Bootstrap()->getResource('BepadoSDK');
        $refl = new \ReflectionObject($sdk);
        $property = $refl->getProperty('dependencies');
        $property->setAccessible(true);
        $deps = $property->getValue($sdk);
        $serviceRegistry = $deps->getServiceRegistry();
        $callable = $serviceRegistry->getService($service, $command);

        return call_user_func_array(array($callable['provider'], $callable['command']), $args);
    }

    protected function getProduct($withImage=false)
    {
        $purchasePrice = 6.99;
        $offerValidUntil = time() + 1 * 365 * 24 * 60 * 60; // One year
        $number = rand(1, 999999999);
        $product =  new \Bepado\SDK\Struct\Product(array(
            'shopId' => 3,
            'revisionId' => time(),
            'sourceId' => $number,
            'ean' => $number,
            'url' => 'http://shopware.de',
            'title' => 'MassImport #'. $number,
            'shortDescription' => 'Ein Produkt aus Bepado',
            'longDescription' => 'Ein Produkt aus Bepado',
            'vendor' => 'Bepado',
            'price' => 9.99,
            'purchasePrice' => $purchasePrice,
            'purchasePriceHash' => hash_hmac(
                'sha256',
                sprintf('%.3F %d', $purchasePrice, $offerValidUntil), '54642546-0001-48ee-b4d0-4f54af66d822'
            ),
            'offerValidUntil' => $offerValidUntil,
            'availability' => 100,
            'categories' => array('/bücher'),
            'translations' => array(
                'en' => new Translation(array(
                    'title' => 'MassImport #'. $number . ' EN',
                    'longDescription' => 'Ein Produkt aus Bepado EN',
                    'shortDescription' => 'Ein Produkt aus Bepado short EN',
                    'url' => 'http://shopware.de',
                ))
            )
        ));

        if ($withImage) {
            $product->images = array('http://lorempixel.com/400/200?'.$number);
        }

        return $product;
    }

    protected function getProducts($number=10, $withImage=false)
    {
        $products = array();
        for($i=0; $i<$number; $i++) {
            $products[] = $this->getProduct($withImage);
        }
        return $products;
    }

    protected function getVariants()
    {
        $number = $groupId = rand(1, 999999999);
        $color = array(
            array('de' => 'Weiss-Blau' . $number, 'en' => 'White-Blue'),
            array('de' => 'Weiss-Rot' . $number, 'en' => 'White-Red'),
            array('de' => 'Blau-Rot' . $number, 'en' => 'Blue-Red'),
            array('de' => 'Schwarz-Rot' . $number, 'en' => 'Black-Red'),
        );

        $variants = array();
        $mainVariant = $this->getProduct(true);
        $mainVariantColor = array_pop($color);
        $mainVariant->variant['Farbe'] = $mainVariantColor['de'];
        $mainVariant->groupId = $groupId;
        $variants[] = $mainVariant;

        //add translations
        $mainVariant->translations['en']->variantLabels = array(
            'Farbe' => 'Color',
        );
        $mainVariant->translations['en']->variantValues = array(
            $mainVariantColor['de'] => $mainVariantColor['en'],
        );

        for ($i=0; $i < 4 - 1; $i++) {
            $variant = $this->getProduct(true);
            $variantSourceId = $mainVariant->sourceId . '-' . $i;
            $variant->title = 'MassImport #'. $variantSourceId;
            $variant->sourceId = $variantSourceId;
            $variant->ean = $variantSourceId;
            $variantColor = array_pop($color);
            $variant->variant['Farbe'] = $variantColor['de'];
            $variant->groupId = $groupId;
            $variant->translations = array(
                'en' => new Translation(array(
                   'title' =>  'MassImport #'. $variantSourceId . ' EN',
                   'longDescription' =>  $mainVariant->longDescription . ' EN',
                   'shortDescription' =>  $mainVariant->shortDescription . ' EN',
                    'variantLabels' => array(
                        'Farbe' => 'Color',
                    ),
                    'variantValues' => array(
                        $variantColor['de'] => $variantColor['en'],
                    ),
                )),
            );

            $variants[] = $variant;
        }

        return $variants;
    }

    public function getLocalArticle()
    {
        $number = rand(1, 999999999);

        $article = new Article();
        $article->fromArray(array(
            'name' => 'LocalArticle #'. $number,
            'active' => true,
        ));
        $tax = Shopware()->Models()->getRepository('Shopware\Models\Tax\Tax')->find(1);
        $article->setTax($tax);

        $supplier = Shopware()->Models()->getRepository('Shopware\Models\Article\Supplier')->find(1);
        $article->setSupplier($supplier);

        Shopware()->Models()->persist($article);
        Shopware()->Models()->flush();

        $mainDetail = new Detail();
        $mainDetail->fromArray(array(
            'number' => $number,
            'inStock' => 30,
            'article' => $article
        ));
        $article->setMainDetail($mainDetail);
        $detailAtrribute = new \Shopware\Models\Attribute\Article();
        $detailAtrribute->fromArray(array(
            'article' => $article,
            'articleDetail' => $mainDetail,
        ));

        $customerGroup = Shopware()->Models()->getRepository('Shopware\Models\Customer\Group')->findOneByKey('EK');

        $price = new Price();
        $price->fromArray(array(
            'article' => $article,
            'detail' => $mainDetail,
            'customerGroup' => $customerGroup,
            'from' => 1,
            'price' => 8.99,
            'basePrice' => 3.99,
        ));
        $mainDetail->setPrices(array($price));

        $bepadoAttribute = new Attribute();
        $bepadoAttribute->fromArray(array(
            'isMainVariant' => true,
            'article' => $article,
            'articleDetail' => $article->getMainDetail(),
            'sourceId' => $article->getId(),
            'category' => '/bücher',
            'fixedPrice' => false,
            'purchasePriceHash' => '',
            'offerValidUntil' => 0,
        ));

        Shopware()->Models()->persist($mainDetail);
        Shopware()->Models()->persist($detailAtrribute);
        Shopware()->Models()->persist($price);
        Shopware()->Models()->persist($bepadoAttribute);
        Shopware()->Models()->flush();

        return $article;
    }

    protected function insertOrUpdateProducts($number, $withImage)
    {
        $commands = array();
        foreach ($this->getProducts($number, $withImage) as $product) {
            $commands[$product->sourceId] = new \Bepado\SDK\Struct\Change\ToShop\InsertOrUpdate(array(
                'product' => $product,
                'revision' => time(),
            ));
        }

        $this->dispatchRpcCall('products', 'toShop', array(
            $commands
        ));

        return array_keys($commands);
    }
}
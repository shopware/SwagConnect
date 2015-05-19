<?php

namespace Tests\Shopware\Bepado\Component\Translations;

use Bepado\SDK\Struct\Translation;
use Shopware\Models\Shop\Locale;
use Tests\Shopware\Bepado\BepadoTestHelper;

class ProductTranslatorTest extends BepadoTestHelper
{
    /**
     * @var \Shopware\Bepado\Components\Translations\ProductTranslatorInterface
     */
    private $productTranslator;

    private $configComponent;

    private $translationGateway;

    private $modelManager;

    private $shopRepository;

    private $localeRepository;

    public function setUp()
    {
        $this->configComponent = $this->getMockBuilder('\\Shopware\\Bepado\\Components\\Config')
            ->disableOriginalConstructor()
            ->getMock();

        $this->translationGateway = $this->getMockBuilder('\\Shopware\\Bepado\\Components\\Gateway\\ProductTranslationsGateway\\PdoProductTranslationsGateway')
            ->disableOriginalConstructor()
            ->getMock();

        $this->modelManager = $this->getMockBuilder('\\Shopware\\Components\\Model\\ModelManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->localeRepository = $this->getMockBuilder('\\Shopware\\Components\\Model\\ModelRepository')
            ->disableOriginalConstructor()
            ->getMock();

        $this->shopRepository = $this->getMockBuilder('\\Shopware\\Models\\Shop\\Repository')
            ->disableOriginalConstructor()
            ->getMock();

        $this->productTranslator = new \Shopware\Bepado\Components\Translations\ProductTranslator(
            $this->configComponent,
            $this->translationGateway,
            $this->modelManager,
            $this->getProductBaseUrl()
        );
    }

    public function testTranslate()
    {
        $translations = array(
            2 => array(
                'title' => 'Bepado Local Product EN',
                'shortDescription' => 'Bepado Local Product short description EN',
                'longDescription' => 'Bepado Local Product long description EN',
                'url' => $this->getProductBaseUrl() . '35&shId=2',
            ),
            176 => array(
                'title' => 'Bepado Local Product NL',
                'shortDescription' => 'Bepado Local Product short description NL',
                'longDescription' => 'Bepado Local Product long description NL',
                'url' => $this->getProductBaseUrl() . '35&shId=3',
            ),
        );
        $this->configComponent->expects($this->any())->method('getConfig')->with('exportLanguages')->willReturn(array(2, 176));
        $this->translationGateway->expects($this->any())->method('getTranslations')->with(108, array(2, 176))->willReturn($translations);

        $this->modelManager->expects($this->at(0))->method('getRepository')->with('Shopware\Models\Shop\Locale')->willReturn($this->localeRepository);
        $this->modelManager->expects($this->at(1))->method('getRepository')->with('Shopware\Models\Shop\Shop')->willReturn($this->shopRepository);

        $enLocale = new Locale();
        $enLocale->setLocale('en_GB');
        $this->localeRepository->expects($this->at(0))->method('find')->with(2)->willReturn($enLocale);
        $nlLocale = new Locale();
        $nlLocale->setLocale('nl_NL');
        $this->localeRepository->expects($this->at(1))->method('find')->with(176)->willReturn($nlLocale);

        $shop = $this->getMockBuilder('\\Shopware\\Models\\Shop\\Shop')
            ->disableOriginalConstructor()
            ->getMock();
        $shop->expects($this->at(0))->method('getId')->willReturn(2);
        $shop->expects($this->at(1))->method('getId')->willReturn(3);
        $this->shopRepository->expects($this->any())->method('findOneBy')->willReturn($shop);

        $expected = array(
            'en' => new Translation(
                array(
                    'title' => 'Bepado Local Product EN',
                    'shortDescription' => 'Bepado Local Product short description EN',
                    'longDescription' => 'Bepado Local Product long description EN',
                    'url' => $this->getProductBaseUrl() . '35&shId=2',
                )
            ),
            'nl' => new Translation(
                array(
                    'title' => 'Bepado Local Product NL',
                    'shortDescription' => 'Bepado Local Product short description NL',
                    'longDescription' => 'Bepado Local Product long description NL',
                    'url' => $this->getProductBaseUrl() . '35&shId=3',
                )
            )
        );

        $this->assertEquals($expected, $this->productTranslator->translate(108, 35));
    }

    public function testTranslateWhenLocaleNotFound()
    {
        $translations = array(
            2 => array(
                'title' => 'Bepado Local Product EN',
                'shortDescription' => 'Bepado Local Product short description EN',
                'longDescription' => 'Bepado Local Product long description EN',
                'url' => $this->getProductBaseUrl() . '35&shId=2',
            ),
            176 => array(
                'title' => 'Bepado Local Product NL',
                'shortDescription' => 'Bepado Local Product short description NL',
                'longDescription' => 'Bepado Local Product long description NL',
                'url' => $this->getProductBaseUrl() . '35&shId=3',
            ),
        );
        $this->configComponent->expects($this->any())->method('getConfig')->with('exportLanguages')->willReturn(array(2, 176));
        $this->translationGateway->expects($this->any())->method('getTranslations')->with(108, array(2, 176))->willReturn($translations);
        $this->modelManager->expects($this->at(0))->method('getRepository')->with('Shopware\Models\Shop\Locale')->willReturn($this->localeRepository);
        $this->localeRepository->expects($this->any())->method('find')->willReturn(null);

        $this->assertEmpty($this->productTranslator->translate(108, 35));
    }

    public function testTranslateWhenShopNotFound()
    {
        $translations = array(
            2 => array(
                'title' => 'Bepado Local Product EN',
                'shortDescription' => 'Bepado Local Product short description EN',
                'longDescription' => 'Bepado Local Product long description EN',
                'url' => $this->getProductBaseUrl() . '35&shId=2',
            ),
            176 => array(
                'title' => 'Bepado Local Product NL',
                'shortDescription' => 'Bepado Local Product short description NL',
                'longDescription' => 'Bepado Local Product long description NL',
                'url' => $this->getProductBaseUrl() . '35&shId=3',
            ),
        );
        $this->configComponent->expects($this->any())->method('getConfig')->with('exportLanguages')->willReturn(array(2, 176));
        $this->translationGateway->expects($this->any())->method('getTranslations')->with(108, array(2, 176))->willReturn($translations);
        $this->modelManager->expects($this->at(0))->method('getRepository')->with('Shopware\Models\Shop\Locale')->willReturn($this->localeRepository);
        $this->modelManager->expects($this->at(1))->method('getRepository')->with('Shopware\Models\Shop\Shop')->willReturn($this->shopRepository);

        $enLocale = new Locale();
        $enLocale->setLocale('en_GB');
        $this->localeRepository->expects($this->at(0))->method('find')->with(2)->willReturn($enLocale);
        $nlLocale = new Locale();
        $nlLocale->setLocale('nl_NL');
        $this->localeRepository->expects($this->at(1))->method('find')->with(176)->willReturn($nlLocale);

        $this->shopRepository->expects($this->any())->method('findOneBy')->willReturn(null);

        $this->assertEmpty($this->productTranslator->translate(108, 35));
    }

    public function testTranslateConfiguratorGroup()
    {
        $groupTranslations = array(
            2 => 'color',
            3 => 'kleur',
        );

        $this->configComponent->expects($this->any())->method('getConfig')->with('exportLanguages')->willReturn(array(2, 3));
        $this->translationGateway->expects($this->any())->method('getConfiguratorGroupTranslations')->with(15, array(2, 3))->willReturn($groupTranslations);

        $this->modelManager->expects($this->at(0))->method('getRepository')->with('Shopware\Models\Shop\Shop')->willReturn($this->shopRepository);

        $enLocale = new Locale();
        $enLocale->setLocale('en_GB');


        $nlLocale = new Locale();
        $nlLocale->setLocale('nl_NL');

        $enShop = $this->getMockBuilder('\\Shopware\\Models\\Shop\\Shop')
            ->disableOriginalConstructor()
            ->getMock();
        $enShop->expects($this->any())->method('getLocale')->willReturn($enLocale);

        $nlShop = $this->getMockBuilder('\\Shopware\\Models\\Shop\\Shop')
            ->disableOriginalConstructor()
            ->getMock();
        $nlShop->expects($this->any())->method('getLocale')->willReturn($nlLocale);

        $this->shopRepository->expects($this->at(0))->method('find')->with(2)->willReturn($enShop);
        $this->shopRepository->expects($this->at(1))->method('find')->with(3)->willReturn($nlShop);

        $expected = array(
            'en' => new Translation(
                array(
                    'title' => 'Bepado Local Product EN',
                    'shortDescription' => 'Bepado Local Product short description EN',
                    'longDescription' => 'Bepado Local Product long description EN',
                    'url' => $this->getProductBaseUrl() . '35&shId=2',
                    'variantLabels' => array(
                        'farbe' => 'color'
                    ),
                )
            ),
            'nl' => new Translation(
                array(
                    'title' => 'Bepado Local Product NL',
                    'shortDescription' => 'Bepado Local Product short description NL',
                    'longDescription' => 'Bepado Local Product long description NL',
                    'url' => $this->getProductBaseUrl() . '35&shId=3',
                    'variantLabels' => array(
                        'farbe' => 'kleur'
                    ),
                )
            )
        );

        $translations = array(
            'en' => new Translation(
                array(
                    'title' => 'Bepado Local Product EN',
                    'shortDescription' => 'Bepado Local Product short description EN',
                    'longDescription' => 'Bepado Local Product long description EN',
                    'url' => $this->getProductBaseUrl() . '35&shId=2',
                )
            ),
            'nl' => new Translation(
                array(
                    'title' => 'Bepado Local Product NL',
                    'shortDescription' => 'Bepado Local Product short description NL',
                    'longDescription' => 'Bepado Local Product long description NL',
                    'url' => $this->getProductBaseUrl() . '35&shId=3',
                )
            )
        );

        $this->assertEquals($expected, $this->productTranslator->translateConfiguratorGroup(15, 'farbe', $translations));
    }

    public function testTranslateConfiguratorGroupWithDefaultShopOnly()
    {
        // translate should be the same after group translation
        // when exportLanguages contains only default shop language
        $translations = array(
            'en' => new Translation(
                array(
                    'title' => 'Bepado Local Product EN',
                    'shortDescription' => 'Bepado Local Product short description EN',
                    'longDescription' => 'Bepado Local Product long description EN',
                    'url' => $this->getProductBaseUrl() . '35&shId=2',
                )
            ),
            'nl' => new Translation(
                array(
                    'title' => 'Bepado Local Product NL',
                    'shortDescription' => 'Bepado Local Product short description NL',
                    'longDescription' => 'Bepado Local Product long description NL',
                    'url' => $this->getProductBaseUrl() . '35&shId=3',
                )
            )
        );

        $this->configComponent->expects($this->any())->method('getConfig')->with('exportLanguages')->willReturn(array(1));
        $this->assertEquals($translations, $this->productTranslator->translateConfiguratorGroup(15, 'farbe', $translations));
    }

    public function testTranslateConfiguratorGroupWhenLocaleNotFound()
    {
        $groupTranslations = array(
            2 => 'color',
            3 => 'kleur',
        );

        $this->configComponent->expects($this->any())->method('getConfig')->with('exportLanguages')->willReturn(array(2, 3));
        $this->translationGateway->expects($this->any())->method('getConfiguratorGroupTranslations')->with(15, array(2, 3))->willReturn($groupTranslations);

        $this->modelManager->expects($this->at(0))->method('getRepository')->with('Shopware\Models\Shop\Shop')->willReturn($this->shopRepository);

        $nlLocale = new Locale();
        $nlLocale->setLocale('nl_NL');

        $enShop = $this->getMockBuilder('\\Shopware\\Models\\Shop\\Shop')
            ->disableOriginalConstructor()
            ->getMock();
        $enShop->expects($this->any())->method('getLocale')->willReturn(null);

        $nlShop = $this->getMockBuilder('\\Shopware\\Models\\Shop\\Shop')
            ->disableOriginalConstructor()
            ->getMock();
        $nlShop->expects($this->any())->method('getLocale')->willReturn($nlLocale);

        $this->shopRepository->expects($this->at(0))->method('find')->with(2)->willReturn($enShop);
        $this->shopRepository->expects($this->at(1))->method('find')->with(3)->willReturn($nlShop);

        $expected = array(
            'en' => new Translation(
                array(
                    'title' => 'Bepado Local Product EN',
                    'shortDescription' => 'Bepado Local Product short description EN',
                    'longDescription' => 'Bepado Local Product long description EN',
                    'url' => $this->getProductBaseUrl() . '35&shId=2',
                )
            ),
            'nl' => new Translation(
                array(
                    'title' => 'Bepado Local Product NL',
                    'shortDescription' => 'Bepado Local Product short description NL',
                    'longDescription' => 'Bepado Local Product long description NL',
                    'url' => $this->getProductBaseUrl() . '35&shId=3',
                    'variantLabels' => array(
                        'farbe' => 'kleur'
                    ),
                )
            )
        );

        $translations = array(
            'en' => new Translation(
                array(
                    'title' => 'Bepado Local Product EN',
                    'shortDescription' => 'Bepado Local Product short description EN',
                    'longDescription' => 'Bepado Local Product long description EN',
                    'url' => $this->getProductBaseUrl() . '35&shId=2',
                )
            ),
            'nl' => new Translation(
                array(
                    'title' => 'Bepado Local Product NL',
                    'shortDescription' => 'Bepado Local Product short description NL',
                    'longDescription' => 'Bepado Local Product long description NL',
                    'url' => $this->getProductBaseUrl() . '35&shId=3',
                )
            )
        );

        $this->assertEquals($expected, $this->productTranslator->translateConfiguratorGroup(15, 'farbe', $translations));
    }

    public function testTranslateConfiguratorGroupWithoutStruct()
    {
        $groupTranslations = array(
            2 => 'color',
            3 => 'kleur',
        );

        $this->configComponent->expects($this->any())->method('getConfig')->with('exportLanguages')->willReturn(array(2, 3));
        $this->translationGateway->expects($this->any())->method('getConfiguratorGroupTranslations')->with(15, array(2, 3))->willReturn($groupTranslations);

        $this->modelManager->expects($this->at(0))->method('getRepository')->with('Shopware\Models\Shop\Shop')->willReturn($this->shopRepository);

        $nlLocale = new Locale();
        $nlLocale->setLocale('nl_NL');

        $enShop = $this->getMockBuilder('\\Shopware\\Models\\Shop\\Shop')
            ->disableOriginalConstructor()
            ->getMock();
        $enShop->expects($this->any())->method('getLocale')->willReturn(null);

        $nlShop = $this->getMockBuilder('\\Shopware\\Models\\Shop\\Shop')
            ->disableOriginalConstructor()
            ->getMock();
        $nlShop->expects($this->any())->method('getLocale')->willReturn($nlLocale);

        $this->shopRepository->expects($this->at(0))->method('find')->with(2)->willReturn($enShop);
        $this->shopRepository->expects($this->at(1))->method('find')->with(3)->willReturn($nlShop);

        $expected = array(
            'en' => array(),
            'nl' => new Translation(
                array(
                    'title' => 'Bepado Local Product NL',
                    'shortDescription' => 'Bepado Local Product short description NL',
                    'longDescription' => 'Bepado Local Product long description NL',
                    'url' => $this->getProductBaseUrl() . '35&shId=3',
                    'variantLabels' => array(
                        'farbe' => 'kleur'
                    ),
                )
            )
        );

        $translations = array(
            'en' => array(),
            'nl' => new Translation(
                array(
                    'title' => 'Bepado Local Product NL',
                    'shortDescription' => 'Bepado Local Product short description NL',
                    'longDescription' => 'Bepado Local Product long description NL',
                    'url' => $this->getProductBaseUrl() . '35&shId=3',
                )
            )
        );

        $this->assertEquals($expected, $this->productTranslator->translateConfiguratorGroup(15, 'farbe', $translations));
    }

    public function testTranslateConfiguratorGroupWithInvalidLocaleCode()
    {
        $groupTranslations = array(
            2 => 'color',
            3 => 'kleur',
        );

        $this->configComponent->expects($this->any())->method('getConfig')->with('exportLanguages')->willReturn(array(2, 3));
        $this->translationGateway->expects($this->any())->method('getConfiguratorGroupTranslations')->with(15, array(2, 3))->willReturn($groupTranslations);

        $this->modelManager->expects($this->at(0))->method('getRepository')->with('Shopware\Models\Shop\Shop')->willReturn($this->shopRepository);

        $enLocale = new Locale();
        $enLocale->setLocale('en-EN');

        $nlLocale = new Locale();
        $nlLocale->setLocale('nl_NL');

        $enShop = $this->getMockBuilder('\\Shopware\\Models\\Shop\\Shop')
            ->disableOriginalConstructor()
            ->getMock();
        $enShop->expects($this->any())->method('getLocale')->willReturn($enLocale);

        $nlShop = $this->getMockBuilder('\\Shopware\\Models\\Shop\\Shop')
            ->disableOriginalConstructor()
            ->getMock();
        $nlShop->expects($this->any())->method('getLocale')->willReturn($nlLocale);

        $this->shopRepository->expects($this->at(0))->method('find')->with(2)->willReturn($enShop);
        $this->shopRepository->expects($this->at(1))->method('find')->with(3)->willReturn($nlShop);

        $expected = array(
            'en' => new Translation(
                array(
                    'title' => 'Bepado Local Product EN',
                    'shortDescription' => 'Bepado Local Product short description EN',
                    'longDescription' => 'Bepado Local Product long description EN',
                    'url' => $this->getProductBaseUrl() . '35&shId=2',
                )
            ),
            'nl' => new Translation(
                array(
                    'title' => 'Bepado Local Product NL',
                    'shortDescription' => 'Bepado Local Product short description NL',
                    'longDescription' => 'Bepado Local Product long description NL',
                    'url' => $this->getProductBaseUrl() . '35&shId=3',
                    'variantLabels' => array(
                        'farbe' => 'kleur'
                    ),
                )
            )
        );

        $translations = array(
            'en' => new Translation(
                array(
                    'title' => 'Bepado Local Product EN',
                    'shortDescription' => 'Bepado Local Product short description EN',
                    'longDescription' => 'Bepado Local Product long description EN',
                    'url' => $this->getProductBaseUrl() . '35&shId=2',
                )
            ),
            'nl' => new Translation(
                array(
                    'title' => 'Bepado Local Product NL',
                    'shortDescription' => 'Bepado Local Product short description NL',
                    'longDescription' => 'Bepado Local Product long description NL',
                    'url' => $this->getProductBaseUrl() . '35&shId=3',
                )
            )
        );

        $this->assertEquals($expected, $this->productTranslator->translateConfiguratorGroup(15, 'farbe', $translations));
    }

    public function testTranslateConfiguratorOption()
    {
        $optionTranslations = array(
            2 => 'red',
            3 => 'rood',
        );

        $this->configComponent->expects($this->any())->method('getConfig')->with('exportLanguages')->willReturn(array(2, 3));
        $this->translationGateway->expects($this->any())->method('getConfiguratorOptionTranslations')->with(15, array(2, 3))->willReturn($optionTranslations);

        $this->modelManager->expects($this->at(0))->method('getRepository')->with('Shopware\Models\Shop\Shop')->willReturn($this->shopRepository);

        $enLocale = new Locale();
        $enLocale->setLocale('en_GB');

        $nlLocale = new Locale();
        $nlLocale->setLocale('nl_NL');

        $enShop = $this->getMockBuilder('\\Shopware\\Models\\Shop\\Shop')
            ->disableOriginalConstructor()
            ->getMock();
        $enShop->expects($this->any())->method('getLocale')->willReturn($enLocale);

        $nlShop = $this->getMockBuilder('\\Shopware\\Models\\Shop\\Shop')
            ->disableOriginalConstructor()
            ->getMock();
        $nlShop->expects($this->any())->method('getLocale')->willReturn($nlLocale);

        $this->shopRepository->expects($this->at(0))->method('find')->with(2)->willReturn($enShop);
        $this->shopRepository->expects($this->at(1))->method('find')->with(3)->willReturn($nlShop);

        $expected = array(
            'en' => new Translation(
                array(
                    'title' => 'Bepado Local Product EN',
                    'shortDescription' => 'Bepado Local Product short description EN',
                    'longDescription' => 'Bepado Local Product long description EN',
                    'url' => $this->getProductBaseUrl() . '35&shId=2',
                    'variantValues' => array(
                        'rot' => 'red'
                    ),
                )
            ),
            'nl' => new Translation(
                array(
                    'title' => 'Bepado Local Product NL',
                    'shortDescription' => 'Bepado Local Product short description NL',
                    'longDescription' => 'Bepado Local Product long description NL',
                    'url' => $this->getProductBaseUrl() . '35&shId=3',
                    'variantValues' => array(
                        'rot' => 'rood'
                    ),
                )
            )
        );

        $translations = array(
            'en' => new Translation(
                array(
                    'title' => 'Bepado Local Product EN',
                    'shortDescription' => 'Bepado Local Product short description EN',
                    'longDescription' => 'Bepado Local Product long description EN',
                    'url' => $this->getProductBaseUrl() . '35&shId=2',
                )
            ),
            'nl' => new Translation(
                array(
                    'title' => 'Bepado Local Product NL',
                    'shortDescription' => 'Bepado Local Product short description NL',
                    'longDescription' => 'Bepado Local Product long description NL',
                    'url' => $this->getProductBaseUrl() . '35&shId=3',
                )
            )
        );

        $this->assertEquals($expected, $this->productTranslator->translateConfiguratorOption(15, 'rot', $translations));
    }

    public function testTranslateConfiguratorOptionWithDefaultShopOnly()
    {
        // translate should be the same after group translation
        // when exportLanguages contains only default shop language
        $translations = array(
            'en' => new Translation(
                array(
                    'title' => 'Bepado Local Product EN',
                    'shortDescription' => 'Bepado Local Product short description EN',
                    'longDescription' => 'Bepado Local Product long description EN',
                    'url' => $this->getProductBaseUrl() . '35&shId=2',
                )
            ),
            'nl' => new Translation(
                array(
                    'title' => 'Bepado Local Product NL',
                    'shortDescription' => 'Bepado Local Product short description NL',
                    'longDescription' => 'Bepado Local Product long description NL',
                    'url' => $this->getProductBaseUrl() . '35&shId=3',
                )
            )
        );

        $this->configComponent->expects($this->any())->method('getConfig')->with('exportLanguages')->willReturn(array(1));
        $this->assertEquals($translations, $this->productTranslator->translateConfiguratorGroup(15, 'red', $translations));
    }

    public function testTranslateConfiguratorOptionWhenLocaleNotFound()
    {
        $optionTranslations = array(
            2 => 'red',
            3 => 'rood',
        );

        $this->configComponent->expects($this->any())->method('getConfig')->with('exportLanguages')->willReturn(array(2, 3));
        $this->translationGateway->expects($this->any())->method('getConfiguratorOptionTranslations')->with(15, array(2, 3))->willReturn($optionTranslations);

        $this->modelManager->expects($this->at(0))->method('getRepository')->with('Shopware\Models\Shop\Shop')->willReturn($this->shopRepository);

        $nlLocale = new Locale();
        $nlLocale->setLocale('nl_NL');

        $enShop = $this->getMockBuilder('\\Shopware\\Models\\Shop\\Shop')
            ->disableOriginalConstructor()
            ->getMock();
        $enShop->expects($this->any())->method('getLocale')->willReturn(null);

        $nlShop = $this->getMockBuilder('\\Shopware\\Models\\Shop\\Shop')
            ->disableOriginalConstructor()
            ->getMock();
        $nlShop->expects($this->any())->method('getLocale')->willReturn($nlLocale);

        $this->shopRepository->expects($this->at(0))->method('find')->with(2)->willReturn($enShop);
        $this->shopRepository->expects($this->at(1))->method('find')->with(3)->willReturn($nlShop);

        $expected = array(
            'en' => new Translation(
                array(
                    'title' => 'Bepado Local Product EN',
                    'shortDescription' => 'Bepado Local Product short description EN',
                    'longDescription' => 'Bepado Local Product long description EN',
                    'url' => $this->getProductBaseUrl() . '35&shId=2',
                )
            ),
            'nl' => new Translation(
                array(
                    'title' => 'Bepado Local Product NL',
                    'shortDescription' => 'Bepado Local Product short description NL',
                    'longDescription' => 'Bepado Local Product long description NL',
                    'url' => $this->getProductBaseUrl() . '35&shId=3',
                    'variantValues' => array(
                        'rot' => 'rood'
                    ),
                )
            )
        );

        $translations = array(
            'en' => new Translation(
                array(
                    'title' => 'Bepado Local Product EN',
                    'shortDescription' => 'Bepado Local Product short description EN',
                    'longDescription' => 'Bepado Local Product long description EN',
                    'url' => $this->getProductBaseUrl() . '35&shId=2',
                )
            ),
            'nl' => new Translation(
                array(
                    'title' => 'Bepado Local Product NL',
                    'shortDescription' => 'Bepado Local Product short description NL',
                    'longDescription' => 'Bepado Local Product long description NL',
                    'url' => $this->getProductBaseUrl() . '35&shId=3',
                )
            )
        );

        $this->assertEquals($expected, $this->productTranslator->translateConfiguratorOption(15, 'rot', $translations));
    }

    public function testTranslateConfiguratorOptionWithoutStruct()
    {
        $optionTranslations = array(
            2 => 'red',
            3 => 'rood',
        );

        $this->configComponent->expects($this->any())->method('getConfig')->with('exportLanguages')->willReturn(array(2, 3));
        $this->translationGateway->expects($this->any())->method('getConfiguratorOptionTranslations')->with(15, array(2, 3))->willReturn($optionTranslations);

        $this->modelManager->expects($this->at(0))->method('getRepository')->with('Shopware\Models\Shop\Shop')->willReturn($this->shopRepository);

        $nlLocale = new Locale();
        $nlLocale->setLocale('nl_NL');

        $enShop = $this->getMockBuilder('\\Shopware\\Models\\Shop\\Shop')
            ->disableOriginalConstructor()
            ->getMock();
        $enShop->expects($this->any())->method('getLocale')->willReturn(null);

        $nlShop = $this->getMockBuilder('\\Shopware\\Models\\Shop\\Shop')
            ->disableOriginalConstructor()
            ->getMock();
        $nlShop->expects($this->any())->method('getLocale')->willReturn($nlLocale);

        $this->shopRepository->expects($this->at(0))->method('find')->with(2)->willReturn($enShop);
        $this->shopRepository->expects($this->at(1))->method('find')->with(3)->willReturn($nlShop);

        $expected = array(
            'en' => array(),
            'nl' => new Translation(
                array(
                    'title' => 'Bepado Local Product NL',
                    'shortDescription' => 'Bepado Local Product short description NL',
                    'longDescription' => 'Bepado Local Product long description NL',
                    'url' => $this->getProductBaseUrl() . '35&shId=3',
                    'variantValues' => array(
                        'rot' => 'rood'
                    ),
                )
            )
        );

        $translations = array(
            'en' => array(),
            'nl' => new Translation(
                array(
                    'title' => 'Bepado Local Product NL',
                    'shortDescription' => 'Bepado Local Product short description NL',
                    'longDescription' => 'Bepado Local Product long description NL',
                    'url' => $this->getProductBaseUrl() . '35&shId=3',
                )
            )
        );

        $this->assertEquals($expected, $this->productTranslator->translateConfiguratorOption(15, 'rot', $translations));
    }

    public function testValidate()
    {
        $translation = new Translation(array(
            'title' => 'Bepado Local Product EN',
            'shortDescription' => 'Bepado Local Product short description EN',
            'longDescription' => 'Bepado Local Product long description EN',
            'url' => $this->getProductBaseUrl() . '35&shId=2',
            'variantLabels' => array(
                'größe' => 'size',
                'farbe' => 'color',
            ),
            'variantValues' => array(
                '52' => 'XL',
                'blau' => 'blue',
            ),
        ));

        $this->assertTrue($this->productTranslator->validate($translation, 2));
    }

    /**
     * @expectedException \Exception
     */
    public function testValidateMissingTitle()
    {
        $translation = new Translation(array(
            'shortDescription' => 'Bepado Local Product short description EN',
            'longDescription' => 'Bepado Local Product long description EN',
            'url' => $this->getProductBaseUrl() . '35&shId=2',
            'variantLabels' => array(
                'größe' => 'size',
                'farbe' => 'color',
            ),
            'variantValues' => array(
                '52' => 'XL',
                'blau' => 'blue',
            ),
        ));

        $this->assertTrue($this->productTranslator->validate($translation, 2));
    }

    /**
     * @expectedException \Exception
     */
    public function testValidateWrongVariantLabels()
    {
        $translation = new Translation(array(
            'title' => 'Bepado Local Product EN',
            'shortDescription' => 'Bepado Local Product short description EN',
            'longDescription' => 'Bepado Local Product long description EN',
            'url' => $this->getProductBaseUrl() . '35&shId=2',
            'variantLabels' => array(
                'farbe' => 'color',
            ),
            'variantValues' => array(
                '52' => 'XL',
                'blau' => 'blue',
            ),
        ));

        $this->assertTrue($this->productTranslator->validate($translation, 2));
    }

    /**
     * @expectedException \Exception
     */
    public function testValidateWrongVariantValues()
    {
        $translation = new Translation(array(
            'title' => 'Bepado Local Product EN',
            'shortDescription' => 'Bepado Local Product short description EN',
            'longDescription' => 'Bepado Local Product long description EN',
            'url' => $this->getProductBaseUrl() . '35&shId=2',
            'variantLabels' => array(
                'größe' => 'size',
                'farbe' => 'color',
            ),
            'variantValues' => array(
                'blau' => 'blue',
            ),
        ));

        $this->assertTrue($this->productTranslator->validate($translation, 2));
    }

    public function getProductBaseUrl()
    {
        if (!Shopware()->Front()->Router()) {
            return null;
        }

        return Shopware()->Front()->Router()->assemble(array(
            'module' => 'frontend',
            'controller' => 'bepado_product_gateway',
            'action' => 'product',
            'id' => '',
            'fullPath' => true
        ));
    }
}
 
<?php

class PdoProductTranslationsGatewayTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \Shopware\Bepado\Components\Gateway\ProductTranslationsGateway\PdoProductTranslationsGateway
     */
    private $gateway;

    private $mockDbStatement;

    private $mockDbAdapter;

    public function setUp()
    {
        $this->mockDbStatement = $this->getMockBuilder('Zend_Db_Statement_Pdo')
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockDbAdapter = $this->getMockBuilder('Enlight_Components_Db_Adapter_Pdo_Mysql')
            ->disableOriginalConstructor()
            ->getMock();

        $this->gateway = new \Shopware\Bepado\Components\Gateway\ProductTranslationsGateway\PdoProductTranslationsGateway($this->mockDbAdapter);
    }

    public function testGetSingleTranslation()
    {
        $this->mockDbStatement->expects($this->any())->method('fetchColumn')->willReturn('a:3:{s:10:"txtArtikel";s:20:"Bepado local article";s:19:"txtshortdescription";s:38:"Bepado local article short description";s:19:"txtlangbeschreibung";s:37:"Bepado local article long description";}');

        $sql = 'SELECT objectdata
                FROM s_core_translations
                WHERE objecttype = ? AND objectkey = ? AND objectlanguage = ?
        ';
        $queryParams = array('article', 105, 3);
        $this->mockDbAdapter->expects($this->any())->method('executeQuery')->with($sql, $queryParams)->will($this->returnValue($this->mockDbStatement));

        $expected = array(
            'title' => 'Bepado local article',
            'shortDescription' => 'Bepado local article short description',
            'longDescription' => 'Bepado local article long description',
        );
        $this->assertEquals($expected, $this->gateway->getSingleTranslation(105, 3));
    }

    public function testGetSingleTranslationTitleOnly()
    {
        $this->mockDbStatement->expects($this->any())->method('fetchColumn')->willReturn('a:1:{s:10:"txtArtikel";s:20:"Bepado local article";}');

        $sql = 'SELECT objectdata
                FROM s_core_translations
                WHERE objecttype = ? AND objectkey = ? AND objectlanguage = ?
        ';
        $queryParams = array('article', 105, 3);
        $this->mockDbAdapter->expects($this->any())->method('executeQuery')->with($sql, $queryParams)->will($this->returnValue($this->mockDbStatement));

        $expected = array(
            'title' => 'Bepado local article',
            'shortDescription' => '',
            'longDescription' => '',
        );
        $this->assertEquals($expected, $this->gateway->getSingleTranslation(105, 3));
    }

    public function testNotFoundTranslation()
    {
        $this->mockDbStatement->expects($this->any())->method('fetchColumn')->willReturn(false);

        $sql = 'SELECT objectdata
                FROM s_core_translations
                WHERE objecttype = ? AND objectkey = ? AND objectlanguage = ?
        ';
        $queryParams = array('article', 111, 3);
        $this->mockDbAdapter->expects($this->any())->method('executeQuery')->with($sql, $queryParams)->will($this->returnValue($this->mockDbStatement));

        $this->assertNull($this->gateway->getSingleTranslation(111, 3));
    }

    public function testGetTranslations()
    {
        $this->mockDbStatement->expects($this->any())
            ->method('fetchAll')
            ->willReturn(array(
                0 => array(
                    'objectdata' => 'a:3:{s:10:"txtArtikel";s:20:"Bepado local article";s:19:"txtshortdescription";s:38:"Bepado local article short description";s:19:"txtlangbeschreibung";s:37:"Bepado local article long description";}',
                    'objectlanguage' => 2,
                ),
                1 => array(
                    'objectdata' => 'a:3:{s:10:"txtArtikel";s:23:"Bepado local article EN";s:19:"txtshortdescription";s:41:"Bepado local article short description EN";s:19:"txtlangbeschreibung";s:40:"Bepado local article long description EN";}',
                    'objectlanguage' => 3,
                ),
            ));

        $sql = "SELECT objectdata, objectlanguage
                FROM s_core_translations
                WHERE objecttype = ? AND objectkey = ? AND objectlanguage IN (2,3)
        ";

        $queryParams = array('article', 103);
        $this->mockDbAdapter->expects($this->any())->method('executeQuery')->with($sql, $queryParams)->will($this->returnValue($this->mockDbStatement));

        $expected = array(
            2 => array(
                'title' => 'Bepado local article',
                'shortDescription' => 'Bepado local article short description',
                'longDescription' => 'Bepado local article long description',
            ),
            3 => array(
                'title' => 'Bepado local article EN',
                'shortDescription' => 'Bepado local article short description EN',
                'longDescription' => 'Bepado local article long description EN',
            ),
        );

        $this->assertEquals($expected, $this->gateway->getTranslations(103, array(2,3)));
    }

    public function testGetTranslationsOnlyTitle()
    {
        $this->mockDbStatement->expects($this->any())
            ->method('fetchAll')
            ->willReturn(array(
                0 => array(
                    'objectdata' => 'a:1:{s:10:"txtArtikel";s:20:"Bepado local article";}',
                    'objectlanguage' => 2,
                ),
                1 => array(
                    'objectdata' => 'a:1:{s:10:"txtArtikel";s:23:"Bepado local article EN";}',
                    'objectlanguage' => 3,
                ),
            ));

        $sql = "SELECT objectdata, objectlanguage
                FROM s_core_translations
                WHERE objecttype = ? AND objectkey = ? AND objectlanguage IN (2,3)
        ";

        $queryParams = array('article', 103);
        $this->mockDbAdapter->expects($this->any())->method('executeQuery')->with($sql, $queryParams)->will($this->returnValue($this->mockDbStatement));

        $expected = array(
            2 => array(
                'title' => 'Bepado local article',
                'shortDescription' => '',
                'longDescription' => '',
            ),
            3 => array(
                'title' => 'Bepado local article EN',
                'shortDescription' => '',
                'longDescription' => '',
            ),
        );

        $this->assertEquals($expected, $this->gateway->getTranslations(103, array(2,3)));
    }

    public function testGetInvalidArticleTranslations()
    {
        $this->mockDbStatement->expects($this->any())
            ->method('fetchAll')
            ->willReturn(array());

        $sql = "SELECT objectdata, objectlanguage
                FROM s_core_translations
                WHERE objecttype = ? AND objectkey = ? AND objectlanguage IN (2,3)
        ";

        $queryParams = array('article', 111);
        $this->mockDbAdapter->expects($this->any())->method('executeQuery')->with($sql, $queryParams)->will($this->returnValue($this->mockDbStatement));

        $this->assertEquals(array(), $this->gateway->getTranslations(111, array(2,3)));
    }

    public function testGetConfiguratorOptionTranslationsWithoutShopIds()
    {
        $this->assertEmpty($this->gateway->getConfiguratorOptionTranslations(15, array()));
    }

    public function testGetConfiguratorOptionTranslationsWithoutSerializedData()
    {
        $this->mockDbStatement->expects($this->any())
            ->method('fetchAll')
            ->willReturn(array(
                0 => array(
                    'objectdata' => 'a:0:{}',
                    'objectlanguage' => 2,
                ),
                1 => array(
                    'objectdata' => 'a:0:{}',
                    'objectlanguage' => 3,
                ),
            ));

        $sql = "SELECT objectdata, objectlanguage
                FROM s_core_translations
                WHERE objecttype = ? AND objectkey = ? AND objectlanguage IN (2,3)
        ";

        $queryParams = array('configuratoroption', 15);
        $this->mockDbAdapter->expects($this->any())->method('executeQuery')->with($sql, $queryParams)->will($this->returnValue($this->mockDbStatement));

        $expected = array();

        $this->assertEquals($expected, $this->gateway->getConfiguratorOptionTranslations(15, array(2,3)));
    }

    public function testGetConfiguratorOptionTranslations()
    {
        $this->mockDbStatement->expects($this->any())
            ->method('fetchAll')
            ->willReturn(array(
                0 => array(
                    'objectdata' => 'a:1:{s:4:"name";s:3:"red";}',
                    'objectlanguage' => 2,
                ),
                1 => array(
                    'objectdata' => 'a:1:{s:4:"name";s:3:"rot";}',
                    'objectlanguage' => 3,
                ),
            ));

        $sql = "SELECT objectdata, objectlanguage
                FROM s_core_translations
                WHERE objecttype = ? AND objectkey = ? AND objectlanguage IN (2,3)
        ";

        $queryParams = array('configuratoroption', 15);
        $this->mockDbAdapter->expects($this->any())->method('executeQuery')->with($sql, $queryParams)->will($this->returnValue($this->mockDbStatement));

        $expected = array(
            2 => 'red',
            3 => 'rot',
        );

        $this->assertEquals($expected, $this->gateway->getConfiguratorOptionTranslations(15, array(2,3)));
    }

    public function testGetMissingSingleConfiguratorOptionTranslation()
    {
        $this->mockDbStatement->expects($this->any())
            ->method('fetchColumn')
            ->willReturn(false);

        $sql = "SELECT objectdata
                FROM s_core_translations
                WHERE objecttype = ? AND objectkey = ? AND objectlanguage = ?
        ";

        $queryParams = array('configuratoroption', 15, 2);
        $this->mockDbAdapter->expects($this->any())->method('executeQuery')->with($sql, $queryParams)->will($this->returnValue($this->mockDbStatement));

        $this->assertNull($this->gateway->getConfiguratorOptionTranslation(15, 2));
    }

    public function testGetSingleConfiguratorOptionTranslation()
    {
        $this->mockDbStatement->expects($this->any())
            ->method('fetchColumn')
            ->willReturn('a:1:{s:4:"name";s:3:"rot";}');

        $sql = "SELECT objectdata
                FROM s_core_translations
                WHERE objecttype = ? AND objectkey = ? AND objectlanguage = ?
        ";

        $queryParams = array('configuratoroption', 15, 2);
        $this->mockDbAdapter->expects($this->any())->method('executeQuery')->with($sql, $queryParams)->will($this->returnValue($this->mockDbStatement));


        $this->assertEquals('rot', $this->gateway->getConfiguratorOptionTranslation(15, 2));
    }

    public function testGetConfiguratorGroupTranslationsWithoutShopIds()
    {
        $this->assertEmpty($this->gateway->getConfiguratorGroupTranslations(15, array()));
    }

    public function testGetConfiguratorGroupTranslationsWithoutSerializedData()
    {
        $this->mockDbStatement->expects($this->any())
            ->method('fetchAll')
            ->willReturn(array(
                0 => array(
                    'objectdata' => 'a:0:{}',
                    'objectlanguage' => 2,
                ),
                1 => array(
                    'objectdata' => 'a:0:{}',
                    'objectlanguage' => 3,
                ),
            ));

        $sql = "SELECT objectdata, objectlanguage
                FROM s_core_translations
                WHERE objecttype = ? AND objectkey = ? AND objectlanguage IN (2,3)
        ";

        $queryParams = array('configuratorgroup', 15);
        $this->mockDbAdapter->expects($this->any())->method('executeQuery')->with($sql, $queryParams)->will($this->returnValue($this->mockDbStatement));

        $expected = array();

        $this->assertEquals($expected, $this->gateway->getConfiguratorGroupTranslations(15, array(2,3)));
    }

    public function testGetConfiguratorGroupTranslations()
    {
        $this->mockDbStatement->expects($this->any())
            ->method('fetchAll')
            ->willReturn(array(
                0 => array(
                    'objectdata' => 'a:1:{s:4:"name";s:5:"color";}',
                    'objectlanguage' => 2,
                ),
                1 => array(
                    'objectdata' => 'a:1:{s:4:"name";s:5:"farbe";}',
                    'objectlanguage' => 3,
                ),
            ));

        $sql = "SELECT objectdata, objectlanguage
                FROM s_core_translations
                WHERE objecttype = ? AND objectkey = ? AND objectlanguage IN (2,3)
        ";

        $queryParams = array('configuratorgroup', 15);
        $this->mockDbAdapter->expects($this->any())->method('executeQuery')->with($sql, $queryParams)->will($this->returnValue($this->mockDbStatement));

        $expected = array(
            2 => 'color',
            3 => 'farbe',
        );

        $this->assertEquals($expected, $this->gateway->getConfiguratorGroupTranslations(15, array(2,3)));
    }
}
 
<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Tests\Unit\Application\Controller\Admin;

use OxidEsales\EshopCommunity\Application\Model\Category;
use \oxField;
use \oxDb;
use OxidEsales\EshopCommunity\Application\Model\Manufacturer;
use \oxRegistry;
use OxidEsales\EshopCommunity\Application\Model\SeoEncoderArticle;
use \oxTestModules;
use OxidEsales\EshopCommunity\Application\Model\Vendor;

/**
 * Tests for Article_Seo class
 */
class ArticleSeoTest extends \OxidTestCase
{

    /**
     * Tear down the fixture.
     */
    protected function tearDown(): void
    {
        $sQ = "delete from oxvendor where oxid like '_test%'";
        oxDb::getDb()->execute($sQ);

        $sQ = "delete from oxmanufacturers where oxid like '_test%'";
        oxDb::getDb()->execute($sQ);

        $sQ = "delete from oxseo where oxobjectid='objectid'";
        oxDb::getDb()->execute($sQ);
        parent::tearDown();
    }

    /**
     * Article_Seo::getEntryUri() test case, with oxvendor as active category type given.
     */
    public function testGetEntryUriOxVendorCase()
    {
        $productId = $this->ensureProductIdExists();

        $seoEncoder = $this->getMock(\OxidEsales\Eshop\Application\Model\SeoEncoderCategory::class, array("getArticleVendorUri"));
        $seoEncoder->method('getArticleVendorUri')->will($this->returnValue("ArticleVendorUri"));

        $oView = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ArticleSeo::class, array("getEditObjectId", "_getEncoder", "getActCatType", "getEditLang"));

        $oView->method('getEditObjectId')->will($this->returnValue($productId));
        $oView->method('_getEncoder')->will($this->returnValue($seoEncoder));
        $oView->method('getActCatType')->will($this->returnValue("oxvendor"));
        $oView->method('getEditLang')->will($this->returnValue(0));

        $this->assertEquals("ArticleVendorUri", $oView->getEntryUri());
    }

    /**
     * Article_Seo::getEntryUri() test case, with the oxmanufacturer as active category type given.
     */
    public function testGetEntryUriOxManufacturerCase()
    {
        $productId = $this->ensureProductIdExists();

        $seoEncoder = $this->getMock(\OxidEsales\Eshop\Application\Model\SeoEncoderCategory::class, array("getArticleManufacturerUri"));
        $seoEncoder->method('getArticleManufacturerUri')->will($this->returnValue("ArticleManufacturerUri"));

        $view = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ArticleSeo::class, array("getEditObjectId", "_getEncoder", "getActCatType", "getEditLang"));

        $view->method('getEditObjectId')->will($this->returnValue($productId));
        $view->method('_getEncoder')->will($this->returnValue($seoEncoder));
        $view->method('getActCatType')->will($this->returnValue("oxmanufacturer"));
        $view->method('getEditLang')->will($this->returnValue(0));

        $this->assertEquals("ArticleManufacturerUri", $view->getEntryUri());
    }

    /**
     * Article_Seo::getEntryUri() test case, with given active category id.
     */
    public function testGetEntryUriDefaultWithActiveCategoryId()
    {
        $productId = $this->ensureProductIdExists();

        $seoEncoder = $this->getMock(\OxidEsales\Eshop\Application\Model\SeoEncoderCategory::class, array("getArticleUri"));
        $seoEncoder->method('getArticleUri')->will($this->returnValue("ArticleUri"));

        $view = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ArticleSeo::class, array("getEditObjectId", "_getEncoder", "getActCatType", "getEditLang", "getActCatId"));

        $view->method('getEditObjectId')->will($this->returnValue($productId));
        $view->method('_getEncoder')->will($this->returnValue($seoEncoder));
        $view->method('getActCatType')->will($this->returnValue("oxsomething"));
        $view->method('getActCatId')->will($this->returnValue(true));
        $view->method('getEditLang')->will($this->returnValue(0));

        $this->assertEquals("ArticleUri", $view->getEntryUri());
    }

    /**
     * Article_Seo::getEntryUri() test case, without given active category id.
     */
    public function testGetEntryUriDefaultWithoutActiveCategoryId()
    {
        $productId = $this->ensureProductIdExists();

        $seoEncoder = $this->getMock(\OxidEsales\Eshop\Application\Model\SeoEncoderCategory::class, array("getArticleMainUri"));
        $seoEncoder->method('getArticleMainUri')->will($this->returnValue("ArticleMainUri"));

        $view = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ArticleSeo::class, array("getEditObjectId", "_getEncoder", "getActCatType", "getEditLang", "getActCatId"));

        $view->method('getEditObjectId')->will($this->returnValue($productId));
        $view->method('_getEncoder')->will($this->returnValue($seoEncoder));
        $view->method('getActCatType')->will($this->returnValue("oxsomething"));
        $view->method('getActCatId')->will($this->returnValue(false));
        $view->method('getEditLang')->will($this->returnValue(0));

        $this->assertEquals("ArticleMainUri", $view->getEntryUri());
    }

    /**
     * Testing Article_Seo::showCatSelect()
     */
    public function showCatSelect()
    {
        $oView = oxNew('Article_Seo');
        $this->assertTrue($oView->showCatSelect());
    }

    /**
     * Article_Seo::_getEncoder() test case
     */
    public function testGetEncoder()
    {
        $oView = oxNew('Article_Seo');
        $this->assertTrue($oView->UNITgetEncoder() instanceof SeoEncoderArticle);
    }


    /**
     * Article_Seo::Render() test case
     */
    public function testRender()
    {
        $oView = oxNew('Article_Seo');
        $this->assertEquals("object_seo.tpl", $oView->render());
    }

    /**
     * Article_Seo::_getVendorList() test case (regular)
     */
    public function testGetVendorList()
    {
        $oVendor = oxNew('oxVendor');
        $oVendor->setId("_test1");
        $oVendor->save();

        $oArticle = oxNew('oxArticle');
        $oArticle->oxarticles__oxvendorid = new oxField("_test1");

        $oView = oxNew('Article_Seo');
        $aList = $oView->UNITgetVendorList($oArticle);

        $this->assertTrue(is_array($aList));

        $oArtVendor = reset($aList);
        $this->assertTrue($oArtVendor instanceof Vendor);
        $this->assertEquals($oVendor->getId(), $oArtVendor->getId());
    }

    /**
     * Article_Seo::_getManufacturerList() test case (regular)
     */
    public function testGetManufacturerList()
    {
        $oManufacturer = oxNew('oxManufacturer');
        $oManufacturer->setId("_test1");
        $oManufacturer->save();

        $oArticle = oxNew('oxArticle');
        $oArticle->oxarticles__oxmanufacturerid = new oxField("_test1");

        $oView = oxNew('Article_Seo');
        $aList = $oView->UNITgetManufacturerList($oArticle);

        $this->assertTrue(is_array($aList));

        $oArtManufacturer = reset($aList);
        $this->assertTrue($oArtManufacturer instanceof Manufacturer);
        $this->assertEquals($oManufacturer->getId(), $oArtManufacturer->getId());
    }


    /**
     * Article_Seo::getActCategory() test case (category)
     */
    public function testGetActCategory()
    {
        oxTestModules::addFunction('oxcategory', 'load', '{ return true; }');

        $oView = oxNew('Article_Seo');
        $this->assertTrue($oView->getActCategory() instanceof Category);
    }

    /**
     * Article_Seo::getActVendor() test case (manufacturer)
     */
    public function testGetActVendor()
    {
        oxTestModules::addFunction('oxvendor', 'load', '{ return true; }');

        $oView = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ArticleSeo::class, array("getActCatType"));
        $oView->expects($this->any())->method('getActCatType')->will($this->returnValue("oxvendor"));
        $this->assertTrue($oView->getActVendor() instanceof Vendor);
    }

    /**
     * Article_Seo::getActManufacturer() test case (manufacturer)
     */
    public function testGetActManufacturer()
    {
        oxTestModules::addFunction('oxmanufacturer', 'load', '{ return true; }');

        $oView = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ArticleSeo::class, array("getActCatType"));
        $oView->expects($this->any())->method('getActCatType')->will($this->returnValue("oxmanufacturer"));
        $this->assertTrue($oView->getActManufacturer() instanceof Manufacturer);
    }

    /**
     * Test, that the method 'getListType' returns null, if the active category type is oxany.
     */
    public function testGetListTypeCaseAny()
    {
        $oView = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ArticleSeo::class, array("getActCatType"));
        $oView->expects($this->any())->method('getActCatType')->will($this->returnValue("oxany"));
        $this->assertNull($oView->getListType());
    }

    /**
     * Test, that the method 'getListType' returns vendor, if the active category type is oxvendor.
     */
    public function testGetListTypeCaseVendor()
    {
        $oView = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ArticleSeo::class, array("getActCatType"));
        $oView->expects($this->any())->method('getActCatType')->will($this->returnValue("oxvendor"));
        $this->assertEquals("vendor", $oView->getListType());
    }

    /**
     * Test, that the method 'getListType' returns manufacturer, if the active category type is oxmanufacturer.
     */
    public function testGetListTypeCaseManufacturer()
    {
        $oView = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ArticleSeo::class, array("getActCatType"));
        $oView->expects($this->any())->method('getActCatType')->will($this->returnValue("oxmanufacturer"));
        $this->assertEquals("manufacturer", $oView->getListType());
    }


    /**
     * Article_Seo::_getAltSeoEntryId() test case
     */
    public function testGetAltSeoEntryId()
    {
        $oView = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ArticleSeo::class, array("getEditObjectId"));
        $oView->expects($this->once())->method('getEditObjectId')->will($this->returnValue(999));
        $this->assertEquals(999, $oView->UNITgetAltSeoEntryId());
    }

    /**
     * Article_Seo::getEditLang() test case
     */
    public function testGetEditLang()
    {
        $oView = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ArticleSeo::class, array("getActCatLang"));
        $oView->expects($this->once())->method('getActCatLang')->will($this->returnValue(999));
        $this->assertEquals(999, $oView->getEditLang());
    }

    /**
     * Article_Seo::_getSeoEntryType() test case (default)
     */
    public function testGetSeoEntryType()
    {
        $view = oxNew("Article_Seo");

        $this->assertEquals('oxarticle', $view->UNITgetSeoEntryType());
    }

    /**
     * Article_Seo::getType() test case (manufacturer)
     */
    public function testGetType()
    {
        $oView = oxNew('Article_Seo');
        $this->assertEquals('oxarticle', $oView->UNITgetType());
    }

    /**
     * Article_Seo::getActCatType() test case
     */
    public function testGetActCatType()
    {
        $this->setRequestParameter("aSeoData", null);

        $oView = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ArticleSeo::class, array("getSelectionList"));
        $oView->expects($this->once())->method("getSelectionList")->will($this->returnValue(array("type" => array(999 => "value"))));
        $this->assertEquals("type", $oView->getActCatType());

        $this->setRequestParameter("aSeoData", array("oxparams" => "type#value#999"));
        $oView->expects($this->never())->method("getSelectionList");
        $this->assertEquals("type", $oView->getActCatType());
    }

    /**
     * Article_Seo::getActCatLang() test case
     */
    public function testGetActCatLang()
    {
        $this->setRequestParameter("aSeoData", null);

        $oView = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ArticleSeo::class, array("getSelectionList"));
        $oView->expects($this->once())->method("getSelectionList")->will($this->returnValue(array("type" => array(999 => "value"))));
        $this->assertEquals(999, $oView->getActCatLang());

        $this->setRequestParameter("aSeoData", array("oxparams" => "type#value#999"));
        $oView->expects($this->never())->method("getSelectionList");
        $this->assertEquals(999, $oView->getActCatLang());
    }

    /**
     * Article_Seo::getActCatId() test case
     */
    public function testGetActCatId()
    {
        $this->setRequestParameter("aSeoData", null);

        $oItem = $this->getMock(\OxidEsales\Eshop\Application\Model\Manufacturer::class, array("getId"));
        $oItem->expects($this->once())->method("getId")->will($this->returnValue("value"));

        $oView = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ArticleSeo::class, array("getSelectionList", "getActCatType", "getActCatLang"));
        $oView->expects($this->once())->method("getSelectionList")->will($this->returnValue(array("type" => array(999 => array($oItem)))));
        $oView->expects($this->once())->method("getActCatType")->will($this->returnValue("type"));
        $oView->expects($this->once())->method("getActCatLang")->will($this->returnValue(999));
        $this->assertEquals("value", $oView->getActCatId());

        $this->setRequestParameter("aSeoData", array("oxparams" => "type#value#999"));
        $oView->expects($this->never())->method("getSelectionList");
        $this->assertEquals("value", $oView->getActCatId());
    }

    /**
     * Article_Seo::_getCategoryList() test case
     */
    public function testGetCategoryList()
    {
        $sO2CView = getViewName('oxobject2category');
        $sQ = "select oxarticles.oxid from oxarticles left join {$sO2CView} on
               oxarticles.oxid={$sO2CView}.oxobjectid where
               oxarticles.oxactive='1' and {$sO2CView}.oxobjectid is not null";

        $oDb = oxDb::getDb(oxDb::FETCH_MODE_ASSOC);
        $sProdId = $oDb->getOne($sQ);

        // must be existing
        $this->assertTrue((bool) $sProdId);

        $oProduct = oxNew('oxArticle');
        $oProduct->load($sProdId);

        $sQ = "select oxobject2category.oxcatnid as oxid from {$sO2CView} as oxobject2category where oxobject2category.oxobjectid="
              . $oDb->quote($oProduct->getId()) . " union " . $oProduct->getSqlForPriceCategories('oxid');

        $sQ = "select count(*) from ( $sQ ) as _tmp";
        $iCount = $oDb->getOne($sQ);

        $oView = oxNew('Article_Seo');
        $aList = $oView->UNITgetCategoryList($oProduct);

        // must be have few assignments
        $this->assertTrue($iCount > 0);
        $this->assertEquals($iCount, count($aList));
    }

    /**
     * Article_Seo::getSelectionList() test case
     */
    public function testGetSelectionList()
    {
        $productId = oxDb::getDb()->getOne("select oxid from oxarticles");
        $editingLanguageId = oxRegistry::getLang()->getEditLanguage();

        $product = oxNew('oxArticle');
        $product->load($productId);

        $view = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ArticleSeo::class, array("getEditObjectId", "_getCategoryList", "_getVendorList", "_getManufacturerList", "_getTagList"));
        $view->expects($this->any())->method("getEditObjectId")->will($this->returnValue($productId));
        $view->expects($this->any())->method("_getCategoryList")->will($this->returnValue("CategoryList"));
        $view->expects($this->any())->method("_getVendorList")->will($this->returnValue("VendorList"));
        $view->expects($this->any())->method("_getManufacturerList")->will($this->returnValue("ManufacturerList"));
        $view->expects($this->any())->method("_getTagList")->will($this->returnValue("TagList"));

        $expectedList = array();
        $expectedList["oxcategory"][$editingLanguageId] = "CategoryList";
        $expectedList["oxvendor"][$editingLanguageId] = "VendorList";
        $expectedList["oxmanufacturer"][$editingLanguageId] = "ManufacturerList";

        $this->assertEquals($expectedList, $view->getSelectionList());
    }

    /**
     * Article_Seo::processParam() test case (any other than tag)
     */
    public function testProcessParam()
    {
        $oView = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ArticleSeo::class, array("getActCatId"));

        $oView->expects($this->once())->method("getActCatId")->will($this->returnValue("testParam2"));

        $this->assertEquals("testParam2", $oView->processParam("testParam1#testParam2#0"));
    }

    /**
     * Vendor_Seo::isEntryFixed() test case
     */
    public function testIsEntryFixed()
    {
        $ShopId = $this->getConfig()->getShopId();
        $iLang = 0;
        $sQ = "insert into oxseo ( oxobjectid, oxident, oxshopid, oxlang, oxstdurl, oxseourl, oxtype, oxfixed, oxparams ) values
                                 ( 'objectid', 'ident', '{$ShopId}', '{$iLang}', 'stdurl', 'seourl', 'type', 1, 'catid' )";
        oxDb::getDb()->execute($sQ);

        $oView = $this->getMock(\OxidEsales\Eshop\Application\Controller\Admin\ArticleSeo::class, array("_getSaveObjectId", "getActCatId", "getEditLang", "processParam"));
        $oView
            ->method('_getSaveObjectId')
            ->willReturnOnConsecutiveCalls('objectid', 'nonexistingobjectid');

        $oView->method('getEditLang')->will($this->returnValue(0));
        $oView->method('getActCatId')->will($this->returnValue("catid"));
        $oView->method('processParam')->will($this->returnValue("catid"));

        $this->assertTrue($oView->isEntryFixed());
        $this->assertFalse($oView->isEntryFixed());
    }

    /**
     * @return string The product id.
     */
    protected function ensureProductIdExists()
    {
        $objectToCategoryViewName = getViewName('oxobject2category');
        $query = "select oxarticles.oxid from oxarticles left join {$objectToCategoryViewName} on
               oxarticles.oxid={$objectToCategoryViewName}.oxobjectid where
               oxarticles.oxactive='1' and {$objectToCategoryViewName}.oxobjectid is not null";

        $produdtId = oxDb::getDb(oxDb::FETCH_MODE_ASSOC)->getOne($query);

        // must be existing
        $this->assertTrue((bool) $produdtId);

        return $produdtId;
    }
}

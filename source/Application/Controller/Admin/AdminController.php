<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Application\Controller\Admin;

use OxidEsales\Eshop\Core\Edition\EditionSelector;
use OxidEsales\Eshop\Core\ShopVersion;
use oxDb;
use oxNavigationTree;
use oxShop;

/**
 * Main Controller class for admin area.
 */
class AdminController extends \OxidEsales\Eshop\Core\Controller\BaseController
{
    /**
     * Fixed types - enums in database.
     *
     * @var array
     */
    protected $_aSumType = [
        0 => 'abs',
        1 => '%',
        2 => 'itm'
    ];

    /**
     * Current class template name.
     *
     * @var string
     */
    protected $_sThisTemplate = null;

    /**
     * Override this in list class to show other tab from beginning
     * (default 0 - the first tab).
     *
     * @var int
     */
    protected $_iDefEdit = 0;

    /**
     * Navigation tree object
     *
     * @var \OxidEsales\Eshop\Application\Controller\Admin\NavigationTree
     */
    protected static $_oNaviTree = null;

    /**
     * Objects editing language (default 0).
     *
     * @var integer
     */
    protected $_iEditLang = 0;

    /**
     * Active shop title
     *
     * @var string
     */
    protected $_sShopTitle = " - ";

    /**
     * Session user rights
     *
     * @var string
     */
    protected static $_sAuthUserRights = null;

    /**
     * Active shop object
     *
     * @return
     */
    protected $_oEditShop = null;

    /**
     * Editable object id
     *
     * @var string
     */
    protected $_sEditObjectId = null;

    /**
     * Optional view id.
     *
     * @var string
     */
    protected $viewId = null;

    /**
     * Creates oxshop object and loads shop data, sets title of shop
     */
    public function __construct()
    {
        $myConfig = \OxidEsales\Eshop\Core\Registry::getConfig();
        $myConfig->setConfigParam('blAdmin', true);
        $this->setAdminMode(true);

        if ($oShop = $this->_getEditShop($myConfig->getShopId())) {
            // passing shop info
            $this->_sShopTitle = $oShop->oxshops__oxname->getRawValue();
        }
    }

    /**
     * Returns (cached) shop object
     *
     * @param object $sShopId shop id
     *
     * @return \OxidEsales\Eshop\Application\Model\Shop
     * @deprecated underscore prefix violates PSR12, will be renamed to "getEditShop" in next major
     */
    protected function _getEditShop($sShopId) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        if (!$this->_oEditShop) {
            $this->_oEditShop = \OxidEsales\Eshop\Core\Registry::getConfig()->getActiveShop();
            if ($this->_oEditShop->getId() != $sShopId) {
                $oEditShop = oxNew(\OxidEsales\Eshop\Application\Model\Shop::class);
                if ($oEditShop->load($sShopId)) {
                    $this->_oEditShop = $oEditShop;
                }
            }
        }

        return $this->_oEditShop;
    }

    /**
     * Sets some shop configuration parameters (such as language),
     * creates some list object (depends on subclass) and executes
     * parent method parent::Init().
     */
    public function init()
    {
        // authorization check
        if (!$this->_authorize()) {
            \OxidEsales\Eshop\Core\Registry::getUtils()->redirect('index.php?cl=login', true, 302);
            exit('Authorization error occurred!');
        }

        $oLang = \OxidEsales\Eshop\Core\Registry::getLang();

        // language handling
        $this->_iEditLang = $oLang->getEditLanguage();
        $oLang->setBaseLanguage();

        parent::init();

        $this->_aViewData['malladmin'] = \OxidEsales\Eshop\Core\Registry::getSession()->getVariable('malladmin');
    }

    /**
     * Sets some global parameters to Smarty engine (such as self link, etc.), returns
     * modified shop object.
     *
     * @param object $oShop Object to modify some parameters
     *
     * @return object
     */
    public function addGlobalParams($oShop = null)
    {
        $myConfig = \OxidEsales\Eshop\Core\Registry::getConfig();
        $oLang = \OxidEsales\Eshop\Core\Registry::getLang();

        $oShop = parent::addGlobalParams($oShop);

        // override cause of admin dir
        $sURL = $myConfig->getConfigParam('sShopURL') . $myConfig->getConfigParam('sAdminDir') . "/";

        if ($myConfig->getConfigParam('sAdminSSLURL')) {
            $sURL = $myConfig->getConfigParam('sAdminSSLURL');
        }

        $oViewConf = $this->getViewConfig();
        $oViewConf->setViewConfigParam('selflink', \OxidEsales\Eshop\Core\Registry::getUtilsUrl()->processUrl($sURL . 'index.php?editlanguage=' . $this->_iEditLang, false));
        $oViewConf->setViewConfigParam('ajaxlink', str_replace('&amp;', '&', \OxidEsales\Eshop\Core\Registry::getUtilsUrl()->processUrl($sURL . 'oxajax.php?editlanguage=' . $this->_iEditLang, false)));

        // set language of admin backend
        $this->_aViewData['adminlang'] = $oLang->getTplLanguage();
        $this->_aViewData['charset'] = $this->getCharSet();

        //setting active currency object
        $this->_aViewData["oActCur"] = $myConfig->getActShopCurrencyObject();

        return $oShop;
    }

    /**
     * Returns service url protocol: "https" is admin works in ssl mode, "http" if no ssl
     *
     * @return string
     * @deprecated underscore prefix violates PSR12, will be renamed to "getServiceProtocol" in next major
     */
    protected function _getServiceProtocol() // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return \OxidEsales\Eshop\Core\Registry::getConfig()->isSsl() ? 'https' : 'http';
    }

    /**
     * Sets-up navigation parameters
     *
     * @param string $sNode active view id
     * @deprecated underscore prefix violates PSR12, will be renamed to "setupNavigation" in next major
     */
    protected function _setupNavigation($sNode) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        // navigation according to class
        if ($sNode) {
            $myAdminNavig = $this->getNavigation();

            // active tab
            $iActTab = \OxidEsales\Eshop\Core\Registry::getConfig()->getRequestParameter('actedit');
            $iActTab = $iActTab ? $iActTab : $this->_iDefEdit;

            $sActTab = $iActTab ? "&actedit=$iActTab" : '';

            // store navigation history
            $this->_addNavigationHistory($sNode);

            // list url
            $this->_aViewData['listurl'] = $myAdminNavig->getListUrl($sNode) . $sActTab;

            // edit url
            $this->_aViewData['editurl'] = $myAdminNavig->getEditUrl($sNode, $iActTab) . $sActTab;
        }
    }

    /**
     * Store navigation history parameters to cookie
     *
     * @param string $sNode active view id
     * @deprecated underscore prefix violates PSR12, will be renamed to "addNavigationHistory" in next major
     */
    protected function _addNavigationHistory($sNode) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        $myUtilsServer = \OxidEsales\Eshop\Core\Registry::getUtilsServer();

        // store navigation history
        $aHistory = explode('|', $myUtilsServer->getOxCookie('oxidadminhistory'));
        if (!is_array($aHistory)) {
            $aHistory = [];
        }

        if (!in_array($sNode, $aHistory)) {
            $aHistory[] = $sNode;
        }

        $myUtilsServer->setOxCookie('oxidadminhistory', implode('|', $aHistory));
    }

    /**
     * Executes parent method parent::render(), passes configuration data to
     * Smarty engine.
     *
     * @return string
     */
    public function render()
    {
        $sReturn = parent::render();

        $myConfig = \OxidEsales\Eshop\Core\Registry::getConfig();
        $oLang = \OxidEsales\Eshop\Core\Registry::getLang();

        // sets up navigation data
        $this->_setupNavigation(\OxidEsales\Eshop\Core\Registry::getConfig()->getRequestControllerId());

        // active object id
        $sOxId = $this->getEditObjectId();
        $this->_aViewData['oxid'] = (!$sOxId) ? -1 : $sOxId;
        // add Sumtype to all templates
        $this->_aViewData['sumtype'] = $this->_aSumType;

        // active shop title
        $this->_aViewData['actshop'] = $this->_sShopTitle;
        $this->_aViewData["shopid"] = $myConfig->getShopId();

        // loading active shop
        if ($sActShopId = \OxidEsales\Eshop\Core\Registry::getSession()->getVariable('actshop')) {
            // load object
            $this->_aViewData['actshopobj'] = $this->_getEditShop($sActShopId);
        }

        // add language data to all templates
        $this->_aViewData['actlang'] = $iLanguage = $oLang->getBaseLanguage();
        $this->_aViewData['editlanguage'] = $this->_iEditLang;
        $this->_aViewData['languages'] = $oLang->getLanguageArray($iLanguage);

        // setting maximum upload size
        list($this->_aViewData['iMaxUploadFileSize'], $this->_aViewData['sMaxFormattedFileSize']) = $this->_getMaxUploadFileInfo(@ini_get("upload_max_filesize"));

        // "save-on-tab"
        if (!isset($this->_aViewData['updatelist'])) {
            $this->_aViewData['updatelist'] = \OxidEsales\Eshop\Core\Registry::getConfig()->getRequestParameter('updatelist');
        }

        return $sReturn;
    }

    /**
     * Returns maximum allowed size of upload file and formatted size equivalent
     *
     * @param string $maxFileSize recommended maximum size of file (normalu value is taken from php ini, otherwise sets 2MB)
     * @param bool $isFormatted Return formated
     *
     * @return array
     * @deprecated underscore prefix violates PSR12, will be renamed to "getMaxUploadFileInfo" in next major
     */
    protected function _getMaxUploadFileInfo( // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
        $maxFileSize,
        $isFormatted = false
    ) {
        $maxFileSize = $maxFileSize ? trim($maxFileSize) : '2M';

        // processing config
        $intMaxFileSize = (int)$maxFileSize;
        $sParam = strtolower($maxFileSize[strlen($maxFileSize) - 1]);
        switch ($sParam) {
            case 'g':
                $intMaxFileSize *= 1024;
            // no break
            case 'm':
                $intMaxFileSize *= 1024;
            // no break
            case 'k':
                $intMaxFileSize *= 1024;
        }

        // formatting
        $markers = ['KB', 'MB', 'GB'];
        $sFormattedMaxSize = '';

        $size = floor($intMaxFileSize / 1024);
        while ($size && current($markers)) {
            $sFormattedMaxSize = $size . " " . current($markers);
            $size = floor($size / 1024);
            next($markers);
        }

        return [$intMaxFileSize, $sFormattedMaxSize];
    }

    /**
     * Clears cache
     */
    public function save()
    {
        $this->resetContentCache();
    }

    /**
     * Reset output cache
     *
     * @param bool $blForceReset if true, forces reset
     */
    public function resetContentCache($blForceReset = null)
    {
        $blDeleteCacheOnLogout = \OxidEsales\Eshop\Core\Registry::getConfig()->getConfigParam('blClearCacheOnLogout');
        if (!$blDeleteCacheOnLogout || $blForceReset) {
            \OxidEsales\Eshop\Core\Registry::getUtils()->oxResetFileCache();
        }
    }

    /**
     * Resets counters values from cache. Resets price category articles, category articles,
     * vendor articles, manufacturer articles count.
     *
     * @param string $sCounterType counter type
     * @param string $sValue       reset value
     */
    public function resetCounter($sCounterType, $sValue = null)
    {
        $blDeleteCacheOnLogout = \OxidEsales\Eshop\Core\Registry::getConfig()->getConfigParam('blClearCacheOnLogout');
        $myUtilsCount = \OxidEsales\Eshop\Core\Registry::getUtilsCount();

        if (!$blDeleteCacheOnLogout) {
            switch ($sCounterType) {
                case 'priceCatArticle':
                    $myUtilsCount->resetPriceCatArticleCount($sValue);
                    break;
                case 'catArticle':
                    $myUtilsCount->resetCatArticleCount($sValue);
                    break;
                case 'vendorArticle':
                    $myUtilsCount->resetVendorArticleCount($sValue);
                    break;
                case 'manufacturerArticle':
                    $myUtilsCount->resetManufacturerArticleCount($sValue);
                    break;
            }
            $this->_resetContentCache();
        }
    }

    /**
     * Resets cache.
     * @deprecated underscore prefix violates PSR12, will be renamed to "resetContentCache" in next major
     */
    protected function _resetContentCache() // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
    }

    /**
     * Checks if current $sUserId user is not an admin and checks if user is able to be edited by logged in user.
     * This method does not perform full rights check.
     *
     * @param string $sUserId user id
     *
     * @return bool
     * @deprecated underscore prefix violates PSR12, will be renamed to "allowAdminEdit" in next major
     */
    protected function _allowAdminEdit($sUserId) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return true;
    }

    /**
     * Get english country name by country iso alpha 2 code
     *
     * @param string $sCountryCode Country code
     *
     * @return boolean
     * @deprecated underscore prefix violates PSR12, will be renamed to "getCountryByCode" in next major
     */
    protected function _getCountryByCode($sCountryCode) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        //default country
        $sCountry = 'international';

        if (!empty($sCountryCode)) {
            $aLangIds = \OxidEsales\Eshop\Core\Registry::getLang()->getLanguageIds();
            $iEnglishId = array_search("en", $aLangIds);
            if (false !== $iEnglishId) {
                $sViewName = getViewName("oxcountry", $iEnglishId);
                $sQ = "select oxtitle from {$sViewName} where oxisoalpha2 = :oxisoalpha2";
                // Value does not change that often, reading from slave is ok here (see ESDEV-3804 and ESDEV-3822).
                $sCountryName = \OxidEsales\Eshop\Core\DatabaseProvider::getDb()->getOne($sQ, [
                    ':oxisoalpha2' => $sCountryCode
                ]);
                if ($sCountryName) {
                    $sCountry = $sCountryName;
                }
            } else {
                // handling when english language is deleted
                switch ($sCountryCode) {
                    case 'de':
                        return 'germany';
                    default:
                        return 'international';
                }
            }
        }

        return strtolower($sCountry);
    }

    /**
     * performs authorization of admin user
     *
     * @return boolean
     * @deprecated underscore prefix violates PSR12, will be renamed to "authorize" in next major
     */
    protected function _authorize() // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        $session = \OxidEsales\Eshop\Core\Registry::getSession();
        return (bool) (
            $session->checkSessionChallenge()
            && count(\OxidEsales\Eshop\Core\Registry::getUtilsServer()->getOxCookie())
            && \OxidEsales\Eshop\Core\Registry::getUtils()->checkAccessRights()
        );
    }

    /**
     * Returns navigation object
     *
     * @return \OxidEsales\Eshop\Application\Controller\Admin\NavigationTree
     */
    public function getNavigation()
    {
        if (self::$_oNaviTree == null) {
            self::$_oNaviTree = oxNew(\OxidEsales\Eshop\Application\Controller\Admin\NavigationTree::class);
        }

        return self::$_oNaviTree;
    }

    /**
     * Current view ID getter helps to identify navigation position
     *
     * @return string
     */
    public function getViewId()
    {
        $viewId = is_null($this->viewId) ? strtolower($this->getControllerKey()) : $this->viewId;
        return $this->getNavigation()->getClassId($viewId);
    }

    /**
     * Changing active shop
     */
    public function chshp()
    {
        $sActShop = \OxidEsales\Eshop\Core\Registry::getConfig()->getRequestParameter('shp');
        \OxidEsales\Eshop\Core\Registry::getSession()->setVariable("shp", $sActShop);
        \OxidEsales\Eshop\Core\Registry::getSession()->setVariable('currentadminshop', $sActShop);
    }

    /**
     * Marks seo entires as expired.
     *
     * @param string $sShopId Shop id
     */
    public function resetSeoData($sShopId)
    {
        $aTypes = ['oxarticle', 'oxcategory', 'oxvendor', 'oxcontent', 'dynamic', 'oxmanufacturer'];
        $oEncoder = \OxidEsales\Eshop\Core\Registry::getSeoEncoder();
        foreach ($aTypes as $sType) {
            $oEncoder->markAsExpired(null, $sShopId, 1, null, "oxtype = '{$sType}'");
        }
    }

    /**
     * Returns id which is used for product preview in shop during administration
     *
     * @return string
     */
    public function getPreviewId()
    {
        return \OxidEsales\Eshop\Core\Registry::getUtils()->getPreviewId();
    }

    /**
     * Returns active/editable object id
     *
     * @return string
     */
    public function getEditObjectId()
    {
        if (null === ($sId = $this->_sEditObjectId)) {
            if (null === ($sId = \OxidEsales\Eshop\Core\Registry::getConfig()->getRequestParameter("oxid"))) {
                $sId = \OxidEsales\Eshop\Core\Registry::getSession()->getVariable("saved_oxid");
            }
        }

        return $sId;
    }

    /**
     * Sets editable object id
     *
     * @param string $sId object id
     */
    public function setEditObjectId($sId)
    {
        $this->_sEditObjectId = $sId;
        $this->_aViewData["updatelist"] = 1;
    }

    /**
     * Returns true if editable object is new.
     *
     * @return bool
     */
    protected function isNewEditObject()
    {
        return '-1' === (string) $this->getEditObjectId();
    }

    /**
     * Get controller key also for chain extended class.
     *
     * @return null|string
     */
    protected function getControllerKey()
    {
        $actualClass = get_class($this);
        $controllerKey = \OxidEsales\Eshop\Core\Registry::getControllerClassNameResolver()->getIdByClassName($actualClass);
        if (is_null($controllerKey)) {
            //we might not have found a class key because class is a module chain extended class
            $controllerKey = \OxidEsales\Eshop\Core\Registry::getControllerClassNameResolver()->getIdByClassName($this->getShopParentClass());
        }
        return $controllerKey;
    }

    /**
     * Method to figure out \OxidEsales\Eshop class.
     *
     * @return string
     */
    protected function getShopParentClass()
    {
        $className = get_class($this); //actual class, might be shop class chain extended by module
        while ($className && !\OxidEsales\Eshop\Core\NamespaceInformationProvider::classBelongsToShopUnifiedNamespace($className)) {
            $className = get_parent_class($className);
        }
        return $className;
    }
}

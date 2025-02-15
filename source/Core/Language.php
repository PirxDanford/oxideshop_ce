<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Core;

use OxidEsales\EshopCommunity\Internal\Framework\Module\Translation\Bridge\AdminAreaModuleTranslationFileLocatorBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Translation\Bridge\FrontendModuleTranslationFileLocatorBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Theme\Bridge\AdminThemeBridgeInterface;
use OxidEsales\Eshop\Core\Exception\LanguageNotFoundException;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\Str;
use stdClass;

/**
 * Language related utility class
 */
class Language extends \OxidEsales\Eshop\Core\Base
{
    /**
     * Language parameter name
     *
     * @var string
     */
    protected $_sName = 'lang';

    /**
     * Current shop base language Id
     *
     * @var int
     */
    protected $_iBaseLanguageId = null;

    /**
     * Templates language Id
     *
     * @var int
     */
    protected $_iTplLanguageId = null;

    /**
     * Editing object language Id
     *
     * @var int
     */
    protected $_iEditLanguageId = null;

    /**
     * Language translations array cache
     *
     * @var array
     */
    protected $_aLangCache = [];

    /**
     * Array containing possible admin template translations
     *
     * @var array
     */
    protected $_aAdminTplLanguageArray = null;

    /**
     * Language abbreviation array
     *
     * @var array
     */
    protected $_aLangAbbr = [];

    /**
     * registered additional language filesets to load
     *
     * @var array
     */
    protected $_aAdditionalLangFiles = [];

    /**
     * registered additional language filesets to load
     *
     * @var array
     */
    protected $_aLangMap = [];

    /**
     * State is string translated or not
     *
     * @var bool
     */
    protected $_blIsTranslated = true;

    /**
     * Template language id.
     *
     * @var int
     */
    protected $_iObjectTplLanguageId = null;

    /**
     * Set translation state
     *
     * @param bool $blIsTranslated State is string translated or not. Default true.
     */
    public function setIsTranslated($blIsTranslated = true)
    {
        $this->_blIsTranslated = $blIsTranslated;
    }

    /**
     * Set translation state
     *
     * @return bool
     */
    public function isTranslated()
    {
        return $this->_blIsTranslated;
    }

    /**
     * resetBaseLanguage resets base language id cache
     *
     * @access public
     */
    public function resetBaseLanguage()
    {
        $this->_iBaseLanguageId = null;
    }

    /**
     * Returns active shop language id
     *
     * @return string
     */
    public function getBaseLanguage()
    {
        if ($this->_iBaseLanguageId === null) {
            $myConfig = Registry::getConfig();
            $blAdmin = $this->isAdmin();

            // languages and search engines
            if ($blAdmin && (($iSeLang = Registry::getConfig()->getRequestParameter('changelang')) !== null)) {
                $this->_iBaseLanguageId = $iSeLang;
            }

            if (is_null($this->_iBaseLanguageId)) {
                $this->_iBaseLanguageId = Registry::getConfig()->getRequestParameter('lang');
            }

            //or determining by domain
            $aLanguageUrls = $myConfig->getConfigParam('aLanguageURLs');

            if (!$blAdmin && is_array($aLanguageUrls)) {
                foreach ($aLanguageUrls as $iId => $sUrl) {
                    if ($sUrl && $myConfig->isCurrentUrl($sUrl)) {
                        $this->_iBaseLanguageId = $iId;
                        break;
                    }
                }
            }

            if (is_null($this->_iBaseLanguageId)) {
                $this->_iBaseLanguageId = Registry::getConfig()->getRequestParameter('language');
                if (!isset($this->_iBaseLanguageId)) {
                    $this->_iBaseLanguageId = Registry::getSession()->getVariable('language');
                }
            }

            // if language still not set and not search engine browsing,
            // getting language from browser
            if (is_null($this->_iBaseLanguageId) && !$blAdmin && !Registry::getUtils()->isSearchEngine()) {
                // getting from cookie
                $this->_iBaseLanguageId = Registry::getUtilsServer()->getOxCookie('language');

                // getting from browser
                if (is_null($this->_iBaseLanguageId)) {
                    $this->_iBaseLanguageId = $this->detectLanguageByBrowser();
                }
            }

            if (is_null($this->_iBaseLanguageId)) {
                $this->_iBaseLanguageId = $myConfig->getConfigParam('sDefaultLang');
            }

            $this->_iBaseLanguageId = (int) $this->_iBaseLanguageId;

            // validating language
            $this->_iBaseLanguageId = $this->validateLanguage($this->_iBaseLanguageId);

            Registry::getUtilsServer()->setOxCookie('language', $this->_iBaseLanguageId);
        }

        return $this->_iBaseLanguageId;
    }

    /**
     * Returns language id used to load objects according to current template language
     *
     * @return int
     */
    public function getObjectTplLanguage()
    {
        if ($this->_iObjectTplLanguageId === null) {
            $this->_iObjectTplLanguageId = $this->getTplLanguage();

            if ($this->isAdmin()) {
                $aLanguages = $this->getAdminTplLanguageArray();
                if (
                    !isset($aLanguages[$this->_iObjectTplLanguageId]) ||
                    $aLanguages[$this->_iObjectTplLanguageId]->active == 0
                ) {
                    $this->_iObjectTplLanguageId = key($aLanguages);
                }
            }
        }

        return $this->_iObjectTplLanguageId;
    }

    /**
     * Returns active shop templates language id
     * If it is not an admin area, template language id is same
     * as base shop language id
     *
     * @return string
     */
    public function getTplLanguage()
    {
        if ($this->_iTplLanguageId === null) {
            $iSessLang = Registry::getSession()->getVariable('tpllanguage');
            $this->_iTplLanguageId = $this->isAdmin() ? $this->setTplLanguage($iSessLang) : $this->getBaseLanguage();
        }

        return $this->_iTplLanguageId;
    }

    /**
     * Returns editing object working language id
     *
     * @return string
     */
    public function getEditLanguage()
    {
        if ($this->_iEditLanguageId === null) {
            if (!$this->isAdmin()) {
                $this->_iEditLanguageId = $this->getBaseLanguage();
            } else {
                $iLang = null;
                // choosing language ident
                // check if we really need to set the new language
                if ("saveinnlang" == Registry::getConfig()->getActiveView()->getFncName()) {
                    $iLang = Registry::getConfig()->getRequestParameter("new_lang");
                }
                $iLang = ($iLang === null) ? Registry::getConfig()->getRequestParameter('editlanguage') : $iLang;
                $iLang = ($iLang === null) ? Registry::getSession()->getVariable('editlanguage') : $iLang;
                $iLang = ($iLang === null) ? $this->getBaseLanguage() : $iLang;

                // validating language
                $this->_iEditLanguageId = $this->validateLanguage($iLang);

                // writing to session
                Registry::getSession()->setVariable('editlanguage', $this->_iEditLanguageId);
            }
        }

        return $this->_iEditLanguageId;
    }

    /**
     * Returns array of available languages.
     *
     * @param integer $iLanguage    Number if current language (default null)
     * @param bool    $blOnlyActive load only current language or all
     * @param bool    $blSort       enable sorting or not
     *
     * @return array
     */
    public function getLanguageArray($iLanguage = null, $blOnlyActive = false, $blSort = false)
    {
        $myConfig = Registry::getConfig();

        if (is_null($iLanguage)) {
            $iLanguage = $this->_iBaseLanguageId;
        }

        $aLanguages = [];
        $aConfLanguages = $myConfig->getConfigParam('aLanguages');
        $aLangParams = $myConfig->getConfigParam('aLanguageParams');

        if (is_array($aConfLanguages)) {
            $i = 0;
            reset($aConfLanguages);
            foreach ($aConfLanguages as $key => $val) {
                if ($blOnlyActive && is_array($aLangParams)) {
                    //skipping non active languages
                    if (!$aLangParams[$key]['active']) {
                        $i++;
                        continue;
                    }
                }

                if ($val) {
                    $oLang = new stdClass();
                    $oLang->id = isset($aLangParams[$key]['baseId']) ? $aLangParams[$key]['baseId'] : $i;
                    $oLang->oxid = $key;
                    $oLang->abbr = $key;
                    $oLang->name = $val;

                    if (is_array($aLangParams)) {
                        $oLang->active = $aLangParams[$key]['active'];
                        $oLang->sort = $aLangParams[$key]['sort'];
                    }

                    $oLang->selected = (isset($iLanguage) && $oLang->id == $iLanguage) ? 1 : 0;
                    $aLanguages[$oLang->id] = $oLang;
                }
                ++$i;
            }
        }

        if ($blSort && is_array($aLangParams)) {
            uasort($aLanguages, [$this, '_sortLanguagesCallback']);
        }

        return $aLanguages;
    }

    /**
     * Returns languages array containing possible admin template translations
     *
     * @return array
     */
    public function getAdminTplLanguageArray()
    {
        if ($this->_aAdminTplLanguageArray === null) {
            $config = Registry::getConfig();

            $langArray = $this->getLanguageArray();
            $this->_aAdminTplLanguageArray = [];

            $adminThemeName = $this->getContainer()
                ->get(AdminThemeBridgeInterface::class)
                ->getActiveTheme();
            $sourceDirectory =
                $config->getAppDir() .
                'views' . DIRECTORY_SEPARATOR .
                $adminThemeName . DIRECTORY_SEPARATOR;

            foreach ($langArray as $langKey => $language) {
                $filePath = $sourceDirectory . $language->abbr . DIRECTORY_SEPARATOR . 'lang.php';
                if (file_exists($filePath) && is_readable($filePath)) {
                    $this->_aAdminTplLanguageArray[$langKey] = $language;
                }
            }
        }

        // moving pointer to beginning
        reset($this->_aAdminTplLanguageArray);

        return $this->_aAdminTplLanguageArray;
    }

    /**
     * Returns selected language abbreviation
     *
     * @param int $iLanguage language id [optional]
     *
     * @return string
     */
    public function getLanguageAbbr($iLanguage = null)
    {
        if ($this->_aLangAbbr === []) {
            $this->_aLangAbbr = $this->getLanguageIds();
        }

        $iLanguage = isset($iLanguage) ? (int) $iLanguage : $this->getBaseLanguage();
        if (isset($this->_aLangAbbr[$iLanguage])) {
            return $this->_aLangAbbr[$iLanguage];
        }

        throw new LanguageNotFoundException(
            'Could not find language abbreviation for language-id ' . $iLanguage . '! '
            . (
                count($this->_aLangAbbr) === 0
                ? 'No languages available'
                : 'Available languages: ' . implode(', ', $this->_aLangAbbr)
            )
        );
    }

    /**
     * getLanguageNames returns array of language names e.g. array('Deutch', 'English')
     *
     * @access public
     * @return array
     */
    public function getLanguageNames()
    {
        $aConfLanguages = Registry::getConfig()->getConfigParam('aLanguages');
        $aLangIds = $this->getLanguageIds();
        $aLanguages = [];
        foreach ($aLangIds as $iId => $sValue) {
            $aLanguages[$iId] = $aConfLanguages[$sValue];
        }

        return $aLanguages;
    }

    /**
     * Searches for translation string in file and on success returns translation,
     * otherwise returns initial string.
     *
     * @param string $sStringToTranslate Initial string
     * @param int    $iLang              optional language number
     * @param bool   $blAdminMode        on special case you can force mode, to load language constant from admin/shops language file
     *
     * @return string|array
     */
    public function translateString($sStringToTranslate, $iLang = null, $blAdminMode = null)
    {
        $this->setIsTranslated();
        // checking if in cache exist
        $aLang = $this->_getLangTranslationArray($iLang, $blAdminMode);
        if (isset($aLang[$sStringToTranslate])) {
            return $aLang[$sStringToTranslate];
        }

        // checking if in map exist
        $aMap = $this->_getLanguageMap($iLang, $blAdminMode);
        if (isset($aMap[$sStringToTranslate], $aLang[$aMap[$sStringToTranslate]])) {
            return $aLang[$aMap[$sStringToTranslate]];
        }

        // checking if in theme options exist
        if (count($this->_aAdditionalLangFiles)) {
            $aLang = $this->_getLangTranslationArray($iLang, $blAdminMode, $this->_aAdditionalLangFiles);
            if (isset($aLang[$sStringToTranslate])) {
                return $aLang[$sStringToTranslate];
            }
        }

        $this->setIsTranslated(false);

        if (!$this->isTranslated()) {
            Registry::getLogger()->warning(
                "translation for $sStringToTranslate not found",
                compact('iLang', 'blAdminMode')
            );
        }

        return $sStringToTranslate;
    }

    /**
     * Iterates through given array ($aData) and collects data if array key is similar as
     * searchable key ($sKey*). If you pass $aCollection, it will be appended with found items
     *
     * @param array  $aData       array to search in
     * @param string $sKey        key to look for (looking for similar with strpos)
     * @param array  $aCollection array to append found items [optional]
     *
     * @return array
     * @deprecated underscore prefix violates PSR12, will be renamed to "collectSimilar" in next major
     */
    protected function _collectSimilar($aData, $sKey, $aCollection = []) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        foreach ($aData as $sValKey => $sValue) {
            if (strpos($sValKey, $sKey) === 0) {
                $aCollection[$sValKey] = $sValue;
            }
        }

        return $aCollection;
    }

    /**
     * Returns array( "MY_TRANSLATION_KEY" => "MY_TRANSLATION_VALUE", ... ) by
     * given filter "MY_TRANSLATION_" from language files
     *
     * @param string $sKey    key to look
     * @param int    $iLang   language files to search [optional]
     * @param bool   $blAdmin admin/non admin mode [optional]
     *
     * @return array
     */
    public function getSimilarByKey($sKey, $iLang = null, $blAdmin = null)
    {
        startProfile("getSimilarByKey");

        $iLang = isset($iLang) ? $iLang : $this->getTplLanguage();
        $blAdmin = isset($blAdmin) ? $blAdmin : $this->isAdmin();

        // checking if exists in cache
        $aLang = $this->_getLangTranslationArray($iLang, $blAdmin);
        $aSimilarConst = $this->_collectSimilar($aLang, $sKey);

        // checking if in map exist
        $aMap = $this->_getLanguageMap($iLang, $blAdmin);
        $aSimilarConst = $this->_collectSimilar($aMap, $sKey, $aSimilarConst);

        // checking if in theme options exist
        if (count($this->_aAdditionalLangFiles)) {
            $aLang = $this->_getLangTranslationArray($iLang, $blAdmin, $this->_aAdditionalLangFiles);
            $aSimilarConst = $this->_collectSimilar($aLang, $sKey, $aSimilarConst);
        }

        stopProfile("getSimilarByKey");

        return $aSimilarConst;
    }

    /**
     * Returns formatted number, according to active currency formatting standards.
     *
     * @param float  $dValue  Plain price
     * @param object $oActCur Object of active currency
     *
     * @return string
     */
    public function formatCurrency($dValue, $oActCur = null)
    {
        if (!$oActCur) {
            $oActCur = Registry::getConfig()->getActShopCurrencyObject();
        }
        $sValue = Registry::getUtils()->fRound($dValue, $oActCur);

        return number_format((double) $sValue, $oActCur->decimal, $oActCur->dec, $oActCur->thousand);
    }

    /**
     * Returns formatted vat value, according to formatting standards.
     *
     * @param float  $dValue  Plain price
     * @param object $oActCur Object of active currency
     *
     * @return string
     */
    public function formatVat($dValue, $oActCur = null)
    {
        $iDecPos = 0;
        $sValue = (string) $dValue;
        $oStr = Str::getStr();
        if (($iDotPos = $oStr->strpos($sValue, '.')) !== false) {
            $iDecPos = $oStr->strlen($oStr->substr($sValue, $iDotPos + 1));
        }

        $oActCur = $oActCur ? $oActCur : Registry::getConfig()->getActShopCurrencyObject();
        $iDecPos = ($iDecPos < $oActCur->decimal) ? $iDecPos : $oActCur->decimal;

        return number_format((double) $dValue, $iDecPos, $oActCur->dec, $oActCur->thousand);
    }

    /**
     * According to user configuration forms and return language prefix.
     *
     * @param integer $iLanguage User selected language (default null)
     *
     * @return string
     */
    public function getLanguageTag($iLanguage = null)
    {
        if (!isset($iLanguage)) {
            $iLanguage = $this->getBaseLanguage();
        }

        $iLanguage = (int) $iLanguage;

        return ($iLanguage) ? "_$iLanguage" : "";
    }

    /**
     * Validate language id. If not valid id, returns default value
     *
     * @param int $iLang Language id
     *
     * @return int
     */
    public function validateLanguage($iLang = null)
    {
        // checking if this language is valid
        $aLanguages = $this->getLanguageArray(null, !$this->isAdmin());
        if (!isset($aLanguages[$iLang]) && is_array($aLanguages)) {
            $oLang = current($aLanguages);
            if (isset($oLang->id)) {
                $iLang = $oLang->id;
            }
        }

        return (int) $iLang;
    }

    /**
     * Set base shop language
     *
     * @param int $iLang Language id
     */
    public function setBaseLanguage($iLang = null)
    {
        if (is_null($iLang)) {
            $iLang = $this->getBaseLanguage();
        } else {
            $this->_iBaseLanguageId = (int) $iLang;
        }

        Registry::getSession()->setVariable('language', $iLang);
    }

    /**
     * Validates and sets templates language id
     *
     * @param int $iLang Language id
     *
     * @return null
     */
    public function setTplLanguage($iLang = null)
    {
        $this->_iTplLanguageId = isset($iLang) ? (int) $iLang : $this->getBaseLanguage();
        if ($this->isAdmin()) {
            $aLanguages = $this->getAdminTplLanguageArray();
            if (!isset($aLanguages[$this->_iTplLanguageId])) {
                $this->_iTplLanguageId = key($aLanguages);
            }
        }

        Registry::getSession()->setVariable('tpllanguage', $this->_iTplLanguageId);

        return $this->_iTplLanguageId;
    }

    /**
     * Returns the encoding all translations will be converted to.
     *
     * @return string
     */
    protected function getTranslationsExpectedEncoding()
    {
        return 'UTF-8';
    }

    /**
     * Returns array with paths where frontend language files are stored
     *
     * @param int $iLang active language
     *
     * @return array
     * @deprecated underscore prefix violates PSR12, will be renamed to "getLangFilesPathArray" in next major
     */
    protected function _getLangFilesPathArray($iLang) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        $oConfig = Registry::getConfig();
        $aLangFiles = [];

        $sAppDir = $oConfig->getAppDir();
        $sLang = Registry::getLang()->getLanguageAbbr($iLang);
        $sTheme = $oConfig->getConfigParam("sTheme");

        //get generic lang files
        $sGenericPath = $sAppDir . 'translations/' . $sLang;
        if ($sGenericPath) {
            $aLangFiles = array_merge($aLangFiles, $this->getAbbreviationDirectoryLanguageFiles($sGenericPath));
        }

        //get theme lang files
        if ($sTheme) {
            $sThemePath = $sAppDir . 'views/' . $sTheme . '/';
            $aLangFiles = array_merge($aLangFiles, $this->getThemeLanguageFiles($sThemePath, $sLang));
        }

        $aLangFiles = array_merge($aLangFiles, $this->getCustomThemeLanguageFiles($iLang));

        // modules language files
        $aLangFiles = $this->appendModuleLangFilesForFrontend($aLangFiles, $sLang);

        // custom language files
        $aLangFiles = $this->_appendCustomLangFiles($aLangFiles, $sLang);

        return count($aLangFiles) ? $aLangFiles : false;
    }

    /**
     * @param string $themePath
     * @param string $languageAbbreviation
     * @return array<string>
     */
    protected function getThemeLanguageFiles(string $themePath, string $languageAbbreviation): array
    {
        $files = $this->getAbbreviationDirectoryLanguageFiles(
            $themePath . 'translations/' . $languageAbbreviation
        );

        $files = array_merge($files, $this->getAbbreviationDirectoryLanguageFiles(
            $themePath . $languageAbbreviation
        ));

        return $files;
    }

    /**
     * @param string $directory
     * @return array<string>
     */
    protected function getAbbreviationDirectoryLanguageFiles(string $directory): array
    {
        $files = [
            $directory . '/lang.php'
        ];

        $files = $this->_appendLangFile($files, $directory);

        return $files;
    }

    /**
     * Returns custom theme language files.
     *
     * @param int $language active language
     *
     * @return array
     */
    protected function getCustomThemeLanguageFiles($language)
    {
        $oConfig = Registry::getConfig();
        $sCustomTheme = $oConfig->getConfigParam("sCustomTheme");
        $sAppDir = $oConfig->getAppDir();
        $sLang = Registry::getLang()->getLanguageAbbr($language);
        $aLangFiles = [];

        if ($sCustomTheme) {
            $customThemePath = $sAppDir . 'views/' . $sCustomTheme;
            $aLangFiles = array_merge($aLangFiles, $this->getThemeLanguageFiles($customThemePath, $sLang));
        }

        return $aLangFiles;
    }

    /**
     * Returns array with paths where admin language files are stored
     *
     * @param int $activeLanguage The active language
     *
     * @return array
     * @deprecated underscore prefix violates PSR12, will be renamed to "getAdminLangFilesPathArray" in next major
     */
    protected function _getAdminLangFilesPathArray($activeLanguage) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        $config = Registry::getConfig();
        $langFiles = [];

        $appDirectory = $config->getAppDir();
        $language = Registry::getLang()->getLanguageAbbr($activeLanguage);

        // admin lang files
        $adminThemeName = $this->getContainer()->get(AdminThemeBridgeInterface::class)->getActiveTheme();
        $adminPath = $appDirectory .
            'views' . DIRECTORY_SEPARATOR .
            $adminThemeName . DIRECTORY_SEPARATOR .
            $language;

        $langFiles[] = $adminPath . DIRECTORY_SEPARATOR . 'lang.php';
        $langFiles[] = $appDirectory .
            'translations' . DIRECTORY_SEPARATOR .
            $language . DIRECTORY_SEPARATOR .
            'translit_lang.php';
        $langFiles = $this->_appendLangFile($langFiles, $adminPath);

        // themes options lang files
        $themePath = $appDirectory . 'views/*/' . $language;
        $langFiles = $this->_appendLangFile($langFiles, $themePath, "options");

        $themePath = $appDirectory . 'views/*/translations/' . $language;
        $langFiles = $this->_appendLangFile($langFiles, $themePath, "options");

        // module language files
        $langFiles = $this->appendModuleLangFilesForAdminArea($langFiles, $language);


        // custom language files
        $langFiles = $this->_appendCustomLangFiles($langFiles, $language, true);

        return count($langFiles) ? $langFiles : false;
    }

    /**
     * Appends lang or options files if exists, except custom lang files
     *
     * @param array  $aLangFiles   existing language files
     * @param string $sFullPath    path to language files to append
     * @param string $sFilePattern file pattern to search for, default is "lang"
     *
     * @return array
     * @deprecated underscore prefix violates PSR12, will be renamed to "appendLangFile" in next major
     */
    protected function _appendLangFile($aLangFiles, $sFullPath, $sFilePattern = "lang") // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        $aFiles = glob($sFullPath . "/*_{$sFilePattern}.php");
        if (is_array($aFiles) && count($aFiles)) {
            foreach ($aFiles as $sFile) {
                if (!strpos($sFile, 'cust_lang.php')) {
                    $aLangFiles[] = $sFile;
                }
            }
        }

        return $aLangFiles;
    }

    /**
     * Appends Custom language files cust_lang.php
     *
     * @param array  $languageFiles existing language files
     * @param string $language      language abbreviation
     * @param bool   $forAdmin      add files for admin
     *
     * @return array
     * @deprecated underscore prefix violates PSR12, will be renamed to "appendCustomLangFiles" in next major
     */
    protected function _appendCustomLangFiles($languageFiles, $language, $forAdmin = false) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        if ($forAdmin) {
            $adminThemeName = $this->getContainer()->get(AdminThemeBridgeInterface::class)->getActiveTheme();
            $languageFiles[] = $this->getCustomFilePath($language, $adminThemeName);
        } else {
            $config = Registry::getConfig();
            if ($config->getConfigParam("sTheme")) {
                $languageFiles[] = $this->getCustomFilePath($language, $config->getConfigParam("sTheme"));
            }
            if ($config->getConfigParam("sCustomTheme")) {
                $languageFiles[] = $this->getCustomFilePath($language, $config->getConfigParam("sCustomTheme"));
            }
        }

        return $languageFiles;
    }

    /**
     * @param int    $language  The language index
     * @param string $themeName The name of the theme
     *
     * @return string
     */
    private function getCustomFilePath($language, $themeName)
    {
        $config = Registry::getConfig();
        return $config->getAppDir() .
            'views' . DIRECTORY_SEPARATOR .
            $themeName . DIRECTORY_SEPARATOR  .
            $language . DIRECTORY_SEPARATOR .
            'cust_lang.php';
    }

    /**
     * @param array  $langFiles
     * @param string $lang
     *
     * @return array
     */
    private function appendModuleLangFilesForAdminArea(array $langFiles, string $lang): array
    {
        $moduleLangFiles = $this->getContainer()
            ->get(AdminAreaModuleTranslationFileLocatorBridgeInterface::class)
            ->locate($lang);

        return array_merge($langFiles, $moduleLangFiles);
    }

    /**
     * @param array  $langFiles
     * @param string $lang
     *
     * @return array
     */
    private function appendModuleLangFilesForFrontend(array $langFiles, string $lang): array
    {
        $moduleLangFiles = $this->getContainer()
            ->get(FrontendModuleTranslationFileLocatorBridgeInterface::class)
            ->locate($lang);

        return array_merge($langFiles, $moduleLangFiles);
    }

    /**
     * Returns language cache file name
     *
     * @param bool  $blAdmin    admin or not
     * @param int   $iLang      current language id
     * @param array $aLangFiles language files to load [optional]
     *
     * @return string
     * @deprecated underscore prefix violates PSR12, will be renamed to "getLangFileCacheName" in next major
     */
    protected function _getLangFileCacheName($blAdmin, $iLang, $aLangFiles = null) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        $myConfig = Registry::getConfig();
        $sLangFilesIdent = '_default';
        if (is_array($aLangFiles) && $aLangFiles) {
            $sLangFilesIdent = '_' . md5(implode('+', $aLangFiles));
        }

        return "langcache_" . ((int) $blAdmin) . "_{$iLang}_" . $myConfig->getShopId() . "_" . $myConfig->getConfigParam('sTheme') . $sLangFilesIdent;
    }

    /**
     * Returns language cache array
     *
     * @param bool  $blAdmin    admin or not [optional]
     * @param int   $iLang      current language id [optional]
     * @param array $aLangFiles language files to load [optional]
     *
     * @return array
     * @deprecated underscore prefix violates PSR12, will be renamed to "getLanguageFileData" in next major
     */
    protected function _getLanguageFileData($blAdmin = false, $iLang = 0, $aLangFiles = null) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        $myUtils = Registry::getUtils();

        $sCacheName = $this->_getLangFileCacheName($blAdmin, $iLang, $aLangFiles);
        $aLangCache = $myUtils->getLangCache($sCacheName);
        if (!$aLangCache && $aLangFiles === null) {
            if ($blAdmin) {
                $aLangFiles = $this->_getAdminLangFilesPathArray($iLang);
            } else {
                $aLangFiles = $this->_getLangFilesPathArray($iLang);
            }
        }
        if (!$aLangCache && $aLangFiles) {
            $aLangCache = [];
            $sBaseCharset = $this->getTranslationsExpectedEncoding();
            $aLang = [];
            $aLangSeoReplaceChars = [];
            foreach ($aLangFiles as $sLangFile) {
                if (file_exists($sLangFile) && is_readable($sLangFile)) {
                    //$aSeoReplaceChars null indicates that there is no setting made
                    $aSeoReplaceChars = null;
                    include $sLangFile;

                    $aLang = array_merge(['charset' => 'UTF-8'], $aLang);

                    if (isset($aSeoReplaceChars) && is_array($aSeoReplaceChars)) {
                        $aLangSeoReplaceChars = array_merge($aLangSeoReplaceChars, $aSeoReplaceChars);
                    }

                    $aLangCache = array_merge($aLangCache, $aLang);
                }
            }

            $aLangCache['charset'] = $sBaseCharset;

            // special character replacement list
            $aLangCache['_aSeoReplaceChars'] = $aLangSeoReplaceChars;

            //save to cache
            $myUtils->setLangCache($sCacheName, $aLangCache);
        }

        return $aLangCache;
    }

    /**
     * Returns language map array
     *
     * @param int  $language language index
     * @param bool $isAdmin  admin mode [default NULL]
     *
     * @return array
     * @deprecated underscore prefix violates PSR12, will be renamed to "getLanguageMap" in next major
     */
    protected function _getLanguageMap($language, $isAdmin = null) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        $isAdmin = isset($isAdmin) ? $isAdmin : $this->isAdmin();
        $key = $language . ((int)$isAdmin);
        if (!isset($this->_aLangMap[$key])) {
            $this->_aLangMap[$key] = [];
            $config = Registry::getConfig();

            $mapFile = '';
            $theme = $this->getRealThemeName($config->getConfigParam("sTheme"), $isAdmin);
            $customTheme = $this->getRealThemeName($config->getConfigParam("sCustomTheme"), $isAdmin);

            $languageAbbr = Registry::getLang()->getLanguageAbbr($language);
            $possibleMapFileLocations = array_merge(
                $this->getThemeLanguageFileMapLocations($customTheme, $languageAbbr),
                $this->getThemeLanguageFileMapLocations($theme, $languageAbbr)
            );

            foreach ($possibleMapFileLocations as $tmpMapFileLocation) {
                $possibleMapFile = $tmpMapFileLocation . DIRECTORY_SEPARATOR . 'map.php';
                if (file_exists($possibleMapFile) && is_readable($possibleMapFile)) {
                    $mapFile = $possibleMapFile;
                    break;
                }
            }

            if ($mapFile) {
                $aMap = [];
                include $mapFile;
                $this->_aLangMap[$key] = $aMap;
            }
        }

        return $this->_aLangMap[$key];
    }

    /**
     * @param string $theme The name of the theme
     * @param string $languageAbbreviation Language abbreviation
     *
     * @return string[]
     */
    private function getThemeLanguageFileMapLocations($theme, $languageAbbreviation)
    {
        $config = \OxidEsales\Eshop\Core\Registry::getConfig();
        $themeDirectory = $config->getAppDir() . DIRECTORY_SEPARATOR
            . 'views' . DIRECTORY_SEPARATOR
            . $theme . DIRECTORY_SEPARATOR;

        return [
            $themeDirectory . $languageAbbreviation, // for backwards compatibility
            $themeDirectory . 'translations' . DIRECTORY_SEPARATOR . $languageAbbreviation,
            $themeDirectory . 'translations'
        ];
    }

    /**
     * @param bool   $isAdmin   The admin mode [default NULL]
     * @param string $themeName The name of the theme
     *
     * @return string
     */
    private function getRealThemeName($themeName, $isAdmin = null)
    {
        $adminTheme = $this->getContainer()->get(AdminThemeBridgeInterface::class)->getActiveTheme();
        return ($isAdmin ? $adminTheme : $themeName);
    }

    /**
     * Returns current language cache language id
     *
     * @param bool $blAdmin admin mode
     * @param int  $iLang   language id [optional]
     *
     * @return int
     * @deprecated underscore prefix violates PSR12, will be renamed to "getCacheLanguageId" in next major
     */
    protected function _getCacheLanguageId($blAdmin, $iLang = null) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        $iLang = ($iLang === null && $blAdmin) ? $this->getTplLanguage() : $iLang;
        if (!isset($iLang)) {
            $iLang = $this->getBaseLanguage();
            if (!isset($iLang)) {
                $iLang = 0;
            }
        }

        return (int) $iLang;
    }

    /**
     * get language array from lang translation file
     *
     * @param int   $iLang      optional language
     * @param bool  $blAdmin    admin mode switch
     * @param array $aLangFiles language files to load [optional]
     *
     * @return array
     * @deprecated underscore prefix violates PSR12, will be renamed to "getLangTranslationArray" in next major
     */
    protected function _getLangTranslationArray($iLang = null, $blAdmin = null, $aLangFiles = null) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        startProfile("_getLangTranslationArray");

        $blAdmin = isset($blAdmin) ? $blAdmin : $this->isAdmin();
        $iLang = $this->_getCacheLanguageId($blAdmin, $iLang);
        $sCacheName = $this->_getLangFileCacheName($blAdmin, $iLang, $aLangFiles);

        if (!isset($this->_aLangCache[$sCacheName])) {
            $this->_aLangCache[$sCacheName] = [];
        }
        if (!isset($this->_aLangCache[$sCacheName][$iLang])) {
            // loading main lang files data
            $this->_aLangCache[$sCacheName][$iLang] = $this->_getLanguageFileData($blAdmin, $iLang, $aLangFiles);
        }

        stopProfile("_getLangTranslationArray");

        // if language array exists ..
        return (isset($this->_aLangCache[$sCacheName][$iLang]) ? $this->_aLangCache[$sCacheName][$iLang] : []);
    }

    /**
     * Language sorting callback function
     *
     * @param object $a1 first value to check
     * @param object $a2 second value to check
     *
     * @return bool
     * @deprecated underscore prefix violates PSR12, will be renamed to "sortLanguagesCallback" in next major
     */
    protected function _sortLanguagesCallback($a1, $a2) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return ($a1->sort > $a2->sort);
    }

    /**
     * Returns language id param name
     *
     * @return string
     */
    public function getName()
    {
        return $this->_sName;
    }

    /**
     * Returns form hidden language parameter
     *
     * @return string
     */
    public function getFormLang()
    {
        if (!$this->isAdmin()) {
            return "<input type=\"hidden\" name=\"" . $this->getName() . "\" value=\"" . $this->getBaseLanguage() . "\" />";
        }
    }

    /**
     * Returns url language parameter
     *
     * @param int $iLang language id [optional]
     *
     * @return string
     */
    public function getUrlLang($iLang = null)
    {
        if (!$this->isAdmin()) {
            $iLang = isset($iLang) ? $iLang : $this->getBaseLanguage();
            return $this->getName() . "=" . $iLang;
        }
    }

    /**
     * Is needed appends url with language parameter
     * Direct usage of this method to retrieve end url result is discouraged - instead
     * see \OxidEsales\Eshop\Core\UtilsUrl::processUrl
     *
     * @param string $sUrl  url to process
     * @param int    $iLang language id [optional]
     *
     * @see \OxidEsales\Eshop\Core\UtilsUrl::processUrl
     *
     * @return string
     */
    public function processUrl($sUrl, $iLang = null)
    {
        $iLang = isset($iLang) ? $iLang : $this->getBaseLanguage();
        $iDefaultLang = (int) Registry::getConfig()->getConfigParam('sDefaultLang');
        $iBrowserLanguage = (int)$this->detectLanguageByBrowser();
        $oStr = Str::getStr();

        if (!$this->isAdmin()) {
            $sParam = $this->getUrlLang($iLang);
            if (
                !$oStr->preg_match('/(\?|&(amp;)?)lang=[0-9]+/', $sUrl) &&
                ($iLang != $iDefaultLang || $iDefaultLang != $iBrowserLanguage)
            ) {
                if ($sUrl) {
                    if ($oStr->strpos($sUrl, '?') === false) {
                        $sUrl .= "?";
                    } elseif (!$oStr->preg_match('/(\?|&(amp;)?)$/', $sUrl)) {
                        $sUrl .= "&amp;";
                    }
                }
                $sUrl .= $sParam . "&amp;";
            } else {
                $sUrl = $oStr->preg_replace('/(\?|&(amp;)?)lang=[0-9]+/', '\1' . $sParam, $sUrl);
            }
        }

        return $sUrl;
    }

    /**
     * Detect language by user browser settings. Returns language ID if
     * detected, otherwise returns null.
     *
     * @return int
     */
    public function detectLanguageByBrowser()
    {
        $sBrowserLanguage = $this->_getBrowserLanguage();

        if (!is_null($sBrowserLanguage)) {
            $aLanguages = $this->getLanguageArray(null, true);
            foreach ($aLanguages as $oLang) {
                if ($oLang->abbr == $sBrowserLanguage) {
                    return $oLang->id;
                }
            }
        }
    }

    /** @return array|string[] */
    public function getMultiLangTables()
    {
        $tables = [
            'oxactions',
            'oxartextends',
            'oxarticles',
            'oxattribute',
            'oxcategories',
            'oxcontents',
            'oxcountry',
            'oxdelivery',
            'oxdeliveryset',
            'oxdiscount',
            'oxgroups',
            'oxlinks',
            'oxmanufacturers',
            'oxmediaurls',
            'oxobject2attribute',
            'oxpayments',
            'oxselectlist',
            'oxshops',
            'oxstates',
            'oxvendor',
            'oxwrapping',
        ];
        $configTables = Registry::getConfig()->getConfigParam('aMultiLangTables');
        if (\is_array($configTables)) {
            $tables = \array_merge($tables, $configTables);
        }
        return $tables;
    }

    /**
     * Get SEO spec. chars replacement list for current language
     *
     * @param int $iLang language ID
     *
     * @return null
     */
    public function getSeoReplaceChars($iLang)
    {
        // get language replace chars
        $aSeoReplaceChars = $this->translateString('_aSeoReplaceChars', $iLang);
        if (!is_array($aSeoReplaceChars)) {
            $aSeoReplaceChars = [];
        }

        return $aSeoReplaceChars;
    }

    /**
     * Gets browser language.
     *
     * @return string
     * @deprecated underscore prefix violates PSR12, will be renamed to "getBrowserLanguage" in next major
     */
    protected function _getBrowserLanguage() // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && $_SERVER['HTTP_ACCEPT_LANGUAGE']) {
            return strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2));
        }
    }

    /**
     * Returns available language IDs (abbreviations) for all sub shops
     *
     * @return array
     */
    public function getAllShopLanguageIds()
    {
        return $this->_getLanguageIdsFromDatabase();
    }

    /**
     * Get current Shop language ids.
     *
     * @param int $iShopId shop id
     *
     * @return array
     */
    public function getLanguageIds($iShopId = 0)
    {
        if (empty($iShopId) || $iShopId == Registry::getConfig()->getShopId()) {
            $aLanguages = $this->getActiveShopLanguageIds();
        } else {
            $aLanguages = $this->_getLanguageIdsFromDatabase($iShopId);
        }

        return $aLanguages;
    }

    /**
     * Returns available language IDs (abbreviations)
     *
     * @return array
     */
    public function getActiveShopLanguageIds()
    {
        $oConfig = Registry::getConfig();

        //if exists language parameters array, extract lang id's from there
        $aLangParams = $oConfig->getConfigParam('aLanguageParams');
        if (is_array($aLangParams)) {
            $aIds = $this->_getLanguageIdsFromLanguageParamsArray($aLangParams);
        } else {
            $languages = $oConfig->getConfigParam('aLanguages');
            $aIds = $this->_getLanguageIdsFromLanguagesArray(
                is_array($languages) ? $languages : []
            );
        }

        return $aIds;
    }

    /**
     * Gets language Ids for given shopId or for all subshops
     *
     * @param null $shopId
     *
     * @return array
     * @deprecated underscore prefix violates PSR12, will be renamed to "getLanguageIdsFromDatabase" in next major
     */
    protected function _getLanguageIdsFromDatabase($shopId = null) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return $this->getLanguageIds();
    }

    /**
     * Returns list of all language codes taken from config values of given 'aLanguages' (for all subshops)
     *
     * @param string $sLanguageParameterName language config parameter name
     * @param int    $iShopId                shop id
     *
     * @return array
     * @deprecated underscore prefix violates PSR12, will be renamed to "getConfigLanguageValues" in next major
     */
    protected function _getConfigLanguageValues($sLanguageParameterName, $iShopId = null) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        $aConfigDecodedValues = [];
        $aConfigValues = $this->_selectLanguageParamValues($sLanguageParameterName, $iShopId);

        foreach ($aConfigValues as $sConfigValue) {
            $aConfigLanguages = unserialize($sConfigValue['oxvarvalue']);

            $aLanguages = [];
            if ($sLanguageParameterName == 'aLanguageParams') {
                $aLanguages = $this->_getLanguageIdsFromLanguageParamsArray($aConfigLanguages);
            } elseif ($sLanguageParameterName == 'aLanguages') {
                $aLanguages = $this->_getLanguageIdsFromLanguagesArray($aConfigLanguages);
            }

            $aConfigDecodedValues = array_unique(array_merge($aConfigDecodedValues, $aLanguages));
        }

        return $aConfigDecodedValues;
    }

    /**
     * Returns array of all config values of given paramName
     *
     * @param string      $sParamName Parameter name
     * @param string|null $sShopId    Shop id
     *
     * @return array
     * @deprecated underscore prefix violates PSR12, will be renamed to "selectLanguageParamValues" in next major
     */
    protected function _selectLanguageParamValues($sParamName, $sShopId = null) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        $oDb = \OxidEsales\Eshop\Core\DatabaseProvider::getDb(\OxidEsales\Eshop\Core\DatabaseProvider::FETCH_MODE_ASSOC);

        $params = [
            ':oxvarname' => $sParamName
        ];
        $sQuery = "
            select oxvarvalue
            from oxconfig
            where oxvarname = :oxvarname";

        if (!empty($sShopId)) {
            $sQuery .= " and oxshopid = :oxshopid limit 1";
            $params[':oxshopid'] = $sShopId;
        }

        return $oDb->getAll($sQuery, $params);
    }

    /**
     * gets language code array from aLanguageParams array
     *
     * @param array $aLanguageParams Language parameters
     *
     * @return array
     * @deprecated underscore prefix violates PSR12, will be renamed to "getLanguageIdsFromLanguageParamsArray" in next major
     */
    protected function _getLanguageIdsFromLanguageParamsArray($aLanguageParams) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        $aLanguages = [];
        foreach ($aLanguageParams as $sAbbr => $aValue) {
            $iBaseId = (int) $aValue['baseId'];
            $aLanguages[$iBaseId] = $sAbbr;
        }

        return $aLanguages;
    }

    /**
     * gets language code array from aLanguages array
     *
     * @param array $aLanguages Languages
     *
     * @return array
     * @deprecated underscore prefix violates PSR12, will be renamed to "getLanguageIdsFromLanguagesArray" in next major
     */
    protected function _getLanguageIdsFromLanguagesArray($aLanguages) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return is_array($aLanguages) ? array_keys($aLanguages) : [];
    }
}

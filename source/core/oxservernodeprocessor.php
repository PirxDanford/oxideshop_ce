<?php
/**
 * This file is part of OXID eShop Community Edition.
 *
 * OXID eShop Community Edition is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OXID eShop Community Edition is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OXID eShop Community Edition.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @link      http://www.oxid-esales.com
 * @copyright (C) OXID eSales AG 2003-2014
 * @version   OXID eShop CE
 */

/**
 * oxServerNodeProcessor
 *
 * @internal Do not make a module extension for this class.
 * @see http://wiki.oxidforge.org/Tutorials/Core_OXID_eShop_classes:_must_not_be_extended
 */
class oxServerNodeProcessor
{
    /** @var oxServerNodesManager */
    private $_oServerNodesManager;

    /** @var oxServerNodeChecker */
    private $_oServerNodeChecker;

    /**
     * @return oxServerNodeChecker
     */
    protected function _getServerNodeChecker()
    {
        return $this->_oServerNodeChecker;
    }

    /**
     * @return oxServerNodesManager
     */
    protected function _getServerNodesManager()
    {
        return $this->_oServerNodesManager;
    }

    /**
     * @param oxServerNodesManager $oServerNodesManager
     * @param oxServerNodeChecker $oServerNodeChecker
     */
    public function __construct(oxServerNodesManager $oServerNodesManager = null, oxServerNodeChecker $oServerNodeChecker = null)
    {
        if (is_null($oServerNodesManager)) {
            /** @var oxServerNodesManager $oServerNodesManager */
            $oServerNodesManager = oxNew('oxServerNodesManager');
        }
        $this->_oServerNodesManager = $oServerNodesManager;

        if (is_null($oServerNodeChecker)) {
            /** @var oxServerNodeChecker $oServerNodeChecker */
            $oServerNodeChecker = oxNew('oxServerNodeChecker');
        }
        $this->_oServerNodeChecker = $oServerNodeChecker;
    }

    /**
     * Renew frontend server node information if it is outdated or it does not exist.
     */
    public function process()
    {
        $sServerNodeId = $this->_getServerNodeId();
        $oNodesManager = $this->_getServerNodesManager();
        $oNode = $oNodesManager->getNode($sIP);

        $oNodeChecker = $this->_getServerNodeChecker();
        if (!$oNodeChecker->check($oNode)) {
            $this->_updateNodeInformation($oNode);
            $oNodesManager->saveNode($oNode);
        }
    }

    /**
     * @todo return real IP address.
     *
     * @return string
     */
    private function _getServerNodeId()
    {
        return md5(php_uname());
    }

    private function _updateNodeInformation($oNode)
    {
        $sServerNodeId = $this->_getServerNodeId();
        $oUtilsDate = oxRegistry::get('oxUtilsDate');

        $oNode->setIp($sServerNodeId);
        $oNode->setTimestamp($oUtilsDate->getTime());
        $oNode->setId('');
        $oNode->setLastFrontendUsage('');
        $oNode->setLastAdminUsage('');
    }
}
<?php
/**
 * Shopware 4.0
 * Copyright © 2013 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Bepado\Components\Gateway\ProductTranslationsGateway;

use Shopware\Bepado\Components\Gateway\ProductTranslationsGateway;

class PdoProductTranslationsGateway implements ProductTranslationsGateway
{
    private $db;

    public function __construct(\Enlight_Components_Db_Adapter_Pdo_Mysql $db)
    {
        $this->db = $db;
    }

    /**
     * {@inheritdoc}
     */
    public function getSingleTranslation($articleId, $languageId)
    {
        $sql = 'SELECT objectdata
                FROM s_core_translations
                WHERE objecttype = ? AND objectkey = ? AND objectlanguage = ?
        ';

        $translation = $this->db->executeQuery(
            $sql,
            array('article', $articleId, $languageId)
        )->fetchColumn();

        if ($translation === false) {
            return null;
        }

        $translation = unserialize($translation);

        return array(
            'title' => $translation['txtArtikel'] ?: '',
            'shortDescription' => $translation['txtshortdescription'] ?: '',
            'longDescription' => $translation['txtlangbeschreibung'] ?: '',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getTranslations($articleId, $languageIds)
    {
        if (is_array($languageIds) === false || count($languageIds) === 0) {
            return array();
        }

        $inQuery = implode(',', $languageIds);
        $sql = "SELECT objectdata, objectlanguage
                FROM s_core_translations
                WHERE objecttype = ? AND objectkey = ? AND objectlanguage IN ($inQuery)
        ";

        $translations = $this->db->executeQuery(
            $sql,
            array('article', $articleId)
        )->fetchAll();

        $result = array();
        foreach ($translations as $translation) {
            $languageId = $translation['objectlanguage'];
            $data = unserialize($translation['objectdata']);
            $result[$languageId] = array(
                'title' => $data['txtArtikel'] ?: '',
                'shortDescription' => $data['txtshortdescription'] ?: '',
                'longDescription' => $data['txtlangbeschreibung'] ?: '',
            );
        }

        return $result;
    }
} 
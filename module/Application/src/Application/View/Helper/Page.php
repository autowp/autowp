<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Pages;
use Page_Language;

use Zend_Db_Table;
use Zend_Db_Table_Row;
use Zend_Locale;
use Zend_Registry;

class Page extends AbstractHelper
{
    /**
     * @var Zend_Db_Table
     */
    private $pageTable;
    /**
     * @var Zend_Db_Table
     */
    private $pageLanguageTable;
    /**
     * @var Page_Row
     */
    private $doc;
    /**
     * @var int
     */
    private $language = 'en';

    private $parentsCache = [];

    private $pages = [];

    private $langPages = [];

    public function __construct()
    {
        $this->pageTable = new Pages();
        $this->pageLanguageTable = new Page_Language();

        if (Zend_Registry::isRegistered('Zend_Locale')) {
            $locale = new Zend_Locale(Zend_Registry::get('Zend_Locale'));
            $this->language = $locale->getLanguage();
        }
    }

    public function __invoke($value)
    {
        if ($value) {
            $doc = null;

            if ($value instanceof Zend_Db_Table_Row)
                $doc = $value;
            elseif (is_numeric($value)) {
                $doc = $this->getPageById($value);
            }

            $this->doc = $doc;
        }

        return $this;
    }

    public function __get($name)
    {
        if (!$this->doc) {
            return '';
        }
        switch ($name) {
            /*case 'html':
                return $this->doc->getHtml(array(
                    'language' => $this->language
                ));*/

            case 'name':
            case 'title':
            case 'breadcrumbs':
                $field = $name;

                $id = $this->doc->id;
                $lang = $this->language;

                if (!isset($this->langPages[$id][$lang])) {
                    $this->langPages[$id][$lang] = $this->pageLanguageTable->fetchRow(array(
                        'page_id = ?'  => $id,
                        'language = ?' => $lang
                    ));
                }

                $langDoc = $this->langPages[$id][$lang];

                if ($langDoc && $langDoc[$field]) {
                    return $langDoc[$field];
                }
                return $this->doc[$field];

            case 'onPath':
                return $this->isParentOrSelf($this->_currentPage, $this->doc);
                break;
        }

        return '';
    }

    private function getPageById($id)
    {
        if (isset($this->pages[$id])) {
            return $this->pages[$id];
        }

        return $this->pages[$id] = $this->pageTable->find($id)->current();
    }

    private function isParentOrSelf($child, $parent)
    {
        if (!$parent || !$child) {
            return false;
        }

        if ($parent->id == $child->id) {
            return true;
        }

        if ($parent->id == $child->parent_id) {
            return true;
        }

        if (isset($this->parentsCache[$child->id][$parent->id])) {
            return $this->parentsCache[$child->id][$parent->id];
        }

        $cParent = $child->parent_id ? $this->getPageById($child->parent_id) : false;
        $result = $this->isParentOrSelf($cParent, $parent);

        $this->parentsCache[$child->id][$parent->id] = $result;

        return $result;
    }

}

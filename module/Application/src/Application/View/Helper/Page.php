<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Pages;
use Page_Language;

use Zend_Db_Table;
use Zend_Locale;
use Zend_Registry;

class Page extends AbstractHelper
{
    /**
     * @var Zend_Db_Table
     */
    protected $_pageTable;
    /**
     * @var Zend_Db_Table
     */
    protected $_pageLanguageTable;
    /**
     * @var Page_Row
     */
    protected $_doc;
    /**
     * @var int
     */
    protected $_language = 'en';

    protected $_parentsCache = array();

    protected $_pages = array();

    protected $_langPages = array();

    public function __construct()
    {
        $this->_pageTable = new Pages();
        $this->_pageLanguageTable = new Page_Language();

        if (Zend_Registry::isRegistered('Zend_Locale')) {
            $locale = new Zend_Locale(Zend_Registry::get('Zend_Locale'));
            $this->_language = $locale->getLanguage();
        }
    }

    public function page($value)
    {
        if ($value) {
            $doc = null;

            if ($value instanceof Zend_Db_Table_Row)
                $doc = $value;
            elseif (is_numeric($value)) {
                $doc = $this->_getPageById($value);
            }

            $this->_doc = $doc;
        }

        return $this;
    }

    public function __get($name)
    {
        if (!$this->_doc) {
            return '';
        }
        switch ($name) {
            /*case 'html':
                return $this->_doc->getHtml(array(
                    'language' => $this->_language
                ));*/

            case 'name':
            case 'title':
            case 'breadcrumbs':
                $field = $name;

                $id = $this->_doc->id;
                $lang = $this->_language;

                if (!isset($this->_langPages[$id][$lang])) {
                    $this->_langPages[$id][$lang] = $this->_pageLanguageTable->fetchRow(array(
                        'page_id = ?'  => $id,
                        'language = ?' => $lang
                    ));
                }

                $langDoc = $this->_langPages[$id][$lang];

                if ($langDoc && $langDoc[$field]) {
                    return $langDoc[$field];
                }
                return $this->_doc[$field];

            case 'onPath':
                return $this->_isParentOrSelf($this->_currentPage, $this->_doc);
                break;
        }

        return '';
    }

    protected function _getPageById($id)
    {
        if (isset($this->_pages[$id])) {
            return $this->_pages[$id];
        }

        return $this->_pages[$id] = $this->_pageTable->find($id)->current();
    }

    protected function _isParentOrSelf($child, $parent)
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

        if (isset($this->_parentsCache[$child->id][$parent->id])) {
            return $this->_parentsCache[$child->id][$parent->id];
        }

        $cParent = $child->parent_id ? $this->_getPageById($child->parent_id) : false;
        $result = $this->_isParentOrSelf($cParent, $parent);

        $this->_parentsCache[$child->id][$parent->id] = $result;

        return $result;
    }

}

<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Pages;

use Zend_Db_Table;
use Zend_Db_Table_Row;

class Page extends AbstractHelper
{
    /**
     * @var Zend_Db_Table
     */
    private $pageTable;

    /**
     * @var Page_Row
     */
    private $doc;

    /**
     * @var array
     */
    private $parentsCache = [];

    /**
     * @var array
     */
    private $pages = [];

    public function __construct()
    {
        $this->pageTable = new Pages();
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
            case 'name':
            case 'title':
            case 'breadcrumbs':
                $key = 'page/' . $this->doc->id. '/' . $name;

                $result = $this->view->translate($key);
                if (!$result || $result == $key) {
                    $result = $this->view->translate($key, null, 'en');
                }

                if ((!$result || $result == $key) && ($name != 'name')) {
                    $key = 'page/' . $this->doc->id. '/name';

                    $result = $this->view->translate($key);
                    if (!$result || $result == $key) {
                        $result = $this->view->translate($key, null, 'en');
                    }
                }

                return $result;

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

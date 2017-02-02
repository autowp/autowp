<?php

namespace Application\View\Helper;

use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\TableGateway;
use Zend\View\Helper\AbstractHelper;

class Page extends AbstractHelper
{
    /**
     * @var TableGateway
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

    public function __construct(Adapter $adapter)
    {
        $this->pageTable = new TableGateway('pages', $adapter);
    }

    public function __invoke($value)
    {
        if ($value) {
            $doc = null;

            if (($value instanceof \ArrayObject) || is_array($value)) {
                $doc = $value;
            } elseif (is_numeric($value)) {
                $doc = $this->getPageById($value);
            }

            $this->doc = $doc;
        }

        return $this;
    }

    public function __get($name)
    {
        if (! $this->doc) {
            return '';
        }
        switch ($name) {
            case 'name':
            case 'title':
            case 'breadcrumbs':
                $key = 'page/' . $this->doc['id']. '/' . $name;

                $result = $this->view->translate($key);
                if (! $result || $result == $key) {
                    $result = $this->view->translate($key, null, 'en');
                }

                if ((! $result || $result == $key) && ($name != 'name')) {
                    $key = 'page/' . $this->doc['id']. '/name';

                    $result = $this->view->translate($key);
                    if (! $result || $result == $key) {
                        $result = $this->view->translate($key, null, 'en');
                    }
                }

                return $result;
        }

        return '';
    }

    private function getPageById($id)
    {
        $id = (int)$id;
        if (isset($this->pages[$id])) {
            return $this->pages[$id];
        }

        $row = $this->pageTable->select([
            'id' => (int)$id
        ])->current();

        $this->pages[$id] = $row;

        return $row;
    }

    private function isParentOrSelf($child, $parent)
    {
        if (! $parent || ! $child) {
            return false;
        }

        $childId = $child['id'];
        $parentId = $parent['id'];

        if ($parentId == $childId) {
            return true;
        }

        if ($parentId == $child['parent_id']) {
            return true;
        }

        if (isset($this->parentsCache[$childId][$parentId])) {
            return $this->parentsCache[$childId][$parentId];
        }

        $cParent = $child['parent_id'] ? $this->getPageById($child['parent_id']) : false;
        $result = $this->isParentOrSelf($cParent, $parent);

        $this->parentsCache[$childId][$parentId] = $result;

        return $result;
    }
}

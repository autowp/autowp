<?php

namespace Application\Model\Item\ListBuilder;

use Application\Model\DbTable;
use Application\Model\Item\ListBuilder;

use Zend_Db_Expr;

class Category extends ListBuilder
{
    /**
     * @var DbTable\Item\ParentTable
     */
    protected $itemParentTable;

    protected $currentItem;

    protected $category;

    /**
     * @var boolean
     */
    protected $isOther;

    protected $path;

    public function setItemParentTable(DbTable\Item\ParentTable $itemParentTable)
    {
        $this->itemParentTable = $itemParentTable;

        return $this;
    }

    public function setCurrentItem($currentItem)
    {
        $this->currentItem = $currentItem;

        return $this;
    }

    public function setCategory($category)
    {
        $this->category = $category;

        return $this;
    }

    public function setIsOther($isOther)
    {
        $this->isOther = $isOther;

        return $this;
    }

    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    public function getDetailsUrl(DbTable\Item\Row $item)
    {
        if ($item->item_type_id == DbTable\Item\Type::CATEGORY) {
            return $this->router->assemble([
                'action'           => 'category',
                'category_catname' => $item->catname
            ], [
                'name' => 'categories'
            ]);
        }

        $itemParentAdapter = $this->itemParentTable->getAdapter();
        $hasChilds = (bool)$itemParentAdapter->fetchOne(
            $itemParentAdapter->select()
                ->from($this->itemParentTable->info('name'), new Zend_Db_Expr('1'))
                ->where('parent_id = ?', $item->id)
        );

        if (! $hasChilds) {
            return null;
        }

        // found parent row
        if ($this->currentItem) {
            $itemParentRow = $this->itemParentTable->fetchRow([
                'item_id = ?'   => $item->id,
                'parent_id = ?' => $this->currentItem->id
            ]);
            if (! $itemParentRow) {
                return null;
            }

            $currentPath = array_merge($this->path, [
                $itemParentRow->catname
            ]);
        } else {
            $currentPath = [];
        }

        return $this->router->assemble([
            'action'           => 'category',
            'category_catname' => $this->category->catname,
            'other'            => $this->isOther,
            'path'             => $currentPath,
            'page'             => 1
        ], [
            'name' => 'categories'
        ]);
    }

    public function getPicturesUrl(DbTable\Item\Row $item)
    {
        if ($item->item_type_id == DbTable\Item\Type::CATEGORY) {
            return $this->router->assemble([
                'action'           => 'category-pictures',
                'category_catname' => $item->catname,
                'path'             => $this->path,
            ], [
                'name' => 'categories'
            ]);
        }

        // found parent row
        if ($this->currentItem) {
            $itemParentRow = $this->itemParentTable->fetchRow([
                'item_id = ?'   => $item->id,
                'parent_id = ?' => $this->currentItem->id
            ]);
            if (! $itemParentRow) {
                return null;
            }

            $currentPath = array_merge($this->path, [
                $itemParentRow->catname
            ]);
        } else {
            $currentPath = [];
        }

        return $this->router->assemble([
            'action'           => 'category-pictures',
            'category_catname' => $this->category->catname,
            'other'            => $this->isOther,
            'path'             => $currentPath,
            'page'             => 1
        ], [
            'name' => 'categories'
        ]);
    }

    public function getSpecificationsUrl(DbTable\Item\Row $item)
    {
        return null;
    }

    public function getPictureUrl(DbTable\Item\Row $item, array $picture)
    {
        if ($item->item_type_id == DbTable\Item\Type::CATEGORY) {
            return $this->router->assemble([
                'action'           => 'category-picture',
                'category_catname' => $item->catname,
                'picture_id'       => $picture['identity']
            ], [
                'name' => 'categories'
            ]);
        }

        // found parent row
        if ($this->currentItem) {
            $itemParentRow = $this->itemParentTable->fetchRow([
                'item_id = ?'   => $item->id,
                'parent_id = ?' => $this->currentItem->id
            ]);
            if (! $itemParentRow) {
                return null;
            }

            $currentPath = array_merge($this->path, [
                $itemParentRow->catname
            ]);
        } else {
            $currentPath = [];
        }

        return $this->router->assemble([
            'action'           => 'category-picture',
            'category_catname' => $this->category->catname,
            'other'            => $this->isOther,
            'path'             => $currentPath,
            'picture_id'       => $picture['identity']
        ], [
            'name' => 'categories'
        ]);
    }
}

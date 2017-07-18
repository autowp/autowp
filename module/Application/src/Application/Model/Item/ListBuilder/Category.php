<?php

namespace Application\Model\Item\ListBuilder;

use Application\Model\DbTable;
use Application\Model\Item\ListBuilder;
use Application\Model\ItemParent;

class Category extends ListBuilder
{
    protected $currentItem;

    protected $category;

    /**
     * @var boolean
     */
    protected $isOther;

    protected $path;

    /**
     * @var ItemParent
     */
    private $itemParent;

    public function setItemParent(ItemParent $model)
    {
        $this->itemParent = $model;

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

    public function getDetailsUrl($item)
    {
        if ($item['item_type_id'] == DbTable\Item\Type::CATEGORY) {
            return $this->router->assemble([
                'action'           => 'category',
                'category_catname' => $item['catname']
            ], [
                'name' => 'categories'
            ]);
        }

        $hasChilds = $this->itemParent->hasChildItems($item['id']);

        if (! $hasChilds) {
            return null;
        }

        // found parent row
        if ($this->currentItem) {
            $itemParentRow = $this->itemParent->getRow($this->currentItem->id, $item['id']);
            if (! $itemParentRow) {
                return null;
            }

            $currentPath = array_merge($this->path, [
                $itemParentRow['catname']
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

    public function getPicturesUrl($item)
    {
        if ($item['item_type_id'] == DbTable\Item\Type::CATEGORY) {
            return $this->router->assemble([
                'action'           => 'category-pictures',
                'category_catname' => $item['catname'],
                'path'             => $this->path,
            ], [
                'name' => 'categories'
            ]);
        }

        // found parent row
        if ($this->currentItem) {
            $itemParentRow = $this->itemParent->getRow($this->currentItem->id, $item['id']);
            if (! $itemParentRow) {
                return null;
            }

            $currentPath = array_merge($this->path, [
                $itemParentRow['catname']
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

    public function getSpecificationsUrl($item)
    {
        return null;
    }

    public function getPictureUrl($item, array $picture)
    {
        if ($item['item_type_id'] == DbTable\Item\Type::CATEGORY) {
            return $this->router->assemble([
                'action'           => 'category-picture',
                'category_catname' => $item['catname'],
                'picture_id'       => $picture['identity']
            ], [
                'name' => 'categories'
            ]);
        }

        // found parent row
        if ($this->currentItem) {
            $itemParentRow = $this->itemParent->getRow($this->currentItem->id, $item['id']);
            if (! $itemParentRow) {
                return null;
            }

            $currentPath = array_merge($this->path, [
                $itemParentRow['catname']
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

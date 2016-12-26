<?php

namespace Application\Model\Item\ListBuilder;

use Application\Model\DbTable;
use Application\Model\Item\ListBuilder;

use Zend_Db_Expr;

class Category extends ListBuilder
{
    /**
     * @var DbTable\Vehicle\ParentTable
     */
    protected $itemParentTable;
    
    protected $currentItem;
    
    protected $category;
    
    /**
     * @var boolean
     */
    protected $isOther;
    
    protected $path;
    
    public function setItemParentTable(DbTable\Vehicle\ParentTable $itemParentTable)
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
    
    public function getDetailsUrl(DbTable\Vehicle\Row $item)
    {
        if ($item->item_type_id == DbTable\Item\Type::CATEGORY) {
            return $this->router->assemble([
                'action'           => 'category',
                'category_catname' => $item->catname
            ], [
                'name' => 'categories'
            ]);
        }
        
        $carParentAdapter = $this->itemParentTable->getAdapter();
        $hasChilds = (bool)$carParentAdapter->fetchOne(
            $carParentAdapter->select()
                ->from($this->itemParentTable->info('name'), new Zend_Db_Expr('1'))
                ->where('parent_id = ?', $item->id)
        );
        
        if (! $hasChilds) {
            return null;
        }
        
        // found parent row
        if ($this->currentItem) {
            $carParentRow = $this->itemParentTable->fetchRow([
                'car_id = ?'    => $item->id,
                'parent_id = ?' => $this->currentItem->id
            ]);
            if (!$carParentRow) {
                return null;
            }
            
            $currentPath = array_merge($this->path, [
                $carParentRow->catname
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
    
    public function getPicturesUrl(DbTable\Vehicle\Row $item)
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
            $carParentRow = $this->itemParentTable->fetchRow([
                'car_id = ?'    => $item->id,
                'parent_id = ?' => $this->currentItem->id
            ]);
            if (!$carParentRow) {
                return null;
            }
        
            $currentPath = array_merge($this->path, [
                $carParentRow->catname
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
    
    public function getSpecificationsUrl(DbTable\Vehicle\Row $item)
    {
        return null;
    }
    
    public function getPictureUrl(DbTable\Vehicle\Row $item, array $picture)
    {
        if ($item->item_type_id == DbTable\Item\Type::CATEGORY) {
            return $this->router->assemble([
                'action'           => 'category-picture',
                'category_catname' => $item->catname,
                'picture_id'       => $picture['identity'] ? $picture['identity'] : $picture['id']
            ], [
                'name' => 'categories'
            ]);
        }
        
        // found parent row
        if ($this->currentItem) {
            $carParentRow = $this->itemParentTable->fetchRow([
                'car_id = ?'    => $item->id,
                'parent_id = ?' => $this->currentItem->id
            ]);
            if (! $carParentRow) {
                return null;
            }
        
            $currentPath = array_merge($this->path, [
                $carParentRow->catname
            ]);
        } else {
            $currentPath = [];
        }
        
        return $this->router->assemble([
            'action'           => 'category-picture',
            'category_catname' => $this->category->catname,
            'other'            => $this->isOther,
            'path'             => $currentPath,
            'picture_id'       => $picture['identity'] ? $picture['identity'] : $picture['id']
        ], [
            'name' => 'categories'
        ]);
    }
}
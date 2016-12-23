<?php

namespace Application\Model\Item\ListBuilder;

use Application\Model\DbTable;

use Exception;

class CatalogueItem extends Catalogue
{
    /**
     * @var string
     */
    protected $brandItemCatname;
    
    /**
     * @var array
     */
    protected $path;
    
    /**
     * @var int
     */
    protected $itemId;
    
    public function setBrandItemCatname($brandItemCatname)
    {
        $this->brandItemCatname = $brandItemCatname;
        
        return $this;
    }
    
    public function setPath(array $path)
    {
        $this->path = $path;
    
        return $this;
    }
    
    public function setItemId($itemId)
    {
        $this->itemId = $itemId;
    
        return $this;
    }
    
    public function getPicturesUrl(DbTable\Vehicle\Row $item)
    {
        return $this->router->assemble([
            'action'        => 'brand-item-pictures',
            'brand_catname' => $this->brand['catname'],
            'car_catname'   => $this->brandItemCatname,
            'path'          => $this->path,
            'exact'         => true
        ], [
            'name' => 'catalogue'
        ]);
    }
    
    public function getSpecificationsUrl(DbTable\Vehicle\Row $item)
    {
        $hasSpecs = $this->specsService->hasSpecs($item->id);
        
        if (! $hasSpecs) {
            return false;
        }
        
        return $this->router->assemble([
            'action'        => 'brand-item-specifications',
            'brand_catname' => $this->brand['catname'],
            'car_catname'   => $this->brandItemCatname,
            'path'          => $this->path,
        ], [
            'name' => 'catalogue'
        ]);
    }
    
    public function getTypeUrl(DbTable\Vehicle\Row $item, $type)
    {
        switch ($type) {
            case DbTable\Vehicle\ParentTable::TYPE_TUNING:
                $catname = 'tuning';
                break;
            case DbTable\Vehicle\ParentTable::TYPE_SPORT:
                $catname = 'sport';
                break;
            default:
                throw new Exception('Unexpected type');
                break;
        }
        
        $carParentRow = $this->itemParentTable->fetchRow([
            'car_id = ?'    => $item->id,
            'parent_id = ?' => $this->itemId
        ]);
        if ($carParentRow) {
            $currentPath = array_merge($this->path, [
                $carParentRow->catname
            ]);
        } else {
            $currentPath = $this->path;
        }
        
        return $this->router->assemble([
            'action'        => 'brand-item',
            'brand_catname' => $this->brand['catname'],
            'car_catname'   => $this->brandItemCatname,
            'path'          => $currentPath,
            'type'          => $catname,
            'page'          => null,
        ], [
            'name' => 'catalogue'
        ]);
    }
    
    public function getPictureUrl(DbTable\Vehicle\Row $item, array $picture)
    {
        return $this->router->assemble([
            'action'        => 'brand-item-picture',
            'brand_catname' => $this->brand['catname'],
            'car_catname'   => $this->brandItemCatname,
            'path'          => $this->path,
            'picture_id'    => $picture['identity'] ? $picture['identity'] : $picture['id']
        ], [
            'name' => 'catalogue'
        ]);
    }
}

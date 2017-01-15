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

    public function getPicturesUrl(DbTable\Item\Row $item)
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

    public function getSpecificationsUrl(DbTable\Item\Row $item)
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

    public function getTypeUrl(DbTable\Item\Row $item, $type)
    {
        switch ($type) {
            case DbTable\Item\ParentTable::TYPE_TUNING:
                $catname = 'tuning';
                break;
            case DbTable\Item\ParentTable::TYPE_SPORT:
                $catname = 'sport';
                break;
            default:
                throw new Exception('Unexpected type');
                break;
        }

        $itemParentRow = $this->itemParentTable->fetchRow([
            'item_id = ?'   => $item->id,
            'parent_id = ?' => $this->itemId
        ]);
        if ($itemParentRow) {
            $currentPath = array_merge($this->path, [
                $itemParentRow->catname
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

    public function getPictureUrl(DbTable\Item\Row $item, array $picture)
    {
        return $this->router->assemble([
            'action'        => 'brand-item-picture',
            'brand_catname' => $this->brand['catname'],
            'car_catname'   => $this->brandItemCatname,
            'path'          => $this->path,
            'picture_id'    => $picture['identity']
        ], [
            'name' => 'catalogue'
        ]);
    }
}

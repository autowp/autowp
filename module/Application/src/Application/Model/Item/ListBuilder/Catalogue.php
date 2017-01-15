<?php

namespace Application\Model\Item\ListBuilder;

use Application\Model\DbTable;
use Application\Model\Item\ListBuilder;
use Application\Service\SpecificationsService;

class Catalogue extends ListBuilder
{
    /**
     * @var DbTable\Item\ParentTable
     */
    protected $itemParentTable;

    /**
     * @var array
     */
    protected $brand;

    /**
     * @var SpecificationsService
     */
    protected $specsService;

    /**
     * @var array
     */
    private $pathsToBrand = [];

    public function setItemParentTable(DbTable\Item\ParentTable $itemParentTable)
    {
        $this->itemParentTable = $itemParentTable;

        return $this;
    }

    public function setBrand(array $brand)
    {
        $this->brand = $brand;

        return $this;
    }

    public function setSpecsService(SpecificationsService $specsService)
    {
        $this->specsService = $specsService;

        return $this;
    }

    public function isTypeUrlEnabled()
    {
        return true;
    }

    private function getPathsToBrand($itemId, $brandId)
    {
        if (! isset($this->pathsToBrand[$itemId][$brandId])) {
            $paths = $this->itemParentTable->getPathsToBrand($itemId, $brandId, [
                'breakOnFirst' => true
            ]);
            $this->pathsToBrand[$itemId][$brandId] = $paths;
        }

        return $this->pathsToBrand[$itemId][$brandId];
    }

    public function getDetailsUrl(DbTable\Item\Row $item)
    {
        $paths = $this->getPathsToBrand($item->id, $this->brand['id'], [
            'breakOnFirst' => true
        ]);

        if (count($paths) <= 0) {
            return null;
        }

        $path = $paths[0];

        return $this->router->assemble([
            'action'        => 'brand-item',
            'brand_catname' => $this->brand['catname'],
            'car_catname'   => $path['car_catname'],
            'path'          => $path['path']
        ], [
            'name' => 'catalogue'
        ]);
    }

    public function getPicturesUrl(DbTable\Item\Row $item)
    {
        $paths = $this->getPathsToBrand($item->id, $this->brand['id'], [
            'breakOnFirst' => true
        ]);

        if (count($paths) <= 0) {
            return null;
        }

        $path = $paths[0];

        return $this->router->assemble([
            'action'        => 'brand-item-pictures',
            'brand_catname' => $this->brand['catname'],
            'car_catname'   => $path['car_catname'],
            'path'          => $path['path'],
            'exact'         => false
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

        $paths = $this->getPathsToBrand($item->id, $this->brand['id'], [
            'breakOnFirst' => true
        ]);

        if (count($paths) <= 0) {
            return null;
        }

        $path = $paths[0];

        return $this->router->assemble([
            'action'        => 'brand-item-specifications',
            'brand_catname' => $this->brand['catname'],
            'car_catname'   => $path['car_catname'],
            'path'          => $path['path'],
        ], [
            'name' => 'catalogue'
        ]);
    }

    public function getPictureUrl(DbTable\Item\Row $item, array $picture)
    {
        $paths = $this->getPathsToBrand($item->id, $this->brand['id'], [
            'breakOnFirst' => true
        ]);

        if (count($paths) <= 0) {
            return $this->picHelper->url($picture['identity']);
        }

        $path = $paths[0];

        return $this->router->assemble([
            'action'        => 'brand-item-picture',
            'brand_catname' => $this->brand['catname'],
            'car_catname'   => $path['car_catname'],
            'path'          => $path['path'],
            'picture_id'    => $picture['identity']
        ], [
            'name' => 'catalogue'
        ]);
    }
}

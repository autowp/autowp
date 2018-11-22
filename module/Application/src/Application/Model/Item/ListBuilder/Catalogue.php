<?php

namespace Application\Model\Item\ListBuilder;

use Application\Model\Item\ListBuilder;

class Catalogue extends ListBuilder
{
    /**
     * @var array
     */
    protected $brand;

    /**
     * @var array
     */
    private $pathsToBrand = [];

    public function setBrand(array $brand)
    {
        $this->brand = $brand;

        return $this;
    }

    public function isTypeUrlEnabled()
    {
        return true;
    }

    private function getPathsToBrand($itemId, $brandId)
    {
        if (! isset($this->pathsToBrand[$itemId][$brandId])) {
            $paths = $this->catalogue->getCataloguePaths($itemId, [
                'toBrand'      => $brandId,
                'breakOnFirst' => true
            ]);
            $this->pathsToBrand[$itemId][$brandId] = $paths;
        }

        return $this->pathsToBrand[$itemId][$brandId];
    }

    public function getDetailsUrl($item)
    {
        $paths = $this->getPathsToBrand($item['id'], $this->brand['id']);

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

    public function getPicturesUrl($item)
    {
        $paths = $this->getPathsToBrand($item['id'], $this->brand['id']);

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

    public function getSpecificationsUrl($item)
    {
        $hasSpecs = $this->specsService->hasSpecs($item['id']);

        if (! $hasSpecs) {
            return false;
        }

        $paths = $this->getPathsToBrand($item['id'], $this->brand['id']);

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

    public function getPictureUrl($item, $picture)
    {
        $paths = $this->getPathsToBrand($item['id'], $this->brand['id']);

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

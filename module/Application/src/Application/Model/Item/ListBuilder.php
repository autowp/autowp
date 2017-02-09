<?php

namespace Application\Model\Item;

use Application\Controller\Plugin\Pic;
use Application\Model\Catalogue;
use Application\Model\DbTable;

use Zend\Router\Http\TreeRouteStack;

class ListBuilder
{
    /**
     * @var Catalogue
     */
    protected $catalogue;

    /**
     * @var TreeRouteStack
     */
    protected $router;

    /**
     * @var Pic
     */
    protected $picHelper;

    /**
     * @var array
     */
    private $cataloguePaths = [];

    public function __construct(array $options)
    {
        $this->setOptions($options);
    }

    public function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);
            $this->$method($value);
        }
    }

    public function setCatalogue(Catalogue $catalogue)
    {
        $this->catalogue = $catalogue;

        return $this;
    }

    public function setRouter(TreeRouteStack $router)
    {
        $this->router = $router;

        return $this;
    }

    public function setPicHelper(Pic $picHelper)
    {
        $this->picHelper = $picHelper;

        return $this;
    }

    public function isTypeUrlEnabled()
    {
        return false;
    }

    private function getCataloguePath($item)
    {
        $id = $item['id'];
        if (! isset($this->cataloguePaths[$id])) {
            $this->cataloguePaths[$id] = $this->catalogue->getCataloguePaths($item['id']);
        }

        return $this->cataloguePaths[$id];
    }

    public function getDetailsUrl(DbTable\Item\Row $item)
    {
        $cataloguePaths = $this->getCataloguePath($item);

        $url = null;
        foreach ($cataloguePaths as $cPath) {
            return $this->router->assemble([
                'action'        => 'brand-item',
                'brand_catname' => $cPath['brand_catname'],
                'car_catname'   => $cPath['car_catname'],
                'path'          => $cPath['path']
            ], [
                'name' => 'catalogue'
            ]);
        }

        return null;
    }

    public function getPicturesUrl(DbTable\Item\Row $item)
    {
        return null;
    }

    public function getSpecificationsUrl(DbTable\Item\Row $item)
    {
        foreach ($this->getCataloguePath($item) as $path) {
            return $this->router->assemble([
                'action'        => 'brand-item-specifications',
                'brand_catname' => $path['brand_catname'],
                'car_catname'   => $path['car_catname'],
                'path'          => $path['path']
            ], [
                'name' => 'catalogue'
            ]);
            break;
        }

        return null;
    }

    public function getTypeUrl(DbTable\Item\Row $item, $type)
    {
        return null;
    }

    public function getPictureUrl(DbTable\Item\Row $item, array $picture)
    {
        return $this->picHelper->href($picture);
    }
}

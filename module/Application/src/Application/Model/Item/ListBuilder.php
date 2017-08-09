<?php

namespace Application\Model\Item;

use Zend\Router\Http\TreeRouteStack;

use Application\Controller\Plugin\Pic;
use Application\Model\Catalogue;
use Application\Service\SpecificationsService;

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
     * @var SpecificationsService
     */
    protected $specsService;

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

    public function setSpecsService(SpecificationsService $specsService)
    {
        $this->specsService = $specsService;

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

    /**
     * @param \ArrayObject|array $item
     * @return mixed|string|NULL
     */
    public function getDetailsUrl($item)
    {
        $cataloguePaths = $this->getCataloguePath($item);

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

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @param \ArrayObject|array $item
     * @return string|NULL
     */
    public function getPicturesUrl($item)
    {
        return null;
    }

    /**
     * @param \ArrayObject|array $item
     * @return string|NULL
     */
    public function getSpecificationsUrl($item)
    {
        $hasSpecs = $this->specsService->hasSpecs($item['id']);

        if (! $hasSpecs) {
            return false;
        }

        $cataloguePaths = $this->getCataloguePath($item, [
            'toBrand' => true
        ]);
        foreach ($cataloguePaths as $path) {
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

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getTypeUrl($item, $type)
    {
        return null;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getPictureUrl($item, array $picture)
    {
        return $this->picHelper->href($picture);
    }
}

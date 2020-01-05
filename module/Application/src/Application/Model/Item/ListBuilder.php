<?php

namespace Application\Model\Item;

use ArrayObject;
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

    private function getCataloguePath($item, array $options)
    {
        $id = $item['id'];
        if (! isset($this->cataloguePaths[$id])) {
            $this->cataloguePaths[$id] = $this->catalogue->getCataloguePaths($item['id'], $options);
        }

        return $this->cataloguePaths[$id];
    }

    /**
     * @param ArrayObject|array $item
     * @param array $options
     * @return string[]|null
     */
    public function getDetailsRoute($item, array $options)
    {
        $cataloguePaths = $this->getCataloguePath($item, $options);

        foreach ($cataloguePaths as $cPath) {
            return array_merge(['/', $cPath['brand_catname'], $cPath['car_catname']], $cPath['path']);
        }

        return null;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @param ArrayObject|array $item
     * @return string|NULL
     */
    public function getPicturesUrl($item)
    {
        return null;
    }

    /**
     * @param ArrayObject|array $item
     * @return array|null
     */
    public function getSpecificationsRoute($item): ?array
    {
        $hasSpecs = $this->specsService->hasSpecs($item['id']);

        if (! $hasSpecs) {
            return null;
        }

        $cataloguePaths = $this->getCataloguePath($item, [
            'toBrand'      => true,
            'breakOnFirst' => true
        ]);
        foreach ($cataloguePaths as $path) {
            return array_merge(['/', $path['brand_catname'], $path['car_catname']], $path['path']);
        }

        return null;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param $item
     * @param $type
     * @return null
     */
    public function getTypeUrl($item, $type)
    {
        return null;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param $item
     * @param $picture
     * @return mixed|null
     */
    public function getPictureUrl($item, $picture)
    {
        return $this->picHelper->href($picture);
    }
}

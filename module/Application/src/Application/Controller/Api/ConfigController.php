<?php

namespace Application\Controller\Api;

use Application\Model\Brand;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class ConfigController extends AbstractRestfulController
{
    private Brand $brand;

    public function __construct(Brand $brand)
    {
        $this->brand = $brand;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function indexAction()
    {
        return new JsonModel([
            'brands' => $this->brand->getCatnames(),
        ]);
    }
}

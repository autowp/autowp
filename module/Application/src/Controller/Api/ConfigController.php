<?php

namespace Application\Controller\Api;

use Application\Model\Brand;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

class ConfigController extends AbstractRestfulController
{
    private Brand $brand;

    public function __construct(Brand $brand)
    {
        $this->brand = $brand;
    }

    public function indexAction(): JsonModel
    {
        return new JsonModel([
            'brands' => $this->brand->getCatnames(),
        ]);
    }
}

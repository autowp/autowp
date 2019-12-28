<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Application\Model\Brand;

/**
 * Class ContactsController
 * @package Application\Controller\Api
 *
 */
class ConfigController extends AbstractRestfulController
{
    /**
     * @var Brand
     */
    private $brand;

    public function __construct(Brand $brand)
    {
        $this->brand = $brand;
    }

    public function indexAction()
    {
        return new JsonModel([
            'brands' => $this->brand->getCatnames()
        ]);
    }
}

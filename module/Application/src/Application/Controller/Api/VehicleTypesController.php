<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\User\Model\DbTable\User;

use Application\Model\VehicleType;

class VehicleTypesController extends AbstractRestfulController
{
    /**
     * @var VehicleType
     */
    private $vehicleType;

    public function __construct(VehicleType $vehicleType)
    {
        $this->vehicleType = $vehicleType;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        return new JsonModel([
            'items' => $this->vehicleType->getTree(),
        ]);
    }
}

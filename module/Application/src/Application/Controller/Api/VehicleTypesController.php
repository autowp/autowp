<?php

namespace Application\Controller\Api;

use Application\Model\VehicleType;
use Autowp\User\Controller\Plugin\User;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

/**
 * @method User user($user = null)
 * @method ViewModel forbiddenAction()
 */
class VehicleTypesController extends AbstractRestfulController
{
    private VehicleType $vehicleType;

    public function __construct(VehicleType $vehicleType)
    {
        $this->vehicleType = $vehicleType;
    }

    public function indexAction(): ViewModel
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        return new JsonModel([
            'items' => $this->vehicleType->getTree(),
        ]);
    }
}

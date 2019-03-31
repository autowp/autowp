<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\User\Controller\Plugin\User;

use Application\Controller\Plugin\ForbiddenAction;
use Application\Model\VehicleType;

/**
 * Class VehicleTypesController
 * @package Application\Controller\Api
 *
 * @method User user()
 * @method ForbiddenAction forbiddenAction()
 */
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

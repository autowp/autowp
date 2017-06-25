<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable;
use Application\Model\VehicleType;
use Autowp\Commons\Paginator\Adapter\Zend1DbSelect;

class ItemVehicleTypeController extends AbstractRestfulController
{
    /**
     * @var VehicleType
     */
    private $vehicleType;

    public function __construct(
        VehicleType $vehicleType
    ) {
        $this->vehicleType = $vehicleType;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemVehicleTypeTable = new DbTable\Vehicle\VehicleType();

        $select = $itemVehicleTypeTable->select(true);

        $itemId = (int)$this->params()->fromQuery('item_id');
        if ($itemId) {
            $select->where('vehicle_id = ?', $itemId);
        }

        $vehicleTypeId = (int)$this->params()->fromQuery('vehicle_type_id');
        if ($vehicleTypeId) {
            $select->where('vehicle_type_id = ?', $vehicleTypeId);
        }

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbSelect($select)
        );

        $paginator
            ->setItemCountPerPage(50)
            ->setCurrentPageNumber($this->params()->fromQuery('page'));

        $items = [];
        foreach ($paginator->getCurrentItems() as $row) {
            $items[] = [
                'item_id'         => (int)$row['vehicle_id'],
                'vehicle_type_id' => (int)$row['vehicle_type_id'],
            ];
        }

        return new JsonModel([
            'items' => $items
        ]);
    }

    public function itemAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemVehicleTypeTable = new DbTable\Vehicle\VehicleType();
        $row = $itemVehicleTypeTable->fetchRow([
            'vehicle_id = ?'      => (int)$this->params('item_id'),
            'vehicle_type_id = ?' => (int)$this->params('vehicle_type_id')
        ]);
        if (! $row) {
            return $this->notFoundAction();
        }

        return new JsonModel([
            'item_id'         => (int)$row['vehicle_id'],
            'vehicle_type_id' => (int)$row['vehicle_type_id'],
        ]);
    }

    public function deleteAction()
    {
        $canMove = $this->user()->isAllowed('car', 'move');
        if (! $canMove) {
            return $this->forbiddenAction();
        }

        $vehicleTypeId = (int)$this->params('vehicle_type_id');
        $itemId        = (int)$this->params('item_id');

        $this->vehicleType->removeVehicleType($itemId, $vehicleTypeId);

        return $this->getResponse()->setStatusCode(204);
    }

    public function createAction()
    {
        $canMove = $this->user()->isAllowed('car', 'move');
        if (! $canMove) {
            return $this->forbiddenAction();
        }

        $vehicleTypeId = (int)$this->params('vehicle_type_id');
        $itemId        = (int)$this->params('item_id');

        $itemTable = new DbTable\Item();
        $itemRow = $itemTable->find($itemId)->current();

        if (! in_array($itemRow['item_type_id'], [DbTable\Item\Type::VEHICLE, DbTable\Item\Type::TWINS])) {
            return $this->notFoundAction();
        }

        $this->vehicleType->addVehicleType($itemId, $vehicleTypeId);

        $url = $this->url()->fromRoute('api/item-vehicle-type/item/get', [
            'vehicle_type_id' => $vehicleTypeId,
            'item_id'         => $itemId
        ]);
        $this->getResponse()->getHeaders()->addHeaderLine('Location', $url);

        return $this->getResponse()->setStatusCode(201);
    }
}

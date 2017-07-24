<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\Paginator;
use Zend\View\Model\JsonModel;

use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable;
use Application\Model\Item;
use Application\Model\VehicleType;

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

        $select = $this->vehicleType->getItemSelect(
            $this->params()->fromQuery('item_id'),
            (Int) $this->params()->fromQuery('vehicle_type_id')
        );

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect(
                $select,
                $this->vehicleType->getItemTable()->getAdapter()
            )
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

        $row = $this->vehicleType->getItemRow(
            $this->params('item_id'),
            $this->params('vehicle_type_id')
        );

        if (! $row) {
            return $this->notFoundAction();
        }

        return new JsonModel($row);
    }

    public function deleteAction()
    {
        $canMove = $this->user()->isAllowed('car', 'move');
        if (! $canMove) {
            return $this->forbiddenAction();
        }

        $this->vehicleType->removeVehicleType(
            $this->params('item_id'),
            $this->params('vehicle_type_id')
        );

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

        if (! in_array($itemRow['item_type_id'], [Item::VEHICLE, Item::TWINS])) {
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

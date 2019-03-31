<?php

namespace Application\Controller\Api;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\Paginator;
use Zend\View\Model\JsonModel;

use Autowp\User\Controller\Plugin\User;

use Application\Controller\Plugin\ForbiddenAction;
use Application\Model\Item;
use Application\Model\VehicleType;

/**
 * Class ItemVehicleTypeController
 * @package Application\Controller\Api
 *
 * @method User user($user = null)
 * @method ForbiddenAction forbiddenAction()
 */
class ItemVehicleTypeController extends AbstractRestfulController
{
    /**
     * @var VehicleType
     */
    private $vehicleType;

    /**
     * @var Item
     */
    private $item;

    public function __construct(
        VehicleType $vehicleType,
        Item $item
    ) {
        $this->vehicleType = $vehicleType;
        $this->item = $item;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $select = $this->vehicleType->getItemSelect(
            (int) $this->params()->fromQuery('item_id'),
            (int) $this->params()->fromQuery('vehicle_type_id')
        );
        $select->where('not inherited');

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

        if (! $row || $row['inherited']) {
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

        /* @phan-suppress-next-line PhanUndeclaredMethod */
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

        $itemRow = $this->item->getRow([
            'id'           => $itemId,
            'item_type_id' => [Item::VEHICLE, Item::TWINS]
        ]);

        if (! $itemRow) {
            return $this->notFoundAction();
        }

        $this->vehicleType->addVehicleType($itemId, $vehicleTypeId);

        $url = $this->url()->fromRoute('api/item-vehicle-type/item/get', [
            'vehicle_type_id' => $vehicleTypeId,
            'item_id'         => $itemId
        ]);
        $this->getResponse()->getHeaders()->addHeaderLine('Location', $url);

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->getResponse()->setStatusCode(201);
    }
}

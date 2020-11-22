<?php

namespace Application\Controller\Api;

use Application\Model\Item;
use Application\Model\VehicleType;
use Autowp\User\Controller\Plugin\User;
use Laminas\Db\Adapter\Adapter;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Paginator;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

/**
 * @method User user($user = null)
 * @method ViewModel forbiddenAction()
 */
class ItemVehicleTypeController extends AbstractRestfulController
{
    private VehicleType $vehicleType;

    private Item $item;

    public function __construct(
        VehicleType $vehicleType,
        Item $item
    ) {
        $this->vehicleType = $vehicleType;
        $this->item        = $item;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function indexAction()
    {
        if (! $this->user()->enforce('global', 'moderate')) {
            return $this->forbiddenAction();
        }

        $select = $this->vehicleType->getItemSelect(
            (int) $this->params()->fromQuery('item_id'),
            (int) $this->params()->fromQuery('vehicle_type_id')
        );
        $select->where('not inherited');

        /** @var Adapter $adapter */
        $adapter   = $this->vehicleType->getItemTable()->getAdapter();
        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $adapter)
        );

        $paginator
            ->setItemCountPerPage(50)
            ->setCurrentPageNumber($this->params()->fromQuery('page'));

        $items = [];
        foreach ($paginator->getCurrentItems() as $row) {
            $items[] = [
                'item_id'         => (int) $row['vehicle_id'],
                'vehicle_type_id' => (int) $row['vehicle_type_id'],
            ];
        }

        return new JsonModel([
            'items' => $items,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function itemAction()
    {
        if (! $this->user()->enforce('global', 'moderate')) {
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

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function deleteAction()
    {
        $canMove = $this->user()->enforce('car', 'move');
        if (! $canMove) {
            return $this->forbiddenAction();
        }

        $this->vehicleType->removeVehicleType(
            $this->params('item_id'),
            $this->params('vehicle_type_id')
        );

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_204);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function createAction()
    {
        $canMove = $this->user()->enforce('car', 'move');
        if (! $canMove) {
            return $this->forbiddenAction();
        }

        $vehicleTypeId = (int) $this->params('vehicle_type_id');
        $itemId        = (int) $this->params('item_id');

        $itemRow = $this->item->getRow([
            'id'           => $itemId,
            'item_type_id' => [Item::VEHICLE, Item::TWINS],
        ]);

        if (! $itemRow) {
            return $this->notFoundAction();
        }

        $this->vehicleType->addVehicleType($itemId, $vehicleTypeId);

        $url = $this->url()->fromRoute('api/item-vehicle-type/item/get', [
            'vehicle_type_id' => $vehicleTypeId,
            'item_id'         => $itemId,
        ]);

        /** @var Response $response */
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $url);
        return $response->setStatusCode(Response::STATUS_CODE_201);
    }
}

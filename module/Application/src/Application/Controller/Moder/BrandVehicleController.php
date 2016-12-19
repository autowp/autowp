<?php

namespace Application\Controller\Moder;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\DbTable;
use Application\Model\BrandVehicle;

class BrandVehicleController extends AbstractActionController
{
    private $languages = ['ru', 'en', 'fr', 'zh'];

    /**
     * @var BrandTable
     */
    private $brandTable;

    /**
     * @var BrandVehicle
     */
    private $model;

    public function __construct(BrandVehicle $model)
    {
        $this->model = $model;
    }

    /**
     * @param Vehicle\Row $car
     * @return string
     */
    private function vehicleModerUrl(DbTable\Vehicle\Row $car, $full = false, $tab = null, $uri = null)
    {
        return $this->url()->fromRoute('moder/cars/params', [
            'action' => 'car',
            'car_id' => $car->id,
            'tab'    => $tab
        ], [
            'force_canonical' => $full,
            'uri'             => $uri
        ]);
    }

    /**
     * @return BrandTable
     */
    private function getBrandTable()
    {
        return $this->brandTable
            ? $this->brandTable
            : $this->brandTable = new DbTable\Brand();
    }

    public function itemAction()
    {
        if (! $this->user()->isAllowed('car', 'move')) {
            return $this->forbiddenAction();
        }

        $brandTable = $this->getBrandTable();
        $brandVehicleTable = new DbTable\BrandItem();
        $brandVehicleLangaugeTable = new DbTable\Brand\VehicleLanguage();
        $vehicleTable = $this->catalogue()->getCarTable();

        $brandItemRow = $brandVehicleTable->fetchRow([
            'brand_id = ?' => $this->params('brand_id'),
            'car_id = ?'   => $this->params('vehicle_id')
        ]);

        if (! $brandItemRow) {
            return $this->notFoundAction();
        }

        $brandRow = $brandTable->find($brandItemRow->brand_id)->current();
        $vehicleRow = $vehicleTable->find($brandItemRow->car_id)->current();
        if (! $brandRow || ! $vehicleRow) {
            return $this->notFoundAction();
        }

        $form = new \Application\Form\Moder\BrandVehicle(null, [
            'languages' => $this->languages,
            'brandId'   => $brandRow->id,
            'vehicleId' => $vehicleRow->id
        ]);

        $values = [
            'catname' => $brandItemRow->catname,
        ];

        $bvlRows = $brandVehicleLangaugeTable->fetchAll([
            'vehicle_id = ?' => $vehicleRow->id,
            'brand_id = ?'   => $brandRow->id
        ]);
        foreach ($bvlRows as $bvlRow) {
            $values[$bvlRow->language] = [
                'name' => $bvlRow->name
            ];
        }

        $form->populateValues($values);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $values = $form->getData();

                $this->model->setBrandVehicle($brandRow->id, $vehicleRow->id, $values, false);

                return $this->redirect()->toRoute(null, [], [], true);
            }
        }

        return [
            'brand'   => $brandRow,
            'vehicle' => $vehicleRow,
            'form'    => $form
        ];
    }

    public function addAction()
    {
        if (! $this->user()->isAllowed('car', 'move')) {
            return $this->forbiddenAction();
        }

        $brandTable = $this->getBrandTable();
        $vehicleTable = $this->catalogue()->getCarTable();

        $brandRow = $brandTable->find($this->params('brand_id'))->current();
        $vehicleRow = $vehicleTable->fetchRow([
            'id = ?'              => (int)$this->params('vehicle_id'),
            'item_type_id IN (?)' => [DbTable\Item\Type::VEHICLE, DbTable\Item\Type::ENGINE]
        ]);
        if (! $brandRow || ! $vehicleRow) {
            return $this->notFoundAction();
        }

        $this->model->create($brandRow->id, $vehicleRow->id);

        $user = $this->user()->get();
        $ucsTable = new DbTable\User\CarSubscribe();
        $ucsTable->subscribe($user, $vehicleRow);

        $message = sprintf(
            'Автомобиль %s добавлен к бренду %s',
            htmlspecialchars($this->car()->formatName($vehicleRow, 'en')),
            $brandRow->name
        );
        $this->log($message, [$brandRow, $vehicleRow]);

        return $this->redirectToCar($vehicleRow, 'catalogue');
    }

    public function deleteAction()
    {
        if (! $this->user()->isAllowed('car', 'move')) {
            return $this->forbiddenAction();
        }

        $brandTable = $this->getBrandTable();
        $vehicleTable = $this->catalogue()->getCarTable();

        $brandRow = $brandTable->find($this->params('brand_id'))->current();
        $vehicleRow = $vehicleTable->find($this->params('vehicle_id'))->current();

        if (! $brandRow || ! $vehicleRow) {
            return $this->notFoundAction();
        }

        $success = $this->model->delete($brandRow->id, $vehicleRow->id);

        if ($success) {
            $user = $this->user()->get();
            $ucsTable = new DbTable\User\CarSubscribe();
            $ucsTable->subscribe($user, $vehicleRow);

            $message = sprintf(
                'Автомобиль %s отсоединен от бренда %s',
                htmlspecialchars($this->car()->formatName($vehicleRow, 'en')),
                $brandRow->name
            );
            $this->log($message, [$brandRow, $vehicleRow]);
        }

        return $this->redirectToCar($vehicleRow, 'catalogue');
    }

    /**
     * @param Vehicle\Row $car
     * @return void
     */
    private function redirectToCar(DbTable\Vehicle\Row $vehicleRow, $tab = null)
    {
        return $this->redirect()->toUrl($this->vehicleModerUrl($vehicleRow, true, $tab));
    }
}

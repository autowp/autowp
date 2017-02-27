<?php

namespace Application\Controller\Moder;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

use Application\HostManager;
use Application\Model\DbTable;
use Application\Model\BrandVehicle;

class ItemParentController extends AbstractActionController
{
    private $languages = ['en'];

    /**
     * @var BrandVehicle
     */
    private $model;

    /**
     * @var HostManager
     */
    private $hostManager;

    public function __construct(
        BrandVehicle $model,
        array $languages,
        HostManager $hostManager
    ) {
        $this->model = $model;
        $this->languages = $languages;
        $this->hostManager = $hostManager;
    }

    /**
     * @param Vehicle\Row $car
     * @return string
     */
    private function vehicleModerUrl(DbTable\Vehicle\Row $car, $full = false, $tab = null, $uri = null)
    {
        return $this->url()->fromRoute('moder/cars/params', [
            'action'  => 'car',
            'item_id' => $car->id,
            'tab'     => $tab
        ], [
            'force_canonical' => $full,
            'uri'             => $uri
        ]);
    }

    public function itemAction()
    {
        if (! $this->user()->isAllowed('car', 'move')) {
            return $this->forbiddenAction();
        }

        $itemParentTable = new DbTable\Item\ParentTable();
        $itemParentLangaugeTable = new DbTable\Item\ParentLanguage();
        $itemTable = $this->catalogue()->getItemTable();

        $itemParentRow = $itemParentTable->fetchRow([
            'parent_id = ?' => $this->params('parent_id'),
            'item_id = ?'   => $this->params('item_id')
        ]);

        if (! $itemParentRow) {
            return $this->notFoundAction();
        }

        $parentRow = $itemTable->find($itemParentRow->parent_id)->current();
        $itemRow = $itemTable->find($itemParentRow->item_id)->current();
        if (! $parentRow || ! $itemRow) {
            return $this->notFoundAction();
        }

        $form = new \Application\Form\Moder\ItemParent(null, [
            'languages' => $this->languages,
            'parentId'  => $parentRow->id,
            'itemId'    => $itemRow->id
        ]);

        $values = [
            'catname' => $itemParentRow->catname,
            'type'    => $itemParentRow->type
        ];

        $bvlRows = $itemParentLangaugeTable->fetchAll([
            'item_id = ?'   => $itemRow->id,
            'parent_id = ?' => $parentRow->id
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

                $this->model->setItemParent($parentRow->id, $itemRow->id, $values, false);

                return $this->redirect()->toRoute(null, [], [], true);
            }
        }

        return [
            'parent' => $parentRow,
            'item'   => $itemRow,
            'form'   => $form
        ];
    }

    public function addAction()
    {
        print 'Temporarly disabled';
        exit;

        if (! $this->user()->isAllowed('car', 'move')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();

        $parentRow = $itemTable->fetchRow([
            'item_type_id = ?' => DbTable\Item\Type::BRAND,
            'id = ?'           => (int)$this->params('brand_id')
        ]);
        $itemRow = $itemTable->fetchRow([
            'id = ?'              => (int)$this->params('vehicle_id'),
            'item_type_id IN (?)' => [DbTable\Item\Type::VEHICLE, DbTable\Item\Type::ENGINE]
        ]);
        if (! $parentRow || ! $itemRow) {
            return $this->notFoundAction();
        }

        $this->model->create($parentRow->id, $itemRow->id);

        $user = $this->user()->get();
        $ucsTable = new DbTable\User\ItemSubscribe();
        $ucsTable->subscribe($user, $itemRow);

        $message = sprintf(
            'Автомобиль %s добавлен к бренду %s',
            htmlspecialchars($this->car()->formatName($itemRow, 'en')),
            $parentRow->name
        );
        $this->log($message, [$parentRow, $itemRow]);

        $subscribers = [];
        foreach ($ucsTable->getItemSubscribers($itemRow) as $subscriber) {
            $subscribers[$subscriber->id] = $subscriber;
        }

        foreach ($ucsTable->getItemSubscribers($parentRow) as $subscriber) {
            $subscribers[$subscriber->id] = $subscriber;
        }

        foreach ($subscribers as $subscriber) {
            if ($subscriber->id != $user->id) {
                $uri = $this->hostManager->getUriByLanguage($subscriber->language);

                $message = sprintf(
                    $this->translate(
                        'pm/user-%s-adds-item-%s-%s-to-item-%s-%s',
                        'default',
                        $subscriber->language
                    ),
                    $this->userModerUrl($user, true, $uri),
                    $this->car()->formatName($itemRow, $subscriber->language),
                    $this->carModerUrl($itemRow, true, null, $uri),
                    $this->car()->formatName($parentRow, $subscriber->language),
                    $this->carModerUrl($parentRow, true, null, $uri)
                );

                $this->message->send(null, $subscriber->id, $message);
            }
        }

        return $this->redirectToCar($itemRow, 'catalogue');
    }

    public function deleteAction()
    {
        print 'Temporarly disabled';
        exit;

        if (! $this->user()->isAllowed('car', 'move')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();

        $brandRow = $brandTable->fetchRow([
            'item_type_id = ?' => DbTable\Item\Type::BRAND,
            'id = ?'           => (int)$this->params('brand_id')
        ]);
        $vehicleRow = $itemTable->find($this->params('vehicle_id'))->current();

        if (! $brandRow || ! $vehicleRow) {
            return $this->notFoundAction();
        }

        $success = $this->model->delete($brandRow->id, $vehicleRow->id);

        if ($success) {
            $user = $this->user()->get();
            $ucsTable = new DbTable\User\ItemSubscribe();
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

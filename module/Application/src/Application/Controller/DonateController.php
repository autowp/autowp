<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Application\Model\Brand;
use Application\Model\CarOfDay;
use Application\Model\Item;
use Application\Model\ItemParent;

class DonateController extends AbstractActionController
{
    private $carOfDay;

    /**
     * @var array
     */
    private $yandexConfig;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var ItemParent
     */
    private $itemParent;

    /**
     * @var Brand
     */
    private $brand;

    public function __construct(
        CarOfDay $carOfDay,
        array $yandexConfig,
        Item $itemModel,
        ItemParent $itemParent,
        Brand $brand
    ) {
        $this->carOfDay = $carOfDay;
        $this->yandexConfig = $yandexConfig;
        $this->itemModel = $itemModel;
        $this->itemParent = $itemParent;
        $this->brand = $brand;
    }

    public function successAction()
    {
    }

    public function vodAction()
    {
        $dates = $this->carOfDay->getNextDates();

        foreach ($dates as &$nextDate) {
            $nextDate['date_str'] = $nextDate['date']->format('Y-m-d');
        }
        unset($nextDate);

        $date = (string)$this->params('date');
        $selectedDate = null;
        foreach ($dates as $nextDate) {
            if ($date == $nextDate['date_str'] && $nextDate['free']) {
                $selectedDate = $nextDate['date_str'];
                break;
            }
        }

        $itemId = (int)$this->params('item_id');

        $itemId = $this->carOfDay->isComplies($itemId) ? $itemId : null;

        $item = null;
        if ($itemId) {
            $item = $this->itemModel->getRow([
                'id'           => $itemId,
                'item_type_id' => Item::VEHICLE
            ]);
        }

        $userId = null;
        $user = $this->user()->get();
        if ($user) {
            $userId = $user['id'];
        }

        $anonymous = (bool)$this->params('anonymous');

        $itemOfDay = null;
        $itemNameData = null;
        if ($item) {
            $itemOfDay = $this->carOfDay->getItemOfDay($item['id'], $anonymous ? null : $userId, $this->language());
            $itemNameData = $this->itemModel->getNameData($item, $this->language());
        }

        return [
            'dates'                => $dates,
            'selectedDate'         => $selectedDate,
            'selectedItem'         => $item,
            'selectedItemNameData' => $itemNameData,
            'sum'                  => $this->yandexConfig['price'],
            'userId'               => $userId,
            'anonymous'            => $userId ? $anonymous : true,
            'itemOfDay'            => $itemOfDay,
        ];
    }

    public function vodSuccessAction()
    {
    }

    public function vodSelectItemAction()
    {
        $language = $this->language();

        $brand = $this->brand->getBrandById((int)$this->params('brand_id'), $language);
        if (! $brand) {
            $rows = $this->brand->getList($language, function () {
            });

            return [
                'brand'  => null,
                'brands' => $rows
            ];
        }

        $haveConcepts = (bool)$this->itemModel->getRow([
            'ancestor'   => $brand['id'],
            'is_concept' => true
        ]);

        $rows = $this->itemModel->getRows([
            'language' => $this->language(),
            'columns'  => ['id', 'name', 'is_group'],
            'order'    => [
                'name',
                'item.begin_year',
                'item.end_year',
                'item.begin_model_year',
                'item.end_model_year'
            ],
            'item_type_id' => Item::VEHICLE,
            'is_concept'   => false,
            'parent'       => $brand['id']
        ]);

        $vehicles = $this->prepareVehicles($rows);

        return [
            'brand'        => $brand,
            'vehicles'     => $vehicles,
            'haveConcepts' => $haveConcepts,
            'conceptsUrl'  => $this->url()->fromRoute('donate/vod/concepts/params', [
                'brand_id' => $brand['id']
            ], [], true),
        ];
    }

    private function prepareVehicles($rows)
    {
        $items = [];
        foreach ($rows as $row) {
            $hasChildItems = $this->itemParent->hasChildItems($row['id']);

            $items[] = [
                'begin_model_year' => $row['begin_model_year'],
                'end_model_year'   => $row['end_model_year'],
                'spec'             => $row['spec'],
                'spec_full'        => $row['spec_full'],
                'body'             => $row['body'],
                'name'             => $row['name'],
                'begin_year'       => $row['begin_year'],
                'end_year'         => $row['end_year'],
                'today'            => $row['today'],
                'url'  => $this->url()->fromRoute('donate/vod/params', [
                    'item_id' => $row['id']
                ], [], true),
                'haveChilds' => $hasChildItems,
                'isGroup'    => $row['is_group'],
                'type'       => null,
                'loadUrl'    => $this->url()->fromRoute('donate/vod/vehicle-childs/params', [
                    'item_id' => $row['id']
                ], [], true),
                'isComplies' => $this->carOfDay->isComplies($row['id'])
            ];
        }

        return $items;
    }

    public function vehicleChildsAction()
    {
        $car = $this->itemModel->getRow(['id' => (int)$this->params('item_id')]);
        if (! $car) {
            return $this->notfoundAction();
        }

        $rows = $this->itemModel->getRows([
            'language' => $this->language(),
            'columns'  => ['id', 'name', 'is_group'],
            'order'    => ['ip1.type', 'name', 'item.begin_year', 'item.end_year'],
            'parent'   => [
                'id'      => $car['id'],
                'columns' => ['link_type']
            ]
        ]);

        $viewModel = new ViewModel([
            'cars' => $this->prepareItemParentRows($rows)
        ]);

        return $viewModel->setTerminal(true);
    }

    private function prepareItemParentRows($rows)
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'begin_model_year' => $row['begin_model_year'],
                'end_model_year'   => $row['end_model_year'],
                'spec'             => $row['spec'],
                'spec_full'        => $row['spec_full'],
                'body'             => $row['body'],
                'name'             => $row['name'],
                'begin_year'       => $row['begin_year'],
                'end_year'         => $row['end_year'],
                'today'            => $row['today'],
                'url'  => $this->url()->fromRoute('donate/vod/params', [
                    'item_id' => $row['id']
                ], [], true),
                'haveChilds' => $this->itemParent->hasChildItems($row['id']),
                'isGroup'    => $row['is_group'],
                'type'       => $row['link_type'],
                'loadUrl'    => $this->url()->fromRoute('donate/vod/vehicle-childs/params', [
                    'action'  => 'car-childs',
                    'item_id' => $row['id']
                ], [], true),
                'isComplies' => $this->carOfDay->isComplies($row['id'])
            ];
        }

        return $items;
    }

    public function conceptsAction()
    {
        $brand = $this->itemModel->getRow([
            'item_type_id' => Item::BRAND,
            'id'           => (int)$this->params('brand_id')
        ]);
        if (! $brand) {
            return $this->notfoundAction();
        }

        $rows = $this->itemModel->getRows([
            'language'   => $this->language(),
            'columns'    => ['id', 'name', 'is_group'],
            'is_concept' => true,
            'ancestor'   => $brand['id'],
            'order'      => ['name', 'item.begin_year', 'item.end_year']
        ]);

        $concepts = $this->prepareVehicles($rows);

        $viewModel = new ViewModel([
            'concepts' => $concepts,
        ]);

        return $viewModel->setTerminal(true);
    }
}

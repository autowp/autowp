<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Application\Model\Brand as BrandModel;
use Application\Model\CarOfDay;
use Application\Model\DbTable;
use Application\Model\Item;

use Zend_Db_Expr;

class DonateController extends AbstractActionController
{
    private $carOfDay;

    /**
     * @var DbTable\Item\ParentTable
     */
    private $itemParentTable;

    /**
     * @var array
     */
    private $yandexConfig;

    /**
     * @var Item
     */
    private $itemModel;

    public function __construct(CarOfDay $carOfDay, array $yandexConfig, Item $itemModel)
    {
        $this->carOfDay = $carOfDay;
        $this->yandexConfig = $yandexConfig;
        $this->itemModel = $itemModel;
    }

    private function getItemParentTable()
    {
        return $this->itemParentTable
            ? $this->itemParentTable
            : $this->itemParentTable = new DbTable\Item\ParentTable();
    }

    public function indexAction()
    {
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

        $itemTable = new DbTable\Item();

        $item = null;
        if ($itemId) {
            $item = $itemTable->fetchRow([
                'id = ?'           => $itemId,
                'item_type_id = ?' => DbTable\Item\Type::VEHICLE
            ]);
        }

        $userId = null;
        $user = $this->user()->get();
        if ($user) {
            $userId = $user->id;
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
        $brandModel = new BrandModel();

        $language = $this->language();

        $brand = $brandModel->getBrandById($this->params('brand_id'), $language);
        if (! $brand) {
            $rows = $brandModel->getList($language, function () {
            });

            return [
                'brand'  => null,
                'brands' => $rows
            ];
        }

        $itemTable = new DbTable\Item();

        $haveConcepts = (bool)$itemTable->fetchRow(
            $itemTable->select(true)
                ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $brand['id'])
                ->where('item.is_concept')
        );

        $db = $itemTable->getAdapter();

        $rows = $db->fetchAll(
            $db->select()
                ->from('item', [
                    'item.id',
                    'name' => 'if(item_language.name, item_language.name, item.name)',
                    'item.begin_model_year', 'item.end_model_year',
                    'spec' => 'spec.short_name',
                    'spec_full' => 'spec.name',
                    'item.body', 'item.today',
                    'item.begin_year', 'item.end_year',
                    'item.is_group'
                ])
                ->joinLeft('item_language', 'item.id = item_language.item_id and item_language.language = :lang', null)
                ->joinLeft('spec', 'item.spec_id = spec.id', null)
                ->join('item_parent', 'item.id = item_parent.item_id', null)
                ->where('item_parent.parent_id = ?', $brand['id'])
                ->where('NOT item.is_concept')
                ->where('item.item_type_id = ?', DbTable\Item\Type::VEHICLE)
                ->order([
                    'item.name',
                    'item.begin_year',
                    'item.end_year',
                    'item.begin_model_year',
                    'item.end_model_year'
                ])
                ->bind([
                    'lang' => $this->language()
                ])
        );
        $vehicles = $this->prepareVehicles($rows);

        $rows = $db->fetchAll(
            $db->select()
                ->from('item', [
                    'item.id',
                    'name' => 'if(item_language.name, item_language.name, item.name)',
                    'item.begin_model_year', 'item.end_model_year',
                    'spec' => 'spec.short_name',
                    'spec_full' => 'spec.name',
                    'item.body', 'item.today',
                    'item.begin_year', 'item.end_year',
                    'item.is_group'
                ])
                ->joinLeft('item_language', 'item.id = item_language.item_id and item_language.language = :lang', null)
                ->joinLeft('spec', 'item.spec_id = spec.id', null)
                ->join('item_parent', 'item.id = item_parent.item_id', null)
                ->where('item_parent.parent_id = ?', $brand['id'])
                ->where('item.item_type_id = ?', DbTable\Item\Type::ENGINE)
                ->order([
                    'item.name',
                    'item.begin_year',
                    'item.end_year',
                    'item.begin_model_year',
                    'item.end_model_year'
                ])
                ->bind([
                    'lang' => $this->language()
                ])
        );

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
        $itemParentTable = $this->getItemParentTable();
        $itemParentAdapter = $itemParentTable->getAdapter();

        $items = [];
        foreach ($rows as $row) {
            $haveChilds = (bool)$itemParentAdapter->fetchOne(
                $itemParentAdapter->select()
                    ->from($itemParentTable->info('name'), new Zend_Db_Expr('1'))
                    ->where('parent_id = ?', $row['id'])
            );
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
                'haveChilds' => $haveChilds,
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
        $itemTable = new DbTable\Item();

        $car = $itemTable->find($this->params('item_id'))->current();
        if (! $car) {
            return $this->notfoundAction();
        }

        $db = $itemTable->getAdapter();

        $rows = $db->fetchAll(
            $db->select()
                ->from('item', [
                    'item.id',
                    'name' => 'if(item_language.name, item_language.name, item.name)',
                    'item.begin_model_year', 'item.end_model_year',
                    'spec' => 'spec.short_name',
                    'spec_full' => 'spec.name',
                    'item.body', 'item.today',
                    'item.begin_year', 'item.end_year',
                    'item.is_group'
                ])
                ->joinLeft('item_language', 'item.id = item_language.item_id and item_language.language = :lang', null)
                ->joinLeft('spec', 'item.spec_id = spec.id', null)
                ->join('item_parent', 'item.id = item_parent.item_id', 'type')
                ->where('item_parent.parent_id = ?', $car->id)
                ->order(['item_parent.type', 'item.name', 'item.begin_year', 'item.end_year'])
                ->bind([
                    'lang' => $this->language()
                ])
        );

        $viewModel = new ViewModel([
            'cars' => $this->prepareItemParentRows($rows)
        ]);

        return $viewModel->setTerminal(true);
    }

    private function prepareItemParentRows($rows)
    {
        $itemParentTable = $this->getItemParentTable();
        $itemParentAdapter = $itemParentTable->getAdapter();

        $items = [];
        foreach ($rows as $row) {
            $haveChilds = (bool)$itemParentAdapter->fetchOne(
                $itemParentAdapter->select()
                    ->from($itemParentTable->info('name'), new Zend_Db_Expr('1'))
                    ->where('parent_id = ?', $row['id'])
            );
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
                'haveChilds' => $haveChilds,
                'isGroup'    => $row['is_group'],
                'type'       => $row['type'],
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
        $itemTable = new DbTable\Item();
        $brand = $itemTable->fetchRow([
            'item_type_id = ?' => DbTable\Item\Type::BRAND,
            'id = ?'           => (int)$this->params('brand_id')
        ]);
        if (! $brand) {
            return $this->notfoundAction();
        }

        $db = $itemTable->getAdapter();

        $rows = $db->fetchAll(
            $db->select()
                ->from('item', [
                    'item.id',
                    'name' => 'if(item_language.name, item_language.name, item.name)',
                    'item.begin_model_year', 'item.end_model_year',
                    'spec' => 'spec.short_name',
                    'spec_full' => 'spec.name',
                    'item.body', 'item.today',
                    'item.begin_year', 'item.end_year',
                    'item.is_group'
                ])
                ->joinLeft('item_language', 'item.id = item_language.item_id and item_language.language = :lang', null)
                ->joinLeft('spec', 'item.spec_id = spec.id', null)
                ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $brand->id)
                ->where('item.is_concept')
                ->order(['item.name', 'item.begin_year', 'item.end_year'])
                ->group('item.id')
                ->bind([
                    'lang' => $this->language()
                ])
        );

        $concepts = $this->prepareVehicles($rows);

        $viewModel = new ViewModel([
            'concepts' => $concepts,
        ]);

        return $viewModel->setTerminal(true);
    }

    public function logAction()
    {
        $data = [
            [
                'sum'      => 980,
                'currency' => 'руб.',
                'date'     => \DateTime::createFromFormat('Y-m-d', '2017-04-17'),
                'user_id'  => null
            ],
            [
                'sum'      => 294,
                'currency' => 'руб.',
                'date'     => \DateTime::createFromFormat('Y-m-d', '2017-04-04'),
                'user_id'  => null
            ],
            [
                'sum'      => 979.02,
                'currency' => 'руб.',
                'date'     => \DateTime::createFromFormat('Y-m-d', '2017-03-30'),
                'user_id'  => 22075
            ],
            [
                'sum'      => 979.02,
                'currency' => 'руб.',
                'date'     => \DateTime::createFromFormat('Y-m-d', '2017-03-24'),
                'user_id'  => null
            ],
            [
                'sum'      => 979.02,
                'currency' => 'руб.',
                'date'     => \DateTime::createFromFormat('Y-m-d', '2017-03-22'),
                'user_id'  => null
            ],
            [
                'sum'      => 298.51,
                'currency' => 'руб.',
                'date'     => \DateTime::createFromFormat('Y-m-d', '2017-02-24'),
                'user_id'  => null
            ],
            [
                'sum'      => 972.02,
                'currency' => 'руб.',
                'date'     => \DateTime::createFromFormat('Y-m-d', '2017-02-17'),
                'user_id'  => 11022
            ],
            [
                'sum'      => 95.30,
                'currency' => '$',
                'date'     => \DateTime::createFromFormat('Y-m-d', '2016-12-27'),
                'user_id'  => null
            ],
            [
                'sum'      => 147,
                'currency' => 'руб.',
                'date'     => \DateTime::createFromFormat('Y-m-d', '2016-10-10'),
                'user_id'  => 2960
            ],
            [
                'sum'      => 99.50,
                'currency' => 'руб.',
                'date'     => \DateTime::createFromFormat('Y-m-d', '2016-08-18'),
                'user_id'  => null
            ],
            [
                'sum'      => 99.50,
                'currency' => 'руб.',
                'date'     => \DateTime::createFromFormat('Y-m-d', '2016-05-23'),
                'user_id'  => null
            ],
            [
                'sum'      => 343,
                'currency' => 'руб.',
                'date'     => \DateTime::createFromFormat('Y-m-d', '2016-04-19'),
                'user_id'  => null
            ],
            [
                'sum'      => 294,
                'currency' => 'руб.',
                'date'     => \DateTime::createFromFormat('Y-m-d', '2016-02-16'),
                'user_id'  => null
            ],
            [
                'sum'      => 98,
                'currency' => 'руб.',
                'date'     => \DateTime::createFromFormat('Y-m-d', '2016-01-21'),
                'user_id'  => null
            ]
        ];

        return [
            'items' => $data
        ];
    }
}

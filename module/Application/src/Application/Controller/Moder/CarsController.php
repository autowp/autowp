<?php

namespace Application\Controller\Moder;

use Exception;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Autowp\Message\MessageService;

use Application\Form\Moder\CarOrganize as CarOrganizeForm;
use Application\Form\Moder\CarOrganizePictures as CarOrganizePicturesForm;
use Application\HostManager;
use Application\Model\Brand as BrandModel;
use Application\Model\BrandVehicle;
use Application\Model\DbTable;
use Application\Model\Modification;
use Application\Model\PictureItem;
use Application\Model\VehicleType;
use Application\Service\SpecificationsService;

class CarsController extends AbstractActionController
{
    /**
     * @var DbTable\Item\ParentTable
     */
    private $itemParentTable;

    private $translator;

    /**
     * @var HostManager
     */
    private $hostManager;

    /**
     * @var BrandVehicle
     */
    private $brandVehicle;

    /**
     * @var MessageService
     */
    private $message;

    /**
     * @var SpecificationsService
     */
    private $specificationsService;

    /**
     * @var PictureItem
     */
    private $pictureItem;

    public function __construct(
        HostManager $hostManager,
        $translator,
        BrandVehicle $brandVehicle,
        MessageService $message,
        SpecificationsService $specificationsService,
        PictureItem $pictureItem
    ) {
        $this->hostManager = $hostManager;
        $this->translator = $translator;
        $this->brandVehicle = $brandVehicle;
        $this->message = $message;
        $this->specificationsService = $specificationsService;
        $this->pictureItem = $pictureItem;
    }

    private function canMove(DbTable\Item\Row $car)
    {
        return $this->user()->isAllowed('car', 'move');
    }

    /**
     * @param DbTable\Item\Row $car
     * @return string
     */
    private function carModerUrl(DbTable\Item\Row $item, $full = false, $tab = null, $uri = null)
    {
        $url = 'moder/items/item/' . $item['id'];

        if ($tab) {
            $url .= '?' . http_build_query([
                'tab' => $tab
            ]);
        }

        return $this->url()->fromRoute('ng', ['path' => ''], [
            'force_canonical' => $full,
            'uri'             => $uri
        ]) . $url;
    }

    /**
     * @param \Autowp\User\Model\DbTable\User\Row $user
     * @param bool $full
     * @param \Zend\Uri\Uri $uri
     * @return string
     */
    private function userModerUrl(\Autowp\User\Model\DbTable\User\Row $user, $full = false, $uri = null)
    {
        return $this->url()->fromRoute('users/user', [
            'user_id' => $user->identity ? $user->identity : 'user' . $user->id
        ], [
            'force_canonical' => $full,
            'uri'             => $uri
        ]);
    }

    /**
     * @param DbTable\Item\Row $car
     * @return void
     */
    private function redirectToCar(DbTable\Item\Row $car, $tab = null)
    {
        return $this->redirect()->toUrl($this->carModerUrl($car, true, $tab));
    }

    private function canEditMeta(DbTable\Item\Row $car)
    {
        return $this->user()->isAllowed('car', 'edit_meta');
    }

    private function carToForm(DbTable\Item\Row $car)
    {
        return [
            'name'        => $car->name,
            'full_name'   => $car->full_name,
            'catname'     => $car->catname,
            'body'        => $car->body,
            'spec_id'     => $car->spec_inherit ? 'inherited' : ($car->spec_id ? $car->spec_id : ''),
            'is_concept'  => $car->is_concept_inherit ? 'inherited' : (bool)$car->is_concept,
            'is_group'    => $car->is_group,
            'model_year'  => [
                'begin' => $car->begin_model_year,
                'end'   => $car->end_model_year,
            ],
            'begin' => [
                'year'  => $car->begin_year,
                'month' => $car->begin_month,
            ],
            'end' => [
                'year'  => $car->end_year,
                'month' => $car->end_month,
                'today' => $car->today === null ? '' : $car->today
            ],
            'produced' => [
                'count'   => $car->produced,
                'exactly' => $car->produced_exactly
            ],
        ];
    }

    public function carSelectBrandAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();
        $car = $itemTable->fetchRow([
            'id = ?' => (int)$this->params('item_id'),
            'item_type_id IN (?)' => [DbTable\Item\Type::ENGINE, DbTable\Item\Type::VEHICLE]
        ]);
        if (! $car) {
            return $this->notFoundAction();
        }

        $canMove = $this->canMove($car);
        if (! $canMove) {
            return $this->forbiddenAction();
        }

        return [
            'brands' => $itemTable->fetchAll(
                $itemTable->select(true)
                    ->where('item.item_type_id = ?', DbTable\Item\Type::BRAND)
                    ->order(['item.position', 'item.name'])
            ),
            'car' => $car
        ];
    }

    public function rebuildTreeAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();

        $car = $itemTable->find($this->params('item_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        $canEditMeta = $this->canEditMeta($car);

        if (! $canEditMeta) {
            return $this->notFoundAction();
        }

        $cpcTable = new DbTable\Item\ParentCache();

        $cpcTable->rebuildCache($car);

        $url = $this->carModerUrl($car, false, 'tree');

        return $this->redirect()->toUrl($url);
    }

    public function carAutocompleteAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();

        $carRow = $itemTable->find($this->params('item_id'))->current();
        if (! $carRow) {
            return $this->notFoundAction();
        }

        $query = trim($this->params()->fromQuery('q'));

        $result = [];

        $language = $this->language();
        $imageStorage = $this->imageStorage();

        $beginYear = false;
        $endYear = false;
        $today = false;
        $body = false;

        $pattern = "|^" .
                "(([0-9]{4})([-–]([^[:space:]]{2,4}))?[[:space:]]+)?(.*?)( \((.+)\))?( '([0-9]{4})(–(.+))?)?" .
            "$|isu";

        if (preg_match($pattern, $query, $match)) {
            $query = trim($match[5]);
            $body = isset($match[7]) ? trim($match[7]) : null;
            $beginYear = isset($match[9]) ? (int)$match[9] : null;
            $endYear = isset($match[11]) ? $match[11] : null;
            $beginModelYear = isset($match[2]) ? (int)$match[2] : null;
            $endModelYear = isset($match[4]) ? $match[4] : null;

            if ($endYear == 'н.в.') {
                $today = true;
                $endYear = false;
            } else {
                $eyLength = strlen($endYear);
                if ($eyLength) {
                    if ($eyLength == 2) {
                        $endYear = $beginYear - $beginYear % 100 + $endYear;
                    } else {
                        $endYear = (int)$endYear;
                    }
                } else {
                    $endYear = false;
                }
            }

            if ($endModelYear == 'н.в.') {
                $today = true;
                $endModelYear = false;
            } else {
                $eyLength = strlen($endModelYear);
                if ($eyLength) {
                    if ($eyLength == 2) {
                        $endModelYear = $beginModelYear - $beginModelYear % 100 + $endModelYear;
                    } else {
                        $endModelYear = (int)$endModelYear;
                    }
                } else {
                    $endModelYear = false;
                }
            }
        }

        $specTable = new DbTable\Spec();
        $specRow = $specTable->fetchRow([
            'INSTR(?, short_name)' => $query
        ]);

        $specId = null;
        if ($specRow) {
            $specId = $specRow->id;
            $query = trim(str_replace($specRow->short_name, '', $query));
        }

        $allowedItemTypes = [$carRow->item_type_id];
        if (in_array($carRow->item_type_id, [DbTable\Item\Type::VEHICLE, DbTable\Item\Type::ENGINE])) {
            $allowedItemTypes[] = DbTable\Item\Type::CATEGORY;
            $allowedItemTypes[] = DbTable\Item\Type::TWINS;
            $allowedItemTypes[] = DbTable\Item\Type::BRAND;
            $allowedItemTypes[] = DbTable\Item\Type::FACTORY;
        }

        if (in_array($carRow->item_type_id, [DbTable\Item\Type::BRAND])) {
            $allowedItemTypes[] = DbTable\Item\Type::BRAND;
            $allowedItemTypes[] = DbTable\Item\Type::CATEGORY;
        }

        $select = $itemTable->select(true)
            ->where('item.is_group')
            ->where('item.item_type_id IN (?)', $allowedItemTypes)
            ->join('item_language', 'item.id = item_language.item_id', null)
            ->where('item_language.name like ?', $query . '%')
            ->group('item.id')
            ->order(['length(item.name)', 'item.is_group desc', 'item.name'])
            ->limit(15);

        if ($specId) {
            $select->where('spec_id = ?', $specId);
        }

        if ($beginYear) {
            $select->where('item.begin_year = ?', $beginYear);
        }
        if ($today) {
            $select->where('item.today');
        } elseif ($endYear) {
            $select->where('item.end_year = ?', $endYear);
        }
        if ($body) {
            $select->where('item.body like ?', $body . '%');
        }

        if ($beginModelYear) {
            $select->where('item.begin_model_year = ?', $beginModelYear);
        }

        if ($endModelYear) {
            $select->where('item.end_model_year = ?', $endModelYear);
        }

        $expr = $itemTable->getAdapter()->quoteInto(
            'item.id = item_parent_cache.item_id and item_parent_cache.parent_id = ?',
            $carRow->id
        );
        $select
            ->joinLeft('item_parent_cache', $expr, null)
            ->where('item_parent_cache.item_id is null');


        $carRows = $itemTable->fetchAll($select);

        foreach ($carRows as $carRow) {
            $img = false;
            if ($carRow['logo_id']) {
                $imageInfo = $imageStorage->getFormatedImage($carRow['logo_id'], 'brandicon2');
                if ($imageInfo) {
                    $img = $imageInfo->getSrc();
                }
            }

            $result[] = [
                'id'       => (int)$carRow['id'],
                'name'     => $this->car()->formatName($carRow, $language),
                'type'     => 'car',
                'image'    => $img,
            ];
        }

        return new JsonModel($result);
    }

    /**
     * @return DbTable\Item\ParentTable
     */
    private function getCarParentTable()
    {
        return $this->itemParentTable
            ? $this->itemParentTable
            : $this->itemParentTable = new DbTable\Item\ParentTable();
    }

    private function walkUpUntilBrand($id, array $path)
    {
        $urls = [];

        $parentRows = $this->getCarParentTable()->fetchAll([
            'item_id = ?' => $id
        ]);

        $itemTable = $this->catalogue()->getItemTable();

        foreach ($parentRows as $parentRow) {
            $brand = $itemTable->fetchRow([
                'item_type_id = ?' => DbTable\Item\Type::BRAND,
                'id = ?'           => $parentRow->parent_id
            ]);

            if ($brand) {
                $urls[] = $this->url()->fromRoute('catalogue', [
                    'action'        => 'brand-item',
                    'brand_catname' => $brand->catname,
                    'car_catname'   => $parentRow->catname,
                    'path'          => $path
                ]);
            }

            $urls = array_merge(
                $urls,
                $this->walkUpUntilBrand($parentRow->parent_id, array_merge([$parentRow->catname], $path))
            );
        }

        return $urls;
    }

    private function carPublicUrls(DbTable\Item\Row $car)
    {
        if ($car['item_type_id'] == DbTable\Item\Type::FACTORY) {
            return [
                $this->url()->fromRoute('factories/factory', [
                    'action' => 'factory',
                    'id'     => $car['id'],
                ])
            ];
        }

        if ($car['item_type_id'] == DbTable\Item\Type::CATEGORY) {
            return [
                $this->url()->fromRoute('categories', [
                    'action'           => 'category',
                    'category_catname' => $car['catname'],
                ])
            ];
        }

        if ($car['item_type_id'] == DbTable\Item\Type::TWINS) {
            return [
                $this->url()->fromRoute('twins/group', [
                    'id' => $car['id'],
                ])
            ];
        }

        if ($car['item_type_id'] == DbTable\Item\Type::BRAND) {
            return [
                $this->url()->fromRoute('catalogue', [
                    'brand_catname' => $car['catname'],
                ])
            ];
        }

        return $this->walkUpUntilBrand($car->id, []);
    }

    public function carParentSetTypeAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();

        $car = $itemTable->find($this->params('item_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        $parent = $itemTable->find($this->params('parent_id'))->current();
        if (! $parent) {
            return $this->notFoundAction();
        }

        $itemParentRow = $this->getCarParentTable()->fetchRow([
            'item_id = ?'   => $car->id,
            'parent_id = ?' => $parent->id
        ]);

        if (! $itemParentRow) {
            return $this->notFoundAction();
        }

        $itemParentRow->type = $this->params()->fromPost('type');
        $itemParentRow->save();

        $cpcTable = new DbTable\Item\ParentCache();
        $cpcTable->rebuildCache($car);

        return new JsonModel([
            'ok' => true
        ]);
    }

    private function carSelectParentWalk(DbTable\Item\Row $car, $itemTypeId)
    {
        $data = [
            'name'   => $car->getNameData($this->language()),
            'url'    => $this->url()->fromRoute('moder/cars/params', [
                'parent_id' => $car['id']
            ], [], true),
            'childs' => []
        ];

        $itemTable = $this->catalogue()->getItemTable();
        $childRows = $itemTable->fetchAll(
            $itemTable->select(true)
                ->join('item_parent', 'item.id = item_parent.item_id', null)
                ->where('item_parent.parent_id = ?', $car['id'])
                ->where('item.is_group')
                ->where('item.item_type_id IN (?)', $itemTypeId)
                ->order(array_merge(['item_parent.type'], $this->catalogue()->itemOrdering()))
        );
        foreach ($childRows as $childRow) {
            $data['childs'][] = $this->carSelectParentWalk($childRow, $itemTypeId);
        }

        return $data;
    }

    public function carSelectParentAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();
        $car = $itemTable->find($this->params('item_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        $canMove = $this->canMove($car);
        if (! $canMove) {
            return $this->forbiddenAction();
        }

        $parent = $itemTable->find($this->params('parent_id'))->current();

        if ($parent) {
            return $this->forward()->dispatch(self::class, [
                'action'    => 'add-parent',
                'item_id'   => $car->id,
                'parent_id' => $parent->id
            ]);
        }

        $tab = $this->params('tab', 'brands');

        $showBrandsTab = $car->item_type_id != DbTable\Item\Type::CATEGORY;

        if (! $showBrandsTab) {
            $tab = 'categories';
        }

        $showTwinsTab = $car->item_type_id == DbTable\Item\Type::VEHICLE;

        $showFactoriesTab = in_array($car->item_type_id, [
            DbTable\Item\Type::VEHICLE,
            DbTable\Item\Type::ENGINE
        ]);

        $brand = null;
        $brands = [];
        $cars = [];

        if ($tab == 'brands') {
            $brand = $itemTable->fetchRow([
                'item_type_id = ?' => DbTable\Item\Type::BRAND,
                'id = ?'           => (int)$this->params('brand_id')
            ]);

            if ($brand) {
                $rows = $itemTable->fetchAll(
                    $itemTable->select(true)
                        ->where('item.is_group')
                        ->where('item.item_type_id = ?', $car->item_type_id)
                        ->join('item_parent', 'item.id = item_parent.item_id', null)
                        ->where('item_parent.parent_id = ?', $brand->id)
                        ->order(['item.name', 'item.body', 'item.begin_year', 'item.begin_model_year'])
                );

                foreach ($rows as $row) {
                    $cars[] = $this->carSelectParentWalk($row, $car->item_type_id);
                }
            } else {
                $brandModel = new \Application\Model\Brand();

                $brands = $brandModel->getList([
                    'language' => $this->language()
                ], null);
            }
        } elseif ($tab == 'categories') {
            $rows = $itemTable->fetchAll(
                $itemTable->select(true)
                    ->where('item.item_type_id = ?', DbTable\Item\Type::CATEGORY)
                    ->joinLeft('item_parent', 'item.id = item_parent.item_id', null)
                    ->where('item_parent.item_id IS NULL')
                    ->order(['item.name', 'item.body', 'item.begin_year', 'item.begin_model_year'])
            );


            if ($car->item_type_id == DbTable\Item\Type::CATEGORY) {
                $itemTypes = [DbTable\Item\Type::CATEGORY];
            } else {
                $itemTypes = [DbTable\Item\Type::CATEGORY]; // , DbTable\Item\Type::VEHICLE
            }

            foreach ($rows as $row) {
                $cars[] = $this->carSelectParentWalk($row, $itemTypes);
            }
        } elseif ($tab == 'twins') {
            $brand = $itemTable->fetchRow([
                'item_type_id = ?' => DbTable\Item\Type::BRAND,
                'id = ?'           => (int)$this->params('brand_id')
            ]);

            if ($brand) {
                $rows = $itemTable->fetchAll(
                    $itemTable->select(true)
                        ->where('item.item_type_id = ?', DbTable\Item\Type::TWINS)
                        ->join(['ipc1' => 'item_parent_cache'], 'ipc1.parent_id = item.id', null)
                        ->join(['ipc2' => 'item_parent_cache'], 'ipc1.item_id = ipc2.item_id', null)
                        ->where('ipc2.parent_id = ?', $brand->id)
                        ->group('item.id')
                        ->order($this->catalogue()->itemOrdering())
                );

                foreach ($rows as $row) {
                    $cars[] = [
                        'name'   => $row->getNameData($this->language()),
                        'url'    => $this->url()->fromRoute('moder/cars/params', [
                            'parent_id' => $row['id']
                        ], [], true),
                        'childs' => []
                    ];
                }
            } else {
                $brandModel = new \Application\Model\Brand();

                $brands = $brandModel->getList([
                    'language' => $this->language()
                ], function ($select) {
                    $select
                        ->join(['ipc1' => 'item_parent_cache'], 'item.id = ipc1.parent_id', null)
                        ->join('item_parent', 'ipc1.item_id = item_parent.item_id', null)
                        ->join(['twins' => 'item'], 'item_parent.parent_id = twins.id', null)
                        ->where('twins.item_type_id = ?', DbTable\Item\Type::TWINS)
                        ->group('item.id');
                });
            }
        } elseif ($tab == 'factories') {
            $rows = $itemTable->fetchAll(
                $itemTable->select(true)
                    ->where('item_type_id = ?', DbTable\Item\Type::FACTORY)
                    ->order($this->catalogue()->itemOrdering())
            );

            foreach ($rows as $row) {
                $cars[] = [
                    'name'   => $row->getNameData($this->language()),
                    'url'    => $this->url()->fromRoute('moder/cars/params', [
                        'parent_id' => $row['id']
                    ], [], true),
                    'childs' => []
                ];
            }
        }

        return [
            'tab'              => $tab,
            'car'              => $car,
            'brand'            => $brand,
            'brands'           => $brands,
            'cars'             => $cars,
            'showBrandsTab'    => $showBrandsTab,
            'showTwinsTab'     => $showTwinsTab,
            'showFactoriesTab' => $showFactoriesTab
        ];
    }

    private function loadSpecs($table, $parentId, $deep = 0)
    {
        if ($parentId) {
            $filter = ['parent_id = ?' => $parentId];
        } else {
            $filter = ['parent_id is null'];
        }

        $result = [];
        foreach ($table->fetchAll($filter, 'short_name') as $row) {
            $result[$row->id] = str_repeat('...', $deep) . $row->short_name;
            $result = array_replace($result, $this->loadSpecs($table, $row->id, $deep + 1));
        }

        return $result;
    }

    public function organizeAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();

        $car = $itemTable->find($this->params('item_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        $canMove = $this->canMove($car);
        if (! $canMove) {
            return $this->forbiddenAction();
        }

        $itemParentTable = $this->getCarParentTable();
        $itemTable = $this->catalogue()->getItemTable();

        $order = array_merge(['item_parent.type'], $this->catalogue()->itemOrdering());

        $itemParentRows = $itemParentTable->fetchAll(
            $itemParentTable->select(true)
                ->join('item', 'item_parent.item_id = item.id', null)
                ->where('item_parent.parent_id = ?', $car->id)
                ->where('item_parent.type = ?', DbTable\Item\ParentTable::TYPE_DEFAULT)
                ->order($order)
        );

        $childs = [];
        foreach ($itemParentRows as $childRow) {
            $carRow = $itemTable->find($childRow->item_id)->current();
            $childs[$carRow->id] = $this->car()->formatName($carRow, $this->language());
        }

        $specTable = new DbTable\Spec();
        $specOptions = $this->loadSpecs($specTable, null, 0);

        $db = $itemTable->getAdapter();
        $avgSpecId = $db->fetchOne(
            $db->select()
                ->from($itemTable->info('name'), 'AVG(spec_id)')
                ->join('item_parent', 'item.id = item_parent.parent_id', null)
                ->where('item_parent.item_id = ?', $car->id)
        );
        $inheritedSpec = null;
        if ($avgSpecId) {
            $avgSpec = $specTable->find($avgSpecId)->current();
            if ($avgSpec) {
                $inheritedSpec = $avgSpec->short_name;
            }
        }

        $organizeItemTypeId = $car['item_type_id'];
        switch ($organizeItemTypeId) {
            case DbTable\Item\Type::BRAND:
                $organizeItemTypeId = DbTable\Item\Type::VEHICLE;
                break;
        }

        $form = new CarOrganizeForm(null, [
            'itemType'           => $organizeItemTypeId,
            'language'           => $this->language(),
            'childOptions'       => $childs,
            'inheritedIsConcept' => $car->is_concept,
            'specOptions'        => array_replace(['' => '-'], $specOptions),
            'inheritedSpec'      => $inheritedSpec,
            'translator'         => $this->translator
        ]);

        $form->setAttribute('action', $this->url()->fromRoute('moder/cars/params', [], [], true));

        $data = $this->carToForm($car);
        $data['is_group'] = true;

        $vehicleType = new VehicleType();
        $data['vehicle_type_id'] = $vehicleType->getVehicleTypes($car->id);

        $form->populateValues($data);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $values = $form->getData();

                $values['is_group'] = true;

                $newCar = $itemTable->createRow(
                    $this->prepareCarMetaToSave($values)
                );
                $newCar->item_type_id = $organizeItemTypeId;
                $newCar->save();

                $this->setLanguageName($newCar['id'], 'xx', $values['name']);

                $vehicleType->setVehicleTypes($newCar->id, (array)$values['vehicle_type_id']);

                $newCar->updateOrderCache();

                $cpcTable = new DbTable\Item\ParentCache();
                $cpcTable->rebuildCache($newCar);

                $this->log(sprintf(
                    'Создан новый автомобиль %s',
                    htmlspecialchars($this->car()->formatName($newCar, 'en'))
                ), $newCar);

                $this->brandVehicle->create($car->id, $newCar->id);

                $message = sprintf(
                    '%s выбран как родительский автомобиль для %s',
                    htmlspecialchars($this->car()->formatName($car, 'en')),
                    htmlspecialchars($this->car()->formatName($newCar, 'en'))
                );
                $this->log($message, [$car, $newCar]);

                $itemTable->updateInteritance($newCar);


                $childCarRows = $itemTable->find($values['childs']);

                foreach ($childCarRows as $childCarRow) {
                    $this->brandVehicle->create($newCar->id, $childCarRow->id);

                    $message = sprintf(
                        '%s выбран как родительский автомобиль для %s',
                        htmlspecialchars($this->car()->formatName($newCar, 'en')),
                        htmlspecialchars($this->car()->formatName($childCarRow, 'en'))
                    );
                    $this->log($message, [$newCar, $childCarRow]);

                    $this->brandVehicle->remove($car->id, $childCarRow->id);

                    $message = sprintf(
                        '%s перестал быть родительским автомобилем для %s',
                        htmlspecialchars($this->car()->formatName($car, 'en')),
                        htmlspecialchars($this->car()->formatName($childCarRow, 'en'))
                    );
                    $this->log($message, [$car, $childCarRow]);

                    $itemTable->updateInteritance($childCarRow);
                }

                $this->specificationsService->updateActualValues($newCar->id);

                $user = $this->user()->get();
                $ucsTable = new DbTable\User\ItemSubscribe();
                $ucsTable->subscribe($user, $newCar);

                return $this->redirect()->toUrl($this->carModerUrl($car, false, 'catalogue'));
            }
        }

        return [
            'car'    => $car,
            //'childs' => $childs,
            'form'   => $form
        ];
    }

    private function prepareCarMetaToSave(array $values)
    {
        $endYear = (int)$values['end']['year'];

        $today = null;
        if ($endYear) {
            if ($endYear < date('Y')) {
                $today = 0;
            } else {
                $today = null;
            }
        } else {
            if (strlen($values['end']['today'])) {
                $today = $values['end']['today'] ? 1 : 0;
            } else {
                $today = null;
            }
        }

        if (isset($values['is_concept'])) {
            $isConcept = false;
            $isConceptInherit = false;
            if ($values['is_concept'] == 'inherited') {
                $isConceptInherit = true;
            } else {
                $isConcept = (bool)$values['is_concept'];
            }
        } else {
            $isConcept = false;
            $isConceptInherit = true;
        }

        $catname = null;
        if (isset($values['catname'])) {
            if (! $values['catname']) {
                $values['catname'] = $values['name'];
            }

            $filter = new \Autowp\ZFComponents\Filter\FilenameSafe();
            $catname = $filter->filter($values['catname']);
        }

        $result = [
            'name'               => $values['name'],
            'full_name'          => isset($values['full_name']) && $values['full_name'] ? $values['full_name'] : null,
            'catname'            => $catname,
            'body'               => isset($values['body']) ? $values['body'] : '',
            'begin_year'         => $values['begin']['year'] ? $values['begin']['year'] : null,
            'begin_month'        => $values['begin']['month'] ? $values['begin']['month'] : null,
            'end_year'           => $endYear ? $endYear : null,
            'end_month'          => $values['end']['month'] ? $values['end']['month'] : null,
            'today'              => $today,
            'is_concept'         => $isConcept ? 1 : 0,
            'is_concept_inherit' => $isConceptInherit ? 1 : 0,
            'is_group'           => isset($values['is_group']) && $values['is_group'] ? 1 : 0,
            'begin_model_year'   => null,
            'end_model_year'     => null,
            'produced_exactly'   => 0
        ];

        if (array_key_exists('model_year', $values)) {
            $result['begin_model_year'] = $values['model_year']['begin'] ? $values['model_year']['begin'] : null;
            $result['end_model_year']   = $values['model_year']['end'] ? $values['model_year']['end'] : null;
        }

        if (array_key_exists('vehicle_type_id', $values)) {
            $result['vehicle_type_id'] = $values['vehicle_type_id'];
        }

        if (array_key_exists('spec_id', $values)) {
            $specId = null;
            $specInherit = false;
            if ($values['spec_id'] == 'inherited') {
                $specInherit = true;
            } else {
                $specId = (int)$values['spec_id'];
                if (! $specId) {
                    $specId = null;
                }
            }

            $result['spec_id'] = $specId;
            $result['spec_inherit'] = $specInherit ? 1 : 0;
        }

        if (array_key_exists('produced', $values)) {
            $result['produced'] = strlen($values['produced']['count']) ? (int)$values['produced']['count'] : null;
            $result['produced_exactly'] = $values['produced']['exactly'] ? 1 : 0;
        }

        if (array_key_exists('lat', $values)) {
            $result['lat'] = $values['lat'];
        }

        if (array_key_exists('lng', $values)) {
            $result['lng'] = $values['lng'];
        }

        return $result;
    }

    private function setLanguageName($carId, $language, $name)
    {
        $carLangTable = new DbTable\Item\Language();

        $carLangRow = $carLangTable->fetchRow([
            'item_id = ?'  => $carId,
            'language = ?' => $language
        ]);

        if (! $carLangRow) {
            $carLangRow = $carLangTable->createRow([
                'item_id'  => $carId,
                'language' => $language
            ]);
        }
        $carLangRow['name'] = $name;
        $carLangRow->save();
    }

    public function organizePicturesAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();

        $car = $itemTable->find($this->params('item_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        $canMove = $this->canMove($car);
        if (! $canMove) {
            return $this->forbiddenAction();
        }

        $itemParentTable = $this->getCarParentTable();
        $itemTable = $this->catalogue()->getItemTable();
        $imageStorage = $this->imageStorage();

        $childs = [];
        $pictureTable = $this->catalogue()->getPictureTable();
        $rows = $pictureTable->fetchAll(
            $pictureTable->select(true)
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->where('picture_item.item_id = ?', $car->id)
                ->order(['pictures.status', 'pictures.id'])
        );
        foreach ($rows as $row) {
            $request = DbTable\Picture\Row::buildFormatRequest($row->toArray());
            $imageInfo = $imageStorage->getFormatedImage($request, 'picture-thumb');
            if ($imageInfo) {
                $childs[$row->id] = $imageInfo->getSrc();
            }
        }

        $specTable = new DbTable\Spec();
        $specOptions = $this->loadSpecs($specTable, null, 0);

        $db = $itemTable->getAdapter();
        $avgSpecId = $db->fetchOne(
            $db->select()
                ->from($itemTable->info('name'), 'AVG(spec_id)')
                ->join('item_parent', 'item.id = item_parent.parent_id', null)
                ->where('item_parent.item_id = ?', $car->id)
        );
        $inheritedSpec = null;
        if ($avgSpecId) {
            $avgSpec = $specTable->find($avgSpecId)->current();
            if ($avgSpec) {
                $inheritedSpec = $avgSpec->short_name;
            }
        }

        $form = new CarOrganizePicturesForm(null, [
            'language'           => $this->language(),
            'childOptions'       => $childs,
            'inheritedIsConcept' => $car->is_concept,
            'specOptions'        => array_replace(['' => '-'], $specOptions),
            'inheritedSpec'      => $inheritedSpec,
            'translator'         => $this->translator
        ]);

        $form->setAttribute('action', $this->url()->fromRoute('moder/cars/params', [], [], true));

        $data = $this->carToForm($car);
        $data['is_group'] = false;

        $vehicleType = new VehicleType();
        $data['vehicle_type_id'] = $vehicleType->getVehicleTypes($car->id);

        $form->populateValues($data);

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $values = $form->getData();

                $values['is_group'] = false;
                $values['produced_exactly'] = false;
                $values['description'] = '';

                $newCar = $itemTable->createRow(
                    $this->prepareCarMetaToSave($values)
                );
                $newCar->item_type_id = $car->item_type_id;
                $newCar->save();

                $this->setLanguageName($newCar['id'], 'xx', $values['name']);

                $vehicleType->setVehicleTypes($newCar->id, (array)$values['vehicle_type_id']);

                $newCar->updateOrderCache();

                $cpcTable = new DbTable\Item\ParentCache();
                $cpcTable->rebuildCache($newCar);

                $this->log(sprintf(
                    'Создан новый автомобиль %s',
                    htmlspecialchars($this->car()->formatName($newCar, 'en'))
                ), $newCar);

                $car->is_group = 1;
                $car->save();

                $this->brandVehicle->create($car->id, $newCar->id);

                $message = sprintf(
                    '%s выбран как родительский автомобиль для %s',
                    htmlspecialchars($this->car()->formatName($car, 'en')),
                    htmlspecialchars($this->car()->formatName($newCar, 'en'))
                );
                $this->log($message, [$car, $newCar]);

                $itemTable->updateInteritance($newCar);


                $pictureRows = $pictureTable->find($values['childs']);

                foreach ($pictureRows as $pictureRow) {
                    $this->pictureItem->changePictureItem($pictureRow->id, $car->id, $newCar->id);

                    $this->imageStorage()->changeImageName($pictureRow->image_id, [
                        'pattern' => $pictureRow->getFileNamePattern(),
                    ]);

                    $this->log(sprintf(
                        'Картинка %s связана с автомобилем %s',
                        htmlspecialchars($pictureRow->id),
                        htmlspecialchars($this->car()->formatName($car, 'en'))
                    ), [$car, $pictureRow]);
                }

                $brandModel = new BrandModel();

                $this->specificationsService->updateActualValues($newCar->id);

                $user = $this->user()->get();
                $ucsTable = new DbTable\User\ItemSubscribe();
                $ucsTable->subscribe($user, $newCar);

                return $this->redirect()->toUrl($this->carModerUrl($car, false, 'catalogue'));
            }
        }

        return [
            'car'    => $car,
            //'childs' => $childs,
            'form'   => $form
        ];
    }

    private function carMofificationsGroupModifications(DbTable\Item\Row $car, $groupId)
    {
        $modModel = new Modification();
        $mTable = new DbTable\Modification();
        $db = $mTable->getAdapter();
        $itemTable = $this->catalogue()->getItemTable();

        $language = $this->language();

        $select = $mTable->select(true)
            ->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', null)
            ->where('item_parent_cache.item_id = ?', $car->id)
            ->order('modification.name');

        if ($groupId) {
            $select->where('modification.group_id = ?', $groupId);
        } else {
            $select->where('modification.group_id IS NULL');
        }

        $modifications = [];
        foreach ($mTable->fetchAll($select) as $mRow) {
            $picturesCount = $db->fetchOne(
                $db->select()
                    ->from('modification_picture', 'count(1)')
                    ->where('modification_picture.modification_id = ?', $mRow->id)
                    ->join('pictures', 'modification_picture.picture_id = pictures.id', null)
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $car->id)
            );

            $isInherited = $mRow->item_id != $car->id;
            $inheritedFrom = null;

            if ($isInherited) {
                $carRow = $itemTable->fetchRow(
                    $itemTable->select(true)
                        ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                        ->join('modification', 'item.id = modification.item_id', null)
                        ->where('modification.id = ?', $mRow['id'])
                );

                if ($carRow) {
                    $inheritedFrom = [
                        'name' => $this->car()->formatName($carRow, $language),
                        'url'  => $this->carModerUrl($carRow)
                    ];
                }
            }

            $modifications[] = [
                'inherited'     => $isInherited,
                'inheritedFrom' => $inheritedFrom,
                'name'      => $mRow->name,
                'url'       => $this->url()->fromRoute('moder/modification/params', [
                    'action'          => 'edit',
                    'item_id'         => $car['id'],
                    'modification_id' => $mRow->id
                ], [], true),
                'count'     => $picturesCount,
                'canDelete' => ! $isInherited && $modModel->canDelete($mRow->id),
                'deleteUrl' => $this->url()->fromRoute('moder/modification/params', [
                    'action'     => 'delete',
                    'id'         => $mRow->id
                ], [], true)
            ];
        }

        return $modifications;
    }

    public function carModificationsAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();

        $car = $itemTable->find($this->params('item_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        $mgTable = new DbTable\Modification\Group();

        $mgRows = $mgTable->fetchAll(
            $mgTable->select(true)
        );

        $groups = [];
        foreach ($mgRows as $mgRow) {
            $groups[] = [
                'name'          => $mgRow->name,
                'modifications' => $this->carMofificationsGroupModifications($car, $mgRow->id)
            ];
        }

        $groups[] = [
            'name'          => null,
            'modifications' => $this->carMofificationsGroupModifications($car, null),
        ];

        $model = new ViewModel([
            'car'    => $car,
            'groups' => $groups
        ]);
        return $model->setTerminal(true);
    }

    public function carModificationPicturesAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();

        $car = $itemTable->find($this->params('item_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        $mTable = new DbTable\Modification();
        $mpTable = new DbTable\Modification\Picture();
        $mgTable = new DbTable\Modification\Group();
        $pictureTable = new DbTable\Picture();
        $db = $mpTable->getAdapter();
        $imageStorage = $this->imageStorage();
        $language = $this->language();


        $request = $this->getRequest();
        if ($request->isPost()) {
            $picture = (array)$this->params('picture', []);

            foreach ($picture as $pictureId => $modificationIds) {
                $pictureRow = $pictureTable->fetchRow(
                    $pictureTable->select(true)
                        ->where('pictures.id = ?', (int)$pictureId)
                        ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                        ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                        ->where('item_parent_cache.parent_id = ?', $car->id)
                );

                if ($pictureRow) {
                    foreach ($modificationIds as &$modificationId) {
                        $modificationId = (int)$modificationId;

                        $mpRow = $mpTable->fetchRow([
                            'picture_id = ?'      => $pictureRow->id,
                            'modification_id = ?' => $modificationId
                        ]);
                        if (! $mpRow) {
                            $mpRow = $mpTable->createRow([
                                'picture_id'      => $pictureRow->id,
                                'modification_id' => $modificationId
                            ]);
                            $mpRow->save();
                        }
                    }
                    unset($modificationId); // prevent bugs

                    $select = $mpTable->select(true)
                        ->where('modification_picture.picture_id = ?', $pictureRow->id)
                        ->join('modification', 'modification_picture.modification_id = modification.id', null)
                        ->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', null)
                        ->where('item_parent_cache.item_id = ?', $car->id);

                    if ($modificationIds) {
                        $select->where('modification.id not in (?)', $modificationIds);
                    }

                    $mpRows = $mpTable->fetchAll($select);
                    foreach ($mpRows as $mpRow) {
                        $mpRow->delete();
                    }
                }
            }

            return $this->redirectToCar($car, 'modifications');
        }



        $pictures = [];

        $pictureRows = $pictureTable->fetchAll(
            $pictureTable->select(true)
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $car->id)
                ->order('pictures.id')
        );

        foreach ($pictureRows as $pictureRow) {
            $request = DbTable\Picture\Row::buildFormatRequest($pictureRow->toArray());
            $imageInfo = $imageStorage->getFormatedImage($request, 'picture-thumb');

            $modificationIds = $db->fetchCol(
                $db->select()
                    ->from('modification_picture', 'modification_id')
                    ->where('picture_id = ?', $pictureRow->id)
            );

            $pictures[] = [
                'id'              => $pictureRow->id,
                'name'            => $this->pic()->name($pictureRow, $language),
                'url'             => $this->pic()->href($pictureRow),
                'src'             => $imageInfo ? $imageInfo->getSrc() : null,
                'modificationIds' => $modificationIds
            ];
        }


        $mgRows = $mgTable->fetchAll(
            $mgTable->select(true)
        );

        $groups = [];
        foreach ($mgRows as $mgRow) {
            $mRows = $mTable->fetchAll(
                $mTable->select(true)
                ->where('modification.group_id = ?', $mgRow->id)
                ->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', null)
                ->where('item_parent_cache.item_id = ?', $car->id)
                ->order('modification.name')
            );

            $modifications = [];
            foreach ($mRows as $mRow) {
                $modifications[] = [
                    'id'     => $mRow->id,
                    'name'   => $mRow->name,
                ];
            }

            $groups[] = [
                'name'          => $mgRow->name,
                'modifications' => $modifications
            ];
        }

        $mRows = $mTable->fetchAll(
            $mTable->select(true)
            ->where('modification.group_id IS NULL')
            ->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', null)
            ->where('item_parent_cache.item_id = ?', $car->id)
            ->order('modification.name')
        );

        $modifications = [];
        foreach ($mRows as $mRow) {
            $modifications[] = [
                'id'   => $mRow->id,
                'name' => $mRow->name,
            ];
        }

        $groups[] = [
            'name'          => null,
            'modifications' => $modifications
        ];


        return [
            'pictures' => $pictures,
            'groups'   => $groups
        ];
    }

    public function addParentAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        /*if (!$this->getRequest()->isPost()) {
         return $this->notFoundAction();
         }*/

        $itemTable = $this->catalogue()->getItemTable();

        $car = $itemTable->find($this->params('item_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        $canEditMeta = $this->canEditMeta($car);

        if (! $canEditMeta) {
            return $this->notFoundAction();
        }

        $parentCar = $itemTable->find($this->params('parent_id'))->current();
        if (! $parentCar) {
            return $this->notFoundAction();
        }

        $this->brandVehicle->create($parentCar->id, $car->id);

        $itemTable->updateInteritance($car);

        $vehicleType = new VehicleType();
        $vehicleType->refreshInheritanceFromParents($car->id);

        $this->specificationsService->updateActualValues($car->id);

        $message = sprintf(
            '%s выбран как родительский автомобиль для %s',
            htmlspecialchars($this->car()->formatName($parentCar, 'en')),
            htmlspecialchars($this->car()->formatName($car, 'en'))
        );
        $this->log($message, [$car, $parentCar]);

        $ucsTable = new DbTable\User\ItemSubscribe();
        $user = $this->user()->get();

        $subscribers = [];
        foreach ($ucsTable->getItemSubscribers($car) as $subscriber) {
            $subscribers[$subscriber->id] = $subscriber;
        }

        foreach ($ucsTable->getItemSubscribers($parentCar) as $subscriber) {
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
                    $this->car()->formatName($car, $subscriber->language),
                    $this->carModerUrl($car, true, null, $uri),
                    $this->car()->formatName($parentCar, $subscriber->language),
                    $this->carModerUrl($parentCar, true, null, $uri)
                );

                $this->message->send(null, $subscriber->id, $message);
            }
        }

        $url = $this->carModerUrl($car, false, 'catalogue');
        if ($this->getRequest()->isXmlHttpRequest()) {
            return new JsonModel([
                'ok'  => true,
                'url' => $url
            ]);
        } else {
            return $this->redirect()->toUrl($url);
        }
    }
}

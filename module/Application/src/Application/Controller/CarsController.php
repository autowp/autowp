<?php

namespace Application\Controller;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Autowp\User\Model\DbTable\User;
use Autowp\User\Model\DbTable\User\Row as UserRow;

use Application\HostManager;
use Application\Model\Message;
use Application\Model\Brand as BrandModel;
use Application\Model\DbTable;
use Application\Model\DbTable\Attr;
use Application\Model\DbTable\Engine;
use Application\Model\DbTable\User\CarSubscribe as UserCarSubscribe;
use Application\Model\DbTable\Vehicle;
use Application\Model\DbTable\Vehicle\Row as VehicleRow;
use Application\Paginator\Adapter\Zend1DbTableSelect;
use Application\Service\SpecificationsService;

class CarsController extends AbstractActionController
{
    /**
     * @var Form
     */
    private $filterForm;

    /**
     * @var HostManager
     */
    private $hostManager;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var Message
     */
    private $message;

    public function __construct(
        HostManager $hostManager,
        Form $filterForm,
        SpecificationsService $specsService,
        Message $message
    ) {

        $this->hostManager = $hostManager;
        $this->filterForm = $filterForm;
        $this->specsService = $specsService;
        $this->message = $message;
    }

    private function carModerUrl(VehicleRow $car, $uri = null)
    {
        return $this->url()->fromRoute('moder/cars/params', [
            'action' => 'car',
            'car_id' => $car->id,
        ], [
            'force_canonical' => true,
            'uri'             => $uri
        ]);
    }

    private function editorUrl($car, $tab = null)
    {
        return $this->url()->fromRoute('cars/params', [
            'action' => 'car-specifications-editor',
            'car_id' => $car->id,
            'tab'    => $tab
        ]);
    }

    public function carSpecificationsEditorAction()
    {
        if (! $this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $editOnlyMode = $this->user()->get()->specs_weight < 0.10;

        $carTable = new Vehicle();

        $car = $carTable->find($this->params('car_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        $user = $this->user()->get();

        $result = $this->specsService->getCarForm($car, $user, [
            'editOnlyMode' => $editOnlyMode,
        ], $this->language());

        $carForm = $result['form'];
        $carFormData = $result['data'];

        $carForm->setAttribute('action', $this->url()->fromRoute('cars/params', [
            'form' => 'car',
            'tab'  => 'spec'
        ], [], true));

        $request = $this->getRequest();
        if ($request->isPost()) {
            $carForm->setData($this->params()->fromPost());
            if ($carForm->isValid()) {
                $this->specsService->saveCarAttributes($car, $carForm->getData(), $user);

                $user->invalidateSpecsVolume();

                $contribPairs = $this->specsService->getContributors(1, [$car->id]);
                $contributors = [];
                if ($contribPairs) {
                    $userTable = new User();
                    $contributors = $userTable->fetchAll(
                        $userTable->select(true)
                            ->where('id IN (?)', array_keys($contribPairs))
                            ->where('not deleted')
                    );
                }

                foreach ($contributors as $contributor) {
                    if ($contributor->id != $user->id) {
                        $uri = $this->hostManager->getUriByLanguage($contributor->language);

                        $message = sprintf(
                            $this->translate('pm/user-%s-edited-vehicle-specs-%s', 'default', $contributor->language),
                            $this->userUrl($user, $uri),
                            $this->car()->formatName($car, $contributor->language)
                        );

                        $this->message->send(null, $contributor->id, $message);
                    }
                }

                return $this->redirect()->toUrl($this->editorUrl($car, 'spec'));
            }
        }
        $engine = $carTable->find($car->engine_item_id)->current();
        $engineInherited = $car->engine_inherit;
        $engineInheritedFrom = [];
        if ($engine && $car->engine_inherit) {
            $carRows = $carTable->fetchAll(
                $carTable->select(true)
                    ->join('item_parent_cache', 'cars.id = item_parent_cache.parent_id', null)
                    ->where('item_parent_cache.item_id = ?', $car->id)
                    ->where('cars.engine_item_id = ?', $engine->id)
                    ->where('cars.id <> ?', $car->id)
                    ->order('item_parent_cache.diff desc')
            );

            foreach ($carRows as $carRow) {
                $engineInheritedFrom[] = [
                    'name' => $this->car()->formatName($carRow, $this->language()),
                    'url'  => $this->url()->fromRoute('moder/cars/params', [
                        'action' => 'car',
                        'car_id' => $carRow->id
                    ])
                ];
            }
        }

        $tabs = [
            'info' => [
                'icon'  => 'fa fa-info',
                'title' => 'specifications-editor/tabs/info',
                'count' => 0,
            ],
            'engine' => [
                'icon'  => 'glyphicon glyphicon-align-left',
                'title' => 'specifications-editor/tabs/engine',
                'count' => (bool)$engine,
            ],
            'spec' => [
                'icon'  => 'fa fa-car',
                'title' => 'specifications-editor/tabs/specs',
                'count' => 0,
            ],
            'result' => [
                'icon'      => 'fa fa-table',
                'title'     => 'specifications-editor/tabs/result',
                'data-load' => $this->url()->fromRoute('cars/params', [
                    'action' => 'car-specs'
                ], [], true),
                'count' => 0,
            ],
            'admin' => [
                'icon'      => 'fa fa-cog',
                'title'     => 'specifications-editor/tabs/admin',
                'count' => 0,
            ],
        ];

        $currentTab = $this->params('tab', 'info');
        foreach ($tabs as $id => &$tab) {
            $tab['active'] = $id == $currentTab;
        }
        
        if ($car->item_type_id != DbTable\Item\Type::VEHICLE) {
            unset($tabs['engine']);
        }

        $isSpecsAdmin = $this->user()->isAllowed('specifications', 'admin');

        if (! $isSpecsAdmin) {
            unset($tabs['admin']);
        }

        return [
            'car'                 => $car,
            'engine'              => $engine,
            'engineInherited'     => $engineInherited,
            'engineInheritedFrom' => $engineInheritedFrom,
            'form'                => $carForm,
            'formData'            => $carFormData,
            'tabs'                => $tabs,
            'isSpecsAdmin'        => $isSpecsAdmin,
            'service'             => $this->specsService
        ];
    }

    public function carSpecsAction()
    {
        if (! $this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $carTable = new Vehicle();

        $car = $carTable->find($this->params('car_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        $specs = $this->specsService->specifications([$car], [
            'language' => $this->language()
        ]);

        $viewModel = new ViewModel([
            'specs' => $specs,
        ]);

        return $viewModel->setTerminal(true);
    }

    public function moveAction()
    {
        if (! $this->user()->isAllowed('specifications', 'admin')) {
            return $this->forward('forbidden', 'error');
        }

        //$carTable = new Vehicle();
        $aitTable = new Attr\ItemType();

        $itemId = (int)$this->params('item_id');
        $itemType = $aitTable->find($this->params('item_type_id'))->current();
        if (! $itemType) {
            return $this->notFoundAction();
        }

        $toItemId = (int)$this->params('to_item_id');
        $toItemType = $aitTable->find($this->params('to_item_type_id'))->current();
        if (! $toItemType) {
            return $this->notFoundAction();
        }

        $userValueTable = new Attr\UserValue();
        $attrTable = new Attr\Attribute();

        $eUserValueRows = $userValueTable->fetchAll([
            'item_id = ?'      => $itemId,
            'item_type_id = ?' => $itemType->id
        ]);

        foreach ($eUserValueRows as $eUserValueRow) {
            // check for value in dest

            $cUserValueRow = $userValueTable->fetchRow([
                'item_id = ?'      => $toItemId,
                'item_type_id = ?' => $toItemType->id,
                'attribute_id = ?' => $eUserValueRow->attribute_id,
                'user_id = ?'      => $eUserValueRow->user_id
            ]);

            if ($cUserValueRow) {
                throw new Exception("Value row already exists");
            }

            $attrRow = $attrTable->find($eUserValueRow->attribute_id)->current();

            if (! $attrRow) {
                throw new Exception("Attr not found");
            }

            $dataTable = $this->specsService->getUserValueDataTable($attrRow->type_id);

            $eDataRows = $dataTable->fetchAll([
                'attribute_id = ?' => $eUserValueRow->attribute_id,
                'item_id = ?'      => $eUserValueRow->item_id,
                'item_type_id = ?' => $eUserValueRow->item_type_id,
                'user_id = ?'      => $eUserValueRow->user_id
            ]);

            foreach ($eDataRows as $eDataRow) {
                // check for data row existance
                $filter = [
                    'attribute_id = ?' => $eDataRow->attribute_id,
                    'item_id = ?'      => $toItemId,
                    'item_type_id = ?' => $toItemType->id,
                    'user_id = ?'      => $eDataRow->user_id
                ];
                if ($attrRow->isMultiple()) {
                    $filter['ordering = ?'] = $eDataRow->ordering;
                }
                $cDataRow = $dataTable->fetchRow($filter);

                if ($cDataRow) {
                    throw new Exception("Data row already exists");
                }
            }

            $eUserValueRow->setFromArray([
                'item_id'      => $toItemId,
                'item_type_id' => $toItemType->id,
            ]);
            $eUserValueRow->save();

            foreach ($eDataRows as $eDataRow) {
                $eDataRow->setFromArray([
                    'item_id'      => $toItemId,
                    'item_type_id' => $toItemType->id,
                ]);
                $eDataRow->save();
            }

            $this->specsService->updateActualValues(
                $toItemType->id,
                $toItemId
            );
            $this->specsService->updateActualValues(
                $itemType->id,
                $itemId
            );
        }

        return $this->redirect()->toRoute('cars/params', [
            'action' => 'car-specifications-editor'
        ], [], true);
    }

    public function specsAdminAction()
    {
        $carTable = new Vehicle();

        if (! $this->user()->isAllowed('specifications', 'admin')) {
            return $this->forward('forbidden', 'error');
        }

        $aitTable = new Attr\ItemType();

        $itemId = (int)$this->params('item_id');
        $itemType = $aitTable->find($this->params('item_type_id'))->current();
        if (! $itemType) {
            return $this->notFoundAction();
        }

        $auvTable = new Attr\UserValue();

        $rows = $auvTable->fetchAll([
            'item_id = ?'      => $itemId,
            'item_type_id = ?' => $itemType->id
        ], 'update_date');

        $language = $this->language();

        $values = [];
        foreach ($rows as $row) {
            $attribute = $row->findParentRow(Attr\Attribute::class);
            $user = $row->findParentRow(User::class);
            $unit = $attribute->findParentRow(Attr\Unit::class);
            $values[] = [
                'attribute' => $attribute,
                'unit'      => $unit,
                'user'      => $user,
                'value'     => $this->specsService->getActualValueText(
                    $attribute->id,
                    $itemType->id,
                    $row->item_id,
                    $language
                ),
                'userValue' => $this->specsService->getUserValueText(
                    $attribute->id,
                    $itemType->id,
                    $row->item_id,
                    $user->id,
                    $language
                ),
                'date'      => $row->getDateTime('update_date'),
                'deleteUrl' => $this->url()->fromRoute('cars/params', [
                    'action'       => 'delete-value',
                    'attribute_id' => $row->attribute_id,
                    'item_type_id' => $row->item_type_id,
                    'item_id'      => $row->item_id,
                    'user_id'      => $row->user_id
                ], [], true)
            ];
        }

        return [
            'values'     => $values,
            'itemId'     => $itemId,
            'itemTypeId' => $itemType->id
        ];
    }

    public function deleteValueAction()
    {
        if (! $this->user()->isAllowed('specifications', 'admin')) {
            return $this->forward('forbidden', 'error');
        }

        $request = $this->getRequest();
        if (! $request->isPost()) {
            return $this->forward('forbidden', 'error');
        }

        $aitTable = new Attr\ItemType();

        $itemType = $aitTable->find($this->params('item_type_id'))->current();
        if (! $itemType) {
            return $this->notFoundAction();
        }

        $itemId = (int)$this->params('item_id');
        $userId = (int)$this->params('user_id');

        $this->specsService->deleteUserValue((int)$this->params('attribute_id'), $itemType->id, $itemId, $userId);

        return $this->redirect()->toUrl($request->getServer('HTTP_REFERER'));
    }

    public function attrsChangeLogAction()
    {
        if (! $this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $this->filterForm->setAttribute('action', $this->url()->fromRoute('cars/params', [
            'action' => 'attrs-change-log'
        ], [], true));

        if ($this->getRequest()->isPost()) {
            $this->filterForm->setData($this->params()->fromPost());
            if ($this->filterForm->isValid()) {
                $values = $this->filterForm->getData();

                return $this->redirect()->toRoute('cars/params', [
                    'user_id' => $values['user_id'] ? $values['user_id'] : null,
                    'page'    => null
                ], [], true);
            }
        }

        $language = $this->language();

        $userValues = new Attr\UserValue();

        $select = $userValues->select()
            ->order('update_date DESC');

        $this->filterForm->setData($this->params()->fromRoute());

        if ($this->filterForm->isValid()) {
            $values = $this->filterForm->getData();

            if ($user_id = $values['user_id']) {
                $select->where('user_id = ?', $user_id);
            }
        }

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage(30)
            ->setPageRange(20)
            ->setCurrentPageNumber($this->params('page'));

        $items = [];

        $cars = new Vehicle();
        
        $isModerator = $this->user()->inheritsRole('moder');

        foreach ($paginator->getCurrentItems() as $row) {
            $objectName = null;
            $editorUrl = null;
            $moderUrl = null;
            $path = [];

            $itemType = $row->findParentRow(Attr\ItemType::class);
            $car = $cars->find($row->item_id)->current();
            if ($car) {
                $objectName = $this->car()->formatName($car, $this->language());
                $editorUrl = $this->url()->fromRoute('cars/params', [
                    'action' => 'car-specifications-editor',
                    'car_id' => $car->id
                ]);

                if ($isModerator) {
                    $moderUrl = $this->url()->fromRoute('moder/cars/params', [
                        'action' => 'car',
                        'car_id' => $car->id
                    ]);
                }
            }

            $attribute = $row->findParentRow(Attr\Attribute::class);
            if ($attribute) {
                $parents = [];
                $parent = $attribute;
                do {
                    $parents[] = $parent->name;
                } while ($parent = $parent->findParentRow(Attr\Attribute::class));

                $path = array_reverse($parents);
            }

            $user = $row->findParentRow(User::class);

            $items[] = [
                'date'     => $row->getDateTime('update_date'),
                'user'     => $user,
                'itemType' => [
                    'id'   => $itemType->id,
                    'name' => $itemType->name,
                ],
                'object'   => [
                    'name'      => $objectName,
                    'editorUrl' => $editorUrl,
                    'moderUrl'  => $moderUrl
                ],
                'path'     => $path,
                'value'    => $this->specsService->getUserValueText(
                    $attribute->id,
                    $itemType->id,
                    $row->item_id,
                    $user->id,
                    $language
                ),
                'unit'     => $attribute->findParentRow(Attr\Unit::class)
            ];
        }

        return [
            'paginator'   => $paginator,
            'filter'      => $this->filterForm,
            'items'       => $items,
            'isModerator' => $isModerator
        ];
    }

    private function userUrl(UserRow $user, $uri = null)
    {
        return $this->url()->fromRoute('users/user', [
            'user_id' => $user->identity ? $user->identity : 'user' . $user->id
        ], [
            'force_canonical' => true,
            'uri'             => $uri
        ]);
    }

    public function cancelCarEngineAction()
    {
        if (! $this->user()->isAllowed('specifications', 'edit-engine')) {
            return $this->forbiddenAction();
        }

        $carTable = new Vehicle();

        $car = $carTable->find($this->params('car_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        $engine = $car->findParentRow(Engine::class);
        $car->engine_inherit = 0;
        $car->engine_item_id = null;
        $car->save();

        $carTable->updateInteritance($car);

        $this->specsService->updateActualValues(1, $car->id);

        if ($engine) {
            $message = sprintf(
                'У автомобиля %s убран двигатель (был %s)',
                htmlspecialchars($this->car()->formatName($car, 'en')),
                htmlspecialchars($engine->name)
            );
            $this->log($message, $car);

            $user = $this->user()->get();
            $ucsTable = new UserCarSubscribe();

            foreach ($ucsTable->getCarSubscribers($car) as $subscriber) {
                if ($subscriber && ($subscriber->id != $user->id)) {
                    $uri = $this->hostManager->getUriByLanguage($subscriber->language);

                    $message = sprintf(
                        $this->translate(
                            'pm/user-%s-canceled-vehicle-engine-%s-%s-%s',
                            'default',
                            $subscriber->language
                        ),
                        $this->userUrl($user, $uri),
                        $engine->name,
                        $this->car()->formatName($car, $subscriber->language),
                        $this->carModerUrl($car, $uri)
                    );

                    $this->message->send(null, $subscriber->id, $message);
                }
            }
        }

        return $this->redirect()->toUrl($this->editorUrl($car, 'engine'));
    }

    public function inheritCarEngineAction()
    {
        if (! $this->user()->isAllowed('specifications', 'edit-engine')) {
            return $this->forbiddenAction();
        }

        $carTable = new Vehicle();

        $car = $carTable->find($this->params('car_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        if (! $car->engine_inherit) {
            $car->engine_item_id = null;
            $car->engine_inherit = 1;
            $car->save();

            $carTable->updateInteritance($car);

            $this->specsService->updateActualValues(1, $car->id);

            $message = sprintf(
                'У автомобиля %s установлено наследование двигателя',
                htmlspecialchars($this->car()->formatName($car, 'en'))
            );
            $this->log($message, $car);

            $user = $this->user()->get();
            $ucsTable = new UserCarSubscribe();

            foreach ($ucsTable->getCarSubscribers($car) as $subscriber) {
                if ($subscriber && ($subscriber->id != $user->id)) {
                    $uri = $this->hostManager->getUriByLanguage($subscriber->language);

                    $message = sprintf(
                        $this->translate(
                            'pm/user-%s-set-inherited-vehicle-engine-%s-%s',
                            'default',
                            $subscriber->language
                        ),
                        $this->userUrl($user, $uri),
                        $this->car()->formatName($car, $subscriber->language),
                        $this->carModerUrl($car, $uri)
                    );

                    $this->message->send(null, $subscriber->id, $message);
                }
            }
        }

        return $this->redirect()->toUrl($this->editorUrl($car, 'engine'));
    }

    private function enginesWalkTree($parentId, $brandId)
    {
        $itemTable = new Vehicle();
        $select = $itemTable->select(true)
            ->where('cars.item_type_id = ?', DbTable\Item\Type::ENGINE)
            ->order('cars.name');
        if ($brandId) {
            $select
                ->join('brand_item', 'cars.id = brand_item.car_id', null)
                ->where('brand_item.brand_id = ?', $brandId);
        }
        if ($parentId) {
            $select
                ->join('car_parent', 'cars.id = car_parent.car_id', null)
                ->where('car_parent.parent_id = ?', $parentId);
        }

        $rows = $itemTable->fetchAll($select);

        $engines = [];
        foreach ($rows as $row) {
            $engines[] = [
                'id'     => $row->id,
                'name'   => $row->getNameData($this->language()),
                'childs' => $this->enginesWalkTree($row->id, null)
            ];
        }

        return $engines;
    }

    public function selectCarEngineAction()
    {
        if (! $this->user()->isAllowed('specifications', 'edit-engine')) {
            return $this->forbiddenAction();
        }

        if (! $this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $carTable = new Vehicle();

        $car = $carTable->fetchRow([
            'id = ?'           => (int)$this->params('car_id'),
            'item_type_id = ?' => DbTable\Item\Type::VEHICLE
        ]);
        if (! $car) {
            return $this->notFoundAction();
        }

        $language = $this->language();

        $brandModel = new BrandModel();

        $brand = $brandModel->getBrandByCatname($this->params()->fromPost('brand'), $language);

        if (! $brand) {
            $brands = $brandModel->getList($language, function ($select) {
                $select
                    ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                    ->group('brands.id');
            });

            return [
                'car'     => $car,
                'brand'   => false,
                'brands'  => $brands,
                'engines' => []
            ];
        }

        $engine = $carTable->fetchRow([
            'id = ?'           => (int)$this->params()->fromPost('engine'),
            'item_type_id = ?' => DbTable\Item\Type::ENGINE
        ]);
        if (! $engine) {
            return [
                'car'     => $car,
                'brand'   => $brand,
                'brands'  => [],
                'engines' => $this->enginesWalkTree(null, $brand['id'])
            ];
        }

        $car->engine_inherit = 0;
        $car->engine_item_id = $engine->id;
        $car->save();

        $carTable->updateInteritance($car);

        $this->specsService->updateActualValues(1, $car->id);

        $user = $this->user()->get();
        $ucsTable = new UserCarSubscribe();

        $message = sprintf(
            'Автомобилю %s назначен двигатель %s',
            htmlspecialchars($this->car()->formatName($car, 'en')),
            htmlspecialchars($engine->name)
        );
        $this->log($message, $car);

        foreach ($ucsTable->getCarSubscribers($car) as $subscriber) {
            if ($subscriber && ($subscriber->id != $user->id)) {
                $uri = $this->hostManager->getUriByLanguage($subscriber->language);

                $message = sprintf(
                    $this->translate('pm/user-%s-set-vehicle-engine-%s-%s-%s', 'default', $subscriber->language),
                    $this->userUrl($user, $uri),
                    $engine->name,
                    $this->car()->formatName($car, $subscriber->language),
                    $this->carModerUrl($car, $uri)
                );

                $this->message->send(null, $subscriber->id, $message);
            }
        }

        return $this->redirect()->toUrl($this->editorUrl($car, 'engine'));
    }

    public function refreshInheritanceAction()
    {
        if (! $this->user()->isAllowed('specifications', 'admin')) {
            return $this->forbiddenAction();
        }

        $carTable = new Vehicle();

        $car = $carTable->find($this->params('car_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        $carTable->updateInteritance($car);

        $this->specsService->updateActualValues(1, $car->id);

        return $this->redirect()->toUrl($this->editorUrl($car, 'admin'));
    }

    public function editValueAction()
    {
        if (! $this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $attrId = (int)$this->params('attr');
        $itemTypeId = (int)$this->params('item_type');
        $itemId = (int)$this->params('item');

        $language = $this->language();

        $form = $this->specsService->getEditValueForm($attrId, $itemTypeId, $itemId, $language);
        if (! $form) {
            return $this->notFoundAction();
        }

        $form->setAction($this->url()->fromRoute(null, [], [], true));

        $viewModel = new ViewModel([
            'form' => $form
        ]);

        return $viewModel->setTerminal(true);
    }

    public function lowWeightAction()
    {
    }

    public function datelessAction()
    {
        if (! $this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $listCars = [];

        $carTable = $this->catalogue()->getCarTable();

        $select = $carTable->select(true)
            ->where('cars.begin_year is null and cars.begin_model_year is null')
            ->order($this->catalogue()->carsOrdering());

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage(20)
            ->setCurrentPageNumber($this->params('page'));

        foreach ($paginator->getCurrentItems() as $row) {
            $listCars[] = $row;
        }

        return [
            'paginator'     => $paginator,
            'childListData' => $this->car()->listData($listCars),
        ];
    }
}

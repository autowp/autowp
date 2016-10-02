<?php

namespace Application\Controller;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Application\HostManager;
use Application\Model\Message;
use Application\Model\Brand as BrandModel;
use Application\Model\DbTable\Engine;
use Application\Model\DbTable\User;
use Application\Model\DbTable\User\CarSubscribe as UserCarSubscribe;
use Application\Model\DbTable\User\Row as UserRow;
use Application\Model\DbTable\Vehicle\Row as VehicleRow;
use Application\Paginator\Adapter\Zend1DbTableSelect;
use Application\Service\SpecificationsService;

use Attrs_Attributes;
use Attrs_Item_Types;
use Attrs_User_Values;
use Cars;

class CarsController extends AbstractActionController
{
    /**
     * @var Engine
     */
    private $engineTable = null;

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

    public function __construct(
        HostManager $hostManager,
        Form $filterForm,
        SpecificationsService $specsService)
    {
        $this->hostManager = $hostManager;
        $this->filterForm = $filterForm;
        $this->specsService = $specsService;
    }

    /**
     * @return Engine
     */
    private function getEngineTable()
    {
        return $this->engineTable
            ? $this->engineTable
            : $this->engineTable = new Engine();
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
        if (!$this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $editOnlyMode = $this->user()->get()->specs_weight < 0.10;

        $carTable = new Cars();

        $car = $carTable->find($this->params('car_id'))->current();
        if (!$car) {
            return $this->notFoundAction();
        }

        $user = $this->user()->get();

        $result = $this->specsService->getCarForm($car, $user, [
            'editOnlyMode' => $editOnlyMode,
        ], $this->language());

        $carForm = $result['form'];
        $carFormData = $result['data'];

        //print_r($carFormData['allValues']); exit;

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

                $mModel = new Message();

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
                            $car->getFullName($contributor->language)
                        );

                        $mModel->send(null, $contributor->id, $message);
                    }
                }

                return $this->redirect()->toUrl($this->editorUrl($car, 'spec'));
            }
        }

        $engine = $car->findParentRow(Engine::class);
        $engineInherited = $car->engine_inherit;
        $engineInheritedFrom = [];
        if ($engine && $car->engine_inherit) {
            $carRows = $carTable->fetchAll(
                $carTable->select(true)
                    ->join('car_parent_cache', 'cars.id = car_parent_cache.parent_id', null)
                    ->where('car_parent_cache.car_id = ?', $car->id)
                    ->where('cars.engine_id = ?', $engine->id)
                    ->where('cars.id <> ?', $car->id)
                    ->order('car_parent_cache.diff desc')
            );

            foreach ($carRows as $carRow) {
                $engineInheritedFrom[] = [
                    'name' => $carRow->getFullName($this->language()),
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

        $isSpecsAdmin = $this->user()->isAllowed('specifications', 'admin');

        if (!$isSpecsAdmin) {
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
        if (!$this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $carTable = new Cars();

        $car = $carTable->find($this->params('car_id'))->current();
        if (!$car) {
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
        if (!$this->user()->isAllowed('specifications', 'admin')) {
            return $this->forward('forbidden', 'error');
        }

        //$carTable = new Cars();
        $aitTable = new Attrs_Item_Types();

        $itemId = (int)$this->params('item_id');
        $itemType = $aitTable->find($this->params('item_type_id'))->current();
        if (!$itemType) {
            return $this->notFoundAction();
        }

        $toItemId = (int)$this->params('to_item_id');
        $toItemType = $aitTable->find($this->params('to_item_type_id'))->current();
        if (!$toItemType) {
            return $this->notFoundAction();
        }

        $userValueTable = new Attrs_User_Values();
        $attrTable = new Attrs_Attributes();

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
                print "Value row already exists \n";
                exit;
            }

            $attrRow = $attrTable->find($eUserValueRow->attribute_id)->current();

            if (!$attrRow) {
                print "Attr not found";
                exit;
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
                    print "Data row already exists \n";
                    exit;
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
        $carTable = new Cars();

        if (!$this->user()->isAllowed('specifications', 'admin')) {
            return $this->forward('forbidden', 'error');
        }

        $aitTable = new Attrs_Item_Types();

        $itemId = (int)$this->params('item_id');
        $itemType = $aitTable->find($this->params('item_type_id'))->current();
        if (!$itemType) {
            return $this->notFoundAction();
        }

        $auvTable = new Attrs_User_Values();

        $rows = $auvTable->fetchAll([
            'item_id = ?'      => $itemId,
            'item_type_id = ?' => $itemType->id
        ], 'update_date');

        $language = $this->language();

        $values = [];
        foreach ($rows as $row) {
            $attribute = $row->findParentAttrs_Attributes();
            $user = $row->findParentRow(User::class);
            $unit = $attribute->findParentAttrs_Units();
            $values[] = [
                'attribute' => $attribute,
                'unit'      => $unit,
                'user'      => $user,
                'value'     => $this->specsService->getActualValueText($attribute->id, $itemType->id, $row->item_id, $language),
                'userValue' => $this->specsService->getUserValueText($attribute->id, $itemType->id, $row->item_id, $user->id, $language),
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
        if (!$this->user()->isAllowed('specifications', 'admin')) {
            return $this->forward('forbidden', 'error');
        }

        $request = $this->getRequest();
        if (!$request->isPost()) {
            return $this->forward('forbidden', 'error');
        }

        $aitTable = new Attrs_Item_Types();

        $itemType = $aitTable->find($this->params('item_type_id'))->current();
        if (!$itemType) {
            return $this->notFoundAction();
        }

        $itemId = (int)$this->params('item_id');
        $userId = (int)$this->params('user_id');

        $this->specsService->deleteUserValue((int)$this->params('attribute_id'), $itemType->id, $itemId, $userId);

        return $this->redirect()->toUrl($request->getServer('HTTP_REFERER'));
    }

    public function engineSpecEditorAction()
    {
        if (!$this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $editOnlyMode = $this->user()->get()->specs_weight < 0.10;

        $engines = $this->getEngineTable();

        $engine = $engines->find($this->params('engine_id'))->current();
        if (!$engine) {
            return $this->notFoundAction();
        }

        $user = $this->user()->get();

        $result = $this->specsService->getEngineForm($engine, $user, [
            'editOnlyMode' => $editOnlyMode,
        ], $this->language());

        $form = $result['form'];
        $formData = $result['data'];

        $form->setAttribute('action', $this->url()->fromRoute(null, [], [], true));

        $request = $this->getRequest();
        if ($request->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {

                $this->specsService->saveEngineAttributes($engine, $form->getData(), $user);

                $user->invalidateSpecsVolume();

                return $this->redirect()->toRoute(null, [], [], true);
            }
        }

        return [
            'engine'   => $engine,
            'form'     => $form,
            'formData' => $formData,
            'service'  => $this->specsService
        ];
    }

    public function attrsChangeLogAction()
    {
        if (!$this->user()->isAllowed('specifications', 'edit')) {
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

        $userValues = new Attrs_User_Values();

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

        $cars = new Cars();
        $engines = $this->getEngineTable();

        $isModerator = $this->user()->inheritsRole('moder');

        foreach ($paginator->getCurrentItems() as $row) {
            $objectName = null;
            $editorUrl = null;
            $moderUrl = null;
            $path = [];

            $itemType = $row->findParentAttrs_Item_Types();
            if ($itemType->id == 1) {
                $car = $cars->find($row->item_id)->current();
                if ($car) {
                    $objectName = $car->getFullName($this->language());
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
            } elseif ($itemType->id == 3) {
                $engine = $engines->find($row->item_id)->current();
                if ($engine) {
                    $objectName = $engine->caption;
                    $editorUrl = $this->url()->fromRoute('cars/params', [
                        'action'    => 'engine-spec-editor',
                        'engine_id' => $engine->id
                    ]);

                    if ($isModerator) {
                        $moderUrl = $this->url()->fromRoute('moder/engines/params', [
                            'action'    => 'engine',
                            'engine_id' => $engine->id
                        ]);
                    }
                }
            }

            $attribute = $row->findParentAttrs_Attributes();
            if ($attribute) {
                $parents = [];
                $parent = $attribute;
                do {
                    $parents[] = $parent->name;
                } while ($parent = $parent->findParentAttrs_Attributes());

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
                'value'    => $this->specsService->getUserValueText($attribute->id, $itemType->id, $row->item_id, $user->id, $language),
                'unit'     => $attribute->findParentAttrs_Units()
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
        if (!$this->user()->isAllowed('specifications', 'edit-engine')) {
            return $this->forward('forbidden', 'error');
        }

        $carTable = new Cars();

        $car = $carTable->find($this->params('car_id'))->current();
        if (!$car) {
            return $this->notFoundAction();
        }

        $engine = $car->findParentRow(Engine::class);
        $car->engine_inherit = 0;
        $car->engine_id = null;
        $car->save();

        $carTable->updateInteritance($car);

        $this->specsService->updateActualValues(1, $car->id);

        if ($engine) {

            $message = sprintf(
                'У автомобиля %s убран двигатель (был %s)',
                htmlspecialchars($car->getFullName('en')), htmlspecialchars($engine->caption)
            );
            $this->log($message, $car);

            $user = $this->user()->get();
            $ucsTable = new UserCarSubscribe();

            $mModel = new Message();

            foreach ($ucsTable->getCarSubscribers($car) as $subscriber) {
                if ($subscriber && ($subscriber->id != $user->id)) {

                    $uri = $this->hostManager->getUriByLanguage($subscriber->language);

                    $message = sprintf(
                        $this->translate('pm/user-%s-canceled-vehicle-engine-%s-%s-%s', 'default', $subscriber->language),
                        $this->userUrl($user, $uri),
                        $engine->caption,
                        $car->getFullName($subscriber->language),
                        $this->carModerUrl($car, $uri)
                    );

                    $mModel->send(null, $subscriber->id, $message);
                }
            }
        }

        return $this->redirect()->toUrl($this->editorUrl($car, 'engine'));
    }

    public function inheritCarEngineAction()
    {
        if (!$this->user()->isAllowed('specifications', 'edit-engine')) {
            return $this->forward('forbidden', 'error');
        }

        $carTable = new Cars();

        $car = $carTable->find($this->params('car_id'))->current();
        if (!$car) {
            return $this->notFoundAction();
        }

        if (!$car->engine_inherit) {
            $car->engine_id = null;
            $car->engine_inherit = 1;
            $car->save();

            $carTable->updateInteritance($car);

            $this->specsService->updateActualValues(1, $car->id);

            $message = sprintf(
                'У автомобиля %s установлено наследование двигателя',
                htmlspecialchars($car->getFullName('en'))
            );
            $this->log($message, $car);

            $user = $this->user()->get();
            $ucsTable = new UserCarSubscribe();

            $mModel = new Message();

            foreach ($ucsTable->getCarSubscribers($car) as $subscriber) {
                if ($subscriber && ($subscriber->id != $user->id)) {

                    $uri = $this->hostManager->getUriByLanguage($subscriber->language);

                    $message = sprintf(
                        $this->translate('pm/user-%s-set-inherited-vehicle-engine-%s-%s', 'default', $subscriber->language),
                        $this->userUrl($user, $uri),
                        $car->getFullName($subscriber->language),
                        $this->carModerUrl($car, $uri)
                    );

                    $mModel->send(null, $subscriber->id, $message);
                }
            }
        }

        return $this->redirect()->toUrl($this->editorUrl($car, 'engine'));
    }

    private function enginesWalkTree($parentId, $brandId)
    {
        $engineTable = $this->getEngineTable();
        $select = $engineTable->select(true)
            ->order('engines.caption');
        if ($brandId) {
            $select
                ->join('brand_engine', 'engines.id = brand_engine.engine_id', null)
                ->where('brand_engine.brand_id = ?', $brandId);
        }
        if ($parentId) {
            $select->where('engines.parent_id = ?', $parentId);
        }

        $rows = $engineTable->fetchAll($select);

        $engines = [];
        foreach ($rows as $row) {
            $engines[] = [
                'id'     => $row->id,
                'name'   => $row->caption,
                'childs' => $this->enginesWalkTree($row->id, null)
            ];
        }

        return $engines;
    }

    public function selectCarEngineAction()
    {
        if (!$this->user()->isAllowed('specifications', 'edit-engine')) {
            return $this->forward('forbidden', 'error');
        }

        if (!$this->user()->isAllowed('specifications', 'edit')) {
            return $this->forward('index', 'login', 'default');
        }

        $carTable = new Cars();

        $car = $carTable->find($this->params('car_id'))->current();
        if (!$car) {
            return $this->notFoundAction();
        }

        $language = $this->language();

        $brandModel = new BrandModel();

        $brand = $brandModel->getBrandByCatname($this->params()->fromPost('brand'), $language);

        if (!$brand) {

            $brands = $brandModel->getList($language, function($select) {
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

        $engineTable = $this->getEngineTable();

        $engine = $engineTable->find($this->params()->fromPost('engine'))->current();
        if (!$engine) {

            return [
                'car'     => $car,
                'brand'   => $brand,
                'brands'  => [],
                'engines' => $this->enginesWalkTree(null, $brand['id'])
            ];
        }

        $car->engine_inherit = 0;
        $car->engine_id = $engine->id;
        $car->save();

        $carTable->updateInteritance($car);

        $this->specsService->updateActualValues(1, $car->id);

        $user = $this->user()->get();
        $ucsTable = new UserCarSubscribe();

        $message = sprintf(
            'Автомобилю %s назначен двигатель %s',
            htmlspecialchars($car->getFullName('en')), htmlspecialchars($engine->caption)
        );
        $this->log($message, $car);

        $mModel = new Message();

        foreach ($ucsTable->getCarSubscribers($car) as $subscriber) {
            if ($subscriber && ($subscriber->id != $user->id)) {

                $uri = $this->hostManager->getUriByLanguage($subscriber->language);

                $message = sprintf(
                    $this->translate('pm/user-%s-set-vehicle-engine-%s-%s-%s', 'default', $subscriber->language),
                    $this->userUrl($user, $uri),
                    $engine->caption,
                    $car->getFullName($subscriber->language),
                    $this->carModerUrl($car, $uri)
                );

                $mModel->send(null, $subscriber->id, $message);
            }
        }

        return $this->redirect()->toUrl($this->editorUrl($car, 'engine'));
    }

    public function refreshInheritanceAction()
    {
        if (!$this->user()->isAllowed('specifications', 'admin')) {
            return $this->forward('forbidden', 'error');
        }

        $carTable = new Cars();

        $car = $carTable->find($this->params('car_id'))->current();
        if (!$car) {
            return $this->notFoundAction();
        }

        $carTable->updateInteritance($car);

        $this->specsService->updateActualValues(1, $car->id);

        return $this->redirect()->toUrl($this->editorUrl($car, 'admin'));
    }

    public function editValueAction()
    {
        if (!$this->user()->isAllowed('specifications', 'edit')) {
            return $this->forward('forbidden', 'error');
        }

        $attrId = (int)$this->params('attr');
        $itemTypeId = (int)$this->params('item_type');
        $itemId = (int)$this->params('item');

        $language = $this->language();

        $form = $this->specsService->getEditValueForm($attrId, $itemTypeId, $itemId, $language);
        if (!$form) {
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
        if (!$this->user()->isAllowed('specifications', 'edit')) {
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
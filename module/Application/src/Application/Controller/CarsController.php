<?php

namespace Application\Controller;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

use Autowp\Commons\Paginator\Adapter\Zend1DbTableSelect;
use Autowp\Message\MessageService;
use Autowp\User\Model\DbTable\User;

use Application\HostManager;
use Application\Model\Brand as BrandModel;
use Application\Model\DbTable;
use Application\Model\DbTable\Attr;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\UserItemSubscribe;
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
     * @var MessageService
     */
    private $message;

    /**
     * @var UserItemSubscribe
     */
    private $userItemSubscribe;

    /**
     * @var Perspective
     */
    private $perspective;

    /**
     * @var Item
     */
    private $itemModel;

    public function __construct(
        HostManager $hostManager,
        Form $filterForm,
        SpecificationsService $specsService,
        MessageService $message,
        UserItemSubscribe $userItemSubscribe,
        Perspective $perspective,
        Item $itemModel
    ) {

        $this->hostManager = $hostManager;
        $this->filterForm = $filterForm;
        $this->specsService = $specsService;
        $this->message = $message;
        $this->userItemSubscribe = $userItemSubscribe;
        $this->perspective = $perspective;
        $this->itemModel = $itemModel;
    }

    private function carModerUrl(\Autowp\Commons\Db\Table\Row $item, $uri = null)
    {
        $url = 'moder/items/item/' . $item['id'];

        return $this->url()->fromRoute('ng', ['path' => ''], [
            'force_canonical' => true,
            'uri'             => $uri
        ]) . $url;
    }

    private function editorUrl($car, $tab = null)
    {
        return $this->url()->fromRoute('cars/params', [
            'action'  => 'car-specifications-editor',
            'item_id' => $car->id,
            'tab'     => $tab
        ]);
    }

    public function carSpecificationsEditorAction()
    {
        if (! $this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $editOnlyMode = $this->user()->get()->specs_weight < 0.10;

        $itemTable = new DbTable\Item();

        $car = $itemTable->fetchRow([
            'id = ?' => (int)$this->params('item_id'),
            'item_type_id IN (?)' => [DbTable\Item\Type::VEHICLE, DbTable\Item\Type::ENGINE]
        ]);
        if (! $car) {
            return $this->notFoundAction();
        }

        $user = $this->user()->get();

        $result = $this->specsService->getCarForm($car, $user, [
            'editOnlyMode' => $editOnlyMode
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

                $userTable = new User();
                $userTable->invalidateSpecsVolume($user->id);

                $contribPairs = $this->specsService->getContributors([$car->id]);
                $contributors = [];
                if ($contribPairs) {
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
        $engine = $itemTable->find($car->engine_item_id)->current();
        $engineInherited = $car->engine_inherit;
        $engineInheritedFrom = [];
        if ($engine && $car->engine_inherit) {
            $carRows = $itemTable->fetchAll(
                $itemTable->select(true)
                    ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                    ->where('item_parent_cache.item_id = ?', $car->id)
                    ->where('item.engine_item_id = ?', $engine->id)
                    ->where('item.id <> ?', $car->id)
                    ->order('item_parent_cache.diff desc')
            );

            foreach ($carRows as $carRow) {
                $engineInheritedFrom[] = [
                    'name' => $this->car()->formatName($carRow, $this->language()),
                    'url'  => '/ng/moder/items/item/' . $carRow->id
                ];
            }
        }

        $engineNameData = null;
        if ($engine) {
            $engineNameData = $this->itemModel->getNameData($engine, $this->language());
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
            'nameData'            => $this->itemModel->getNameData($car, $this->language()),
            'engine'              => $engine,
            'engineInherited'     => $engineInherited,
            'engineInheritedFrom' => $engineInheritedFrom,
            'engineNameData'      => $engineNameData,
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

        $itemTable = new DbTable\Item();

        $car = $itemTable->find($this->params('item_id'))->current();
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

        //$itemTable = new DbTable\Item();
        $itemId = (int)$this->params('item_id');

        $toItemId = (int)$this->params('to_item_id');

        $userValueTable = new Attr\UserValue();
        $attrTable = new Attr\Attribute();

        $eUserValueRows = $userValueTable->fetchAll([
            'item_id = ?' => $itemId
        ]);

        foreach ($eUserValueRows as $eUserValueRow) {
            // check for value in dest

            $cUserValueRow = $userValueTable->fetchRow([
                'item_id = ?'      => $toItemId,
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
                'user_id = ?'      => $eUserValueRow->user_id
            ]);

            foreach ($eDataRows as $eDataRow) {
                // check for data row existance
                $filter = [
                    'attribute_id = ?' => $eDataRow->attribute_id,
                    'item_id = ?'      => $toItemId,
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
                'item_id'      => $toItemId
            ]);
            $eUserValueRow->save();

            foreach ($eDataRows as $eDataRow) {
                $eDataRow->setFromArray([
                    'item_id'      => $toItemId
                ]);
                $eDataRow->save();
            }

            $this->specsService->updateActualValues($toItemId);
            $this->specsService->updateActualValues($itemId);
        }

        return $this->redirect()->toRoute('cars/params', [
            'action' => 'car-specifications-editor'
        ], [], true);
    }

    public function specsAdminAction()
    {
        if (! $this->user()->isAllowed('specifications', 'admin')) {
            return $this->forward('forbidden', 'error');
        }

        $itemId = (int)$this->params('item_id');

        $auvTable = new Attr\UserValue();

        $rows = $auvTable->fetchAll([
            'item_id = ?' => $itemId
        ], 'update_date');

        $language = $this->language();

        $values = [];
        foreach ($rows as $row) {
            $attribute = $row->findParentRow(Attr\Attribute::class);
            $user = $row->findParentRow(User::class);
            $unit = $this->specsService->getUnit($attribute['unit_id']);
            $values[] = [
                'attribute' => $attribute,
                'unit'      => $unit,
                'user'      => $user,
                'value'     => $this->specsService->getActualValueText(
                    $attribute->id,
                    $row->item_id,
                    $language
                ),
                'userValue' => $this->specsService->getUserValueText(
                    $attribute->id,
                    $row->item_id,
                    $user->id,
                    $language
                ),
                'date'      => $row->getDateTime('update_date'),
                'deleteUrl' => $this->url()->fromRoute('cars/params', [
                    'action'       => 'delete-value',
                    'attribute_id' => $row->attribute_id,
                    'item_id'      => $row->item_id,
                    'user_id'      => $row->user_id
                ], [], true)
            ];
        }

        return [
            'values'     => $values,
            'itemId'     => $itemId,
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

        $itemId = (int)$this->params('item_id');
        $userId = (int)$this->params('user_id');

        $this->specsService->deleteUserValue((int)$this->params('attribute_id'), $itemId, $userId);

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

            if ($userId = $values['user_id']) {
                $select->where('user_id = ?', $userId);
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

        $cars = new DbTable\Item();

        $isModerator = $this->user()->inheritsRole('moder');

        foreach ($paginator->getCurrentItems() as $row) {
            $objectName = null;
            $editorUrl = null;
            $moderUrl = null;
            $path = [];

            $car = $cars->find($row->item_id)->current();
            if ($car) {
                $objectName = $this->car()->formatName($car, $this->language());
                $editorUrl = $this->url()->fromRoute('cars/params', [
                    'action'  => 'car-specifications-editor',
                    'item_id' => $car->id
                ]);

                if ($isModerator) {
                    $moderUrl = '/ng/moder/items/item/' . $car->id;
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
                'object'   => [
                    'name'      => $objectName,
                    'editorUrl' => $editorUrl,
                    'moderUrl'  => $moderUrl
                ],
                'path'     => $path,
                'value'    => $this->specsService->getUserValueText(
                    $attribute->id,
                    $row->item_id,
                    $user->id,
                    $language
                ),
                'unit'     => $this->specsService->getUnit($attribute['unit_id'])
            ];
        }

        return [
            'paginator'   => $paginator,
            'filter'      => $this->filterForm,
            'items'       => $items,
            'isModerator' => $isModerator
        ];
    }

    private function userUrl(\Autowp\Commons\Db\Table\Row $user, $uri = null)
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

        $itemTable = new DbTable\Item();

        $car = $itemTable->find($this->params('item_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        $engine = $car->findParentRow(DbTable\Item::class, 'Engine');
        $car->engine_inherit = 0;
        $car->engine_item_id = null;
        $car->save();

        $this->itemModel->updateInteritance($car);

        $this->specsService->updateActualValues($car->id);

        if ($engine) {
            $message = sprintf(
                'У автомобиля %s убран двигатель (был %s)',
                htmlspecialchars($this->car()->formatName($car, 'en')),
                htmlspecialchars($engine->name)
            );
            $this->log($message, $car);

            $user = $this->user()->get();

            foreach ($this->userItemSubscribe->getItemSubscribers($car['id']) as $subscriber) {
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

        $itemTable = new DbTable\Item();

        $car = $itemTable->find($this->params('item_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        if (! $car->engine_inherit) {
            $car->engine_item_id = null;
            $car->engine_inherit = 1;
            $car->save();

            $this->itemModel->updateInteritance($car);

            $this->specsService->updateActualValues($car->id);

            $message = sprintf(
                'У автомобиля %s установлено наследование двигателя',
                htmlspecialchars($this->car()->formatName($car, 'en'))
            );
            $this->log($message, $car);

            $user = $this->user()->get();

            foreach ($this->userItemSubscribe->getItemSubscribers($car['id']) as $subscriber) {
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
        $itemTable = new DbTable\Item();
        $select = $itemTable->select(true)
            ->where('item.item_type_id = ?', DbTable\Item\Type::ENGINE)
            ->order('item.name');
        if ($brandId) {
            $select
                ->join('item_parent', 'item.id = item_parent.item_id', null)
                ->where('item_parent.parent_id = ?', $brandId);
        }
        if ($parentId) {
            $select
                ->join('item_parent', 'item.id = item_parent.item_id', null)
                ->where('item_parent.parent_id = ?', $parentId);
        }

        $rows = $itemTable->fetchAll($select);

        $engines = [];
        foreach ($rows as $row) {
            $engines[] = [
                'id'     => $row->id,
                'name'   => $this->itemModel->getNameData($row, $this->language()),
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

        $itemTable = new DbTable\Item();

        $car = $itemTable->fetchRow([
            'id = ?'           => (int)$this->params('item_id'),
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
                    ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                    ->join(['engine' => 'item'], 'item_parent_cache.item_id = engine.id', null)
                    ->where('engine.item_type_id = ?', DbTable\Item\Type::ENGINE)
                    ->group('item.id');
            });

            return [
                'car'      => $car,
                'nameData' => $this->itemModel->getNameData($car, $language),
                'brand'    => false,
                'brands'   => $brands,
                'engines'  => []
            ];
        }

        $engine = $itemTable->fetchRow([
            'id = ?'           => (int)$this->params()->fromPost('engine'),
            'item_type_id = ?' => DbTable\Item\Type::ENGINE
        ]);
        if (! $engine) {
            return [
                'car'      => $car,
                'nameData' => $this->itemModel->getNameData($car, $language),
                'brand'    => $brand,
                'brands'   => [],
                'engines'  => $this->enginesWalkTree(null, $brand['id'])
            ];
        }

        $car->engine_inherit = 0;
        $car->engine_item_id = $engine->id;
        $car->save();

        $this->itemModel->updateInteritance($car);

        $this->specsService->updateActualValues($car->id);

        $user = $this->user()->get();

        $message = sprintf(
            'Автомобилю %s назначен двигатель %s',
            htmlspecialchars($this->car()->formatName($car, 'en')),
            htmlspecialchars($engine->name)
        );
        $this->log($message, $car);

        foreach ($this->userItemSubscribe->getItemSubscribers($car['id']) as $subscriber) {
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

        $itemTable = new DbTable\Item();

        $car = $itemTable->find($this->params('item_id'))->current();
        if (! $car) {
            return $this->notFoundAction();
        }

        $this->itemModel->updateInteritance($car);

        $this->specsService->updateActualValues($car->id);

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

        $itemTable = $this->catalogue()->getItemTable();

        $select = $itemTable->select(true)
            ->where('item.begin_year is null and item.begin_model_year is null')
            ->order($this->catalogue()->itemOrdering());

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
            'childListData' => $this->car()->listData($listCars, [
                'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                    'perspective'          => $this->perspective,
                    'type'                 => null,
                    'onlyExactlyPictures'  => false,
                    'dateSort'             => false,
                    'disableLargePictures' => false,
                    'perspectivePageId'    => null,
                    'onlyChilds'           => []
                ]),
                'listBuilder' => new \Application\Model\Item\ListBuilder([
                    'catalogue'    => $this->catalogue(),
                    'router'       => $this->getEvent()->getRouter(),
                    'picHelper'    => $this->getPluginManager()->get('pic'),
                    'specsService' => $this->specsService
                ]),
            ]),
        ];
    }
}

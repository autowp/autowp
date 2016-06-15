<?php

use Application\Model\Message;
use Application\Model\Brand;

class CarsController extends Zend_Controller_Action
{
    /**
     * @var Engines
     */
    private $_engineTable = null;

    /**
     * @return Engines
     */
    private function getEngineTable()
    {
        return $this->_engineTable
            ? $this->_engineTable
            : $this->_engineTable = new Engines();
    }

    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->isAllowed('specifications', 'edit')) {
            return $this->_forward('index', 'login', 'default');
        }
    }

    private function carModerUrl(Cars_Row $car)
    {
        return $this->view->serverUrl($this->_helper->url->url(array(
            'module'     => 'moder',
            'controller' => 'cars',
            'action'     => 'car',
            'car_id'     => $car->id,
        ), 'default', true));
    }

    private function editorUrl($car, $tab = null)
    {
        return $this->_helper->url->url(array(
            'module'     => 'default',
            'controller' => 'cars',
            'action'     => 'car-specifications-editor',
            'car_id'     => $car->id,
            'tab'        => $tab
        ), 'default', true);
    }

    public function carSpecificationsEditorAction()
    {
        if ($this->_helper->user()->get()->specs_weight < 0.10) {
            return $this->_forward('low-weight');
        }

        $carTable = new Cars();

        $car = $carTable->find($this->_getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $service = new Application_Service_Specifications();

        $user = $this->_helper->user()->get();

        $carForm = $service->getCarForm($car, $user, array(
            'action' => $this->_helper->url->url(array(
                'form' => 'car',
                'tab'  => 'spec'
            ))
        ));

        $request = $this->getRequest();
        if ($request->isPost()) {
            if ($carForm->isValid($request->getPost())) {
                $service->saveAttrsZoneAttributes($carForm, $user);

                $user->invalidateSpecsVolume();
                
                $mModel = new Message();
                
                $message = sprintf(
                        '%s внес ттх для автомобиля %s',
                        $this->userUrl($user), $car->getFullName($this->_helper->language())
                );
                
                $contribPairs = $service->getContributors(1, array($car->id));
                $contributors = [];
                if ($contribPairs) {
                    $userTable = new Users();
                    $contributors = $userTable->fetchAll(
                        $userTable->select(true)
                            ->where('id IN (?)', array_keys($contribPairs))
                            ->where('not deleted')
                    );
                }
                
                foreach ($contributors as $contributor) {
                    if ($contributor->id != $user->id) {
                        $mModel->send(null, $contributor->id, $message);
                    }
                }

                return $this->_redirect($this->editorUrl($car, 'spec'));
            }
        }

        $engine = $car->findParentEngines();
        $engineInherited = $car->engine_inherit;
        $engineInheritedFrom = array();
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
                $engineInheritedFrom[] = array(
                    'name' => $carRow->getFullName(),
                    'url'  => $this->_helper->url->url(array(
                        'module' => 'moder',
                        'controller' => 'cars',
                        'action' => 'car',
                        'car_id' => $carRow->id
                    ), 'default', true)
                );
            }
        }

        $tabs = array(
            'info' => array(
                'icon'  => 'fa fa-info',
                'title' => 'Информация',
                'count' => 0,
            ),
            'engine' => array(
                'icon'  => 'glyphicon glyphicon-align-left',
                'title' => 'Двигатель',
                'count' => (bool)$engine,
            ),
            'spec' => array(
                'icon'  => 'fa fa-car',
                'title' => 'Основные ТТХ',
                'count' => 0,
            ),
            'result' => array(
                'icon'      => 'fa fa-table',
                'title'     => 'Результат',
                'data-load' => $this->_helper->url->url(array(
                    'action' => 'car-specs'
                )),
                'count' => 0,
            ),
            'admin' => array(
                'icon'      => 'fa fa-cog',
                'title'     => 'Admin',
                'count' => 0,
            ),
        );

        $currentTab = $this->_getParam('tab', 'info');
        foreach ($tabs as $id => &$tab) {
            $tab['active'] = $id == $currentTab;
        }

        $isSpecsAdmin = $this->_helper->user()->isAllowed('specifications', 'admin');

        if (!$isSpecsAdmin) {
            unset($tabs['admin']);
        }

        $this->view->assign(array(
            'car'                 => $car,
            'engine'              => $engine,
            'engineInherited'     => $engineInherited,
            'engineInheritedFrom' => $engineInheritedFrom,
            'form'                => $carForm,
            'tabs'                => $tabs,
            'isSpecsAdmin'        => $isSpecsAdmin
        ));
    }

    public function carSpecsAction()
    {
        $carTable = new Cars();

        $car = $carTable->find($this->_getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $service = new Application_Service_Specifications();

        $specs = $service->specifications(array($car), array(
            'language' => 'en'
        ));

        $this->view->assign(array(
            'specs' => $specs,
        ));
    }

    public function moveAction()
    {
        if (!$this->_helper->user()->isAllowed('specifications', 'admin')) {
            return $this->_forward('forbidden', 'error');
        }

        //$carTable = new Cars();
        $aitTable = new Attrs_Item_Types();

        $itemId = (int)$this->_getParam('item_id');
        $itemType = $aitTable->find($this->_getParam('item_type_id'))->current();
        if (!$itemType) {
            return $this->_forward('notfound', 'error');
        }

        $toItemId = (int)$this->_getParam('to_item_id');
        $toItemType = $aitTable->find($this->_getParam('to_item_type_id'))->current();
        if (!$toItemType) {
            return $this->_forward('notfound', 'error');
        }

        $service = new Application_Service_Specifications();

        $userValueTable = new Attrs_User_Values();
        $attrTable = new Attrs_Attributes();

        $eUserValueRows = $userValueTable->fetchAll(array(
            'item_id = ?'      => $itemId,
            'item_type_id = ?' => $itemType->id
        ));

        foreach ($eUserValueRows as $eUserValueRow) {

            // check for value in dest

            $cUserValueRow = $userValueTable->fetchRow(array(
                'item_id = ?'      => $toItemId,
                'item_type_id = ?' => $toItemType->id,
                'attribute_id = ?' => $eUserValueRow->attribute_id,
                'user_id = ?'      => $eUserValueRow->user_id
            ));

            if ($cUserValueRow) {
                print "Value row already exists \n";
                exit;
            }

            $attrRow = $attrTable->find($eUserValueRow->attribute_id)->current();

            if (!$attrRow) {
                print "Attr not found";
                exit;
            }

            $dataTable = $service->getUserValueDataTable($attrRow->type_id);

            $eDataRows = $dataTable->fetchAll(array(
                'attribute_id = ?' => $eUserValueRow->attribute_id,
                'item_id = ?'      => $eUserValueRow->item_id,
                'item_type_id = ?' => $eUserValueRow->item_type_id,
                'user_id = ?'      => $eUserValueRow->user_id
            ));

            foreach ($eDataRows as $eDataRow) {

                // check for data row existance
                $filter = array(
                    'attribute_id = ?' => $eDataRow->attribute_id,
                    'item_id = ?'      => $toItemId,
                    'item_type_id = ?' => $toItemType->id,
                    'user_id = ?'      => $eDataRow->user_id
                );
                if ($attrRow->isMultiple()) {
                    $filter['ordering = ?'] = $eDataRow->ordering;
                }
                $cDataRow = $dataTable->fetchRow($filter);

                if ($cDataRow) {
                    print "Data row already exists \n";
                    exit;
                }
            }

            $eUserValueRow->setFromArray(array(
                'item_id'      => $toItemId,
                'item_type_id' => $toItemType->id,
            ));
            $eUserValueRow->save();

            foreach ($eDataRows as $eDataRow) {
                $eDataRow->setFromArray(array(
                    'item_id'      => $toItemId,
                    'item_type_id' => $toItemType->id,
                ));
                $eDataRow->save();
            }

            $service->updateActualValues(
                $toItemType->id,
                $toItemId
            );
            $service->updateActualValues(
                $itemType->id,
                $itemId
            );

        }

        return $this->redirect($this->_helper->url->url(array(
            'action' => 'car-specifications-editor'
        )));
    }

    public function specsAdminAction()
    {
        $carTable = new Cars();

        if (!$this->_helper->user()->isAllowed('specifications', 'admin')) {
            return $this->_forward('forbidden', 'error');
        }

        $aitTable = new Attrs_Item_Types();

        $itemId = (int)$this->_getParam('item_id');
        $itemType = $aitTable->find($this->_getParam('item_type_id'))->current();
        if (!$itemType) {
            return $this->_forward('notfound', 'error');
        }

        $auvTable = new Attrs_User_Values();

        $rows = $auvTable->fetchAll(array(
            'item_id = ?'      => $itemId,
            'item_type_id = ?' => $itemType->id
        ), 'update_date');

        $specService = new Application_Service_Specifications();

        $values = array();
        foreach ($rows as $row) {
            $attribute = $row->findParentAttrs_Attributes();
            $user = $row->findParentUsers();
            $unit = $attribute->findParentAttrs_Units();
            $values[] = array(
                'attribute' => $attribute,
                'unit'      => $unit,
                'user'      => $user,
                'value'     => $specService->getActualValueText($attribute->id, $itemType->id, $row->item_id),
                'userValue' => $specService->getUserValueText($attribute->id, $itemType->id, $row->item_id, $user->id),
                'date'      => $row->getDate('update_date'),
                'deleteUrl' => $this->_helper->url->url(array(
                    'action'  => 'delete-value',
                    'attribute_id' => $row->attribute_id,
                    'item_type_id' => $row->item_type_id,
                    'item_id'      => $row->item_id,
                    'user_id'      => $row->user_id
                ))
            );
        }

        $this->view->assign(array(
            'values'     => $values,
            'itemId'     => $itemId,
            'itemTypeId' => $itemType->id
        ));
    }

    public function deleteValueAction()
    {
        if (!$this->_helper->user()->isAllowed('specifications', 'admin')) {
            return $this->_forward('forbidden', 'error');
        }

        $request = $this->getRequest();
        if (!$request->isPost()) {
            return $this->_forward('forbidden', 'error');
        }

        $aitTable = new Attrs_Item_Types();

        $itemType = $aitTable->find($this->_getParam('item_type_id'))->current();
        if (!$itemType) {
            return $this->_forward('notfound', 'error');
        }

        $itemId = (int)$this->_getParam('item_id');
        $userId = (int)$this->_getParam('user_id');

        $specService = new Application_Service_Specifications();
        $specService->deleteUserValue((int)$this->_getParam('attribute_id'), $itemType->id, $itemId, $userId);

        return $this->_redirect($request->getServer('HTTP_REFERER'));
    }

    public function engineSpecEditorAction()
    {
        if ($this->_helper->user()->get()->specs_weight < 0.10) {
            return $this->_forward('low-weight');
        }

        $engines = $this->getEngineTable();

        $engine = $engines->find($this->_getParam('engine_id'))->current();
        if (!$engine) {
            return $this->_forward('notfound', 'error');
        }

        $service = new Application_Service_Specifications();

        $user = $this->_helper->user()->get();

        $form = $service->getEngineForm($engine, $user, array(
            'action'  => $this->_helper->url->url()
        ));

        $request = $this->getRequest();
        if ($request->isPost()) {
            if ($form->isValid($request->getPost())) {
                $service->saveAttrsZoneAttributes($form, $user);

                $user->invalidateSpecsVolume();

                return $this->_redirect($this->_helper->url->url());
            }
        }

        $this->view->assign(array(
            'engine' => $engine,
            'form'   => $form
        ));
    }

    public function attrsChangeLogAction()
    {
        $filter = new Application_Form_Attrs_ChangeLogFilter(array(
            'action' => $this->_helper->url->url(array(
                'module'     => 'default',
                'controller' => 'cars',
                'action'     => 'attrs-change-log'
            ))
        ));

        $filter->populate($this->_getAllParams());

        if ($this->getRequest()->isPost()) {
            $filter->isValid($this->getRequest()->getPost());
            $values = $filter->getValues();

            if ($values['user_id']) {
                return $this->_redirect($this->_helper->url->url(array(
                    'user_id' => $values['user_id'],
                    'page'    => null
                )));
            } else {
                return $this->_redirect($this->_helper->url->url(array(
                    'user_id' => null,
                    'page'    => null
                )));
            }
        }

        $userValues = new Attrs_User_Values();

        $select = $userValues->select()
            ->order('update_date DESC');

        if ($user_id = $this->_getParam('user_id'))
            $select->where('user_id = ?', $user_id);

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(30)
            ->setPageRange(20)
            ->setCurrentPageNumber($this->_getParam('page'));

        $items = array();

        $cars = new Cars();
        $engines = $this->getEngineTable();

        $isModerator = $this->_helper->user()->inheritsRole('moder');

        $service = new Application_Service_Specifications();

        foreach ($paginator->getCurrentItems() as $row) {
            $objectName = null;
            $editorUrl = null;
            $moderUrl = null;
            $path = array();

            $itemType = $row->findParentAttrs_Item_Types();
            if ($itemType->id == 1) {
                $car = $cars->find($row->item_id)->current();
                if ($car) {
                    $objectName = $car->getFullName();
                    $editorUrl = $this->_helper->url->url(array(
                        'module'     => 'default',
                        'controller' => 'cars',
                        'action'     => 'car-specifications-editor',
                        'car_id'     => $car->id
                    ), 'default', true);

                    if ($isModerator) {
                        $moderUrl = $this->_helper->url->url(array(
                            'module'     => 'moder',
                            'controller' => 'cars',
                            'action'     => 'car',
                            'car_id'     => $car->id
                        ), 'default', true);
                    }
                }
            } elseif ($itemType->id == 3) {
                $engine = $engines->find($row->item_id)->current();
                if ($engine) {
                    $objectName = $engine->caption;
                    $editorUrl = $this->_helper->url->url(array(
                        'module'     => 'default',
                        'controller' => 'cars',
                        'action'     => 'engine-spec-editor',
                        'engine_id'  => $engine->id
                    ), 'default', true);

                    if ($isModerator) {
                        $moderUrl = $this->_helper->url->url(array(
                            'module'     => 'moder',
                            'controller' => 'engines',
                            'action'     => 'engine',
                            'engine_id'  => $engine->id
                        ), 'default', true);
                    }
                }
            }

            $attribute = $row->findParentAttrs_Attributes();
            if ($attribute) {
                $parents = array();
                $parent = $attribute;
                do {
                    $parents[] = $parent->name;
                } while ($parent = $parent->findParentAttrs_Attributes());

                $path = array_reverse($parents);
            }

            $user = $row->findParentUsers();

            $items[] = array(
                'date'     => $row->getDate('update_date'),
                'user'     => $user,
                'itemType' => array(
                    'id'   => $itemType->id,
                    'name' => $itemType->name,
                ),
                'object'   => array(
                    'name'      => $objectName,
                    'editorUrl' => $editorUrl,
                    'moderUrl'  => $moderUrl
                ),
                'path'     => $path,
                'value'    => $service->getUserValueText($attribute->id, $itemType->id, $row->item_id, $user->id),
                'unit'     => $attribute->findParentAttrs_Units()
            );
        }

        $this->view->assign(array(
            'paginator'   => $paginator,
            'filter'      => $filter,
            'items'       => $items,
            'isModerator' => $isModerator
        ));
    }

    private function userUrl(Users_Row $user)
    {
        return $this->view->serverUrl($user->getAboutUrl());
    }

    public function cancelCarEngineAction()
    {
        if (!$this->_helper->user()->isAllowed('specifications', 'edit-engine')) {
            return $this->_forward('forbidden', 'error');
        }

        $carTable = new Cars();

        $car = $carTable->find($this->_getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $engine = $car->findParentEngines();
        $car->engine_inherit = 0;
        $car->engine_id = null;
        $car->save();

        $carTable->updateInteritance($car);

        $service = new Application_Service_Specifications();
        $service->updateActualValues(1, $car->id);

        if ($engine) {

            $message = sprintf(
                'У автомобиля %s убран двигатель (был %s)',
                $this->view->htmlA($this->editorUrl($car), $car->getFullName()), $this->view->escape($engine->getMETACaption())
            );
            $this->_helper->log($message, $car);

            $user = $this->_helper->user()->get();
            $ucsTable = new User_Car_Subscribe();
            
            $mModel = new Message();

            $message = sprintf(
                '%s отменил двигатель %s для автомобиля %s ( %s )',
                $this->userUrl($user), $engine->caption, $car->getFullName(), $this->carModerUrl($car)
            );
            foreach ($ucsTable->getCarSubscribers($car) as $subscriber) {
                if ($subscriber && ($subscriber->id != $user->id)) {
                    $mModel->send(null, $subscriber->id, $message);
                }
            }
        }

        return $this->_redirect($this->editorUrl($car, 'engine'));
    }

    public function inheritCarEngineAction()
    {
        if (!$this->_helper->user()->isAllowed('specifications', 'edit-engine')) {
            return $this->_forward('forbidden', 'error');
        }

        $carTable = new Cars();

        $car = $carTable->find($this->_getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        if (!$car->engine_inherit) {
            $car->engine_id = null;
            $car->engine_inherit = 1;
            $car->save();

            $carTable->updateInteritance($car);

            $service = new Application_Service_Specifications();
            $service->updateActualValues(1, $car->id);

            $message = sprintf(
                'У автомобиля %s установлено наследование двигателя',
                $this->view->htmlA($this->editorUrl($car), $car->getFullName())
            );
            $this->_helper->log($message, $car);

            $user = $this->_helper->user()->get();
            $ucsTable = new User_Car_Subscribe();
            
            $mModel = new Message();

            $message = sprintf(
                '%s установил наследование двигателя автомобилю %s ( %s )',
                $this->userUrl($user), $car->getFullName(), $this->carModerUrl($car)
            );
            foreach ($ucsTable->getCarSubscribers($car) as $subscriber) {
                if ($subscriber && ($subscriber->id != $user->id)) {
                    $mModel->send(null, $subscriber->id, $message);
                }
            }
        }

        return $this->_redirect($this->editorUrl($car, 'engine'));
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

        $engines = array();
        foreach ($rows as $row) {
            $engines[] = array(
                'id'     => $row->id,
                'name'   => $row->caption,
                'childs' => $this->enginesWalkTree($row->id, null)
            );
        }

        return $engines;
    }

    public function selectCarEngineAction()
    {
        if (!$this->_helper->user()->isAllowed('specifications', 'edit-engine')) {
            return $this->_forward('forbidden', 'error');
        }

        if (!$this->_helper->user()->isAllowed('specifications', 'edit')) {
            return $this->_forward('index', 'login', 'default');
        }

        $carTable = new Cars();

        $car = $carTable->find($this->_getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $this->view->assign(array(
            'car'     => $car,
            'brand'   => false,
            'brands'  => [],
            'engines' => []
        ));
        
        $language = $this->_helper->language();
        
        $brandModel = new Brand();

        $brand = $brandModel->getBrandByCatname($this->getParam('brand'), $language);

        if (!$brand) {
            
            $brands = $brandModel->getList($language, function($select) {
                $select
                    ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                    ->group('brands.id');
            });
            
            $this->view->assign([
                'brands' => $brands
            ]);

            return;
        }

        $engineTable = $this->getEngineTable();

        $engine = $engineTable->find($this->_getParam('engine'))->current();
        if (!$engine) {

            $this->view->assign(array(
                'brand'   => $brand,
                'engines' => $this->enginesWalkTree(null, $brand['id'])
            ));

            return;
        }

        $car->engine_inherit = 0;
        $car->engine_id = $engine->id;
        $car->save();

        $carTable->updateInteritance($car);

        $service = new Application_Service_Specifications();
        $service->updateActualValues(1, $car->id);

        $user = $this->_helper->user()->get();
        $ucsTable = new User_Car_Subscribe();

        $message = sprintf(
            'Автомобилю %s назначен двигатель %s',
            $this->view->htmlA($this->editorUrl($car), $car->getFullName()), $this->view->escape($engine->caption)
        );
        $this->_helper->log($message, $car);

        $mModel = new Message();

        $message = sprintf(
            '%s назначил двигатель %s автомобилю %s ( %s )',
            $this->userUrl($user), $engine->caption, $car->getFullName(), $this->carModerUrl($car)
        );
        foreach ($ucsTable->getCarSubscribers($car) as $subscriber) {
            if ($subscriber && ($subscriber->id != $user->id)) {
                $mModel->send(null, $subscriber->id, $message);
            }
        }

        return $this->_redirect($this->editorUrl($car, 'engine'));
    }

    public function refreshInheritanceAction()
    {
        if (!$this->_helper->user()->isAllowed('specifications', 'admin')) {
            return $this->_forward('forbidden', 'error');
        }

        $carTable = new Cars();

        $car = $carTable->find($this->_getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $carTable->updateInteritance($car);

        $service = new Application_Service_Specifications();

        $service->updateActualValues(1, $car->id);

        return $this->_redirect($this->editorUrl($car, 'admin'));
    }

    public function editValueAction()
    {
        if (!$this->_helper->user()->isAllowed('specifications', 'edit')) {
            return $this->_forward('forbidden', 'error');
        }

        $attrId = (int)$this->_getParam('attr');
        $itemTypeId = (int)$this->_getParam('item_type');
        $itemId = (int)$this->_getParam('item');

        $language = $this->_helper->language();

        $service = new Application_Service_Specifications();
        $form = $service->getEditValueForm($attrId, $itemTypeId, $itemId, $language);
        if (!$form) {
            return $this->_forward('notfound', 'error');
        }

        $form->setAction($this->_helper->url->url());

        $this->view->assign(array(
            'form' => $form
        ));
    }

    public function lowWeightAction()
    {

    }

    public function datelessAction()
    {
        $listCars = array();

        $carTable = $this->_helper->catalogue()->getCarTable();

        $select = $carTable->select(true)
            ->where('cars.begin_year is null and cars.begin_model_year is null')
            ->order($this->_helper->catalogue()->carsOrdering());

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(20)
            ->setCurrentPageNumber($this->_getParam('page'));

        foreach ($paginator->getCurrentItems() as $row) {
            $listCars[] = $row;
        }

        $this->view->assign(array(
            'paginator'     => $paginator,
            'childListData' => $this->_helper->car->listData($listCars),
        ));
    }
}
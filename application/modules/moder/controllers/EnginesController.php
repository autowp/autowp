<?php

class Moder_EnginesController extends Zend_Controller_Action
{
    /**
     * @var Engines
     */
    private $engineTable = null;

    /**
     * @return Engines
     */
    private function getEngineTable()
    {
        return $this->engineTable
            ? $this->engineTable
            : $this->engineTable = new Engines();
    }

    private function engineModerUrl($id)
    {
        return $this->_helper->url->url(array(
            'action'     => 'engine',
            'controller' => 'engines',
            'module'     => 'moder',
            'engine_id'  => $id
        ), 'default', true);
    }

    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder') ) {
            return $this->_forward('forbidden', 'error', 'default');
        }
    }

    public function engineAction()
    {
        $engineTable = $this->getEngineTable();

        $engine = $engineTable->find($this->_getParam('engine_id'))->current();
        if (!$engine) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $isModer = $this->_helper->user()->isAllowed('engine', 'edit');
        $canEdit = $isModer;

        if ($canEdit) {
            $form = new Application_Form_Moder_Engine_Edit(array(
                'action' => $this->_helper->url->url()
            ));

            $form->populate($engine->toArray());
            $request = $this->getRequest();

            if ($request->isPost() && $form->isValid($request->getPost())) {
                $values = $form->getValues();

                $user = $this->_helper->user()->get();
                $engine->setFromArray($values);
                $engine->last_editor_id = $user->id;
                $engine->save();

                $engineUrl = $this->engineModerUrl($engine->id);

                $message = sprintf(
                    'Редактирование двигателя %s',
                    $this->view->htmlA($engineUrl, $engine->caption)
                );
                $this->_helper->log($message, $engine);

                return $this->_redirect($engineUrl);
            }

            $this->view->formModerEngineEdit = $form;
        }

        $childEngines = $engineTable->fetchAll(array(
            'parent_id = ?' => $engine->id
        ));

        $parentEngine = null;
        if ($engine->parent_id) {
            $parentEngine = $engineTable->find($engine->parent_id)->current();
        }

        $brandTable = new Brands();
        $brandRows = $brandTable->fetchAll(
            $brandTable->select(true)
                ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                ->where('brand_engine.engine_id = ?', $engine->id)
                ->order(array('brands.position', 'brands.caption'))
        );

        $brands = array();
        foreach ($brandRows as $brandRow) {

            $cataloguePaths = $this->_helper->catalogue()->engineCataloguePaths($engine, array(
                'brand_id' => $brandRow->id
            ));

            $urls = array();
            foreach ($cataloguePaths as $cataloguePath) {
                $urls[] = $this->_helper->url->url(array(
                    'module'        => 'default',
                    'controller'    => 'catalogue',
                    'action'        => 'engines',
                    'brand_catname' => $cataloguePath['brand_catname'],
                    'path'          => $cataloguePath['path'],
                ), 'catalogue', true);
            }

            $brands[] = array(
                'name'      => $brandRow->caption,
                'urls'      => $urls,
                'inherited' => false,
                'moderUrl'  => $this->_helper->url->url(array(
                    'module'     => 'moder',
                    'controller' => 'brands',
                    'action'     => 'brand',
                    'brand_id'   => $brandRow->id
                ), 'default', true),
                'removeUrl'  => $this->_helper->url->url(array(
                    'module'     => 'moder',
                    'controller' => 'engines',
                    'action'     => 'remove-brand',
                    'engine_id'  => $engine->id,
                    'brand_id'   => $brandRow->id
                )),
            );
        }

        $brandEngineTable = new Brand_Engine();
        $brandEngineRows = $brandEngineTable->fetchAll(
            $brandEngineTable->select(true)
                ->join('brands', 'brand_engine.brand_id = brands.id', null)
                ->join('engine_parent_cache', 'brand_engine.engine_id = engine_parent_cache.parent_id', null)
                ->where('engine_parent_cache.engine_id = ?', $engine->id)
                ->where('engine_parent_cache.engine_id <> engine_parent_cache.parent_id')
                ->order(array('brands.position', 'brands.caption'))
        );
        foreach ($brandEngineRows as $brandEngineRow) {

            $brandRow = $brandTable->find($brandEngineRow->brand_id)->current();
            if (!$brandRow) {
                throw new Exception("Broken brand_engine link");
            }

            $parentEngineRow = $this->getEngineTable()->find($brandEngineRow->engine_id)->current();
            if (!$parentEngineRow) {
                throw new Exception("Broken brand_engine link");
            }

            $cataloguePaths = $this->_helper->catalogue()->engineCataloguePaths($engine, array(
                'brand_id' => $brandRow->id
            ));

            $urls = array();
            foreach ($cataloguePaths as $cataloguePath) {
                $urls[] = $this->_helper->url->url(array(
                    'module'        => 'default',
                    'controller'    => 'catalogue',
                    'action'        => 'engines',
                    'brand_catname' => $cataloguePath['brand_catname'],
                    'path'          => $cataloguePath['path'],
                ), 'catalogue', true);
            }

            $brands[] = array(
                'name'      => $brandRow->caption,
                'urls'      => $urls,
                'inherited' => true,
                'inheritedFrom' => array(
                    'name' => $parentEngineRow->caption,
                    'url'  => $this->engineModerUrl($parentEngineRow->id)
                ),
                'moderUrl'  => $this->_helper->url->url(array(
                    'module'     => 'moder',
                    'controller' => 'brands',
                    'action'     => 'brand',
                    'brand_id'   => $brandRow->id
                ), 'default', true),
            );
        }

        $specService = new Application_Service_Specifications();
        $specsCount = $specService->getSpecsCount(3, $engine->id);

        $this->view->assign(array(
            'engine'       => $engine,
            'parentEngine' => $parentEngine,
            'childEngines' => $childEngines,
            'canEdit'      => $canEdit,
            'cars'         => $engine->findCars(),
            'brands'       => $brands,
            'specsCount'   => $specsCount
        ));
    }

    public function indexAction()
    {
        $brandTable = new Brands();

        $db = $brandTable->getAdapter();

        $brandOptions = array('' => '-') + $db->fetchPairs(
            $db->select()
                ->from('brands', array('id', 'caption'))
                ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                ->group('brands.id')
                ->order(array('brands.position', 'brands.caption'))
        );


        $form = new Zend_Form(array(
            'decorators'    => array(
                'PrepareElements',
                array('viewScript', array('viewScript' => 'forms/bootstrap-vertical.phtml')),
                'Form'
            ),
            'action'   => $this->_helper->url->url(),
            'method'   => 'post',
            'elements' => array(
                array('text', 'name', array(
                    'label'      => 'Name',
                    'decorators' => array('ViewHelper')
                )),
                array('select', 'brand_id', array(
                    'label'        => 'Бренд',
                    'decorators'   => array('ViewHelper'),
                    'multioptions' => $brandOptions
                )),
                array('select', 'order', array(
                    'label'        => 'Сортировка',
                    'multioptions' => array(
                        0 => 'id asc',
                        1 => 'id desc',
                        2 => 'Название asc',
                        3 => 'Название desc',
                    ),
                    'decorators'   => array(
                        'ViewHelper'
                    )
                )),
            )
        ));

        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            unset($params['submit']);
            foreach ($params as $key => $value) {
                if (strlen($value) <= 0) {
                    unset($params[$key]);
                }
            }
            return $this->_redirect($this->_helper->url->url($params));
        }

        $engineTable = $this->getEngineTable();

        $select = $engineTable->select(true);

        if ($form->isValid($this->_getAllParams())) {
            $values = $form->getValues();

            if ($values['name']) {
                $select->where('engines.caption like ?', '%' . $values['name'] . '%');
            }

            if ($values['brand_id']) {
                $select
                    ->join('engine_parent_cache', 'engines.id = engine_parent_cache.engine_id', null)
                    ->join('brand_engine', 'engine_parent_cache.parent_id = brand_engine.engine_id', null)
                    ->where('brand_engine.brand_id = ?', $values['brand_id']);
            }

            switch ($values['order']) {
                case 0:
                    $select->order('engines.id asc');
                    break;

                case 1:
                    $select->order('engines.id desc');
                    break;

                case 2:
                    $select->order('engines.caption asc');
                    break;

                case 3:
                    $select->order('engines.caption desc');
                    break;
            }
        }

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(10)
            ->setCurrentPageNumber($this->_getParam('page'));

        $pictureTable = new Picture();

        $engines = array();
        foreach ($paginator->getCurrentItems() as $engine) {

            $pictures = $pictureTable->fetchAll(array(
                'engine_id = ?' => $engine->id,
                'type = ?'      => Picture::ENGINE_TYPE_ID
            ), 'id', 4);

            $engines[] = array(
                'name'     => $engine->caption,
                'pictures' => $pictures,
                'moderUrl' => $this->engineModerUrl($engine->id),
                'specsUrl' => $this->_helper->url->url(array(
                    'module'     => 'default',
                    'controller' => 'cars',
                    'action'     => 'engine-spec-editor',
                    'engine_id'  => $engine->id
                ), 'default', true),
            );
        }

        $this->view->assign(array(
            'form'      => $form,
            'paginator' => $paginator,
            'engines'   => $engines
        ));
    }

    public function addAction()
    {
        if (!$this->_helper->user()->isAllowed('engine', 'add')) {
            return $this->_forward('forbidden', 'error');
        }

        $engineTable = $this->getEngineTable();
        $parentEngine = $engineTable->find($this->_getParam('parent_id'))->current();

        $brandTable = new Brands();
        $brandRow = $brandTable->find($this->_getParam('brand_id'))->current();

        $form = new Application_Form_Moder_Engine_Add(array(
            'description'  => 'Новый двигатель',
            'disableBrand' => (bool)$parentEngine,
            'action'       => $this->_helper->url->url(array(
                'brand_id' => null
            )),
        ));

        if ($brandRow) {
            $form->setDefaults(array(
                'brand_id' => $brandRow->id
            ));
        }

        $request = $this->getRequest();

        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $engine = $engineTable->createRow(array(
                'last_editor_id' => $this->_helper->user()->get()->id,
                'caption'        => $values['caption']
            ));
            $engine->save();

            if (!$parentEngine) {
                $brandEngineTable = new Brand_Engine();
                $brandEngineRow = $brandEngineTable->createRow(array(
                    'brand_id'  => $values['brand_id'],
                    'engine_id' => $engine->id,
                    'add_date'  => new Zend_Db_Expr('now()')
                ));
                $brandEngineRow->save();
            }

            $epcTable = new Engine_Parent_Cache();
            $epcTable->rebuildOnCreate($engine);

            if ($parentEngine) {
                $engine->parent_id = $parentEngine->id;
                $engine->save();

                $epcTable = new Engine_Parent_Cache();
                $epcTable->rebuildOnAddParent($engine);

                $specService = new Application_Service_Specifications();
                $specService->updateActualValues(3, $engine->id);
            }

            $url = $this->engineModerUrl($engine->id);

            $this->_helper->log(sprintf(
                'Создан новый двигатель %s',
                $this->view->htmlA($url, $engine->caption)
            ), $engine);

            return $this->_redirect($url);
        }

        $this->view->assign(array(
            'form'         => $form,
            'parentEngine' => $parentEngine
        ));
    }

    private function enginesWalkTree($parentId, $brandId, $excludeId)
    {
        $engineTable = $this->getEngineTable();
        $select = $engineTable->select(true)
            ->where('engines.id <> ?', $excludeId)
            ->order('engines.caption');

        if ($brandId) {
            $select
                ->join('brand_engine', 'engines.id = brand_engine.engine_id', null)
                ->where('brand_id = ?', $brandId);
        }
        if ($parentId) {
            $select->where('parent_id = ?', $parentId);
        }

        $rows = $engineTable->fetchAll($select);

        $engines = array();
        foreach ($rows as $row) {
            $engines[] = array(
                'id'     => $row->id,
                'name'   => $row->caption,
                'childs' => $this->enginesWalkTree($row->id, null, $excludeId)
            );
        }

        return $engines;
    }

    public function cancelParentAction()
    {
        if (!$this->_helper->user()->isAllowed('engine', 'edit')) {
            return $this->_forward('forbidden', 'error');
        }
        $engineTable = $this->getEngineTable();

        $engine = $engineTable->find($this->_getParam('engine_id'))->current();
        if (!$engine) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $engine->parent_id = null;
        $engine->save();

        $epcTable = new Engine_Parent_Cache();
        $epcTable->rebuildOnRemoveParent($engine);

        $specService = new Application_Service_Specifications();
        $specService->updateActualValues(3, $engine->id);

        $this->_helper->log(sprintf(
            'Двигатель %s перестал иметь родительский двигатель',
            $this->view->htmlA($this->engineModerUrl($engine->id), $engine->caption)
        ), $engine);

        return $this->_redirect($this->engineModerUrl($engine->id));
    }

    public function selectParentAction()
    {
        if (!$this->_helper->user()->isAllowed('engine', 'edit')) {
            return $this->_forward('forbidden', 'error');
        }
        $engineTable = $this->getEngineTable();

        $engine = $engineTable->find($this->_getParam('engine_id'))->current();
        if (!$engine) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $parentId = (int)$this->_getParam('parent_id');

        if ($parentId) {

            $parentEngine = $engineTable->find($parentId)->current();
            if (!$parentEngine) {
                return $this->_forward('notfound', 'error', 'default');
            }

            // check for cicle
            if ($engine->id == $parentEngine->id) {
                return $this->_forward('notfound', 'error', 'default');
            }

            $currentEngine = $parentEngine;
            while ($currentEngine = $engineTable->find($currentEngine->parent_id)->current()) {
                if ($engine->id == $currentEngine->id) {
                    return $this->_forward('notfound', 'error', 'default');
                }
            }

            $engine->parent_id = $parentEngine->id;
            $engine->save();

            $epcTable = new Engine_Parent_Cache();
            $epcTable->rebuildOnRemoveParent($engine);
            $epcTable->rebuildOnAddParent($engine);

            $specService = new Application_Service_Specifications();
            $specService->updateActualValues(3, $engine->id);

            $this->_helper->log(sprintf(
                'Двигатель %s назначен родительским для двигателя %s',
                $this->view->htmlA($this->engineModerUrl($parentEngine->id), $parentEngine->caption),
                $this->view->htmlA($this->engineModerUrl($engine->id), $engine->caption)
            ), array($engine, $parentEngine));

            return $this->_redirect($this->engineModerUrl($engine->id));
        }

        $brandId = (int)$this->_getParam('brand_id');
        $brandTable = new Brands();

        if ($brandId) {

            $brand = $brandTable->find($brandId)->current();

            if (!$brand) {
                return $this->_forward('notfound', 'error');
            }

            $brands = array();

            $engines = $this->enginesWalkTree(null, $brand->id, $engine->id);

        } else {
            $brands = $brandTable->fetchAll(
                $brandTable->select(true)
                    ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                    ->group('brands.id')
                    ->order(array('brands.position', 'brands.caption'))
            );

            $brand = false;

            $engines = array();
        }

        $this->view->assign(array(
            'engine'  => $engine,
            'brand'   => $brand,
            'brands'  => $brands,
            'engines' => $engines
        ));
    }

    public function rebuildAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        if (!$this->_helper->user()->isAllowed('engine', 'edit')) {
            return $this->_forward('forbidden', 'error');
        }

        $epcTable = new Engine_Parent_Cache();

        $epcTable->rebuild();

        print 'ok';
    }

    public function removeBrandAction()
    {
        if (!$this->_helper->user()->isAllowed('engine', 'edit')) {
            return $this->_forward('forbidden', 'error');
        }

        $engineTable = $this->getEngineTable();
        $engineRow =$engineTable->find($this->_getParam('engine_id'))->current();

        if (!$engineRow) {
            return $this->_forward('notfound', 'error');
        }

        $brandTable = new Brands();

        $brandRow = $brandTable->find($this->_getParam('brand_id'))->current();
        if (!$brandRow) {
            return $this->_forward('notfound', 'error');
        }

        $brandEngineTable = new Brand_Engine();

        $brandEngineRow = $brandEngineTable->fetchRow(array(
            'brand_id = ?'  => $brandRow->id,
            'engine_id = ?' => $engineRow->id
        ));

        $engineUrl = $this->engineModerUrl($engineRow->id);

        if ($brandEngineRow) {

            $message = sprintf(
                'Двигатель %s отвязан от бренда %s',
                $this->view->htmlA($engineUrl, $engineRow->caption),
                $this->view->escape($brandRow->caption)
            );
            $this->_helper->log($message, $engineRow);

            $brandEngineRow->delete();
        }

        return $this->_redirect($engineUrl);
    }

    public function addBrandAction()
    {
        if (!$this->_helper->user()->isAllowed('engine', 'edit')) {
            return $this->_forward('forbidden', 'error');
        }

        $engineTable = $this->getEngineTable();
        $engineRow =$engineTable->find($this->_getParam('engine_id'))->current();

        if (!$engineRow) {
            return $this->_forward('notfound', 'error');
        }

        $brandTable = new Brands();

        $brandRow = $brandTable->find($this->_getParam('brand_id'))->current();
        if ($brandRow) {

            $brandEngineTable = new Brand_Engine();

            $brandEngineRow = $brandEngineTable->fetchRow(array(
                'brand_id = ?'  => $brandRow->id,
                'engine_id = ?' => $engineRow->id
            ));

            $engineUrl = $this->engineModerUrl($engineRow->id);

            if (!$brandEngineRow) {
                $brandEngineRow = $brandEngineTable->createRow(array(
                    'brand_id'  => $brandRow->id,
                    'engine_id' => $engineRow->id,
                    'add_date'  => new Zend_Db_Expr('now()')
                ));
                $brandEngineRow->save();

                $message = sprintf(
                    'Двигатель %s добавлен к бренду %s',
                    $this->view->htmlA($engineUrl, $engineRow->caption),
                    $this->view->escape($brandRow->caption)
                );
                $this->_helper->log($message, $engineRow);
            }

            return $this->_redirect($engineUrl);
        }

        $brands = $brandTable->fetchAll(null, array('position', 'caption'));

        $this->view->assign(array(
            'engine' => $engineRow,
            'brands' => $brands
        ));
    }
}
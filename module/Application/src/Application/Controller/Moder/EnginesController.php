<?php

namespace Application\Controller\Moder;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Form\Moder\EngineAdd as EngineAddForm;
use Application\Model\DbTable\Brand as BrandTable;
use Application\Model\DbTable\BrandEngine;
use Application\Paginator\Adapter\Zend1DbTableSelect;
use Application\Service\SpecificationsService;

use Engine_Parent_Cache;
use Engines;
use Picture;

use Exception;

use Zend_Db_Expr;

class EnginesController extends AbstractActionController
{
    /**
     * @var Engines
     */
    private $engineTable = null;

    /**
     * @var Form
     */
    private $filterForm;

    /**
     * @var Form
     */
    private $editForm;

    public function __construct(Form $filterForm, Form $editForm)
    {
        $this->filterForm = $filterForm;
        $this->editForm = $editForm;
    }

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
        return $this->url()->fromRoute('moder/engines/params', [
            'action'    => 'engine',
            'engine_id' => $id
        ]);
    }

    public function engineAction()
    {
        if (!$this->user()->inheritsRole('moder') ) {
            return $this->forbiddenAction();
        }

        $engineTable = $this->getEngineTable();

        $engine = $engineTable->find($this->params('engine_id'))->current();
        if (!$engine) {
            return $this->notFoundAction();
        }

        $isModer = $this->user()->isAllowed('engine', 'edit');
        $canEdit = $isModer;

        if ($canEdit) {
            $this->editForm->setAttribute('action', $this->url()->fromRoute(null, [], [], true));

            $this->editForm->populateValues($engine->toArray());
            $request = $this->getRequest();

            if ($request->isPost()) {
                $this->editForm->setData($this->params()->fromPost());
                if ($this->editForm->isValid()) {
                    $values = $this->editForm->getData();

                    $user = $this->user()->get();
                    $engine->setFromArray($values);
                    $engine->last_editor_id = $user->id;
                    $engine->save();

                    $message = sprintf(
                        'Редактирование двигателя %s',
                        $engine->caption
                    );
                    $this->log($message, $engine);

                    return $this->redirect()->toUrl($this->engineModerUrl($engine->id));
                }
            }
        }

        $childEngines = $engineTable->fetchAll([
            'parent_id = ?' => $engine->id
        ]);

        $parentEngine = null;
        if ($engine->parent_id) {
            $parentEngine = $engineTable->find($engine->parent_id)->current();
        }

        $brandTable = new BrandTable();
        $brandRows = $brandTable->fetchAll(
            $brandTable->select(true)
                ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                ->where('brand_engine.engine_id = ?', $engine->id)
                ->order(['brands.position', 'brands.caption'])
        );

        $brands = [];
        foreach ($brandRows as $brandRow) {

            $cataloguePaths = $this->catalogue()->engineCataloguePaths($engine, [
                'brand_id' => $brandRow->id
            ]);

            $urls = [];
            foreach ($cataloguePaths as $cataloguePath) {
                $urls[] = $this->url()->fromRoute('catalogue', [
                    'action'        => 'engines',
                    'brand_catname' => $cataloguePath['brand_catname'],
                    'path'          => $cataloguePath['path'],
                ]);
            }

            $brands[] = [
                'name'      => $brandRow->caption,
                'urls'      => $urls,
                'inherited' => false,
                'moderUrl'  => $this->url()->fromRoute('moder/brands/params', [
                    'action'   => 'brand',
                    'brand_id' => $brandRow->id
                ]),
                'removeUrl'  => $this->url()->fromRoute('moder/engines/params', [
                    'action'    => 'remove-brand',
                    'engine_id' => $engine->id,
                    'brand_id'  => $brandRow->id
                ]),
            ];
        }

        $brandEngineTable = new BrandEngine();
        $brandEngineRows = $brandEngineTable->fetchAll(
            $brandEngineTable->select(true)
                ->join('brands', 'brand_engine.brand_id = brands.id', null)
                ->join('engine_parent_cache', 'brand_engine.engine_id = engine_parent_cache.parent_id', null)
                ->where('engine_parent_cache.engine_id = ?', $engine->id)
                ->where('engine_parent_cache.engine_id <> engine_parent_cache.parent_id')
                ->order(['brands.position', 'brands.caption'])
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

            $cataloguePaths = $this->catalogue()->engineCataloguePaths($engine, [
                'brand_id' => $brandRow->id
            ]);

            $urls = [];
            foreach ($cataloguePaths as $cataloguePath) {
                $urls[] = $this->url()->fromRoute('catalogue', [
                    'action'        => 'engines',
                    'brand_catname' => $cataloguePath['brand_catname'],
                    'path'          => $cataloguePath['path'],
                ]);
            }

            $brands[] = [
                'name'      => $brandRow->caption,
                'urls'      => $urls,
                'inherited' => true,
                'inheritedFrom' => [
                    'name' => $parentEngineRow->caption,
                    'url'  => $this->engineModerUrl($parentEngineRow->id)
                ],
                'moderUrl'  => $this->url()->fromRoute('moder/brands/params', [
                    'action'   => 'brand',
                    'brand_id' => $brandRow->id
                ]),
            ];
        }

        $specService = new SpecificationsService();
        $specsCount = $specService->getSpecsCount(3, $engine->id);

        return [
            'engine'       => $engine,
            'parentEngine' => $parentEngine,
            'childEngines' => $childEngines,
            'canEdit'      => $canEdit,
            'cars'         => $engine->findCars(),
            'brands'       => $brands,
            'specsCount'   => $specsCount,
            'formModerEngineEdit' => $this->editForm
        ];
    }

    public function indexAction()
    {
        if (!$this->user()->inheritsRole('moder') ) {
            return $this->forbiddenAction();
        }

        $brandTable = new BrandTable();

        $db = $brandTable->getAdapter();

        $brandOptions = ['' => '-'] + $db->fetchPairs(
            $db->select()
                ->from('brands', ['id', 'caption'])
                ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                ->group('brands.id')
                ->order(['brands.position', 'brands.caption'])
        );

        $this->filterForm->get('brand_id')->setValueOptions($brandOptions);

        if ($this->getRequest()->isPost()) {
            $this->filterForm->setData($this->params()->fromPost());
            if ($this->filterForm->isValid()) {
                $params = $this->filterForm->getData();
                return $this->redirect()->toRoute('moder/engines/params', $params, [], true);
            }
        }

        $engineTable = $this->getEngineTable();

        $select = $engineTable->select(true);

        $this->filterForm->setData($this->params()->fromRoute());
        if ($this->filterForm->isValid()) {
            $values = $this->filterForm->getData();

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

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage(10)
            ->setCurrentPageNumber($this->params('page'));

        $pictureTable = new Picture();

        $engines = [];
        foreach ($paginator->getCurrentItems() as $engine) {

            $pictures = $pictureTable->fetchAll([
                'engine_id = ?' => $engine->id,
                'type = ?'      => Picture::ENGINE_TYPE_ID
            ], 'id', 4);

            $engines[] = [
                'name'     => $engine->caption,
                'pictures' => $pictures,
                'moderUrl' => $this->engineModerUrl($engine->id),
                'specsUrl' => $this->url()->fromRoute('cars', [
                    'action'    => 'engine-spec-editor',
                    'engine_id' => $engine->id
                ]),
            ];
        }

        return [
            'form'      => $this->filterForm,
            'paginator' => $paginator,
            'engines'   => $engines
        ];
    }

    public function addAction()
    {
        if (!$this->user()->isAllowed('engine', 'add')) {
            return $this->forbiddenAction();
        }

        $engineTable = $this->getEngineTable();
        $parentEngine = $engineTable->find($this->params('parent_id'))->current();

        $brandTable = new BrandTable();
        $brandRow = $brandTable->find($this->params('brand_id'))->current();

        $form = new EngineAddForm(null, [
            'disableBrand' => (bool)$parentEngine,
            'attributes' => [
                'action' => $this->url()->fromRoute(null, [
                    'brand_id' => null
                ], [], true),
            ]
        ]);

        if ($brandRow) {
            $form->populateValues([
                'brand_id' => $brandRow->id
            ]);
        }

        $request = $this->getRequest();

        if ($request->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $values = $form->getData();

                $engine = $engineTable->createRow([
                    'last_editor_id' => $this->user()->get()->id,
                    'caption'        => $values['caption']
                ]);
                $engine->save();

                if (!$parentEngine) {
                    $brandEngineTable = new BrandEngine();
                    $brandEngineRow = $brandEngineTable->createRow([
                        'brand_id'  => $values['brand_id'],
                        'engine_id' => $engine->id,
                        'add_date'  => new Zend_Db_Expr('now()')
                    ]);
                    $brandEngineRow->save();
                }

                $epcTable = new Engine_Parent_Cache();
                $epcTable->rebuildOnCreate($engine);

                if ($parentEngine) {
                    $engine->parent_id = $parentEngine->id;
                    $engine->save();

                    $epcTable = new Engine_Parent_Cache();
                    $epcTable->rebuildOnAddParent($engine);

                    $specService = new SpecificationsService();
                    $specService->updateActualValues(3, $engine->id);
                }

                $this->log(sprintf(
                    'Создан новый двигатель %s',
                    $engine->caption
                ), $engine);

                return $this->redirect()->toUrl($this->engineModerUrl($engine->id));
            }
        }

        return [
            'form'         => $form,
            'parentEngine' => $parentEngine
        ];
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

        $engines = [];
        foreach ($rows as $row) {
            $engines[] = [
                'id'     => $row->id,
                'name'   => $row->caption,
                'childs' => $this->enginesWalkTree($row->id, null, $excludeId)
            ];
        }

        return $engines;
    }

    public function cancelParentAction()
    {
        if (!$this->user()->isAllowed('engine', 'edit')) {
            return $this->forbiddenAction();
        }
        $engineTable = $this->getEngineTable();

        $engine = $engineTable->find($this->params('engine_id'))->current();
        if (!$engine) {
            return $this->notFoundAction();
        }

        $engine->parent_id = null;
        $engine->save();

        $epcTable = new Engine_Parent_Cache();
        $epcTable->rebuildOnRemoveParent($engine);

        $specService = new SpecificationsService();
        $specService->updateActualValues(3, $engine->id);

        $this->log(sprintf(
            'Двигатель %s перестал иметь родительский двигатель',
            $engine->caption
        ), $engine);

        return $this->redirect()->toUrl($this->engineModerUrl($engine->id));
    }

    public function selectParentAction()
    {
        if (!$this->user()->isAllowed('engine', 'edit')) {
            return $this->forbiddenAction();
        }
        $engineTable = $this->getEngineTable();

        $engine = $engineTable->find($this->params('engine_id'))->current();
        if (!$engine) {
            return $this->notFoundAction();
        }

        $parentId = (int)$this->params()->fromPost('parent_id');

        if ($parentId) {

            $parentEngine = $engineTable->find($parentId)->current();
            if (!$parentEngine) {
                return $this->notFoundAction();
            }

            // check for cicle
            if ($engine->id == $parentEngine->id) {
                return $this->notFoundAction();
            }

            $currentEngine = $parentEngine;
            while ($currentEngine = $engineTable->find($currentEngine->parent_id)->current()) {
                if ($engine->id == $currentEngine->id) {
                    return $this->notFoundAction();
                }
            }

            $engine->parent_id = $parentEngine->id;
            $engine->save();

            $epcTable = new Engine_Parent_Cache();
            $epcTable->rebuildOnRemoveParent($engine);
            $epcTable->rebuildOnAddParent($engine);

            $specService = new SpecificationsService();
            $specService->updateActualValues(3, $engine->id);

            $this->log(sprintf(
                'Двигатель %s назначен родительским для двигателя %s',
                $parentEngine->caption,
                $engine->caption
            ), [$engine, $parentEngine]);

            return $this->redirect()->toUrl($this->engineModerUrl($engine->id));
        }

        $brandId = (int)$this->params()->fromPost('brand_id');
        $brandTable = new BrandTable();

        if ($brandId) {

            $brand = $brandTable->find($brandId)->current();

            if (!$brand) {
                return $this->notFoundAction();
            }

            $brands = [];

            $engines = $this->enginesWalkTree(null, $brand->id, $engine->id);

        } else {
            $brands = $brandTable->fetchAll(
                $brandTable->select(true)
                    ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                    ->group('brands.id')
                    ->order(['brands.position', 'brands.caption'])
            );

            $brand = false;

            $engines = [];
        }

        return [
            'engine'  => $engine,
            'brand'   => $brand,
            'brands'  => $brands,
            'engines' => $engines
        ];
    }

    public function rebuildAction()
    {
        if (!$this->user()->isAllowed('engine', 'edit')) {
            return $this->forbiddenAction();
        }

        $epcTable = new Engine_Parent_Cache();

        $epcTable->rebuild();

        return 'ok';
    }

    public function removeBrandAction()
    {
        if (!$this->user()->isAllowed('engine', 'edit')) {
            return $this->forbiddenAction();
        }

        $engineTable = $this->getEngineTable();
        $engineRow = $engineTable->find($this->params('engine_id'))->current();

        if (!$engineRow) {
            return $this->notFoundAction();
        }

        $brandTable = new BrandTable();

        $brandRow = $brandTable->find($this->params('brand_id'))->current();
        if (!$brandRow) {
            return $this->notFoundAction();
        }

        $brandEngineTable = new BrandEngine();

        $brandEngineRow = $brandEngineTable->fetchRow([
            'brand_id = ?'  => $brandRow->id,
            'engine_id = ?' => $engineRow->id
        ]);

        $engineUrl = $this->engineModerUrl($engineRow->id);

        if ($brandEngineRow) {

            $message = sprintf(
                'Двигатель %s отвязан от бренда %s',
                $engineRow->caption,
                $brandRow->caption
            );
            $this->log($message, $engineRow);

            $brandEngineRow->delete();
        }

        return $this->redirect()->toUrl($engineUrl);
    }

    public function addBrandAction()
    {
        if (!$this->user()->isAllowed('engine', 'edit')) {
            return $this->forbiddenAction();
        }

        $engineTable = $this->getEngineTable();
        $engineRow = $engineTable->find($this->params('engine_id'))->current();

        if (!$engineRow) {
            return $this->notFoundAction();
        }

        $brandTable = new BrandTable();

        $brandRow = $brandTable->find($this->params()->fromPost('brand_id'))->current();
        if ($brandRow) {

            $brandEngineTable = new BrandEngine();

            $brandEngineRow = $brandEngineTable->fetchRow([
                'brand_id = ?'  => $brandRow->id,
                'engine_id = ?' => $engineRow->id
            ]);

            if (!$brandEngineRow) {
                $brandEngineRow = $brandEngineTable->createRow([
                    'brand_id'  => $brandRow->id,
                    'engine_id' => $engineRow->id,
                    'add_date'  => new Zend_Db_Expr('now()')
                ]);
                $brandEngineRow->save();

                $message = sprintf(
                    'Двигатель %s добавлен к бренду %s',
                    $engineRow->caption,
                    $brandRow->caption
                );
                $this->log($message, $engineRow);
            }

            return $this->redirect()->toUrl($this->engineModerUrl($engineRow->id));
        }

        $brands = $brandTable->fetchAll(null, ['position', 'caption']);

        return [
            'engine' => $engineRow,
            'brands' => $brands
        ];
    }
}
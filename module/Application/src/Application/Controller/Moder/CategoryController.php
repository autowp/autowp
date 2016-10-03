<?php

namespace Application\Controller\Moder;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Form\Moder\Category as CategoryForm;
use Application\Model\DbTable\Brand as BrandTable;
use Application\Model\DbTable\Category;
use Application\Model\DbTable\Category\Language as CategoryLanguage;
use Application\Model\DbTable\Category\ParentTable as CategoryParent;
use Application\Model\DbTable\Category\Vehicle as CategoryVehicle;
use Application\Model\DbTable\User\CarSubscribe as UserCarSubscribe;
use Application\Model\DbTable\Vehicle;
use Application\Model\DbTable\Vehicle\ParentCache as VehicleParentCache;
use Application\Model\DbTable\Vehicle\ParentTable as VehicleParent;
use Application\Model\DbTable\Vehicle\Row as VehicleRow;

use Zend_Db_Expr;

class CategoryController extends AbstractActionController
{
    /**
     * @var Category
     */
    private $table;

    /**
     * @var CategoryLanguage
     */
    private $langTable;

    public function __construct()
    {
        $this->table = new Category();
        $this->langTable = new CategoryLanguage();
    }

    private function getCategories($parentId = null)
    {
        $select = $this->table->select(true)
            ->order('short_name');
        if ($parentId) {
            $select->where('parent_id = ?', $parentId);
        } else {
            $select->where('parent_id IS NULL');
        }
        $result = [];
        foreach ($this->table->fetchAll($select) as $category) {
            $result[] = [
                'id'   => $category->id,
                'name' => $category->name,
                'childs' => $this->getCategories($category->id)
            ];
        }

        return $result;
    }

    public function indexAction()
    {
        if (!$this->user()->isAllowed('category', 'edit')) {
            return $this->forbiddenAction();
        }

        return [
            'categories' => $this->getCategories()
        ];
    }

    private function getLanguages()
    {
        return [
            'ru', 'en', 'fr', 'zh'
        ];
    }

    private function getParentOptions($parentId = null)
    {
        $db = $this->table->getAdapter();
        $select = $db->select()
            ->from($this->table->info('name'), ['id', 'name'])
            ->order('short_name');

        if ($parentId) {
            $select->where('parent_id = ?', $parentId);
        } else {
            $select->where('parent_id is null');
        }

        $result = [];

        $pairs = $db->fetchPairs($select);

        foreach ($pairs as $id => $name) {
            $result[$id] = $name;
            foreach ($this->getParentOptions($id) as $childId => $childName) {
                $result[$childId] = '...' . $childName;
            }
        }

        return $result;
    }

    /**
     * @return CategoryForm
     */
    private function getForm()
    {
        $form = new CategoryForm(null, [
            'languages' => $this->getLanguages(),
            'parents'   => $this->getParentOptions()
        ]);
        $form->setAttribute('action', $this->url()->fromRoute(null, [], [], true));

        return $form;
    }

    public function itemAction()
    {
        if (!$this->user()->isAllowed('category', 'edit')) {
            return $this->forbiddenAction();
        }

        $languages = $this->getLanguages();

        $form = $this->getForm();

        $id = (int)$this->params('id');
        if ($id) {
            $category = $this->table->find($id)->current();
            if (!$category) {
                return $this->notFoundAction();
            }
        } else {
            $category = $this->table->createRow([
                'parent_id' => $this->params('parent_id')
            ]);
        }

        $values = $category->toArray();

        if ($category->id) {
            foreach ($languages as $lang) {
                $langCategory = $this->langTable->fetchRow([
                    'category_id = ?' => $category->id,
                    'language = ?'    => $lang
                ]);
                if ($langCategory) {
                    $values[$lang] = $langCategory->toArray();
                }
            }
        }

        $form->populateValues($values);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $values = $form->getData();

                $category->setFromArray([
                    'parent_id'      => $values['parent_id'] ? $values['parent_id'] : null,
                    'name'           => $values['name'],
                    'short_name'     => $values['short_name'],
                    'catname'        => $values['catname'],
                    'split_by_brand' => $values['split_by_brand'] ? 1 : 0,
                ]);
                $category->save();

                foreach ($languages as $lang) {
                    $langValues = $values[$lang];
                    unset($values[$lang]);

                    $langCategory = $this->langTable->fetchRow([
                        'category_id = ?' => $category->id,
                        'language = ?'    => $lang
                    ]);

                    if (!$langCategory) {
                        $langCategory = $this->langTable->fetchNew();
                        $langCategory->setFromArray([
                            'category_id' => $category->id,
                            'language'    => $lang
                        ]);
                    }

                    $langCategory->setFromArray($langValues);
                    $langCategory->save();
                }

                $cpTable = new CategoryParent();
                $cpTable->rebuild();

                return $this->redirect()->toRoute('moder/category/params', [
                    'id' => $category->id
                ], [], true);
            }
        }

        return [
            'category' => $category,
            'form'     => $form
        ];
    }

    public function organizeAction()
    {
        if (!$this->user()->isAllowed('category', 'edit')) {
            return $this->forbiddenAction();
        }

        $form = $this->getForm();

        $category = $this->table->find($this->params('id'))->current();
        if (!$category) {
            return $this->notFoundAction();
        }

        $brandTable = new BrandTable();

        $carParentTable = new VehicleParent();
        $carParentCacheTable = new VehicleParentCache();
        $carTable = $this->catalogue()->getCarTable();

        $order = array_merge(['car_parent.type'], $this->catalogue()->carsOrdering());

        $carParentRows = $carParentCacheTable->fetchAll(
            $carParentCacheTable->select(true)
                ->join('cars', 'car_parent_cache.car_id = cars.id', null)
                ->join('category_car', 'car_parent_cache.parent_id = category_car.car_id', null)
                ->where('category_car.category_id = ?', $category->id)
                ->order($this->catalogue()->carsOrdering())
        );

        $brandAdapter = $brandTable->getAdapter();

        $childs = [];
        foreach ($carParentRows as $carParentRow) {

            $carRow = $carTable->find($carParentRow->car_id)->current();

            $brandNames = $brandAdapter->fetchPairs(
                $brandAdapter->select()
                    ->from($brandTable->info('name'), ['id', 'caption'])
                    ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                    ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                    ->where('car_parent_cache.car_id = ?', $carRow->id)
                    ->group('brands.id')
            );

            /*$brandIds = array_keys($brandNames);
            $filtered = [];*/

            /*foreach ($brandNames as $brandId => $brandName) {
                $skip = $brandAdapter->fetchOne(
                    $brandAdapter->select(true)
                        ->from('cars', new Zend_Db_Expr(1))
                        ->join(['childs' => 'car_parent_cache'], 'cars.id = childs.parent_id', null)
                        ->where('childs.diff > 0')
                        ->where('childs.car_id = ?', $carRow->id)
                        ->join(['parents' => 'car_parent_cache'], 'cars.id = parents.car_id', null)
                        ->join('brands_cars', 'parents.parent_id = brands_cars.car_id', null)
                        ->where('brands_cars.brand_id = ?', $brandId)
                        ->join(['parents2' => 'car_parent_cache'], 'cars.id = parents2.car_id', null)
                        ->join('category_car', 'parents2.parent_id = category_car.car_id', null)
                        ->where('category_car.category_id = ?', $category->id)
                        ->limit(1)
                );

                if (!$skip) {
                    $filtered[$brandId] = $brandName;
                }
            }*/

            if (count($brandNames)) {

                $categoryLinksCount = $brandAdapter->fetchOne(
                    $brandAdapter->select()
                        ->from('category_car', 'count(distinct category_car.car_id)')
                        ->where('category_car.category_id = ?', $category->id)
                        ->where('car_parent_cache.diff > 0')
                        ->join('car_parent_cache', 'category_car.car_id = car_parent_cache.parent_id')
                        ->where('car_parent_cache.car_id = ?', $carRow->id)
                );

                if ($categoryLinksCount < count($brandNames)) {
                    $childs[$carRow->id] = str_repeat('...', $carParentRow->diff) . ' ' . implode(', ', $brandNames) . ': ' . $carRow->getFullName($this->language());
                }
            }
        }
        //exit;

        $form = new Application_Form_Moder_Category_Organize([
            'action'       => $this->url()->fromRoute(null, [], [], true),
            'childOptions' => $childs,
        ]);

        $form->populate([
            'is_group' => 1
        ]);

        $request = $this->getRequest();
        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            switch ((int)$values['today']) {
                case 0:
                    $values['today'] = null;
                    break;

                case 1:
                    $values['today'] = 0;
                    break;

                case 2:
                    $values['today'] = 1;
                    break;
            }

            $cars = new Vehicle();
            $newCar = $cars->createRow([
                'caption'     => $values['caption'],
                'body'        => $values['body'],
                'begin_year'  => $values['begin_year'],
                'end_year'    => $values['end_year'],
                'today'       => $values['today'],
                'car_type_id' => $values['car_type_id'],
                'is_group'    => true,
            ]);
            $newCar->save();

            $cpcTable = new VehicleParentCache();
            $cpcTable->rebuildCache($newCar);

            $url = $this->url()->fromRoute('moder/cars/params', [
                'action' => 'car',
                'car_id' => $newCar->id
            ]);
            $this->log(sprintf(
                'Создан новый автомобиль %s',
                $newCar->getFullName('en')
            ), $newCar);

            $ccTable = new CategoryVehicle();

            $user = $this->user()->get();

            $ccRow = $ccTable->createRow([
                'category_id'  => $category->id,
                'car_id'       => $newCar->id,
                'add_datetime' => new Zend_Db_Expr('NOW()'),
                'user_id'      => $user->id
            ]);
            $ccRow->save();


            $childCarRows = $carTable->find($values['childs']);

            foreach ($childCarRows as $childCarRow) {
                $carParentTable->addParent($childCarRow, $newCar);

                $message = sprintf(
                    '%s выбран как родительский автомобиль для %s',
                    $newCar->getFullName('en'),
                    $childCarRow->getFullName('en')
                );
                $this->log($message, [$newCar, $childCarRow]);


                $ccRow = $ccTable->fetchRow([
                    'category_id = ?' => $category->id,
                    'car_id = ?'      => $childCarRow->id
                ]);
                if ($ccRow) {
                    $ccRow->delete();
                }
            }

            $user = $this->user()->get();
            $ucsTable = new UserCarSubscribe();
            $ucsTable->subscribe($user, $newCar);

            return $this->redirect()->toRoute(null, [
                'ok' => '1'
            ], [], true);
        }

        return [
            'category' => $category,
            'form'     => $form
        ];
    }

    /**
     * @param VehicleRow $car
     * @return string
     */
    private function carModerUrl(VehicleRow $car, $full = false, $tab = null)
    {
        return $this->url()->fromRoute('moder/cars/params', [
            'action' => 'car',
            'car_id' => $car->id,
            'tab'    => $tab
        ], [
            'force_canonical' => $full
        ]);
    }
}
<?php

class Moder_CategoryController extends Zend_Controller_Action
{
    /**
     * @var Pages
     */
    private $table;

    /**
     * @var Category_Language
     */
    private $langTable;

    public function init()
    {
        parent::init();

        $this->table = new Category();
        $this->langTable = new Category_Language();
    }

    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder') ) {
            return $this->_forward('forbidden', 'error', 'default');
        }
    }

    public function indexAction()
    {
        if (!$this->_helper->user()->isAllowed('category', 'edit')) {
            return $this->_forward('forbidden', 'error');
        }
    }

    private function getLanguages()
    {
        return array(
            'ru', 'en', 'fr'
        );
    }

    private function getForm()
    {
        return new Application_Form_Moder_Category_Edit(array(
            'action'    => $this->_helper->url->url(),
            'languages' => $this->getLanguages()
        ));
    }

    public function newAction()
    {
        if (!$this->_helper->user()->isAllowed('category', 'edit')) {
            return $this->_forward('forbidden', 'error');
        }

        $form = $this->getForm();

        $form->populate($this->_getAllParams());

        $request = $this->getRequest();

        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();
            $languages = $this->getLanguages();

            $row = $this->table->fetchNew();
            $row->setFromArray($values);
            $row->save();

            foreach ($languages as $lang) {
                $langValues = $values[$lang];
                unset($values[$lang]);

                $langRow = $this->langTable->fetchRow(array(
                    'category_id = ?' => $row->id,
                    'language = ?'    => $lang
                ));

                if (!$langRow) {
                    $langRow = $this->langTable->fetchNew();
                    $langRow->setFromArray(array(
                        'category_id' => $row->id,
                        'language'    => $lang
                    ));
                }

                $langRow->setFromArray($langValues);
                $langRow->save();
            }

            $cpTable = new Category_Parent();
            $cpTable->rebuild();

            return $this->_redirect($this->_helper->url->url(array(
                'action' => 'edit',
                'id'     => $row->id
            )));
        }

        $this->view->form = $form;
    }

    public function editAction()
    {
        if (!$this->_helper->user()->isAllowed('category', 'edit')) {
            return $this->_forward('forbidden', 'error');
        }

        $languages = $this->getLanguages();

        $form = $this->getForm();

        $page = $this->table->find($this->_getParam('id'))->current();

        if (!$page)
            return $this->_forward('notfound', 'error');

        $values = $page->toArray();
        foreach ($languages as $lang) {
            $langPage = $this->langTable->fetchRow(array(
                'category_id = ?' => $page->id,
                'language = ?'    => $lang
            ));
            if ($langPage) {
                $values[$lang] = $langPage->toArray();
            }
        }

        $form->populate($values);

        $request = $this->getRequest();
        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            foreach ($languages as $lang) {
                $langValues = $values[$lang];
                unset($values[$lang]);

                $langPage = $this->langTable->fetchRow(array(
                    'category_id = ?' => $page->id,
                    'language = ?'    => $lang
                ));

                if (!$langPage) {
                    $langPage = $this->langTable->fetchNew();
                    $langPage->setFromArray(array(
                        'category_id' => $page->id,
                        'language'    => $lang
                    ));
                }

                $langPage->setFromArray($langValues);
                $langPage->save();
            }

            $page->setFromArray($values);
            $page->save();

            $cpTable = new Category_Parent();
            $cpTable->rebuild();

            return $this->_redirect($this->_helper->url->url());
        }

        $this->view->form = $form;
    }

    public function organizeAction()
    {
        if (!$this->_helper->user()->isAllowed('category', 'edit')) {
            return $this->_forward('forbidden', 'error');
        }

        $form = $this->getForm();

        $category = $this->table->find($this->_getParam('id'))->current();
        if (!$category) {
            return $this->_forward('notfound', 'error');
        }

        $brandTable = new Brands();

        $carParentTable = new Car_Parent();
        $carParentCacheTable = new Car_Parent_Cache();
        $carTable = $this->_helper->catalogue()->getCarTable();

        $order = array_merge(array('car_parent.type'), $this->_helper->catalogue()->carsOrdering());

        $carParentRows = $carParentCacheTable->fetchAll(
            $carParentCacheTable->select(true)
                ->join('cars', 'car_parent_cache.car_id = cars.id', null)
                ->join('category_car', 'car_parent_cache.parent_id = category_car.car_id', null)
                ->where('category_car.category_id = ?', $category->id)
                ->order($this->_helper->catalogue()->carsOrdering())
        );

        $brandAdapter = $brandTable->getAdapter();

        $childs = array();
        foreach ($carParentRows as $carParentRow) {

            $carRow = $carTable->find($carParentRow->car_id)->current();

            $brandNames = $brandAdapter->fetchPairs(
                $brandAdapter->select()
                    ->from($brandTable->info('name'), array('id', 'caption'))
                    ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                    ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                    ->where('car_parent_cache.car_id = ?', $carRow->id)
                    ->group('brands.id')
            );

            /*$brandIds = array_keys($brandNames);
            $filtered = array();*/

            /*foreach ($brandNames as $brandId => $brandName) {
                $skip = $brandAdapter->fetchOne(
                    $brandAdapter->select(true)
                        ->from('cars', new Zend_Db_Expr(1))
                        ->join(array('childs' => 'car_parent_cache'), 'cars.id = childs.parent_id', null)
                        ->where('childs.diff > 0')
                        ->where('childs.car_id = ?', $carRow->id)
                        ->join(array('parents' => 'car_parent_cache'), 'cars.id = parents.car_id', null)
                        ->join('brands_cars', 'parents.parent_id = brands_cars.car_id', null)
                        ->where('brands_cars.brand_id = ?', $brandId)
                        ->join(array('parents2' => 'car_parent_cache'), 'cars.id = parents2.car_id', null)
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
                    $childs[$carRow->id] = str_repeat('...', $carParentRow->diff) . ' ' . implode(', ', $brandNames) . ': ' . $carRow->getFullName();

                    //print $this->view->htmlA($this->carModerUrl($carRow), implode(', ', $brandNames) . ': ' . $carRow->getFullName()) . '<br />';
                }
            }
        }
        //exit;

        $form = new Application_Form_Moder_Category_Organize(array(
            'action'       => $this->_helper->url->url(),
            'childOptions' => $childs,
        ));

        $form->populate(array(
            'is_group' => 1
        ));

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

            $cars = new Cars();
            $newCar = $cars->createRow(array(
                'caption'     => $values['caption'],
                'body'        => $values['body'],
                'begin_year'  => $values['begin_year'],
                'end_year'    => $values['end_year'],
                'today'       => $values['today'],
                'car_type_id' => $values['car_type_id'],
                'is_group'    => true,
            ));
            $newCar->save();

            $cpcTable = new Car_Parent_Cache();
            $cpcTable->rebuildCache($newCar);

            $url = $this->_helper->url->url(array(
                'module'     => 'moder',
                'controller' => 'cars',
                'action'     => 'car',
                'car_id'     => $newCar->id
            ), 'default', true);
            $this->_helper->log(sprintf(
                'Создан новый автомобиль %s',
                $this->view->htmlA($url, $newCar->getFullName())
            ), $newCar);

            $ccTable = new Category_Car();

            $user = $this->_helper->user()->get();

            $ccRow = $ccTable->createRow(array(
                'category_id'  => $category->id,
                'car_id'       => $newCar->id,
                'add_datetime' => new Zend_Db_Expr('NOW()'),
                'user_id'      => $user->id
            ));
            $ccRow->save();


            $childCarRows = $carTable->find($values['childs']);

            foreach ($childCarRows as $childCarRow) {
                $carParentTable->addParent($childCarRow, $newCar);

                $message = sprintf(
                    '%s выбран как родительский автомобиль для %s',
                    $this->view->htmlA($this->carModerUrl($newCar), $newCar->getFullName()),
                    $this->view->htmlA($this->carModerUrl($childCarRow), $childCarRow->getFullName())
                );
                $this->_helper->log($message, array($newCar, $childCarRow));


                $ccRow = $ccTable->fetchRow(array(
                    'category_id = ?' => $category->id,
                    'car_id = ?'      => $childCarRow->id
                ));
                if ($ccRow) {
                    $ccRow->delete();
                }
            }

            $user = $this->_helper->user()->get();
            $ucsTable = new User_Car_Subscribe();
            $ucsTable->subscribe($user, $newCar);

            return $this->_redirect($this->_helper->url->url(array(
                'ok' => '1'
            )));
        }

        $this->view->assign(array(
            'category' => $category,
            'form'     => $form
        ));
    }

    /**
     * @param Cars_Row $car
     * @return string
     */
    private function carModerUrl(Cars_Row $car, $full = false, $tab = null)
    {
        return
            ($full ? 'http://'.$_SERVER['HTTP_HOST'] : '') .
            $this->_helper->url->url(array(
                'module'        => 'moder',
                'controller'    => 'cars',
                'action'        => 'car',
                'car_id'        => $car->id,
                'tab'           => $tab
            ), 'default', true);
    }
}
<?php

use Application\Model\Message;
use Application\Service\TrafficControl;

class Moder_PicturesController extends Zend_Controller_Action
{
    private $table;

    /**
     * @var Car_Parent
     */
    private $carParentTable;

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

    private function getCarParentTable()
    {
        return $this->carParentTable
            ? $this->carParentTable
            : $this->carParentTable = new Car_Parent();
    }

    public function init()
    {
        parent::init();

        $this->table = $this->_helper->catalogue()->getPictureTable();
    }

    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder') ) {
            return $this->forward('forbidden', 'error', 'default');
        }
    }

    public function ownerTypeaheadAction()
    {
        $q = $this->_getParam('query');

        $users = new Users();

        $selects = array();

        $selects[] = $users->select(true)
            ->join(array('p' => 'pictures'), 'users.id = p.owner_id', null)
            ->group('users.id')
            ->where('users.id like ?', $q . '%')
            ->limit(10);

        $selects[] = $users->select(true)
            ->join(array('p' => 'pictures'), 'users.id = p.owner_id', null)
            ->group('users.id')
            ->where('users.login like ?', $q . '%')
            ->limit(10);

        $selects[] = $users->select(true)
            ->join(array('p' => 'pictures'), 'users.id = p.owner_id', null)
            ->group('users.id')
            ->where('users.identity like ?', $q . '%')
            ->limit(10);

        $selects[] = $users->select(true)
            ->join(array('p' => 'pictures'), 'users.id = p.owner_id', null)
            ->group('users.id')
            ->where('users.name like ?', $q . '%')
            ->limit(10);


        $options = array();
        foreach ($selects as $select) {
            if (count($options) < 10) {
                foreach ($users->fetchAll($select) as $user) {
                    $str = array('#' . $user->id);
                    if ($user->name) {
                        $str[] = $user->name;
                        if ($user->login) {
                            $str[] = '(' . $user->login . ')';
                        }
                    } else {
                        $str[] = $user->login;
                    }
                    $options[$user->id] = implode(' ', $str);
                }
            }
        }

        return $this->_helper->json(array_values($options));
    }

    private function getFilterForm()
    {
        $db = $this->table->getAdapter();

        $resOptions = $db->fetchCol(
            $db->select()
                ->from('pictures', new Zend_Db_Expr('CONCAT(width, "×", height) AS res'))
                ->where('status = ?', Picture::STATUS_INBOX)
                ->group(array('width', 'height'))
        );
        $resOptions = array_combine($resOptions, $resOptions);

        $brandMultioptions = $db->fetchPairs(
            $db->select()
                ->from('brands', array('id', 'caption'))
                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->join('pictures', 'car_parent_cache.car_id = pictures.car_id', null)
                ->where('pictures.status = ?', Picture::STATUS_INBOX)
                ->group('brands.id')
                ->order(array('brands.position', 'brands.caption'))
        );

        return new Application_Form_Moder_Inbox(array(
            'action'                  => $this->_helper->url->url(),
            'perspectiveMultioptions' => array(
                ''     => 'любой',
                'null' => 'не задан'
            ) + $db->fetchPairs(
                $db
                    ->select()
                    ->from('perspectives', array('id', 'name'))
                    ->order('position')
            ),
            'brandMultioptions'       => array(
                '' => 'любой'
            ) + $brandMultioptions,
            'resolutionMultioptions' => array(
                '' => 'любое'
            ) + $resOptions,
        ));
    }

    public function indexAction()
    {
        $perPage = 24;

        $orders = array(
            1 => array('sql' => 'pictures.add_date DESC',                    'name' => 'Дата добавления (новые)'),
            2 => array('sql' => 'pictures.add_date',                         'name' => 'Дата добавления (старые)'),
            3 => array('sql' => array('pictures.width DESC', 'pictures.height DESC'), 'name' => 'Разрешение (большие)'),
            4 => array('sql' => array('pictures.width', 'pictures.height'),           'name' => 'Разрешение (маленькие)'),
            5 => array('sql' => 'pictures.filesize DESC',                    'name' => 'Размер (большие)'),
            6 => array('sql' => 'pictures.filesize',                         'name' => 'Размер (маленькие)'),
            7 => array('sql' => 'comment_topic.messages DESC',      'name' => 'Комментируемые'),
            8 => array('sql' => 'picture_view.views DESC',          'name' => 'Просмотры'),
            9 => array('sql' => 'pdr.day_date DESC',                'name' => 'Заявки на принятие/удаление'),
        );

        if ($this->getRequest()->isPost()) {
            $post = $this->getRequest()->getPost();
            foreach ($post as $key => &$value) {
                if (strlen($value) == 0) {
                    $value = null;
                }
            }
            unset($value);
            return $this->_redirect($this->_helper->url->url(
                $post
            ));
        }

        $form = $this->getFilterForm();
        $form->isValid($this->_getAllParams());
        $formdata = $form->getValues();

        $select = $this->table->select(true)
            //->where('pictures.status = ?', Picture::STATUS_INBOX)
            ->group('pictures.id');

        $joinPdr = false;
        $joinLeftComments = false;
        $joinComments = false;

        if ($formdata['order']) {
            $select->order($orders[$formdata['order']]['sql']);
            switch ($formdata['order']) {
                case 7:
                    $joinLeftComments = true;
                    break;
                case 8:
                    $select->joinLeft('picture_view', 'pictures.id = picture_view.picture_id', null);
                    break;
                case 9:
                    $joinPdr = true;
                    break;
            }
        } else {
            $select->order($orders[1]['sql']);
        }

        if (strlen($formdata['status'])) {
            switch ($formdata['status']) {
                case Picture::STATUS_INBOX:
                case Picture::STATUS_NEW:
                case Picture::STATUS_ACCEPTED:
                case Picture::STATUS_REMOVING:
                    $select->where('pictures.status = ?', $formdata['status']);
                    break;
                case 'custom1':
                    $select->where('pictures.status not in (?)', array(
                        Picture::STATUS_REMOVING,
                        Picture::STATUS_REMOVED
                    ));
                    break;
            }
        }

        if (strlen($formdata['type_id'])) {
            $select->where('pictures.type = ?', $formdata['type_id']);
        }

        if ($formdata['brand_id']) {
            if (strlen($formdata['type_id']) && in_array($formdata['type_id'], array(Picture::UNSORTED_TYPE_ID, Picture::LOGO_TYPE_ID, Picture::MIXED_TYPE_ID))) {
                $select->where('pictures.brand_id = ?', $formdata['brand_id']);
            } elseif ($formdata['type_id'] == Picture::ENGINE_TYPE_ID) {
                $select
                    ->join('engine_parent_cache', 'pictures.engine_id = engine_parent_cache.engine_id', null)
                    ->join('brand_engine', 'engine_parent_cache.parent_id = brand_engine.engine_id', null)
                    ->where('brand_engine.brand_id = ?', $formdata['brand_id']);
            } else {
                $select
                    ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                    ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                    ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                    ->where('brands_cars.brand_id = ?', $formdata['brand_id']);
            }
        }

        if ($formdata['car_id']) {
            $select
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                ->where('car_parent_cache.parent_id = ?', $formdata['car_id']);
        }

        if ($formdata['perspective_id']) {
            if ($formdata['perspective_id'] == 'null') {
                $select->where('pictures.perspective_id IS NULL');
            } else {
                $select->where('pictures.perspective_id = ?', $formdata['perspective_id']);
            }
        }

        if (strlen($formdata['comments'])) {
            if ($formdata['comments'] == '1') {
                $joinComments = true;
                $select->where('comment_topic.messages > 0');
            } elseif ($formdata['comments'] == '0') {
                $joinLeftComments = true;
                $select->where('comment_topic.messages = 0 or comment_topic.messages is null');
            }
        }

        if ($formdata['resolution']) {
            list ($width, $height) = explode('×', $formdata['resolution']);
            $select->where('pictures.height = ?', $height)
                   ->where('pictures.width = ?', $width);
        }

        if ($formdata['owner_id']) {
            $select->where('pictures.owner_id = ?', $formdata['owner_id']);
        }

        if ($formdata['car_type_id']) {
            $select->join('cars', 'pictures.car_id=cars.id', null)
                ->join('car_types_parents', 'cars.car_type_id=car_types_parents.id', null)
                ->where('car_types_parents.parent_id = ?', $formdata['car_type_id'])
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID);
        }

        if ($formdata['special_name']) {
            $select->where('pictures.name <> "" and pictures.name is not null');
        }

        if (strlen($formdata['requests'])) {
            switch ($formdata['requests']) {
                case '0':
                    $select
                        ->joinLeft(array('pdr' => 'pictures_moder_votes'), 'pictures.id=pdr.picture_id', null)
                        ->where('pdr.picture_id IS NULL');
                    break;

                case '1':
                    $select
                        ->join(array('pdr' => 'pictures_moder_votes'), 'pictures.id=pdr.picture_id', null)
                        ->where('pdr.vote > 0');
                    break;

                case '2':
                    $select
                        ->join(array('pdr' => 'pictures_moder_votes'), 'pictures.id=pdr.picture_id', null)
                        ->where('pdr.vote <= 0');
                    break;

                case '3':
                    $joinPdr = true;
                    break;
            }
        }

        if (strlen($formdata['replace'])) {
            if ($formdata['replace'] == '1') {
                $select->where('pictures.replace_picture_id');
            } elseif ($formdata['replace'] == '0') {
                $select->where('pictures.replace_picture_id is null');
            }
        }

        if ($formdata['lost']) {
            switch ($formdata['type_id']) {
                case Picture::LOGO_TYPE_ID:
                case Picture::MIXED_TYPE_ID:
                case Picture::UNSORTED_TYPE_ID:
                    $select->where('pictures.brand_id IS NULL');
                    break;
                case Picture::ENGINE_TYPE_ID:
                    $select->where('pictures.engine_id IS NULL');
                    break;
                case Picture::FACTORY_TYPE_ID:
                    $select->where('pictures.factory_id IS NULL');
                    break;
                default:
                    $select
                        ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                        ->where('pictures.car_id IS NULL');
                    break;
            }
        }

        if ($joinPdr) {
            $select
                ->join(array('pdr' => 'pictures_moder_votes'), 'pictures.id=pdr.picture_id', null);
        }

        if ($joinLeftComments) {
            $expr = 'pictures.id = comment_topic.item_id and ' .
                    $this->table->getAdapter()->quoteInto(
                        'comment_topic.type_id = ?',
                        Comment_Message::PICTURES_TYPE_ID
                    );
            $select->joinLeft('comment_topic', $expr, null);
        } elseif ($joinComments) {
            $select
                ->join('comment_topic', 'pictures.id = comment_topic.item_id', null)
                ->where('comment_topic.type_id = ?', Comment_Message::PICTURES_TYPE_ID);
        }

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage($perPage)
            ->setCurrentPageNumber($this->_getParam('page'));

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $picturesData = $this->_helper->pic->listData($select, array(
            'width' => 4
        ));

        $reasons = array(
            'плохое качество',
            'дубль',
            'любительское фото',
            'не по теме сайта',
            'другая',
            'своя'
        );
        $reasons = array_combine($reasons, $reasons);
        if (isset($_COOKIE['customReason'])) {
            foreach ((array)unserialize($_COOKIE['customReason']) as $reason)
                if (strlen($reason) && !in_array($reason, $reasons))
                    $reasons[$reason] = $reason;
        }

        $this->view->assign(array(
            'paginator'    => $paginator,
            'picturesData' => $picturesData,
            'form'         => $form,
            'reasons'      => $reasons
        ));
    }

    private function pictureUrl(Picture_Row $picture)
    {
        return $this->_helper->url->url(array(
            'module'        => 'moder',
            'controller'    => 'pictures',
            'action'        => 'picture',
            'picture_id'    => $picture->id
        ), 'default', true);
    }

    public function picturePerspectiveAction()
    {
        $picture = $this->table->find($this->_getParam('picture_id'))->current();
        if (!$picture) {
            return $this->forward('notfound', 'error', 'default');
        }

        if ($picture->type != Picture::CAR_TYPE_ID)
            throw new Exception('Картинка несовместимого типа');

        $perspectives = new Perspectives();

        $multioptions = array(
            ''    => '--'
        ) + $perspectives->getAdapter()->fetchPairs(
            $perspectives->getAdapter()->select()
                ->from($perspectives->info('name'), array('id', 'name'))
                ->order('position')
        );

        $form = new Zend_Form(array(
            'method'    => 'post',
            'action'    => $this->_helper->url->url(array(
                'module'        => 'moder',
                'controller'    => 'pictures',
                'action'        => 'picture-perspective',
                'picture_id'    => $picture->id
            ), 'default', true),
            'elements'    => array(
                array('select', 'perspective_id', array(
                    'required'     => false,
                    'label'        => 'Ракурс',
                    'decorators'   => array('ViewHelper'),
                    'multioptions' => $multioptions,

                )),
            ),
            'class' => 'tiny',
            'decorators'    => array(
                'PrepareElements',
                array('viewScript', array('viewScript' => 'forms/pictures/perspective.phtml')),
                'Form'
            ),
        ));

        $form->populate(array(
            'perspective_id' => $picture->perspective_id
        ));

        $request = $this->getRequest();

        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $user = $this->_helper->user()->get();
            $picture->perspective_id = $values['perspective_id'];
            $picture->change_perspective_user_id = $user->id;
            $picture->save();

            $this->_helper->log(sprintf(
                'Установка ракурса картинки %s',
                $this->view->htmlA($this->pictureUrl($picture), $picture->getCaption())
            ), array($picture));

            if ($request->isXmlHttpRequest())
                return $this->_helper->json(array(
                    'ok' => true
                ));

            return $this->_redirect($this->pictureUrl($picture));
        }

        $this->view->assign(array(
            'picture' => $picture,
            'form'    => $form,
            'tiny'    => (bool)$this->getParam('tiny')
        ));
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

    public function pictureSelectEngineAction()
    {
        $picture = $this->table->find($this->_getParam('picture_id'))->current();
        if (!$picture) {
            return $this->forward('notfound', 'error', 'default');
        }

        if ($picture->type != Picture::ENGINE_TYPE_ID)
            throw new Exception('Картинка несовместимого типа');

        $canMove = $this->_helper->user()->isAllowed('picture', 'move');
        if (!$canMove) {
            return $this->forward('forbidden', 'error', 'default');
        }

        $this->view->picture = $picture;

        $brand = null;
        $engines = new Engines();
        $engine = $engines->find($this->_getParam('engine'))->current();

        if ($engine) {
            $brandTable = new Brands();

            $oldBrand = null;
            $oldEngine = $picture->findParentEngines();
            if ($oldEngine) {

                $oldBrands = $brandTable->fetchAll(
                    $brandTable->select(true)
                        ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                        ->join('engine_parent_cache', 'brand_engine.engine_id = engine_parent_cache.parent_id', null)
                        ->where('engine_parent_cache.engine_id = ?', $oldEngine->id)
                        ->group('brands.id')
                );
            }

            $picture->engine_id = $engine->id;
            $picture->save();

            if ($picture->image_id) {
                $imageStorage = $this->getInvokeArg('bootstrap')
                    ->getResource('imagestorage');
                $imageStorage->changeImageName($picture->image_id, array(
                    'pattern' => $picture->getFileNamePattern(),
                ));
            } else {
                $picture->correctFileName();
            }
            $rows = $brandTable->fetchAll(
                $brandTable->select(true)
                    ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                    ->join('engine_parent_cache', 'brand_engine.engine_id = engine_parent_cache.parent_id', null)
                    ->where('engine_parent_cache.engine_id = ?', $engine->id)
            );

            foreach ($rows as $brand) {
                $brand->updatePicturesCache();
                $brand->refreshEnginePicturesCount();
            }

            foreach ($oldBrands as $oldBrand) {
                $oldBrand->updatePicturesCache();
                $oldBrand->refreshEnginePicturesCount();
            }

            $this->_helper->log(sprintf(
                'Назначение двигателя %s картинке %s',
                $this->view->escape($engine->getMetaCaption()),
                $this->view->htmlA($this->pictureUrl($picture), $picture->getCaption())
            ), array($engine, $picture));

            return $this->_redirect($this->pictureUrl($picture));
        } else {
            $brands = new Brands();
            $brand = $brands->find($this->_getParam('brand_id'))->current();
            if ($brand) {
                $engines = new Engines();
                $this->view->engines = $engines->fetchAll(
                    $engines->select(true)
                        ->join('brand_engine', 'engines.id = brand_engine.engine_id', null)
                        ->where('brand_engine.brand_id = ?', $brand->id)
                        ->order('engines.caption')
                );

                $this->view->assign(array(
                    'brand'   => $brand,
                    'engines' => $this->enginesWalkTree(null, $brand->id)
                ));

            } else {
                $this->view->brands = $brands->fetchAll(
                    $brands->select(true)
                        ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                        ->group('brands.id')
                        ->order(array('brands.position', 'brands.caption'))
                );
            }
        }

        $this->view->brand = $brand;
    }

    public function pictureSelectFactoryAction()
    {
        $picture = $this->table->find($this->_getParam('picture_id'))->current();
        if (!$picture) {
            return $this->forward('notfound', 'error', 'default');
        }

        if ($picture->type != Picture::FACTORY_TYPE_ID) {
            throw new Exception('Картинка несовместимого типа');
        }

        $canMove = $this->_helper->user()->isAllowed('picture', 'move');
        if (!$canMove) {
            return $this->forward('forbidden', 'error', 'default');
        }

        $this->view->picture = $picture;

        $factoryTable = new Factory();
        $factory = $factoryTable->find($this->_getParam('factory_id'))->current();

        if ($factory) {

            $picture->factory_id = $factory->id;
            $picture->save();

            $imageStorage = $this->getInvokeArg('bootstrap')
                ->getResource('imagestorage');
            $imageStorage->changeImageName($picture->image_id, array(
                'pattern' => $picture->getFileNamePattern(),
            ));

            $this->_helper->log(sprintf(
                'Назначение завода %s картинке %s',
                $this->view->escape($factory->name),
                $this->view->htmlA($this->pictureUrl($picture), $picture->getCaption())
            ), array($factory, $picture));

            return $this->_redirect($this->pictureUrl($picture));

        }

        $this->view->factories = $factoryTable->fetchAll(
            $factoryTable->select(true)
                ->order('name')
        );
    }

    public function pictureSelectCarAction()
    {
        $picture = $this->table->find($this->_getParam('picture_id'))->current();
        if (!$picture) {
            return $this->forward('notfound', 'error', 'default');
        }

        if ($picture->type != Picture::CAR_TYPE_ID)
            throw new Exception('Картинка несовместимого типа');

        $canMove = $this->_helper->user()->isAllowed('picture', 'move');
        if (!$canMove) {
            return $this->forward('forbidden', 'error', 'default');
        }

        $this->view->picture = $picture;

        $brand = null;
        $carTable = new Cars();
        $car = $carTable->find($this->_getParam('car_id'))->current();

        if ($car) {
            $oldCar = $picture->findParentCars();

            $picture->car_id = $car->id;
            $picture->save();

            // обнволяем кэш старого автомобиля
            if ($oldCar) {
                $oldCar->refreshPicturesCount();
                foreach ($oldCar->findBrandsViaBrands_Cars() as $brand)
                {
                    $brand->updatePicturesCache();
                    $brand->refreshPicturesCount();
                }
            }
            // обнволяем кэш нового автомобиля
            $car->refreshPicturesCount();
            foreach ($car->findBrandsViaBrands_Cars() as $brand) {
                $brand->updatePicturesCache();
                $brand->refreshPicturesCount();
            }

            if ($picture->image_id) {
                $imageStorage = $this->getInvokeArg('bootstrap')
                    ->getResource('imagestorage');
                $imageStorage->changeImageName($picture->image_id, array(
                    'pattern' => $picture->getFileNamePattern(),
                ));
            } else {
                $picture->correctFileName();
            }

            $namespace = new Zend_Session_Namespace('Moder_Car');
            $namespace->lastCarId = $car->id;

            $this->_helper->log(sprintf(
                'Картинка %s связана с автомобилем %s',
                $this->view->htmlA($this->pictureUrl($picture), $picture->id),
                $this->view->htmlA($this->_helper->url->url(array(
                    'module' => 'moder',
                    'controller' => 'cars',
                    'action'    => 'car',
                    'car_id' => $car->id
                ), 'default', true), $car->getFullName())
            ), array($car, $picture));

            return $this->_redirect($this->pictureUrl($picture));
        } else {
            $brands = new Brands();
            $brand = $brands->find($this->_getParam('brand_id'))->current();
            if ($brand) {


                /*$dpTable = new Design_Projects();
                $dpRows = $dpTable->fetchAll(
                    $dpTable->select(true)
                        ->where('brand_id = ?', $brand->id)
                        ->order('name')
                );
                $designProjects = array();
                foreach ($dpRows as $dpRow) {
                    $designProjects[] = array(
                        'name'    => $dpRow->name,
                        'cars'    => $carTable->fetchAll(array(
                            'cars.design_project_id = ?'    => $dpRow->id
                        ), array('cars.caption', 'cars.body', 'cars.begin_year'))
                    );
                }

                $carRows = $carTable->fetchAll(
                    $carTable->select(true)
                         ->join('brands_cars', 'cars.id=brands_cars.car_id', null)
                         ->where('brands_cars.brand_id = ?', $brand->id)
                         ->where('NOT cars.is_concept')
                         ->order(array('cars.caption', 'cars.body', 'cars.begin_year', 'cars.end_year'))
                );

                $cars = array();
                foreach ($carRows as $carRow) {
                    $cars[] = array(
                        'url'    => $this->_helper->url->url(array(
                            'car_id' => $carRow->id
                        )),
                        'name'   => $carRow->getFullName(),
                        'childs' => $this->loadCarSelect($carRow->id)
                    );
                }

                $carRows = $carTable->fetchAll(
                    $carTable->select(true)
                         ->join('brands_cars', 'cars.id=brands_cars.car_id', null)
                         ->where('brands_cars.brand_id = ?', $brand->id)
                         ->where('cars.is_concept')
                         ->order(array('cars.caption', 'cars.body', 'cars.begin_year', 'cars.end_year'))
                );

                $conceptCars = array();
                foreach ($carRows as $carRow) {
                    $conceptCars[] = array(
                        'url'  => $this->_helper->url->url(array(
                            'car_id' => $carRow->id
                        )),
                        'name' => $carRow->getFullName()
                    );
                }

                $this->view->assign(array(
                    'conceptCars'       => $conceptCars,
                    'cars'              => $cars,
                    'designProjects'    => $designProjects
                ));*/

                $rows = $carTable->fetchAll(
                    $carTable->select(true)
                        ->join('brands_cars', 'cars.id=brands_cars.car_id', null)
                        ->where('brands_cars.brand_id = ?', $brand->id)
                        ->where('NOT cars.is_concept')
                        ->order(array('cars.caption', 'cars.begin_year', 'cars.end_year', 'cars.begin_model_year', 'cars.end_model_year'))
                );
                $cars = $this->prepareCars($rows);

                $haveConcepts = (bool)$carTable->fetchRow(
                    $carTable->select(true)
                        ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                        ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                        ->where('brands_cars.brand_id = ?', $brand->id)
                        ->where('cars.is_concept')
                );

                $this->view->assign(array(
                    'cars'         => $cars,
                    'haveConcepts' => $haveConcepts,
                    'conceptsUrl'  => $this->_helper->url->url(array(
                        'action' => 'concepts',
                    )),
                ));

            } else {
                $this->view->brands = $brands->fetchAll(null, array('position', 'caption'));
            }
        }

        $this->view->brand = $brand;
    }

    private function loadCarSelect($parentId)
    {
        $carTable = $this->_helper->catalogue()->getCarTable();

        $carRows = $carTable->fetchAll(
            $carTable->select(true)
                ->join('car_parent', 'cars.id = car_parent.car_id', null)
                ->where('car_parent.parent_id = ?', $parentId)
                ->order(array('cars.caption', 'cars.body', 'cars.begin_year', 'cars.end_year'))
        );

        $cars = array();
        foreach ($carRows as $carRow) {
            $cars[] = array(
                'url'    => $this->_helper->url->url(array(
                    'car_id' => $carRow->id
                )),
                'name'   => $carRow->getFullName(),
                'childs' => $this->loadCarSelect($carRow->id)
            );
        }

        return $cars;
    }

    public function pictureSelectBrandAction()
    {
        $picture = $this->table->find($this->_getParam('picture_id'))->current();
        if (!$picture) {
            return $this->forward('notfound', 'error', 'default');
        }

        if (!in_array($picture->type, array(Picture::UNSORTED_TYPE_ID, Picture::LOGO_TYPE_ID, Picture::MIXED_TYPE_ID)))
            throw new Exception('Картинка несовместимого типа');

        $canMove = $this->_helper->user()->isAllowed('picture', 'move');
        if (!$canMove) {
            return $this->forward('forbidden', 'error', 'default');
        }

        $this->view->picture = $picture;

        $brands = new Brands();
        $brand = $brands->find($this->_getParam('brand_id'))->current();

        if ($brand) {
            $oldBrand = $picture->findParentBrands();

            $picture->brand_id = $brand->id;
            $picture->save();

            if ($picture->image_id) {
                $imageStorage = $this->getInvokeArg('bootstrap')
                    ->getResource('imagestorage');
                $imageStorage->changeImageName($picture->image_id, array(
                    'pattern' => $picture->getFileNamePattern(),
                ));
            } else {
                $picture->correctFileName();
            }

            if ($oldBrand) {
                $oldBrand->updatePicturesCache();
            }
            $brand->updatePicturesCache();

            $this->_helper->log(sprintf(
                'Назначение бренда %s картинке %s',
                $this->view->escape($brand->caption), $this->view->htmlA($this->pictureUrl($picture), $picture->getCaption())
            ), array($picture, $brand));

            return $this->_redirect($this->pictureUrl($picture));
        } else {
            $this->view->brands = $brands->fetchAll(null, array('position', 'caption'));
        }
    }


    private function pictureCanDelete($picture)
    {
        $user = $this->_helper->user()->get();
        $canDelete = false;
        if (in_array($picture->status, array(Picture::STATUS_INBOX, Picture::STATUS_NEW))) {
            if ($this->_helper->user()->isAllowed('picture', 'remove')) {
                if ($this->pictureVoteExists($picture, $user)) {
                    $canDelete = true;
                }
            } elseif ($this->_helper->user()->isAllowed('picture', 'remove_by_vote')) {
                if ($this->pictureVoteExists($picture, $user)) {
                    $db = $this->table->getAdapter();
                    $acceptVotes = (int)$db->fetchOne(
                        $db->select()
                            ->from('pictures_moder_votes', array(new Zend_Db_Expr('COUNT(1)')))
                            ->where('picture_id = ?', $picture->id)
                            ->where('vote > 0')
                    );
                    $deleteVotes = (int)$db->fetchOne(
                        $db->select()
                            ->from('pictures_moder_votes', array(new Zend_Db_Expr('COUNT(1)')))
                            ->where('picture_id = ?', $picture->id)
                            ->where('vote = 0')
                    );

                    $canDelete = ($deleteVotes > $acceptVotes);
                }
            }
        }

        return $canDelete;
    }

    private function pictureVoteExists($picture, $user)
    {
        $db = $this->table->getAdapter();
        return $db->fetchOne(
            $db->select()
                ->from('pictures_moder_votes', new Zend_Db_Expr('COUNT(1)'))
                ->where('picture_id = ?', $picture->id)
                ->where('user_id = ?', $user->id)
        );
    }

    public function deletePictureAction()
    {
        $picture = $this->table->find($this->_getParam('picture_id'))->current();
        if (!$picture) {
            return $this->forward('notfound', 'error', 'default');
        }

        $canDelete = $this->pictureCanDelete($picture);
        if (!$canDelete)
            throw new Exception('Forbidden');

        $user = $this->_helper->user()->get();
        $picture->setFromArray(array(
            'status'                => Picture::STATUS_REMOVING,
            'removing_date'         => new Zend_Db_Expr('CURDATE()'),
            'change_status_user_id' => $user->id
        ));
        $picture->save();

        if ($owner = $picture->findParentUsersByOwner()) {
            $message = sprintf(
                "Добавленная вами картинка %s поставлена в очередь на удаление" . PHP_EOL,
                $this->view->serverUrl($this->view->pic($picture)->url())
            );

            $requests = new Pictures_Moder_Votes();
            $deleteRequests = $requests->fetchAll(
                $requests->select()
                         ->where('picture_id = ?', $picture->id)
                         ->where('vote = 0')
            );
            if (count($deleteRequests)) {
                $message .= "Причины:" . PHP_EOL;
                foreach ($deleteRequests as $request) {
                    if ($user = $request->findParentUsers()) {
                        $message .= 'http://www.autowp.ru' . $user->getAboutUrl() . ' : ';
                    }
                    $message .= $request->reason . PHP_EOL;
                }
            }
            $mModel = new Message();
            $mModel->send(null, $owner->id, $message);
        }

        $this->_helper->log(sprintf(
            'Картинка %s поставлена в очередь на удаление',
            $this->view->htmlA($this->pictureUrl($picture), $picture->getCaption())
        ), $picture);

        return $this->_redirect($this->pictureUrl($picture));
    }

    public function pictureVoteAction()
    {
        $picture = $this->table->find($this->_getParam('picture_id'))->current();
        if (!$picture) {
            return $this->forward('notfound', 'error', 'default');
        }

        $hideVote = (bool)$this->_getParam('hide-vote');

        $canDelete = $this->pictureCanDelete($picture);

        $isLastPicture = null;
        if ($picture->type == Picture::CAR_TYPE_ID && $picture->status == Picture::STATUS_ACCEPTED) {
            $car = $picture->findParentCars();
            if ($car) {
                $db = $this->table->getAdapter();
                $isLastPicture = !$db->fetchOne(
                    $db->select()
                        ->from('pictures', array(new Zend_Db_Expr('COUNT(1)')))
                        ->where('car_id = ?', $car->id)
                        ->where('status = ?', Picture::STATUS_ACCEPTED)
                        ->where('id <> ?', $picture->id)
                );
            }
        }

        $acceptedCount = null;
        if ($picture->type == Picture::CAR_TYPE_ID) {
            $car = $picture->findParentCars();
            if ($car) {
                $db = $this->table->getAdapter();
                $acceptedCount = $db->fetchOne(
                    $db->select()
                        ->from('pictures', array(new Zend_Db_Expr('COUNT(1)')))
                        ->where('car_id = ?', $car->id)
                        ->where('status = ?', Picture::STATUS_ACCEPTED)
                );
            }
        }

        $user = $this->_helper->user()->get();
        $voteExists = $this->pictureVoteExists($picture, $user);

        $request = $this->getRequest();

        $formPictureVote = null;
        if (!$voteExists && $this->_helper->user()->isAllowed('picture', 'moder_vote'))
        {
            $form = new Application_Form_Moder_Picture_Vote(array(
                'action' => $this->_helper->url->url(array(
                    'module'     => 'moder',
                    'controller' => 'pictures',
                    'action'     => 'picture-vote',
                    'form'       => 'picture-vote',
                    'picture_id' => $picture->id
                ), 'default'),
            ));

            if ($request->isPost() && $this->_getParam('form') == 'picture-vote' && $form->isValid($request->getPost()))
            {
                $values = $form->getValues();

                if ($customReason = $request->getCookie('customReason')) {
                    $customReason = (array)unserialize($customReason);
                } else {
                    $customReason = array();
                }

                $customReason[] = $values['reason'];
                $customReason = array_unique($customReason);

                setcookie('customReason', serialize($customReason), time()+60*60*24*30, '/');

                $vote = (bool)($values['vote'] == 'Хочу принять');

                $user = $this->_helper->user()->get();
                $moderVotes = new Pictures_Moder_Votes();
                $moderVotes->insert(array(
                    'user_id'    => $user->id,
                    'picture_id' => $picture->id,
                    'day_date'   => new Zend_Db_Expr('NOW()'),
                    'reason'     => $values['reason'],
                    'vote'       => $vote ? 1 : 0
                ));

                if ($vote && $picture->status == Picture::STATUS_REMOVING) {
                    $picture->status = Picture::STATUS_INBOX;
                    $picture->save();
                }

                $message = sprintf(
                    $vote
                        ? 'Подана заявка на принятие картинки %s'
                        : 'Подана заявка на удаление картинки %s',
                    $this->view->htmlA($this->pictureUrl($picture), $picture->getCaption())
                );
                $this->_helper->log($message, $picture);

                $owner = $picture->findParentUsersByOwner();
                $ownerIsModer = $owner && $this->_helper->user($owner)->inheritsRole('moder');
                if ($ownerIsModer) {
                    if ($owner->id != $this->_helper->user()->get()->id) {
                        $message = sprintf(
                            'Подана заявка на %s добавленной вами картинки %s'.PHP_EOL.' Причина: %s',
                            $vote ? 'удаление' : 'принятие',
                            $this->view->serverUrl($this->pictureUrl($picture)),
                            $values['reason']
                        );

                        $mModel = new Message();
                        $mModel->send(null, $owner->id, $message);
                    }
                }

                $referer = $request->getServer('HTTP_REFERER');
                if ($referer) {
                    return $this->_redirect($this->pictureUrl($picture));
                }

                return $this->_redirect($this->_helper->url->url());
            }

            $formPictureVote = $form;
        }

        $formPictureUnvote = null;
        if ($voteExists) {
            $form = new Application_Form_Moder_Picture_Unvote(array(
                'action' => $this->view->url(array(
                    'module'     => 'moder',
                    'controller' => 'pictures',
                    'action'     => 'picture-vote',
                    'form'       => 'picture-unvote',
                    'picture_id' => $picture->id
                ), 'default', true)
            ));

            if ($request->isPost() && $this->_getParam('form') == 'picture-unvote' && $form->isValid($request->getPost())) {
                $values = $form->getValues();

                $moderVotes = new Pictures_Moder_Votes();

                $user = $this->_helper->user()->get();
                $moderVotes->delete(array(
                    'user_id = ?'    => $user->id,
                    'picture_id = ?' => $picture->id
                ));

                $referer = $request->getServer('HTTP_REFERER');
                if ($referer) {
                    return $this->_redirect($referer);
                }

                return $this->_redirect($this->pictureUrl($picture));
            }

            $formPictureUnvote = $form;
        }

        $deletePictureForm = null;
        if ($canDelete) {
            $form = new Application_Form_Moder_Picture_Delete(array(
                'action' => $this->_helper->url->url(array(
                    'module'     => 'moder',
                    'controller' => 'pictures',
                    'action'     => 'delete-picture',
                    'picture_id' => $picture->id,
                    'form'       => 'picture-delete'
                ), 'default', true)
            ));
            $deletePictureForm = $form;
        }

        $this->view->assign(array(
            'isLastPicture'     => $isLastPicture,
            'acceptedCount'     => $acceptedCount,
            'canDelete'         => $canDelete,
            'deletePictureForm' => $deletePictureForm,
            'formPictureVote'   => $formPictureVote,
            'formPictureUnvote' => $formPictureUnvote,
            'moderVotes'        => null
        ));

        if (!$hideVote) {
            $this->view->assign(array(
                'moderVotes' => $picture->findPictures_Moder_Votes(),
            ));
        }
    }

    public function pictureAction()
    {
        $picture = $this->table->find($this->_getParam('picture_id'))->current();
        if (!$picture) {
            return $this->forward('notfound', 'error', 'default');
        }

        $prevPicture = $this->table->fetchRow(
            $this->table->select(true)
                 ->where('id < ?', $picture->id)
                 ->order('id DESC')
                 ->limit(1)
        );

        $nextPicture = $this->table->fetchRow(
            $this->table->select(true)
                 ->where('id > ?', $picture->id)
                 ->order('id')
                 ->limit(1)
        );

        $prevNewPicture = $this->table->fetchRow(
            $this->table->select(true)
                 ->where('id < ?', $picture->id)
                 ->where('status IN (?)', array(Picture::STATUS_NEW, Picture::STATUS_INBOX))
                 ->order('id DESC')
                 ->limit(1)
        );

        $nextNewPicture = $this->table->fetchRow(
            $this->table->select(true)
                 ->where('id > ?', $picture->id)
                 ->where('status IN (?)', array(Picture::STATUS_NEW, Picture::STATUS_INBOX))
                 ->order('id')
                 ->limit(1)
        );


        $ban = false;
        $canBan = $this->_helper->user()->isAllowed('user', 'ban');
        $canViewIp = $this->_helper->user()->isAllowed('user', 'ip');

        if ($canBan && $picture->ip !== null && $picture->ip !== '') {

            $service = new TrafficControl();
            $ban = $service->getBanInfo(inet_ntop($picture->ip));
            if ($ban) {
                $userTable = new Users();
                $ban['user'] = $userTable->find($ban['user_id'])->current();
            }
        }

        $editPictureForm = new Application_Form_Moder_Picture_Edit(array(
            'action' => $this->_helper->url->url(array(
                'form' => 'picture-edit'
            ))
        ));

        $editPictureForm->populate($picture->toArray());
        $request = $this->getRequest();
        if ($request->isPost() && ($this->getParam('form') == 'picture-edit') && $editPictureForm->isValid($request->getPost())) {
            $picture->setFromArray($editPictureForm->getValues());
            $picture->save();

            return $this->_redirect($this->pictureUrl($picture));
        }

        $copyrightsForm = $this->getCopyrightsForm();
        if ($picture->copyrights_text_id) {
            $textStorage = $this->_helper->textStorage();
            $text = $textStorage->getText($picture->copyrights_text_id);
            $copyrightsForm->populate(array(
                'text' => $text
            ));
        }
        if ($request->isPost() && ($this->getParam('form') == 'copyrights-edit') && $copyrightsForm->isValid($request->getPost())) {
            $values = $copyrightsForm->getValues();

            $text = $values['text'];

            $textStorage = $this->_helper->textStorage();

            $user = $this->_helper->user()->get();

            if ($picture->copyrights_text_id) {
                $textStorage->setText($picture->copyrights_text_id, $text, $user->id);
            } elseif ($text) {
                $textId = $textStorage->createText($text, $user->id);
                $picture->copyrights_text_id = $textId;
                $picture->save();
            }

            $this->_helper->log(sprintf(
                'Редактирование текста копирайтов изображения %s',
                $this->view->htmlA($this->pictureUrl($picture), $picture->getCaption())
            ), $picture);

            if ($picture->copyrights_text_id) {
                $userIds = $textStorage->getTextUserIds($picture->copyrights_text_id);
                $message = sprintf(
                    'Пользователь %s редактировал текст копирайтов изображения %s ( %s )',
                    $this->view->serverUrl($user->getAboutUrl()),
                    $picture->getCaption(),
                    $this->view->serverUrl($this->pictureUrl($picture))
                );

                $mModel = new Message();
                $userTable = new Users();
                foreach ($userIds as $userId) {
                    if ($userId != $user->id) {
                        foreach ($userTable->find($userId) as $userRow) {
                            $mModel->send(null, $userRow->id, $message);
                        }
                    }
                }
            }

            return $this->_redirect($this->pictureUrl($picture));
        }

        $this->view->assign(array(
            'ban'             => $ban,
            'canBan'          => $canBan,
            'canViewIp'       => $canViewIp,
            'prevPicture'     => $prevPicture,
            'nextPicture'     => $nextPicture,
            'prevNewPicture'  => $prevNewPicture,
            'nextNewPicture'  => $nextNewPicture,
            'editPictureForm' => $editPictureForm,
            'copyrightsForm'  => $copyrightsForm
        ));

        if ($picture->image_id) {
            $imageStorage = $this->getInvokeArg('bootstrap')
                ->getResource('imagestorage');
            $iptcStr = $imageStorage->getImageIPTC($picture->image_id);

            $exifStr = $imageStorage->getImageEXIF($picture->image_id);
        } else {

            $iptcStr = '';
            try {
                getimagesize($picture->getSourceFilePath(), $info);
                if (is_array($info) && array_key_exists('APP13', $info)) {
                    $IPTC = iptcparse($info['APP13']);
                    if (is_array($IPTC)) {
                        foreach ($IPTC as $key => $value) {
                            $iptcStr .= "<b>IPTC Key:</b> ".htmlspecialchars($key)." <b>Contents:</b> ";
                            foreach ($value as $innerkey => $innervalue) {
                                if ( ($innerkey+1) != count($value) )
                                    $iptcStr .= htmlspecialchars($innervalue) . ", ";
                                else
                                    $iptcStr .= htmlspecialchars($innervalue);
                            }
                            $iptcStr .= '<br />';
                        }
                    } else {
                        $iptcStr .= $IPTC;
                    }
                }
            } catch (Exception $e) {
                $iptcStr = 'Ошибка при чтении IPTC: '.$e->getMessage();
            }

            $exifStr = '';
            try {
                $NotSections = array('FILE', 'COMPUTED');
                $exif = @exif_read_data($picture->getSourceFilePath(), 0, True);
                if ($exif !== false) {
                    foreach ($exif as $key => $section) {
                        if (array_search($key, $NotSections) !== false)
                            continue;

                        $exifStr .= '<p>['.$this->view->escape($key).']';
                        foreach ($section as $name => $val) {
                            $exifStr .= "<br />".$this->view->escape($name).": ";
                            if (is_array($val))
                                $exifStr .= $this->view->escape(implode(', ', $val));
                            else
                                $exifStr .= $this->view->escape($val);
                        }

                        $exifStr .= '</p>';
                    }
                }
            } catch (Exception $e) {
                $exifStr .= 'Ошибка при чтении EXIF: '.$e->getMessage();
            }
        }



        $canMove =  $this->_helper->user()->isAllowed('picture', 'move');

        $this->view->formModerPictureType = false;
        if ($canMove) {
            $form = new Application_Form_Moder_Picture_Type(array(
                'action' => $this->_helper->url->url(array('form' => 'picture-type'))
            ));
            $form->populate($picture->toArray());

            $this->view->formModerPictureType = $form;
            if ($request->isPost() && $this->_getParam('form') == 'picture-type' && $form->isValid($request->getPost())) {
                $values = $form->getValues();

                $oldType = $picture->type;
                $oldEngineId = $picture->engine_id;

                $picture->type = $values['type'];
                $picture->save();

                if ($oldType == Picture::ENGINE_TYPE_ID) {
                    $engineTable = new Engines();
                    $oldEngine = $engineTable->find($oldEngineId)->current();
                    if ($oldEngine) {
                        $oldEngineBrand = $oldEngine->findParentBrands();
                        if ($oldEngineBrand) {
                            $oldEngineBrand->refreshEnginePicturesCount();
                        }
                    }
                }

                if ($picture->type == Picture::ENGINE_TYPE_ID) {
                    $engineTable = new Engines();
                    $engine = $engineTable->find($picture->engine_id)->current();
                    if ($engine) {
                        $engineBrand = $engine->findParentBrands();
                        if ($engineBrand) {
                            $engineBrand->refreshEnginePicturesCount();
                        }
                    }
                }

                $this->_helper->log(sprintf(
                    'Изменение типа картинки %s',
                    $this->view->htmlA($this->view->url(array(
                        'module'     => 'moder',
                        'controller' => 'pictures',
                        'action'     => 'picture',
                        'picture_id' => $picture->id
                    )), $picture->getCaption())
                ), $picture);

                return $this->_redirect($this->view->url(array(
                    'module'     => 'moder',
                    'controller' => 'pictures',
                    'action'     => 'picture',
                    'picture_id' => $picture->id
                ), null, true));
            }
        }

        $lastCar = null;
        $namespace = new Zend_Session_Namespace('Moder_Car');
        if (isset($namespace->lastCarId)) {
            $cars = new Cars();
            $car = $cars->find($namespace->lastCarId)->current();
            if ($car->id != $picture->car_id) {
                $lastCar = $car;
            }
        }


        $canUnaccept = ($picture->status == Picture::STATUS_ACCEPTED)
                    && $this->_helper->user()->isAllowed('picture', 'unaccept');

        if ($canUnaccept) {

            $form = new Zend_Form(array(
                'action'     => $this->_helper->url->url(array('form' => 'picture-unaccept')),
                'decorators' => array(
                    'FormElements',
                    'PrepareElements',
                    'Form',
                ),
                'elements'   => array(
                    array('submit', 'send', array(
                        'required'   => false,
                        'ignore'     => true,
                        'label'      => 'Сделать не принятой',
                        'class'      => 'btn btn-warning',
                        'decorators' => array(
                            'ViewHelper'
                        )
                    ))
                )
            ));

            $this->view->unacceptPictureForm = $form;

            if ($request->isPost() && $this->_getParam('form') == 'picture-unaccept' && $form->isValid($request->getPost())) {
                $values = $form->getValues();

                $previousStatusUserId = $picture->change_status_user_id;

                $user = $this->_helper->user()->get();
                $picture->status = Picture::STATUS_INBOX;
                $picture->change_status_user_id = $user->id;
                $picture->save();



                $this->_helper->log(sprintf(
                    'С картинки %s снят статус "принято"',
                    $this->view->htmlA($this->pictureUrl($picture), $picture->getCaption())
                ), $picture);


                $mModel = new Message();

                $pictureUrl = $this->view->serverUrl($this->view->pic($picture)->url());
                if ($previousStatusUserId != $user->id) {
                    $userTable = new Users();
                    foreach ($userTable->find($previousStatusUserId) as $prevUser) {
                        $message = sprintf(
                            'С картинки %s снят статус "принято"',
                            $pictureUrl
                        );
                        $mModel->send(null, $prevUser->id, $message);
                    }
                }

                $referer = $this->getRequest()->getServer('HTTP_REFERER');
                return $this->_redirect($referer ? $referer : $this->view->url());
            }
        }

        $canAccept = in_array($picture->status, array(Picture::STATUS_NEW, Picture::STATUS_INBOX))
                  && $this->_helper->user()->isAllowed('picture', 'accept');

        $acceptPictureForm = null;
        if ($canAccept) {

            $acceptPictureForm = new Application_Form_Moder_Picture_Accept(array(
                'action'  => $this->_helper->url->url(array(
                    'form' => 'picture-accept'
                ))
            ));

            if ($request->isPost() && $this->_getParam('form') == 'picture-accept' && $acceptPictureForm->isValid($request->getPost())) {

                $this->accept($picture);

                $url = $request->getServer('HTTP_REFERER');
                if (!$url) {
                    $url = $this->_helper->url->url();
                }

                return $this->_redirect($url);
            }
        }

        $canRestore = $this->canRestore($picture);
        $restorePictureForm = null;
        if ($canRestore) {
            $restorePictureForm = new Application_Form_Moder_Picture_Restore(array(
                'action' => $this->_helper->url->url(array(
                    'action' => 'restore'
                ))
            ));
        }

        $replacePicture = null;
        if ($picture->replace_picture_id) {
            $row = $this->table->find($picture->replace_picture_id)->current();
            if ($row) {

                $canAcceptReplace = $this->canReplace($picture, $row);

                $replacePicture = array(
                    'url' => $this->_helper->url->url(array(
                        'module'     => 'moder',
                        'controller' => 'pictures',
                        'action'     => 'picture',
                        'picture_id' => $row->id
                    )),
                    'canAccept' => $canAcceptReplace,
                    'acceptUrl' => $this->_helper->url->url(array(
                        'module'     => 'moder',
                        'controller' => 'pictures',
                        'action'     => 'accept-replace',
                        'picture_id' => $picture->id
                    )),
                    'cancelUrl' => $this->_helper->url->url(array(
                        'module'     => 'moder',
                        'controller' => 'pictures',
                        'action'     => 'cancel-replace',
                        'picture_id' => $picture->id
                    )),
                );
            }
        }

        $imageStorage = $this->getInvokeArg('bootstrap')
            ->getResource('imagestorage');

        $image = $imageStorage->getImage($picture->image_id);

        if (!$image) {
            return $this->forward('notfound', 'error', 'default');
        }

        $sourceUrl = $image->getSrc();

        $image = $imageStorage->getFormatedImage($picture->getFormatRequest(), 'picture-gallery-full');
        $galleryFullUrl = null;
        if ($image) {
            $galleryFullUrl = $image->getSrc();
        }




        $canCrop = $this->canCrop();
        $crop = false;

        if ($canCrop) {
            if ($picture->cropParametersExists()) {
                $crop = array(
                    (int)$picture->crop_left,  (int)$picture->crop_top,
                    (int)$picture->crop_width, (int)$picture->crop_height,
                );
            } else {
                $crop = array(
                    0, 0,
                    (int)$picture->width, (int)$picture->height,
                );
            }
        }


        $this->view->assign(array(
            'picture'                       => $picture,
            'canMove'                       => $canMove,
            'canNormalize'                  => $this->canNormalize($picture),
            'canCrop'                       => $this->canCrop(),
            'canFlop'                       => $this->canFlop($picture),
            'canRestore'                    => $canRestore,
            'canAccept'                     => $canAccept,
            'canUnaccept'                   => $canUnaccept,
            'acceptPictureForm'             => $acceptPictureForm,
            'restorePictureForm'            => $restorePictureForm,
            'iptc'                          => $iptcStr,
            'exif'                          => $exifStr,
            'lastCar'                       => $lastCar,
            'pictureTypes'                  => array(
                Picture::UNSORTED_TYPE_ID  => 'Несортировано',
                Picture::CAR_TYPE_ID       => 'Автомобиль',
                Picture::LOGO_TYPE_ID      => 'Логотип',
                Picture::MIXED_TYPE_ID     => 'Разное',
                Picture::ENGINE_TYPE_ID    => 'Двигатель',
            ),
            'galleryFullUrl'                => $galleryFullUrl,
            'sourceUrl'                     => $sourceUrl,
            'replacePicture'                => $replacePicture,
            'crop'                          => $crop
        ));
    }

    private function canCrop()
    {
        return $this->_helper->user()->isAllowed('picture', 'crop');
    }

    private function canNormalize(Picture_Row $picture)
    {
        return in_array($picture->status, array(Picture::STATUS_NEW, Picture::STATUS_INBOX))
            && $this->_helper->user()->isAllowed('picture', 'normalize');
    }

    private function canFlop(Picture_Row $picture)
    {
        return in_array($picture->status, array(Picture::STATUS_NEW, Picture::STATUS_INBOX, Picture::STATUS_REMOVING))
            && $this->_helper->user()->isAllowed('picture', 'flop');
    }

    private function canRestore(Picture_Row $picture)
    {
        return $picture->status == Picture::STATUS_REMOVING
            && $this->_helper->user()->isAllowed('picture', 'restore');
    }

    public function restoreAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forward('forbidden', 'error', 'default');
        }

        $picture = $this->table->find($this->_getParam('picture_id'))->current();

        if (!$picture) {
            return $this->forward('notfound', 'error', 'default');
        }

        if (!$this->canRestore($picture)) {
            return $this->forward('forbidden', 'error', 'default');
        }

        $user = $this->_helper->user()->get();
        $picture->setFromArray(array(
            'status'                => Picture::STATUS_INBOX,
            'change_status_user_id' => $user->id
        ));
        $picture->save();

        $this->_helper->log(sprintf(
            'Картинки %s восстановлена из очереди удаления',
            $this->view->htmlA($this->_helper->url->url(array(
                'controller' => 'picture',
                'action'     => 'index',
                'picture_id' => $picture->id
            )), $picture->getCaption())
        ), $picture);

        $referer = $this->getRequest()->getServer('HTTP_REFERER');
        return $this->_redirect($referer ? $referer : $this->view->url());
    }

    public function flopAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forward('forbidden', 'error', 'default');
        }

        $picture = $this->table->find($this->_getParam('picture_id'))->current();
        if (!$picture) {
            return $this->forward('notfound', 'error', 'default');
        }

        if (!$this->canFlop($picture)) {
            return $this->forward('forbidden', 'error', 'default');
        }

        if ($picture->image_id) {
            $imageStorage = $this->getInvokeArg('bootstrap')
                ->getResource('imagestorage');

            $imageStorage->flop($picture->image_id);
        }

        $this->_helper->log(sprintf(
            'К картинке %s применён flop',
            $this->view->htmlA($this->view->pic($picture)->url(), $picture->getCaption())
        ), $picture);

        return $this->_helper->json(array(
            'ok' => true
        ));
    }

    public function normalizeAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forward('notfound', 'error', 'default');
        }

        $picture = $this->table->find($this->_getParam('picture_id'))->current();
        if (!$picture) {
            return $this->forward('notfound', 'error', 'default');
        }

        if (!$this->canNormalize($picture)) {
            return $this->forward('notfound', 'error', 'default');
        }

        if ($picture->image_id) {
            $imageStorage = $this->getInvokeArg('bootstrap')
                ->getResource('imagestorage');
        
            $imageStorage->normalize($picture->image_id);
        }

        $this->_helper->log(sprintf(
            'К картинке %s применён normalize',
            $this->view->htmlA($this->view->pic($picture)->url(), $picture->getCaption())
        ), $picture);

        return $this->_helper->json(array(
            'ok' => true
        ));
    }

    public function filesRepairAction()
    {
        /*if (!$this->getRequest()->isPost()) {
            return $this->forward('forbidden', 'error', 'default');
        }*/

        $picture = $this->table->find($this->_getParam('picture_id'))->current();
        if (!$picture) {
            return $this->forward('notfound', 'error', 'default');
        }

        if ($picture->image_id) {
            $imageStorage = $this->getInvokeArg('bootstrap')
                ->getResource('imagestorage');

            $imageStorage->flush(array(
                'image' => $picture->image_id
            ));
        }

        return $this->_helper->json(array(
            'ok' => true
        ));
    }

    public function filesCorrectNamesAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forward('notfound', 'error', 'default');
        }

        $picture = $this->table->find($this->_getParam('picture_id'))->current();
        if (!$picture) {
            return $this->forward('notfound', 'error', 'default');
        }

        $imageStorage = $this->getInvokeArg('bootstrap')
            ->getResource('imagestorage');

        $imageStorage->changeImageName($picture->image_id, array(
            'pattern' => $picture->getFileNamePattern(),
        ));

        return $this->_helper->json(array(
            'ok' => true
        ));
    }

    public function cropperSaveAction()
    {
        $picture = $this->table->find($this->_getParam('picture_id'))->current();
        if (!$picture || !$this->canCrop()) {
            return $this->forward('notfound', 'error', 'default');
        }

        $left = round($this->_getParam('x'));
        $top = round($this->_getParam('y'));
        $width = round($this->_getParam('w'));
        $height = round($this->_getParam('h'));

        $left = max(0, $left);
        $left = min($picture->width, $left);
        $width = max(1, $width);
        $width = min($picture->width, $width);

        $top = max(0, $top);
        $top = min($picture->height, $top);
        $height = max(1, $height);
        $height = min($picture->height, $height);

        if ($left > 0 || $top > 0 || $width < $picture->width || $height < $picture->height) {
            $picture->setFromArray(array(
                'crop_left'   => $left,
                'crop_top'    => $top,
                'crop_width'  => $width,
                'crop_height' => $height
            ));
        } else {
            $picture->setFromArray(array(
                'crop_left'   => null,
                'crop_top'    => null,
                'crop_width'  => null,
                'crop_height' => null
            ));
        }
        $picture->save();

        $imageStorage = $this->getInvokeArg('bootstrap')
            ->getResource('imagestorage');

        $imageStorage->flush(array(
            'image' => $picture->image_id
        ));

        $this->_helper->log(sprintf(
            'Выделение области на картинке %s',
            $this->view->escape($picture->getCaption())
        ), array($picture));

        $this->_helper->json(array(
            'ok' => true
        ));
    }

    private function prepareCars(Cars_Rowset $rows)
    {
        $carParentTable = $this->getCarParentTable();
        $carParentAdapter = $carParentTable->getAdapter();

        $cars = array();
        foreach ($rows as $row) {
            $haveChilds = (bool)$carParentAdapter->fetchOne(
                $carParentAdapter->select()
                    ->from($carParentTable->info('name'), new Zend_Db_Expr('1'))
                    ->where('parent_id = ?', $row->id)
            );
            $cars[] = array(
                'name' => $row->getFullName(),
                'url'  => $this->_helper->url->url(array(
                    'action' => 'picture-select-car',
                    'car_id' => $row['id']
                )),
                'haveChilds' => $haveChilds,
                'isGroup'    => $row->is_group,
                'type'       => null,
                'loadUrl'    => $this->_helper->url->url(array(
                    'action' => 'car-childs',
                    'car_id' => $row['id']
                )),
            );
        }

        return $cars;
    }

    private function prepareCarParentRows($rows)
    {
        $carParentTable = $this->getCarParentTable();
        $carParentAdapter = $carParentTable->getAdapter();
        $carTable = new Cars();

        $items = array();
        foreach ($rows as $carParentRow) {
            $car = $carTable->find($carParentRow->car_id)->current();
            if ($car) {
                $haveChilds = (bool)$carParentAdapter->fetchOne(
                    $carParentAdapter->select()
                        ->from($carParentTable->info('name'), new Zend_Db_Expr('1'))
                        ->where('parent_id = ?', $car->id)
                );
                $items[] = array(
                    'name' => $car->getFullName(),
                    'url'  => $this->_helper->url->url(array(
                        'action' => 'picture-select-car',
                        'car_id' => $car['id']
                    )),
                    'haveChilds' => $haveChilds,
                    'isGroup'    => $car['is_group'],
                    'type'       => $carParentRow->type,
                    'loadUrl'    => $this->_helper->url->url(array(
                        'action' => 'car-childs',
                        'car_id' => $car['id']
                    )),
                );
            }
        }

        return $items;
    }

    public function carChildsAction()
    {
        $user = $this->_helper->user()->get();
        if (!$user) {
            return $this->forward('only-registered');
        }

        $carTable = new Cars();
        $carParentTable = $this->getCarParentTable();

        $car = $carTable->find($this->_getParam('car_id'))->current();
        if (!$car) {
            return $this->forward('notfound', 'error', 'default');
        }

        $rows = $carParentTable->fetchAll(
            $carParentTable->select(true)
                ->join('cars', 'cars.id = car_parent.car_id', null)
                ->where('car_parent.parent_id = ?', $car->id)
                ->order(array('car_parent.type', 'cars.caption', 'cars.begin_year', 'cars.end_year'))
        );

        $this->view->assign(array(
            'cars' => $this->prepareCarParentRows($rows)
        ));
    }

    public function conceptsAction()
    {

        $brandTable = new Brands();
        $brand = $brandTable->find($this->_getParam('brand_id'))->current();
        if (!$brand) {
            return $this->forward('notfound', 'error', 'default');
        }

        $carTable = new Cars();

        $rows = $carTable->fetchAll(
            $carTable->select(true)
                ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand->id)
                ->where('cars.is_concept')
                ->order(array('cars.caption', 'cars.begin_year', 'cars.end_year'))
                ->group('cars.id')
        );
        $concepts = $this->prepareCars($rows);

        $this->view->assign(array(
            'concepts' => $concepts,
        ));
    }

    private function canReplace($picture, $replacedPicture)
    {
        $can1 = false;
        switch ($picture->status) {
            case Picture::STATUS_ACCEPTED:
                $can1 = true;
                break;

            case Picture::STATUS_INBOX:
            case Picture::STATUS_NEW:
                $can1 = $this->_helper->user()->isAllowed('picture', 'accept');
                break;
        }

        $can2 = false;
        switch ($replacedPicture->status) {
            case Picture::STATUS_ACCEPTED:
                $can2 = $this->_helper->user()->isAllowed('picture', 'unaccept')
                     && $this->_helper->user()->isAllowed('picture', 'remove_by_vote');
                break;

            case Picture::STATUS_INBOX:
            case Picture::STATUS_NEW:
                $can2 = $this->_helper->user()->isAllowed('picture', 'remove_by_vote');
                break;

            case Picture::STATUS_REMOVING:
            case Picture::STATUS_REMOVED:
                $can2 = true;
                break;
        }

        return $can1 && $can2 && $this->_helper->user()->isAllowed('picture', 'move');
    }

    public function cancelReplaceAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forward('forbidden', 'error', 'default');
        }

        $picture = $this->table->find($this->_getParam('picture_id'))->current();
        if (!$picture) {
            return $this->forward('notfound', 'error', 'default');
        }

        if (!$picture->replace_picture_id) {
            return $this->forward('notfound', 'error', 'default');
        }

        $replacePicture = $this->table->find($picture->replace_picture_id)->current();
        if (!$replacePicture) {
            return $this->forward('notfound', 'error', 'default');
        }

        if (!$this->_helper->user()->isAllowed('picture', 'move')) {
            return $this->forward('forbidden', 'error', 'default');
        }

        $picture->replace_picture_id = null;
        $picture->save();

        // log
        $this->_helper->log(sprintf(
            'Замена %s на %s отклонена',
            $this->view->htmlA($this->pictureUrl($replacePicture), $replacePicture->getCaption()),
            $this->view->htmlA($this->pictureUrl($picture), $picture->getCaption())
        ), array($picture, $replacePicture));

        return $this->_redirect($this->_helper->url->url(array(
            'action' => 'picture'
        )));
    }

    public function acceptReplaceAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forward('forbidden', 'error', 'default');
        }

        $picture = $this->table->find($this->_getParam('picture_id'))->current();
        if (!$picture) {
            return $this->forward('notfound', 'error', 'default');
        }

        if (!$picture->replace_picture_id) {
            return $this->forward('notfound', 'error', 'default');
        }

        $replacePicture = $this->table->find($picture->replace_picture_id)->current();
        if (!$replacePicture) {
            return $this->forward('notfound', 'error', 'default');
        }

        if (!$this->canReplace($picture, $replacePicture)) {
            return $this->forward('forbidden', 'error', 'default');
        }

        $user = $this->_helper->user()->get();

        // statuses
        if ($picture->status != Picture::STATUS_ACCEPTED) {
            $picture->setFromArray(array(
                'status'                => Picture::STATUS_ACCEPTED,
                'change_status_user_id' => $user->id
            ));
            if (!$picture->accept_datetime) {
                $picture->accept_datetime = new Zend_Db_Expr('NOW()');
            }
            $picture->save();
        }

        if (!in_array($replacePicture->status, array(Picture::STATUS_REMOVING, Picture::STATUS_REMOVED))) {
            $replacePicture->setFromArray(array(
                'status'                => Picture::STATUS_REMOVING,
                'removing_date'         => new Zend_Db_Expr('now()'),
                'change_status_user_id' => $user->id
            ));
            $replacePicture->save();
        }

        // comments
        $comments = new Comments();
        $comments->moveMessages(
            Comment_Message::PICTURES_TYPE_ID, $replacePicture->id,
            Comment_Message::PICTURES_TYPE_ID, $picture->id
        );
        $ctTable = new Comment_Topic();
        $ctTable->updateTopicStat(Comment_Message::PICTURES_TYPE_ID, $replacePicture->id);
        $ctTable->updateTopicStat(Comment_Message::PICTURES_TYPE_ID, $picture->id);

        // pms
        $owner = $picture->findParentUsersByOwner();
        $replaceOwner = $replacePicture->findParentUsersByOwner();
        $recepients = array();
        if ($owner) {
            $recepients[$owner->id] = $owner;
        }
        if ($replaceOwner) {
            $recepients[$replaceOwner->id] = $replaceOwner;
        }
        unset($recepients[$user->id]);
        if ($recepients) {
            $url = $this->view->serverUrl($this->view->pic($picture)->url());
            $replaceUrl = $this->view->serverUrl($this->view->pic($replacePicture)->url());
            $moderUrl = $this->view->serverUrl($this->_helper->url->url(array(
                'module'     => 'default',
                'controller' => 'users',
                'action'     => 'user',
                'identity'   => $user->identity,
                'user_id'    => $user->id
            ), 'users', true));

            $message = sprintf(
                '%s принял замену %s на %s',
                $moderUrl, $replaceUrl, $url
            );
            $mModel = new Message();
            foreach ($recepients as $recepient) {
                $mModel->send(null, $recepient->id, $message);
            }
        }

        // log
        $this->_helper->log(sprintf(
            'Замена %s на %s',
            $this->view->htmlA($this->pictureUrl($replacePicture), $replacePicture->getCaption()),
            $this->view->htmlA($this->pictureUrl($picture), $picture->getCaption())
        ), array($picture, $replacePicture));

        return $this->_redirect($this->_helper->url->url(array(
            'action' => 'picture'
        )));
    }

    private function accept(Picture_Row $picture)
    {
        $hasAcceptRight = $this->_helper->user()->isAllowed('picture', 'accept');

        $canAccept = $hasAcceptRight
                  && in_array($picture->status, [Picture::STATUS_NEW, Picture::STATUS_INBOX]);

        if ($canAccept) {

            $user = $this->_helper->user()->get();

            $previousStatusUserId = $picture->change_status_user_id;

            $pictureUrl = $this->view->serverUrl($this->view->pic($picture)->url());

            $picture->status = Picture::STATUS_ACCEPTED;
            $picture->change_status_user_id = $user->id;
            if (!$picture->accept_datetime) {
                $picture->accept_datetime = new Zend_Db_Expr('NOW()');

                $owner = $picture->findParentUsersByOwner();
                if ( $owner && ($owner->id != $user->id) ) {
                    $message = sprintf(
                        'Добавленная вами картинка %s принята на сайт',
                        $pictureUrl
                    );

                    $mModel = new Message();
                    $mModel->send(null, $owner->id, $message);
                }
            }
            $picture->save();

            if ($previousStatusUserId != $user->id) {
                $userTable = new Users();
                $mModel = new Message();
                foreach ($userTable->find($previousStatusUserId) as $prevUser) {
                    $message = sprintf(
                        'Принята картинка %s',
                        $pictureUrl
                    );
                    $mModel->send(null, $prevUser->id, $message);
                }
            }

            $this->_helper->log(sprintf(
                'Картинка %s принята',
                $this->view->htmlA($this->pictureUrl($picture), $picture->getCaption())
            ), $picture);
        }
    }

    public function acceptAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forward('forbidden', 'error', 'default');
        }

        foreach ($this->table->find($this->getParam('id')) as $picture) {
            $this->accept($picture);
        }

        return $this->_helper->json(true);
    }

    public function voteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forward('forbidden', 'error', 'default');
        }

        $pictureRows = $this->table->find($this->_getParam('id'));

        $user = $this->_helper->user()->get();

        $request = $this->getRequest();

        $hasVoteRight = $this->_helper->user()->isAllowed('picture', 'moder_vote');

        $vote = (int)$this->_getParam('vote');

        $reason = trim($this->_getParam('reason'));

        $moderVotes = new Pictures_Moder_Votes();

        foreach ($pictureRows as $picture) {

            $voteExists = $this->pictureVoteExists($picture, $user);

            if (!$voteExists && $hasVoteRight) {
                $moderVotes->insert(array(
                    'user_id'    => $user->id,
                    'picture_id' => $picture->id,
                    'day_date'   => new Zend_Db_Expr('NOW()'),
                    'reason'     => $reason,
                    'vote'       => $vote ? 1 : 0
                ));

                if ($vote && $picture->status == Picture::STATUS_REMOVING) {
                    $picture->status = Picture::STATUS_INBOX;
                    $picture->save();
                }

                $message = sprintf(
                    $vote
                        ? 'Подана заявка на принятие картинки %s'
                        : 'Подана заявка на удаление картинки %s',
                    $this->view->htmlA($this->pictureUrl($picture), $picture->getCaption())
                );
                $this->_helper->log($message, $picture);

                $owner = $picture->findParentUsersByOwner();
                $ownerIsModer = $owner && $this->_helper->user($owner)->inheritsRole('moder');
                if ($ownerIsModer) {
                    if ($owner->id != $this->_helper->user()->get()->id) {
                        $message = sprintf(
                            'Подана заявка на %s добавленной вами картинки %s'.PHP_EOL.' Причина: %s',
                            $vote ? 'удаление' : 'принятие',
                            $this->view->serverUrl($this->pictureUrl($picture)),
                            $values['reason']
                        );
                        $mModel = new Message();
                        $mModel->send(null, $owner->id, $message);
                    }
                }
            }
        }

        return $this->_helper->json(true);
    }

    private function getCopyrightsForm()
    {
        return new Project_Form(array(
            'method' => Zend_Form::METHOD_POST,
            'action' => $this->_helper->url->url(array(
                'form' => 'copyrights-edit'
            )),
            'decorators' => array(
                'PrepareElements',
                ['viewScript', array(
                    'viewScript' => 'forms/markdown.phtml'
                )],
                'Form'
            ),
            'elements' => [
                ['textarea', 'text', array(
                    'required'   => false,
                    'decorators' => ['ViewHelper'],
                    'rows'       => 5
                )],
            ]
        ));
    }
}
<?php

namespace Application\Controller\Moder;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Application\Form\Moder\Inbox as InboxForm;
use Application\Model\Message;
use Application\Paginator\Adapter\Zend1DbTableSelect;
use Application\Service\TrafficControl;

use Brands;
use Car_Parent;
use Cars;
use Comments;
use Comment_Message;
use Engines;
use Factory;
use Perspectives;
use Picture;
use Pictures_Moder_Votes;
use Users;

use Exception;

use Zend_Db_Expr;
use Zend_Session_Namespace;

class PicturesController extends AbstractActionController
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

    private $textStorage;

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

    public function __construct($textStorage)
    {
        $this->table = new Picture();
        $this->textStorage = $textStorage;
    }

    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->user()->inheritsRole('moder') ) {
            return $this->forbiddenAction();
        }
    }

    public function ownerTypeaheadAction()
    {
        $q = $this->params()->fromQuery('query');

        $users = new Users();

        $selects = [];

        $selects[] = $users->select(true)
            ->join(['p' => 'pictures'], 'users.id = p.owner_id', null)
            ->group('users.id')
            ->where('users.id like ?', $q . '%')
            ->limit(10);

        $selects[] = $users->select(true)
            ->join(['p' => 'pictures'], 'users.id = p.owner_id', null)
            ->group('users.id')
            ->where('users.login like ?', $q . '%')
            ->limit(10);

        $selects[] = $users->select(true)
            ->join(['p' => 'pictures'], 'users.id = p.owner_id', null)
            ->group('users.id')
            ->where('users.identity like ?', $q . '%')
            ->limit(10);

        $selects[] = $users->select(true)
            ->join(['p' => 'pictures'], 'users.id = p.owner_id', null)
            ->group('users.id')
            ->where('users.name like ?', $q . '%')
            ->limit(10);


        $options = [];
        foreach ($selects as $select) {
            if (count($options) < 10) {
                foreach ($users->fetchAll($select) as $user) {
                    $str = ['#' . $user->id];
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

        return new JsonModel(array_values($options));
    }

    private function getFilterForm()
    {
        $db = $this->table->getAdapter();

        $brandMultioptions = $db->fetchPairs(
            $db->select()
                ->from('brands', ['id', 'caption'])
                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->join('pictures', 'car_parent_cache.car_id = pictures.car_id', null)
                ->where('pictures.status = ?', Picture::STATUS_INBOX)
                ->group('brands.id')
                ->order(['brands.position', 'brands.caption'])
        );

        $form = new InboxForm(null, [
            'perspectiveOptions' => [
                ''     => 'любой',
                'null' => 'не задан'
            ] + $db->fetchPairs(
                $db
                    ->select()
                    ->from('perspectives', ['id', 'name'])
                    ->order('position')
            ),
            'brandOptions'       => [
                '' => 'любой'
            ] + $brandMultioptions,
        ]);

        $form->setAttribute('action', $this->url()->fromRoute(null, [
            'action' => 'index'
        ]));

        return $form;
    }

    public function indexAction()
    {
        $perPage = 24;

        $orders = [
            1 => ['sql' => 'pictures.add_date DESC',                    'name' => 'Дата добавления (новые)'],
            2 => ['sql' => 'pictures.add_date',                         'name' => 'Дата добавления (старые)'],
            3 => ['sql' => ['pictures.width DESC', 'pictures.height DESC'], 'name' => 'Разрешение (большие)'],
            4 => ['sql' => ['pictures.width', 'pictures.height'],           'name' => 'Разрешение (маленькие)'],
            5 => ['sql' => 'pictures.filesize DESC',                    'name' => 'Размер (большие)'],
            6 => ['sql' => 'pictures.filesize',                         'name' => 'Размер (маленькие)'],
            7 => ['sql' => 'comment_topic.messages DESC',      'name' => 'Комментируемые'],
            8 => ['sql' => 'picture_view.views DESC',          'name' => 'Просмотры'],
            9 => ['sql' => 'pdr.day_date DESC',                'name' => 'Заявки на принятие/удаление'],
        ];

        if ($this->getRequest()->isPost()) {
            $form = $this->getFilterForm();
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $post = $form->getData();
                foreach ($post as $key => $value) {
                    if (strlen($value) == 0) {
                        unset($post[$key]);
                    }
                }
                $post['action'] = 'index';
                return $this->redirect()->toRoute('moder/pictures/params', $post);
            }
        }

        $form = $this->getFilterForm();
        $form->setData($this->params()->fromRoute());
        $form->isValid();
        $formdata = $form->getData();

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
                    $select->where('pictures.status not in (?)', [
                        Picture::STATUS_REMOVING,
                        Picture::STATUS_REMOVED
                    ]);
                    break;
            }
        }

        if (strlen($formdata['type_id'])) {
            $select->where('pictures.type = ?', $formdata['type_id']);
        }

        if ($formdata['brand_id']) {
            if (strlen($formdata['type_id']) && in_array($formdata['type_id'], [Picture::UNSORTED_TYPE_ID, Picture::LOGO_TYPE_ID, Picture::MIXED_TYPE_ID])) {
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
                        ->joinLeft(['pdr' => 'pictures_moder_votes'], 'pictures.id=pdr.picture_id', null)
                        ->where('pdr.picture_id IS NULL');
                    break;

                case '1':
                    $select
                        ->join(['pdr' => 'pictures_moder_votes'], 'pictures.id=pdr.picture_id', null)
                        ->where('pdr.vote > 0');
                    break;

                case '2':
                    $select
                        ->join(['pdr' => 'pictures_moder_votes'], 'pictures.id=pdr.picture_id', null)
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
                ->join(['pdr' => 'pictures_moder_votes'], 'pictures.id=pdr.picture_id', null);
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

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage($perPage)
            ->setCurrentPageNumber($this->params('page'));

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $picturesData = $this->pic()->listData($select, [
            'width' => 4
        ]);

        $perspectives = new Perspectives();
        $multioptions = $perspectives->getAdapter()->fetchPairs(
            $perspectives->getAdapter()->select()
                ->from($perspectives->info('name'), ['id', 'name'])
                ->order('position')
        );

        $multioptions = array_replace([
            '' => '--'
        ], $multioptions);

        foreach ($picturesData['items'] as &$item) {
            $picturePerspective = null;
            if ($item['type'] == Picture::CAR_TYPE_ID) {
                if ($this->user()->inheritsRole('moder')) {
                    $perspectives = new Perspectives();

                    $item['perspective'] = [
                        'options' => $multioptions,
                        'url'     => $this->url()->fromRoute('moder/pictures/params', [
                            'action'     => 'picture-perspective',
                            'picture_id' => $item['id']
                        ]),
                        'value'   => $item['perspective_id'],
                        'user'    => null
                    ];
                }
            }
        }
        unset($item);

        $reasons = [
            'плохое качество',
            'дубль',
            'любительское фото',
            'не по теме сайта',
            'другая',
            'своя'
        ];
        $reasons = array_combine($reasons, $reasons);
        if (isset($_COOKIE['customReason'])) {
            foreach ((array)unserialize($_COOKIE['customReason']) as $reason) {
                if (strlen($reason) && !in_array($reason, $reasons)) {
                    $reasons[$reason] = $reason;
                }
            }
        }

        return [
            'paginator'    => $paginator,
            'picturesData' => $picturesData,
            'form'         => $form,
            'reasons'      => $reasons
        ];
    }

    private function pictureUrl(Picture_Row $picture, $forceCanonical = false)
    {
        return $this->url()->fromRoute('moder/pictures/params', [
            'action'     => 'picture',
            'picture_id' => $picture->id
        ], [
            'force_canonical' => $forceCanonical
        ]);
    }

    public function picturePerspectiveAction()
    {
        $picture = $this->table->find($this->params('picture_id'))->current();
        if (!$picture) {
            return $this->notFoundAction();
        }

        if ($picture->type != Picture::CAR_TYPE_ID)
            throw new Exception('Картинка несовместимого типа');

        $perspectives = new Perspectives();

        $multioptions = [
            ''    => '--'
        ] + $perspectives->getAdapter()->fetchPairs(
            $perspectives->getAdapter()->select()
                ->from($perspectives->info('name'), ['id', 'name'])
                ->order('position')
        );

        $form = new Zend_Form([
            'method'    => 'post',
            'action'    => $this->url()->fromRoute('moder/pictures/params', [
                'action'     => 'picture-perspective',
                'picture_id' => $picture->id
            ]),
            'elements'    => [
                ['select', 'perspective_id', [
                    'required'     => false,
                    'label'        => 'Ракурс',
                    'decorators'   => ['ViewHelper'],
                    'multioptions' => $multioptions,

                ]],
            ],
            'class' => 'tiny',
            'decorators'    => [
                'PrepareElements',
                ['viewScript', ['viewScript' => 'forms/pictures/perspective.phtml']],
                'Form'
            ],
        ]);

        $form->populate([
            'perspective_id' => $picture->perspective_id
        ]);

        $request = $this->getRequest();

        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $user = $this->user()->get();
            $picture->perspective_id = $values['perspective_id'];
            $picture->change_perspective_user_id = $user->id;
            $picture->save();

            $this->log(sprintf(
                'Установка ракурса картинки %s',
                $picture->getCaption()
            ), [$picture]);

            if ($request->isXmlHttpRequest()) {
                return new JsonModel([
                    'ok' => true
                ]);
            }

            return $this->redirect()->toUrl($this->pictureUrl($picture));
        }

        return [
            'picture' => $picture,
            'form'    => $form,
            'tiny'    => (bool)$this->getParam('tiny')
        ];
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

    public function pictureSelectEngineAction()
    {
        $picture = $this->table->find($this->params('picture_id'))->current();
        if (!$picture) {
            return $this->notFoundAction();
        }

        if ($picture->type != Picture::ENGINE_TYPE_ID)
            throw new Exception('Картинка несовместимого типа');

        $canMove = $this->user()->isAllowed('picture', 'move');
        if (!$canMove) {
            return $this->forbiddenAction();
        }

        $brand = null;
        $brands = null;
        $engines = null;
        $engineTable = new Engines();
        $engine = $engineTable->find($this->params('engine'))->current();

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
                $imageStorage->changeImageName($picture->image_id, [
                    'pattern' => $picture->getFileNamePattern(),
                ]);
            } else {
                $picture->correctFileName();
            }
            $rows = $brandTable->fetchAll(
                $brandTable->select(true)
                    ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                    ->join('engine_parent_cache', 'brand_engine.engine_id = engine_parent_cache.parent_id', null)
                    ->where('engine_parent_cache.engine_id = ?', $engine->id)
            );

            foreach ($rows as $cBrand) {
                $cBrand->updatePicturesCache();
                $cBrand->refreshEnginePicturesCount();
            }

            foreach ($oldBrands as $oldBrand) {
                $oldBrand->updatePicturesCache();
                $oldBrand->refreshEnginePicturesCount();
            }

            $this->log(sprintf(
                'Назначение двигателя %s картинке %s',
                $engine->getMetaCaption(),
                $picture->getCaption()
            ), [$engine, $picture]);

            return $this->redirect()->toUrl($this->pictureUrl($picture));
        } else {
            $brandTable = new Brands();
            $brand = $brandTable->find($this->params('brand_id'))->current();
            if ($brand) {
                $engineTable = new Engines();
                $engines = $engineTable->fetchAll(
                    $engineTable->select(true)
                        ->join('brand_engine', 'engines.id = brand_engine.engine_id', null)
                        ->where('brand_engine.brand_id = ?', $brand->id)
                        ->order('engines.caption')
                );

                /*$this->view->assign([
                    'engines' => $this->enginesWalkTree(null, $brand->id)
                ]);*/

            } else {
                $brands = $brandTable->fetchAll(
                    $brandTable->select(true)
                        ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                        ->group('brands.id')
                        ->order(['brands.position', 'brands.caption'])
                );
            }
        }

        return [
            'picture' => $picture,
            'brand'   => $brand,
            'brands'  => $brands,
            'engines' => $engines
        ];
    }

    public function pictureSelectFactoryAction()
    {
        $picture = $this->table->find($this->params('picture_id'))->current();
        if (!$picture) {
            return $this->notFoundAction();
        }

        if ($picture->type != Picture::FACTORY_TYPE_ID) {
            throw new Exception('Картинка несовместимого типа');
        }

        $canMove = $this->user()->isAllowed('picture', 'move');
        if (!$canMove) {
            return $this->forbiddenAction();
        }

        $factoryTable = new Factory();
        $factory = $factoryTable->find($this->params('factory_id'))->current();

        if ($factory) {

            $picture->factory_id = $factory->id;
            $picture->save();

            $imageStorage = $this->getInvokeArg('bootstrap')
                ->getResource('imagestorage');
            $imageStorage->changeImageName($picture->image_id, [
                'pattern' => $picture->getFileNamePattern(),
            ]);

            $this->log(sprintf(
                'Назначение завода %s картинке %s',
                htmlspecialchars($factory->name),
                htmlspecialchars($picture->getCaption())
            ), [$factory, $picture]);

            return $this->redirect()->toUrl($this->pictureUrl($picture));

        }

        return [
            'picture'   => $picture,
            'factories' => $factoryTable->fetchAll(
                $factoryTable->select(true)
                    ->order('name')
            )
        ];
    }

    public function pictureSelectCarAction()
    {
        $picture = $this->table->find($this->params('picture_id'))->current();
        if (!$picture) {
            return $this->notFoundAction();
        }

        if ($picture->type != Picture::CAR_TYPE_ID) {
            throw new Exception('Картинка несовместимого типа');
        }

        $canMove = $this->user()->isAllowed('picture', 'move');
        if (!$canMove) {
            return $this->forbiddenAction();
        }

        $brand = null;
        $brands = null;
        $cars = null;
        $haveConcepts = null;
        $carTable = new Cars();
        $car = $carTable->find($this->params('car_id'))->current();

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
                $imageStorage->changeImageName($picture->image_id, [
                    'pattern' => $picture->getFileNamePattern(),
                ]);
            } else {
                $picture->correctFileName();
            }

            $namespace = new Zend_Session_Namespace('Moder_Car');
            $namespace->lastCarId = $car->id;

            $this->log(sprintf(
                'Картинка %s связана с автомобилем %s',
                htmlspecialchars($picture->id),
                htmlspecialchars($car->getFullName())
            ), [$car, $picture]);

            return $this->redirect()->toUrl($this->pictureUrl($picture));
        } else {
            $brandTable = new Brands();
            $brand = $brandTable->find($this->params('brand_id'))->current();
            if ($brand) {

                $rows = $carTable->fetchAll(
                    $carTable->select(true)
                        ->join('brands_cars', 'cars.id=brands_cars.car_id', null)
                        ->where('brands_cars.brand_id = ?', $brand->id)
                        ->where('NOT cars.is_concept')
                        ->order(['cars.caption', 'cars.begin_year', 'cars.end_year', 'cars.begin_model_year', 'cars.end_model_year'])
                );
                $cars = $this->prepareCars($rows);

                $haveConcepts = (bool)$carTable->fetchRow(
                    $carTable->select(true)
                        ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                        ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                        ->where('brands_cars.brand_id = ?', $brand->id)
                        ->where('cars.is_concept')
                );

            } else {
                $brands = $brandTable->fetchAll(null, ['position', 'caption']);
            }
        }

        return [
            'picture'      => $picture,
            'brand'        => $brand,
            'brands'       => $brands,
            'cars'         => $cars,
            'haveConcepts' => $haveConcepts,
            'conceptsUrl'  => $this->url()->fromRoute(null, [
                'action' => 'concepts',
            ], [], true),
        ];
    }

    private function loadCarSelect($parentId)
    {
        $carTable = $this->catalogue()->getCarTable();

        $carRows = $carTable->fetchAll(
            $carTable->select(true)
                ->join('car_parent', 'cars.id = car_parent.car_id', null)
                ->where('car_parent.parent_id = ?', $parentId)
                ->order(['cars.caption', 'cars.body', 'cars.begin_year', 'cars.end_year'])
        );

        $cars = [];
        foreach ($carRows as $carRow) {
            $cars[] = [
                'url'    => $this->url()->fromRoute(null, [
                    'car_id' => $carRow->id
                ], [], true),
                'name'   => $carRow->getFullName(),
                'childs' => $this->loadCarSelect($carRow->id)
            ];
        }

        return $cars;
    }

    public function pictureSelectBrandAction()
    {
        $picture = $this->table->find($this->params('picture_id'))->current();
        if (!$picture) {
            return $this->notFoundAction();
        }

        if (!in_array($picture->type, [Picture::UNSORTED_TYPE_ID, Picture::LOGO_TYPE_ID, Picture::MIXED_TYPE_ID])) {
            throw new Exception('Картинка несовместимого типа');
        }

        $canMove = $this->user()->isAllowed('picture', 'move');
        if (!$canMove) {
            return $this->forbiddenAction();
        }

        $brandTable = new Brands();
        $brand = $brandTable->find($this->params('brand_id'))->current();
        $brands = null;

        if ($brand) {
            $oldBrand = $picture->findParentBrands();

            $picture->brand_id = $brand->id;
            $picture->save();

            if ($picture->image_id) {
                $imageStorage = $this->getInvokeArg('bootstrap')
                    ->getResource('imagestorage');
                $imageStorage->changeImageName($picture->image_id, [
                    'pattern' => $picture->getFileNamePattern(),
                ]);
            } else {
                $picture->correctFileName();
            }

            if ($oldBrand) {
                $oldBrand->updatePicturesCache();
            }
            $brand->updatePicturesCache();

            $this->log(sprintf(
                'Назначение бренда %s картинке %s',
                htmlspecialchars($brand->caption), htmlspecialchars($picture->getCaption())
            ), [$picture, $brand]);

            return $this->redirect()->toUrl($this->pictureUrl($picture));
        } else {
            $brands = $brandTable->fetchAll(null, ['position', 'caption']);
        }

        return [
            'picture' => $picture,
            'brands'  => $brands
        ];
    }

    private function pictureCanDelete($picture)
    {
        $user = $this->user()->get();
        $canDelete = false;
        if (in_array($picture->status, [Picture::STATUS_INBOX, Picture::STATUS_NEW])) {
            if ($this->user()->isAllowed('picture', 'remove')) {
                if ($this->pictureVoteExists($picture, $user)) {
                    $canDelete = true;
                }
            } elseif ($this->user()->isAllowed('picture', 'remove_by_vote')) {
                if ($this->pictureVoteExists($picture, $user)) {
                    $db = $this->table->getAdapter();
                    $acceptVotes = (int)$db->fetchOne(
                        $db->select()
                            ->from('pictures_moder_votes', [new Zend_Db_Expr('COUNT(1)')])
                            ->where('picture_id = ?', $picture->id)
                            ->where('vote > 0')
                    );
                    $deleteVotes = (int)$db->fetchOne(
                        $db->select()
                            ->from('pictures_moder_votes', [new Zend_Db_Expr('COUNT(1)')])
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
        $picture = $this->table->find($this->params('picture_id'))->current();
        if (!$picture) {
            return $this->notFoundAction();
        }

        $canDelete = $this->pictureCanDelete($picture);
        if (!$canDelete) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();
        $picture->setFromArray([
            'status'                => Picture::STATUS_REMOVING,
            'removing_date'         => new Zend_Db_Expr('CURDATE()'),
            'change_status_user_id' => $user->id
        ]);
        $picture->save();

        if ($owner = $picture->findParentUsersByOwner()) {
            $message = sprintf(
                "Добавленная вами картинка %s поставлена в очередь на удаление" . PHP_EOL,
                $this->pic()->url($picture->id, $picture->identity, true)
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

        $this->log(sprintf(
            'Картинка %s поставлена в очередь на удаление',
            htmlspecialchars($picture->getCaption())
        ), $picture);

        return $this->redirect()->toUrl($this->pictureUrl($picture));
    }

    public function pictureVoteAction()
    {
        $picture = $this->table->find($this->params('picture_id'))->current();
        if (!$picture) {
            return $this->notFoundAction();
        }

        $hideVote = (bool)$this->params('hide-vote');

        $canDelete = $this->pictureCanDelete($picture);

        $isLastPicture = null;
        if ($picture->type == Picture::CAR_TYPE_ID && $picture->status == Picture::STATUS_ACCEPTED) {
            $car = $picture->findParentCars();
            if ($car) {
                $db = $this->table->getAdapter();
                $isLastPicture = !$db->fetchOne(
                    $db->select()
                        ->from('pictures', [new Zend_Db_Expr('COUNT(1)')])
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
                        ->from('pictures', [new Zend_Db_Expr('COUNT(1)')])
                        ->where('car_id = ?', $car->id)
                        ->where('status = ?', Picture::STATUS_ACCEPTED)
                );
            }
        }

        $user = $this->user()->get();
        $voteExists = $this->pictureVoteExists($picture, $user);

        $request = $this->getRequest();

        $formPictureVote = null;
        if (!$voteExists && $this->user()->isAllowed('picture', 'moder_vote'))
        {
            $form = new Application_Form_Moder_Picture_Vote([
                'action' => $this->url()->fromRoute('moder/pictures/params', [
                    'action'     => 'picture-vote',
                    'form'       => 'picture-vote',
                    'picture_id' => $picture->id
                ], [], true),
            ]);

            if ($request->isPost() && $this->params('form') == 'picture-vote' && $form->isValid($request->getPost()))
            {
                $values = $form->getValues();

                if ($customReason = $request->getCookie('customReason')) {
                    $customReason = (array)unserialize($customReason);
                } else {
                    $customReason = [];
                }

                $customReason[] = $values['reason'];
                $customReason = array_unique($customReason);

                setcookie('customReason', serialize($customReason), time()+60*60*24*30, '/');

                $vote = (bool)($values['vote'] == 'Хочу принять');

                $user = $this->user()->get();
                $moderVotes = new Pictures_Moder_Votes();
                $moderVotes->insert([
                    'user_id'    => $user->id,
                    'picture_id' => $picture->id,
                    'day_date'   => new Zend_Db_Expr('NOW()'),
                    'reason'     => $values['reason'],
                    'vote'       => $vote ? 1 : 0
                ]);

                if ($vote && $picture->status == Picture::STATUS_REMOVING) {
                    $picture->status = Picture::STATUS_INBOX;
                    $picture->save();
                }

                $message = sprintf(
                    $vote
                        ? 'Подана заявка на принятие картинки %s'
                        : 'Подана заявка на удаление картинки %s',
                    htmlspecialchars($picture->getCaption())
                );
                $this->log($message, $picture);

                $owner = $picture->findParentUsersByOwner();
                $ownerIsModer = $owner && $this->user($owner)->inheritsRole('moder');
                if ($ownerIsModer) {
                    if ($owner->id != $this->user()->get()->id) {
                        $message = sprintf(
                            'Подана заявка на %s добавленной вами картинки %s'.PHP_EOL.' Причина: %s',
                            $vote ? 'удаление' : 'принятие',
                            $this->pictureUrl($picture, true),
                            $values['reason']
                        );

                        $mModel = new Message();
                        $mModel->send(null, $owner->id, $message);
                    }
                }

                $referer = $request->getServer('HTTP_REFERER');
                if ($referer) {
                    return $this->redirect()->toUrl($this->pictureUrl($picture));
                }

                return $this->redirect()->toRoute(null, [], [], true);
            }

            $formPictureVote = $form;
        }

        $formPictureUnvote = null;
        if ($voteExists) {
            $form = new Application_Form_Moder_Picture_Unvote([
                'action' => $this->url()->fromRoute('moder/pictures/params', [
                    'action'     => 'picture-vote',
                    'form'       => 'picture-unvote',
                    'picture_id' => $picture->id
                ])
            ]);

            if ($request->isPost() && $this->params('form') == 'picture-unvote' && $form->isValid($request->getPost())) {
                $values = $form->getValues();

                $moderVotes = new Pictures_Moder_Votes();

                $user = $this->user()->get();
                $moderVotes->delete([
                    'user_id = ?'    => $user->id,
                    'picture_id = ?' => $picture->id
                ]);

                $referer = $request->getServer('HTTP_REFERER');
                if ($referer) {
                    return $this->redirect()->toUrl($referer);
                }

                return $this->redirect()->toUrl($this->pictureUrl($picture));
            }

            $formPictureUnvote = $form;
        }

        $deletePictureForm = null;
        if ($canDelete) {
            $form = new Application_Form_Moder_Picture_Delete([
                'action' => $this->url()->fromRoute('moder/pictures/params', [
                    'action'     => 'delete-picture',
                    'picture_id' => $picture->id,
                    'form'       => 'picture-delete'
                ])
            ]);
            $deletePictureForm = $form;
        }

        $moderVotes = null;
        if (!$hideVote) {
            $moderVotes = $picture->findPictures_Moder_Votes();
        }

        return [
            'isLastPicture'     => $isLastPicture,
            'acceptedCount'     => $acceptedCount,
            'canDelete'         => $canDelete,
            'deletePictureForm' => $deletePictureForm,
            'formPictureVote'   => $formPictureVote,
            'formPictureUnvote' => $formPictureUnvote,
            'moderVotes'        => $moderVotes
        ];
    }

    public function pictureAction()
    {
        $picture = $this->table->find($this->params('picture_id'))->current();
        if (!$picture) {
            return $this->notFoundAction();
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
                 ->where('status IN (?)', [Picture::STATUS_NEW, Picture::STATUS_INBOX])
                 ->order('id DESC')
                 ->limit(1)
        );

        $nextNewPicture = $this->table->fetchRow(
            $this->table->select(true)
                 ->where('id > ?', $picture->id)
                 ->where('status IN (?)', [Picture::STATUS_NEW, Picture::STATUS_INBOX])
                 ->order('id')
                 ->limit(1)
        );


        $ban = false;
        $canBan = $this->user()->isAllowed('user', 'ban');
        $canViewIp = $this->user()->isAllowed('user', 'ip');

        if ($canBan && $picture->ip !== null && $picture->ip !== '') {

            $service = new TrafficControl();
            $ban = $service->getBanInfo(inet_ntop($picture->ip));
            if ($ban) {
                $userTable = new Users();
                $ban['user'] = $userTable->find($ban['user_id'])->current();
            }
        }

        $editPictureForm = new Application_Form_Moder_Picture_Edit([
            'action' => $this->url()->fromRoute([
                'form' => 'picture-edit'
            ], [], true)
        ]);

        $editPictureForm->populate($picture->toArray());
        $request = $this->getRequest();
        if ($request->isPost() && ($this->getParam('form') == 'picture-edit') && $editPictureForm->isValid($request->getPost())) {
            $picture->setFromArray($editPictureForm->getValues());
            $picture->save();

            return $this->redirect()->toUrl($this->pictureUrl($picture));
        }

        $copyrightsForm = $this->getCopyrightsForm();
        if ($picture->copyrights_text_id) {
            $text = $this->textStorage->getText($picture->copyrights_text_id);
            $copyrightsForm->populate([
                'text' => $text
            ]);
        }
        if ($request->isPost() && ($this->getParam('form') == 'copyrights-edit') && $copyrightsForm->isValid($request->getPost())) {
            $values = $copyrightsForm->getValues();

            $text = $values['text'];

            $user = $this->user()->get();

            if ($picture->copyrights_text_id) {
                $this->textStorage->setText($picture->copyrights_text_id, $text, $user->id);
            } elseif ($text) {
                $textId = $this->textStorage->createText($text, $user->id);
                $picture->copyrights_text_id = $textId;
                $picture->save();
            }

            $this->log(sprintf(
                'Редактирование текста копирайтов изображения %s',
                htmlspecialchars($picture->getCaption())
            ), $picture);

            if ($picture->copyrights_text_id) {
                $userIds = $this->textStorage->getTextUserIds($picture->copyrights_text_id);
                $message = sprintf(
                    'Пользователь %s редактировал текст копирайтов изображения %s ( %s )',
                    $this->url()->fromRoute('users/user', [
                        'user_id' => $user->identity ? $user->identity : 'user' . $user->id
                    ], [
                        'force_canonical' => true
                    ]),
                    $picture->getCaption(),
                    $this->pictureUrl($picture, true)
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

            return $this->redirect()->toUrl($this->pictureUrl($picture));
        }

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
                $NotSections = ['FILE', 'COMPUTED'];
                $exif = @exif_read_data($picture->getSourceFilePath(), 0, True);
                if ($exif !== false) {
                    foreach ($exif as $key => $section) {
                        if (array_search($key, $NotSections) !== false)
                            continue;

                        $exifStr .= '<p>['.htmlspecialchars($key).']';
                        foreach ($section as $name => $val) {
                            $exifStr .= "<br />".htmlspecialchars($name).": ";
                            if (is_array($val))
                                $exifStr .= htmlspecialchars(implode(', ', $val));
                            else
                                $exifStr .= htmlspecialchars($val);
                        }

                        $exifStr .= '</p>';
                    }
                }
            } catch (Exception $e) {
                $exifStr .= 'Ошибка при чтении EXIF: '.$e->getMessage();
            }
        }



        $canMove =  $this->user()->isAllowed('picture', 'move');

        $formModerPictureType = false;
        if ($canMove) {
            $form = new Application_Form_Moder_Picture_Type([
                'action' => $this->url()->fromRoute(null, ['form' => 'picture-type'], [], true)
            ]);
            $form->populate($picture->toArray());

            $formModerPictureType = $form;
            if ($request->isPost() && $this->params('form') == 'picture-type' && $form->isValid($request->getPost())) {
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

                $this->log(sprintf(
                    'Изменение типа картинки %s',
                    htmlspecialchars($picture->getCaption())
                ), $picture);

                return $this->redirect()->toUrl($this->pictureUrl($picture));
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

        $unacceptPictureForm = null;

        $canUnaccept = ($picture->status == Picture::STATUS_ACCEPTED)
                    && $this->user()->isAllowed('picture', 'unaccept');

        if ($canUnaccept) {

            $form = new Zend_Form([
                'action'     => $this->url()->fromRoute(['form' => 'picture-unaccept'], [], true),
                'decorators' => [
                    'FormElements',
                    'PrepareElements',
                    'Form',
                ],
                'elements'   => [
                    ['submit', 'send', [
                        'required'   => false,
                        'ignore'     => true,
                        'label'      => 'Сделать не принятой',
                        'class'      => 'btn btn-warning',
                        'decorators' => ['ViewHelper']
                    ]]
                ]
            ]);

            $unacceptPictureForm = $form;

            if ($request->isPost() && $this->params('form') == 'picture-unaccept' && $form->isValid($request->getPost())) {
                $values = $form->getValues();

                $previousStatusUserId = $picture->change_status_user_id;

                $user = $this->user()->get();
                $picture->status = Picture::STATUS_INBOX;
                $picture->change_status_user_id = $user->id;
                $picture->save();



                $this->log(sprintf(
                    'С картинки %s снят статус "принято"',
                    htmlspecialchars($picture->getCaption())
                ), $picture);


                $mModel = new Message();

                $pictureUrl = $this->pic()->url($picture->id, $picture->identity, true);
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
                return $this->redirect()->toUrl($referer ? $referer : $this->url()->fromRoute(null, [], [], true));
            }
        }

        $canAccept = in_array($picture->status, [Picture::STATUS_NEW, Picture::STATUS_INBOX])
                  && $this->user()->isAllowed('picture', 'accept');

        $acceptPictureForm = null;
        if ($canAccept) {

            $acceptPictureForm = new Application_Form_Moder_Picture_Accept([
                'action'  => $this->url()->fromRoute(null, [
                    'form' => 'picture-accept'
                ], [], true)
            ]);

            if ($request->isPost() && $this->params('form') == 'picture-accept' && $acceptPictureForm->isValid($request->getPost())) {

                $this->accept($picture);

                $url = $request->getServer('HTTP_REFERER');
                if (!$url) {
                    $url = $this->url()->fromRoute(null, [], [], true);
                }

                return $this->redirect()->toUrl($url);
            }
        }

        $canRestore = $this->canRestore($picture);
        $restorePictureForm = null;
        if ($canRestore) {
            $restorePictureForm = new Application_Form_Moder_Picture_Restore([
                'action' => $this->url()->fromRoute(null, [
                    'action' => 'restore'
                ], [], true)
            ]);
        }

        $replacePicture = null;
        if ($picture->replace_picture_id) {
            $row = $this->table->find($picture->replace_picture_id)->current();
            if ($row) {

                $canAcceptReplace = $this->canReplace($picture, $row);

                $replacePicture = [
                    'url' => $this->url()->fromRoute('moder/pictures/params', [
                        'action'     => 'picture',
                        'picture_id' => $row->id
                    ], [], true),
                    'canAccept' => $canAcceptReplace,
                    'acceptUrl' => $this->url()->fromRoute('moder/pictures/params', [
                        'action'     => 'accept-replace',
                        'picture_id' => $picture->id
                    ], [], true),
                    'cancelUrl' => $this->url()->fromRoute('moder/pictures/params', [
                        'action'     => 'cancel-replace',
                        'picture_id' => $picture->id
                    ], [], true),
                ];
            }
        }

        $imageStorage = $this->getInvokeArg('bootstrap')
            ->getResource('imagestorage');

        $image = $imageStorage->getImage($picture->image_id);

        if (!$image) {
            return $this->notFoundAction();
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
                $crop = [
                    (int)$picture->crop_left,  (int)$picture->crop_top,
                    (int)$picture->crop_width, (int)$picture->crop_height,
                ];
            } else {
                $crop = [
                    0, 0,
                    (int)$picture->width, (int)$picture->height,
                ];
            }
        }


        return [
            'ban'             => $ban,
            'canBan'          => $canBan,
            'canViewIp'       => $canViewIp,
            'prevPicture'     => $prevPicture,
            'nextPicture'     => $nextPicture,
            'prevNewPicture'  => $prevNewPicture,
            'nextNewPicture'  => $nextNewPicture,
            'editPictureForm' => $editPictureForm,
            'copyrightsForm'  => $copyrightsForm,
            'unacceptPictureForm'           => $unacceptPictureForm,
            'formModerPictureType'          => $formModerPictureType,
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
            'pictureTypes'                  => [
                Picture::UNSORTED_TYPE_ID  => 'Несортировано',
                Picture::CAR_TYPE_ID       => 'Автомобиль',
                Picture::LOGO_TYPE_ID      => 'Логотип',
                Picture::MIXED_TYPE_ID     => 'Разное',
                Picture::ENGINE_TYPE_ID    => 'Двигатель',
            ],
            'galleryFullUrl'                => $galleryFullUrl,
            'sourceUrl'                     => $sourceUrl,
            'replacePicture'                => $replacePicture,
            'crop'                          => $crop
        ];
    }

    private function canCrop()
    {
        return $this->user()->isAllowed('picture', 'crop');
    }

    private function canNormalize(Picture_Row $picture)
    {
        return in_array($picture->status, [Picture::STATUS_NEW, Picture::STATUS_INBOX])
            && $this->user()->isAllowed('picture', 'normalize');
    }

    private function canFlop(Picture_Row $picture)
    {
        return in_array($picture->status, [Picture::STATUS_NEW, Picture::STATUS_INBOX, Picture::STATUS_REMOVING])
            && $this->user()->isAllowed('picture', 'flop');
    }

    private function canRestore(Picture_Row $picture)
    {
        return $picture->status == Picture::STATUS_REMOVING
            && $this->user()->isAllowed('picture', 'restore');
    }

    public function restoreAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        $picture = $this->table->find($this->params('picture_id'))->current();

        if (!$picture) {
            return $this->notFoundAction();
        }

        if (!$this->canRestore($picture)) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();
        $picture->setFromArray([
            'status'                => Picture::STATUS_INBOX,
            'change_status_user_id' => $user->id
        ]);
        $picture->save();

        $this->log(sprintf(
            'Картинки `%s` восстановлена из очереди удаления',
            htmlspecialchars($picture->getCaption())
        ), $picture);

        $referer = $this->getRequest()->getServer('HTTP_REFERER');
        return $this->redirect()->toUrl($referer ? $referer : $this->url()->fromRoute(null, [], [], true));
    }

    public function flopAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        $picture = $this->table->find($this->params('picture_id'))->current();
        if (!$picture) {
            return $this->notFoundAction();
        }

        if (!$this->canFlop($picture)) {
            return $this->forbiddenAction();
        }

        if ($picture->image_id) {
            $imageStorage = $this->getInvokeArg('bootstrap')
                ->getResource('imagestorage');

            $imageStorage->flop($picture->image_id);
        }

        $this->log(sprintf(
            'К картинке %s применён flop',
            htmlspecialchars($picture->getCaption())
        ), $picture);

        return new JsonModel([
            'ok' => true
        ]);
    }

    public function normalizeAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->notFoundAction();
        }

        $picture = $this->table->find($this->params('picture_id'))->current();
        if (!$picture) {
            return $this->notFoundAction();
        }

        if (!$this->canNormalize($picture)) {
            return $this->notFoundAction();
        }

        if ($picture->image_id) {
            $imageStorage = $this->getInvokeArg('bootstrap')
                ->getResource('imagestorage');

            $imageStorage->normalize($picture->image_id);
        }

        $this->log(sprintf(
            'К картинке %s применён normalize',
            htmlspecialchars($picture->getCaption())
        ), $picture);

        return new JsonModel([
            'ok' => true
        ]);
    }

    public function filesRepairAction()
    {
        /*if (!$this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }*/

        $picture = $this->table->find($this->params('picture_id'))->current();
        if (!$picture) {
            return $this->notFoundAction();
        }

        if ($picture->image_id) {
            $imageStorage = $this->getInvokeArg('bootstrap')
                ->getResource('imagestorage');

            $imageStorage->flush([
                'image' => $picture->image_id
            ]);
        }

        return new JsonModel([
            'ok' => true
        ]);
    }

    public function filesCorrectNamesAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->notFoundAction();
        }

        $picture = $this->table->find($this->params('picture_id'))->current();
        if (!$picture) {
            return $this->notFoundAction();
        }

        $imageStorage = $this->getInvokeArg('bootstrap')
            ->getResource('imagestorage');

        $imageStorage->changeImageName($picture->image_id, [
            'pattern' => $picture->getFileNamePattern(),
        ]);

        return new JsonModel([
            'ok' => true
        ]);
    }

    public function cropperSaveAction()
    {
        $picture = $this->table->find($this->params('picture_id'))->current();
        if (!$picture || !$this->canCrop()) {
            return $this->notFoundAction();
        }

        $left = round($this->params()->fromPost('x'));
        $top = round($this->params()->fromPost('y'));
        $width = round($this->params()->fromPost('w'));
        $height = round($this->params()->fromPost('h'));

        $left = max(0, $left);
        $left = min($picture->width, $left);
        $width = max(1, $width);
        $width = min($picture->width, $width);

        $top = max(0, $top);
        $top = min($picture->height, $top);
        $height = max(1, $height);
        $height = min($picture->height, $height);

        if ($left > 0 || $top > 0 || $width < $picture->width || $height < $picture->height) {
            $picture->setFromArray([
                'crop_left'   => $left,
                'crop_top'    => $top,
                'crop_width'  => $width,
                'crop_height' => $height
            ]);
        } else {
            $picture->setFromArray([
                'crop_left'   => null,
                'crop_top'    => null,
                'crop_width'  => null,
                'crop_height' => null
            ]);
        }
        $picture->save();

        $imageStorage = $this->getInvokeArg('bootstrap')
            ->getResource('imagestorage');

        $imageStorage->flush([
            'image' => $picture->image_id
        ]);

        $this->log(sprintf(
            'Выделение области на картинке %s',
            htmlspecialchars($picture->getCaption())
        ), [$picture]);

        return new JsonModel([
            'ok' => true
        ]);
    }

    private function prepareCars(Cars_Rowset $rows)
    {
        $carParentTable = $this->getCarParentTable();
        $carParentAdapter = $carParentTable->getAdapter();

        $cars = [];
        foreach ($rows as $row) {
            $haveChilds = (bool)$carParentAdapter->fetchOne(
                $carParentAdapter->select()
                    ->from($carParentTable->info('name'), new Zend_Db_Expr('1'))
                    ->where('parent_id = ?', $row->id)
            );
            $cars[] = [
                'name' => $row->getFullName(),
                'url'  => $this->url()->fromRoute([
                    'action' => 'picture-select-car',
                    'car_id' => $row['id']
                ], [], true),
                'haveChilds' => $haveChilds,
                'isGroup'    => $row->is_group,
                'type'       => null,
                'loadUrl'    => $this->url()->fromRoute([
                    'action' => 'car-childs',
                    'car_id' => $row['id']
                ], [], true),
            ];
        }

        return $cars;
    }

    private function prepareCarParentRows($rows)
    {
        $carParentTable = $this->getCarParentTable();
        $carParentAdapter = $carParentTable->getAdapter();
        $carTable = new Cars();

        $items = [];
        foreach ($rows as $carParentRow) {
            $car = $carTable->find($carParentRow->car_id)->current();
            if ($car) {
                $haveChilds = (bool)$carParentAdapter->fetchOne(
                    $carParentAdapter->select()
                        ->from($carParentTable->info('name'), new Zend_Db_Expr('1'))
                        ->where('parent_id = ?', $car->id)
                );
                $items[] = [
                    'name' => $car->getFullName(),
                    'url'  => $this->url()->fromRoute(null, [
                        'action' => 'picture-select-car',
                        'car_id' => $car['id']
                    ], [], true),
                    'haveChilds' => $haveChilds,
                    'isGroup'    => $car['is_group'],
                    'type'       => $carParentRow->type,
                    'loadUrl'    => $this->url()->fromRoute(null, [
                        'action' => 'car-childs',
                        'car_id' => $car['id']
                    ], [], true),
                ];
            }
        }

        return $items;
    }

    public function carChildsAction()
    {
        $user = $this->user()->get();
        if (!$user) {
            return $this->forbiddenAction();
        }

        $carTable = new Cars();
        $carParentTable = $this->getCarParentTable();

        $car = $carTable->find($this->params('car_id'))->current();
        if (!$car) {
            return $this->notFoundAction();
        }

        $rows = $carParentTable->fetchAll(
            $carParentTable->select(true)
                ->join('cars', 'cars.id = car_parent.car_id', null)
                ->where('car_parent.parent_id = ?', $car->id)
                ->order(['car_parent.type', 'cars.caption', 'cars.begin_year', 'cars.end_year'])
        );

        $viewModel = new ViewModel([
            'cars' => $this->prepareCarParentRows($rows)
        ]);
        $viewModel->setTerminal(true);

        return $viewModel;
    }

    public function conceptsAction()
    {

        $brandTable = new Brands();
        $brand = $brandTable->find($this->params('brand_id'))->current();
        if (!$brand) {
            return $this->notFoundAction();
        }

        $carTable = new Cars();

        $rows = $carTable->fetchAll(
            $carTable->select(true)
                ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand->id)
                ->where('cars.is_concept')
                ->order(['cars.caption', 'cars.begin_year', 'cars.end_year'])
                ->group('cars.id')
        );
        $concepts = $this->prepareCars($rows);

        $viewModel = new ViewModel([
            'concepts' => $concepts,
        ]);
        $viewModel->setTerminal(true);

        return $viewModel;
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
                $can1 = $this->user()->isAllowed('picture', 'accept');
                break;
        }

        $can2 = false;
        switch ($replacedPicture->status) {
            case Picture::STATUS_ACCEPTED:
                $can2 = $this->user()->isAllowed('picture', 'unaccept')
                     && $this->user()->isAllowed('picture', 'remove_by_vote');
                break;

            case Picture::STATUS_INBOX:
            case Picture::STATUS_NEW:
                $can2 = $this->user()->isAllowed('picture', 'remove_by_vote');
                break;

            case Picture::STATUS_REMOVING:
            case Picture::STATUS_REMOVED:
                $can2 = true;
                break;
        }

        return $can1 && $can2 && $this->user()->isAllowed('picture', 'move');
    }

    public function cancelReplaceAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        $picture = $this->table->find($this->params('picture_id'))->current();
        if (!$picture) {
            return $this->notFoundAction();
        }

        if (!$picture->replace_picture_id) {
            return $this->notFoundAction();
        }

        $replacePicture = $this->table->find($picture->replace_picture_id)->current();
        if (!$replacePicture) {
            return $this->notFoundAction();
        }

        if (!$this->user()->isAllowed('picture', 'move')) {
            return $this->forbiddenAction();
        }

        $picture->replace_picture_id = null;
        $picture->save();

        // log
        $this->log(sprintf(
            'Замена %s на %s отклонена',
            htmlspecialchars($replacePicture->getCaption()),
            htmlspecialchars($picture->getCaption())
        ), [$picture, $replacePicture]);

        return $this->redirect()->toRoute(null, [
            'action' => 'picture'
        ], [], true);
    }

    public function acceptReplaceAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        $picture = $this->table->find($this->params('picture_id'))->current();
        if (!$picture) {
            return $this->notFoundAction();
        }

        if (!$picture->replace_picture_id) {
            return $this->notFoundAction();
        }

        $replacePicture = $this->table->find($picture->replace_picture_id)->current();
        if (!$replacePicture) {
            return $this->notFoundAction();
        }

        if (!$this->canReplace($picture, $replacePicture)) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();

        // statuses
        if ($picture->status != Picture::STATUS_ACCEPTED) {
            $picture->setFromArray([
                'status'                => Picture::STATUS_ACCEPTED,
                'change_status_user_id' => $user->id
            ]);
            if (!$picture->accept_datetime) {
                $picture->accept_datetime = new Zend_Db_Expr('NOW()');
            }
            $picture->save();
        }

        if (!in_array($replacePicture->status, [Picture::STATUS_REMOVING, Picture::STATUS_REMOVED])) {
            $replacePicture->setFromArray([
                'status'                => Picture::STATUS_REMOVING,
                'removing_date'         => new Zend_Db_Expr('now()'),
                'change_status_user_id' => $user->id
            ]);
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
        $recepients = [];
        if ($owner) {
            $recepients[$owner->id] = $owner;
        }
        if ($replaceOwner) {
            $recepients[$replaceOwner->id] = $replaceOwner;
        }
        unset($recepients[$user->id]);
        if ($recepients) {
            $url = $this->pic()->url($picture->id, $picture->identity, true);
            $replaceUrl = $this->pic()->url($replacePicture->id, $replacePicture->identity, true);
            $moderUrl = $this->url()->fromRoute('users/user', [
                'user_id' => $user->identity ? $user->identity : 'user' . $user->id
            ], [
                'force_canonical' => true
            ]);

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
        $this->log(sprintf(
            'Замена %s на %s',
            htmlspecialchars($replacePicture->getCaption()),
            htmlspecialchars($picture->getCaption())
        ), [$picture, $replacePicture]);

        return $this->redirect()->toRoute([
            'action' => 'picture'
        ], [], true);
    }

    private function accept(Picture_Row $picture)
    {
        $hasAcceptRight = $this->user()->isAllowed('picture', 'accept');

        $canAccept = $hasAcceptRight
                  && in_array($picture->status, [Picture::STATUS_NEW, Picture::STATUS_INBOX]);

        if ($canAccept) {

            $user = $this->user()->get();

            $previousStatusUserId = $picture->change_status_user_id;

            $pictureUrl = $this->pic()->url($picture->id, $picture->identity, true);

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

            $this->log(sprintf(
                'Картинка %s принята',
                htmlspecialchars($picture->getCaption())
            ), $picture);
        }
    }

    public function acceptAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        foreach ($this->table->find($this->getParam('id')) as $picture) {
            $this->accept($picture);
        }

        return new JsonModel(true);
    }

    public function voteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->forbiddenAction();
        }

        $pictureRows = $this->table->find($this->params('id'));

        $user = $this->user()->get();

        $request = $this->getRequest();

        $hasVoteRight = $this->user()->isAllowed('picture', 'moder_vote');

        $vote = (int)$this->params('vote');

        $reason = trim($this->params('reason'));

        $moderVotes = new Pictures_Moder_Votes();

        foreach ($pictureRows as $picture) {

            $voteExists = $this->pictureVoteExists($picture, $user);

            if (!$voteExists && $hasVoteRight) {
                $moderVotes->insert([
                    'user_id'    => $user->id,
                    'picture_id' => $picture->id,
                    'day_date'   => new Zend_Db_Expr('NOW()'),
                    'reason'     => $reason,
                    'vote'       => $vote ? 1 : 0
                ]);

                if ($vote && $picture->status == Picture::STATUS_REMOVING) {
                    $picture->status = Picture::STATUS_INBOX;
                    $picture->save();
                }

                $message = sprintf(
                    $vote
                        ? 'Подана заявка на принятие картинки %s'
                        : 'Подана заявка на удаление картинки %s',
                    htmlspecialchars($picture->getCaption())
                );
                $this->log($message, $picture);

                $owner = $picture->findParentUsersByOwner();
                $ownerIsModer = $owner && $this->user($owner)->inheritsRole('moder');
                if ($ownerIsModer) {
                    if ($owner->id != $this->user()->get()->id) {
                        $message = sprintf(
                            'Подана заявка на %s добавленной вами картинки %s'.PHP_EOL.' Причина: %s',
                            $vote ? 'удаление' : 'принятие',
                            $this->pictureUrl($picture, true),
                            $values['reason']
                        );
                        $mModel = new Message();
                        $mModel->send(null, $owner->id, $message);
                    }
                }
            }
        }

        return new JsonModel(true);
    }

    private function getCopyrightsForm()
    {
        return new Project_Form([
            'method' => Zend_Form::METHOD_POST,
            'action' => $this->url()->fromRoute([
                'form' => 'copyrights-edit'
            ], [], true),
            'decorators' => [
                'PrepareElements',
                ['viewScript', [
                    'viewScript' => 'forms/markdown.phtml'
                ]],
                'Form'
            ],
            'elements' => [
                ['textarea', 'text', [
                    'required'   => false,
                    'decorators' => ['ViewHelper'],
                    'rows'       => 5
                ]],
            ]
        ]);
    }
}
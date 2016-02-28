<?php

use Application\Model\Message;

class Moder_FactoryController extends Zend_Controller_Action
{
    /**
     * @var Factory
     */
    private $factoryTable = null;

    /**
     * @return Factory
     */
    private function getFactoryTable()
    {
        return $this->factoryTable
            ? $this->factoryTable
            : $this->factoryTable = new Factory();
    }

    private function factoryModerUrl($id)
    {
        return $this->_helper->url->url(array(
            'action'     => 'factory',
            'controller' => 'factory',
            'module'     => 'moder',
            'factory_id' => $id
        ), 'default', true);
    }

    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder') ) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        geoPHP::version(); // for autoload classes
    }

    private function getDescriptionForm()
    {
        return new Project_Form(array(
            'method' => Zend_Form::METHOD_POST,
            'action' => $this->_helper->url->url(array(
                'action' => 'save-description'
            )),
            'decorators' => array(
                'PrepareElements',
                ['viewScript', array(
                    'viewScript' => 'forms/markdown.phtml'
                )],
                'Form'
            ),
            'elements' => [
                ['Brand_Description', 'markdown', array(
                    'required'   => false,
                    'decorators' => ['ViewHelper'],
                )],
            ]
        ));
    }

    public function factoryAction()
    {
        $factoryTable = $this->getFactoryTable();

        $factory = $factoryTable->find($this->_getParam('factory_id'))->current();
        if (!$factory) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $canEdit = $this->_helper->user()->isAllowed('factory', 'edit');

        if ($canEdit) {
            $form = new Application_Form_Moder_Factory_Edit(array(
                'action' => $this->_helper->url->url()
            ));

            $point = null;
            if ($factory->point) {
                $point = geoPHP::load(substr($factory->point, 4), 'wkb');
            }

            $form->populate(array(
                'name'        => $factory->name,
                'lat'         => $point ? $point->y() : null,
                'lng'         => $point ? $point->x() : null,
                'year_from'   => $factory->year_from,
                'year_to'     => $factory->year_to,
            ));
            $request = $this->getRequest();

            if ($request->isPost() && $form->isValid($request->getPost())) {
                $values = $form->getValues();

                if (strlen($values['lat']) && strlen($values['lng'])) {
                    $point = new Point($values['lng'], $values['lat']);

                    $point = new Zend_Db_Expr($factoryTable->getAdapter()->quoteInto('GeomFromText(?)', $point->out('wkt')));
                } else {
                    $point = null;
                }


                $factory->setFromArray(array(
                    'name'        => $values['name'],
                    'year_from'   => strlen($values['year_from']) ? $values['year_from'] : null,
                    'year_to'     => strlen($values['year_to']) ? $values['year_to'] : null,
                    'point'       => $point,
                ));
                $factory->save();

                $factoryUrl = $this->factoryModerUrl($factory->id);

                $message = sprintf(
                    'Редактирование завода %s',
                    $this->view->htmlA($factoryUrl, $factory->name)
                );
                $this->_helper->log($message, $factory);

                return $this->_redirect($factoryUrl);
            }

            $this->view->formModerFactoryEdit = $form;
        }

        $descriptionForm = null;
        if ($canEdit) {
            $descriptionForm = $this->getDescriptionForm();
        }

        if ($factory->text_id) {
            $textStorage = $this->_helper->textStorage();
            $description = $textStorage->getText($factory->text_id);
            if ($canEdit) {
                $descriptionForm->populate(array(
                    'markdown' => $description
                ));
            }
        } else {
            $description = '';
        }

        $carTable = new Cars();

        $cars = $carTable->fetchAll(
            $carTable->select(true)
                ->join('factory_car', 'cars.id = factory_car.car_id', null)
                ->where('factory_car.factory_id = ?', $factory->id)
        );

        $this->view->assign(array(
            'factory'         => $factory,
            'canEdit'         => $canEdit,
            'cars'            => $cars,
            'description'     => $description,
            'descriptionForm' => $descriptionForm
        ));
    }

    public function indexAction()
    {
        $brandTable = new Brands();

        $db = $brandTable->getAdapter();

        $brandOptions = array('' => '-') + $db->fetchPairs(
            $db->select()
                ->from('brands', array('id', 'caption'))
                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->join('factory_car', 'car_parent_cache.car_id = factory_car.car_id', null)
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

        $factoryTable = $this->getFactoryTable();

        $select = $factoryTable->select(true);

        if ($form->isValid($this->_getAllParams())) {
            $values = $form->getValues();

            if ($values['name']) {
                $select->where('factory.name like ?', '%' . $values['name'] . '%');
            }

            /*if ($values['brand_id']) {
                $select
                    ->join('engine_parent_cache', 'engines.id = engine_parent_cache.engine_id', null)
                    ->join('brand_engine', 'engine_parent_cache.parent_id = brand_engine.engine_id', null)
                    ->where('brand_engine.brand_id = ?', $values['brand_id']);
            }*/

            switch ($values['order']) {
                case 0:
                    $select->order('factory.id asc');
                    break;

                case 1:
                    $select->order('factory.id desc');
                    break;

                case 2:
                    $select->order('factory.name asc');
                    break;

                case 3:
                    $select->order('factory.name desc');
                    break;
            }
        }

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(10)
            ->setCurrentPageNumber($this->_getParam('page'));

        $pictureTable = new Picture();

        $factories = array();
        foreach ($paginator->getCurrentItems() as $factory) {

            $pictures = $pictureTable->fetchAll(array(
                'factory_id = ?' => $factory->id,
                'type = ?'       => Picture::FACTORY_TYPE_ID
            ), 'id', 4);

            $factories[] = array(
                'name'     => $factory->name,
                'pictures' => $pictures,
                'moderUrl' => $this->factoryModerUrl($factory->id),
            );
        }

        $this->view->assign(array(
            'form'      => $form,
            'paginator' => $paginator,
            'factories' => $factories
        ));
    }

    public function addAction()
    {
        if (!$this->_helper->user()->isAllowed('factory', 'add')) {
            return $this->_forward('forbidden', 'error');
        }

        $factoryTable = $this->getFactoryTable();

        $form = new Application_Form_Moder_Factory_Add(array(
            'description' => 'Новый завод',
            'action'      => $this->_helper->url->url(),
        ));

        $request = $this->getRequest();

        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $factory = $factoryTable->createRow(array(
                'name'      => $values['name'],
                'year_from' => strlen($values['year_from']) ? $values['year_from'] : null,
                'year_to'   => strlen($values['year_to']) ? $values['year_to'] : null,
            ));
            $factory->save();

            $url = $this->factoryModerUrl($factory->id);

            $this->_helper->log(sprintf(
                'Создан новый завод %s',
                $this->view->htmlA($url, $factory->name)
            ), $factory);

            return $this->_redirect($url);
        }

        $this->view->assign(array(
            'form' => $form,
        ));
    }

    public function saveDescriptionAction()
    {
        $canEdit = $this->_helper->user()->isAllowed('factory', 'edit');
        if (!$canEdit) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $user = $this->_helper->user()->get();

        $request = $this->getRequest();
        if (!$request->isPost()) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $factoryTable = $this->getFactoryTable();

        $factory = $factoryTable->find($this->_getParam('factory_id'))->current();
        if (!$factory) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $form = $this->getDescriptionForm();

        if ($form->isValid($request->getPost())) {

            $values = $form->getValues();

            $text = $values['markdown'];

            $textStorage = $this->_helper->textStorage();

            if ($factory->text_id) {
                $textStorage->setText($factory->text_id, $text, $user->id);
            } elseif ($text) {
                $textId = $textStorage->createText($text, $user->id);
                $factory->text_id = $textId;
                $factory->save();
            }


            $this->_helper->log(sprintf(
                'Редактирование описания завода %s',
                $this->view->htmlA($this->factoryModerUrl($factory->id), $factory->name)
            ), $factory);

            if ($factory->text_id) {
                $userIds = $textStorage->getTextUserIds($factory->text_id);
                $message = sprintf(
                    'Пользователь %s редактировал описание группы близнецов %s ( %s )',
                    $this->view->serverUrl($user->getAboutUrl()),
                    $factory->name,
                    $this->view->serverUrl($this->factoryModerUrl($factory->id))
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
        }

        return $this->redirect($this->factoryModerUrl($factory->id));
    }
}
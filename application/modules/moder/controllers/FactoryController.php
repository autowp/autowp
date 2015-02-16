<?php

require_once APPLICATION_PATH . '/../vendor/phayes/geoPHP/geoPHP.inc';

class Moder_FactoryController extends Zend_Controller_Action
{
    /**
     * @var Factory
     */
    protected $_factoryTable = null;

    /**
     * @return Factory
     */
    protected function _getFactoryTable()
    {
        return $this->_factoryTable
            ? $this->_factoryTable
            : $this->_factoryTable = new Factory();
    }

    protected function _factoryModerUrl($id)
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
    }

    public function factoryAction()
    {
        $factoryTable = $this->_getFactoryTable();

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
                'description' => $factory['description'],
            ));
            $request = $this->getRequest();

            if ($request->isPost() && $form->isValid($request->getPost())) {
                $values = $form->getValues();

                if (strlen($values['lat']) && strlen($values['lng'])) {
                    $point = new Point($values['lng'], $values['lat']);
                    $point = new Zend_Db_Expr($factoryTable->getAdapter()->quoteInto('GeomFromWKB(?)', $point->out('wkb')));
                } else {
                    $point = null;
                }

                $user = $this->_helper->user()->get();
                $factory->setFromArray(array(
                    'name'        => $values['name'],
                    'year_from'   => strlen($values['year_from']) ? $values['year_from'] : null,
                    'year_to'     => strlen($values['year_to']) ? $values['year_to'] : null,
                    'point'       => $point,
                    'description' => $values['description']
                ));
                $factory->save();

                $factoryUrl = $this->_factoryModerUrl($factory->id);

                $message = sprintf(
                    'Редактирование завода %s',
                    $this->view->htmlA($factoryUrl, $factory->name)
                );
                $this->_helper->log($message, $factory);

                return $this->_redirect($factoryUrl);
            }

            $this->view->formModerFactoryEdit = $form;
        }

        $carTable = new Cars();

        $cars = $carTable->fetchAll(
            $carTable->select(true)
                ->join('factory_car', 'cars.id = factory_car.car_id', null)
                ->where('factory_car.factory_id = ?', $factory->id)
        );

        $this->view->assign(array(
            'factory' => $factory,
            'canEdit' => $canEdit,
            'cars'    => $cars
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

        $factoryTable = $this->_getFactoryTable();

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
                'moderUrl' => $this->_factoryModerUrl($factory->id),
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

        $factoryTable = $this->_getFactoryTable();

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

            $url = $this->_factoryModerUrl($factory->id);

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
}
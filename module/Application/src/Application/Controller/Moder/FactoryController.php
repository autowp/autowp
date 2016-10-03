<?php

namespace Application\Controller\Moder;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

use Application\HostManager;
use Application\Model\DbTable\Brand as BrandTable;
use Application\Model\DbTable\Factory;
use Application\Model\DbTable\Picture;
use Application\Model\DbTable\User;
use Application\Model\DbTable\Vehicle;
use Application\Model\Message;
use Application\Paginator\Adapter\Zend1DbTableSelect;

use geoPHP;
use Point;

use Zend_Db_Expr;

class FactoryController extends AbstractActionController
{
    private $textStorage;

    /**
     * @var Factory
     */
    private $factoryTable = null;

    /**
     * @var Form
     */
    private $addForm;

    /**
     * @var Form
     */
    private $editForm;

    /**
     * @var Form
     */
    private $descForm;

    /**
     * @var Form
     */
    private $filterForm;

    /**
     * @var HostManager
     */
    private $hostManager;

    public function __construct(
        HostManager $hostManager,
        $textStorage,
        Form $addForm,
        Form $editForm,
        Form $descForm,
        Form $filterForm)
    {
        $this->hostManager = $hostManager;
        $this->textStorage = $textStorage;
        $this->addForm = $addForm;
        $this->editForm = $editForm;
        $this->descForm = $descForm;
        $this->filterForm = $filterForm;
    }

    /**
     * @return Factory
     */
    private function getFactoryTable()
    {
        return $this->factoryTable
            ? $this->factoryTable
            : $this->factoryTable = new Factory();
    }

    private function factoryModerUrl($id, $canonical = false, $uri = null)
    {
        return $this->url()->fromRoute('moder/factories/params', [
            'action'     => 'factory',
            'factory_id' => $id
        ], [
            'force_canonical' => $canonical,
            'uri'             => $uri
        ]);
    }

    public function factoryAction()
    {
        if (!$this->user()->inheritsRole('moder') ) {
            return $this->forbiddenAction();
        }

        $factoryTable = $this->getFactoryTable();

        $factory = $factoryTable->find($this->params('factory_id'))->current();
        if (!$factory) {
            return $this->notfoundAction();
        }

        $canEdit = $this->user()->isAllowed('factory', 'edit');

        if ($canEdit) {
            $this->editForm->setAttribute('action', $this->url()->fromRoute(null, [], [], true));

            $point = null;
            if ($factory->point) {
                geoPHP::version(); // for autoload classes
                $point = geoPHP::load(substr($factory->point, 4), 'wkb');
            }

            $this->editForm->populateValues([
                'name'        => $factory->name,
                'lat'         => $point ? $point->y() : null,
                'lng'         => $point ? $point->x() : null,
                'year_from'   => $factory->year_from,
                'year_to'     => $factory->year_to,
            ]);
            $request = $this->getRequest();

            if ($request->isPost()) {
                $this->editForm->setData($this->params()->fromPost());
                if ($this->editForm->isValid()) {
                    $values = $this->editForm->getData();

                    if (strlen($values['lat']) && strlen($values['lng'])) {
                        $point = new Point($values['lng'], $values['lat']);

                        $point = new Zend_Db_Expr($factoryTable->getAdapter()->quoteInto('GeomFromText(?)', $point->out('wkt')));
                    } else {
                        $point = null;
                    }


                    $factory->setFromArray([
                        'name'        => $values['name'],
                        'year_from'   => strlen($values['year_from']) ? $values['year_from'] : null,
                        'year_to'     => strlen($values['year_to']) ? $values['year_to'] : null,
                        'point'       => $point,
                    ]);
                    $factory->save();

                    $factoryUrl = $this->factoryModerUrl($factory->id);

                    $message = sprintf(
                        'Редактирование завода `%s`',
                        $factory->name
                    );
                    $this->log($message, $factory);

                    return $this->redirect()->toUrl($factoryUrl);
                }
            }
        }

        $this->descForm->setAttribute('action', $this->url()->fromRoute(null, [
            'action' => 'save-description'
        ], [], true));

        if ($factory->text_id) {
            $description = $this->textStorage->getText($factory->text_id);
            if ($canEdit) {
                $this->descForm->populateValues([
                    'markdown' => $description
                ]);
            }
        } else {
            $description = '';
        }

        $carTable = new Vehicle();

        $cars = $carTable->fetchAll(
            $carTable->select(true)
                ->join('factory_car', 'cars.id = factory_car.car_id', null)
                ->where('factory_car.factory_id = ?', $factory->id)
        );

        return [
            'factory'         => $factory,
            'canEdit'         => $canEdit,
            'cars'            => $cars,
            'description'     => $description,
            'descriptionForm' => $this->descForm,
            'formModerFactoryEdit' => $this->editForm
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
                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->join('factory_car', 'car_parent_cache.car_id = factory_car.car_id', null)
                ->group('brands.id')
                ->order(['brands.position', 'brands.caption'])
        );

        $this->filterForm->setAttribute('action', $this->url()->fromRoute(null, [], [], true));
        $this->filterForm->get('brand_id')->setValueOptions($brandOptions);

        if ($this->getRequest()->isPost()) {
            $this->filterForm->setData($this->params()->fromPost());
            if ($this->filterForm->isValid()) {
                $params = $this->filterForm->getData();
                unset($params['submit']);
                foreach ($params as $key => $value) {
                    if (strlen($value) <= 0) {
                        unset($params[$key]);
                    }
                }
                return $this->redirect()->toRoute('moder/factories/params', array_replace($params, [
                    'action' => 'index'
                ]));
            }
        }

        $factoryTable = $this->getFactoryTable();

        $select = $factoryTable->select(true);

        $this->filterForm->setData($this->params()->fromRoute());

        if ($this->filterForm->isValid()) {
            $values = $this->filterForm->getData();

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

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage(10)
            ->setCurrentPageNumber($this->params('page'));

        $pictureTable = new Picture();

        $factories = [];
        foreach ($paginator->getCurrentItems() as $factory) {

            $pictures = $pictureTable->fetchAll([
                'factory_id = ?' => $factory->id,
                'type = ?'       => Picture::FACTORY_TYPE_ID
            ], 'id', 4);

            $factories[] = [
                'name'     => $factory->name,
                'pictures' => $pictures,
                'moderUrl' => $this->factoryModerUrl($factory->id),
            ];
        }

        return [
            'form'      => $this->filterForm,
            'paginator' => $paginator,
            'factories' => $factories
        ];
    }

    public function addAction()
    {
        if (!$this->user()->isAllowed('factory', 'add')) {
            return $this->forbiddenAction();
        }

        $factoryTable = $this->getFactoryTable();

        $this->addForm->setAttribute('action', $this->url()->fromRoute(null, [], [], true));

        $request = $this->getRequest();

        if ($request->isPost()) {
            $this->addForm->setData($this->params()->fromPost());
            if ($this->addForm->isValid()) {
                $values = $this->addForm->getData();

                $factory = $factoryTable->createRow([
                    'name'      => $values['name'],
                    'year_from' => strlen($values['year_from']) ? $values['year_from'] : null,
                    'year_to'   => strlen($values['year_to']) ? $values['year_to'] : null,
                ]);
                $factory->save();

                $this->log(sprintf(
                    'Создан новый завод `%s`',
                    $factory->name
                ), $factory);

                return $this->redirect()->toUrl($this->factoryModerUrl($factory->id));
            }
        }

        return [
            'form' => $this->addForm,
        ];
    }

    public function saveDescriptionAction()
    {
        $canEdit = $this->user()->isAllowed('factory', 'edit');
        if (!$canEdit) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();

        $request = $this->getRequest();
        if (!$request->isPost()) {
            return $this->forbiddenAction();
        }

        $factoryTable = $this->getFactoryTable();

        $factory = $factoryTable->find($this->params('factory_id'))->current();
        if (!$factory) {
            return $this->notfoundAction();
        }

        $this->descForm->setData($this->params()->fromPost());
        if ($this->descForm->isValid()) {

            $values = $this->descForm->getData();

            $text = $values['markdown'];

            if ($factory->text_id) {
                $this->textStorage->setText($factory->text_id, $text, $user->id);
            } elseif ($text) {
                $textId = $this->textStorage->createText($text, $user->id);
                $factory->text_id = $textId;
                $factory->save();
            }


            $this->log(sprintf(
                'Редактирование описания завода `%s`',
                $factory->name
            ), $factory);

            if ($factory->text_id) {
                $userIds = $this->textStorage->getTextUserIds($factory->text_id);

                $mModel = new Message();
                $userTable = new User();
                foreach ($userIds as $userId) {
                    if ($userId != $user->id) {
                        foreach ($userTable->find($userId) as $userRow) {

                            $uri = $this->hostManager->getUriByLanguage($notifyUser->language);

                            $message = sprintf(
                                $this->translate('pm/user-%s-edited-factory-description-%s-%s', 'default', $notifyUser->language),
                                $this->url()->fromRoute('users/user', [
                                    'action'  => 'user',
                                    'user_id' => $user->identity ? $user->identity : 'user' . $user->id
                                ], [
                                    'force_canonical' => true,
                                    'uri'             => $uri
                                ]),
                                $factory->name,
                                $this->factoryModerUrl($factory->id, true, $uri)
                            );

                            $mModel->send(null, $userRow->id, $message);
                        }
                    }
                }
            }
        }

        return $this->redirect()->toUrl($this->factoryModerUrl($factory->id));
    }
}
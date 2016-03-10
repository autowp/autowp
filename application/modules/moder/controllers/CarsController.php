<?php

use Application\Model\Message;
use Autowp\Filter\Filename\Safe;

class Moder_CarsController extends Zend_Controller_Action
{
    private $allowedLanguages = array('ru', 'en', 'it', 'fr', 'zh', 'de', 'es');

    /**
     * @var Car_Parent
     */
    private $carParentTable;

    /**
     * @var Brands_Cars
     */
    private $brandCarTable;

    /**
     * @var Brands
     */
    private $brandTable;

    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder') ) {
            return $this->_forward('forbidden', 'error', 'default');
        }
    }

    private function canMove(Cars_Row $car)
    {
        return $this->_helper->user()->isAllowed('car', 'move');
    }

    public function indexAction()
    {
        $categories = array('0' => '--') + $this->getCategoriesOptions(null, 0);

        $form = new Project_Form(array(
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
                    'decorators' => array(
                        'ViewHelper'
                    )
                )),
                array('text', 'no_name', array(
                    'label'      => 'Name (исключить)',
                    'decorators' => array(
                        'ViewHelper'
                    )
                )),
                array('Car_Spec', 'spec', array(
                    'decorators' => array(
                        'ViewHelper'
                    )
                )),
                array('text', 'from_year', array(
                    'label'      => 'From year',
                    'validators' => array(
                        'Number'
                    ),
                    'decorators' => array(
                        'ViewHelper'
                    )
                )),
                array('text', 'to_year', array(
                    'label'      => 'To year',
                    'validators' => array(
                        'Number'
                    ),
                    'decorators' => array(
                        'ViewHelper'
                    )
                )),
                array('text', 'description', array(
                    'label'      => 'Description',
                    'decorators' => array(
                        'ViewHelper'
                    )
                )),
                array('select', 'category', array(
                    'label'        => 'Category',
                    'multioptions' => $categories,
                    'decorators'   => array(
                        'ViewHelper'
                    )
                )),
                array('select', 'no_category', array(
                    'label'        => 'Category (исключить)',
                    'multioptions' => $categories,
                    'decorators'   => array(
                        'ViewHelper'
                    )
                )),
                array('checkbox', 'no_parent', array(
                    'label'        => 'Без родителей',
                    'decorators'   => array(
                        'ViewHelper'
                    )
                )),
                array('select', 'order', array(
                    'label'        => 'Сортировка',
                    'multioptions' => array(
                        0 => 'id asc',
                        1 => 'id desc',
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

        $cars = $this->_helper->catalogue()->getCarTable();

        $select = $cars->select(true);

        //if ($this->getParam())
        if ($form->isValid($this->_getAllParams())) {
            $values = $form->getValues();

            if ($values['name']) {
                $select->where('cars.caption like ?', '%' . $values['name'] . '%');
            }

            if ($values['no_name']) {
                $select->where('cars.caption not like ?', '%' . $values['no_name'] . '%');
            }

            if ($values['spec']) {
                $select->where('cars.spec_id = ?', $values['spec']);
            }

            if ($values['from_year']) {
                $select->where('cars.begin_year = ?', $values['from_year']);
            }

            if ($values['to_year']) {
                $select->where('cars.end_year = ?', $values['to_year']);
            }

            if ($values['description']) {
                $select->where('cars.description like ?', '%' . $values['description'] . '%');
            }

            if ($values['category']) {
                $select
                    ->join('category_car', 'cars.id=category_car.car_id', null)
                    ->join('category_parent', 'category_car.category_id=category_parent.category_id', null)
                    ->where('category_parent.parent_id = ?', $values['category']);
            }

            if ($values['no_category']) {

                $cpTable = new Category_Parent();

                $ids = $cpTable->getAdapter()->fetchCol(
                    $cpTable->getAdapter()->select()
                        ->from($cpTable->info('name'), 'category_id')
                        ->where('parent_id = ?', $values['no_category'])
                );

                if ($ids) {
                    $expr = $cars->getAdapter()->quoteInto(
                        'cars.id = no_category.car_id and no_category.category_id in (?)',
                        $ids
                    );
                    $select
                        ->joinLeft(array('no_category'    => 'category_car'), $expr, null)
                        ->where('no_category.car_id is null');
                }
            }

            if ($values['no_parent']) {
                $select
                    ->joinLeft('car_parent_cache', 'cars.id = car_parent_cache.car_id and cars.id <> car_parent_cache.parent_id', null)
                    ->joinLeft('brands_cars', 'cars.id = brands_cars.car_id', null)
                    ->where('car_parent_cache.car_id IS NULL')
                    ->where('brands_cars.car_id IS NULL');
            }

            switch ($values['order']) {
                case 0:
                    $select->order('id asc');
                    break;

                case 1:
                    $select->order('id desc');
                    break;
            }
        }

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(10)
            ->setCurrentPageNumber($this->getParam('page'));

        $this->view->assign(array(
            'form'      => $form,
            'paginator' => $paginator,
            'listData'  => $this->_helper->car->listData($paginator->getCurrentItems())
        ));
    }

    public function alphaAction()
    {
        $carTable = $this->_helper->catalogue()->getCarTable();
        $carAdapter = $carTable->getAdapter();
        $chars = $carAdapter->fetchCol(
            $carAdapter->select()
                ->distinct()
                ->from('cars', array('char' => new Zend_Db_Expr('UPPER(LEFT(caption, 1))')))
                ->order('char')
        );
        $this->view->assign(array(
            'chars' => $chars,
            'char'  => null
        ));

        $groups = array(
            'numbers' => array(),
            'english' => array(),
            'other'   => array()
        );

        foreach ($chars as $char) {
            if (preg_match('|^["0-9-]$|isu', $char)) {
                $groups['numbers'][] = $char;
            } elseif (preg_match('|^[A-Za-z]$|isu', $char)) {
                $groups['english'][] = $char;
            } else {
                $groups['other'][] = $char;
            }
        }

        $this->view->groups = $groups;

        if ($this->getParam('char')) {
            $char = mb_substr(trim($this->getParam('char')), 0, 1);

            $this->view->char = $char;
            $this->view->cars = $carTable->fetchAll(
                $carTable->select(true)
                     ->where('caption LIKE ?', $char.'%')
                     ->order(array('caption', 'begin_year', 'end_year'))
            );
        }
    }

    /**
     * @param Cars_Row $car
     * @return string
     */
    private function carModerUrl(Cars_Row $car, $full = false, $tab = null)
    {
        $url = $this->_helper->url->url(array(
            'module'     => 'moder',
            'controller' => 'cars',
            'action'     => 'car',
            'car_id'     => $car->id,
            'tab'        => $tab
        ), 'default', true);
        
        if ($full) {
            $url = $this->view->serverUrl($url);
        }
        return $url;
    }

    /**
     * @param Cars_Row $car
     * @return void
     */
    private function redirectToCar(Cars_Row $car, $tab = null)
    {
        return $this->_redirect($this->carModerUrl($car, true, $tab));
    }

    private function canEditMeta(Cars_Row $car)
    {
        return $this->_helper->user()->isAllowed('car', 'edit_meta');
    }

    public function carPicturesAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error', 'default');
        }

        // все картинки
        $table = $this->_helper->catalogue()->getPictureTable();
        $select = $table->select(true)
            ->where('pictures.car_id = ?', $car->id)
            ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
            ->order(array('pictures.status', 'pictures.id'));

        $picturesData = $this->_helper->pic->listData($select, array(
            'width' => 6
        ));

        $this->view->assign(array(
            'picturesData' => $picturesData,
        ));
    }

    private function getCategoriesOptions($parent, $deep = 0)
    {
        $cdTable = new Category();
        $cdlTable = new Category_Language();

        $language = $this->_helper->language();

        $filter = $parent ? array(
            'parent_id = ?'    => $parent->id
        ) : array(
            'parent_id IS NULL'
        );

        $rows = $cdTable->fetchAll($filter, 'name');

        $categories = array();

        foreach ($rows as $row) {
            $lRow = $cdlTable->fetchRow(array(
                'language = ?'    => $language,
                'category_id = ?' => $row->id
            ));
            $categories[$row->id] = str_repeat('…', $deep) . ($lRow ? $lRow->name : $row->name);

            $categories = $categories + $this->getCategoriesOptions($row, $deep+1);
        }

        return $categories;
    }

    private function getRandomPicture($car)
    {
        $pictures = $this->_helper->catalogue()->getPictureTable();

        $randomPicture = false;
        $statuses = array(
            Picture::STATUS_ACCEPTED,
            Picture::STATUS_NEW,
            Picture::STATUS_INBOX,
            Picture::STATUS_REMOVING
        );
        foreach ($statuses as $status) {
            $randomPicture = $pictures->fetchRow(
                $pictures->select(true)
                    ->where('type = ?', Picture::CAR_TYPE_ID)
                    ->where('car_id = ?', $car->id)
                    ->where('status = ?', $status)
                    ->order(new Zend_Db_Expr('RAND()'))
                    ->limit(1)
            );
            if ($randomPicture)
                break;
        }

        return $randomPicture;
    }

    public function saveDescAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $canEditMeta = $this->canEditMeta($car);
        
        $textForm = $this->getTextForm();
        
        if ($car->full_text_id) {
            $textStorage = $this->_helper->textStorage();
            $text = $textStorage->getText($car->full_text_id);
            $textForm->populate(array(
                'text' => $text
            ));
        }

        if ($canEditMeta) {
            
            $request = $this->getRequest();
            
            if ($request->isPost() && $textForm->isValid($request->getPost())) {
                $values = $textForm->getValues();
            
                $text = $values['text'];
            
                $textStorage = $this->_helper->textStorage();
            
                $user = $this->_helper->user()->get();
            
                if ($car->full_text_id) {
                    $textStorage->setText($car->full_text_id, $text, $user->id);
                } elseif ($text) {
                    $textId = $textStorage->createText($text, $user->id);
                    $car->full_text_id = $textId;
                    $car->save();
                }
            
            
                $this->_helper->log(sprintf(
                    'Редактирование полного описания автомобиля %s',
                    $this->view->htmlA($this->carModerUrl($car), $car->getFullName())
                ), $car);
            
                if ($car->full_text_id) {
                    $userIds = $textStorage->getTextUserIds($car->full_text_id);
                    $message = sprintf(
                        'Пользователь %s редактировал полное описание автомобиля %s ( %s )',
                        $this->view->serverUrl($user->getAboutUrl()),
                        $car->getFullName(),
                        $this->view->serverUrl($this->carModerUrl($car))
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

        }

        return $this->redirectToCar($car, 'desc');
    }
    
    private function getDescriptionForm()
    {
        return new Project_Form(array(
            'method' => Zend_Form::METHOD_POST,
            'action' => $this->_helper->url->url(array(
                'form' => 'car-edit-description' 
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
    
    private function getTextForm()
    {
        return new Project_Form(array(
            'method' => Zend_Form::METHOD_POST,
            'action' => $this->_helper->url->url(array(
                'action' => 'save-desc'
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
                )],
            ]
        ));
    }

    public function carAction()
    {
        $carTable = $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $pictures = $this->_helper->catalogue()->getPictureTable();


        $canEditMeta = $this->canEditMeta($car);

        if ($canEditMeta) {

            $carParentTable = $this->getCarParentTable();
            $haveChilds = (bool)$carParentTable->fetchRow(array(
                'parent_id = ?' => $car->id
            ));

            $isGroupDisabled = $car->is_group && $haveChilds;

            $specTable = new Spec();
            $specOptions = $this->loadSpecs($specTable, null, 0);

            $inheritedSpec = null;
            if ($car->spec_inherit) {
                if ($car->spec_id) {
                    $specRow = $specTable->find($car->spec_id)->current();
                    if ($specRow) {
                        $inheritedSpec = $specRow->short_name;
                    }
                }
            } else {
                $db = $carTable->getAdapter();
                $avgSpecId = $db->fetchOne(
                    $db->select()
                        ->from($carTable->info('name'), 'AVG(spec_id)')
                        ->join('car_parent', 'cars.id = car_parent.parent_id', null)
                        ->where('car_parent.car_id = ?', $car->id)
                );
                if ($avgSpecId) {
                    $specRow = $specTable->find($avgSpecId)->current();
                    if ($specRow) {
                        $inheritedSpec = $specRow->short_name;
                    }
                }
            }

            $form = new Application_Form_Moder_Car_Edit_Meta(array(
                'inheritedCarType'   => $car->car_type_inherit ? $car->car_type_id : null,
                'inheritedIsConcept' => $car->is_concept_inherit ? $car->is_concept : null,
                'isGroupDisabled'    => $isGroupDisabled,
                'specOptions'        => array_replace(array('' => '-'), $specOptions),
                'inheritedSpec'      => $inheritedSpec,
                'action'             => $this->_helper->url->url(array(
                    'module'      => 'moder',
                    'comtroller'  => 'cars',
                    'action'      => 'car',
                    'car_id'      => $car->id,
                    'form'        => 'car-edit-meta'
                )),
            ));

            $oldData = $data = $car->toArray();
            if (!is_null($data['today'])) {
                switch ($data['today']) {
                    case 0:
                        $data['today'] = 1;
                        break;

                    case 1:
                        $data['today'] = 2;
                        break;
                }
            }

            if ($data['car_type_inherit']) {
                $data['car_type_id'] = 'inherited';
            }
            unset($data['car_type_inherit']);

            if ($data['is_concept_inherit']) {
                $data['is_concept'] = 'inherited';
            }
            unset($data['is_concept_inherit']);

            if ($data['spec_inherit']) {
                $data['spec_id'] = 'inherited';
            }
            unset($data['spec_inherit']);

            $form->populate($data);

            $request = $this->getRequest();

            if ($request->isPost() && $this->getParam('form') == 'car-edit-meta' && $form->isValid($request->getPost())) {

                $user = $this->_helper->user()->get();
                $ucsTable = new User_Car_Subscribe();
                $ucsTable->subscribe($user, $car);

                $values = $form->getValues();

                if ($haveChilds) {
                    $values['is_group'] = 1;
                }

                $car->setFromArray($this->prepareCarMetaToSave($values))->save();

                $carTable->updateInteritance($car);

                $newData = $car->toArray();

                $fields = array(
                    'caption'          => array('str', 'название автомобиля с "%s" на "%s"'),
                    'body'             => array('str', 'номер кузова с "%s" на "%s"'),
                    'begin_year'       => array('int', 'год начала выпуска c "%s" на "%s"'),
                    'begin_month'      => array('int', 'месяц начала выпуска с "%s" на "%s"'),
                    'end_year'         => array('int', 'год окончания выпуска с "%s" на "%s"'),
                    'end_month'        => array('int', 'месяц окончания выпуска с "%s" на "%s"'),
                    'today'            => array('bool', 'выпуск в наше время с "%s" на "%s"'),
                    'produced'         => array('int', 'количество выпущенных единиц с "%s" на "%s"'),
                    'produced_exactly' => array('bool', 'точность количества выпущенных единиц с "%s" на "%s"'),
                    'is_concept'       => array('bool', 'флаг "концепт" с "%s" на "%s"'),
                    'is_group'         => array('bool', 'флаг "группа" с "%s" на "%s"'),
                    'car_type_id'      => array('car_type_id', 'тип кузова с "%s" на "%s"'),
                    'begin_model_year' => array('int', 'модельный год начала выпуска c "%s" на "%s"'),
                    'end_model_year'   => array('int', 'модельный год окончания выпуска c "%s" на "%s"'),
                    'spec_id'          => array('spec_id', 'Spec с "%s" на "%s"'),
                );

                $changes = array();
                foreach ($fields as $field => $info) {
                    switch ($info[0]) {
                        case 'int':
                            $old = is_null($oldData[$field]) ? null : (int)$oldData[$field];
                            $new = is_null($newData[$field]) ? null : (int)$newData[$field];
                            if ($old !== $new)
                                $changes[] = sprintf($info[1], $old, $new);
                            break;
                        case 'str':
                            $old = is_null($oldData[$field]) ? null : (string)$oldData[$field];
                            $new = is_null($newData[$field]) ? null : (string)$newData[$field];
                            if ($old !== $new)
                                $changes[] = sprintf($info[1], $old, $new);
                            break;
                        case 'bool':
                            $old = is_null($oldData[$field]) ? null : ($oldData[$field] ? 'да' : 'нет');
                            $new = is_null($newData[$field]) ? null : ($newData[$field] ? 'да' : 'нет');
                            if ($old !== $new)
                                $changes[] = sprintf($info[1], $old, $new);
                            break;

                        case 'spec_id':
                            $old = $oldData[$field];
                            $new = $newData[$field];
                            if ($old !== $new) {
                                $old = $specTable->find($old)->current();
                                $new = $specTable->find($new)->current();
                                $changes[] = sprintf($info[1], $old ? $old->short_name : '-', $new ? $new->short_name : '-');
                            }
                            break;

                        case 'car_type_id':
                            $carTypeTable = new Car_Types();
                            $old = $oldData[$field];
                            $new = $newData[$field];
                            if ($old !== $new) {
                                $old = $carTypeTable->find($old)->current();
                                $new = $carTypeTable->find($new)->current();
                                $changes[] = sprintf($info[1], $old ? $old->name : '-', $new ? $new->name : '-');
                            }
                            break;
                    }
                }

                $car->updateOrderCache();

                $message = sprintf(
                    'Редактирование мета-информации автомобиля %s',
                    $this->view->htmlA($this->carModerUrl($car), $car->getFullName()).
                    ( count($changes) ? '<p>'.implode('<br />', $changes).'</p>' : '')
                );
                $this->_helper->log($message, $car);
                
                $mModel = new Message();

                $user = $this->_helper->user()->get();
                $message = 'Пользователь http://www.autowp.ru' . $user->getAboutUrl() . ' редактировал информацию об автомобиле '.$car->getFullName().' ('.$this->carModerUrl($car, true).")\n".
                           ( count($changes) ? implode("\n", $changes) : '');
                foreach ($ucsTable->getCarSubscribers($car) as $subscriber) {
                    if ($subscriber && ($subscriber->id != $user->id)) {
                        $mModel->send(null, $subscriber->id, $message);
                    }
                }

                return $this->redirectToCar($car, 'meta');
            }
            
            
            $descriptionForm = $this->getDescriptionForm();
            
            if ($car->text_id) {
                $textStorage = $this->_helper->textStorage();
                $description = $textStorage->getText($car->text_id);
                $descriptionForm->populate(array(
                    'markdown' => $description
                ));
            }
            
            if ($request->isPost() && $this->getParam('form') == 'car-edit-description' && $descriptionForm->isValid($request->getPost())) {
                $values = $descriptionForm->getValues();
                
                $text = $values['markdown'];
                
                $textStorage = $this->_helper->textStorage();
                
                $user = $this->_helper->user()->get();
                
                if ($car->text_id) {
                    $textStorage->setText($car->text_id, $text, $user->id);
                } elseif ($text) {
                    $textId = $textStorage->createText($text, $user->id);
                    $car->text_id = $textId;
                    $car->save();
                }
                
                
                $this->_helper->log(sprintf(
                    'Редактирование описания автомобиля %s',
                    $this->view->htmlA($this->carModerUrl($car), $car->getFullName())
                ), $car);
                
                if ($car->text_id) {
                    $userIds = $textStorage->getTextUserIds($car->text_id);
                    $message = sprintf(
                        'Пользователь %s редактировал описание автомобиля %s ( %s )',
                        $this->view->serverUrl($user->getAboutUrl()),
                        $car->getFullName(),
                        $this->view->serverUrl($this->carModerUrl($car))
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
                
                return $this->redirectToCar($car, 'meta');
            }
            
            $textForm = $this->getTextForm();
            
            if ($car->full_text_id) {
                $textStorage = $this->_helper->textStorage();
                $text = $textStorage->getText($car->full_text_id);
                $textForm->populate(array(
                    'text' => $text
                ));
            }
            
            $this->view->assign([
                'textForm'             => $textForm,
                'descriptionForm'      => $descriptionForm,
                'formModerCarEditMeta' => $form
            ]);
        }


        // количество картинок
        $picturesCount = $pictures->getAdapter()->fetchOne(
            $pictures->getAdapter()->select()
                ->from('pictures', array(new Zend_Db_Expr('COUNT(1)')))
                ->where('type = ?', Picture::CAR_TYPE_ID)
                ->where('car_id = ?', $car->id)
        );

        $ucsTable = new User_Car_Subscribe();

        $user = $this->_helper->user()->get();
        $ucsRow = $ucsTable->fetchRow(array(
            'user_id = ?' => $user->id,
            'car_id = ?'  => $car->id
        ));

        $db = $carTable->getAdapter();

        $categoriesCount = $db->fetchOne(
            $db->select()
                ->from('category_car', 'count(1)')
                ->where('car_id = ?', $car->id)
        );

        $carLangTable = new Car_Language();
        $langNameCount = $carLangTable->getAdapter()->fetchOne(
            $carLangTable->getAdapter()->select()
                ->from('car_language', 'count(1)')
                ->where('car_id = ?', $car->id)
        );

        $twinsGroupsCount = $db->fetchOne(
            $db->select()
                ->from('twins_groups_cars', 'count(1)')
                ->where('car_id = ?', $car->id)
        );

        $catalogueLinksCount = $db->fetchOne(
            $db->select()
                ->from('car_parent', 'count(1)')
                ->where('car_id = ?', $car->id)
        );
        $catalogueLinksCount += $db->fetchOne(
            $db->select()
                ->from('car_parent', 'count(1)')
                ->where('parent_id = ?', $car->id)
        );
        $catalogueLinksCount += $db->fetchOne(
            $db->select()
                ->from('brands_cars', 'count(1)')
                ->where('car_id = ?', $car->id)
        );

        $factoriesCount = $db->fetchOne(
            $db->select()
                ->from('factory_car', 'count(1)')
                ->where('car_id = ?', $car->id)
        );

        $tabs = array(
            'meta' => array(
                'icon'  => 'glyphicon glyphicon-pencil',
                'title' => 'Мета',
                'count' => 0,
            ),
            'name' => array(
                'icon'      => 'glyphicon glyphicon-align-left',
                'title'     => 'Название',
                'data-load' => $this->_helper->url->url(array(
                    'action' => 'car-name'
                )),
                'count' => $langNameCount,
            ),
            'desc' => array(
                'icon'  => 'glyphicon glyphicon-align-left',
                'title' => 'Описание',
                'count' => (bool)$car->full_text_id,
            ),
            'catalogue' => array(
                'icon'      => false,
                'title'     => 'Каталог',
                'data-load' => $this->_helper->url->url(array(
                    'action' => 'car-catalogue'
                )),
                'count' => $catalogueLinksCount,
            ),
            'tree' => array(
                'icon'      => 'fa fa-tree',
                'title'     => 'Дерево',
                'data-load' => $this->_helper->url->url(array(
                    'action' => 'car-tree'
                )),
                'count' => 0,
            ),
            'categories' => array(
                'icon'      => 'glyphicon glyphicon-tag',
                'title'     => 'Категории',
                'data-load' => $this->_helper->url->url(array(
                    'action' => 'car-categories'
                )),
                'count' => $categoriesCount,
            ),
            'twins' => array(
                'icon'      => 'glyphicon glyphicon-adjust',
                'title'     => 'Близнецы',
                'data-load' => $this->_helper->url->url(array(
                    'action' => 'car-twins'
                )),
                'count' => $twinsGroupsCount,
            ),
            'factories' => array(
                'icon'      => 'fa fa-cogs',
                'title'     => 'Заводы',
                'data-load' => $this->_helper->url->url(array(
                    'action' => 'car-factories'
                )),
                'count' => $factoriesCount,
            ),
            'pictures' => array(
                'icon'      => 'glyphicon glyphicon-th',
                'title'     => 'Картинки',
                'data-load' => $this->_helper->url->url(array(
                    'action' => 'car-pictures'
                )),
                'count' => $picturesCount,
            ),
        );

        if ($this->_helper->user()->get()->id == 1) {
            $tabs['modifications'] = array(
                'icon'      => 'glyphicon glyphicon-th',
                'title'     => 'Модификации',
                'data-load' => $this->_helper->url->url(array(
                    'action' => 'car-modifications'
                )),
                'count' => 0
            );
        }

        $currentTab = $this->getParam('tab', 'meta');
        foreach ($tabs as $id => &$tab) {
            $tab['active'] = $id == $currentTab;
        }

        $specService = new Application_Service_Specifications();
        $specsCount = $specService->getSpecsCount(1, $car->id);

        $this->view->assign(array(
            'picturesCount'  => $picturesCount,
            'canEditMeta'    => $canEditMeta,
            'car'            => $car,
            'randomPicture'  => $this->getRandomPicture($car),
            'subscribed'     => (bool)$ucsRow,
            'tabs'           => $tabs,
            'specsCount'     => $specsCount
        ));
    }

    public function deleteCarFromBrandAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car)
            return $this->_forward('notfound', 'error');

        $canMove = $this->canMove($car);
        if (!$canMove)
            return $this->_forward('forbidden', 'error');

        $brands = $this->getBrandTable();

        $brand = $brands->find($this->getParam('brand_id'))->current();
        if (!$brand)
            return $this->_forward('notfound', 'error');

        $sql = 'DELETE FROM brands_cars WHERE (brand_id = ?) AND (car_id = ?) LIMIT 1';
        $brands->getAdapter()->query($sql, array($brand->id, $car->id));

        $user = $this->_helper->user()->get();
        $ucsTable = new User_Car_Subscribe();
        $ucsTable->subscribe($user, $car);

        $brand->updatePicturesCache();
        $brand->RefreshPicturesCount();
        $brand->RefreshActivePicturesCount();

        // обновляем кэши близнецов
        $car->updateRelatedTwinsGroupsCount();

        $message = sprintf(
            'Автомобиль %s отсоединен от бренда %s',
            $this->view->htmlA($this->carModerUrl($car), $car->getFullName()),
            $brand->caption
        );
        $this->_helper->log($message, array($brand, $car));

        return $this->redirectToCar($car, 'catalogue');
    }

    public function carSelectBrandAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();
        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car)
            return $this->_forward('notfound', 'error');

        $canMove = $this->canMove($car);
        if (!$canMove)
            throw new Exception('Access denied');

        $this->view->car = $car;

        $brand = null;


        $brands = $this->getBrandTable();
        $brand = $brands->find($this->getParam('brand_id'))->current();
        if ($brand) {
            return $this->_forward('add-car-to-brand');
        } else {
            $this->view->brands = $brands->fetchAll(
                $brands->select()
                       ->order(array('brands.position', 'brands.caption'))
            );
        }
    }

    public function addCarToBrandAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $canMove = $this->canMove($car);
        if (!$canMove) {
            return $this->_forward('forbidden', 'error');
        }

        $brands = $this->getBrandTable();

        $brand = $brands->find($this->getParam('brand_id'))->current();
        if (!$brand) {
            return $this->_forward('notfound', 'error');
        }

        foreach ($car->findBrandsViaBrands_Cars() as $iBrand) {
            if ($iBrand->id == $brand->id) {
                throw new Exception('Автомобиль уже связан с брендом '.$iBrand->caption);
            }
        }

        $filter = new Safe();
        $catnameTemplate = $filter->filter(trim(str_replace($brand->caption, '', $car->caption)));

        $brandsCars = $this->getBrandCarTable();

        $i = 0;
        do {

            $catname = $catnameTemplate . ($i ? '_' . $i : '');

            $exists = (bool)$brandsCars->fetchRow(array(
                'brand_id = ?' => $brand->id,
                'catname = ?'  => $catname
            ));

            $i++;

        } while ($exists);


        $brandsCars->insert(array(
            'brand_id' => $brand->id,
            'car_id'   => $car->id,
            'type'     => Brands_Cars::TYPE_DEFAULT,
            'catname'  => $catname ? $catname : 'car' . $car->id
        ));

        $user = $this->_helper->user()->get();
        $ucsTable = new User_Car_Subscribe();
        $ucsTable->subscribe($user, $car);

        $brand->updatePicturesCache();
        $brand->refreshPicturesCount();
        $brand->refreshActivePicturesCount();

        // обновляем кэши близнецов
        $car->updateRelatedTwinsGroupsCount();

        $message = sprintf(
            'Автомобиль %s добавлен к бренду %s',
            $this->view->htmlA($this->carModerUrl($car), $car->getFullName()),
            $brand->caption
        );
        $this->_helper->log($message, array($brand, $car));

        return $this->redirectToCar($car, 'catalogue');
    }

    public function setBrandCarTypeAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $canMove = $this->canMove($car);
        if (!$canMove) {
            return $this->_forward('forbidden', 'error');
        }

        $brandTable = $this->getBrandTable();
        $brand = $brandTable->find($this->getParam('brand_id'))->current();
        if (!$brand) {
            return $this->_forward('notfound', 'error');
        }

        $type = (int)$this->getParam('type');

        $brandCarTable = new Brand_Car();
        $brandCarRow = $brandCarTable->fetchRow(array(
            'brand_id = ?' => $brand->id,
            'car_id = ?'   => $car->id
        ));

        if (!$brandCarRow) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $brandCarRow->type = $type;
        $brandCarRow->save();

        if ($this->getRequest()->isXmlHttpRequest()) {
            return $this->_helper->json(array(
                'ok' => true
            ));
        } else {
            return $this->_redirect($this->carModerUrl($car));
        }
    }

    public function setBrandCarCatnameAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $canMove = $this->canMove($car);
        if (!$canMove) {
            return $this->_forward('forbidden', 'error');
        }

        $brandTable = $this->getBrandTable();
        $brand = $brandTable->find($this->getParam('brand_id'))->current();
        if (!$brand) {
            return $this->_forward('notfound', 'error');
        }

        $brandCarTable = new Brand_Car();
        $brandCarRow = $brandCarTable->fetchRow(array(
            'brand_id = ?' => $brand->id,
            'car_id = ?'   => $car->id
        ));

        if (!$brandCarRow) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $form = new Project_Form(array(
            'elements' => array(
                array('text', 'catname', array(
                    'filters'    => array('StringTrim', 'StringToLower', 'Filename_Safe'),
                    'validators' => array(
                        array('StringLength', true, array(
                            'min' => 1,
                            'max' => 50
                        )),
                        array('Callback', true, function($value) use ($brand, $car, $brandCarTable) {
                            $brandCarRow = $brandCarTable->fetchRow(array(
                                'brand_id = ?' => $brand->id,
                                'catname = ?'  => $value,
                                'car_id <> ?'  => $car->id
                            ));

                            return !$brandCarRow;
                        })
                    )
                ))
            )
        ));

        $ok = false;
        if ($form->isValid($this->getRequest()->getPost())) {
            $values = $form->getValues();
            $brandCarRow->catname = $values['catname'] ? $values['catname'] : $car->id;
            $brandCarRow->save();

            $ok = true;
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            return $this->_helper->json(array(
                'ok' => $ok,
                'messages' => $form->getMessages()
            ));
        } else {
            return $this->_redirect($this->carModerUrl($car, false, 'catalogue'));
        }
    }

    public function carSelectTwinsGroupAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();
        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $canEditTwins = $this->_helper->user()->isAllowed('twins', 'edit');
        if (!$canEditTwins) {
            return $this->_forward('forbidden', 'error');
        }

        $this->view->car = $car;

        $this->view->brand = $brand = null;

        $twinsGroups = new Twins_Groups();

        $twinsGroup = $twinsGroups->find($this->getParam('twins_group_id'))->current();

        if ($twinsGroup) {
            $twinsGroupsCars = new Twins_Groups_Cars();
            $twinsGroupsCars->insert(array(
                'twins_group_id' => $twinsGroup->id,
                'car_id' => $car->id
            ));

            // обновляем кэши
            $car->updateRelatedTwinsGroupsCount();

            $this->_helper->log(sprintf(
                'Автомобиль %s добавлен в группу близнецов %s',
                $this->view->htmlA($this->carModerUrl($car), $car->getFullName()),
                $this->view->escape($twinsGroup->name)
            ), array($twinsGroup, $car));

            return $this->redirectToCar($car, 'twins');

        } else {

            $brands = $this->getBrandTable();
            $brand = $brands->find($this->getParam('brand_id'))->current();
            if ($brand) {
                $this->view->brand = $brand;

                $this->view->groups = $twinsGroups->fetchAll(
                    $twinsGroups
                        ->select(true)
                        ->join('twins_groups_cars', 'twins_groups.id = twins_groups_cars.twins_group_id', null)
                        ->join('car_parent_cache', 'twins_groups_cars.car_id = car_parent_cache.car_id', null)
                        ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                        ->where('brands_cars.brand_id = ?', $brand->id)
                        ->group('twins_groups.id')
                        ->order('twins_groups.name')
                );

            } else {
                $this->view->brands = $brands->fetchAll(
                    $brands->select(true)
                        ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                        ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                        ->join('twins_groups_cars', 'car_parent_cache.car_id = twins_groups_cars.car_id', null)
                        ->group('brands.id')
                        ->order(array('brands.position', 'brands.caption'))
                );
            }
        }

        $form = new Application_Form_Moder_Twins_Group_Add(array(
            'action' => $this->_helper->url->url()
        ));
        $request = $this->getRequest();
        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();
            $values['add_datetime'] = new Zend_Db_Expr('NOW()');

            $id = $twinsGroups->insert($values);

            return $this->_forward('car-select-twins-group', 'cars', 'moder', array(
                'car_id'         => $car->id,
                'twins_group_id' => $id
            ));
        }
        $this->view->formTwinsGroupAdd = $form;

        $this->view->canEditTwins = $canEditTwins;
    }

    public function carRemoveFromTwinsGroupAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();
        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car)
            return $this->_forward('notfound', 'error');

        $canEditTwins = $this->_helper->user()->isAllowed('twins', 'edit');
        if (!$canEditTwins)
            throw new Exception('Access denied');

        $twinsGroups = new Twins_Groups();
        $twinsGroup = $twinsGroups->find($this->getParam('twins_group_id'))->current();

        if (!$twinsGroup)
            return $this->_forward('notfound', 'error');

        $twinsGroupsCars = new Twins_Groups_Cars();
        $twinsGroupCar = $twinsGroupsCars->fetchRow(
            $twinsGroupsCars
                ->select()
                ->where('car_id = ?', $car->id)
                ->where('twins_group_id = ?', $twinsGroup->id)
        );

        $twinsGroupCar->delete();

        // удаляем пустую группу
        if ($twinsGroup->findCarsViaTwins_Groups_Cars()->count() <= 0) {
            $twinsGroup->delete();
        }

        // обновляем кэши
        $car->updateRelatedTwinsGroupsCount();

        return $this->redirectToCar($car, 'twins');
    }

    public function carSelectFactoryAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();
        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $canEditFactory = $this->_helper->user()->isAllowed('factory', 'edit');
        if (!$canEditFactory) {
            return $this->_forward('forbidden', 'error');
        }

        $this->view->car = $car;

        $factoryTable = new Factory();

        $factory = $factoryTable->find($this->getParam('factory_id'))->current();

        if ($factory) {
            $factoryCarTable = new Factory_Car();
            $factoryCarTable->insert(array(
                'factory_id' => $factory->id,
                'car_id'     => $car->id
            ));

            $this->_helper->log(sprintf(
                'Автомобиль %s добавлен к заводу %s',
                $this->view->htmlA($this->carModerUrl($car), $car->getFullName()),
                $this->view->escape($factory->name)
            ), array($factory, $car));

            return $this->redirectToCar($car, 'factories');

        } else {

            $this->view->factories = $factoryTable->fetchAll(
                $factoryTable->select(true)
                    ->order('factory.name')
            );

        }
    }

    public function carRemoveFromFactoryAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();
        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $canEditFactory = $this->_helper->user()->isAllowed('factory', 'edit');
        if (!$canEditFactory) {
            return $this->_forward('forbidden', 'error');
        }

        $factoryTable = new Factory();
        $factory = $factoryTable->find($this->getParam('factory_id'))->current();

        if (!$factory) {
            return $this->_forward('notfound', 'error');
        }

        $factoryCarTable = new Factory_Car();
        $factoryCar = $factoryCarTable->fetchRow(
            $factoryCarTable->select(true)
                ->where('car_id = ?', $car->id)
                ->where('factory_id = ?', $factory->id)
        );

        if ($factoryCar) {
            $factoryCar->delete();
        }

        return $this->redirectToCar($car, 'factories');
    }

    private function getCategoriesArray($parent, $selection, $deep = 0)
    {
        $cdTable = new Category();
        $cdlTable = new Category_Language();

        $language = $this->_helper->language();

        $filter = $parent ? array(
            'parent_id = ?' => $parent->id
        ) : array(
            'parent_id IS NULL'
        );

        $rows = $cdTable->fetchAll($filter, 'name');

        $categories = array();

        foreach ($rows as $row) {
            $lRow = $cdlTable->fetchRow(array(
                'language = ?'    => $language,
                'category_id = ?' => $row->id
            ));

            $childs = $this->getCategoriesArray($row, $selection, $deep+1);

            $inherited = false;
            $active = $checked = array_key_exists($row->id, $selection);
            if ($checked) {
                $inherited = $selection[$row->id]['inherited'];
            } else {
                foreach ($childs as $child) {
                    if ($child['active']) {
                        $active = true;
                        break;
                    }
                }
            }

            $category = array(
                'id'            => $row->id,
                'name'          => $lRow ? $lRow->name : $row->name,
                'categories'    => $childs,
                'checked'       => $checked,
                'active'        => $active,
                'inherited'     => $inherited,
                'user'          => $checked ? $selection[$row->id]['user'] : false,
                'inheritedFrom' => $checked ? $selection[$row->id]['inheritedFrom'] : array()
            );

            $categories[] = $category;
        }

        return $categories;
    }

    public function carCategoriesSaveAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car)
            return $this->_forward('notfound', 'error');

        $canEditMeta = $this->canEditMeta($car);

        if (!$canEditMeta) {
            return $this->_forbidden('forbidden', 'error', 'default');
        }

        if (!$this->getRequest()->isPost()) {
            return $this->_forbidden('forbidden', 'error', 'default');
        }

        $cTable = new Category();
        $ccTable = new Category_Car();

        $categories = $cTable->find($this->getParam('category'));

        // insert new
        $insertedNames = array();
        $ids = array();
        foreach ($categories as $category) {
            $ids[] = $category->id;

            $ccRow = $ccTable->fetchRow(array(
                'category_id = ?' => $category->id,
                'car_id = ?'      => $car->id
            ));
            if (!$ccRow) {
                $user = $this->_helper->user()->get();
                $ccRow = $ccTable->fetchNew();
                $ccRow->setFromArray(array(
                    'car_id'       => $car->id,
                    'category_id'  => $category->id,
                    'add_datetime' => new Zend_Db_Expr('NOW()'),
                    'user_id'      => $user->id
                ));
                $ccRow->save();

                $insertedNames[] = $category->name;
            }
        }

        // delete old
        $deletedNames = array();
        $notify = array();
        $filter = array(
            'car_id = ?' => $car->id,
        );
        if (count($ids)) {
            $filter['category_id NOT IN (?)'] = $ids;
        }
        foreach ($ccTable->fetchAll($filter) as $oldCc) {
            $oldCategory = $oldCc->findParentCategory();
            if ($oldCategory) {
                $deletedNames[] = $oldCategory->name;

                if ($oldUser = $oldCc->findParentUsers()) {
                    $user = $this->_helper->user()->get();
                    if ($oldUser->id != $user->id) {
                        $notify[$oldUser->id][] = $oldCategory;
                    }
                }
            }

            $oldCc->delete();
        }

        if ($deletedNames || $insertedNames) {
            $logText =  'Изменение категорий автомобиля ' . $car->getFullName() . '. ' .
                        ($deletedNames ? 'Удалено: ' . implode(', ', $deletedNames) . '. ' : '') .
                        ($insertedNames ? 'Добавлено: ' . implode(', ', $insertedNames) . '. ' : '');
            $this->_helper->log($this->view->escape($logText), $car);
        }
        
        $mModel = new Message();

        $users = new Users();
        foreach ($notify as $userId => $categories) {
            $notifyUser = $users->find($userId)->current();

            $categoryNames = array();
            foreach ($categories as $category) {
                $categoryNames[] = $category->name . ' (' . $this->view->serverUrl($this->_helper->url->url(array(
                    'controller'       => 'category',
                    'action'           => 'category',
                    'category_catname' => $category->catname
                ), 'category', true)) .')';
            }

            if ($notifyUser && count($categoryNames)) {
                $user = $this->_helper->user()->get();
                $message =    'Пользователь http://www.autowp.ru' . $user->getAboutUrl() . ' отменил вашу привязку автомобиля ' . $car->getFullName().' ('.$this->carModerUrl($car, true).') ' .
                            (count($categoryNames) > 1 ? 'к категориям ' : 'к категории ') . implode(', ', $categoryNames);
                $mModel->send(null, $notifyUser->id, $message);
            }
        }

        return $this->_helper->json(array(
            'ok' => true,
            'n'  => count($notify),
            'd'  => $deletedNames,
            'f'  => $filter
        ));
    }

    public function carCategoriesAction()
    {
        $carTable = $this->_helper->catalogue()->getCarTable();

        $car = $carTable->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $users = new Users();


        $canEditMeta = $this->canEditMeta($car);

        if (!$canEditMeta) {
            return $this->_forbidden('forbidden', 'error', 'default');
        }

        $db = $carTable->getAdapter();

        $selected = $db->fetchPairs(
            $db->select()
                ->from('category_car', array('category_id', 'user_id'))
                ->where('car_id = ?', $car->id)
        );

        $inherited = $db->fetchCol(
            $db->select()
                ->from('category_car', array('category_id'))
                ->join('car_parent_cache', 'category_car.car_id = car_parent_cache.parent_id', null)
                ->where('car_parent_cache.car_id = ?', $car->id)
                //->where('car_parent_cache.diff > 0')
        );

        $selection = array();

        foreach ($selected as $id => $value) {
            $selection[$id] = array(
                'inherited'     => false,
                'inheritedFrom' => array(),
                'user'          => $users->find($value)->current()
            );
        }

        foreach ($inherited as $id) {
            if (!isset($selection[$id])) {

                $carRows = $carTable->fetchAll(
                    $carTable->select(true)
                        ->join('car_parent_cache', 'cars.id = car_parent_cache.parent_id', null)
                        ->where('car_parent_cache.car_id = ?', $car->id)
                        ->join('category_car', 'car_parent_cache.parent_id = category_car.car_id', null)
                        ->where('category_car.category_id = ?', $id)
                );

                $inheritedFrom = array();
                foreach ($carRows as $carRow) {
                    $inheritedFrom[] = array(
                        'name' => $carRow->getFullName(),
                        'url'  => $this->carModerUrl($carRow)
                    );
                }

                $selection[$id] = array(
                    'inherited'     => true,
                    'inheritedFrom' => $inheritedFrom,
                    'user'          => null
                );
            }
        }

        $categories = $this->getCategoriesArray(null, $selection, 0);

        $this->view->assign(array(
            'canEditMeta' => $canEditMeta,
            'car'         => $car,
            'categories'  => $categories
        ));
    }

    public function subscribeAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_forbidden();
        }

        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $user = $this->_helper->user()->get();
        $ucsTable = new User_Car_Subscribe();
        $ucsTable->subscribe($user, $car);

        return $this->_helper->json(array(
            'ok' => true
        ));
    }

    public function unsubscribeAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_forbidden();
        }

        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $user = $this->_helper->user()->get();
        $ucsTable = new User_Car_Subscribe();
        $ucsTable->unsubscribe($user, $car);

        return $this->_helper->json(array(
            'ok' => true
        ));
    }

    public function saveNameAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_forbidden();
        }

        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $canEditMeta = $this->canEditMeta($car);

        if (!$canEditMeta) {
            return $this->_forbidden();
        }

        $carLangTable = new Car_Language();

        $changes = array();

        foreach ($this->allowedLanguages as $lang) {
            $value = trim($this->getParam($lang));

            $row = $carLangTable->fetchRow(array(
                'car_id = ?'   => $car->id,
                'language = ?' => $lang
            ));

            if ($value) {

                if (!$row) {
                    $row = $carLangTable->createRow(array(
                        'car_id'   => $car->id,
                        'language' => $lang
                    ));
                }

                if ($row->name != $value) {
                    $changes[] = 'Установлено ' . strtoupper($lang) . ': ' . $value;
                }

                $row->name = $value;
                $row->save();

            } else {

                if ($row) {
                    $changes[] = 'Удалено ' . strtoupper($lang) . ': ' . $row->name;
                    $row->delete();
                }

            }
        }

        if ($changes) {
            foreach ($changes as &$change) {
                $change = $this->view->escape($change);
            }
            unset($change); // prevent future bugs
            $message = sprintf(
                'Редактирование названий автомобиля %s',
                $this->view->htmlA($this->carModerUrl($car), $car->getFullName()).
                ( count($changes) ? '<p>'.implode('<br />', $changes).'</p>' : '')
            );
            $this->_helper->log($message, $car);
        }

        return $this->redirectToCar($car, 'name');
    }

    public function treeAction()
    {
        $carTable = $this->_helper->catalogue()->getCarTable();

        $car = $carTable->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $canEditMeta = $this->canEditMeta($car);

        if (!$canEditMeta) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $parents = $carTable->fetchAll(
            $carTable->select(true)
                ->join('car_parent', 'cars.id = car_parent.parent_id', null)
                ->where('car_parent.car_id = ?', $car->id)
                ->order($this->_helper->catalogue()->carsOrdering())
        );

        $allParents = $carTable->fetchAll(
            $carTable->select(true)
                ->join('car_parent_cache', 'cars.id = car_parent_cache.parent_id', null)
                ->where('car_parent_cache.car_id = ?', $car->id)
                ->order($this->_helper->catalogue()->carsOrdering())
        );

        $allChilds = $carTable->fetchAll(
            $carTable->select(true)
                ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                ->where('car_parent_cache.parent_id = ?', $car->id)
                ->order($this->_helper->catalogue()->carsOrdering())
        );

        $graphItems = array();
        foreach ($allParents as $c) {
            $graphItems[$c->id] = $c->getFullName();
        }
        foreach ($allChilds as $c) {
            $graphItems[$c->id] = $c->getFullName();
        }

        $graphItemsIds = array_keys($graphItems);

        $carParentTable = $this->getCarParentTable();

        $carParentRows = $carParentTable->fetchAll(
            $carParentTable->select(true)
                ->where('car_id in (?)', $graphItemsIds)
                ->where('parent_id in (?)', $graphItemsIds)
        );
        $graphLinks = array();
        foreach ($carParentRows as $carParentRow) {
            $graphLinks[] = array(
                'car_id'    => $carParentRow->car_id,
                'parent_id' => $carParentRow->parent_id
            );
        }

        $carParentRows = $carParentTable->fetchAll(array(
            'parent_id = ?' => $car->id
        ));

        $childCars = array();
        foreach ($carParentRows as $carParentRow) {
            $childRow = $carTable->find($carParentRow->car_id)->current();
            $childCars[] = array(
                'name'      => $childRow->getFullName(),
                'isPrimary' => $carParentRow->is_primary,
                'treeUrl'   => $this->_helper->url->url(array(
                    'car_id' => $childRow->id
                )),
                'moderUrl'  => $this->_helper->url->url(array(
                    'action' => 'car',
                    'car_id' => $childRow->id
                )),
                'setIsPrimaryUrl' => $this->_helper->url->url(array(
                    'action'    => 'set-is-primary',
                    'car_id'    => $childRow->id,
                    'parent_id' => $car->id,
                    'value'     => !$carParentRow->is_primary
                ))
            );
        }

        $this->view->assign(array(
            'car'        => $car,
            'parents'    => $parents,
            'childs'     => $childCars,
            'graphItems' => $graphItems,
            'graphLinks' => $graphLinks
        ));
    }

    public function rebuildTreeAction()
    {
        $carTable = $this->_helper->catalogue()->getCarTable();

        $car = $carTable->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $canEditMeta = $this->canEditMeta($car);

        if (!$canEditMeta) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $cpcTable = new Car_Parent_Cache();

        $cpcTable->rebuildCache($car);

        return $this->_redirect($this->_helper->url->url(array(
            'action' => 'tree'
        )));
    }

    public function setIsPrimaryAction()
    {
        $carTable = $this->_helper->catalogue()->getCarTable();

        $car = $carTable->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $canEditMeta = $this->canEditMeta($car);
        if (!$canEditMeta) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $parentCar = $carTable->find($this->getParam('parent_id'))->current();
        if (!$parentCar) {
            return $this->_forward('notfound', 'error');
        }

        $canEditMeta = $this->canEditMeta($parentCar);
        if (!$canEditMeta) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $carParentTable = $this->getCarParentTable();
        $carParentRow = $carParentTable->fetchRow(array(
            'car_id = ?'    => $car->id,
            'parent_id = ?' => $parentCar->id
        ));

        if (!$carParentRow) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $carParentRow->is_primary = (bool)$this->getParam('value');
        $carParentRow->save();

        return $this->_redirect($this->_helper->url->url(array(
            'module'     => 'moder',
            'controller' => 'cars',
            'action'     => 'tree',
            'car_id'     => $parentCar->id,
        ), 'default', true));
    }

    public function removeParentAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $carTable = $this->_helper->catalogue()->getCarTable();

        $car = $carTable->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $canEditMeta = $this->canEditMeta($car);

        if (!$canEditMeta) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $parentCar = $carTable->find($this->getParam('parent_id'))->current();
        if (!$parentCar) {
            return $this->_forward('notfound', 'error');
        }

        $carParentTable = $this->getCarParentTable();

        $carParentTable->removeParent($car, $parentCar);

        $carTable->updateInteritance($car);

        $specService = new Application_Service_Specifications();
        $specService->updateActualValues(1, $car->id);

        $message = sprintf(
            '%s перестал быть родительским автомобилем для %s',
            $this->view->htmlA($this->carModerUrl($parentCar), $parentCar->getFullName()),
            $this->view->htmlA($this->carModerUrl($car), $car->getFullName())
        );
        $this->_helper->log($message, array($car, $parentCar));

        return $this->_redirect($this->getRequest()->getServer('HTTP_REFERER'));
    }

    public function addParentOptionsAction()
    {
        $carTable = $this->_helper->catalogue()->getCarTable();

        $car = $carTable->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $canEditMeta = $this->canEditMeta($car);

        if (!$canEditMeta) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $parentCar = $carTable->find($this->getParam('parent_id'))->current();
        if (!$parentCar) {
            return $this->_forward('notfound', 'error');
        }

        $this->view->assign(array(
            'car'       => $car,
            'parentCar' => $parentCar
        ));
    }

    public function addParentAction()
    {
        /*if (!$this->getRequest()->isPost()) {
            return $this->_forward('notfound', 'error', 'default');
        }*/

        $carTable = $this->_helper->catalogue()->getCarTable();

        $car = $carTable->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $canEditMeta = $this->canEditMeta($car);

        if (!$canEditMeta) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $parentCar = $carTable->find($this->getParam('parent_id'))->current();
        if (!$parentCar) {
            return $this->_forward('notfound', 'error');
        }

        /*if (!$parentCar->is_group) {
            return $this->_forward('add-parent-options');
        }*/

        $this->getCarParentTable()->addParent($car, $parentCar);

        $carTable->updateInteritance($car);

        $specService = new Application_Service_Specifications();
        $specService->updateActualValues(1, $car->id);

        $message = sprintf(
            '%s выбран как родительский автомобиль для %s',
            $this->view->htmlA($this->carModerUrl($parentCar), $parentCar->getFullName()),
            $this->view->htmlA($this->carModerUrl($car), $car->getFullName())
        );
        $this->_helper->log($message, array($car, $parentCar));

        $url = $this->_helper->url->url(array(
            'action' => 'car',
            'tab'    => 'catalogue'
        ));
        if ($this->getRequest()->isXmlHttpRequest()) {
            return $this->_helper->json(array(
                'ok'  => true,
                'url' => $url
            ));
        } else {
            return $this->_redirect($url);
        }
    }

    public function carAutocompleteAction()
    {
        $query = trim($this->getParam('q'));
        $beginYear = false;
        $endYear = false;
        $today = false;
        $body = false;

        if (preg_match("|^(([0-9]{4})([-–]([^[:space:]]{2,4}))?[[:space:]]+)?(.*?)( \((.+)\))?( '([0-9]{4})(–(.+))?)?$|isu", $query, $match)) {

            $query = trim($match[5]);
            $body = isset($match[7]) ? trim($match[7]) : null;
            $beginYear = isset($match[9]) ? (int)$match[9] : null;
            $endYear = isset($match[11]) ? $match[11] : null;
            $beginModelYear = isset($match[2]) ? (int)$match[2] : null;
            $endModelYear = isset($match[4]) ? $match[4] : null;

            if ($endYear == 'н.в.') {
                $today = true;
                $endYear = false;
            } else {
                $eyLength = strlen($endYear);
                if ($eyLength) {
                    if ($eyLength == 2) {
                        $endYear = $beginYear - $beginYear % 100 + $endYear;
                    } else {
                        $endYear = (int)$endYear;
                    }
                } else {
                    $endYear = false;
                }
            }

            if ($endModelYear == 'н.в.') {
                $today = true;
                $endModelYear = false;
            } else {
                $eyLength = strlen($endModelYear);
                if ($eyLength) {
                    if ($eyLength == 2) {
                        $endModelYear = $beginModelYear - $beginModelYear % 100 + $endModelYear;
                    } else {
                        $endModelYear = (int)$endModelYear;
                    }
                } else {
                    $endModelYear = false;
                }
            }
        }

        $specTable = new Spec();
        $specRow = $specTable->fetchRow(array(
            'INSTR(?, short_name)' => $query
        ));

        $specId = null;
        if ($specRow) {
            $specId = $specRow->id;
            $query = trim(str_replace($specRow->short_name, '', $query));
        }

        $carTable = $this->_helper->catalogue()->getCarTable();

        $select = $carTable->select(true)
            ->where('cars.is_group')
            ->where('cars.caption like ?', $query . '%')
            ->order(array('length(cars.caption)', 'cars.is_group desc', 'cars.caption'))
            ->limit(15);

        if ($specId) {
            $select->where('spec_id = ?', $specId);
        }

        if ($beginYear) {
            $select->where('cars.begin_year = ?', $beginYear);
        }
        if ($today) {
            $select->where('cars.today');
        } elseif ($endYear) {
            $select->where('cars.end_year = ?', $endYear);
        }
        if ($body) {
            $select->where('cars.body like ?', $body . '%');
        }

        if ($beginModelYear) {
            $select->where('cars.begin_model_year = ?', $beginModelYear);
        }

        if ($endModelYear) {
            $select->where('cars.end_model_year = ?', $endModelYear);
        }

        $excludeChild = (int)$this->getParam('exclude-child');
        if ($excludeChild) {
            $expr = $carTable->getAdapter()->quoteInto(
                'cars.id = car_parent_cache.car_id and car_parent_cache.parent_id = ?',
                $excludeChild
            );
            $select
                ->joinLeft('car_parent_cache', $expr, null)
                ->where('car_parent_cache.car_id is null');
        }

        $carRows = $carTable->fetchAll($select);

        $result = array();
        foreach ($carRows as $carRow) {
            $result[] = array(
                'id'       => (int)$carRow->id,
                'is_group' => (boolean)$carRow->is_group,
                'name'     => $carRow->getFullName()
            );
        }

        $this->_helper->json($result);
    }

    public function carTwinsAction()
    {
        $carTable = $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }


        $twinsGroupsTable = new Twins_Groups();

        $twinsGroups = array();
        $canEditTwins = $this->_helper->user()->isAllowed('twins', 'edit');

        $twinsGroupRows = $twinsGroupsTable->fetchAll(
            $twinsGroupsTable->select(true)
                ->join('twins_groups_cars', 'twins_groups.id = twins_groups_cars.twins_group_id', null)
                ->where('twins_groups_cars.car_id = ?', $car->id)
        );
        foreach ($twinsGroupRows as $twinsGroupRow) {
            $twinsGroup = array(
                'id'        => $twinsGroupRow->id,
                'name'      => $twinsGroupRow->name,
                'inherited' => false,
            );

            if ($canEditTwins) {
                $twinsGroup['removeUrl'] = $this->_helper->url->url(array(
                    'module'         => 'moder',
                    'controller'     => 'cars',
                    'action'         => 'car-remove-from-twins-group',
                    'car_id'         => $car->id,
                    'twins_group_id' => $twinsGroup['id']
                ), 'default', true);
            }

            $twinsGroups[$twinsGroupRow->id] = $twinsGroup;
        }
        $twinsGroupRows = $twinsGroupsTable->fetchAll(
            $twinsGroupsTable->select(true)
                ->join('twins_groups_cars', 'twins_groups.id = twins_groups_cars.twins_group_id', null)
                ->join('car_parent_cache', 'twins_groups_cars.car_id = car_parent_cache.parent_id', null)
                ->where('car_parent_cache.car_id = ?', $car->id)
        );
        foreach ($twinsGroupRows as $twinsGroupRow) {
            if (isset($twinsGroups[$twinsGroupRow->id])) {
                continue;
            }

            $carRows = $carTable->fetchAll(
                $carTable->select(true)
                    ->join('car_parent_cache', 'cars.id = car_parent_cache.parent_id', null)
                    ->where('car_parent_cache.car_id = ?', $car->id)
                    ->join('twins_groups_cars', 'twins_groups_cars.car_id = car_parent_cache.parent_id', null)
                    ->where('twins_groups_cars.twins_group_id = ?', $twinsGroupRow->id)
            );

            $inheritedFrom = array();
            foreach ($carRows as $carRow) {
                $inheritedFrom[] = array(
                    'name' => $carRow->getFullName(),
                    'url'  => $this->carModerUrl($carRow)
                );
            }

            $twinsGroups[$twinsGroupRow->id] = array(
                'id'            => $twinsGroupRow->id,
                'name'          => $twinsGroupRow->name,
                'inherited'     => true,
                'inheritedFrom' => $inheritedFrom
            );
        }

        foreach ($twinsGroups as &$twinsGroup) {
            $twinsGroup['url'] = $this->_helper->url->url(array(
                'module'         => 'moder',
                'controller'     => 'twins',
                'action'         => 'twins-group',
                'twins_group_id' => $twinsGroup['id']
            ), 'default', true);
        }

        $this->view->assign(array(
            'car'          => $car,
            'twinsGroups'  => $twinsGroups,
            'canEditTwins' => $canEditTwins,
        ));
    }

    public function carFactoriesAction()
    {
        $carTable = $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $factoryTable = new Factory();

        $factories = array();
        $canEditFactory = $this->_helper->user()->isAllowed('factory', 'edit');

        $factoriesRows = $factoryTable->fetchAll(
            $factoryTable->select(true)
                ->join('factory_car', 'factory.id = factory_car.factory_id', null)
                ->where('factory_car.car_id = ?', $car->id)
        );
        foreach ($factoriesRows as $factoriesRow) {
            $factory = array(
                'id'        => $factoriesRow->id,
                'name'      => $factoriesRow->name,
                'inherited' => false,
            );

            if ($canEditFactory) {
                $factory['removeUrl'] = $this->_helper->url->url(array(
                    'module'     => 'moder',
                    'controller' => 'cars',
                    'action'     => 'car-remove-from-factory',
                    'car_id'     => $car->id,
                    'factory_id' => $factory['id']
                ), 'default', true);
            }

            $factories[$factoriesRow->id] = $factory;
        }
        $factoriesRows = $factoryTable->fetchAll(
            $factoryTable->select(true)
                ->join('factory_car', 'factory.id = factory_car.factory_id', null)
                ->join('car_parent_cache', 'factory_car.car_id = car_parent_cache.parent_id', null)
                ->where('car_parent_cache.car_id = ?', $car->id)
        );
        foreach ($factoriesRows as $factoriesRow) {
            if (isset($factories[$factoriesRow->id])) {
                continue;
            }

            $carRows = $carTable->fetchAll(
                $carTable->select(true)
                    ->join('car_parent_cache', 'cars.id = car_parent_cache.parent_id', null)
                    ->where('car_parent_cache.car_id = ?', $car->id)
                    ->join('factory_car', 'factory_car.car_id = car_parent_cache.parent_id', null)
                    ->where('factory_car.factory_id = ?', $factoriesRow->id)
            );

            $inheritedFrom = array();
            foreach ($carRows as $carRow) {
                $inheritedFrom[] = array(
                    'name' => $carRow->getFullName(),
                    'url'  => $this->carModerUrl($carRow)
                );
            }

            $factories[$factoriesRow->id] = array(
                'id'            => $factoriesRow->id,
                'name'          => $factoriesRow->name,
                'inherited'     => true,
                'inheritedFrom' => $inheritedFrom
            );
        }

        foreach ($factories as &$factory) {
            $factory['url'] = $this->_helper->url->url(array(
                'module'     => 'moder',
                'controller' => 'factory',
                'action'     => 'factory',
                'factory_id' => $factory['id']
            ), 'default', true);
        }

        $this->view->assign(array(
            'car'            => $car,
            'factories'      => $factories,
            'canEditFactory' => $canEditFactory,
        ));
    }

    private function carTreeWalk(Cars_Row $car, $carParentRow = null)
    {
        $data = array(
            'name'   => $car->getFullName(),
            'url'    => $this->carModerUrl($car),
            'childs' => array(),
            'type'   => $carParentRow ? $carParentRow->type : null
        );

        $carParentTable = $this->getCarParentTable();
        $carParentRows = $carParentTable->fetchAll(
            $carParentTable->select(true)
                ->join('cars', 'car_parent.car_id = cars.id', null)
                ->where('car_parent.parent_id = ?', $car['id'])
                ->order(array_merge(array('car_parent.type'), $this->_helper->catalogue()->carsOrdering()))
        );

        $carTable = $this->_helper->catalogue()->getCarTable();
        foreach ($carParentRows as $carParentRow) {
            $carRow = $carTable->find($carParentRow->car_id)->current();
            if ($carRow) {
                $data['childs'][] = $this->carTreeWalk($carRow, $carParentRow);
            }
        }

        return $data;
    }

    public function carTreeAction()
    {
        $carTable = $this->_helper->catalogue()->getCarTable();

        $car = $carTable->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $this->view->assign(array(
            'car' => $this->carTreeWalk($car)
        ));
    }

    public function carCatalogueAction()
    {
        $carTable = $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $brandCarTable = new Brand_Car();
        $brandTable = $this->getBrandTable();

        $brandCarRows = $brandCarTable->fetchAll(
            $brandCarTable->select(true)
                ->where('car_id = ?', $car->id)
        );

        $brands = array();
        foreach ($brandCarRows as $brandCarRow) {
            $brandRow = $brandTable->find($brandCarRow->brand_id)->current();
            if ($brandRow) {

                if ($brandCarRow->catname) {
                    $url = $this->_helper->url->url(array(
                        'controller'    => 'catalogue',
                        'action'        => 'brand-car',
                        'brand_catname' => $brandRow->folder,
                        'car_catname'   => $brandCarRow->catname
                    ), 'catalogue', true);
                } else {
                    $url = $this->_helper->url->url(array(
                        'controller'    => 'catalogue',
                        'action'        => 'car',
                        'brand_catname' => $brandRow->folder,
                        'car_id'        => $car->id
                    ), 'catalogue', true);
                }

                $brands[] = array(
                    'name'     => $brandRow->caption,
                    'type'     => $brandCarRow->type,
                    'catname'  => $brandCarRow->catname,
                    'moderUrl' => $this->_helper->url->url(array(
                        'module'     => 'moder',
                        'controller' => 'brands',
                        'action'     => 'brand',
                        'brand_id'   => $brandRow->id
                    )),
                    'url' => $url,
                    'deleteUrl' => $this->_helper->url->url(array(
                        'module'     => 'moder',
                        'controller' => 'cars',
                        'action'     => 'delete-car-from-brand',
                        'car_id'     => $car->id,
                        'brand_id'   => $brandRow->id
                    )),
                    'setBrandCarTypeUrl' => $this->_helper->url->url(array(
                        'action'   => 'set-brand-car-type',
                        'brand_id' => $brandRow->id,
                    )),
                    'setBrandCarCatnameUrl' => $this->_helper->url->url(array(
                        'action'   => 'set-brand-car-catname',
                        'brand_id' => $brandRow->id,
                    )),
                );
            }
        }

        $brandCarRows = $brandCarTable->fetchAll(
            $brandCarTable->select(true)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->where('car_parent_cache.car_id = ?', $car->id)
                ->where('car_parent_cache.car_id <> car_parent_cache.parent_id')
        );
        $inheritBrands = array();
        foreach ($brandCarRows as $brandCarRow) {
            $brandRow = $brandTable->find($brandCarRow->brand_id)->current();
            if ($brandRow) {

                if ($brandCarRow->catname) {
                    $url = $this->_helper->url->url(array(
                        'controller'    => 'catalogue',
                        'action'        => 'brand-car',
                        'brand_catname' => $brandRow->folder,
                        'car_catname'   => $brandCarRow->catname
                    ), 'catalogue', true);
                } else {
                    $url = $this->_helper->url->url(array(
                        'controller'    => 'catalogue',
                        'action'        => 'car',
                        'brand_catname' => $brandRow->folder,
                        'car_id'        => $car->id
                    ), 'catalogue', true);
                }

                $inheritedCar = $carTable->find($brandCarRow->car_id)->current();

                $inheritBrands[] = array(
                    'name'     => $brandRow->caption,
                    'type'     => $brandCarRow->type,
                    'catname'  => $brandCarRow->catname,
                    'moderUrl' => $this->_helper->url->url(array(
                        'module'     => 'moder',
                        'controller' => 'brands',
                        'action'     => 'brand',
                        'brand_id'   => $brandRow->id
                    )),
                    'url' => $url,
                    'car' => array(
                        'name' => $inheritedCar->getFullName(),
                        'url'  => $this->_helper->url->url(array(
                            'module'     => 'moder',
                            'controller' => 'cars',
                            'action'     => 'car',
                            'car_id'     => $inheritedCar->id,
                        ))
                    )
                );
            }
        }


        $relevantBrands = array();

        if (strlen($car->caption) > 0) {
            $rows = $brandTable->fetchAll(
                $brandTable->select(true)
                    ->where('INSTR(?, caption)', $car->caption)
            );

            foreach ($rows as $row) {
                $relevantBrands[$row->id] = $row;
            }
            
            $brandRows = $brandTable->fetchAll(
                $brandTable->select(true)
                    ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                    ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                    ->where('car_parent_cache.car_id = ?', $car->id)
            );

            foreach ($brandRows as $brand) {
                unset($relevantBrands[$brand->id]);
            }

        }
        $canUseTree = in_array($this->_helper->user()->get()->id, array(1, 15603, 1565, 11022, 10713, 14892, 11668, 14360, 216, 3282, 14175, 7715, 1826, 266, 9373, 3755));

        $parents = array();
        $childs = array();
        if ($canUseTree) {

            $carParentTable = $this->getCarParentTable();

            $order = array_merge(array('car_parent.type'), $this->_helper->catalogue()->carsOrdering());

            $carParentRows = $carParentTable->fetchAll(
                $carParentTable->select(true)
                    ->join('cars', 'car_parent.parent_id = cars.id', null)
                    ->where('car_parent.car_id = ?', $car->id)
                    ->order($order)
            );
            $parents = $this->perepareCatalogueCars($carParentRows, true);

            $carParentRows = $carParentTable->fetchAll(
                $carParentTable->select(true)
                    ->join('cars', 'car_parent.car_id = cars.id', null)
                    ->where('car_parent.parent_id = ?', $car->id)
                    ->order($order)
            );
            $childs = $this->perepareCatalogueCars($carParentRows, false);
        }

        $this->view->assign(array(
            'car'                 => $car,
            'canMove'             => $this->canMove($car),
            'brands'              => $brands,
            'inheritBrands'       => $inheritBrands,
            'publicUrls'          => $this->carPublicUrls($car),
            'brandCarTypeOptions' => array(
                Brand_Car::TYPE_DEFAULT => 'стоковая модель',
                Brand_Car::TYPE_TUNING  => $this->view->translate('catalogue/related'),
                Brand_Car::TYPE_SPORT   => 'спорт',
                Brand_Car::TYPE_DESIGN  => 'дизайн'
            ),
            'relevantBrands'      => $relevantBrands,
            'canUseTree'          => $canUseTree,
            'parents'             => $parents,
            'childs'              => $childs,
            'carParentTypeOptions' => array(
                Car_Parent::TYPE_DEFAULT => 'подвид',
                Car_Parent::TYPE_TUNING  => $this->view->translate('catalogue/related'),
                Car_Parent::TYPE_SPORT   => 'спорт'
            )
        ));
    }

    /**
     * @return Car_Parent
     */
    private function getCarParentTable()
    {
        return $this->carParentTable
            ? $this->carParentTable
            : $this->carParentTable = new Car_Parent();
    }

    /**
     * @return Brands_Cars
     */
    private function getBrandCarTable()
    {
        return $this->brandCarTable
            ? $this->brandCarTable
            : $this->brandCarTable = new Brands_Cars();
    }

    /**
     * @return Brands
     */
    private function getBrandTable()
    {
        return $this->brandTable
            ? $this->brandTable
            : $this->brandTable = new Brands();
    }

    private function walkUpUntilBrand($id, array $path)
    {
        $urls = array();

        $brandCarRows = $this->getBrandCarTable()->fetchAll(array(
            'car_id = ?' => $id
        ));

        foreach ($brandCarRows as $brandCarRow) {

            $brand = $this->getBrandTable()->find($brandCarRow->brand_id)->current();
            if (!$brand) {
                throw new Exception("Broken link `{$brandCarRow->brand_id}`");
            }

            $urls[] = $this->_helper->url->url(array(
                'module'        => 'default',
                'controller'    => 'catalogue',
                'action'        => 'brand-car',
                'brand_catname' => $brand->folder,
                'car_catname'   => $brandCarRow->catname ? $brandCarRow->catname : 'car' . $brandCarRow->car_id,
                'path'          => $path
            ), 'catalogue', true);
        }

        $parentRows = $this->getCarParentTable()->fetchAll(array(
            'car_id = ?' => $id
        ));
        foreach ($parentRows as $parentRow) {
            $urls = array_merge(
                $urls,
                $this->walkUpUntilBrand($parentRow->parent_id, array_merge(array($parentRow->catname), $path))
            );
        }

        return $urls;
    }

    private function carPublicUrls(Cars_Row $car)
    {
        return $this->walkUpUntilBrand($car->id, array());
    }

    private function perepareCatalogueCars($carParentRows, $parent)
    {
        $cars = array();

        $carTable = $this->_helper->catalogue()->getCarTable();

        $parentIds = [];
        foreach ($carParentRows as $carParentRow) {
            $parentIds = $carParentRow->parent_id;
        }

        $language = $this->_helper->language();

        foreach ($carParentRows as $carParentRow) {

            $carRow = $carTable->fetchRow(array(
                'id = ?' => $parent ? $carParentRow->parent_id : $carParentRow->car_id
            ));
            if (!$carRow) {
                throw new Exception("Broken car parent link");
            }

            $duplicateRow = null;
            if (!$parent) {
                $select = $carTable->select(true)
                    ->join('car_parent', 'cars.id = car_parent.car_id', null)
                    ->join('car_parent_cache', 'car_parent.car_id = car_parent_cache.parent_id', null)
                    ->where('car_parent_cache.car_id = ?', $carRow->id)
                    ->where('car_parent.parent_id = ?', $carParentRow->parent_id)
                    ->where('car_parent.car_id <> ?', $carRow->id)
                    ->where('car_parent.type = ?', $carParentRow->type);

                $duplicateRow = $carTable->fetchRow($select);
            } else {

                /*$select = $carTable->select(true)
                    ->where('cars.id IN (?)', $parentIds)
                    ->where('cars.id <> ?', $carRow->id)
                    ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                    ->where('car_parent_cache.parent_id = ?', $carRow->id)
                    ->where('not car_parent_cache.tuning')
                    ->where('not car_parent_cache.sport');*/

                $select = $carTable->select(true)
                    ->join('car_parent', 'cars.id = car_parent.parent_id', null)
                    ->where('car_parent.car_id = ?', $carParentRow->car_id)
                    ->where('car_parent.parent_id <> ?', $carRow->id)
                    ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                    ->where('car_parent_cache.parent_id = ?', $carRow->id)
                    ->where('not car_parent_cache.tuning')
                    ->where('not car_parent_cache.sport')
                    ->where('car_parent.type = ?', Car_Parent::TYPE_DEFAULT);

                $duplicateRow = $carTable->fetchRow($select);
            }


            $cars[] = array(
                'id'         => $carRow->id,
                'name'       => $carRow->getNameData($language),
                'publicUrls' => $this->carPublicUrls($carRow),
                'type'       => $carParentRow->type,
                'duplicateRow' => $duplicateRow,
                'url'        => $this->_helper->url->url(array(
                    'module'     => 'moder',
                    'controller' => 'cars',
                    'action'     => 'car',
                    'car_id'     => $carRow->id,
                    'tab'        => 'catalogue'
                ), 'default', true),
                'parent'    => array(
                    'type'      => $carParentRow->type,
                    'name'      => $carParentRow->name,
                    'catname'   => $carParentRow->catname,
                ),
                'deleteUrl' => $this->_helper->url->url(array(
                    'module'     => 'moder',
                    'controller' => 'cars',
                    'action'     => 'remove-parent',
                    'car_id'     => $parent ? $carParentRow->car_id : $carRow->id,
                    'parent_id'  => $parent ? $carRow->id : $carParentRow->parent_id,
                )),
                'typeUrl' => $this->_helper->url->url(array(
                    'module'     => 'moder',
                    'controller' => 'cars',
                    'action'     => 'car-parent-set-type',
                    'car_id'     => $carParentRow->car_id,
                    'parent_id'  => $carParentRow->parent_id
                )),
                'catnameUrl' => $this->_helper->url->url(array(
                    'module'     => 'moder',
                    'controller' => 'cars',
                    'action'     => 'car-parent-set-catname',
                    'car_id'     => $carParentRow->car_id,
                    'parent_id'  => $carParentRow->parent_id
                ))
            );
        }

        return $cars;
    }

    public function carNameAction()
    {
        $carTable = $this->_helper->catalogue()->getCarTable();
        $car = $carTable->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $carLangTable = new Car_Language();

        $languages = array();
        $langValues = array();

        $language = $this->_helper->language();
        $list = Zend_Locale::getTranslationList('language', $language);

        foreach ($list as $code => $content) {
            if (in_array($code, $this->allowedLanguages)) {
                $languages[$code] = $content;

                $carLangRow = $carLangTable->fetchRow(array(
                    'car_id = ?'   => $car->id,
                    'language = ?' => $code
                ));

                $langValues[$code] = $carLangRow ? $carLangRow->name : null;
            }
        }

        $this->view->assign(array(
            'car'        => $car,
            'languages'  => $languages,
            'langValues' => $langValues
        ));
    }

    public function carParentSetTypeAction()
    {
        $carTable = $this->_helper->catalogue()->getCarTable();

        $car = $carTable->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $parent = $carTable->find($this->getParam('parent_id'))->current();
        if (!$parent) {
            return $this->_forward('notfound', 'error');
        }

        $carParentRow = $this->getCarParentTable()->fetchRow(array(
            'car_id = ?'    => $car->id,
            'parent_id = ?' => $parent->id
        ));

        if (!$carParentRow) {
            return $this->_forward('notfound', 'error');
        }

        $carParentRow->type = $this->getParam('type');
        $carParentRow->save();

        $cpcTable = new Car_Parent_Cache();
        $cpcTable->rebuildCache($car);

        return $this->_helper->json(array(
            'ok' => true
        ));
    }

    public function carParentSetCatnameAction()
    {
        $carTable = $this->_helper->catalogue()->getCarTable();

        $car = $carTable->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $parent = $carTable->find($this->getParam('parent_id'))->current();
        if (!$parent) {
            return $this->_forward('notfound', 'error');
        }

        $carParentTable = $this->getCarParentTable();

        $carParentRow = $carParentTable->fetchRow(array(
            'car_id = ?'    => $car->id,
            'parent_id = ?' => $parent->id
        ));

        if (!$carParentRow) {
            return $this->_forward('notfound', 'error');
        }

        $form = new Project_Form(array(
            'elements' => array(
                array('text', 'name', array(
                    'required'   => false,
                    'filters'    => array('StringTrim'),
                    'validators' => array(
                        array('StringLength', true, array(
                            'min' => 1,
                            'max' => 50
                        ))
                    )
                )),
                array('text', 'catname', array(
                    'required'   => false,
                    'filters'    => array('StringTrim', 'StringToLower', 'Filename_Safe'),
                    'validators' => array(
                        array('StringLength', true, array(
                            'min' => 1,
                            'max' => 50
                        )),
                        array('Callback', true, function($value) use ($carParentRow, $carParentTable) {
                            $row = $carParentTable->fetchRow(array(
                                'parent_id = ?' => $carParentRow->parent_id,
                                'catname = ?'   => $value,
                                'car_id <> ?'   => $carParentRow->car_id
                            ));

                            return !$row;
                        })
                    )
                ))
            )
        ));

        $ok = false;
        $messages = array();

        $data = $this->getRequest()->getPost();
        if (!isset($data['catname']) || !strlen($data['catname']) || (!$carParentRow->manual_catname && ($data['catname'] == $carParentRow->car_id))) {
            if (isset($data['name'])) {
                $data['catname'] = $data['name'];
            }
        }

        if ($form->isValid($data)) {

            $values = $form->getValues();

            $nameIsEmpty = strlen($values['name']) == 0;

            if (!$nameIsEmpty) {
                $carParentRow->name = $values['name'];
            } else {
                $carParentRow->name = null;
            }

            $catnameIsEmpty = strlen($values['catname']) == 0 || $values['catname'] == '_';
            if (!$catnameIsEmpty) {
                $carParentRow->catname = $values['catname'];
                $carParentRow->manual_catname = 1;
            } else {
                $carParentRow->catname = $carParentRow->car_id;
                $carParentRow->manual_catname = 0;
            }

            $carParentRow->save();

            $ok = true;
        } else {
            $messages = array_values($form->catname->getMessages());
        }

        $urls = array(
            (int)$car->id => $this->carPublicUrls($car)
        );

        $carParentTable = $this->getCarParentTable();

        $carParentRows = $carParentTable->fetchAll(array(
            'parent_id = ?' => $car->id
        ));
        foreach ($carParentRows as $cpRow) {
            $carRow = $carTable->fetchRow(array(
                'id = ?' => $cpRow->car_id
            ));
            if (!$carRow) {
                throw new Exception("Broken car parent link");
            }

            $urls[(int)$carRow->id] = $this->carPublicUrls($carRow);
        }

        return $this->_helper->json(array(
            'ok'         => $ok,
            'name'       => $carParentRow->name,
            'catname'    => $carParentRow->catname,
            'messages'   => $messages,
            'urls'       => $urls
        ));
    }

    private function carSelectParentWalk(Cars_Row $car)
    {
        $data = array(
            'name'   => $car->getFullName(),
            'url'    => $this->_helper->url->url(array(
                'parent_id' => $car['id']
            )),
            'childs' => array()
        );

        $carTable = $this->_helper->catalogue()->getCarTable();
        $childRows = $carTable->fetchAll(
            $carTable->select(true)
                ->join('car_parent', 'cars.id = car_parent.car_id', null)
                ->where('car_parent.parent_id = ?', $car['id'])
                ->order(array_merge(array('car_parent.type'), $this->_helper->catalogue()->carsOrdering()))
        );
        foreach ($childRows as $childRow) {
            $data['childs'][] = $this->carSelectParentWalk($childRow);
        }

        return $data;
    }


    public function carSelectParentAction()
    {
        $carTable = $this->_helper->catalogue()->getCarTable();
        $car = $carTable->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $canMove = $this->canMove($car);
        if (!$canMove) {
            return $this->_forward('forbidden', 'error');
        }

        $parent = $carTable->find($this->getParam('parent_id'))->current();

        if ($parent) {

            return $this->_forward('add-parent');

        } else {

            $brandTable = $this->getBrandTable();
            $brand = $brandTable->find($this->getParam('brand_id'))->current();
            if ($brand) {

                $rows = $carTable->fetchAll(
                    $carTable->select(true)
                        ->join('brands_cars', 'cars.id = brands_cars.car_id', null)
                        ->where('brands_cars.brand_id = ?', $brand->id)
                        ->order($this->_helper->catalogue()->carsOrdering())
                );

                $cars = array();
                foreach ($rows as $row) {
                    $cars[] = $this->carSelectParentWalk($row);
                }

                $this->view->cars = $cars;
            } else {
                $this->view->brands = $brandTable->fetchAll(null, array('brands.position', 'brands.caption'));
            }
        }

        $this->view->assign(array(
            'car'   => $car,
            'brand' => $brand
        ));
    }

    private function loadSpecs($table, $parentId, $deep = 0)
    {
        if ($parentId) {
            $filter = array('parent_id = ?' => $parentId);
        } else {
            $filter = array('parent_id is null');
        }

        $result = [];
        foreach ($table->fetchAll($filter, 'short_name') as $row) {
            $result[$row->id] = str_repeat('...', $deep) . $row->short_name;
            $result = array_replace($result, $this->loadSpecs($table, $row->id, $deep + 1));
        }

        return $result;
    }

    public function organizeAction()
    {
        $carTable = $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error');
        }

        $canMove = $this->canMove($car);
        if (!$canMove) {
            return $this->_forward('forbidden', 'error');
        }

        $carParentTable = $this->getCarParentTable();
        $carTable = $this->_helper->catalogue()->getCarTable();

        $order = array_merge(array('car_parent.type'), $this->_helper->catalogue()->carsOrdering());

        $carParentRows = $carParentTable->fetchAll(
            $carParentTable->select(true)
                ->join('cars', 'car_parent.car_id = cars.id', null)
                ->where('car_parent.parent_id = ?', $car->id)
                ->where('car_parent.type = ?', Car_Parent::TYPE_DEFAULT)
                ->order($order)
        );

        $childs = array();
        foreach ($carParentRows as $childRow) {
            $carRow = $carTable->find($childRow->car_id)->current();
            $childs[$carRow->id] = $carRow->getFullName();
        }

        $specTable = new Spec();
        $specOptions = $this->loadSpecs($specTable, null, 0);

        $db = $carTable->getAdapter();
        $avgSpecId = $db->fetchOne(
            $db->select()
                ->from($carTable->info('name'), 'AVG(spec_id)')
                ->join('car_parent', 'cars.id = car_parent.parent_id', null)
                ->where('car_parent.car_id = ?', $car->id)
        );
        $inheritedSpec = null;
        if ($avgSpecId) {
            $avgSpec = $specTable->find($avgSpecId)->current();
            if ($avgSpec) {
                $inheritedSpec = $avgSpec->short_name;
            }
        }

        $form = new Application_Form_Moder_Car_Organize(array(
            'action'             => $this->_helper->url->url(),
            'childOptions'       => $childs,
            'inheritedCarType'   => $car->car_type_id,
            'inheritedIsConcept' => $car->is_concept,
            'specOptions'        => array_replace(array('' => '-'), $specOptions),
            'inheritedSpec'      => $inheritedSpec
        ));

        $form->populate(array(
            'is_group' => true
        ));

        $request = $this->getRequest();
        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $values['is_group'] = true;
            $values['produced_exactly'] = false;
            $values['description'] = '';

            $newCar = $carTable->createRow(
                $this->prepareCarMetaToSave($values)
            );
            $newCar->save();

            $newCar->updateOrderCache();

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


            $carParentTable->addParent($newCar, $car);

            $message = sprintf(
                '%s выбран как родительский автомобиль для %s',
                $this->view->htmlA($this->carModerUrl($car), $car->getFullName()),
                $this->view->htmlA($this->carModerUrl($newCar), $newCar->getFullName())
            );
            $this->_helper->log($message, array($car, $newCar));

            $carTable->updateInteritance($newCar);


            $childCarRows = $carTable->find($values['childs']);

            foreach ($childCarRows as $childCarRow) {
                $carParentTable->addParent($childCarRow, $newCar);

                $message = sprintf(
                    '%s выбран как родительский автомобиль для %s',
                    $this->view->htmlA($this->carModerUrl($newCar), $newCar->getFullName()),
                    $this->view->htmlA($this->carModerUrl($childCarRow), $childCarRow->getFullName())
                );
                $this->_helper->log($message, array($newCar, $childCarRow));

                $carParentTable->removeParent($childCarRow, $car);
                $message = sprintf(
                    '%s перестал быть родительским автомобилем для %s',
                    $this->view->htmlA($this->carModerUrl($car), $car->getFullName()),
                    $this->view->htmlA($this->carModerUrl($childCarRow), $childCarRow->getFullName())
                );
                $this->_helper->log($message, array($car, $childCarRow));

                $carTable->updateInteritance($childCarRow);
            }

            $specService = new Application_Service_Specifications();
            $specService->updateActualValues(1, $newCar->id);

            $user = $this->_helper->user()->get();
            $ucsTable = new User_Car_Subscribe();
            $ucsTable->subscribe($user, $newCar);

            return $this->_redirect($this->carModerUrl($car, false, 'catalogue'));
        }

        $this->view->assign(array(
            'car'    => $car,
            //'childs' => $childs,
            'form'   => $form
        ));
    }

    private function prepareCarMetaToSave(array $values)
    {
        $endYear = (int)$values['end_year'];

        $today = null;
        if ($endYear) {
            if ($endYear < date('Y')) {
                $today = false;
            } else {
                $today = null;
            }
        } else {
            switch ($values['today']) {
                case 0:
                    $today = null;
                    break;

                case 1:
                    $today = false;
                    break;

                case 2:
                    $today = true;
                    break;
            }
        }

        $isConcept = false;
        $isConceptInherit = false;
        if ($values['is_concept'] == 'inherited') {
            $isConceptInherit = true;
        } else {
            $isConcept = (bool)$values['is_concept'];
        }

        $carTypeId = null;
        $carTypeInherit = false;
        if ($values['car_type_id'] == 'inherited') {
            $carTypeInherit = true;
        } else {
            $carTypeId = (int)$values['car_type_id'];
            if (!$carTypeId) {
                $carTypeId = null;
            }
        }

        $result = array(
            'caption'            => $values['caption'],
            'body'               => $values['body'],
            'car_type_id'        => $carTypeId,
            'car_type_inherit'   => $carTypeInherit ? 1 : 0,
            'begin_model_year'   => $values['begin_model_year'],
            'end_model_year'     => $values['end_model_year'],
            'begin_year'         => $values['begin_year'],
            'begin_month'        => $values['begin_month'],
            'end_year'           => $endYear ? $endYear : null,
            'end_month'          => $values['end_month'],
            'today'              => $today ? 1 : 0,
            'is_concept'         => $isConcept ? 1 : 0,
            'is_concept_inherit' => $isConceptInherit ? 1 : 0,
            'is_group'           => $values['is_group'] ? 1 : 0,
        );

        if (array_key_exists('spec_id', $values)) {
            $specId = null;
            $specInherit = false;
            if ($values['spec_id'] == 'inherited') {
                $specInherit = true;
            } else {
                $specId = (int)$values['spec_id'];
                if (!$specId) {
                    $specId = null;
                }
            }

            $result['spec_id'] = $specId;
            $result['spec_inherit'] = $specInherit ? 1 : 0;
        }

        if (array_key_exists('description', $values)) {
            $result['description'] = $values['description'];
        }

        if (array_key_exists('produced', $values)) {
            $result['produced'] = $values['produced'];
        }

        if (array_key_exists('produced_exactly', $values)) {
            $result['produced_exactly'] = $values['produced_exactly'] ? 1 : 0;
        }

        return $result;
    }

    public function newAction()
    {
        if (!$this->_helper->user()->isAllowed('car', 'add')) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $carTable = $cars = $this->_helper->catalogue()->getCarTable();

        $parentCar = $cars->find($this->getParam('parent_id'))->current();

        $specTable = new Spec();
        $specOptions = $this->loadSpecs($specTable, null, 0);

        $inheritedSpec = null;
        if ($parentCar) {
            if ($parentCar->spec_id) {
                $specRow = $specTable->find($parentCar->spec_id)->current();
                if ($specRow) {
                    $inheritedSpec = $specRow->short_name;
                }
            }
        }

        $form = new Application_Form_Moder_Car_New(array(
            'inheritedCarType'   => $parentCar ? $parentCar->car_type_id : null,
            'inheritedIsConcept' => $parentCar ? $parentCar->is_concept : null,
            'specOptions'        => array_replace(array('' => '-'), $specOptions),
            'action'             => $this->_helper->url->url(),
            'inheritedSpec'      => $inheritedSpec
        ));

        $request = $this->getRequest();

        if ($request->isPost() && $form->isValid($request->getPost())) {

            $values = $this->prepareCarMetaToSave($form->getValues());
            $values['produced_exactly'] = 0;
            $values['description'] = '';

            $car = $carTable->createRow($values);
            $car->save();

            $car->updateOrderCache();

            $cpcTable = new Car_Parent_Cache();
            $cpcTable->rebuildCache($car);

            $namespace = new Zend_Session_Namespace('Moder_Car');
            $namespace->lastCarId = $car->id;

            $url = $this->_helper->url->url(array(
                'module'     => 'moder',
                'controller' => 'cars',
                'action'     => 'car',
                'car_id'     => $car->id
            ), 'default', true);
            $this->_helper->log(sprintf(
                'Создан новый автомобиль %s',
                $this->view->htmlA($url, $car->getFullName())
            ), $car);

            $user = $this->_helper->user()->get();
            $ucsTable = new User_Car_Subscribe();
            $ucsTable->subscribe($user, $car);

            if ($parentCar) {
                $this->getCarParentTable()->addParent($car, $parentCar);

                $message = sprintf(
                    '%s выбран как родительский автомобиль для %s',
                    $this->view->htmlA($this->carModerUrl($parentCar), $parentCar->getFullName()),
                    $this->view->htmlA($this->carModerUrl($car), $car->getFullName())
                );
                $this->_helper->log($message, array($car, $parentCar));
            }

            $carTable->updateInteritance($car);

            $specService = new Application_Service_Specifications();
            $specService->updateInheritedValues(1, $car->id);

            return $this->_redirect($url);
        }

        $this->view->assign(array(
            'parentCar' => $parentCar,
            'form'      => $form
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

    public function organizePicturesAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $canMove = $this->canMove($car);
        if (!$canMove) {
            return $this->_forward('forbidden', 'error');
        }

        $carParentTable = $this->getCarParentTable();
        $carTable = $this->_helper->catalogue()->getCarTable();
        $imageStorage = $this->getInvokeArg('bootstrap')->getResource('imagestorage');

        $childs = array();
        $pictureTable = $this->_helper->catalogue()->getPictureTable();
        $rows = $pictureTable->fetchAll(
            $pictureTable->select(true)
                ->where('pictures.car_id = ?', $car->id)
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->order(array('pictures.status', 'pictures.id'))
        );
        foreach ($rows as $row) {
            $request = Pictures_Row::buildFormatRequest($row->toArray());
            $imageInfo = $imageStorage->getFormatedImage($request, 'picture-thumb');
            if ($imageInfo) {
                $childs[$row->id] = $imageInfo->getSrc();
            }
        }

        $specTable = new Spec();
        $specOptions = $this->loadSpecs($specTable, null, 0);

        $db = $carTable->getAdapter();
        $avgSpecId = $db->fetchOne(
            $db->select()
                ->from($carTable->info('name'), 'AVG(spec_id)')
                ->join('car_parent', 'cars.id = car_parent.parent_id', null)
                ->where('car_parent.car_id = ?', $car->id)
        );
        $inheritedSpec = null;
        if ($avgSpecId) {
            $avgSpec = $specTable->find($avgSpecId)->current();
            if ($avgSpec) {
                $inheritedSpec = $avgSpec->short_name;
            }
        }

        $form = new Application_Form_Moder_Car_OrganizePictures(array(
            'action'             => $this->_helper->url->url(),
            'childOptions'       => $childs,
            'inheritedCarType'   => $car->car_type_id,
            'inheritedIsConcept' => $car->is_concept,
            'specOptions'        => array_replace(array('' => '-'), $specOptions),
            'inheritedSpec'      => $inheritedSpec
        ));


        $form->populate(array(
            'is_group' => true
        ));

        $request = $this->getRequest();
        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $values['is_group'] = false;
            $values['produced_exactly'] = false;
            $values['description'] = '';

            $newCar = $carTable->createRow(
                $this->prepareCarMetaToSave($values)
            );
            $newCar->save();

            $newCar->updateOrderCache();

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

            $car->is_group = 1;
            $car->save();

            $carParentTable->addParent($newCar, $car);

            $message = sprintf(
                '%s выбран как родительский автомобиль для %s',
                $this->view->htmlA($this->carModerUrl($car), $car->getFullName()),
                $this->view->htmlA($this->carModerUrl($newCar), $newCar->getFullName())
            );
            $this->_helper->log($message, array($car, $newCar));

            $carTable->updateInteritance($newCar);


            $pictureRows = $pictureTable->find($values['childs']);

            foreach ($pictureRows as $pictureRow) {
                $pictureRow->car_id = $newCar->id;
                $pictureRow->save();

                if ($pictureRow->image_id) {
                    $imageStorage = $this->getInvokeArg('bootstrap')
                        ->getResource('imagestorage');
                    $imageStorage->changeImageName($pictureRow->image_id, array(
                        'pattern' => $pictureRow->getFileNamePattern(),
                    ));
                } else {
                    $pictureRow->correctFileName();
                }

                $this->_helper->log(sprintf(
                    'Картинка %s связана с автомобилем %s',
                    $this->view->htmlA($this->pictureUrl($pictureRow), $pictureRow->id),
                    $this->view->htmlA($this->_helper->url->url(array(
                        'module'     => 'moder',
                        'controller' => 'cars',
                        'action'     => 'car',
                        'car_id'     => $car->id
                    ), 'default', true), $car->getFullName())
                ), array($car, $pictureRow));
            }

            // обнволяем кэш старого автомобиля
            $car->refreshPicturesCount();
            foreach ($car->findBrandsViaBrands_Cars() as $brand) {
                $brand->updatePicturesCache();
                $brand->refreshPicturesCount();
            }

            // обнволяем кэш нового автомобиля
            $newCar->refreshPicturesCount();
            foreach ($newCar->findBrandsViaBrands_Cars() as $brand) {
                $brand->updatePicturesCache();
                $brand->refreshPicturesCount();
            }

            $specService = new Application_Service_Specifications();
            $specService->updateActualValues(1, $newCar->id);

            $user = $this->_helper->user()->get();
            $ucsTable = new User_Car_Subscribe();
            $ucsTable->subscribe($user, $newCar);

            return $this->_redirect($this->carModerUrl($car, false, 'catalogue'));
        }

        $this->view->assign(array(
            'car'    => $car,
            //'childs' => $childs,
            'form'   => $form
        ));
    }

    private function carMofificationsGroupModifications(Cars_Row $car, $groupId)
    {
        $mTable = new Modification();
        $db = $mTable->getAdapter();

        $select = $mTable->select(true)
            ->join('car_parent_cache', 'modification.car_id = car_parent_cache.parent_id', null)
            ->where('car_parent_cache.car_id = ?', $car->id)
            ->order('modification.name');

        if ($groupId) {
            $select->where('modification.group_id = ?', $groupId);
        } else {
            $select->where('modification.group_id IS NULL');
        }

        $modifications = [];
        foreach ($mTable->fetchAll($select) as $mRow) {
            $modifications[] = array(
                'inherited' => $mRow->car_id != $car->id,
                'name'      => $mRow->name,
                'url'       => $this->_helper->url->url(array(
                    'module'          => 'moder',
                    'controller'      => 'modification',
                    'action'          => 'edit',
                    'car_id'          => $car['id'],
                    'modification_id' => $mRow->id
                ), 'default', true),
                'count'     => $db->fetchOne(
                    $db->select()
                        ->from('modification_picture', 'count(1)')
                        ->where('modification_picture.modification_id = ?', $mRow->id)
                        ->join('pictures', 'modification_picture.picture_id = pictures.id', null)
                        ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                        ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                        ->where('car_parent_cache.parent_id = ?', $car->id)
                )
            );
        }

        return $modifications;
    }

    public function carModificationsAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $mgTable = new Modification_Group();

        $mgRows = $mgTable->fetchAll(
            $mgTable->select(true)
        );

        $groups = [];
        foreach ($mgRows as $mgRow) {
            $groups[] = array(
                'name'          => $mgRow->name,
                'modifications' => $this->carMofificationsGroupModifications($car, $mgRow->id)
            );
        }

        $groups[] = array(
            'name'          => null,
            'modifications' => $this->carMofificationsGroupModifications($car, null)
        );

        $this->view->assign(array(
            'car'    => $car,
            'groups' => $groups
        ));
    }

    public function carModificationPicturesAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $mTable = new Modification();
        $mpTable = new Modification_Picture();
        $mgTable = new Modification_Group();
        $pictureTable = new Picture();
        $db = $mpTable->getAdapter();
        $imageStorage = $this->getInvokeArg('bootstrap')->getResource('imagestorage');
        $language = $this->_helper->language();


        $request = $this->getRequest();
        if ($request->isPost()) {

            $picture = (array)$this->getParam('picture', []);

            foreach ($picture as $pictureId => $modificationIds) {
                $pictureRow = $pictureTable->fetchRow(
                    $pictureTable->select(true)
                        ->where('pictures.id = ?', (int)$pictureId)
                        ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                        ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                        ->where('car_parent_cache.parent_id = ?', $car->id)
                );

                if ($pictureRow) {
                    foreach ($modificationIds as &$modificationId) {
                        $modificationId = (int)$modificationId;

                        $mpRow = $mpTable->fetchRow(array(
                            'picture_id = ?'      => $pictureRow->id,
                            'modification_id = ?' => $modificationId
                        ));
                        if (!$mpRow) {
                            $mpRow = $mpTable->createRow(array(
                                'picture_id'      => $pictureRow->id,
                                'modification_id' => $modificationId
                            ));
                            $mpRow->save();
                        }
                    }
                    unset($modificationId); // prevent bugs

                    $select = $mpTable->select(true)
                        ->where('modification_picture.picture_id = ?', $pictureRow->id)
                        ->join('modification', 'modification_picture.modification_id = modification.id', null)
                        ->join('car_parent_cache', 'modification.car_id = car_parent_cache.parent_id', null)
                        ->where('car_parent_cache.car_id = ?', $car->id);

                    if ($modificationIds) {
                        $select->where('modification.id not in (?)', $modificationIds);
                    }

                    $mpRows = $mpTable->fetchAll($select);
                    foreach ($mpRows as $mpRow) {
                        $mpRow->delete();
                    }
                }
            }

            return $this->redirectToCar($car, 'modifications');
        }



        $pictures = [];

        $pictureRows = $pictureTable->fetchAll(
            $pictureTable->select(true)
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                ->where('car_parent_cache.parent_id = ?', $car->id)
                ->order('pictures.id')
        );

        foreach ($pictureRows as $pictureRow) {

            $request = Pictures_Row::buildFormatRequest($pictureRow->toArray());
            $imageInfo = $imageStorage->getFormatedImage($request, 'picture-thumb');

            $modificationIds = $db->fetchCol(
                $db->select()
                    ->from('modification_picture', 'modification_id')
                    ->where('picture_id = ?', $pictureRow->id)
            );

            $pictures[] = array(
                'id'              => $pictureRow->id,
                'name'            => $pictureRow->getCaption(array(
                    'language' => $language
                )),
                'url'             => $this->_helper->pic->href($pictureRow),
                'src'             => $imageInfo ? $imageInfo->getSrc() : null,
                'modificationIds' => $modificationIds
            );
        }


        $mgRows = $mgTable->fetchAll(
            $mgTable->select(true)
        );

        $groups = [];
        foreach ($mgRows as $mgRow) {

            $mRows = $mTable->fetchAll(
                $mTable->select(true)
                    ->where('modification.group_id = ?', $mgRow->id)
                    ->join('car_parent_cache', 'modification.car_id = car_parent_cache.parent_id', null)
                    ->where('car_parent_cache.car_id = ?', $car->id)
                    ->order('modification.name')
            );

            $modifications = [];
            foreach ($mRows as $mRow) {
                $modifications[] = array(
                    'id'     => $mRow->id,
                    'name'   => $mRow->name,
                );
            }

            $groups[] = array(
                'name'          => $mgRow->name,
                'modifications' => $modifications
            );
        }

        $mRows = $mTable->fetchAll(
            $mTable->select(true)
                ->where('modification.group_id IS NULL')
                ->join('car_parent_cache', 'modification.car_id = car_parent_cache.parent_id', null)
                ->where('car_parent_cache.car_id = ?', $car->id)
                ->order('modification.name')
        );

        $modifications = [];
        foreach ($mRows as $mRow) {
            $modifications[] = array(
                'id'   => $mRow->id,
                'name' => $mRow->name,
            );
        }

        $groups[] = array(
            'name'          => null,
            'modifications' => $modifications
        );


        $this->view->assign(array(
            'pictures' => $pictures,
            'groups'   => $groups
        ));
    }
}
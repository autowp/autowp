<?php

class Moder_CommentsController extends Zend_Controller_Action
{
    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder') ) {
            return $this->_forward('forbidden', 'error', 'default');
        }
    }

    public function indexAction()
    {

        $brandTable = new Brands();

        $brandRows = $brandTable->fetchAll(
            $brandTable->select(true)
                /*->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->join('pictures', 'pictures.car_id = car_parent_cache.car_id', null)
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->join('comments_messages', 'comments_messages.item_id = pictures.id', null)
                ->where('comments_messages.type_id = ?', Comment_Message::PICTURES_TYPE_ID)
                ->group('brands.id')*/
                ->order(array('brands.position', 'brands.caption'))
        );
        $brandOptions = array(
            '' => '--'
        );
        foreach ($brandRows as $brandRow) {
            $brandOptions[$brandRow->id] = $brandRow->caption;
        }

        $form = new Project_Form(array(
            'decorators' => array(
                'PrepareElements',
                array('viewScript', array('viewScript' => 'forms/bootstrap-vertical.phtml')),
                'Form'
            ),
            'action'   => $this->_helper->url->url(),
            'method'   => 'post',
            'elements' => array(
                array('text', 'user', array(
                    'label'      => 'Пользователь №',
                    'decorators' => array(
                        'ViewHelper'
                    )
                )),
                array('select', 'brand_id', array(
                    'label'        => 'Бренд',
                    'multioptions' => $brandOptions,
                    'decorators' => array(
                        'ViewHelper'
                    )
                )),
                array('select', 'moderator_attention', array(
                    'label'        => 'Внимание модераторов',
                    'multioptions' => array(
                        ''                                             => 'Не важно',
                        Comment_Message::MODERATOR_ATTENTION_NONE      => 'Не требуется',
                        Comment_Message::MODERATOR_ATTENTION_REQUIRED  => 'Требуется',
                        Comment_Message::MODERATOR_ATTENTION_COMPLETED => 'Выполнено',
                    ),
                    'decorators' => array(
                        'ViewHelper'
                    )
                )),
                array('text', 'car_id', array(
                    'required'     => false,
                    'label'        => 'Автомобиль (id)',
                    'decorators'   => array('ViewHelper')
                )),
            )
        ));

        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            unset($params['submit']);
            foreach ($params as $key => $value) {
                if (strlen($value) <= 0) {
                    $params[$key] = null;
                }
            }
            return $this->_redirect($this->_helper->url->url($params));
        }

        $commentTable = new Comment_Message();

        $select = $commentTable->select(true)
            ->order(array('comments_messages.datetime DESC'));

        if ($form->isValid($this->_getAllParams())) {
            $values = $form->getValues();

            if ($values['user']) {

                if (!is_numeric($values['user'])) {
                    $userTable = new Users();
                    $userRow = $userTable->fetchRow(array(
                        'identity = ?' => $values['user']
                    ));
                    if ($userRow) {
                        $values['user'] = $userRow->id;
                    }
                }

                $select->where('comments_messages.author_id = ?', $values['user']);
            }

            if (strlen($values['moderator_attention'])) {
                switch ($values['moderator_attention']) {
                    case Comment_Message::MODERATOR_ATTENTION_NONE:
                    case Comment_Message::MODERATOR_ATTENTION_REQUIRED:
                    case Comment_Message::MODERATOR_ATTENTION_COMPLETED:
                        $select->where('comments_messages.moderator_attention = ?', $values['moderator_attention']);
                        break;
                }
            }

            if ($values['brand_id']) {
                $select
                    ->where('comments_messages.type_id = ?', Comment_Message::PICTURES_TYPE_ID)
                    ->join('pictures', 'comments_messages.item_id = pictures.id', null)
                    ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                    ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                    ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                    ->where('brands_cars.brand_id = ?', $values['brand_id']);
            }

            if ($values['car_id']) {
                $select
                    ->where('comments_messages.type_id = ?', Comment_Message::PICTURES_TYPE_ID)
                    ->join('pictures', 'comments_messages.item_id = pictures.id', null)
                    ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                    ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                    ->where('car_parent_cache.parent_id = ?', $values['car_id']);
            }
        }

        //print $select; exit;

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(50)
            ->setCurrentPageNumber($this->_getParam('page'));



        $comments = array();
        foreach ($paginator->getCurrentItems() as $commentRow) {
            $status = '';
            if ($commentRow->type_id == Comment_Message::PICTURES_TYPE_ID) {
                $pictures = $this->_helper->catalogue()->getPictureTable();
                $picture = $pictures->find($commentRow->item_id)->current();
                if ($picture) {
                    switch ($picture->status) {
                        case Picture::STATUS_ACCEPTED: $status = '<span class="label label-success">принято</span>'; break;
                        case Picture::STATUS_NEW:      $status = '<span class="label label-warning">новое</span>'; break;
                        case Picture::STATUS_INBOX:    $status = '<span class="label label-warning">входящее</span>'; break;
                        case Picture::STATUS_REMOVED:  $status = '<span class="label label-danger">удалено</span>'; break;
                        case Picture::STATUS_REMOVING: $status = '<span class="label label-danger">удаляется</span>'; break;
                    }
                }
            }

            $comments[] = array(
                'url'     => $commentRow->getUrl(),
                'message' => $commentRow->getMessagePreview(),
                'user'    => $commentRow->findParentUsers(),
                'status'  => $status,
                'new'     => $commentRow->isNew($this->_helper->user()->get()->id)
            );
        }

        $this->view->assign(array(
            'form'      => $form,
            'paginator' => $paginator,
            'comments'  => $comments
        ));
    }
}
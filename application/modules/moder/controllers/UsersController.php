<?php

class Moder_UsersController extends Zend_Controller_Action
{
    /**
     * @var Users
     */
    private $table;

    public function init()
    {
        parent::init();

        $this->table = new Users();
    }

    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder')) {
            return $this->_forward('forbidden', 'error', 'default');
        }
    }

    public function indexAction()
    {
        $isModer = $this->_helper->user()->inheritsRole('moder');
        if (!$isModer) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $select = $this->table->select()
            ->order('id');

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(30)
            ->setCurrentPageNumber($this->_getParam('page'));

        $this->view->assign(array(
            'paginator' => $paginator
        ));
    }

    public function removeUserPhotoAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $can = $this->_helper->user()->isAllowed('user', 'ban');
        if (!$can) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $row = $this->table->find($this->_getParam('id'))->current();

        if (!$row) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $oldImageId = $row->img;
        if ($oldImageId) {
            $row->img = null;
            $row->save();
            $imageStorage = $this->getInvokeArg('bootstrap')->getResource('imagestorage');
            $imageStorage->removeImage($oldImageId);
        }

        $this->_helper->log(sprintf(
            'Удаление фотографии пользователя №%s',
            $row->id
        ), array($row));

        return $this->_redirect($this->_helper->url->url(array(
            'module'     => 'default',
            'controller' => 'users',
            'action'     => 'user',
            'user_id'    => $row->id
        ), 'users', true));
    }

    public function deleteUserAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $can = $this->_helper->user()->isAllowed('user', 'delete');
        if (!$can) {
            return $this->_forward('forbidden', 'error', 'default');
        }

        $row = $this->table->find($this->_getParam('id'))->current();
        if (!$row) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $oldImageId = $row->img;
        if ($oldImageId) {
            $row->img = null;
            $row->save();
            $imageStorage = $this->getInvokeArg('bootstrap')->getResource('imagestorage');
            $imageStorage->removeImage($oldImageId);
        }

        $row->deleted = 1;
        $row->save();

        $sessionTable = new Session();

        $sessionTable->delete(array(
            'user_id = ?' => $row->id
        ));

        $this->_helper->log(sprintf(
            'Удаление пользователя №%s',
            $row->id
        ), array($row));

        return $this->_redirect($this->_helper->url->url(array(
            'module'     => 'default',
            'controller' => 'users',
            'action'     => 'user',
            'user_id'    => $row->id
        ), 'users', true));
    }
}
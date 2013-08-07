<?php
class CutawayController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $pictures = new Picture();

        $select = $pictures->select(true)
            ->where('status in (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
            ->where('perspective_id = ?', 9)
            ->order('add_date DESC');

        $this->view->paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(18)
            ->setCurrentPageNumber($this->_getParam('page'));
    }
}
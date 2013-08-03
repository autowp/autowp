<?php
class CutawayController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $pictures = new Pictures();

        $select = $pictures->select(true)
            ->where('status in (?)', array(Pictures::STATUS_ACCEPTED, Pictures::STATUS_NEW))
            ->where('perspective_id = ?', 9)
            ->order('add_date DESC');

        $this->view->paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(18)
            ->setCurrentPageNumber($this->_getParam('page'));
    }
}
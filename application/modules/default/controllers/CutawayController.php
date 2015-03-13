<?php
class CutawayController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $pictures = $this->_helper->catalogue()->getPictureTable();

        $select = $pictures->select(true)
            ->where('status in (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
            ->where('perspective_id = ?', 9)
            ->order('add_date DESC');

        $paginator = Zend_Paginator::factory($select)
            ->setItemCountPerPage(18)
            ->setCurrentPageNumber($this->_getParam('page'));

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $picturesData = $this->_helper->pic->listData($select, array(
            'width' => 6
        ));

        $this->view->assign(array(
            'paginator'    => $paginator,
            'picturesData' => $picturesData,
        ));
    }
}
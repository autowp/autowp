<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Paginator\Adapter\Zend1DbTableSelect;

use Zend_Paginator;

use Picture;

class CutawayController extends AbstractActionController
{
    public function indexAction()
    {
        $pictures = new Picture();

        $select = $pictures->select(true)
            ->where('status in (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
            ->where('perspective_id = ?', 9)
            ->order('add_date DESC');

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage(18)
            ->setCurrentPageNumber($this->params('page'));

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $picturesData = $this->pic()->listData($select, [
            'width' => 6
        ]);

        return [
            'paginator'    => $paginator,
            'picturesData' => $picturesData,
        ];
    }
}
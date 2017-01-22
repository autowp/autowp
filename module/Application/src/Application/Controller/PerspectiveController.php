<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\DbTable\Picture;

use Autowp\Commons\Paginator\Adapter\Zend1DbTableSelect;

class PerspectiveController extends AbstractActionController
{
    public function indexAction()
    {
        $pictures = new Picture();

        $select = $pictures->select(true)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->where('pictures.status in (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW])
            ->where('picture_item.perspective_id = ?', (int)$this->params('perspective'))
            ->order('pictures.accept_datetime DESC');

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
            'page'         => (int)$this->params('page_id'),
            'paginator'    => $paginator,
            'picturesData' => $picturesData,
        ];
    }
}

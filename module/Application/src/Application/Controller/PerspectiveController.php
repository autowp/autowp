<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Autowp\Commons\Paginator\Adapter\Zend1DbTableSelect;

use Application\Model\DbTable;
use Application\Model\Picture;

class PerspectiveController extends AbstractActionController
{
    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    public function __construct(DbTable\Picture $pictureTable)
    {
        $this->pictureTable = $pictureTable;
    }

    public function indexAction()
    {
        $select = $this->pictureTable->select(true)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
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

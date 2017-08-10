<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Picture;

class PerspectiveController extends AbstractActionController
{
    /**
     * @var Picture
     */
    private $picture;

    public function __construct(Picture $picture)
    {
        $this->picture = $picture;
    }

    public function indexAction()
    {
        $paginator = $this->picture->getPaginator([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'perspective' => (int)$this->params('perspective')
            ],
            'order'  => 'accept_datetime_desc'
        ]);

        $paginator
            ->setItemCountPerPage(18)
            ->setCurrentPageNumber($this->params('page'));

        $picturesData = $this->pic()->listData($paginator->getCurrentItems(), [
            'width' => 6
        ]);

        return [
            'page'         => (int)$this->params('page_id'),
            'paginator'    => $paginator,
            'picturesData' => $picturesData,
        ];
    }
}

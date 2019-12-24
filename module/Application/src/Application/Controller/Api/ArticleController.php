<?php

namespace Application\Controller\Api;

use Zend\Db\TableGateway\TableGateway;
use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Paginator;
use Zend\View\Model\JsonModel;
use Application\Hydrator\Api\RestHydrator;

class ArticleController extends AbstractActionController
{
    const ARTICLES_PER_PAGE = 10;
    const PREVIEW_CAT_PATH = '/img/articles/preview/';

    /**
     * @var InputFilter
     */
    private $listInputFilter;

    /**
     * @var RestHydrator
     */
    private $hydrator;

    /**
     * @var TableGateway
     */
    private $table;

    public function __construct(
        TableGateway $table,
        InputFilter $listInputFilter,
        RestHydrator $hydrator
    ) {
        $this->table = $table;
        $this->listInputFilter = $listInputFilter;
        $this->hydrator = $hydrator;
    }

    public function indexAction()
    {
        $this->listInputFilter->setData($this->params()->fromQuery());

        if (! $this->listInputFilter->isValid()) {
            return $this->inputFilterResponse($this->listInputFilter);
        }

        $data = $this->listInputFilter->getValues();

        $select = $this->table->getSql()->select()
            ->where('enabled')
            ->order(['add_date DESC']);

        if ($data['catname']) {
            $select->where(['catname' => $data['catname']]);
        }

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->table->getAdapter())
        );

        $paginator
            ->setItemCountPerPage($data['limit'] > 0 ? $data['limit'] : 1)
            ->setCurrentPageNumber($data['page']);


        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
        ]);

        $items = [];
        foreach ($paginator->getCurrentItems() as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'paginator' => get_object_vars($paginator->getPages()),
            'items'     => $items
        ]);
    }
}

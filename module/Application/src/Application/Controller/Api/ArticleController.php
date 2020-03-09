<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\RestHydrator;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Paginator;
use Laminas\View\Model\JsonModel;

use function get_object_vars;

class ArticleController extends AbstractActionController
{
    public const PREVIEW_CAT_PATH = '/img/articles/preview/';

    private InputFilter $listInputFilter;

    private RestHydrator $hydrator;

    private TableGateway $table;

    public function __construct(
        TableGateway $table,
        InputFilter $listInputFilter,
        RestHydrator $hydrator
    ) {
        $this->table           = $table;
        $this->listInputFilter = $listInputFilter;
        $this->hydrator        = $hydrator;
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
            'items'     => $items,
        ]);
    }
}

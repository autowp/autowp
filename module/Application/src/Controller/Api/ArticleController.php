<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\AbstractRestHydrator;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Paginator;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function get_object_vars;

/**
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 * @method string language()
 */
class ArticleController extends AbstractActionController
{
    public const PREVIEW_CAT_PATH = '/img/articles/preview/';

    private InputFilter $listInputFilter;

    private AbstractRestHydrator $hydrator;

    private TableGateway $table;

    public function __construct(
        TableGateway $table,
        InputFilter $listInputFilter,
        AbstractRestHydrator $hydrator
    ) {
        $this->table           = $table;
        $this->listInputFilter = $listInputFilter;
        $this->hydrator        = $hydrator;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
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

        /** @var Adapter $adapter */
        $adapter   = $this->table->getAdapter();
        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $adapter)
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

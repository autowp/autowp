<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\AbstractRestHydrator;
use Autowp\User\Controller\Plugin\User;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

/**
 * @method ViewModel forbiddenAction()
 * @method User user($user = null)
 * @method ApiProblemResponse inputFilterResponse(InputFilterInterface $inputFilter)
 * @method string language()
 */
class PerspectivePageController extends AbstractRestfulController
{
    private TableGateway $table;

    private AbstractRestHydrator $hydrator;

    private InputFilter $listInputFilter;

    public function __construct(AbstractRestHydrator $hydrator, InputFilter $listInputFilter, TableGateway $table)
    {
        $this->table           = $table;
        $this->hydrator        = $hydrator;
        $this->listInputFilter = $listInputFilter;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $this->listInputFilter->setData($this->params()->fromQuery());

        if (! $this->listInputFilter->isValid()) {
            return $this->inputFilterResponse($this->listInputFilter);
        }

        $data = $this->listInputFilter->getValues();

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
        ]);

        $select = new Sql\Select($this->table->getTable());
        $select->order('id');

        $items = [];
        foreach ($this->table->selectWith($select) as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'items' => $items,
        ]);
    }
}

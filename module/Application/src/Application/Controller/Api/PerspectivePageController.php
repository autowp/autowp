<?php

namespace Application\Controller\Api;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use ZF\ApiProblem\ApiProblemResponse;

use Autowp\User\Controller\Plugin\User;

use Application\Controller\Plugin\ForbiddenAction;
use Application\Hydrator\Api\RestHydrator;

/**
 * Class PerspectivePageController
 * @package Application\Controller\Api
 *
 * @method ForbiddenAction forbiddenAction()
 * @method User user($user = null)
 * @method ApiProblemResponse inputFilterResponse(InputFilter $inputFilter)
 * @method string language()
 */
class PerspectivePageController extends AbstractRestfulController
{
    /**
     * @var TableGateway
     */
    private $table;

    /**
     * @var RestHydrator
     */
    private $hydrator;

    /**
     * @var InputFilter
     */
    private $listInputFilter;

    public function __construct(RestHydrator $hydrator, InputFilter $listInputFilter, TableGateway $table)
    {
        $this->table = $table;
        $this->hydrator = $hydrator;
        $this->listInputFilter = $listInputFilter;
    }

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
            'fields'   => $data['fields']
        ]);

        $select = new Sql\Select($this->table->getTable());
        $select->order('id');

        $items = [];
        foreach ($this->table->selectWith($select) as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'items' => $items
        ]);
    }
}

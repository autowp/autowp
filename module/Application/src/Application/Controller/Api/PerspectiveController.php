<?php

namespace Application\Controller\Api;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Application\Hydrator\Api\RestHydrator;

class PerspectiveController extends AbstractRestfulController
{
    /**
     * @var TableGateway
     */
    private $table;

    /**
     * @var RestHydrator
     */
    private $hydrator;

    public function __construct(RestHydrator $hydrator, TableGateway $table)
    {
        $this->hydrator = $hydrator;
        $this->table = $table;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => []
        ]);

        $select = new Sql\Select($this->table->getTable());
        $select->order('position');

        $rows = $this->table->selectWith($select);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'items' => $items
        ]);
    }
}

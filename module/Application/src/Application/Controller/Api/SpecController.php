<?php

namespace Application\Controller\Api;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

class SpecController extends AbstractRestfulController
{
    /**
     * @var TableGateway
     */
    private $table;

    public function __construct(TableGateway $table)
    {
        $this->table = $table;
    }

    private function getSpecOptions(int $parentId = 0): array
    {
        $select = new Sql\Select($this->table->getTable());
        $select->order('name');

        if ($parentId) {
            $select->where([
                'parent_id' => $parentId
            ]);
        } else {
            $select->where(['parent_id is null']);
        }

        $rows = $this->table->selectWith($select);
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'short_name' => $row['short_name'],
                'childs'     => $this->getSpecOptions($row['id'])
            ];
        }

        return $result;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        return new JsonModel([
            'items' => $this->getSpecOptions(0),
        ]);
    }
}

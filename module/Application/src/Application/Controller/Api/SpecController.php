<?php

namespace Application\Controller\Api;

use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class SpecController extends AbstractRestfulController
{
    private TableGateway $table;

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
                'parent_id' => $parentId,
            ]);
        } else {
            $select->where(['parent_id is null']);
        }

        $rows   = $this->table->selectWith($select);
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id'         => (int) $row['id'],
                'name'       => $row['name'],
                'short_name' => $row['short_name'],
                'childs'     => $this->getSpecOptions($row['id']),
            ];
        }

        return $result;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function indexAction()
    {
        return new JsonModel([
            'items' => $this->getSpecOptions(0),
        ]);
    }
}

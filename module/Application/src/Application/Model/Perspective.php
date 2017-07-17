<?php

namespace Application\Model;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

class Perspective
{
    /**
     * @var TableGateway
     */
    private $table;

    /**
     * @var TableGateway
     */
    private $groupTable;

    public function __construct(TableGateway $table, TableGateway $groupTable)
    {
        $this->table = $table;
        $this->groupTable = $groupTable;
    }

    private function fetchBySelect(Sql\Select $select): array
    {
        $options = [];
        foreach ($this->table->selectWith($select) as $row) {
            $options[] = [
                'id'   => (int)$row['id'],
                'name' => $row['name']
            ];
        }

        return $options;
    }

    public function getArray(): array
    {
        $select = new Sql\Select($this->table->getTable());
        $select->columns(['id', 'name'])
            ->order('position');

        return $this->fetchBySelect($select);
    }

    public function getPairs(): array
    {
        $options = [];
        foreach ($this->getArray() as $row) {
            $options[$row['id']] = $row['name'];
        }

        return $options;
    }

    public function getOnlyPairs(array $ids): array
    {
        if (count($ids) <= 0) {
            return [];
        }

        $select = new Sql\Select($this->table->getTable());
        $select->columns(['id', 'name'])
            ->where([new Sql\Predicate\In('id', $ids)])
            ->order('position');

        $result = [];
        foreach ($this->table->selectWith($select) as $row) {
            $result[] = [
                'id'   => (int)$row['id'],
                'name' => $row['name']
            ];
        }

        return $result;
    }

    public function getGroupPerspectives(int $groupId): array
    {
        $select = new Sql\Select($this->table->getTable());
        $select->columns(['id', 'name'])
            ->join(
                'perspectives_groups_perspectives',
                'perspectives.id = perspectives_groups_perspectives.perspective_id',
                []
            )
            ->where(['perspectives_groups_perspectives.group_id = ?' => $groupId])
            ->order('perspectives_groups_perspectives.position');

        return $this->fetchBySelect($select);
    }

    public function getPageGroupIds(int $pageId): array
    {
        $select = new Sql\Select($this->groupTable->getTable());
        $select->columns(['id'])
            ->where(['page_id' => $pageId])
            ->order('position');

        $ids = [];
        foreach ($this->groupTable->selectWith($select) as $row) {
            $ids[] = (int)$row['id'];
        }

        return $ids;
    }
}

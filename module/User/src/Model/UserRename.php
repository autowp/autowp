<?php

namespace Autowp\User\Model;

use Autowp\Commons\Db\Table\Row;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

class UserRename
{
    private TableGateway $table;

    public function __construct(TableGateway $table)
    {
        $this->table = $table;
    }

    public function garbageCollect()
    {
        return (int) $this->table->delete([
            'date < DATE_SUB(NOW(), INTERVAL 3 MONTH)',
        ]);
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function add(int $userId, string $oldName, string $newName): void
    {
        $this->table->insert([
            'user_id'  => $userId,
            'old_name' => $oldName,
            'new_name' => $newName,
            'date'     => new Sql\Expression('NOW()'),
        ]);
    }

    public function getRenames(int $userId): array
    {
        $select = new Sql\Select($this->table->getTable());
        $select->where(['user_id' => $userId])
            ->order('date DESC');

        $result = [];
        foreach ($this->table->selectWith($select) as $row) {
            $result[] = [
                'old_name' => $row['old_name'],
                'new_name' => $row['new_name'],
                'date'     => Row::getDateTimeByColumnType('timestamp', $row['date']),
            ];
        }

        return $result;
    }
}

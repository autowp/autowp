<?php

namespace Application\Model;

use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function Autowp\Commons\currentFromResultSetInterface;

class UserPicture
{
    private TableGateway $pictureTable;

    private TableGateway $userTable;

    public function __construct(TableGateway $pictureTable, TableGateway $userTable)
    {
        $this->pictureTable = $pictureTable;
        $this->userTable    = $userTable;
    }

    public function refreshAllPicturesCount(): void
    {
        $select = new Sql\Select($this->pictureTable->getTable());
        $select->columns([
            'owner_id',
            'count' => new Sql\Expression('count(1)'),
        ])
            ->where(['status' => Picture::STATUS_ACCEPTED])
            ->group(['owner_id']);

        $userIds = [];
        foreach ($this->pictureTable->selectWith($select) as $row) {
            $userIds[] = $row['owner_id'];
            $this->userTable->update([
                'pictures_total' => $row['count'],
            ], [
                'id' => $row['owner_id'],
            ]);
        }

        $filter = [];
        if ($userIds) {
            $filter = [
                new Sql\Predicate\NotIn('id', $userIds),
            ];
        }

        $this->userTable->update([
            'pictures_total' => 0,
        ], $filter);
    }

    /**
     * @throws Exception
     */
    public function refreshPicturesCount(int $userId): void
    {
        $select = new Sql\Select($this->pictureTable->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->where([
                'owner_id' => $userId,
                'status'   => Picture::STATUS_ACCEPTED,
            ]);

        $row = currentFromResultSetInterface($this->pictureTable->selectWith($select));

        $this->userTable->update([
            'pictures_total' => $row ? $row['count'] : 0,
        ], [
            'id' => $userId,
        ]);
    }

    public function incrementUploads(int $userId): void
    {
        $this->userTable->update([
            'pictures_added' => new Sql\Expression('pictures_added+1'),
        ], [
            'id' => $userId,
        ]);
    }
}

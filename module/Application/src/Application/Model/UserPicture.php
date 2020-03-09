<?php

namespace Application\Model;

use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

class UserPicture
{
    private TableGateway $pictureTable;

    private TableGateway $userTable;

    public function __construct(TableGateway $pictureTable, TableGateway $userTable)
    {
        $this->pictureTable = $pictureTable;
        $this->userTable    = $userTable;
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     */
    public function refreshAllPicturesCount()
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
     * @suppress PhanDeprecatedFunction
     */
    public function refreshPicturesCount(int $userId): void
    {
        $select = new Sql\Select($this->pictureTable->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->where([
                'owner_id' => $userId,
                'status'   => Picture::STATUS_ACCEPTED,
            ]);

        $row = $this->pictureTable->selectWith($select)->current();

        $this->userTable->update([
            'pictures_total' => $row ? $row['count'] : 0,
        ], [
            'id' => (int) $userId,
        ]);
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function incrementUploads(int $userId): void
    {
        $this->userTable->update([
            'pictures_added' => new Sql\Expression('pictures_added+1'),
        ], [
            'id' => (int) $userId,
        ]);
    }
}

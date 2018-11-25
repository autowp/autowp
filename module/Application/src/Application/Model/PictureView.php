<?php

namespace Application\Model;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

class PictureView
{
    /**
     * @var TableGateway
     */
    private $table;

    public function __construct(TableGateway $table)
    {
        $this->table = $table;
    }

    public function inc(int $pictureId)
    {
        $sql = '
            INSERT INTO picture_view (picture_id, views)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE views=views+1
        ';

        $adapter = $this->table->getAdapter();
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $stmt = $adapter->query($sql);
        $stmt->execute([$pictureId]);
    }

    public function get(int $pictureId): int
    {
        $row = $this->table->select([
            'picture_id' => $pictureId
        ])->current();

        return $row ? (int)$row['views'] : 0;
    }

    public function getValues(array $ids): array
    {
        if (count($ids) <= 0) {
            return [];
        }

        $rows = $this->table->select([
            new Sql\Predicate\In('picture_id', $ids)
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['picture_id']] = (int)$row['views'];
        }

        return $result;
    }
}

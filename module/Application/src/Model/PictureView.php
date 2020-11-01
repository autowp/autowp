<?php

namespace Application\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function Autowp\Commons\currentFromResultSetInterface;
use function count;

class PictureView
{
    private TableGateway $table;

    public function __construct(TableGateway $table)
    {
        $this->table = $table;
    }

    public function inc(int $pictureId): void
    {
        $sql = '
            INSERT INTO picture_view (picture_id, views)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE views=views+1
        ';

        /** @var Adapter $adapter */
        $adapter = $this->table->getAdapter();
        $stmt    = $adapter->query($sql);
        $stmt->execute([$pictureId]);
    }

    public function get(int $pictureId): int
    {
        $row = currentFromResultSetInterface($this->table->select(['picture_id' => $pictureId]));

        return $row ? (int) $row['views'] : 0;
    }

    /**
     * @param int[] $ids
     * @return int[]
     */
    public function getValues(array $ids): array
    {
        if (count($ids) <= 0) {
            return [];
        }

        $rows = $this->table->select([
            new Sql\Predicate\In('picture_id', $ids),
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['picture_id']] = (int) $row['views'];
        }

        return $result;
    }
}

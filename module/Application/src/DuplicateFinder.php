<?php

declare(strict_types=1);

namespace Application;

use Application\Service\RabbitMQ;
use Exception;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Json\Json;

use function Autowp\Commons\currentFromResultSetInterface;

class DuplicateFinder
{
    private RabbitMQ $rabbitmq;

    private TableGateway $distanceTable;

    public function __construct(RabbitMQ $rabbitmq, TableGateway $distanceTable)
    {
        $this->rabbitmq      = $rabbitmq;
        $this->distanceTable = $distanceTable;
    }

    /**
     * @throws Exception
     */
    public function indexImage(int $id, string $url): void
    {
        $this->rabbitmq->send('duplicate_finder', Json::encode([
            'picture_id' => $id,
            'url'        => $url,
        ]));
    }

    /**
     * @throws Exception
     */
    public function findSimilar(int $id): ?array
    {
        $row = currentFromResultSetInterface($this->distanceTable->select(function (Select $select) use ($id): void {
            $select
                ->columns([
                    'dst_picture_id',
                    'distance',
                ])
                ->where([
                    'src_picture_id' => $id,
                    'src_picture_id <> dst_picture_id',
                    'not hide',
                ])
                ->order('distance ASC')
                ->limit(1);
        }));

        if (! $row) {
            return null;
        }

        return [
            'picture_id' => $row['dst_picture_id'],
            'distance'   => $row['distance'],
        ];
    }
}

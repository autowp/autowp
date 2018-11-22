<?php

declare(strict_types=1);

namespace Application;

use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\TableGateway;
use Zend\Json\Json;

use Application\Service\RabbitMQ;

class DuplicateFinder
{
    /**
     * @var RabbitMQ
     */
    private $rabbitmq;

    /**
     * @var TableGateway
     */
    private $distanceTable;

    public function __construct(RabbitMQ $rabbitmq, TableGateway $distanceTable)
    {
        $this->rabbitmq = $rabbitmq;
        $this->distanceTable = $distanceTable;
    }

    public function indexImage(int $id): void
    {
        $this->rabbitmq->send('duplicate_finder', Json::encode([
            'picture_id' => $id
        ]));
    }

    public function findSimilar($id)
    {
        $row = $this->distanceTable->select(
            /**
             * @suppress PhanPluginMixedKeyNoKey
             */
            function (Select $select) use ($id) {
                $select
                    ->columns([
                        'dst_picture_id',
                        'distance'
                    ])
                    ->where([
                        'src_picture_id' => $id,
                        'src_picture_id <> dst_picture_id',
                        'not hide'
                    ])
                    ->order('distance ASC')
                    ->limit(1);
            }
        )->current();

        if (! $row) {
            return null;
        }

        return [
            'picture_id' => $row['dst_picture_id'],
            'distance'   => $row['distance']
        ];
    }

    public function hideSimilar($srcId, $dstId)
    {
        $this->distanceTable->update([
            'hide' => 1
        ], [
            'src_picture_id' => $srcId,
            'dst_picture_id' => $dstId
        ]);
        $this->distanceTable->update([
            'hide' => 1
        ], [
            'src_picture_id' => $dstId,
            'dst_picture_id' => $srcId
        ]);
    }
}

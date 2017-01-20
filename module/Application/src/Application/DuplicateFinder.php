<?php

namespace Application;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;
use Zend\Db\TableGateway\TableGateway;

use Jenssegers\ImageHash\ImageHash;

use InvalidArgumentException;

class DuplicateFinder
{
    private $threshold = 3;

    /**
     * @var TableGateway
     */
    private $hashTable;

    /**
     * @var TableGateway
     */
    private $distanceTable;

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->hashTable = new TableGateway('df_hash', $this->adapter);
        $this->distanceTable = new TableGateway('df_distance', $this->adapter);
    }

    public function indexImage($id, $filepath)
    {
        $id = (int)$id;

        if (! $id) {
            throw new InvalidArgumentException("Invalid id provided");
        }

        $row = $this->hashTable->select([
            'picture_id' => $id
        ])->current();
        if (! $row) {
            $hasher = new ImageHash(null, ImageHash::DECIMAL);
            try {
                $hash = $hasher->hash($filepath);
            } catch (InvalidArgumentException $e) {
                return null;
            }
            $this->hashTable->insert([
                'picture_id' => $id,
                'hash'       => $hash
            ]);
        } else {
            $this->hashTable->update([
                'hash'       => $hash
            ], [
                'picture_id' => $id
            ]);
        }

        $this->updateDistance($id);

        return null;
    }

    public function updateDistance($id)
    {
        $id = (int)$id;

        if (! $id) {
            throw new InvalidArgumentException("Invalid id provided");
        }

        $row = $this->hashTable->select([
            'picture_id' => $id
        ])->current();

        if (! $row) {
            return;
        }

        $similarRows = $this->hashTable->select(function (Select $select) use ($id, $row) {
            $select
                ->columns([
                    'picture_id',
                    'distance' => new Expression('BIT_COUNT(hash ^ ?)', [$row['hash']]),
                ])
                ->where([
                    'picture_id <> ?' => $id
                ])
                ->having([
                    'distance <= ?' => $this->threshold
                ]);
        });

        foreach ($similarRows as $similarRow) {
            try {
                $this->distanceTable->insert([
                    'src_picture_id' => $id,
                    'dst_picture_id' => $similarRow['picture_id'],
                    'distance'       => $similarRow['distance']
                ]);
            } catch (InvalidQueryException $e) {
            }
            try {
                $this->distanceTable->insert([
                    'src_picture_id' => $similarRow['picture_id'],
                    'dst_picture_id' => $id,
                    'distance'       => $similarRow['distance']
                ]);
            } catch (InvalidQueryException $e) {
            }
        }
    }

    public function findSimilar($id)
    {
        $row = $this->distanceTable->select(function (Select $select) use ($id) {
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
        })->current();

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

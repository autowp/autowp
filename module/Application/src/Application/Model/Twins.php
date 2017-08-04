<?php

namespace Application\Model;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

use Application\Model\Brand;
use Application\Model\DbTable;

use Zend_Db_Select;

class Twins
{
    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    /**
     * @var TableGateway
     */
    private $itemTable;

    public function __construct(
        DbTable\Picture $pictureTable,
        TableGateway $itemTable,
        Brand $brand
    ) {
        $this->pictureTable = $pictureTable;
        $this->itemTable = $itemTable;
        $this->brand = $brand;
    }

    /**
     * @param array $options
     * @return array
     */
    public function getBrands(array $options)
    {
        $defaults = [
            'language' => 'en',
            'limit'    => null
        ];
        $options = array_replace($defaults, $options);

        $limit = $options['limit'];

        return $this->brand->getList([
            'language' => $options['language'],
            'columns'  => [
                'count'     => new Sql\Expression('count(distinct twins.id)'),
                'new_count' => new Sql\Expression(
                    'count(distinct if(twins.add_datetime > date_sub(NOW(), INTERVAL 7 DAY), twins.id, null))'
                ),
            ]
        ], function (Sql\Select $select) use ($limit) {
            $select
                ->join(['ipc1' => 'item_parent_cache'], 'item.id = ipc1.parent_id', [])
                ->join('item_parent', 'ipc1.item_id = item_parent.item_id', [])
                ->join(['twins' => 'item'], 'item_parent.parent_id = twins.id', [])
                ->where(['twins.item_type_id' => Item::TWINS])
                ->group('item.id');

            if ($limit > 0) {
                $select
                    ->order('count desc')
                    ->limit($limit);
            }
        });
    }

    /**
     * @param int|array $groupId
     * @return int
     */
    public function getGroupPicturesCount($groupId)
    {
        $db = $this->pictureTable->getAdapter();

        $select = $db->select()
            ->from($this->pictureTable->info('name'), null)
            ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null);

        if (is_array($groupId)) {
            if ($groupId) {
                $select
                    ->columns(['item_parent_cache.parent_id', 'COUNT(1)'])
                    ->group('item_parent_cache.parent_id')
                    ->where('item_parent_cache.parent_id in (?)', $groupId);

                $result = $db->fetchPairs($select);
            } else {
                $result = [];
            }
        } else {
            $select
                ->columns('COUNT(1)')
                ->where('item_parent_cache.parent_id = ?', (int)$groupId);

            $result = (int)$db->fetchOne($select);
        }

        return $result;
    }

    public function getGroupBrandIds(int $groupId): array
    {
        $select = new Sql\Select($this->itemTable->getTable());
        $select->columns(['id'])
            ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
            ->join(['vehicle' => 'item'], 'item_parent_cache.item_id = vehicle.id', [])
            ->where([
                'item.item_type_id'         => Item::BRAND,
                'item_parent_cache.item_id' => $groupId,
                'vehicle.item_type_id'      => Item::TWINS,
            ]);

        $result = [];
        foreach ($this->itemTable->selectWith($select) as $row) {
            $result[] = (int)$row['id'];
        }

        return $result;
    }

    public function getTotalBrandsCount(): int
    {
        $select = new Sql\Select($this->itemTable->getTable());
        $select->columns(['count' => new Sql\Expression('count(distinct item.id)')])
            ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
            ->join(['vehicle' => 'item'], 'item_parent_cache.item_id = vehicle.id', [])
            ->where([
                'item.item_type_id'    => Item::BRAND,
                'vehicle.item_type_id' => Item::TWINS
            ]);

        $row = $this->itemTable->selectWith($select)->current();
        return $row ? (int)$row['count'] : 0;
    }

    /**
     * @param array $options
     * @return \Zend\Paginator\Paginator
     */
    public function getGroupsPaginator(array $options = [])
    {
        $defaults = [
            'brandId' => null
        ];
        $options = array_merge($defaults, $options);

        $brandId = (int)$options['brandId'];

        $select = new Sql\Select($this->itemTable->getTable());
        $select->where(['item.item_type_id' => Item::TWINS])
            ->order('item.add_datetime desc');

        if ($options['brandId']) {
            $select
                ->join('item_parent', 'item.id = item_parent.parent_id', [])
                ->join('item_parent_cache', 'item_parent.item_id = item_parent_cache.item_id', [])
                ->join(['brand' => 'item'], 'item_parent_cache.parent_id = brand.id', [])
                ->where([
                    'brand.item_type_id'          => Item::BRAND,
                    'item_parent_cache.parent_id' => $brandId
                ])
                ->group('item.id');
        }

        return new \Zend\Paginator\Paginator(
            new \Zend\Paginator\Adapter\DbSelect($select, $this->itemTable->getAdapter())
        );
    }

    public function getGroupCars(int $groupId): array
    {
        $select = new Sql\Select($this->itemTable->getTable());
        $select->join('item_parent', 'item.id = item_parent.item_id', [])
            ->where(['item_parent.parent_id' => $groupId])
            ->order('name');

        $result = [];
        foreach ($this->itemTable->selectWith($select) as $row) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @param int $groupId
     * @param array $options
     * @return Zend_Db_Select
     */
    public function getGroupPicturesSelect($groupId, array $options = [])
    {
        $defaults = [
            'ordering' => null
        ];
        $options = array_merge($defaults, $options);

        $ordering = $options['ordering'];

        $select = $this->pictureTable->select(true)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
            ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
            ->where('item_parent_cache.parent_id = ?', (int)$groupId);

        if ($ordering) {
            $select->order($ordering);
        }

        return $select;
    }

    /**
     * @param int $groupId
     * @return NULL|array
     */
    public function getGroup(int $groupId)
    {
        $row = $this->itemTable->select([
            'id'           => $groupId,
            'item_type_id' => Item::TWINS
        ])->current();
        if (! $row) {
            return null;
        }

        return [
            'id'         => $row['id'],
            'name'       => $row['name'],
            'begin_year' => $row['begin_year'],
            'end_year'   => $row['end_year']
        ];
    }

    /**
     * @param int $itemId
     * @return array
     */
    public function getCarGroups(int $itemId)
    {
        $select = new Sql\Select($this->itemTable->getTable());
        $select->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
            ->where([
                'item_parent_cache.item_id' => $itemId,
                'item.item_type_id'         => Item::TWINS
            ])
            ->group('item.id');

        $result = [];
        foreach ($this->itemTable->selectWith($select) as $row) {
            $result[] = [
                'id'   => (int)$row['id'],
                'name' => $row['name']
            ];
        }

        return $result;
    }

    public function getCarsGroups(array $itemIds): array
    {
        if (! $itemIds) {
            return [];
        }

        $select = new Sql\Select($this->itemTable->getTable());
        $select->columns(['id', 'name'])
            ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', ['item_id'])
            ->where([
                'item.item_type_id' => Item::TWINS,
                new Sql\Predicate\In('item_parent_cache.item_id', $itemIds)
            ])
            ->group(['item_parent_cache.item_id', 'item.id']);

        $result = [];
        foreach ($itemIds as $itemId) {
            $result[(int)$itemId] = [];
        }
        foreach ($this->itemTable->selectWith($select) as $row) {
            $itemId = (int)$row['item_id'];
            $result[$itemId][] = [
                'id'   => (int)$row['id'],
                'name' => $row['name']
            ];
        }

        return $result;
    }
}

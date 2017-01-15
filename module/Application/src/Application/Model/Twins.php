<?php

namespace Application\Model;

use Application\Model\DbTable;
use Application\Paginator\Adapter\Zend1DbTableSelect;

use Zend_Db_Expr;
use Zend_Db_Select;

class Twins
{
    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    /**
     * @var DbTable\Item
     */
    private $itemTable;

    /**
     * @return DbTable\Picture
     */
    private function getPictureTable()
    {
        return $this->pictureTable
            ? $this->pictureTable
            : $this->pictureTable = new DbTable\Picture();
    }

    /**
     * @return DbTable\Item
     */
    private function getItemTable()
    {
        return $this->itemTable
            ? $this->itemTable
            : $this->itemTable = new DbTable\Item();
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
        $options = array_merge($defaults, $options);

        $language = $options['language'];
        $limit = $options['limit'];

        $itemTable = $this->getItemTable();
        $db = $itemTable->getAdapter();

        $langExpr = $db->quoteInto('item.id = item_language.item_id and item_language.language = ?', $language);

        $select = $db->select(true)
            ->from('item', [
                'id',
                'name'      => 'IFNULL(item_language.name, item.name)',
                'catname',
                'count'     => new Zend_Db_Expr('count(distinct twins.id)'),
                'new_count' => new Zend_Db_Expr(
                    'count(distinct if(twins.add_datetime > date_sub(NOW(), INTERVAL 7 DAY), twins.id, null))'
                ),
            ])
            ->where('item.item_type_id = ?', DbTable\Item\Type::BRAND)
            ->joinLeft('item_language', $langExpr, null)
            ->join(['ipc1' => 'item_parent_cache'], 'item.id = ipc1.parent_id', null)
            ->join('item_parent', 'ipc1.item_id = item_parent.item_id', null)
            ->join(['twins' => 'item'], 'item_parent.parent_id = twins.id', null)
            ->where('twins.item_type_id = ?', DbTable\Item\Type::TWINS)
            ->group('item.id');

        if ($limit > 0) {
            $select
                ->order('count desc')
                ->limit($limit);
        }

        $brandList = $db->fetchAll($select);

        usort($brandList, function ($a, $b) {
            return strcoll($a['name'], $b['name']);
        });

        return $brandList;
    }

    /**
     * @param int|array $groupId
     * @return int
     */
    public function getGroupPicturesCount($groupId)
    {
        $pictureTable = $this->getPictureTable();

        $db = $pictureTable->getAdapter();

        $select = $db->select()
            ->from($pictureTable->info('name'), null)
            ->where('pictures.status IN (?)', [
                DbTable\Picture::STATUS_ACCEPTED,
                DbTable\Picture::STATUS_NEW
            ])
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

    /**
     * @param int $groupId
     * @return int[]
     */
    public function getGroupBrandIds($groupId)
    {
        $itemTable = $this->getItemTable();
        $brandAdapter = $itemTable->getAdapter();
        return $brandAdapter->fetchCol(
            $brandAdapter->select()
                ->from($itemTable->info('name'), 'id')
                ->where('item.item_type_id = ?', DbTable\Item\Type::BRAND)
                ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                ->where('item_parent_cache.item_id = ?', $groupId)
                ->join(['vehicle' => 'item'], 'item_parent_cache.item_id = vehicle.id', null)
                ->where('vehicle.item_type_id = ?', DbTable\Item\Type::TWINS)
        );
    }

    /**
     * @return int
     */
    public function getTotalBrandsCount()
    {
        $itemTable = $this->getItemTable();
        $db = $itemTable->getAdapter();

        return (int)$db->fetchOne(
            $db->select(true)
                ->from('item', 'count(distinct item.id)')
                ->where('item.item_type_id = ?', DbTable\Item\Type::BRAND)
                ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                ->join(['vehicle' => 'item'], 'item_parent_cache.item_id = vehicle.id', null)
                ->where('vehicle.item_type_id = ?', DbTable\Item\Type::TWINS)
        );
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

        $select = $this->getItemTable()->select(true)
            ->where('item.item_type_id = ?', DbTable\Item\Type::TWINS)
            ->order('item.add_datetime desc');

        if ($options['brandId']) {
            $select
                ->join('item_parent', 'item.id = item_parent.parent_id', null)
                ->join('item_parent_cache', 'item_parent.item_id = item_parent_cache.item_id', null)
                ->join(['brand' => 'item'], 'item_parent_cache.parent_id = brand.id', null)
                ->where('brand.item_type_id = ?', DbTable\Item\Type::BRAND)
                ->where('item_parent_cache.parent_id = ?', $brandId)
                ->group('item.id');
        }

        return new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );
    }

    /**
     * @param int $groupId
     * @return DbTable\Item\Row[]
     */
    public function getGroupCars($groupId)
    {
        $itemTable = $this->getItemTable();
        return $itemTable->fetchAll(
            $itemTable->select(true)
                ->join('item_parent', 'item.id = item_parent.item_id', null)
                ->where('item_parent.parent_id = ?', (int)$groupId)
                ->order('name')
        );
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

        $select = $this->getPictureTable()->select(true)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
            ->where('pictures.status IN (?)', [
                DbTable\Picture::STATUS_NEW,
                DbTable\Picture::STATUS_ACCEPTED
            ])
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
    public function getGroup($groupId)
    {
        $row = $this->getItemTable()->fetchRow([
            'id = ?' => $groupId,
            'item_type_id = ?' => DbTable\Item\Type::TWINS
        ]);
        if (! $row) {
            return null;
        }

        return [
            'id'      => $row->id,
            'name'    => $row->name,
            'text_id' => null//$row->text_id
        ];
    }

    /**
     * @param int $carId
     * @return array
     */
    public function getCarGroups($carId)
    {
        $groupTable = $this->getItemTable();

        $rows = $groupTable->fetchAll(
            $groupTable->select(true)
                ->where('item.item_type_id = ?', DbTable\Item\Type::TWINS)
                ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                ->where('item_parent_cache.item_id = ?', (int)$carId)
                ->group('item.id')
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id'   => $row->id,
                'name' => $row->name
            ];
        }

        return $result;
    }

    /**
     * @param array $carIds
     * @return array
     */
    public function getCarsGroups(array $carIds)
    {
        $groupTable = $this->getItemTable();

        $db = $groupTable->getAdapter();

        $rows = $db->fetchAll(
            $db->select()
                ->from($groupTable->info('name'), ['id', 'name'])
                ->where('item.item_type_id = ?', DbTable\Item\Type::TWINS)
                ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', 'item_id')
                ->where('item_parent_cache.item_id IN (?)', $carIds)
                ->group(['item_parent_cache.item_id', 'item.id'])
        );

        $result = [];
        foreach ($carIds as $carId) {
            $result[(int)$carId] = [];
        }
        foreach ($rows as $row) {
            $carId = (int)$row['item_id'];
            $result[$carId][] = [
                'id'   => $row['id'],
                'name' => $row['name']
            ];
        }

        return $result;
    }
}

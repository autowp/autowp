<?php

namespace Application\Model;

use Application\Paginator\Adapter\Zend1DbTableSelect;

use Twins_Groups;
use Brands;
use Picture;
use Cars;

use Zend_Db_Expr;
use Zend_Db_Select;

class Twins
{
    /**
     * @var Twins_Groups
     */
    private $groupsTable;

    /**
     * @var Brands
     */
    private $brandTable;

    /**
     * @var Picture
     */
    private $pictureTable;

    /**
     * @var Cars
     */
    private $carTable;

    /**
     * @return Twins_Groups
     */
    private function getGroupsTable()
    {
        return $this->groupsTable
            ? $this->groupsTable
            : $this->groupsTable = new Twins_Groups();
    }

    /**
     * @return Brands
     */
    private function getBrandTable()
    {
        return $this->brandTable
            ? $this->brandTable
            : $this->brandTable = new Brands();
    }

    /**
     * @return Picture
     */
    private function getPictureTable()
    {
        return $this->pictureTable
            ? $this->pictureTable
            : $this->pictureTable = new Picture();
    }

    /**
     * @return Cars
     */
    private function getCarTable()
    {
        return $this->carTable
            ? $this->carTable
            : $this->carTable = new Cars();
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

        $brandTable = $this->getBrandTable();
        $db = $brandTable->getAdapter();

        $langExpr = $db->quoteInto('brands.id = brand_language.brand_id and brand_language.language = ?', $language);

        $select = $db->select(true)
            ->from('brands', [
                'id',
                'name'      => 'IFNULL(brand_language.name, brands.caption)',
                'folder'    => 'folder',
                'count'     => new Zend_Db_Expr('count(distinct tg.id)'),
                'new_count' => new Zend_Db_Expr('count(distinct if(tg.add_datetime > date_sub(NOW(), INTERVAL 7 DAY), tg.id, null))'),
            ])
            ->joinLeft('brand_language', $langExpr, null)
            ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
            ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
            ->join(
                ['tgc' => 'twins_groups_cars'],
                'car_parent_cache.car_id = tgc.car_id',
                null
            )
            ->join(
                ['tg' => 'twins_groups'],
                'tgc.twins_group_id = tg.id',
                null
            )
            ->group('brands.id');

        if ($limit > 0) {
            $select
                ->order('count desc')
                ->limit($limit);
        }

        $brandList = $db->fetchAll($select);

        usort($brandList, function($a, $b) {
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
            ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
            ->join(['tgc' => 'twins_groups_cars'], 'tgc.car_id = car_parent_cache.parent_id', null)
            ->where('pictures.type = ?', Picture::VEHICLE_TYPE_ID)
            ->where('pictures.status IN (?)', [Picture::STATUS_ACCEPTED, Picture::STATUS_NEW]);

        if (is_array($groupId)) {

            if ($groupId) {

                $select
                    ->columns(['tgc.twins_group_id', 'COUNT(1)'])
                    ->group('tgc.twins_group_id')
                    ->where('tgc.twins_group_id in (?)', $groupId);

                $result = $db->fetchPairs($select);

            } else {

                $result = [];
            }

        } else {
            $select
                ->columns('COUNT(1)')
                ->where('tgc.twins_group_id = ?', (int)$groupId);

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
        $brandTable = $this->getBrandTable();
        $brandAdapter = $brandTable->getAdapter();
        return $brandAdapter->fetchCol(
            $brandAdapter->select()
                ->from($brandTable->info('name'), 'id')
                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->join(['tgc' => 'twins_groups_cars'], 'car_parent_cache.car_id = tgc.car_id', null)
                ->where('tgc.twins_group_id = ?', $groupId)
        );
    }

    /**
     * @return int
     */
    public function getTotalBrandsCount()
    {
        $brandTable = $this->getBrandTable();
        $db = $brandTable->getAdapter();

        return (int)$db->fetchOne(
            $db->select(true)
                ->from('brands', 'count(distinct brands.id)')
                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->join(
                    ['tgc' => 'twins_groups_cars'],
                    'car_parent_cache.car_id = tgc.car_id',
                    null
                )
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

        $select = $this->getGroupsTable()->select(true)
            ->order('twins_groups.add_datetime desc');

        if ($options['brandId']) {
            $select
                ->join(['tgc' => 'twins_groups_cars'], 'twins_groups.id = tgc.twins_group_id', null)
                ->join('car_parent_cache', 'tgc.car_id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brandId)
                ->group('twins_groups.id');
        }

        return new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );
    }

    /**
     * @param int $groupId
     * @return Car_Row[]
     */
    public function getGroupCars($groupId)
    {
        $carTable = $this->getCarTable();
        return $carTable->fetchAll(
            $carTable->select(true)
                ->join('twins_groups_cars', 'cars.id = twins_groups_cars.car_id', null)
                ->where('twins_groups_cars.twins_group_id = ?', (int)$groupId)
                ->order('caption')
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
            ->where('pictures.type = ?', Picture::VEHICLE_TYPE_ID)
            ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
            ->join(['tgc' => 'twins_groups_cars'], 'tgc.car_id = car_parent_cache.parent_id', null)
            ->where('pictures.status IN (?)', [Picture::STATUS_NEW, Picture::STATUS_ACCEPTED])
            ->where('tgc.twins_group_id = ?', (int)$groupId);

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
        $row = $this->getGroupsTable()->find($groupId)->current();
        if (!$row) {
            return null;
        }

        return [
            'id'      => $row->id,
            'name'    => $row->name,
            'text_id' => $row->text_id
        ];
    }

    /**
     * @param int $carId
     * @return array
     */
    public function getCarGroups($carId)
    {
        $groupTable = $this->getGroupsTable();

        $rows = $groupTable->fetchAll(
            $groupTable->select(true)
                ->join('twins_groups_cars', 'twins_groups.id = twins_groups_cars.twins_group_id', null)
                ->join('car_parent_cache', 'twins_groups_cars.car_id=car_parent_cache.parent_id', null)
                ->where('car_parent_cache.car_id = ?', (int)$carId)
                ->group('twins_groups.id')
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
        $groupTable = $this->getGroupsTable();

        $db = $groupTable->getAdapter();

        $rows = $db->fetchAll(
            $db->select()
                ->from($groupTable->info('name'), ['id', 'name'])
                ->join('twins_groups_cars', 'twins_groups.id = twins_groups_cars.twins_group_id', null)
                ->join('car_parent_cache', 'twins_groups_cars.car_id = car_parent_cache.parent_id', 'car_id')
                ->where('car_parent_cache.car_id IN (?)', $carIds)
                ->group(['car_parent_cache.car_id', 'twins_groups.id'])
        );

        $result = [];
        foreach ($carIds as $carId) {
            $result[(int)$carId] = [];
        }
        foreach ($rows as $row) {
            $carId = (int)$row['car_id'];
            $result[$carId][] = [
                'id'   => $row['id'],
                'name' => $row['name']
            ];
        }

        return $result;
    }
}
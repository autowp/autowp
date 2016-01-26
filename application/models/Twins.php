<?php

class Twins
{
    /**
     * @var Twins_Groups
     */
    protected $_groupsTable;

    /**
     * @var Brands
     */
    protected $_brandTable;

    /**
     * @var Picture
     */
    protected $_pictureTable;

    /**
     * @var Cars
     */
    protected $_carTable;

    /**
     * @return Twins_Groups
     */
    protected function _getGroupsTable()
    {
        return $this->_groupsTable
            ? $this->_groupsTable
            : $this->_groupsTable = new Twins_Groups();
    }

    /**
     * @return Brands
     */
    protected function _getBrandTable()
    {
        return $this->_brandTable
            ? $this->_brandTable
            : $this->_brandTable = new Brands();
    }

    /**
     * @return Picture
     */
    protected function _getPictureTable()
    {
        return $this->_pictureTable
            ? $this->_pictureTable
            : $this->_pictureTable = new Picture();
    }

    /**
     * @return Cars
     */
    protected function _getCarTable()
    {
        return $this->_carTable
            ? $this->_carTable
            : $this->_carTable = new Cars();
    }

    /**
     * @param array $options
     * @return array
     */
    public function getBrands(array $options)
    {
        $defaults = array(
            'language' => 'en',
            'limit'    => null
        );
        $options = array_merge($defaults, $options);

        $language = $options['language'];
        $limit = $options['limit'];

        $brandTable = $this->_getBrandTable();
        $db = $brandTable->getAdapter();

        $langExpr = $db->quoteInto('brands.id = brand_language.brand_id and brand_language.language = ?', $language);

        $select = $db->select(true)
            ->from('brands', array(
                'id',
                'name'      => 'IFNULL(brand_language.name, brands.caption)',
                'folder'    => 'folder',
                'count'     => new Zend_Db_Expr('count(distinct tg.id)'),
                'new_count' => new Zend_Db_Expr('count(distinct if(tg.add_datetime > date_sub(NOW(), INTERVAL 7 DAY), tg.id, null))'),
            ))
            ->joinLeft('brand_language', $langExpr, null)
            ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
            ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
            ->join(
                array('tgc' => 'twins_groups_cars'),
                'car_parent_cache.car_id = tgc.car_id',
                null
            )
            ->join(
                array('tg' => 'twins_groups'),
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
        $pictureTable = $this->_getPictureTable();

        $db = $pictureTable->getAdapter();

        $select = $db->select()
            ->from($pictureTable->info('name'), null)
            ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
            ->join(array('tgc' => 'twins_groups_cars'), 'tgc.car_id = car_parent_cache.parent_id', null)
            ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
            ->where('pictures.status IN (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW));

        if (is_array($groupId)) {

            if ($groupId) {

                $select
                    ->columns(array('tgc.twins_group_id', 'COUNT(1)'))
                    ->group('tgc.twins_group_id')
                    ->where('tgc.twins_group_id in (?)', $groupId);

                $result = $db->fetchPairs($select);

            } else {

                $result = array();
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
        $brandTable = $this->_getBrandTable();
        $brandAdapter = $brandTable->getAdapter();
        return $brandAdapter->fetchCol(
            $brandAdapter->select()
                ->from($brandTable->info('name'), 'id')
                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->join(array('tgc' => 'twins_groups_cars'), 'car_parent_cache.car_id = tgc.car_id', null)
                ->where('tgc.twins_group_id = ?', $groupId)
        );
    }

    /**
     * @return int
     */
    public function getTotalBrandsCount()
    {
        $brandTable = $this->_getBrandTable();
        $db = $brandTable->getAdapter();

        return (int)$db->fetchOne(
            $db->select(true)
                ->from('brands', 'count(distinct brands.id)')
                ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                ->join(
                    array('tgc' => 'twins_groups_cars'),
                    'car_parent_cache.car_id = tgc.car_id',
                    null
                )
        );
    }

    /**
     * @param array $options
     * @return Zend_Paginator
     */
    public function getGroupsPaginator(array $options = array())
    {
        $defaults = array(
            'brandId' => null
        );
        $options = array_merge($defaults, $options);

        $brandId = (int)$options['brandId'];

        $select = $this->_getGroupsTable()->select(true)
            ->order('twins_groups.add_datetime desc');

        if ($options['brandId']) {
            $select
                ->join(array('tgc' => 'twins_groups_cars'), 'twins_groups.id = tgc.twins_group_id', null)
                ->join('car_parent_cache', 'tgc.car_id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brandId)
                ->group('twins_groups.id');
        }

        return Zend_Paginator::factory($select);
    }

    /**
     * @param int $groupId
     * @return Cars_Row[]
     */
    public function getGroupCars($groupId)
    {
        $carTable = $this->_getCarTable();
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
     * @return Zend_Paginator
     */
    public function getGroupPicturesSelect($groupId, array $options = array())
    {
        $defaults = array(
            'ordering' => null
        );
        $options = array_merge($defaults, $options);

        $ordering = $options['ordering'];

        $select = $this->_getPictureTable()->select(true)
            ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
            ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
            ->join(array('tgc' => 'twins_groups_cars'), 'tgc.car_id = car_parent_cache.parent_id', null)
            ->where('pictures.status IN (?)', array(Picture::STATUS_NEW, Picture::STATUS_ACCEPTED))
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
        $row = $this->_getGroupsTable()->find($groupId)->current();
        if (!$row) {
            return null;
        }

        return array(
            'id'      => $row->id,
            'name'    => $row->name,
            'text_id' => $row->text_id
        );
    }

    /**
     * @param int $carId
     * @return array
     */
    public function getCarGroups($carId)
    {
        $groupTable = $this->_getGroupsTable();

        $rows = $groupTable->fetchAll(
            $groupTable->select(true)
                ->join('twins_groups_cars', 'twins_groups.id = twins_groups_cars.twins_group_id', null)
                ->join('car_parent_cache', 'twins_groups_cars.car_id=car_parent_cache.parent_id', null)
                ->where('car_parent_cache.car_id = ?', (int)$carId)
                ->group('twins_groups.id')
        );

        $result = array();
        foreach ($rows as $row) {
            $result[] = array(
                'id'   => $row->id,
                'name' => $row->name
            );
        }

        return $result;
    }

    /**
     * @param array $carIds
     * @return array
     */
    public function getCarsGroups(array $carIds)
    {
        $groupTable = $this->_getGroupsTable();

        $db = $groupTable->getAdapter();

        $rows = $db->fetchAll(
            $db->select()
                ->from($groupTable->info('name'), array('id', 'name'))
                ->join('twins_groups_cars', 'twins_groups.id = twins_groups_cars.twins_group_id', null)
                ->join('car_parent_cache', 'twins_groups_cars.car_id = car_parent_cache.parent_id', 'car_id')
                ->where('car_parent_cache.car_id IN (?)', $carIds)
                ->group(array('car_parent_cache.car_id', 'twins_groups.id'))
        );

        $result = array();
        foreach ($carIds as $carId) {
            $result[(int)$carId] = array();
        }
        foreach ($rows as $row) {
            $carId = (int)$row['car_id'];
            $result[$carId][] = array(
                'id'   => $row['id'],
                'name' => $row['name']
            );
        }

        return $result;
    }
}
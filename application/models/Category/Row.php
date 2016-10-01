<?php

use Application\Db\Table\Row;

class Category_Row extends Row
{
    private static $categoryCarTable;

    private static function getCategoryCarTable()
    {
        return self::$categoryCarTable
            ? self::$categoryCarTable
            : self::$categoryCarTable = new Category_Car();
    }

    public function findTopPicture()
    {
        $pictures = new Picture();
        return $pictures->fetchRow(
            $pictures->select(true)
                ->join('category_car', 'pictures.car_id=category_car.car_id', null)
                ->where('pictures.type = ?', Picture::VEHICLE_TYPE_ID)
                ->where('pictures.status IN (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
                ->where('category_car.category_id = ?', $this->id)
                ->order(array(
                    new Zend_Db_Expr('pictures.perspective_id = 7 DESC'),
                    new Zend_Db_Expr('pictures.perspective_id = 8 DESC'),
                    new Zend_Db_Expr('pictures.perspective_id = 1 DESC'),
                    'pictures.ratio DESC',
                    'pictures.views DESC'
                ))
                ->limit(1)
        );
    }

    public function getCarsCount(array $options = array())
    {
        $options = array_merge(array(
            'brand' => false
        ), $options);

        $categoryCarTable = self::getCategoryCarTable();
        $db = $categoryCarTable->getAdapter();

        $select = $db->select()
            ->from('cars', new Zend_Db_Expr('COUNT(1)'))
            ->where('not cars.is_group')
            ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
            ->join('category_car', 'car_parent_cache.parent_id = category_car.car_id', null)
            ->join('category_parent', 'category_car.category_id = category_parent.category_id', null)
            ->where('category_parent.parent_id = ?', $this->id);

        return $db->fetchOne($select);
    }

    public function getWeekCarsCount(array $options = array())
    {
        $options = array_merge(array(
            'brand' => false
        ), $options);

        $categoryCarTable = self::getCategoryCarTable();
        $db = $categoryCarTable->getAdapter();

        //TODO: group by cars.id
        $select = $db->select()
            ->from('cars', new Zend_Db_Expr('COUNT(1)'))
            ->where('not cars.is_group')
            ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
            ->join('category_car', 'car_parent_cache.parent_id = category_car.car_id', null)
            ->join('category_parent', 'category_car.category_id = category_parent.category_id', null)
            ->where('category_parent.parent_id = ?', $this->id)
            ->where('category_car.add_datetime > DATE_SUB(NOW(), INTERVAL 7 DAY)');

        return $db->fetchOne($select);
    }

    public function getOwnCarsCount()
    {
        $categoryCarTable = self::getCategoryCarTable();

        $db = $categoryCarTable->getAdapter();
        //TODO: group by cars.id
        $select = $db->select()
            ->from('cars', new Zend_Db_Expr('COUNT(1)'))
            ->where('not cars.is_group')
            ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
            ->join('category_car', 'car_parent_cache.parent_id = category_car.car_id', null)
            ->where('category_car.category_id = ?', $this->id);

        return $db->fetchOne($select);
    }

    public function getWeekOwnCarsCount()
    {
        $categoryCarTable = self::getCategoryCarTable();

        $db = $categoryCarTable->getAdapter();
        //TODO: group by cars.id
        $select = $db->select()
            ->from('cars', new Zend_Db_Expr('COUNT(1)'))
            ->where('not cars.is_group')
            ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
            ->join('category_car', 'car_parent_cache.parent_id = category_car.car_id', null)
            ->where('category_car.category_id = ?', $this->id)
            ->where('category_car.add_datetime > DATE_SUB(NOW(), INTERVAL 7 DAY)');

        return $db->fetchOne($select);
    }
}
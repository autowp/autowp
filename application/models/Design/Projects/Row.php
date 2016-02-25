<?php
class Design_Projects_Row extends Project_Db_Table_Row
{
    public function findCarsByBrand(Zend_Db_Table_Row_Abstract $brand)
    {
        $cars = new Cars();

        return $cars->fetchAll(
            $cars->select(true)
                ->where('cars.design_project_id = ?', $this->id)
                ->join('car_parent_cache', 'cars.id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $brand->id)
        );
    }

    public function getPicturesCount()
    {
        $db = $this->getTable()->getAdapter();

        return $db->fetchOne(
            $db->select()
               ->from('pictures', array(new Zend_Db_Expr('COUNT(1)')))
               ->join('cars', 'pictures.car_id=cars.id', null)
               ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
               ->where('pictures.status IN (?)', array(Picture::STATUS_NEW, Picture::STATUS_ACCEPTED))
               ->where('cars.design_project_id = ?', $this->id)
        );
    }
}
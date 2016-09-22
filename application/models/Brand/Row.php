<?php

class Brand_Row extends Project_Db_Table_Row
{
    /**
     * @var Brand_Language
     */
    protected $_langTable;

    /**
     * @return Brand_Language
     */
    protected function _getLanguageTable()
    {
        return $this->_langTable
            ? $this->_langTable
            : $this->_langTable = new Brand_Language();
    }

    public function getLanguageName($language)
    {
        $langRow = $this->_getLanguageTable()->fetchRow(array(
            'brand_id = ?' => $this->id,
            'language = ?' => $language
        ));

        return $langRow ? $langRow->name : $this->caption;
    }

    /**
     * @deprecated
     * @param bool $absolute
     * @return string
     */
    public function getUrl($absolute = false)
    {
        return ($absolute ? HOST : '/').$this->folder.'/';
    }

    public function getLogoPath()
    {
        return Brands::buildLogoPath($this->logo);
    }

    public function getTotalPicturesCount()
    {
        return $this->carpictures_count + $this->enginepictures_count +
               $this->logopictures_count + $this->mixedpictures_count +
               $this->unsortedpictures_count;
    }

    public function getNewCarsCount()
    {
        $db = $this->getTable()->getAdapter();
        return $db->fetchOne(
            $db->select()
                ->from(array('bcc' => 'brands_cars_cache'), array('COUNT(1)'))
                ->join('cars', 'bcc.car_id=cars.id', null)
                ->where('bcc.brand_id=?', $this->id)
                ->where('cars.add_datetime > DATE_SUB(NOW(), INTERVAL 7 DAY)')
        );
    }

    public function refreshPicturesCount()
    {
        $this->refreshCarPicturesCount();
        $this->refreshLogoPicturesCount();
        $this->refreshMixedPicturesCount();
        $this->refreshUnsortedPicturesCount();
        $this->refreshEnginePicturesCount();
    }

    public function refreshCarPicturesCount()
    {
        $db = $this->getTable()->getAdapter();

        $this->carpictures_count = (int)$db->fetchOne(
            $db->select()
                ->from('pictures', new Zend_Db_Expr('COUNT(pictures.id)'))
                ->where('pictures.type = ?', Picture::CAR_TYPE_ID)
                ->where('pictures.status IN (?)', array(Picture::STATUS_ACCEPTED, Picture::STATUS_NEW))
                ->join('car_parent_cache', 'pictures.car_id = car_parent_cache.car_id', null)
                ->join('brands_cars', 'car_parent_cache.parent_id = brands_cars.car_id', null)
                ->where('brands_cars.brand_id = ?', $this->id)
        );
        $this->save();
    }

    public function refreshLogoPicturesCount()
    {
        $db = $this->getTable()->getAdapter();
        $sql = 'SELECT COUNT(id) FROM pictures '.
               'WHERE brand_id=? AND type=? AND pictures.status IN (?, ?)';
        $this->logopictures_count = (int)$db->fetchOne($sql, array(
            $this->id, Picture::LOGO_TYPE_ID,
            Picture::STATUS_ACCEPTED, Picture::STATUS_NEW));
        $this->save();
    }

    public function refreshMixedPicturesCount()
    {
        $db = $this->getTable()->getAdapter();
        $sql = 'SELECT COUNT(id) FROM pictures '.
               'WHERE brand_id=? AND type=? AND pictures.status IN (?, ?)';
        $this->mixedpictures_count = (int)$db->fetchOne($sql, array(
            $this->id, Picture::MIXED_TYPE_ID,
            Picture::STATUS_ACCEPTED, Picture::STATUS_NEW));
        $this->save();
    }

    public function refreshEnginePicturesCount()
    {
        $db = $this->getTable()->getAdapter();
        $this->enginepictures_count = (int)$db->fetchOne('
            SELECT COUNT(DISTINCT pictures.id)
            FROM pictures
                INNER JOIN engine_parent_cache ON pictures.engine_id = engine_parent_cache.engine_id
                INNER JOIN brand_engine ON engine_parent_cache.parent_id = brand_engine.engine_id
            WHERE brand_engine.brand_id = ? and pictures.type = ?
                AND pictures.status IN (?, ?)
        ', array(
            $this->id, Picture::ENGINE_TYPE_ID, Picture::STATUS_ACCEPTED, Picture::STATUS_NEW
        ));
        $this->save();
    }

    public function refreshUnsortedPicturesCount()
    {
        $db = $this->getTable()->getAdapter();
        $sql = 'SELECT COUNT(id) FROM pictures '.
               'WHERE brand_id=? AND type=? AND pictures.status IN (?, ?)';
        $this->unsortedpictures_count = (int)$db->fetchOne($sql, array(
            $this->id, Picture::UNSORTED_TYPE_ID,
            Picture::STATUS_ACCEPTED, Picture::STATUS_NEW));
        $this->save();
    }

}
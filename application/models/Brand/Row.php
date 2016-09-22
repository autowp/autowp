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

    public function RefreshUnsortedPicturesCount()
    {
        $db = $this->getTable()->getAdapter();
        $sql = 'SELECT COUNT(id) FROM pictures '.
               'WHERE brand_id=? AND type=? AND pictures.status IN (?, ?)';
        $this->unsortedpictures_count = (int)$db->fetchOne($sql, array(
            $this->id, Picture::UNSORTED_TYPE_ID,
            Picture::STATUS_ACCEPTED, Picture::STATUS_NEW));
        $this->save();
    }

    public function updatePicturesCache()
    {
        /*$db = $this->getTable()->getAdapter();

        $id = $this->id;

        $sql =  'LOCK TABLES brands_pictures_cache WRITE, pictures READ, '.
                    'brands_cars_cache READ, models READ, engines READ';
        $db->query($sql);

        $sql =  'DELETE FROM brands_pictures_cache WHERE brand_id=?';
        $db->query($sql, $id);

        // картинки логотипов, несортированные и разные
        $sql =  'INSERT IGNORE INTO brands_pictures_cache (brand_id, picture_id) '.
                'SELECT ?, id '.
                'FROM pictures '.
                'WHERE brand_id=? AND type IN (?, ?, ?)';
        $db->query($sql, array($id, $id, Picture::LOGO_TYPE_ID,
            Picture::MIXED_TYPE_ID, Picture::UNSORTED_TYPE_ID));

        // картинки моделей
        $sql =  'INSERT IGNORE INTO brands_pictures_cache (brand_id, picture_id) '.
                'SELECT ?, pictures.id '.
                'FROM pictures INNER JOIN models '.
                    'ON pictures.model_id=models.id AND pictures.type=? '.
                'WHERE models.brand_id=?';
        $db->query($sql, array($id, Picture::MODEL_TYPE_ID, $id));

        // картинки автомобилей
        $sql =  'INSERT IGNORE INTO brands_pictures_cache (brand_id, picture_id) '.
                'SELECT ?, pictures.id '.
                'FROM pictures INNER JOIN brands_cars_cache '.
                    'ON pictures.car_id=brands_cars_cache.car_id AND pictures.type=? '.
                'WHERE brands_cars_cache.brand_id=?';
        $db->query($sql, array($id, Picture::CAR_TYPE_ID, $id));

        // картинки двигателей
        $sql =  'INSERT IGNORE INTO brands_pictures_cache (brand_id, picture_id) '.
                'SELECT ?, pictures.id '.
                'FROM pictures INNER JOIN engines '.
                    'ON pictures.engine_id=engines.id AND pictures.type=? '.
                'WHERE engines.brand_id=?';
        $db->query($sql, array($id, Picture::ENGINE_TYPE_ID, $id));

        $sql = 'UNLOCK TABLES';
        $db->query($sql);*/
    }

    public function refreshActivePicturesCount()
    {
        $db = $this->getTable()->getAdapter();
        $sql = 'SELECT COUNT(id) '.
               'FROM pictures INNER JOIN brands_pictures_cache '.
                 'ON pictures.id=brands_pictures_cache.picture_id '.
               'WHERE (brands_pictures_cache.brand_id=?) '.
                 'AND (add_date>=DATE_SUB(CURDATE(), INTERVAL 7 DAY)) '.
                 'AND pictures.status IN (?, ?)';
        $this->activepictures_count = $db->fetchOne($sql, array(
            $this->id, Picture::STATUS_ACCEPTED, Picture::STATUS_NEW));
        $this->save();

    }

    /**
     * @deprecated
     */
    public function updateTwinsGroupsCount()
    {

    }
}
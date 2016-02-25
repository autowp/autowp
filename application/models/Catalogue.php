<?php

use Autowp\Image\Storage\Request;

class Catalogue
{
    protected $_picturesPerPage = 20;
    protected $_carsPerPage = 7;

    /**
     * @var Brands
     */
    protected $_brandTable;

    /**
     * @var Brands_Cars
     */
    protected $_brandCarTable;

    /**
     * @var Cars
     */
    protected $_carTable;

    /**
     * @var Car_Parent
     */
    protected $_carParentTable;

    /**
     * @var Car_Types
     */
    protected $_carTypeTable;

    /**
     * @var Picture
     */
    protected $_pictureTable;

    /**
     * @var Engines
     */
    protected $_engineTable;

    /**
     * @var Brand_Engine
     */
    protected $_brandEngineTable;

    protected $_perspectiveLangTable;

    protected $_perspectivePrefix = array();

    protected $_prefixedPerspectives = array(5, 6, 17, 20, 21);

    /**
     * @return array
     */
    public function carsOrdering()
    {
        return array(
            'cars.begin_order_cache',
            'cars.end_order_cache',
            'cars.caption',
            'cars.body',
            'cars.spec_id'
        );

        /*return array(
            new Zend_Db_Expr('cars.begin_year IS NULL'),
            'cars.begin_year',
            new Zend_Db_Expr('cars.begin_month IS NULL'),
            'cars.begin_month',
            new Zend_Db_Expr('cars.end_year=0'),
            'cars.end_year',
            new Zend_Db_Expr('cars.end_month IS NULL'),
            'cars.end_month',
            'cars.caption',
            'cars.body'
        );*/
    }

    /**
     * @return array
     */
    public function picturesOrdering()
    {
        return array(
            'pictures.width DESC', 'pictures.height DESC',
            'pictures.add_date DESC', 'pictures.id DESC'
        );
    }

    public function getCarsPerPage()
    {
        return $this->_carsPerPage;
    }

    public function getPicturesPerPage()
    {
        return $this->_picturesPerPage;
    }

    /**
     * @return Brands
     */
    public function getBrandTable()
    {
        return $this->_brandTable
            ? $this->_brandTable
            : $this->_brandTable = new Brands();
    }

    /**
     * @return Brands_Cars
     */
    public function getBrandCarTable()
    {
        return $this->_brandCarTable
            ? $this->_brandCarTable
            : $this->_brandCarTable = new Brands_Cars();
    }

    /**
     * @return Cars
     */
    public function getCarTable()
    {
        return $this->_carTable
            ? $this->_carTable
            : $this->_carTable = new Cars();
    }

    /**
     * @return Car_Parent
     */
    public function getCarParentTable()
    {
        return $this->_carParentTable
            ? $this->_carParentTable
            : $this->_carParentTable = new Car_Parent();
    }

    /**
     * @return Car_Types
     */
    public function getCarTypeTable()
    {
        return $this->_carTypeTable
        ? $this->_carTypeTable
        : $this->_carTypeTable = new Car_Types();
    }

    /**
     * @return Picture
     */
    public function getPictureTable()
    {
        return $this->_pictureTable
            ? $this->_pictureTable
            : $this->_pictureTable = new Picture();
    }

    /**
     * @return Engines
     */
    public function getEngineTable()
    {
        return $this->_engineTable
            ? $this->_engineTable
            : $this->_engineTable = new Engines();
    }

    /**
     * @return Brand_Engine
     */
    public function getBrandEngineTable()
    {
        return $this->_brandEngineTable
            ? $this->_brandEngineTable
            : $this->_brandEngineTable = new Brand_Engine();
    }

    /**
     * @param Cars_Row $car
     * @return array
     */
    public function cataloguePaths(Cars_Row $car)
    {
        return $this->_walkUpUntilBrand($car->id, array());
    }

    /**
     * @param int $id
     * @param array $path
     * @throws Exception
     * @return array
     */
    protected function _walkUpUntilBrand($id, array $path)
    {
        $urls = array();

        $brandCarRows = $this->getBrandCarTable()->fetchAll(array(
            'car_id = ?' => $id
        ));

        foreach ($brandCarRows as $brandCarRow) {

            $brand = $this->getBrandTable()->find($brandCarRow->brand_id)->current();
            if (!$brand) {
                throw new Exception("Broken link `{$brandCarRow->brand_id}`");
            }

            $urls[] = array(
                'brand_catname' => $brand->folder,
                'car_catname'   => $brandCarRow->catname ? $brandCarRow->catname : 'car' . $brandCarRow->car_id,
                'path'          => $path
            );
        }

        $parentRows = $this->getCarParentTable()->fetchAll(array(
            'car_id = ?' => $id
        ));
        foreach ($parentRows as $parentRow) {
            $urls = array_merge(
                $urls,
                $this->_walkUpUntilBrand($parentRow->parent_id, array_merge(array($parentRow->catname), $path))
            );
        }

        return $urls;
    }

    public function engineCataloguePaths(Engines_Row $engine, array $options = array())
    {
        $defaults = array(
            'brand_id' => null,
            'limit'    => null
        );
        $options = array_merge($defaults, $options);

        return $this->_engineWalkUpUntilBrand($engine->id, $options);
    }

    /**
     * @param int $id
     * @param array $path
     * @throws Exception
     * @return array
     */
    protected function _engineWalkUpUntilBrand($id, $options)
    {
        $urls = array();

        $engineTable = $this->getEngineTable();

        $engineRow = $engineTable->find($id)->current();
        if (!$engineRow) {
            return $urls;
        }

        $brandTable = $this->getBrandTable();

        $limit = $options['limit'];

        $path = array();

        while ($engineRow) {

            array_unshift($path, $engineRow->id);

            $brandEngineRows = $this->getBrandEngineTable()->fetchAll(array(
                'engine_id = ?' => $engineRow->id
            ));

            foreach ($brandEngineRows as $brandEngineRow) {

                if ($options['brand_id'] && $options['brand_id'] != $brandEngineRow->brand_id) {
                    continue;
                }

                $brand = $brandTable->find($brandEngineRow->brand_id)->current();
                if (!$brand) {
                    throw new Exception("Broken link `{$brandCarRow->brand_id}`");
                }

                $urls[] = array(
                    'brand_catname' => $brand->folder,
                    'path'          => $path
                );

                if ($limit !== null) {
                    $limit--;
                    if ($limit <= 0) {
                        break;
                    }
                }
            }

            if ($limit !== null && $limit <= 0) {
                break;
            }

            if ($engineRow->parent_id) {
                $engineRow = $engineTable->fetchRow(array(
                    'id = ?' => $engineRow->parent_id
                ));
            } else {
                $engineRow = null;
            }
        }

        return $urls;
    }

    protected function _getPerspectiveLangTable()
    {
        return $this->_perspectiveLangTable ?: $this->_perspectiveLangTable = new Perspective_Language();
    }

    private static function mbUcfirst($str)
    {
        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
    }

    protected function _getPerspectivePrefix($id, $language)
    {
        if (in_array($id, $this->_prefixedPerspectives)) {
            if (!isset($this->_perspectivePrefix[$id][$language])) {
                $row = $this->_getPerspectiveLangTable()->fetchRow(array(
                    'perspective_id = ?' => $id,
                    'language = ?'       => $language
                ));
                if ($row) {
                    $this->_perspectivePrefix[$id][$language] = self::mbUcfirst($row->name) . ' ';
                } else {
                    $this->_perspectivePrefix[$id][$language] = '';
                }
            }

            return $this->_perspectivePrefix[$id][$language];
        }

        return '';
    }

    /**
     * @param array $pictures
     * @param string $language
     * @return array
     */
    public function buildPicturesName(array $pictures, $language)
    {
        return $this->getPictureTable()->getNames($pictures, array(
            'language' => $language
        ));
    }

    /**
     * @param array $picture
     * @param string $language
     * @return string
     */
    public function buildPictureName(array $picture, $language)
    {
        $defaults = array(
            'id'             => null,
            'name'           => null,
            'type'           => null,
            'brand_id'       => null,
            'engine_id'      => null,
            'car_id'         => null,
            'perspective_id' => null,
        );
        $picture = array_replace($defaults, $picture);

        if ($picture['name']) {
            return $picture['name'];
        }

        switch ($picture['type']) {
            case Picture::CAR_TYPE_ID:
                $car = $this->getCarTable()->find($picture['car_id'])->current();
                if ($car) {
                    $caption = $this->_getPerspectivePrefix($picture['perspective_id'], $language) .
                    $car->getFullName($language);
                }
                break;

            case Picture::ENGINE_TYPE_ID:
                $engine = $this->getEngineTable()->find($picture['engine_id'])->current();
                $caption = 'Двигатель '.($engine ? ' '.$engine->caption: '');
                break;

            case Picture::LOGO_TYPE_ID:
                $brand = $this->getBrandTable()->find($picture['brand_id'])->current();
                $caption = 'Логотип '.($brand ? ' '.$brand->getLanguageName($language) : '');
                break;

            case Picture::MIXED_TYPE_ID:
                $brand = $this->getBrandTable()->find($picture['brand_id'])->current();
                $caption = ($brand ? $brand->getLanguageName($language).' ' : '').' Разное';
                break;

            case Picture::UNSORTED_TYPE_ID:
                $brand = $this->getBrandTable()->find($picture['brand_id'])->current();
                $caption = ($brand ? $brand->getLanguageName($language) : 'Несортировано');
                break;
        }

        if (!$caption) {
            $caption = 'Изображение №'. $picture['id'];
        }

        return $caption;
    }

    private static function _between($a, $min, $max)
    {
        return ($min <= $a) && ($a <= $max);
    }

    public function cropParametersExists(array $picture)
    {
        // проверяем установлены ли границы обрезания
        // проверяем верные ли значения границ обрезания
        $canCrop =  !is_null($picture['crop_left']) && !is_null($picture['crop_top']) &&
            !is_null($picture['crop_width']) && !is_null($picture['crop_height']) &&
            self::_between($picture['crop_left'], 0, $picture['width']) &&
            self::_between($picture['crop_width'], 1, $picture['width']) &&
            self::_between($picture['crop_top'], 0, $picture['height']) &&
            self::_between($picture['crop_height'], 1, $picture['height']);

        return $canCrop;
    }

    /**
     * @return Request
     */
    public function getPictureFormatRequest(array $picture)
    {
        $options = array(
            'imageId' => $picture['image_id']
        );
        if ($this->cropParametersExists($picture)) {
            $options['crop'] = array(
                'left'   => $picture['crop_left'],
                'top'    => $picture['crop_top'],
                'width'  => $picture['crop_width'],
                'height' => $picture['crop_height']
            );
        }

        return new Request($options);
    }
}
<?php

use Autowp\Image\Storage\Request;

class Catalogue
{
    private $_picturesPerPage = 20;

    private $carsPerPage = 7;

    /**
     * @var Brands
     */
    private $brandTable;

    /**
     * @var Brand_Car
     */
    private $brandCarTable;

    /**
     * @var Cars
     */
    private $carTable;

    /**
     * @var Car_Parent
     */
    private $carParentTable;

    /**
     * @var Car_Types
     */
    private $carTypeTable;

    /**
     * @var Picture
     */
    private $pictureTable;

    /**
     * @var Engines
     */
    private $engineTable;

    /**
     * @var Brand_Engine
     */
    private $brandEngineTable;

    private $perspectiveLangTable;

    private $perspectivePrefix = [];

    private $prefixedPerspectives = [5, 6, 17, 20, 21, 23];

    /**
     * @return array
     */
    public function carsOrdering()
    {
        return [
            'cars.begin_order_cache',
            'cars.end_order_cache',
            'cars.caption',
            'cars.body',
            'cars.spec_id'
        ];

        /*return [
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
        ];*/
    }

    /**
     * @return array
     */
    public function picturesOrdering()
    {
        return [
            'pictures.width DESC', 'pictures.height DESC',
            'pictures.add_date DESC', 'pictures.id DESC'
        ];
    }

    public function getCarsPerPage()
    {
        return $this->carsPerPage;
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
        return $this->brandTable
            ? $this->brandTable
            : $this->brandTable = new Brands();
    }

    /**
     * @return Brand_Car
     */
    public function getBrandCarTable()
    {
        return $this->brandCarTable
            ? $this->brandCarTable
            : $this->brandCarTable = new Brand_Car();
    }

    /**
     * @return Cars
     */
    public function getCarTable()
    {
        return $this->carTable
            ? $this->carTable
            : $this->carTable = new Cars();
    }

    /**
     * @return Car_Parent
     */
    public function getCarParentTable()
    {
        return $this->carParentTable
            ? $this->carParentTable
            : $this->carParentTable = new Car_Parent();
    }

    /**
     * @return Car_Types
     */
    public function getCarTypeTable()
    {
        return $this->carTypeTable
        ? $this->carTypeTable
        : $this->carTypeTable = new Car_Types();
    }

    /**
     * @return Picture
     */
    public function getPictureTable()
    {
        return $this->pictureTable
            ? $this->pictureTable
            : $this->pictureTable = new Picture();
    }

    /**
     * @return Engines
     */
    public function getEngineTable()
    {
        return $this->engineTable
            ? $this->engineTable
            : $this->engineTable = new Engines();
    }

    /**
     * @return Brand_Engine
     */
    public function getBrandEngineTable()
    {
        return $this->brandEngineTable
            ? $this->brandEngineTable
            : $this->brandEngineTable = new Brand_Engine();
    }

    /**
     * @param Cars_Row $car
     * @return array
     */
    public function cataloguePaths(Cars_Row $car)
    {
        return $this->walkUpUntilBrand($car->id, []);
    }

    /**
     * @param int $id
     * @param array $path
     * @throws Exception
     * @return array
     */
    private function walkUpUntilBrand($id, array $path)
    {
        $urls = [];

        $brandCarRows = $this->getBrandCarTable()->fetchAll([
            'car_id = ?' => $id
        ]);

        foreach ($brandCarRows as $brandCarRow) {

            $brand = $this->getBrandTable()->find($brandCarRow->brand_id)->current();
            if (!$brand) {
                throw new Exception("Broken link `{$brandCarRow->brand_id}`");
            }

            $urls[] = [
                'brand_catname' => $brand->folder,
                'car_catname'   => $brandCarRow->catname ? $brandCarRow->catname : 'car' . $brandCarRow->car_id,
                'path'          => $path
            ];
        }

        $parentRows = $this->getCarParentTable()->fetchAll([
            'car_id = ?' => $id
        ]);
        foreach ($parentRows as $parentRow) {
            $urls = array_merge(
                $urls,
                $this->walkUpUntilBrand($parentRow->parent_id, array_merge([$parentRow->catname], $path))
            );
        }

        return $urls;
    }

    public function engineCataloguePaths(Engine_Row $engine, array $options = [])
    {
        $defaults = [
            'brand_id' => null,
            'limit'    => null
        ];
        $options = array_merge($defaults, $options);

        return $this->engineWalkUpUntilBrand($engine->id, $options);
    }

    /**
     * @param int $id
     * @param array $path
     * @throws Exception
     * @return array
     */
    private function engineWalkUpUntilBrand($id, $options)
    {
        $urls = [];

        $engineTable = $this->getEngineTable();

        $engineRow = $engineTable->find($id)->current();
        if (!$engineRow) {
            return $urls;
        }

        $brandTable = $this->getBrandTable();

        $limit = $options['limit'];

        $path = [];

        while ($engineRow) {

            array_unshift($path, $engineRow->id);

            $brandEngineRows = $this->getBrandEngineTable()->fetchAll([
                'engine_id = ?' => $engineRow->id
            ]);

            foreach ($brandEngineRows as $brandEngineRow) {

                if ($options['brand_id'] && $options['brand_id'] != $brandEngineRow->brand_id) {
                    continue;
                }

                $brand = $brandTable->find($brandEngineRow->brand_id)->current();
                if (!$brand) {
                    throw new Exception("Broken link `{$brandCarRow->brand_id}`");
                }

                $urls[] = [
                    'brand_catname' => $brand->folder,
                    'path'          => $path
                ];

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
                $engineRow = $engineTable->fetchRow([
                    'id = ?' => $engineRow->parent_id
                ]);
            } else {
                $engineRow = null;
            }
        }

        return $urls;
    }

    private function getPerspectiveLangTable()
    {
        return $this->perspectiveLangTable ?: $this->perspectiveLangTable = new Perspective_Language();
    }

    private static function mbUcfirst($str)
    {
        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
    }

    private function getPerspectivePrefix($id, $language)
    {
        if (in_array($id, $this->prefixedPerspectives)) {
            if (!isset($this->perspectivePrefix[$id][$language])) {
                $row = $this->getPerspectiveLangTable()->fetchRow([
                    'perspective_id = ?' => $id,
                    'language = ?'       => $language
                ]);
                if ($row) {
                    $this->perspectivePrefix[$id][$language] = self::mbUcfirst($row->name) . ' ';
                } else {
                    $this->perspectivePrefix[$id][$language] = '';
                }
            }

            return $this->perspectivePrefix[$id][$language];
        }

        return '';
    }

    /**
     * @param array $pictures
     * @param string $language
     * @return array
     */
    public function buildPicturesName(array $pictures, $language, $translator)
    {
        return $this->getPictureTable()->getNames($pictures, [
            'language'   => $language,
            'translator' => $translator
        ]);
    }

    /**
     * @param array $picture
     * @param string $language
     * @return string
     */
    public function buildPictureName(array $picture, $language)
    {
        $defaults = [
            'id'             => null,
            'name'           => null,
            'type'           => null,
            'brand_id'       => null,
            'engine_id'      => null,
            'car_id'         => null,
            'perspective_id' => null,
        ];
        $picture = array_replace($defaults, $picture);

        if ($picture['name']) {
            return $picture['name'];
        }

        switch ($picture['type']) {
            case Picture::CAR_TYPE_ID:
                $car = $this->getCarTable()->find($picture['car_id'])->current();
                if ($car) {
                    $caption = $this->getPerspectivePrefix($picture['perspective_id'], $language) .
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

    private static function between($a, $min, $max)
    {
        return ($min <= $a) && ($a <= $max);
    }

    public function cropParametersExists(array $picture)
    {
        // проверяем установлены ли границы обрезания
        // проверяем верные ли значения границ обрезания
        $canCrop =  !is_null($picture['crop_left']) && !is_null($picture['crop_top']) &&
            !is_null($picture['crop_width']) && !is_null($picture['crop_height']) &&
            self::between($picture['crop_left'], 0, $picture['width']) &&
            self::between($picture['crop_width'], 1, $picture['width']) &&
            self::between($picture['crop_top'], 0, $picture['height']) &&
            self::between($picture['crop_height'], 1, $picture['height']);

        return $canCrop;
    }

    /**
     * @return Request
     */
    public function getPictureFormatRequest(array $picture)
    {
        $options = [
            'imageId' => $picture['image_id']
        ];
        if ($this->cropParametersExists($picture)) {
            $options['crop'] = [
                'left'   => $picture['crop_left'],
                'top'    => $picture['crop_top'],
                'width'  => $picture['crop_width'],
                'height' => $picture['crop_height']
            ];
        }

        return new Request($options);
    }
}
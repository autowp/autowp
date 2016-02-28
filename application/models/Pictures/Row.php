<?php

use Autowp\Filter\Filename\Safe;
use Autowp\Image\Storage\Request;

class Pictures_Row extends Project_Db_Table_Row
{
    private $_caption_cache = array();

    private $perspectiveTable;
    private $perspectivePrefix = array();
    private $prefixedPerspectives = array(5, 6, 17, 20, 21, 22);

    private function getPerspectiveTable()
    {
        return $this->perspectiveTable
            ? $this->perspectiveTable
            : $this->perspectiveTable = new Perspectives();
    }

    private static function mbUcfirst($str)
    {
        return mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
    }

    private function getPerspectivePrefix($id, $language)
    {
        if (in_array($id, $this->prefixedPerspectives)) {
            if (!isset($this->perspectivePrefix[$id][$language])) {
                $row = $this->getPerspectiveTable()->find($id)->current();
                if ($row) {
                    $translate = Zend_Registry::get('Zend_Translate');
                    $name = $translate->translate($row->name, $language);
                    $this->perspectivePrefix[$id][$language] = self::mbUcfirst($name) . ' ';
                } else {
                    $this->perspectivePrefix[$id][$language] = '';
                }
            }

            return $this->perspectivePrefix[$id][$language];
        }

        return '';
    }

    public function getCaption(array $options = array())
    {
        if ($this->name) {
            return $this->name;
        }

        $language = isset($options['language']) ? $options['language'] : 'en';

        if (isset($this->_caption_cache[$language])) {
            return $this->_caption_cache[$language];
        }

        $caption = null;

        switch ($this->type) {
            case Picture::CAR_TYPE_ID:
                $car = $this->findParentCars();
                if ($car) {
                    $caption = $this->getPerspectivePrefix($this->perspective_id, $language) .
                               $car->getFullName($language);
                }
                break;

            case Picture::ENGINE_TYPE_ID:
                $engine = $this->findParentEngines();
                $caption = 'Двигатель '.($engine ? ' '.$engine->caption: '');
                break;

            case Picture::LOGO_TYPE_ID:
                $brand = $this->findParentBrands();
                $caption = 'Логотип '.($brand ? ' '.$brand->getLanguageName($language) : '');
                break;

            case Picture::MIXED_TYPE_ID:
                $brand = $this->findParentBrands();
                $caption = ($brand ? $brand->getLanguageName($language).' ' : '').' Разное';
                break;

            case Picture::UNSORTED_TYPE_ID:
                $brand = $this->findParentBrands();
                $caption = ($brand ? $brand->getLanguageName($language) : 'Несортировано');
                break;

            case Picture::FACTORY_TYPE_ID:
                $factory = $this->findParentFactory();
                if ($factory) {
                    $caption = $factory->name;
                }
                break;
        }

        if (!$caption) {
            $caption = 'Изображение №'.$this->id;
        }

        $this->_caption_cache[$language] = $caption;
        return $caption;
    }

    /**
     * @deprecated
     * @param string $absolute
     * @return string
     */
    public function getModerUrl($absolute = false)
    {
        return ($absolute ? HOST : '/').'moder/car/?car_id='.$this->id;
    }

    private static function between($a, $min, $max)
    {
        return ($min <= $a) && ($a <= $max);
    }

    public static function checkCropParameters($options)
    {
        // проверяем установлены ли границы обрезания
        // проверяем верные ли значения границ обрезания
        return  !is_null($options['crop_left']) && !is_null($options['crop_top']) &&
                !is_null($options['crop_width']) && !is_null($options['crop_height']) &&
                self::between($options['crop_left'], 0, $options['width']) &&
                self::between($options['crop_width'], 1, $options['width']) &&
                self::between($options['crop_top'], 0, $options['height']) &&
                self::between($options['crop_height'], 1, $options['height']);
    }

    public function cropParametersExists()
    {
        return self::checkCropParameters($this->toArray());
    }

    /**
     * @return void
     */
    protected function _update()
    {
        if (array_key_exists('type', $this->_modifiedFields))
        {
            $newTypeID = intval($this->_data['type']);
            $typeID = intval($this->_cleanData['type']);

            $brandId = null;
            $carId = null;
            $engineId = null;

            // вычисляем старые значения
            switch ($typeID)
            {
                case Picture::UNSORTED_TYPE_ID:
                case Picture::MIXED_TYPE_ID:
                case Picture::LOGO_TYPE_ID:
                    $brandId = $this->brand_id;
                    break;
                case Picture::CAR_TYPE_ID:
                    $brandId = null;
                    $car = $this->findParentCars();
                    if ($car)
                    {
                        $carId = $car->id;
                    }
                    break;
                case Picture::ENGINE_TYPE_ID:
                    break;

                case Picture::FACTORY_TYPE_ID:
                    break;
                default:
                    throw new Exception('Unknown typeId');
            }

            // вычисляем новые значения
            $newBrandId = null;
            $newCarId = null;
            $newEngineId = null;
            $newFactoryId = null;
            switch ($newTypeID)
            {
                case Picture::UNSORTED_TYPE_ID:
                case Picture::MIXED_TYPE_ID:
                case Picture::LOGO_TYPE_ID:
                    $newBrandId = null;
                    if ($brandId)
                        $newBrandId = $brandId;
                    elseif ($carId)
                    {
                        $cars = new Cars();
                        $car = $cars->find($carId)->current();
                        foreach ($car->findBrandsViaBrands_Cars() as $brand)
                            $newBrandId = $brand->id;
                    }
                    elseif ($engineId)
                    {
                        $engines = new Engines();
                        $engine = $engines->find($engineId)->current();
                        if ($engine)
                            $newBrandId = $engine->brand_id;
                    }
                    break;
                case Picture::CAR_TYPE_ID:
                    $newCarId = $carId;
                    break;
                case Picture::ENGINE_TYPE_ID:
                    $newEngineId = $engineId;
                    break;
                case Picture::FACTORY_TYPE_ID:
                    break;
                default:
                    throw new Exception('Unknown typeId');
            }

            $this->car_id = $newCarId;
            $this->brand_id = $newBrandId;
            $this->engine_id = $newEngineId;
            $this->factory_id = $newFactoryId;
        }
    }

    /**
     * @return void
     */
    protected function _postUpdate()
    {
        // удаляем ссылки
        if (array_key_exists('car_id', $this->_modifiedFields))
        {
            $cars = new Cars();
            $bpcTable = new Brands_Pictures_Cache();

            if ($this->_cleanData['car_id'])
            {
                $car = $cars->find($this->_cleanData['car_id'])->current();
                if ($car) {
                    $car->refreshPicturesCount();
                    foreach ($car->findBrandsViaBrands_Cars() as $brand)
                    {
                        $bpcTable->delete(array(
                            $bpcTable->getAdapter()->quoteInto('picture_id = ?', $this->id),
                            $bpcTable->getAdapter()->quoteInto('brand_id = ?', $brand->id)
                        ));
                        $brand->refreshPicturesCount();
                    }
                }
            }
        }

        if (array_key_exists('brand_id', $this->_modifiedFields) && $this->_cleanData['brand_id'])
        {
            $bpcTable = new Brands_Pictures_Cache();
            $db = $bpcTable->getAdapter();

            $bpcTable->delete(array(
                $db->quoteInto('picture_id = ?', $this->id),
                $db->quoteInto('brand_id = ?', $this->_cleanData['brand_id'])
            ));
        }

        if (array_key_exists('engine_id', $this->_modifiedFields) && $this->_cleanData['engine_id'])
        {
        }


        // вставляем ссылки
        $bpcRows = array();

        if (array_key_exists('car_id', $this->_modifiedFields))
        {
            if ($this->_data['car_id'] && $this->_data['type'] == Picture::CAR_TYPE_ID)
            {
                $car = $cars->find($this->_data['car_id'])->current();
                if ($car) {
                    $car->refreshPicturesCount();
                    foreach ($car->findBrandsViaBrands_Cars() as $brand)
                    {
                        $bpcRows[] = array(
                            'picture_id'    =>  $this->id,
                            'brand_id'      =>  $brand->id
                        );
                    }
                }
            }
        }

        if (array_key_exists('brand_id', $this->_modifiedFields) && $this->_data['brand_id'] && in_array($this->_data['type'], array(Picture::UNSORTED_TYPE_ID, Picture::MIXED_TYPE_ID, Picture::LOGO_TYPE_ID)))
        {
            $bpcRows[] = array(
                'picture_id'    =>  $this->id,
                'brand_id'      =>  $this->_data['brand_id']
            );
        }

        if (array_key_exists('engine_id', $this->_modifiedFields) && $this->_data['engine_id'] && $this->_data['type'] == Picture::ENGINE_TYPE_ID) {
            $brandTable = new Brands();
            $rows = $brandTable->fetchAll(
                $brandTable->select(true)
                    ->join('brand_engine', 'brands.id = brand_engine.brand_id', null)
                    ->join('engine_parent_cache', 'brand_engine.engine_id = engine_parent_cache.parent_id', null)
                    ->where('engine_parent_cache.engine_id = ?', $this->_data['engine_id'])
            );

            foreach ($rows as $row) {
                $bpcRows[] = array(
                    'picture_id' => $this->id,
                    'brand_id'   => $row->id
                );
            }
        }

        $bpcTable = new Brands_Pictures_Cache();
        $brands = new Brands();

        foreach ($bpcRows as $row)
        {
            if (is_null($row['brand_id']) || is_null($row['picture_id'])) {
                throw new Exception('Unexpected null in ' . print_r($bpcRows, true));
            }

            $select = $bpcTable->select()
                               ->where('brand_id = ?', $row['brand_id'])
                               ->where('picture_id = ?', $row['picture_id']);
            if (!$bpcTable->fetchRow($select)) {
                $bpcTable->insert($row);
                $brand = $brands->find($row['brand_id'])->current();
                $brand->refreshPicturesCount();
            }
        }
    }

    /**
     * @throws Exception
     * @return string
     */
    public function getFileNamePattern()
    {
        $result = rand(1, 9999);

        $filenameFilter = new Safe();

        switch ($this->type) {
            case Picture::LOGO_TYPE_ID:
                $brand = $this->findParentBrands();
                if ($brand) {
                    $catname = $filenameFilter->filter($brand->folder);
                    $firstChar = mb_substr($catname, 0, 1);
                    $result = $firstChar . '/' . $catname.'/logotypes/'.$catname.'_logo';
                }
                break;

            case Picture::MIXED_TYPE_ID:
                $brand = $this->findParentBrands();
                if ($brand) {
                    $catname = $filenameFilter->filter($brand->folder);
                    $firstChar = mb_substr($catname, 0, 1);
                    $result = $firstChar . '/' . $catname.'/mixed/'.$catname.'_mixed';
                }
                break;

            case Picture::UNSORTED_TYPE_ID:
                $brand = $this->findParentBrands();
                if ($brand) {
                    $catname = $filenameFilter->filter($brand->folder);
                    $firstChar = mb_substr($catname, 0, 1);
                    $result = $firstChar . '/' . $catname . '/unsorted/' . $catname.'_unsorted';
                }
                break;

            case Picture::CAR_TYPE_ID:
                $car = $this->findParentCars();
                if ($car) {
                    $carCatname = $filenameFilter->filter($car->caption);

                    $brandTable = new Brands();

                    $brands = $brandTable->fetchAll(
                        $brandTable->select(true)
                            ->join('brands_cars', 'brands.id = brands_cars.brand_id', null)
                            ->join('car_parent_cache', 'brands_cars.car_id = car_parent_cache.parent_id', null)
                            ->where('car_parent_cache.car_id = ?', $car->id)
                    );

                    $sBrands = array();
                    foreach ($brands as $brand) {
                        $sBrands[$brand->id] = $brand;
                    }

                    if (count($sBrands) > 1) {
                        $f = array();
                        foreach ($sBrands as $brand) {
                            $f[] = $filenameFilter->filter($brand->folder);
                        }
                        $f = array_unique($f);
                        sort($f, SORT_STRING);

                        $carFolder = $carCatname;
                        foreach ($f as $i) {
                            $carFolder = str_replace($i, '', $carFolder);
                        }

                        $carFolder = str_replace('__', '_', $carFolder);
                        $carFolder = trim($carFolder, '_-');

                        $brandsFolder = implode('/', $f);
                        $firstChar = mb_substr($brandsFolder, 0, 1);

                        $result = $firstChar . '/' . $brandsFolder .'/'.$carFolder.'/'.$carCatname;
                    } else {
                        $dp = $car->findParentDesign_Projects();
                        if ((count($sBrands) == 0) && $dp) {
                            $brand = $dp->findParentBrands();
                            $brandFolder = $filenameFilter->filter($brand->folder);
                            $firstChar = mb_substr($brandFolder, 0, 1);
                            $result = implode('/', array(
                                $firstChar,
                                $brandFolder,
                                $carCatname,
                                $carCatname
                            ));
                        } else {

                            if (count($sBrands) == 1) {
                                $sBrandsA = array_values($sBrands);
                                $brand = $sBrandsA[0];

                                $brandFolder = $filenameFilter->filter($brand->folder);
                                $firstChar = mb_substr($brandFolder, 0, 1);

                                $carFolder = $carCatname;
                                $carFolder = trim(str_replace($brandFolder, '', $carFolder), '_-');

                                $result = implode('/', array(
                                    $firstChar,
                                    $brandFolder,
                                    $carFolder,
                                    $carCatname
                                ));
                            } else {
                                $carFolder = $filenameFilter->filter($car->caption);
                                $firstChar = mb_substr($carFolder, 0, 1);
                                $result = $firstChar . '/' . $carFolder.'/'.$carCatname;
                            }
                        }
                    }
                }
                break;

            case Picture::ENGINE_TYPE_ID:
                $engine = $this->findParentEngines();
                if ($engine) {
                    $result = implode('/', array(
                        'engines',
                        $filenameFilter->filter($engine->getMetaCaption())
                    ));
                }
                break;

            case Picture::FACTORY_TYPE_ID:
                $factory = $this->findParentFactory();
                if ($factory) {
                    $result = implode('/', array(
                        'factories',
                        $filenameFilter->filter($factory->name)
                    ));
                }
                break;

            default:
                throw new Exception("Unknown picture type [{$this->type}]");
        }

        $result = str_replace('//', '/', $result);

        return $result;
    }

    /**
     * @deprecated
     * @param string $ext
     * @return string
     */
    public function getFileNameTemplate($ext)
    {
        return $this->getFileNamePattern() . '_%d.' . $ext;
    }

    protected function _delete()
    {
        $comments = new Comment_Message();
        $comments->delete(array(
            'type_id = ?' => Comment_Message::PICTURES_TYPE_ID,
            'item_id = ?' => $this->id,
        ));

        //$this->flushFormatImages();

        //$this->removeSigned();
        //$this->removePicture280();
        //$this->removeThumb();
        //$this->removePod();
        //$this->removeSource();


    }

    public function getImageOptions($col)
    {
        $options = array();

        if ($this->cropParametersExists()) {
            $options['crop'] = array(
                'left'   => $this->crop_left,
                'top'    => $this->crop_top,
                'width'  => $this->crop_width,
                'height' => $this->crop_height
            );
        }

        return $options;
    }

    protected function postImageFormat($imageRow, $format)
    {
        if ($format->id == 8) {

            $image = $imageRow->getImage('image');

            $caption = $this->getCaption();

            $cmd = sprintf(
                "convert %s -gravity northwest " .
                    "-pointsize 20 " .
                    "-stroke '#000C' -strokewidth 2 -size 342x caption:%s " .
                    "-stroke none -fill white -size 342x caption:%s %s",
                escapeshellarg($image['filepath']),
                escapeshellarg($caption),
                escapeshellarg($caption),
                escapeshellarg($image['filepath'])
            );
            $code = exec($cmd);

            print $cmd;
        }
    }

    public function refreshRatio()
    {
        $votes = new Votes();

        $row = $votes->getAdapter()->fetchRow(
            $votes->select()
                ->from($votes, array(
                    'sum'   =>  new Zend_Db_Expr('SUM(summary)'),
                    'cnt'   =>  new Zend_Db_Expr('SUM(count)')
                ))
                ->where('picture_id = ?', $this->id)
        );

        if ($row) {
            $this->setFromArray(array(
                'ratio' =>  $row['cnt'] > 0 ? $row['sum']/$row['cnt'] : 0,
                'votes' =>  $row['cnt']
            ));
        }

        $row = $votes->getAdapter()->fetchRow(
            $votes->select()
                ->from($votes, array(
                    'sum'   =>  new Zend_Db_Expr('SUM(summary)'),
                    'cnt'   =>  new Zend_Db_Expr('SUM(count)')
                ))
                ->where('picture_id = ?', $this->id)
                ->where('day_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)')
        );

        if ($row) {
            $this->setFromArray(array(
                'active_ratio'  =>  $row['cnt'] > 0 ? $row['sum']/$row['cnt'] : 0,
                'active_votes'  =>  $row['cnt']
            ));
        }

        $this->save();

    }

    /**
     * @return Request
     */
    public function getFormatRequest()
    {
        return self::buildFormatRequest($this->toArray());
    }

    /**
     * @param array $options
     * @return Request
     */
    public static function buildFormatRequest(array $options)
    {
        $defaults = array(
            'image_id'    => null,
            'crop_left'   => null,
            'crop_top'    => null,
            'crop_width'  => null,
            'crop_height' => null
        );
        $options = array_replace($defaults, $options);

        $request = array(
            'imageId' => $options['image_id']
        );
        if (self::checkCropParameters($options)) {
            $request['crop'] = array(
                'left'   => $options['crop_left'],
                'top'    => $options['crop_top'],
                'width'  => $options['crop_width'],
                'height' => $options['crop_height']
            );
        }

        return new Request($request);
    }
}
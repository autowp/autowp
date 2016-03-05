<?php

namespace Autowp\Controller\Router\Route;

use Brand_Car;
use Brands;
use Car_Parent;
use Car_Types;

use Zend_Config;

class Catalogue extends AbstractRoute
{
    /**
     * @var Car_Types
     */
    protected $_carTypeTable;

    protected $_defaults = array(
        'controller' => 'catalogue',
        'action'     => 'brand'
    );

    /**
     * Instantiates route based on passed Zend_Config structure
     *
     * @param Zend_Config $config Configuration object
     */
    public static function getInstance(Zend_Config $config)
    {
        return new self();
    }

    /**
     * @return Car_Types
     */
    protected function _getCarTypeTable()
    {
        return $this->_carTypeTable
            ? $this->_carTypeTable
            : $this->_carTypeTable = new Car_Types();
    }

    protected function _assembleMatch(array $data)
    {
        $result = array_merge($this->_defaults, $data);
        $this->_variables = $result;
        return $result;
    }

    public function match($path)
    {
        $data = $this->_defaults;

        $path = trim($path, self::DELIMETER);
        $path = explode(self::DELIMETER, $path);

        foreach ($path as &$node) {
            $node = urldecode($node);
        }

        if (!count($path)) {
            return false;
        }

        if (strlen($path[0]) <= 0) {
            return false;
        }

        $brands = new Brands();
        $brand = $brands->fetchRow(array(
           'folder = ?' => $path[0]
        ));

        if (!$brand) {
            return false;
        }

        $data['brand_catname'] = $brand->folder;
        array_shift($path);

        if (!$path) {
            // :brand
            return $this->_assembleMatch(array(
                'action'        => 'brand',
                'brand_catname' => $brand->folder,
            ));
        }

        $match = null;

        switch ($path[0]) {
            case 'mosts':
                array_shift($path);

                if (!$path) {
                    // most
                    return $this->_assembleMatch(array(
                        'action'        => 'brand-mosts',
                        'brand_catname' => $brand->folder,
                    ));
                }

                $most = array_shift($path);

                if (!$path) {
                    // most/:most
                    return $this->_assembleMatch(array(
                        'action'        => 'brand-mosts',
                        'brand_catname' => $brand->folder,
                        'most_catname'  => $most
                    ));
                }

                $shape = array_shift($path);

                if (!$path) {
                    // most/:most/:shape
                    return $this->_assembleMatch(array(
                        'action'        => 'brand-mosts',
                        'brand_catname' => $brand->folder,
                        'most_catname'  => $most,
                        'shape_catname' => $shape
                    ));
                }

                $years = array_shift($path);

                if (!$path) {
                    // most/:most/:shape/:years
                    return $this->_assembleMatch(array(
                        'action'        => 'brand-mosts',
                        'brand_catname' => $brand->folder,
                        'most_catname'  => $most,
                        'shape_catname' => $shape,
                        'years_catname' => $years
                    ));
                }

                break;

            case 'cars':
                array_shift($path);

                if (!$path) {
                    // :brand/cars
                    return $this->_assembleMatch(array(
                        'action'        => 'cars',
                        'brand_catname' => $brand->folder,
                    ));
                }

                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $page = intval($match[1]);
                    array_shift($path);

                    if (!$path) {
                        // :brand/cars/pageX
                        return $this->_assembleMatch(array(
                            'action'        => 'cars',
                            'brand_catname' => $brand->folder,
                            'page'          => $page
                        ));
                    }

                    return false;
                }

                $cartypeCatname = array_shift($path);

                if (!$path) {
                    // :brand/cars/:cartype_catname
                    return $this->_assembleMatch(array(
                        'action'          => 'cars',
                        'brand_catname'   => $brand->folder,
                        'cartype_catname' => $cartypeCatname
                    ));
                }

                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $page = intval($match[1]);
                    array_shift($path);

                    if (!$path) {
                        // :brand/cars/:cartype_catname/pageX
                        return $this->_assembleMatch(array(
                            'action'          => 'cars',
                            'brand_catname'   => $brand->folder,
                            'cartype_catname' => $cartypeCatname,
                            'page'            => $page
                        ));
                    }

                    return false;
                }

                return false;
                break;

            case 'recent':
                array_shift($path);

                if (!$path) {
                    // :brand/recent
                    return $this->_assembleMatch(array(
                        'action'        => 'recent',
                        'brand_catname' => $brand->folder,
                    ));
                }

                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $page = intval($match[1]);
                    array_shift($path);

                    if (!$path) {
                        // :brand/recent/pageX
                        return $this->_assembleMatch(array(
                            'action'        => 'recent',
                            'brand_catname' => $brand->folder,
                            'page'          => $page
                        ));
                    }

                    return false;
                }

                return false;
                break;

            case 'other':
            case 'mixed':
            case 'logotypes':
                $action = array_shift($path);

                if (!$path) {
                    // :brand/:action
                    return $this->_assembleMatch(array(
                        'action'        => $action,
                        'brand_catname' => $brand->folder,
                    ));
                }

                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $page = intval($match[1]);
                    array_shift($path);

                    if (!$path) {
                        // :brand/:action/pageX
                        return $this->_assembleMatch(array(
                            'action'        => $action,
                            'brand_catname' => $brand->folder,
                            'page'          => $page
                        ));
                    }

                    return false;
                }

                if ($path[0] == 'gallery') {
                    array_shift($path);

                    if (!$path) {
                        // :brand/:action/gallery
                        return $this->_assembleMatch(array(
                            'action'        => $action . '-gallery',
                            'brand_catname' => $brand->folder
                        ));
                    }

                    if (in_array($path[0], ['inbox', 'removing'])) {
                        $gallery = array_shift($path);

                        if (!$path) {
                            // :brand/:action/gallery/:gallery
                            return $this->_assembleMatch(array(
                                'action'        => $action . '-gallery',
                                'brand_catname' => $brand->folder,
                                'gallery'       => $gallery
                            ));
                        }

                        return false;
                    }

                    return false;
                }

                if (count($path)) {
                    $pictureId = $path[0];
                    array_shift($path);

                    if (!$path) {
                        // :brand/:action/:picture
                        return $this->_assembleMatch(array(
                            'action'        => $action . '-picture',
                            'brand_catname' => $brand->folder,
                            'picture_id'    => $pictureId
                        ));
                    }

                    return false;
                }

                return false;
                break;
            case 'concepts':
                $action = array_shift($path);

                if (!$path) {
                    // :brand/:action
                    return $this->_assembleMatch(array(
                        'action'        => $action,
                        'brand_catname' => $brand->folder,
                    ));
                }

                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $page = intval($match[1]);
                    array_shift($path);

                    if (!$path) {
                        // :brand/:action/pageX
                        return $this->_assembleMatch(array(
                            'action'        => $action,
                            'brand_catname' => $brand->folder,
                            'page'          => $page
                        ));
                    }

                    return false;
                }

                return false;
                break;

            case 'engines':
                array_shift($path);

                if (!$path) {
                    // :brand/engines
                    return $this->_assembleMatch(array(
                        'action'        => 'engines',
                        'brand_catname' => $brand->folder,
                    ));
                }

                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $page = intval($match[1]);
                    array_shift($path);

                    if (!$path) {
                        // :brand/engines/pageX
                        return $this->_assembleMatch(array(
                            'action'        => 'engines',
                            'brand_catname' => $brand->folder,
                            'page'          => $page
                        ));
                    }

                    return false;
                }

                $enginePath = array();
                while ($path) {
                    if (!preg_match('|^[0-9]+$|', $path[0], $match)) {
                        break;
                    }
                    $enginePath[] = intval($match[0]);
                    array_shift($path);
                }

                if (!$path) {
                    // :brand/engines/:path
                    return $this->_assembleMatch(array(
                        'action'        => 'engines',
                        'brand_catname' => $brand->folder,
                        'path'          => $enginePath
                    ));
                }

                switch ($path[0]) {
                    case 'cars':
                        array_shift($path);

                        if (!$path) {
                            // :brand/engines/:path/cars
                            return $this->_assembleMatch(array(
                                'action'        => 'engine-cars',
                                'brand_catname' => $brand->folder,
                                'path'          => $enginePath
                            ));
                        }

                        return false;
                        break;

                    case 'specifications':
                        array_shift($path);

                        if (!$path) {
                            // :brand/engines/:path/specifications
                            return $this->_assembleMatch(array(
                                'action'        => 'engine-specs',
                                'brand_catname' => $brand->folder,
                                'path'          => $enginePath
                            ));
                        }

                        return false;
                        break;

                    case 'pictures':
                        array_shift($path);

                        if (!$path) {
                            // :brand/engines/:path/pictures
                            return $this->_assembleMatch(array(
                                'action'        => 'engine-pictures',
                                'brand_catname' => $brand->folder,
                                'path'          => $enginePath
                            ));
                        }

                        $pictureId = $path[0];
                        array_shift($path);

                        if (!$path) {
                            // :brand/engines/:path/pictures/:picture
                            return $this->_assembleMatch(array(
                                'action'        => 'engine-picture',
                                'brand_catname' => $brand->folder,
                                'path'          => $enginePath,
                                'picture_id'    => $pictureId
                            ));
                        }

                        return false;
                        break;

                    case 'gallery':
                        array_shift($path);

                        if (!$path) {
                            // :brand/engines/:path/gallery
                            return $this->_assembleMatch(array(
                                'action'        => 'engine-gallery',
                                'brand_catname' => $brand->folder,
                                'path'          => $enginePath,
                            ));
                        }

                        if (in_array($path[0], ['inbox', 'removing'])) {
                            $gallery = array_shift($path);

                            if (!$path) {
                                // :brand/engines/:path/gallery/:gallery
                                return $this->_assembleMatch(array(
                                    'action'        => 'engine-gallery',
                                    'brand_catname' => $brand->folder,
                                    'path'          => $enginePath,
                                    'gallery'       => $gallery
                                ));
                            }

                            return false;
                        }

                        return false;
                        break;
                }

                return false;
                break;
        }

        $brandCarTable = new Brand_Car();
        $brandCarRow = $brandCarTable->fetchRow(array(
            'brand_id = ?' => $brand->id,
            'catname = ?'  => $path[0]
        ));

        if ($brandCarRow) {
            array_shift($path);

            $treePath = array();

            if (!$path) {
                // :brand/:car_catname
                return $this->_assembleMatch(array(
                    'action'        => 'brand-car',
                    'brand_catname' => $brand->folder,
                    'car_catname'   => $brandCarRow->catname,
                    'path'          => $treePath
                ));
            }

            $carParentTable = new Car_Parent();

            $currentCarId = $brandCarRow->car_id;
            while($path) {
                $carParentRow = $carParentTable->fetchRow(array(
                    'parent_id = ?' => $currentCarId,
                    'catname = ?'   => $path[0]
                ));

                if (!$carParentRow) {
                    break;
                }

                array_shift($path);
                $treePath[] = $carParentRow->catname;

                $currentCarId = $carParentRow->car_id;
            }

            if (!$path) {

                // :brand/:car_catname/:path[]
                return $this->_assembleMatch(array(
                    'action'        => 'brand-car',
                    'brand_catname' => $brand->folder,
                    'car_catname'   => $brandCarRow->catname,
                    'path'          => $treePath
                ));
            }

            if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                $page = intval($match[1]);
                array_shift($path);

                if (!$path) {
                    // :brand/:car_catname/:path[]/pageX
                    return $this->_assembleMatch(array(
                        'action'        => 'brand-car',
                        'brand_catname' => $brand->folder,
                        'car_catname'   => $brandCarRow->catname,
                        'path'          => $treePath,
                        'page'          => $page,
                    ));
                }

                return false;
            }

            switch ($path[0]) {
                case 'tuning':
                case 'sport':
                    $type = array_shift($path);

                    if (!$path) {
                        // :brand/:car_catname/:path[]/:type
                        return $this->_assembleMatch(array(
                            'action'        => 'brand-car',
                            'brand_catname' => $brand->folder,
                            'car_catname'   => $brandCarRow->catname,
                            'path'          => $treePath,
                            'type'          => $type
                        ));
                    }

                    if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                        $page = intval($match[1]);
                        array_shift($path);

                        if (!$path) {
                            // :brand/:car_catname/:path[]/:type/pageX
                            return $this->_assembleMatch(array(
                                'action'        => 'brand-car',
                                'brand_catname' => $brand->folder,
                                'car_catname'   => $brandCarRow->catname,
                                'page'          => $page,
                                'path'          => $treePath,
                                'type'          => $type
                            ));
                        }

                        return false;
                    }

                    switch ($path[0]) {
                        case 'specifications':
                            array_shift($path);

                            if (!$path) {
                                // :brand/:car_catname/:path[]/specifications
                                return $this->_assembleMatch(array(
                                    'action'        => 'brand-car-specifications',
                                    'brand_catname' => $brand->folder,
                                    'car_catname'   => $brandCarRow->catname,
                                    'path'          => $treePath,
                                    'type'          => $type
                                ));
                            }

                            return false;
                            break;
                    }

                    return false;
                    break;

                case 'specifications':
                    array_shift($path);

                    if (!$path) {
                        // :brand/:car_catname/:path[]/specifications
                        return $this->_assembleMatch(array(
                            'action'        => 'brand-car-specifications',
                            'brand_catname' => $brand->folder,
                            'car_catname'   => $brandCarRow->catname,
                            'path'          => $treePath
                        ));
                    }

                    return false;
                    break;

                case 'exact':
                    array_shift($path);

                    if (!$path) {
                        // :brand/:car_catname/:path[]/exact
                        return $this->_assembleMatch(array(
                            'action'        => 'brand-car',
                            'brand_catname' => $brand->folder,
                            'car_catname'   => $brandCarRow->catname,
                            'path'          => $treePath,
                            'exact'         => true
                        ));
                    }

                    if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                        $page = intval($match[1]);
                        array_shift($path);

                        if (!$path) {
                            // :brand/:car_catname/:path[]/pageX
                            return $this->_assembleMatch(array(
                                'action'        => 'brand-car',
                                'brand_catname' => $brand->folder,
                                'car_catname'   => $brandCarRow->catname,
                                'path'          => $treePath,
                                'exact'         => true,
                                'page'          => $page,
                            ));
                        }

                        return false;
                    }

                    switch ($path[0]) {
                        case 'pictures':
                            array_shift($path);
                            return $this->_matchCarPictures($path, $brand, $brandCarRow, $treePath, true);
                            break;
                    }

                    return false;
                    break;

                case 'pictures':
                    array_shift($path);

                    return $this->_matchCarPictures($path, $brand, $brandCarRow, $treePath, false);
                    break;


            }

            return false;
        }

        return false;
    }

    private function _matchCarPictures($path, $brand, $brandCarRow, $treePath, $exact)
    {
        if (!$path) {
            // :brand/:car_catname/:path[]/pictures
            return $this->_assembleMatch(array(
                'action'        => 'brand-car-pictures',
                'brand_catname' => $brand->folder,
                'car_catname'   => $brandCarRow->catname,
                'path'          => $treePath,
                'exact'         => $exact
            ));
        }

        if ($path[0] == 'mod') {
            array_shift($path);

            if ($path) {
                $mod = array_shift($path);

                if (!$path) {
                    // :brand/:car_catname/:path[]/pictures/mod/:mod
                    return $this->_assembleMatch(array(
                        'action'        => 'brand-car-pictures',
                        'brand_catname' => $brand->folder,
                        'car_catname'   => $brandCarRow->catname,
                        'path'          => $treePath,
                        'exact'         => $exact,
                        'mod'           => $mod
                    ));
                }

                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $page = intval($match[1]);
                    array_shift($path);

                    if (!$path) {
                        // :brand/:car_catname/:path[]/pictures/mod/:mod/pageX
                        return $this->_assembleMatch(array(
                            'action'        => 'brand-car-pictures',
                            'brand_catname' => $brand->folder,
                            'car_catname'   => $brandCarRow->catname,
                            'page'          => $page,
                            'path'          => $treePath,
                            'exact'         => $exact,
                            'mod'           => $mod
                        ));
                    }

                    return false;
                }

                return false;
            }

            return false;
        }

        if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
            $page = intval($match[1]);
            array_shift($path);

            if (!$path) {
                // :brand/:car_catname/:path[]/pictures/pageX
                return $this->_assembleMatch(array(
                    'action'        => 'brand-car-pictures',
                    'brand_catname' => $brand->folder,
                    'car_catname'   => $brandCarRow->catname,
                    'page'          => $page,
                    'path'          => $treePath,
                    'exact'         => $exact
                ));
            }

            return false;
        }

        if ($path[0] == 'gallery') {
            array_shift($path);

            if (!$path) {
                // :brand/:car_catname/:path[]/pictures/pageX
                return $this->_assembleMatch(array(
                    'action'        => 'brand-car-gallery',
                    'brand_catname' => $brand->folder,
                    'car_catname'   => $brandCarRow->catname,
                    'path'          => $treePath,
                    'exact'         => $exact
                ));
            }

            if (in_array($path[0], ['inbox', 'removing'])) {
                $gallery = array_shift($path);

                if (!$path) {
                    // :brand/:car_catname/:path[]/pictures/pageX
                    return $this->_assembleMatch(array(
                        'action'        => 'brand-car-gallery',
                        'brand_catname' => $brand->folder,
                        'car_catname'   => $brandCarRow->catname,
                        'path'          => $treePath,
                        'exact'         => $exact,
                        'gallery'       => $gallery
                    ));
                }

                return false;
            }

            return false;
        }

        $pictureId = $path[0];
        array_shift($path);

        if (!count($path)) {
            // :brand/:car_catname/:path[]/pictures/:picture
            return $this->_assembleMatch(array(
                'action'        => 'brand-car-picture',
                'brand_catname' => $brand->folder,
                'car_catname'   => $brandCarRow->catname,
                'path'          => $treePath,
                'exact'         => $exact,
                'picture_id'    => $pictureId
            ));
        }

        return false;
    }

    public function assemble($data = array(), $reset = false, $encode = false)
    {
        $def = $this->_defaults;
        if (!$reset)
            $def = array_merge($def, $this->_variables);
        $data = array_merge($def, $data);

        if ($encode) {
            foreach ($data as &$value) {
                if (is_string($value)) {
                    $value = urlencode($value);
                } elseif (is_array($value)) {
                    foreach ($value as &$sValue) {
                        $sValue = urlencode($sValue);
                    }
                }
            }
        }

        $url = array($data['brand_catname']);

        switch ($data['action'])
        {
            case 'engine-gallery':
                $url[] = 'engines';
                if (isset($data['path']) && is_array($data['path'])) {
                    foreach ($data['path'] as $node) {
                        $url[] = $node;
                    }
                }
                $url[] = 'gallery';
                if (isset($data['gallery']) && $data['gallery']) {
                    $url[] = $data['gallery'];
                }
                break;

            case 'engine-pictures':
                $url[] = 'engines';
                if (isset($data['path']) && is_array($data['path'])) {
                    foreach ($data['path'] as $node) {
                        $url[] = $node;
                    }
                }
                $url[] = 'pictures';
                break;

            case 'engine-picture':
                $url[] = 'engines';
                if (isset($data['path']) && is_array($data['path'])) {
                    foreach ($data['path'] as $node) {
                        $url[] = $node;
                    }
                }
                $url[] = 'pictures';
                $url[] = $data['picture_id'];
                break;

            case 'engine-cars':
                $url[] = 'engines';
                if (isset($data['path']) && is_array($data['path'])) {
                    foreach ($data['path'] as $node) {
                        $url[] = $node;
                    }
                }
                $url[] = 'cars';
                break;

            case 'engine-specs':
                $url[] = 'engines';
                if (isset($data['path']) && is_array($data['path'])) {
                    foreach ($data['path'] as $node) {
                        $url[] = $node;
                    }
                }
                $url[] = 'specifications';
                break;

            case 'engines':
                $url[] = 'engines';
                if (isset($data['path']) && is_array($data['path'])) {
                    foreach ($data['path'] as $node) {
                        $url[] = $node;
                    }
                }
                if (isset($data['page']) && $data['page'] > 1) {
                    $url[] = 'page' . $data['page'];
                }
                break;

            case 'mixed':
            case 'other':
            case 'concepts':
            case 'logotypes':
                $url[] = $data['action'];
                if (isset($data['page']) && $data['page'] > 1)
                    $url[] = 'page' . $data['page'];
                break;

            case 'mixed-picture':
                $url[] = 'mixed';
                $url[] = $data['picture_id'];
                break;
            case 'other-picture':
                $url[] = 'other';
                $url[] = $data['picture_id'];
                break;
            case 'logotypes-picture':
                $url[] = 'logotypes';
                $url[] = $data['picture_id'];
                break;

            case 'mixed-gallery':
                $url[] = 'mixed';
                $url[] = 'gallery';
                if (isset($data['gallery']) && $data['gallery']) {
                    $url[] = $data['gallery'];
                }
                break;
            case 'other-gallery':
                $url[] = 'other';
                $url[] = 'gallery';
                if (isset($data['gallery']) && $data['gallery']) {
                    $url[] = $data['gallery'];
                }
                break;
            case 'logotypes-gallery':
                $url[] = 'logotypes';
                $url[] = 'gallery';
                if (isset($data['gallery']) && $data['gallery']) {
                    $url[] = $data['gallery'];
                }
                break;

            case 'brand-car':
                $url[] = $data['car_catname'];
                if (isset($data['path']) && is_array($data['path'])) {
                    foreach ($data['path'] as $node) {
                        $url[] = $node;
                    }
                }
                if (isset($data['type']) && $data['type']) {
                    $url[] = $data['type'];
                }
                if (isset($data['page']) && $data['page'] > 1) {
                    $url[] = 'page' . $data['page'];
                }
                break;

            case 'brand-car-pictures':
                $url[] = $data['car_catname'];
                if (isset($data['path']) && is_array($data['path'])) {
                    foreach ($data['path'] as $node) {
                        $url[] = $node;
                    }
                }
                if (isset($data['exact']) && $data['exact']) {
                    $url[] = 'exact';
                }
                $url[] = 'pictures';
                if (isset($data['mod']) && $data['mod']) {
                    $url[] = 'mod';
                    $url[] = $data['mod'];
                }
                if (isset($data['page']) && $data['page'] > 1) {
                    $url[] = 'page' . $data['page'];
                }
                break;

            case 'brand-car-picture':
                $url[] = $data['car_catname'];
                if (isset($data['path']) && is_array($data['path'])) {
                    foreach ($data['path'] as $node) {
                        $url[] = $node;
                    }
                }
                if (isset($data['exact']) && $data['exact']) {
                    $url[] = 'exact';
                }
                $url[] = 'pictures';
                $url[] = $data['picture_id'];
                break;

            case 'brand-car-gallery':
                $url[] = $data['car_catname'];
                if (isset($data['path']) && is_array($data['path'])) {
                    foreach ($data['path'] as $node) {
                        $url[] = $node;
                    }
                }
                if (isset($data['exact']) && $data['exact']) {
                    $url[] = 'exact';
                }
                $url[] = 'pictures';
                $url[] = 'gallery';
                if (isset($data['gallery']) && $data['gallery']) {
                    $url[] = $data['gallery'];
                }
                break;

            case 'brand-car-specifications':
                $url[] = $data['car_catname'];
                if (isset($data['path']) && is_array($data['path'])) {
                    foreach ($data['path'] as $node) {
                        $url[] = $node;
                    }
                }
                if (isset($data['type']) && $data['type']) {
                    $url[] = $data['type'];
                }
                $url[] = 'specifications';
                break;

            case 'cars':
                $url[] = $data['action'];
                if (isset($data['cartype_catname']) && $data['cartype_catname'])
                    $url[] = $data['cartype_catname'];
                if (isset($data['page']) && $data['page'] > 1)
                    $url[] = 'page' . $data['page'];
                break;

            case 'recent':
                $url[] = $data['action'];
                if (isset($data['page']) && $data['page'] > 1)
                    $url[] = 'page' . $data['page'];
                break;

            case 'brand-mosts':
                $url[] = 'mosts';
                if (isset($data['most_catname']) && $data['most_catname']) {
                    $url[] = $data['most_catname'];
                    if (isset($data['shape_catname']) && $data['shape_catname']) {
                        $url[] = $data['shape_catname'];
                        if (isset($data['years_catname']) && $data['years_catname']) {
                            $url[] = $data['years_catname'];
                        }
                    }
                }
                break;
        }

        return implode(self::DELIMETER, $url) . self::DELIMETER;
    }
}

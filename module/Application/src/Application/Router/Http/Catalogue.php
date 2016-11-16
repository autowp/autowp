<?php

namespace Application\Router\Http;

use Zend\Router\Http\RouteInterface;
use Zend\Router\Http\RouteMatch;
use Zend\Stdlib\RequestInterface as Request;

use Application\Model\DbTable\Brand as BrandTable;
use Application\Model\DbTable\BrandCar;
use Application\Model\DbTable\Vehicle\ParentTable as VehicleParent;

use Exception;

class Catalogue implements RouteInterface
{
    const DELIMETER = '/';

    private $defaults = [];

    /**
     * Create a new route with given options.
     *
     * @param  array|\Traversable $options
     * @return Catalogue
     */
    public static function factory($options = [])
    {
        return new self($options);
    }

    public function __construct($options = [])
    {
        $this->defaults = $options['defaults'];
    }

    private function assembleMatch(array $data, $length)
    {
        $result = array_replace($this->defaults, $data);
        return new RouteMatch($result, $length);
    }

    /**
     * Match a given request.
     *
     * @param  Request $request
     * @return RouteMatch|null
     */
    public function match(Request $request)
    {
        if (! method_exists($request, 'getUri')) {
            return;
        }

        $path = $request->getUri()->getPath();

        $length = strlen($path);

        $data = $this->defaults;

        $path = trim($path, self::DELIMETER);
        $path = explode(self::DELIMETER, $path);

        foreach ($path as &$node) {
            $node = urldecode($node);
        }

        if (! count($path)) {
            return false;
        }

        if (strlen($path[0]) <= 0) {
            return false;
        }

        $brands = new BrandTable();
        $brand = $brands->fetchRow([
           'folder = ?' => $path[0]
        ]);

        if (! $brand) {
            return false;
        }

        $data['brand_catname'] = $brand->folder;
        array_shift($path);

        if (! $path) {
            // :brand
            return $this->assembleMatch([
                'action'        => 'brand',
                'brand_catname' => $brand->folder,
            ], $length);
        }

        $match = null;

        switch ($path[0]) {
            case 'mosts':
                array_shift($path);

                if (! $path) {
                    // most
                    return $this->assembleMatch([
                        'action'        => 'brand-mosts',
                        'brand_catname' => $brand->folder,
                    ], $length);
                }

                $most = array_shift($path);

                if (! $path) {
                    // most/:most
                    return $this->assembleMatch([
                        'action'        => 'brand-mosts',
                        'brand_catname' => $brand->folder,
                        'most_catname'  => $most
                    ], $length);
                }

                $shape = array_shift($path);

                if (! $path) {
                    // most/:most/:shape
                    return $this->assembleMatch([
                        'action'        => 'brand-mosts',
                        'brand_catname' => $brand->folder,
                        'most_catname'  => $most,
                        'shape_catname' => $shape
                    ], $length);
                }

                $years = array_shift($path);

                if (! $path) {
                    // most/:most/:shape/:years
                    return $this->assembleMatch([
                        'action'        => 'brand-mosts',
                        'brand_catname' => $brand->folder,
                        'most_catname'  => $most,
                        'shape_catname' => $shape,
                        'years_catname' => $years
                    ], $length);
                }

                break;

            case 'cars':
                array_shift($path);

                if (! $path) {
                    // :brand/cars
                    return $this->assembleMatch([
                        'action'        => 'cars',
                        'brand_catname' => $brand->folder,
                    ], $length);
                }

                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $page = intval($match[1]);
                    array_shift($path);

                    if (! $path) {
                        // :brand/cars/pageX
                        return $this->assembleMatch([
                            'action'        => 'cars',
                            'brand_catname' => $brand->folder,
                            'page'          => $page
                        ], $length);
                    }

                    return false;
                }

                $cartypeCatname = array_shift($path);

                if (! $path) {
                    // :brand/cars/:cartype_catname
                    return $this->assembleMatch([
                        'action'          => 'cars',
                        'brand_catname'   => $brand->folder,
                        'cartype_catname' => $cartypeCatname
                    ], $length);
                }

                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $page = intval($match[1]);
                    array_shift($path);

                    if (! $path) {
                        // :brand/cars/:cartype_catname/pageX
                        return $this->assembleMatch([
                            'action'          => 'cars',
                            'brand_catname'   => $brand->folder,
                            'cartype_catname' => $cartypeCatname,
                            'page'            => $page
                        ], $length);
                    }

                    return false;
                }

                return false;
                break;

            case 'recent':
                array_shift($path);

                if (! $path) {
                    // :brand/recent
                    return $this->assembleMatch([
                        'action'        => 'recent',
                        'brand_catname' => $brand->folder,
                    ], $length);
                }

                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $page = intval($match[1]);
                    array_shift($path);

                    if (! $path) {
                        // :brand/recent/pageX
                        return $this->assembleMatch([
                            'action'        => 'recent',
                            'brand_catname' => $brand->folder,
                            'page'          => $page
                        ], $length);
                    }

                    return false;
                }

                return false;
                break;

            case 'other':
            case 'mixed':
            case 'logotypes':
                $action = array_shift($path);

                if (! $path) {
                    // :brand/:action
                    return $this->assembleMatch([
                        'action'        => $action,
                        'brand_catname' => $brand->folder,
                    ], $length);
                }

                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $page = intval($match[1]);
                    array_shift($path);

                    if (! $path) {
                        // :brand/:action/pageX
                        return $this->assembleMatch([
                            'action'        => $action,
                            'brand_catname' => $brand->folder,
                            'page'          => $page
                        ], $length);
                    }

                    return false;
                }

                if ($path[0] == 'gallery') {
                    array_shift($path);

                    if (! $path) {
                        // :brand/:action/gallery
                        return $this->assembleMatch([
                            'action'        => $action . '-gallery',
                            'brand_catname' => $brand->folder
                        ], $length);
                    }

                    if (in_array($path[0], ['inbox', 'removing'])) {
                        $gallery = array_shift($path);

                        if (! $path) {
                            // :brand/:action/gallery/:gallery
                            return $this->assembleMatch([
                                'action'        => $action . '-gallery',
                                'brand_catname' => $brand->folder,
                                'gallery'       => $gallery
                            ], $length);
                        }

                        return false;
                    }

                    return false;
                }

                if (count($path)) {
                    $pictureId = $path[0];
                    array_shift($path);

                    if (! $path) {
                        // :brand/:action/:picture
                        return $this->assembleMatch([
                            'action'        => $action . '-picture',
                            'brand_catname' => $brand->folder,
                            'picture_id'    => $pictureId
                        ], $length);
                    }

                    return false;
                }

                return false;
                break;
            case 'concepts':
                $action = array_shift($path);

                if (! $path) {
                    // :brand/:action
                    return $this->assembleMatch([
                        'action'        => $action,
                        'brand_catname' => $brand->folder,
                    ], $length);
                }

                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $page = intval($match[1]);
                    array_shift($path);

                    if (! $path) {
                        // :brand/:action/pageX
                        return $this->assembleMatch([
                            'action'        => $action,
                            'brand_catname' => $brand->folder,
                            'page'          => $page
                        ], $length);
                    }

                    return false;
                }

                return false;
                break;

            case 'engines':
                array_shift($path);

                if (! $path) {
                    // :brand/engines
                    return $this->assembleMatch([
                        'action'        => 'engines',
                        'brand_catname' => $brand->folder,
                    ], $length);
                }

                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $page = intval($match[1]);
                    array_shift($path);

                    if (! $path) {
                        // :brand/engines/pageX
                        return $this->assembleMatch([
                            'action'        => 'engines',
                            'brand_catname' => $brand->folder,
                            'page'          => $page
                        ], $length);
                    }

                    return false;
                }

                $enginePath = [];
                while ($path) {
                    if (! preg_match('|^[0-9]+$|', $path[0], $match)) {
                        break;
                    }
                    $enginePath[] = intval($match[0]);
                    array_shift($path);
                }

                if (! $path) {
                    // :brand/engines/:path
                    return $this->assembleMatch([
                        'action'        => 'engines',
                        'brand_catname' => $brand->folder,
                        'path'          => $enginePath
                    ], $length);
                }

                switch ($path[0]) {
                    case 'cars':
                        array_shift($path);

                        if (! $path) {
                            // :brand/engines/:path/cars
                            return $this->assembleMatch([
                                'action'        => 'engine-cars',
                                'brand_catname' => $brand->folder,
                                'path'          => $enginePath
                            ], $length);
                        }

                        return false;
                        break;

                    case 'specifications':
                        array_shift($path);

                        if (! $path) {
                            // :brand/engines/:path/specifications
                            return $this->assembleMatch([
                                'action'        => 'engine-specs',
                                'brand_catname' => $brand->folder,
                                'path'          => $enginePath
                            ], $length);
                        }

                        return false;
                        break;

                    case 'pictures':
                        array_shift($path);

                        if (! $path) {
                            // :brand/engines/:path/pictures
                            return $this->assembleMatch([
                                'action'        => 'engine-pictures',
                                'brand_catname' => $brand->folder,
                                'path'          => $enginePath
                            ], $length);
                        }

                        $pictureId = $path[0];
                        array_shift($path);

                        if (! $path) {
                            // :brand/engines/:path/pictures/:picture
                            return $this->assembleMatch([
                                'action'        => 'engine-picture',
                                'brand_catname' => $brand->folder,
                                'path'          => $enginePath,
                                'picture_id'    => $pictureId
                            ], $length);
                        }

                        return false;
                        break;

                    case 'gallery':
                        array_shift($path);

                        if (! $path) {
                            // :brand/engines/:path/gallery
                            return $this->assembleMatch([
                                'action'        => 'engine-gallery',
                                'brand_catname' => $brand->folder,
                                'path'          => $enginePath,
                            ], $length);
                        }

                        if (in_array($path[0], ['inbox', 'removing'])) {
                            $gallery = array_shift($path);

                            if (! $path) {
                                // :brand/engines/:path/gallery/:gallery
                                return $this->assembleMatch([
                                    'action'        => 'engine-gallery',
                                    'brand_catname' => $brand->folder,
                                    'path'          => $enginePath,
                                    'gallery'       => $gallery
                                ], $length);
                            }

                            return false;
                        }

                        return false;
                        break;
                }

                return false;
                break;
        }

        $brandCarTable = new BrandCar();
        $brandCarRow = $brandCarTable->fetchRow([
            'brand_id = ?' => $brand->id,
            'catname = ?'  => $path[0]
        ]);

        if ($brandCarRow) {
            array_shift($path);

            $treePath = [];

            if (! $path) {
                // :brand/:car_catname
                return $this->assembleMatch([
                    'action'        => 'brand-car',
                    'brand_catname' => $brand->folder,
                    'car_catname'   => $brandCarRow->catname,
                    'path'          => $treePath
                ], $length);
            }

            $carParentTable = new VehicleParent();

            $currentCarId = $brandCarRow->car_id;
            while ($path) {
                $carParentRow = $carParentTable->fetchRow([
                    'parent_id = ?' => $currentCarId,
                    'catname = ?'   => $path[0]
                ]);

                if (! $carParentRow) {
                    break;
                }

                array_shift($path);
                $treePath[] = $carParentRow->catname;

                $currentCarId = $carParentRow->car_id;
            }

            if (! $path) {
                // :brand/:car_catname/:path[]
                return $this->assembleMatch([
                    'action'        => 'brand-car',
                    'brand_catname' => $brand->folder,
                    'car_catname'   => $brandCarRow->catname,
                    'path'          => $treePath
                ], $length);
            }

            if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                $page = intval($match[1]);
                array_shift($path);

                if (! $path) {
                    // :brand/:car_catname/:path[]/pageX
                    return $this->assembleMatch([
                        'action'        => 'brand-car',
                        'brand_catname' => $brand->folder,
                        'car_catname'   => $brandCarRow->catname,
                        'path'          => $treePath,
                        'page'          => $page,
                    ], $length);
                }

                return false;
            }

            switch ($path[0]) {
                case 'tuning':
                case 'sport':
                    $type = array_shift($path);

                    if (! $path) {
                        // :brand/:car_catname/:path[]/:type
                        return $this->assembleMatch([
                            'action'        => 'brand-car',
                            'brand_catname' => $brand->folder,
                            'car_catname'   => $brandCarRow->catname,
                            'path'          => $treePath,
                            'type'          => $type
                        ], $length);
                    }

                    if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                        $page = intval($match[1]);
                        array_shift($path);

                        if (! $path) {
                            // :brand/:car_catname/:path[]/:type/pageX
                            return $this->assembleMatch([
                                'action'        => 'brand-car',
                                'brand_catname' => $brand->folder,
                                'car_catname'   => $brandCarRow->catname,
                                'page'          => $page,
                                'path'          => $treePath,
                                'type'          => $type
                            ], $length);
                        }

                        return false;
                    }

                    switch ($path[0]) {
                        case 'specifications':
                            array_shift($path);

                            if (! $path) {
                                // :brand/:car_catname/:path[]/specifications
                                return $this->assembleMatch([
                                    'action'        => 'brand-car-specifications',
                                    'brand_catname' => $brand->folder,
                                    'car_catname'   => $brandCarRow->catname,
                                    'path'          => $treePath,
                                    'type'          => $type
                                ], $length);
                            }

                            return false;
                            break;
                    }

                    return false;
                    break;

                case 'specifications':
                    array_shift($path);

                    if (! $path) {
                        // :brand/:car_catname/:path[]/specifications
                        return $this->assembleMatch([
                            'action'        => 'brand-car-specifications',
                            'brand_catname' => $brand->folder,
                            'car_catname'   => $brandCarRow->catname,
                            'path'          => $treePath
                        ], $length);
                    }

                    return false;
                    break;

                case 'exact':
                    array_shift($path);

                    if (! $path) {
                        // :brand/:car_catname/:path[]/exact
                        return $this->assembleMatch([
                            'action'        => 'brand-car',
                            'brand_catname' => $brand->folder,
                            'car_catname'   => $brandCarRow->catname,
                            'path'          => $treePath,
                            'exact'         => true
                        ], $length);
                    }

                    if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                        $page = intval($match[1]);
                        array_shift($path);

                        if (! $path) {
                            // :brand/:car_catname/:path[]/pageX
                            return $this->assembleMatch([
                                'action'        => 'brand-car',
                                'brand_catname' => $brand->folder,
                                'car_catname'   => $brandCarRow->catname,
                                'path'          => $treePath,
                                'exact'         => true,
                                'page'          => $page,
                            ], $length);
                        }

                        return false;
                    }

                    switch ($path[0]) {
                        case 'pictures':
                            array_shift($path);
                            return $this->matchCarPictures($path, $brand, $brandCarRow, $treePath, true, $length);
                            break;
                    }

                    return false;
                    break;

                case 'pictures':
                    array_shift($path);

                    return $this->matchCarPictures($path, $brand, $brandCarRow, $treePath, false, $length);
                    break;

                case 'mod':
                    array_shift($path);

                    if (! $path) {
                        return false;
                    }

                    $mod = array_shift($path);

                    // :brand/:car_catname/:path[]/mod/:mod
                    return $this->assembleMatch([
                        'action'        => 'brand-car',
                        'brand_catname' => $brand->folder,
                        'car_catname'   => $brandCarRow->catname,
                        'path'          => $treePath,
                        'mod'           => $mod
                    ], $length);
                    break;

                case 'modgroup':
                    array_shift($path);

                    if (! $path) {
                        return false;
                    }

                    $modgroup = array_shift($path);

                    // :brand/:car_catname/:path[]/modgroup/:modgroup
                    return $this->assembleMatch([
                        'action'        => 'brand-car',
                        'brand_catname' => $brand->folder,
                        'car_catname'   => $brandCarRow->catname,
                        'path'          => $treePath,
                        'modgroup'      => $modgroup
                    ], $length);
                    break;
            }

            return false;
        }

        return false;
    }

    private function matchCarPictures($path, $brand, $brandCarRow, $treePath, $exact, $length)
    {
        if (! $path) {
            // :brand/:car_catname/:path[]/pictures
            return $this->assembleMatch([
                'action'        => 'brand-car-pictures',
                'brand_catname' => $brand->folder,
                'car_catname'   => $brandCarRow->catname,
                'path'          => $treePath,
                'exact'         => $exact
            ], $length);
        }

        if ($path[0] == 'mod') {
            array_shift($path);

            if ($path) {
                $mod = array_shift($path);

                if (! $path) {
                    // :brand/:car_catname/:path[]/pictures/mod/:mod
                    return $this->assembleMatch([
                        'action'        => 'brand-car-pictures',
                        'brand_catname' => $brand->folder,
                        'car_catname'   => $brandCarRow->catname,
                        'path'          => $treePath,
                        'exact'         => $exact,
                        'mod'           => $mod
                    ], $length);
                }

                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $page = intval($match[1]);
                    array_shift($path);

                    if (! $path) {
                        // :brand/:car_catname/:path[]/pictures/mod/:mod/pageX
                        return $this->assembleMatch([
                            'action'        => 'brand-car-pictures',
                            'brand_catname' => $brand->folder,
                            'car_catname'   => $brandCarRow->catname,
                            'page'          => $page,
                            'path'          => $treePath,
                            'exact'         => $exact,
                            'mod'           => $mod
                        ], $length);
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

            if (! $path) {
                // :brand/:car_catname/:path[]/pictures/pageX
                return $this->assembleMatch([
                    'action'        => 'brand-car-pictures',
                    'brand_catname' => $brand->folder,
                    'car_catname'   => $brandCarRow->catname,
                    'page'          => $page,
                    'path'          => $treePath,
                    'exact'         => $exact
                ], $length);
            }

            return false;
        }

        if ($path[0] == 'gallery') {
            array_shift($path);

            if (! $path) {
                // :brand/:car_catname/:path[]/pictures/pageX
                return $this->assembleMatch([
                    'action'        => 'brand-car-gallery',
                    'brand_catname' => $brand->folder,
                    'car_catname'   => $brandCarRow->catname,
                    'path'          => $treePath,
                    'exact'         => $exact
                ], $length);
            }

            if (in_array($path[0], ['inbox', 'removing'])) {
                $gallery = array_shift($path);

                if (! $path) {
                    // :brand/:car_catname/:path[]/pictures/pageX
                    return $this->assembleMatch([
                        'action'        => 'brand-car-gallery',
                        'brand_catname' => $brand->folder,
                        'car_catname'   => $brandCarRow->catname,
                        'path'          => $treePath,
                        'exact'         => $exact,
                        'gallery'       => $gallery
                    ], $length);
                }

                return false;
            }

            return false;
        }

        $pictureId = $path[0];
        array_shift($path);

        if (! count($path)) {
            // :brand/:car_catname/:path[]/pictures/:picture
            return $this->assembleMatch([
                'action'        => 'brand-car-picture',
                'brand_catname' => $brand->folder,
                'car_catname'   => $brandCarRow->catname,
                'path'          => $treePath,
                'exact'         => $exact,
                'picture_id'    => $pictureId
            ], $length);
        }

        return false;
    }

    /**
     * Assemble the route.
     *
     * @param  array $params
     * @param  array $options
     * @return mixed
     */
    public function assemble(array $params = [], array $options = [])
    {
        $data = $params;

        $def = $this->defaults;
        $data = array_merge($def, $data);

        foreach ($data as &$value) {
            if (is_string($value)) {
                $value = urlencode($value);
            } elseif (is_array($value)) {
                foreach ($value as &$sValue) {
                    $sValue = urlencode($sValue);
                }
            }
        }

        if (! isset($data['brand_catname'])) {
            throw new Exception('`brand_catname` expected');
        }

        $url = [$data['brand_catname']];

        switch ($data['action']) {
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
                if (isset($data['page']) && $data['page'] > 1) {
                    $url[] = 'page' . $data['page'];
                }
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
                if (isset($data['modgroup']) && $data['modgroup']) {
                    $url[] = 'modgroup';
                    $url[] = $data['modgroup'];
                }
                if (isset($data['mod']) && $data['mod']) {
                    $url[] = 'mod';
                    $url[] = $data['mod'];
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
                if (isset($data['cartype_catname']) && $data['cartype_catname']) {
                    $url[] = $data['cartype_catname'];
                }
                if (isset($data['page']) && $data['page'] > 1) {
                    $url[] = 'page' . $data['page'];
                }
                break;

            case 'recent':
                $url[] = $data['action'];
                if (isset($data['page']) && $data['page'] > 1) {
                    $url[] = 'page' . $data['page'];
                }
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

        return self::DELIMETER . implode(self::DELIMETER, $url) . self::DELIMETER;
    }

    /**
     * Get a list of parameters used while assembling.
     *
     * @return array
     */
    public function getAssembledParams()
    {
    }
}

<?php

namespace Autowp\Controller\Router\Route;

use Zend_Config;

class Category extends AbstractRoute
{
    protected $_defaults = array(
        'controller' => 'category',
        'action'     => 'index'
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

        if (!count($path)) {
            return false;
        }

        if ($path[0] != $this->_defaults['controller']) {
            return false;
        }

        array_shift($path);

        if (!count($path)) {
            // category
            return $this->_assembleMatch(array(
                'action' => 'index',
            ));
        }

        $categoryCatname = $path[0];
        array_shift($path);

        if (!count($path)) {
            // category/:category_catname
            return $this->_assembleMatch(array(
                'action'           => 'category',
                'category_catname' => $categoryCatname
            ));
        }

        $isOther = false;
        if ($path[0] == 'other') {
            array_shift($path);
            $isOther = true;
        }

        if (!count($path)) {
            // category/:category_catname/[:other/]
            return $this->_assembleMatch(array(
                'action'           => 'category',
                'category_catname' => $categoryCatname,
                'other'            => $isOther,
            ));
        }

        if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
            $page = intval($match[1]);
            array_shift($path);

            if (!count($path)) {
                // category/:category_catname/[:other/]pageX
                return $this->_assembleMatch(array(
                    'action'           => 'category',
                    'category_catname' => $categoryCatname,
                    'other'            => $isOther,
                    'page'             => $page
                ));
            }

            return false;
        }

        if ($path[0] == 'pictures') {
            array_shift($path);

            if (!count($path)) {
                // category/:category_catname/[:other/]pictures
                return $this->_assembleMatch(array(
                    'action'           => 'category-pictures',
                    'category_catname' => $categoryCatname,
                    'other'            => $isOther,
                ));
            }

            if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                $page = intval($match[1]);
                array_shift($path);

                if (!count($path)) {
                    // category/:category_catname/[:other/]pageX
                    return $this->_assembleMatch(array(
                        'action'           => 'category-pictures',
                        'category_catname' => $categoryCatname,
                        'other'            => $isOther,
                        'page'             => $page
                    ));
                }

                return false;
            }

            $pictureId = $path[0];
            array_shift($path);

            if (!count($path)) {
                // category/:category_catname/[:other/]:picture
                return $this->_assembleMatch(array(
                    'action'           => 'category-picture',
                    'category_catname' => $categoryCatname,
                    'other'            => $isOther,
                    'picture_id'       => $pictureId
                ));
            }

            if ($path[0] == 'gallery') {
                array_shift($path);

                if (!$path) {
                    // category/:category_catname/[:other/]:picture/gallery
                    return $this->_assembleMatch(array(
                        'action'           => 'category-picture-gallery',
                        'category_catname' => $categoryCatname,
                        'other'            => $isOther,
                        'picture_id'       => $pictureId
                    ));
                }

                return false;
            }

            return false;
        }

        if (preg_match('|^([0-9]+)$|', $path[0], $match)) {
            $carId = intval($match[1]);
            array_shift($path);

            $treePath = array();
            while (count($path) > 0) {
                $node = array_shift($path);

                if ($node == 'pictures') {

                    if (!count($path)) {
                        // category/:category_catname/[:other/]:car_id/:path/pictures
                        return $this->_assembleMatch(array(
                            'action'           => 'category-pictures',
                            'category_catname' => $categoryCatname,
                            'other'            => $isOther,
                            'car_id'           => $carId,
                            'path'             => $treePath,
                        ));
                    }

                    if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                        array_shift($path);
                        $page = intval($match[1]);

                        if (!count($path)) {
                            // category/:category_catname/[:other/]:car_id/:path/pictures/pageX
                            return $this->_assembleMatch(array(
                                'action'           => 'category-pictures',
                                'category_catname' => $categoryCatname,
                                'other'            => $isOther,
                                'car_id'           => $carId,
                                'path'             => $treePath,
                                'page'             => $page
                            ));
                        }

                        return false;
                    }

                    $pictureId = $path[0];
                    array_shift($path);

                    if (!count($path)) {
                        // category/:category_catname/[:other/]:picture
                        return $this->_assembleMatch(array(
                            'action'           => 'category-picture',
                            'category_catname' => $categoryCatname,
                            'other'            => $isOther,
                            'car_id'           => $carId,
                            'path'             => $treePath,
                            'picture_id'       => $pictureId
                        ));
                    }

                    if ($path[0] == 'gallery') {
                        array_shift($path);

                        if (!$path) {
                            // category/:category_catname/[:other/]:picture/gallery
                            return $this->_assembleMatch(array(
                                'action'           => 'category-picture-gallery',
                                'category_catname' => $categoryCatname,
                                'other'            => $isOther,
                                'car_id'           => $carId,
                                'path'             => $treePath,
                                'picture_id'       => $pictureId
                            ));
                        }

                        return false;
                    }

                    return false;
                }

                if (preg_match('|^page([0-9]+)$|', $node, $match)) {
                    $page = intval($match[1]);

                    if (!count($path)) {
                        // category/:category_catname/[:other/]:car_id/:path/pageX
                        return $this->_assembleMatch(array(
                            'action'           => 'category',
                            'category_catname' => $categoryCatname,
                            'other'            => $isOther,
                            'car_id'           => $carId,
                            'path'             => $treePath,
                            'page'             => $page
                        ));
                    }

                    return false;
                }

                $treePath[] = $node;
            }

            // category/:category_catname/[:other/]:car_id/:path
            return $this->_assembleMatch(array(
                'action'           => 'category',
                'category_catname' => $categoryCatname,
                'other'            => $isOther,
                'car_id'           => $carId,
                'path'             => $treePath,
            ));

            return false;
        }

        return false;
    }

    public function assemble($data = array(), $reset = false, $encode = false)
    {
        $data = array_merge(array_merge($this->_defaults, $this->_variables), $data);

        $url = array($data['controller']);

        switch ($data['action']) {
            case 'index':
                break;

            case 'category':
                if (isset($data['category_catname'])) {
                    $url[] = $data['category_catname'];

                    if (isset($data['other']) && $data['other']) {
                        $url[] = 'other';
                    }

                    if (isset($data['car_id']) && $data['car_id']) {
                        $url[] = $data['car_id'];

                        if (isset($data['path']) && is_array($data['path'])) {
                            foreach ($data['path'] as $node) {
                                $url[] = $node;
                            }
                        }
                    }

                    if (isset($data['page']) && $data['page'] > 1) {
                        $url[] = 'page'.$data['page'];
                    }
                }
                break;

            case 'category-pictures':
                if (isset($data['category_catname'])) {
                    $url[] = $data['category_catname'];

                    if (isset($data['other']) && $data['other']) {
                        $url[] = 'other';
                    }

                    if (isset($data['car_id']) && $data['car_id']) {
                        $url[] = $data['car_id'];

                        if (isset($data['path']) && is_array($data['path'])) {
                            foreach ($data['path'] as $node) {
                                $url[] = $node;
                            }
                        }
                    }

                    $url[] = 'pictures';

                    if (isset($data['page']) && $data['page'] > 1) {
                        $url[] = 'page'.$data['page'];
                    }
                }
                break;

            case 'category-picture':
                if (isset($data['category_catname'])) {
                    $url[] = $data['category_catname'];

                    if (isset($data['other']) && $data['other']) {
                        $url[] = 'other';
                    }

                    if (isset($data['car_id']) && $data['car_id']) {
                        $url[] = $data['car_id'];

                        if (isset($data['path']) && is_array($data['path'])) {
                            foreach ($data['path'] as $node) {
                                $url[] = $node;
                            }
                        }
                    }

                    $url[] = 'pictures';
                    $url[] = $data['picture_id'];

                }
                break;

            case 'category-picture-gallery':
                if (isset($data['category_catname'])) {
                    $url[] = $data['category_catname'];

                    if (isset($data['other']) && $data['other']) {
                        $url[] = 'other';
                    }

                    if (isset($data['car_id']) && $data['car_id']) {
                        $url[] = $data['car_id'];

                        if (isset($data['path']) && is_array($data['path'])) {
                            foreach ($data['path'] as $node) {
                                $url[] = $node;
                            }
                        }
                    }

                    $url[] = 'pictures';
                    $url[] = $data['picture_id'];
                    $url[] = 'gallery';

                }
                break;
        }

        return implode(self::DELIMETER, $url);
    }
}

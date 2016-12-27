<?php

namespace Application\Router\Http;

use Zend\Router\Http\RouteInterface;
use Zend\Router\Http\RouteMatch;
use Zend\Stdlib\RequestInterface as Request;

class Category implements RouteInterface
{
    const DELIMETER = '/';

    private $defaults = [];

    /**
     * Create a new route with given options.
     *
     * @param  array|\Traversable $options
     * @return Category
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

    public function match(Request $request)
    {
        if (! method_exists($request, 'getUri')) {
            return;
        }

        $path = $request->getUri()->getPath();

        $length = strlen($path);

        $path = trim($path, self::DELIMETER);
        $path = explode(self::DELIMETER, $path);

        if (! count($path)) {
            return false;
        }

        if ($path[0] != 'category') {
            return false;
        }

        array_shift($path);

        if (! count($path)) {
            // category
            return $this->assembleMatch([
                'action' => 'index',
            ], $length);
        }

        $categoryCatname = $path[0];
        array_shift($path);

        if (! count($path)) {
            // category/:category_catname
            return $this->assembleMatch([
                'action'           => 'category',
                'category_catname' => $categoryCatname
            ], $length);
        }

        $isOther = false;
        if ($path[0] == 'other') {
            array_shift($path);
            $isOther = true;
        }

        if (! count($path)) {
            // category/:category_catname/[:other/]
            return $this->assembleMatch([
                'action'           => 'category',
                'category_catname' => $categoryCatname,
                'other'            => $isOther,
            ], $length);
        }

        if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
            $page = intval($match[1]);
            array_shift($path);

            if (! count($path)) {
                // category/:category_catname/[:other/]pageX
                return $this->assembleMatch([
                    'action'           => 'category',
                    'category_catname' => $categoryCatname,
                    'other'            => $isOther,
                    'page'             => $page
                ], $length);
            }

            return false;
        }

        if ($path[0] == 'pictures') {
            array_shift($path);

            if (! count($path)) {
                // category/:category_catname/[:other/]pictures
                return $this->assembleMatch([
                    'action'           => 'category-pictures',
                    'category_catname' => $categoryCatname,
                    'other'            => $isOther,
                ], $length);
            }

            if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                $page = intval($match[1]);
                array_shift($path);

                if (! count($path)) {
                    // category/:category_catname/[:other/]pageX
                    return $this->assembleMatch([
                        'action'           => 'category-pictures',
                        'category_catname' => $categoryCatname,
                        'other'            => $isOther,
                        'page'             => $page
                    ], $length);
                }

                return false;
            }

            $pictureId = $path[0];
            array_shift($path);

            if (! count($path)) {
                // category/:category_catname/[:other/]:picture
                return $this->assembleMatch([
                    'action'           => 'category-picture',
                    'category_catname' => $categoryCatname,
                    'other'            => $isOther,
                    'picture_id'       => $pictureId
                ], $length);
            }

            if ($path[0] == 'gallery') {
                array_shift($path);

                if (! $path) {
                    // category/:category_catname/[:other/]:picture/gallery
                    return $this->assembleMatch([
                        'action'           => 'category-picture-gallery',
                        'category_catname' => $categoryCatname,
                        'other'            => $isOther,
                        'picture_id'       => $pictureId
                    ], $length);
                }

                return false;
            }

            return false;
        }

        $treePath = [];
        while (count($path) > 0) {
            $node = array_shift($path);

            if ($node == 'pictures') {
                if (! count($path)) {
                    // category/:category_catname/[:other/]:item_id/:path/pictures
                    return $this->assembleMatch([
                        'action'           => 'category-pictures',
                        'category_catname' => $categoryCatname,
                        'other'            => $isOther,
                        'path'             => $treePath,
                    ], $length);
                }

                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    array_shift($path);
                    $page = intval($match[1]);

                    if (! count($path)) {
                        // category/:category_catname/[:other/]:item_id/:path/pictures/pageX
                        return $this->assembleMatch([
                            'action'           => 'category-pictures',
                            'category_catname' => $categoryCatname,
                            'other'            => $isOther,
                            'path'             => $treePath,
                            'page'             => $page
                        ], $length);
                    }

                    return false;
                }

                $pictureId = $path[0];
                array_shift($path);

                if (! count($path)) {
                    // category/:category_catname/[:other/]:picture
                    return $this->assembleMatch([
                        'action'           => 'category-picture',
                        'category_catname' => $categoryCatname,
                        'other'            => $isOther,
                        'path'             => $treePath,
                        'picture_id'       => $pictureId
                    ], $length);
                }

                if ($path[0] == 'gallery') {
                    array_shift($path);

                    if (! $path) {
                        // category/:category_catname/[:other/]:picture/gallery
                        return $this->assembleMatch([
                            'action'           => 'category-picture-gallery',
                            'category_catname' => $categoryCatname,
                            'other'            => $isOther,
                            'path'             => $treePath,
                            'picture_id'       => $pictureId
                        ], $length);
                    }

                    return false;
                }

                return false;
            }

            if (preg_match('|^page([0-9]+)$|', $node, $match)) {
                $page = intval($match[1]);

                if (! count($path)) {
                    // category/:category_catname/[:other/]:item_id/:path/pageX
                    return $this->assembleMatch([
                        'action'           => 'category',
                        'category_catname' => $categoryCatname,
                        'other'            => $isOther,
                        'path'             => $treePath,
                        'page'             => $page
                    ], $length);
                }

                return false;
            }

            $treePath[] = $node;
        }

        // category/:category_catname/[:other/]:item_id/:path
        return $this->assembleMatch([
            'action'           => 'category',
            'category_catname' => $categoryCatname,
            'other'            => $isOther,
            'path'             => $treePath,
        ], $length);

        return false;
    }

    public function assemble(array $params = [], array $options = [])
    {
        $data = $params;

        $def = $this->defaults;
        $data = array_merge($def, $data);

        $url = ['category'];

        switch ($data['action']) {
            case 'index':
                break;

            case 'category':
                if (isset($data['category_catname'])) {
                    $url[] = $data['category_catname'];

                    if (isset($data['other']) && $data['other']) {
                        $url[] = 'other';
                    }

                    if (isset($data['path']) && is_array($data['path'])) {
                        foreach ($data['path'] as $node) {
                            $url[] = $node;
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

                    if (isset($data['path']) && is_array($data['path'])) {
                        foreach ($data['path'] as $node) {
                            $url[] = $node;
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

                    if (isset($data['path']) && is_array($data['path'])) {
                        foreach ($data['path'] as $node) {
                            $url[] = $node;
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

                    if (isset($data['path']) && is_array($data['path'])) {
                        foreach ($data['path'] as $node) {
                            $url[] = $node;
                        }
                    }

                    $url[] = 'pictures';
                    $url[] = $data['picture_id'];
                    $url[] = 'gallery';
                }
                break;
        }

        return self::DELIMETER . implode(self::DELIMETER, $url);
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

<?php

namespace Application\Router\Http;

use Zend\Router\Http\RouteInterface;
use Zend\Router\Http\RouteMatch;
use Zend\Stdlib\RequestInterface as Request;

use Application\Model\DbTable\Brand as BrandTable;

class Articles implements RouteInterface
{
    const DELIMETER = '/';

    private $defaults = [];

    /**
     * Create a new route with given options.
     *
     * @param  array|\Traversable $options
     * @return void
     */
    public static function factory($options = [])
    {
        return new self($options);
    }

    public function __construct($options = [])
    {
        $this->defaults = $options['defaults'];
    }

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

        if ($path[0] != 'articles') {
            return false;
        }

        array_shift($path);

        $match = null;

        if ($path) {
            $brandTable = new BrandTable();

            $isBrandFolder = (bool)$brandTable->fetchRow([
                'folder = ?' => $path[0]
            ]);

            if ($isBrandFolder) {
                $data['action'] = 'index';
                $data['brand_catname'] = $path[0];
                array_shift($path);

                if ($path && preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $data['page'] = intval($match[1]);
                    array_shift($path);
                }
            } else {
                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $data['page'] = intval($match[1]);
                    array_shift($path);
                } else {
                    $data['action'] = 'article';
                    $data['article_catname'] = $path[0];
                    array_shift($path);
                }
            }
        }

        return new RouteMatch($data, $length);
    }

    public function assemble(array $params = [], array $options = [])
    {
        $data = $params;

        $def = $this->defaults;
        $data = array_replace($def, $data);

        foreach ($data as &$value) {
            if (is_string($value)) {
                $value = urlencode($value);
            }
        }

        $url = ['articles'];

        switch ($data['action']) {
            case 'article':
                $url[] = $data['article_catname'];
                break;

            case 'index':
                if (isset($data['brand_catname'])) {
                    $url[] = $data['brand_catname'];
                }

                if (isset($data['page']) && $data['page'] > 1) {
                    $url[] = 'page'.$data['page'];
                }
            default:
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

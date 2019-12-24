<?php

namespace Application\Router\Http;

use Exception;
use Traversable;
use Zend\Router\Http\RouteInterface;
use Zend\Router\Http\RouteMatch;
use Zend\Stdlib\RequestInterface as Request;

class PictureFile implements RouteInterface
{
    private const URI_DELIMITER = '/';

    private $defaults = [];

    /**
     * Create a new route with given options.
     *
     * @param  array|Traversable $options
     * @return PictureFile
     */
    public static function factory($options = [])
    {
        return new self($options);
    }

    public function __construct($options = [])
    {
        $this->defaults = $options['defaults'];
    }

    /**
     * @param Request $request
     * @return \Zend\Router\RouteMatch|null
     */
    public function match(Request $request)
    {
        if (! method_exists($request, 'getUri')) {
            return null;
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $uri = $request->getUri();
        $path = $uri->getPath();

        $length = strlen($path);

        $path = explode(self::URI_DELIMITER, trim($path, self::URI_DELIMITER));

        foreach ($path as &$node) {
            $node = urldecode($node);
        }

        if (! count($path)) {
            return null;
        }
        if ($path[0] != 'pictures') {
            return null;
        }

        $variables = [
            'hostname' => $uri->getHost(),
            'file'     => implode('/', $path)
        ];

        return new RouteMatch(array_replace($this->defaults, $variables), $length);
    }

    public function assemble(array $params = [], array $options = [])
    {
        $data = array_replace($this->defaults, $params);

        if (! isset($data['file'])) {
            throw new Exception("`file` not specified");
        }

        $encoded = explode('/', $data['file']);
        foreach ($encoded as &$value) {
            $value = urlencode($value);
        }
        unset($value);

        if (isset($options['uri'])) {
            if (! isset($data['hostname'])) {
                throw new Exception("`hostname` not specified");
            }

            $options['uri']->setHost($data['hostname']);
        }

        return self::URI_DELIMITER . implode(self::URI_DELIMITER, $encoded);
    }

    /**
     * Get a list of parameters used while assembling.
     *
     * @return array
     */
    public function getAssembledParams()
    {
        return [];
    }
}

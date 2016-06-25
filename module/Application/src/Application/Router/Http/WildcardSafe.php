<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Router\Http;

use Zend\Router\Http\RouteMatch;
use Zend\Router\Http\Wildcard;
use Zend\Stdlib\RequestInterface as Request;

/**
 * WildcardSafe route.
 */
class WildcardSafe extends Wildcard
{
    private $exclude = ['controller', 'action'];

    /**
     * match(): defined by RouteInterface interface.
     *
     * @see    \Zend\Router\RouteInterface::match()
     * @param  Request      $request
     * @param  integer|null $pathOffset
     * @return RouteMatch|null
     */
    public function match(Request $request, $pathOffset = null)
    {
        if (!method_exists($request, 'getUri')) {
            return;
        }

        $uri  = $request->getUri();
        $path = $uri->getPath() ?: '';

        if ($path === '/') {
            $path = '';
        }

        if ($pathOffset !== null) {
            $path = substr($path, $pathOffset) ?: '';
        }

        $matches = [];
        $params  = explode($this->paramDelimiter, $path);

        if (count($params) > 1 && ($params[0] !== '' || end($params) === '')) {
            return;
        }

        if ($this->keyValueDelimiter === $this->paramDelimiter) {
            $count = count($params);

            for ($i = 1; $i < $count; $i += 2) {
                if (isset($params[$i + 1])) {
                    $matches[rawurldecode($params[$i])] = rawurldecode($params[$i + 1]);
                }
            }
        } else {
            array_shift($params);

            foreach ($params as $param) {
                $param = explode($this->keyValueDelimiter, $param, 2);

                if (isset($param[1])) {
                    $matches[rawurldecode($param[0])] = rawurldecode($param[1]);
                }
            }
        }

        foreach ($this->exclude as $key) {
            unset($matches[$key]);
        }

        return new RouteMatch(array_merge($this->defaults, $matches), strlen($path));
    }

    /**
     * assemble(): Defined by RouteInterface interface.
     *
     * @see    \Zend\Router\RouteInterface::assemble()
     * @param  array $params
     * @param  array $options
     * @return mixed
     */
    public function assemble(array $params = [], array $options = [])
    {
        $elements              = [];
        $mergedParams          = array_replace($this->defaults, $params);
        $this->assembledParams = [];

        foreach ($this->exclude as $key) {
            unset($mergedParams[$key]);
        }

        if ($mergedParams) {
            foreach ($mergedParams as $key => $value) {
                $elements[] = rawurlencode($key) . $this->keyValueDelimiter . rawurlencode($value);

                $this->assembledParams[] = $key;
            }

            return $this->paramDelimiter . implode($this->paramDelimiter, $elements);
        }

        return '';
    }
}

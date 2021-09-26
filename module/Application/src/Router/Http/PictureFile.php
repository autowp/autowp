<?php

namespace Application\Router\Http;

use ArrayAccess;
use Exception;
use Laminas\Router\Http\RouteInterface;
use Laminas\Router\Http\RouteMatch;
use Laminas\Stdlib\RequestInterface as Request;
use Traversable;

use function array_replace;
use function explode;
use function implode;
use function method_exists;
use function strlen;
use function trim;
use function urldecode;
use function urlencode;

class PictureFile implements RouteInterface
{
    private const URI_DELIMITER = '/';

    private array $defaults;

    /**
     * Create a new route with given options.
     *
     * @param  array|Traversable $options
     */
    public static function factory($options = []): self
    {
        return new self($options);
    }

    /**
     * @param array|ArrayAccess $options
     */
    public function __construct($options = [])
    {
        $this->defaults = $options['defaults'];
    }

    public function match(Request $request): ?RouteMatch
    {
        if (! method_exists($request, 'getUri')) {
            return null;
        }

        $uri  = $request->getUri();
        $path = $uri->getPath();

        $length = strlen($path);

        $path = explode(self::URI_DELIMITER, trim($path, self::URI_DELIMITER));

        foreach ($path as &$node) {
            $node = urldecode($node);
        }

        if ($path[0] !== 'pictures') {
            return null;
        }

        $variables = [
            'hostname' => $uri->getHost(),
            'file'     => implode('/', $path),
        ];

        return new RouteMatch(array_replace($this->defaults, $variables), $length);
    }

    /**
     * @throws Exception
     */
    public function assemble(array $params = [], array $options = []): string
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
     */
    public function getAssembledParams(): array
    {
        return [];
    }
}

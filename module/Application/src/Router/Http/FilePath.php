<?php

namespace Application\Router\Http;

use Exception;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Router\Http\RouteInterface;
use Laminas\Router\Http\RouteMatch;
use Laminas\Stdlib\RequestInterface;
use Traversable;

use function array_merge;
use function array_replace;
use function count;
use function explode;
use function implode;
use function strlen;
use function urldecode;
use function urlencode;

class FilePath implements RouteInterface
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

    public function __construct(array $options = [])
    {
        $this->defaults = $options['defaults'];
    }

    public function match(RequestInterface $request): ?RouteMatch
    {
        if (! $request instanceof Request) {
            return null;
        }

        $path = $request->getUri()->getPath();

        $length = strlen($path);

        $path = $request->getRequestUri();
        $path = explode(self::URI_DELIMITER, $path);

        foreach ($path as &$node) {
            $node = urldecode($node);
        }

        if (! count($path)) {
            return null;
        }

        $variables = [
            'file' => implode('/', $path),
        ];

        return new RouteMatch(array_replace($this->defaults, $variables), $length);
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @throws Exception
     */
    public function assemble(array $params = [], array $options = []): string
    {
        $data = array_merge($this->defaults, $params);

        if (! isset($data['file'])) {
            throw new Exception("`file` not specified");
        }

        $encoded = explode('/', $data['file']);
        foreach ($encoded as &$value) {
            $value = urlencode($value);
        }
        unset($value);

        return implode(self::URI_DELIMITER, $encoded);
    }

    /**
     * Get a list of parameters used while assembling.
     */
    public function getAssembledParams(): array
    {
        return [];
    }
}

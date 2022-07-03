<?php

declare(strict_types=1);

namespace Application\Validator\Attr;

use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

use function array_replace;
use function is_array;

class UnitIdFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): UnitId
    {
        return new UnitId(array_replace(is_array($options) ? $options : [], [
            'table' => $container->get('TableManager')->get('attrs_units'),
        ]));
    }
}

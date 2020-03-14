<?php

namespace Application\Validator\Attr;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

use function array_replace;
use function is_array;

class TypeIdFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): TypeId
    {
        return new TypeId(array_replace(is_array($options) ? $options : [], [
            'table' => $container->get('TableManager')->get('attrs_types'),
        ]));
    }
}

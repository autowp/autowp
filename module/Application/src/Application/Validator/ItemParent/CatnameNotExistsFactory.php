<?php

namespace Application\Validator\ItemParent;

use Application\Model\ItemParent;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

use function array_merge;

class CatnameNotExistsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): CatnameNotExists
    {
        return new CatnameNotExists(array_merge($options, [
            'itemParent' => $container->get(ItemParent::class),
        ]));
    }
}

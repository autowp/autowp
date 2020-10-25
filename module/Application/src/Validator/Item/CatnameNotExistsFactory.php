<?php

namespace Application\Validator\Item;

use Application\Model\Item;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

use function array_replace;

class CatnameNotExistsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): CatnameNotExists
    {
        return new CatnameNotExists(array_replace($options, [
            'item' => $container->get(Item::class),
        ]));
    }
}

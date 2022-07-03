<?php

declare(strict_types=1);

namespace Application\Validator\ItemParent;

use Application\Model\ItemParent;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

use function array_merge;

class CatnameNotExistsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): CatnameNotExists
    {
        return new CatnameNotExists(array_merge($options, [
            'itemParent' => $container->get(ItemParent::class),
        ]));
    }
}

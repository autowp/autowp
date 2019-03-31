<?php

namespace Application\Validator\ItemParent;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Application\Model\ItemParent;

class CatnameNotExistsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return CatnameNotExists
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new CatnameNotExists(array_merge($options, [
            'itemParent' => $container->get(ItemParent::class)
        ]));
    }
}

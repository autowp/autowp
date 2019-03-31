<?php

namespace Application\Validator\Item;

use Application\Model\Item;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

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
        return new CatnameNotExists(array_replace($options, [
            'item' => $container->get(Item::class)
        ]));
    }
}

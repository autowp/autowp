<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Application\ItemNameFormatter as Model;

class ItemNameFormatterFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Model
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Model(
            $container->get('MvcTranslator'),
            $container->get('ViewRenderer')
        );
    }
}

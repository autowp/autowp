<?php

namespace Application\View\Helper\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\View\Helper\ApiData as Helper;

class ApiDataFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        return new Helper(
            $hydrators->get(\Application\Hydrator\Api\UserHydrator::class)
        );
    }
}

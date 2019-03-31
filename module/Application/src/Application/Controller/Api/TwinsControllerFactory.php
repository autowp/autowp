<?php

namespace Application\Controller\Api;

use Application\Model\Twins;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class TwinsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return TwinsController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new TwinsController(
            $container->get(Twins::class),
            $container->get('longCache')
        );
    }
}

<?php

namespace Application\Controller\Plugin\Service;

use Application\Model\Log;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Application\Controller\Plugin\Log as Plugin;

class LogFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Plugin
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Plugin(
            $container->get(Log::class)
        );
    }
}

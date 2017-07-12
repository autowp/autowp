<?php

namespace Application\Controller\Plugin\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Plugin\Language as Plugin;

class LanguageFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Plugin(
            $container->get(\Application\Language::class)
        );
    }
}

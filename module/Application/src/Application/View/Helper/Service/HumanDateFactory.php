<?php

namespace Application\View\Helper\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\View\Helper\HumanDate as Helper;

class HumanDateFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $language = $container->get(\Application\Language::class);
        return new Helper(
            $language->getLanguage()
        );
    }
}

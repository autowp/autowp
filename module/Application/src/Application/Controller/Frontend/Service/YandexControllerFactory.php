<?php

namespace Application\Controller\Frontend\Service;

use Application\Model\CarOfDay;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Frontend\YandexController as Controller;

class YandexControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Controller
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        return new Controller(
            $config['yandex'],
            $container->get(CarOfDay::class)
        );
    }
}

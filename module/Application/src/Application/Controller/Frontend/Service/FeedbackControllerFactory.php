<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\FeedbackController as Controller;

class FeedbackControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get('FeedbackForm'),
            $container->get(\Zend\Mail\Transport\TransportInterface::class),
            $container->get('Config')['feedback']
        );
    }
}

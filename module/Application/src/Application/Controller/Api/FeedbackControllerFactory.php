<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class FeedbackControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $filters = $container->get('InputFilterManager');
        $config = $container->get('Config');

        return new FeedbackController(
            $filters->get('api_feedback'),
            $container->get(\Zend\Mail\Transport\TransportInterface::class),
            $config['feedback'],
            $config['recaptcha']
        );
    }
}

<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\Mail\Transport\TransportInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class FeedbackControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return FeedbackController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $filters = $container->get('InputFilterManager');
        $config = $container->get('Config');

        return new FeedbackController(
            $filters->get('api_feedback'),
            $container->get(TransportInterface::class),
            $config['feedback'],
            $config['recaptcha'],
            (bool)getenv('AUTOWP_CAPTCHA')
        );
    }
}

<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Laminas\Mail\Transport\TransportInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

use function getenv;

class FeedbackControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): FeedbackController
    {
        $filters = $container->get('InputFilterManager');
        $config  = $container->get('Config');

        return new FeedbackController(
            $filters->get('api_feedback'),
            $container->get(TransportInterface::class),
            $config['feedback'],
            $config['recaptcha'],
            (bool) $config['captcha']
        );
    }
}

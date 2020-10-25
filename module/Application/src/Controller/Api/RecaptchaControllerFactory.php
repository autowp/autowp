<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class RecaptchaControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): RecaptchaController
    {
        $config = $container->get('Config');
        return new RecaptchaController(
            $config['recaptcha']['publicKey']
        );
    }
}

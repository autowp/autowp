<?php

namespace Application\Factory;

use Application\Controller\Api\Plugin\Oauth2 as OAuth2Plugin;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use OAuth2\Server as OAuth2Server;

class OAuth2PluginFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): OAuth2Plugin
    {
        $services = $container->get('ServiceManager');

        // For BC, if the ZF\OAuth2\Service\OAuth2Server service returns an
        // OAuth2\Server instance, wrap it in a closure.
        $oauth2ServerFactory = $services->get('ZF\OAuth2\Service\OAuth2Server');
        if ($oauth2ServerFactory instanceof OAuth2Server) {
            $oauth2Server        = $oauth2ServerFactory;
            $oauth2ServerFactory = function () use ($oauth2Server) {
                return $oauth2Server;
            };
        }

        return new OAuth2Plugin(
            $oauth2ServerFactory,
            $services->get('ZF\OAuth2\Provider\UserId')
        );
    }

    public function createService(ServiceLocatorInterface $controllers): OAuth2Plugin
    {
        return $this($controllers, self::class);
    }
}

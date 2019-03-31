<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace Application\Factory;

use OAuth2\Server as OAuth2Server;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Interop\Container\ContainerInterface;
use Application\Controller\Api\Plugin\Oauth2 as OAuth2Plugin;

class OAuth2PluginFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return OAuth2Plugin
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $services = $container->get('ServiceManager');

        // For BC, if the ZF\OAuth2\Service\OAuth2Server service returns an
        // OAuth2\Server instance, wrap it in a closure.
        $oauth2ServerFactory = $services->get('ZF\OAuth2\Service\OAuth2Server');
        if ($oauth2ServerFactory instanceof OAuth2Server) {
            $oauth2Server = $oauth2ServerFactory;
            $oauth2ServerFactory = function () use ($oauth2Server) {
                return $oauth2Server;
            };
        }

        return new OAuth2Plugin(
            $oauth2ServerFactory,
            $services->get('ZF\OAuth2\Provider\UserId')
        );
    }

    /**
     * @param ServiceLocatorInterface $controllers
     * @return OAuth2Plugin
     */
    public function createService(ServiceLocatorInterface $controllers)
    {
        return $this($controllers, OAuth2PluginFactory::class);
    }
}

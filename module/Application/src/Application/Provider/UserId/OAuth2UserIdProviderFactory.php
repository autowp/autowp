<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace Application\Provider\UserId;

use Interop\Container\ContainerInterface;
use Zend\Authentication\AuthenticationService;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class OAuth2UserIdProviderFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');

        return new OAuth2UserIdProvider($config);
    }

    /**
     * @param ServiceLocatorInterface $services
     * @return AuthenticationService
     */
    public function createService(ServiceLocatorInterface $services)
    {
        return $this($services, OAuth2UserIdProviderFactory::class);
    }
}

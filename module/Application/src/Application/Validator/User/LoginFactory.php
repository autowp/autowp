<?php

namespace Application\Validator\User;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class LoginFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Login(array_replace($options ? $options : [], [
            'userModel' => $container->get(\Autowp\User\Model\User::class)
        ]));
    }
}

<?php

namespace Application\Validator\User;

use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class LoginFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Login
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Login(array_replace($options ? $options : [], [
            'userModel' => $container->get(User::class)
        ]));
    }
}

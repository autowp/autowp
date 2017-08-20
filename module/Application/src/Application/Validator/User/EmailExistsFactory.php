<?php

namespace Application\Validator\User;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class EmailExistsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new EmailExists(array_replace($options ? $options : [], [
            'userModel' => $container->get(\Autowp\User\Model\User::class)
        ]));
    }
}

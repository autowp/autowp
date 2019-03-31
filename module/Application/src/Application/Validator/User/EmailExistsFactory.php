<?php

namespace Application\Validator\User;

use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class EmailExistsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return EmailExists
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new EmailExists(array_replace($options ? $options : [], [
            'userModel' => $container->get(User::class)
        ]));
    }
}

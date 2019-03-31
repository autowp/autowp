<?php

namespace Application\Validator\User;

use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class EmailNotExistsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return EmailNotExists
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new EmailNotExists(array_replace($options ? $options : [], [
            'userModel' => $container->get(User::class)
        ]));
    }
}

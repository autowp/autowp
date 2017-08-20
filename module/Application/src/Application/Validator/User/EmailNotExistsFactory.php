<?php

namespace Application\Validator\User;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class EmailNotExistsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new EmailNotExists(array_replace($options ? $options : [], [
            'userModel' => $container->get(\Autowp\User\Model\User::class)
        ]));
    }
}

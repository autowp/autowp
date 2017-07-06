<?php

namespace Application\Model;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class UserItemSubscribeFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $this->get(\Application\Db\TableManager::class);
        return new UserItemSubscribe(
            $tables->get('user_item_subscribe')
        );
    }
}

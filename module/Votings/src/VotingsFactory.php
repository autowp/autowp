<?php

namespace Autowp\Votings;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class VotingsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new Votings(
            $tables->get('voting'),
            $tables->get('voting_variant'),
            $tables->get('voting_variant_vote'),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}

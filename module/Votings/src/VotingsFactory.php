<?php

namespace Autowp\Votings;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class VotingsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param  string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Votings
    {
        $tables = $container->get('TableManager');
        return new Votings(
            $tables->get('voting'),
            $tables->get('voting_variant'),
            $tables->get('voting_variant_vote')
        );
    }
}

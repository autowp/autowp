<?php

declare(strict_types=1);

namespace Autowp\Votings;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class VotingsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
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

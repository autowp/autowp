<?php

declare(strict_types=1);

namespace Autowp\Votings;

use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class VotingsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param null|array $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Votings
    {
        $tables = $container->get('TableManager');
        return new Votings(
            $tables->get('voting'),
            $tables->get('voting_variant'),
            $tables->get('voting_variant_vote')
        );
    }
}

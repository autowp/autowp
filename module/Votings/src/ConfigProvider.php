<?php

namespace Autowp\Votings;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencyConfig(),
            'tables'       => $this->getTablesConfig(),
        ];
    }

    public function getDependencyConfig(): array
    {
        return [
            'factories' => [
                Votings::class => VotingsFactory::class,
            ],
        ];
    }

    public function getTablesConfig(): array
    {
        return [
            'voting'              => [],
            'voting_variant'      => [],
            'voting_variant_vote' => [],
        ];
    }
}

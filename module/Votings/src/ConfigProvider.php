<?php

declare(strict_types=1);

namespace Autowp\Votings;

class ConfigProvider
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencyConfig(),
            'tables'       => $this->getTablesConfig(),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getDependencyConfig(): array
    {
        return [
            'factories' => [
                Votings::class => VotingsFactory::class,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTablesConfig(): array
    {
        return [
            'voting'              => [],
            'voting_variant'      => [],
            'voting_variant_vote' => [],
        ];
    }
}

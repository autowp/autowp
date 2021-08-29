<?php

declare(strict_types=1);

namespace Autowp\Forums;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencyConfig(),
            'tables'       => $this->getTablesConfig(),
        ];
    }

    /**
     * Return application-level dependency configuration.
     */
    public function getDependencyConfig(): array
    {
        return [
            'factories' => [
                Forums::class => ForumsFactory::class,
            ],
        ];
    }

    public function getTablesConfig(): array
    {
        return [
            'forums_themes' => [],
            'forums_topics' => [],
        ];
    }
}

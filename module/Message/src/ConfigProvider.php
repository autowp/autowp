<?php

namespace Autowp\Message;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencyConfig(),
            'tables'       => $this->getTablesConfig()
        ];
    }

    /**
     * Return application-level dependency configuration.
     */
    public function getDependencyConfig(): array
    {
        return [
            'factories' => [
                MessageService::class => Service\MessageServiceFactory::class
            ]
        ];
    }

    public function getTablesConfig(): array
    {
        return [
            'personal_messages' => [],
        ];
    }
}

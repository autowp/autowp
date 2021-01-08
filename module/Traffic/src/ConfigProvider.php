<?php

namespace Autowp\Traffic;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencyConfig(),
        ];
    }

    /**
     * Return application-level dependency configuration.
     */
    public function getDependencyConfig(): array
    {
        return [
            'factories' => [
                TrafficControl::class => Service\TrafficControlFactory::class,
            ],
        ];
    }
}

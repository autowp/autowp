<?php

declare(strict_types=1);

namespace Application\Model\Service;

use Application\Model\Log;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class LogFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Log
    {
        $tables = $container->get('TableManager');
        return new Log(
            $tables->get('log_events'),
            $tables->get('log_events_articles'),
            $tables->get('log_events_item'),
            $tables->get('log_events_pictures'),
            $tables->get('log_events_user')
        );
    }
}

<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Autowp\Votings\Votings;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class VotingControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): VotingController
    {
        return new VotingController($container->get(Votings::class));
    }
}

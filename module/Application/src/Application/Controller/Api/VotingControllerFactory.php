<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\VotingVariantVoteHydrator;
use Autowp\Votings\Votings;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class VotingControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): VotingController
    {
        $filters   = $container->get('InputFilterManager');
        $hydrators = $container->get('HydratorManager');
        return new VotingController(
            $container->get(Votings::class),
            $filters->get('api_voting_variant_vote_get'),
            $hydrators->get(VotingVariantVoteHydrator::class)
        );
    }
}

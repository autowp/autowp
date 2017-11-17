<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class VotingControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $filters = $container->get('InputFilterManager');
        $hydrators = $container->get('HydratorManager');
        return new VotingController(
            $container->get(\Autowp\Votings\Votings::class),
            $filters->get('api_voting_variant_vote_get'),
            $hydrators->get(\Application\Hydrator\Api\VotingVariantVoteHydrator::class)
        );
    }
}

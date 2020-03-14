<?php

namespace Application\Model\Service;

use Application\Model\PictureVote;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PictureVoteFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): PictureVote
    {
        $tables = $container->get('TableManager');
        return new PictureVote(
            $tables->get('picture_vote'),
            $tables->get('picture_vote_summary')
        );
    }
}

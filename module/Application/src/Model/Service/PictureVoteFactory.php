<?php

declare(strict_types=1);

namespace Application\Model\Service;

use Application\Model\PictureVote;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PictureVoteFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): PictureVote
    {
        $tables = $container->get('TableManager');
        return new PictureVote(
            $tables->get('picture_vote'),
            $tables->get('picture_vote_summary')
        );
    }
}

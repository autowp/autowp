<?php

declare(strict_types=1);

namespace Application\Service;

use Application\Comments;
use Application\Model\Picture;
use Autowp\Comments\CommentsService;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CommentsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Comments
    {
        $tables = $container->get('TableManager');
        return new Comments(
            $container->get(CommentsService::class),
            $container->get(Picture::class),
            $tables->get('articles'),
            $tables->get('item')
        );
    }
}

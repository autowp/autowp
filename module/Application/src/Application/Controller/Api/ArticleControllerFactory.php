<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\ArticleHydrator;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ArticleControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ArticleController
    {
        $tables    = $container->get('TableManager');
        $hydrators = $container->get('HydratorManager');
        $filters   = $container->get('InputFilterManager');
        return new ArticleController(
            $tables->get('articles'),
            $filters->get('api_article_list'),
            $hydrators->get(ArticleHydrator::class)
        );
    }
}

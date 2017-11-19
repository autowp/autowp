<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ArticleControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');
        return new ArticleController(
            $tables->get('articles'),
            $filters->get('api_article_list'),
            $hydrators->get(\Application\Hydrator\Api\ArticleHydrator::class)
        );
    }
}

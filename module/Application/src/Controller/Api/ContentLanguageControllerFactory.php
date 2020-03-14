<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ContentLanguageControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): ContentLanguageController {
        $config = $container->get('Config');
        return new ContentLanguageController(
            $config['content_languages']
        );
    }
}

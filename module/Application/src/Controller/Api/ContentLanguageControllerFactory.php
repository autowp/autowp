<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ContentLanguageControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(
        containerinterface $container,
        $requestedName,
        ?array $options = null
    ): ContentLanguageController {
        $config = $container->get('Config');
        return new ContentLanguageController(
            $config['content_languages']
        );
    }
}

<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Model\ItemParent;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ItemParentLanguageControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(
        containerinterface $container,
        $requestedName,
        ?array $options = null
    ): ItemParentLanguageController {
        $filters = $container->get('InputFilterManager');

        return new ItemParentLanguageController(
            $container->get(ItemParent::class),
            $filters->get('api_item_parent_language_put')
        );
    }
}

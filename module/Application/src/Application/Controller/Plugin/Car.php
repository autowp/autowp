<?php

namespace Application\Controller\Plugin;

use Application\ItemNameFormatter;
use Application\Model\Item;
use ArrayAccess;
use Exception;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class Car extends AbstractPlugin
{
    private ItemNameFormatter $itemNameFormatter;

    private Item $itemModel;

    public function __construct(
        ItemNameFormatter $itemNameFormatter,
        Item $item
    ) {
        $this->itemNameFormatter = $itemNameFormatter;
        $this->itemModel         = $item;
    }

    public function __invoke(): self
    {
        return $this;
    }

    /**
     * @param array|ArrayAccess $vehicle
     * @throws Exception
     */
    public function formatName($vehicle, string $language): string
    {
        return $this->itemNameFormatter->format(
            $this->itemModel->getNameData($vehicle, $language),
            $language
        );
    }
}

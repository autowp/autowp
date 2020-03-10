<?php

namespace Application\View\Helper;

use Application\ItemNameFormatter;
use Laminas\View\Helper\AbstractHelper;

class Car extends AbstractHelper
{
    private ItemNameFormatter $itemNameFormatter;

    public function __construct(ItemNameFormatter $itemNameFormatter)
    {
        $this->itemNameFormatter = $itemNameFormatter;
    }

    public function __invoke(): self
    {
        return $this;
    }

    /**
     * @param array|ArrayAccess $item
     * @throws \Exception
     */
    public function htmlTitle($item): string
    {
        return $this->itemNameFormatter->formatHtml($item, $this->view->language());
    }

    /**
     * @param array|ArrayAccess $item
     * @throws \Exception
     */
    public function textTitle($item): string
    {
        return $this->itemNameFormatter->format($item, $this->view->language());
    }
}

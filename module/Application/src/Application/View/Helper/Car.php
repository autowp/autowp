<?php

namespace Application\View\Helper;

use Application\ItemNameFormatter;
use Laminas\View\Helper\AbstractHelper;

class Car extends AbstractHelper
{
    /** @var ItemNameFormatter */
    private $itemNameFormatter;

    public function __construct(ItemNameFormatter $itemNameFormatter)
    {
        $this->itemNameFormatter = $itemNameFormatter;
    }

    public function __invoke()
    {
        return $this;
    }

    public function htmlTitle($item)
    {
        return $this->itemNameFormatter->formatHtml($item, $this->view->language());
    }

    public function textTitle($item)
    {
        return $this->itemNameFormatter->format($item, $this->view->language());
    }
}

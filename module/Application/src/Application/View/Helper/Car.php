<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Application\ItemNameFormatter;

class Car extends AbstractHelper
{
    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    public function __construct(ItemNameFormatter $itemNameFormatter)
    {
        $this->itemNameFormatter = $itemNameFormatter;
    }

    public function __invoke()
    {
        return $this;
    }

    public function htmlTitle(array $item)
    {
        return $this->itemNameFormatter->formatHtml($item, $this->view->language());
    }

    public function textTitle(array $item)
    {
        return $this->itemNameFormatter->format($item, $this->view->language());
    }
}

<?php

namespace Application\View\Helper;

use Application\ItemNameFormatter;
use Application\Language as AppLanguage;
use ArrayAccess;
use Exception;
use Laminas\View\Helper\AbstractHelper;

class Car extends AbstractHelper
{
    private ItemNameFormatter $itemNameFormatter;

    private AppLanguage $language;

    public function __construct(ItemNameFormatter $itemNameFormatter, AppLanguage $language)
    {
        $this->itemNameFormatter = $itemNameFormatter;
        $this->language          = $language;
    }

    public function __invoke(): self
    {
        return $this;
    }

    /**
     * @param array|ArrayAccess $item
     * @throws Exception
     */
    public function htmlTitle($item): string
    {
        return $this->itemNameFormatter->formatHtml($item, $this->language->getLanguage());
    }

    /**
     * @param array|ArrayAccess $item
     * @throws Exception
     */
    public function textTitle($item): string
    {
        return $this->itemNameFormatter->format($item, $this->language->getLanguage());
    }
}

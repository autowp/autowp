<?php

namespace Application\View\Helper;

use Application\LanguagePicker as Model;
use Laminas\View\Helper\AbstractHtmlElement;

class LanguagePicker extends AbstractHtmlElement
{
    private Model $languagePicker;

    public function __construct(Model $languagePicker)
    {
        $this->languagePicker = $languagePicker;
    }

    public function __invoke(): array
    {
        return $this->languagePicker->getItems();
    }
}

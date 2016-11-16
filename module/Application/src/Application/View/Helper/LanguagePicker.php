<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;
use Application\LanguagePicker as Model;

class LanguagePicker extends AbstractHtmlElement
{
    /**
     * @var Model
     */
    private $languagePicker;

    public function __construct(Model $languagePicker)
    {
        $this->languagePicker = $languagePicker;
    }

    public function __invoke()
    {
        return $this->languagePicker->getItems();
    }
}

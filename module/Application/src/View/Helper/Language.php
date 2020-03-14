<?php

namespace Application\View\Helper;

use Application\Language as AppLanguage;
use Laminas\View\Helper\AbstractHelper;

class Language extends AbstractHelper
{
    private AppLanguage $language;

    public function __construct(AppLanguage $language)
    {
        $this->language = $language;
    }

    public function __invoke(): string
    {
        return $this->language->getLanguage();
    }
}

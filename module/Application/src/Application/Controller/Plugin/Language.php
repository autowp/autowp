<?php

namespace Application\Controller\Plugin;

use Application\Language as AppLanguage;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class Language extends AbstractPlugin
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

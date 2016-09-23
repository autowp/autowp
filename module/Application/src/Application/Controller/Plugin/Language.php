<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Application\Language as AppLanguage;

class Language extends AbstractPlugin
{
    /**
     * @var AppLanguage
     */
    private $language = null;

    public function __construct(AppLanguage $language)
    {
        $this->language = $language;
    }

    public function __invoke()
    {
        return $this->language->getLanguage();
    }
}

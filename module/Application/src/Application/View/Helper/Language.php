<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Application\Language as AppLanguage;

class Language extends AbstractHelper
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

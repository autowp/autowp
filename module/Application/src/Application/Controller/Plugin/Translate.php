<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class Translate extends AbstractPlugin
{
    private $translator;

    public function __construct($translator)
    {
        $this->translator = $translator;
    }

    public function __invoke($message, $textDomain = null, $locale = null)
    {
        return $translator->translate($message, $textDomain, $locale);
    }
}

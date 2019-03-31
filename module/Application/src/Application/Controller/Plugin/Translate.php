<?php

namespace Application\Controller\Plugin;

use Zend\I18n\Translator\TranslatorInterface;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class Translate extends AbstractPlugin
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @param string $message
     * @param string $textDomain
     * @param string|null $locale
     * @return string
     */
    public function __invoke(string $message, string $textDomain = 'default', $locale = null)
    {
        return $this->translator->translate($message, $textDomain, $locale);
    }
}

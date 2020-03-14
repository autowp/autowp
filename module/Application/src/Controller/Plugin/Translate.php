<?php

namespace Application\Controller\Plugin;

use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;

class Translate extends AbstractPlugin
{
    private TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function __invoke(string $message, string $textDomain = 'default', ?string $locale = null): string
    {
        return $this->translator->translate($message, $textDomain, $locale);
    }
}

<?php

namespace Application;

use Laminas\Http\PhpEnvironment\Request;
use Locale;

use function array_keys;

class Language
{
    private string $defaultLanguage = 'en';

    private string $language;

    /**
     * @param mixed $request
     */
    public function __construct($request, array $hosts)
    {
        $this->language = $this->defaultLanguage;

        if ($request instanceof Request) {
            $acceptLanguage = $request->getHeader('Accept-Language');
            if ($acceptLanguage) {
                $locale = Locale::acceptFromHttp($acceptLanguage->getFieldValue());
                if ($locale) {
                    $closest = Locale::lookup(array_keys($hosts), $locale, false, $this->defaultLanguage);
                    if (isset($hosts[$closest])) {
                        $this->language = $closest;
                    }
                }
            }
        }
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }
}

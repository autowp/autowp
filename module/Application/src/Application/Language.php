<?php

namespace Application;

use Laminas\Http\PhpEnvironment\Request;

class Language
{
    private string $defaultLanguage = 'en';

    private string $language;

    public function __construct($request, array $hosts)
    {
        $this->language = $this->defaultLanguage;

        $map = [];
        foreach ($hosts as $language => $host) {
            $map[$host['hostname']] = $language;
            foreach ($host['aliases'] as $alias) {
                $map[$alias] = $language;
            }
        }

        if ($request instanceof Request) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $hostname = $request->getServer('HTTP_HOST');
            if (isset($map[$hostname])) {
                $this->language = $map[$hostname];
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

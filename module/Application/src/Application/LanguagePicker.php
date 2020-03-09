<?php

namespace Application;

use Laminas\Http\PhpEnvironment\Request;

class LanguagePicker
{
    private Request $request;

    private array $hosts = [];

    public function __construct(Request $request, array $hosts)
    {
        $this->request = $request;
        $this->hosts   = $hosts;
    }

    public function getItems(): array
    {
        $languages = [];

        $uri = $this->request->getUri();
        foreach ($this->hosts as $itemLanguage => $item) {
            $clone = clone $uri;
            $clone->setHost($item['hostname']);

            $languages[] = [
                'name'     => $item['name'],
                'language' => $itemLanguage,
                'hostname' => $item['hostname'],
                'flag'     => $item['flag'],
                'url'      => $clone->__toString(),
            ];
        }

        return $languages;
    }
}

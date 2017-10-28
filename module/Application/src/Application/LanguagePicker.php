<?php

namespace Application;

class LanguagePicker
{
    private $request;

    /**
     * @var array
     */
    private $hosts = [];

    public function __construct($request, $hosts)
    {
        $this->request = $request;
        $this->hosts = $hosts;
    }

    /**
     * @return array
     */
    public function getItems()
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
                'url'      => $clone->__toString()
            ];
        }

        return $languages;
    }
}

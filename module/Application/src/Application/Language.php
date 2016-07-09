<?php

namespace Application;

class Language
{
    /**
     * @var string
     */
    private $defaultLanguage = 'en';

    /**
     * @var array
     */
    private $whitelist = [
        'fr.wheelsage.org'  => 'fr',
        'en.wheelsage.org'  => 'en',
        'wheelsage.org'     => 'en',
        'www.wheelsage.org' => 'en',
        'autowp.ru'         => 'ru',
        'www.autowp.ru'     => 'ru',
        'ru.autowp.ru'      => 'ru',
        'en.autowp.ru'      => 'ru',
        'fr.autowp.ru'      => 'fr',
        'i.wheelsage.org'   => 'en'
    ];

    /**
     * @var string
     */
    private $language;

    public function __construct($request)
    {
        $this->language = $this->defaultLanguage;

        if ($request instanceof \Zend\Http\PhpEnvironment\Request) {
            $hostname = $request->getServer('HTTP_HOST');
            if (isset($this->whitelist[$hostname])) {
                $this->language = $this->whitelist[$hostname];
            }
        }
    }

    /**
     * @return string
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * @param string $language
     */
    public function setLanguage($language)
    {
        $this->language = $language;
    }
}

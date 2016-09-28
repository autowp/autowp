<?php

namespace Application;

use Zend\Uri\UriFactory;
use Zend\Uri\Uri;

use Exception;

class HostManager
{
    /**
     * @var array
     */
    private $hosts;

    public function __construct(array $hosts)
    {
        $this->hosts = $hosts;
    }

    /**
     * @param string $language
     * @return Uri
     */
    public function getUriByLanguage($language)
    {
        if (!isset($this->hosts[$language])) {
            throw new Exception("Host for language `$language` not found");
        }

        $hostname = $this->hosts[$language]['hostname'];

        return UriFactory::factory('https://' . $hostname);
    }
}
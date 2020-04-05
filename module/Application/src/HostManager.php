<?php

namespace Application;

use Exception;
use Laminas\Uri\Uri;
use Laminas\Uri\UriFactory;

class HostManager
{
    private array $hosts;

    public function __construct(array $hosts)
    {
        $this->hosts = $hosts;
    }

    /**
     * @throws Exception
     */
    public function getUriByLanguage(string $language): Uri
    {
        if (! isset($this->hosts[$language])) {
            throw new Exception("Host for language `$language` not found");
        }

        $hostname = $this->hosts[$language]['hostname'];

        return UriFactory::factory('https://' . $hostname);
    }
}

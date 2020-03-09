<?php

namespace Application;

use Exception;
use Laminas\Uri\Uri;
use Laminas\Uri\UriFactory;

class HostManager
{
    /** @var array */
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

    public function getCookieDomain(string $language): string
    {
        if (! isset($this->hosts[$language])) {
            throw new Exception("Host for language `$language` not found");
        }

        return $this->hosts[$language]['cookie'];
    }
}

<?php

namespace Application\Model\Referer;

use Application\Db\Table;

class Blacklist extends Table
{
    protected $_name = 'referer_blacklist';
    protected $_primary = ['host'];

    public function containsHost($host)
    {
        return (bool)$this->fetchRow([
            'host = ?' => (string)$host
        ]);
    }

    public function containsUrl($url)
    {
        $host = @parse_url($url, PHP_URL_HOST);
        if ($host) {
            return $this->containsHost($host);
        }

        return false;
    }

    public function fetchRowByHost($host)
    {
        return $this->fetchRow([
            'host = ?' => (string)$host
        ]);
    }

    public function fetchRowByUrl($url)
    {
        $host = @parse_url($url, PHP_URL_HOST);
        if ($host) {
            return $this->fetchRowByHost($host);
        }

        return false;
    }
}
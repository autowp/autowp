<?php

class Referer_Blacklist extends Project_Db_Table
{
    protected $_name = 'referer_blacklist';
    protected $_primary = array('host');

    public function containsHost($host)
    {
        return (bool)$this->fetchRow(array(
            'host = ?' => (string)$host
        ));
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
        return $this->fetchRow(array(
            'host = ?' => (string)$host
        ));
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
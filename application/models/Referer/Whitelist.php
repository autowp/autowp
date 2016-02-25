<?php

class Referer_Whitelist extends Project_Db_Table
{
    protected $_name = 'referer_whitelist';
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
}
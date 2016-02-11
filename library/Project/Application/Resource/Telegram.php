<?php

use Application\Service\Telegram;

class Project_Application_Resource_Telegram extends Zend_Application_Resource_ResourceAbstract
{
    public function init()
    {
        return new Telegram($this->getOptions());
    }
}

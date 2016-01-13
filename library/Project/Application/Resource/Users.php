<?php

class Project_Application_Resource_Users
    extends Zend_Application_Resource_ResourceAbstract
{
    public function init()
    {
        return new Application_Service_Users($this->getOptions());
    }
}

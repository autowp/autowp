<?php
/**
 * @see Zend_Application_Resource_ResourceAbstract
 */
require_once 'Zend/Application/Resource/ResourceAbstract.php';


class Project_Application_Resource_Telegram extends Zend_Application_Resource_ResourceAbstract
{
    public function init()
    {
        return new Application_Service_Telegram($this->getOptions());
    }
}

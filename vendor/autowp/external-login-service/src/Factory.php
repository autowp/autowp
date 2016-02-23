<?php

namespace Autowp\ExternalLoginService;

use Autowp\ExternalLoginService\AbstractService;
use Autowp\ExternalLoginService\Exception;

use Zend_Filter_Word_DashToCamelCase;

class Factory
{
    /**
     * @var array
     */
    private $_options;

    public function __construct(array $options)
    {
        $this->_options = $options;
    }

    /**
     * @param string $service
     * @return AbstractService
     * @throws Exception
     */
    public function getService($service, $optionsKey, array $options)
    {
        $service = trim($service);
        if (!isset($this->_options[$optionsKey])) {
            throw new Exception("Service '$optionsKey' options not found");
        }

        $filter = new Zend_Filter_Word_DashToCamelCase();

        $className = 'Autowp\\ExternalLoginService\\' . ucfirst($filter->filter($service));

        $serviceOptions = array_replace($this->_options[$optionsKey], $options);
        $serviceObj = new $className($serviceOptions);

        if (!$serviceObj instanceof AbstractService) {
            throw new Exception(
                "'$className' is not AbstractService"
            );
        }

        return $serviceObj;
    }

    public function getCallbackUrl()
    {
        if (!isset($this->_options['callback'])) {
            throw new Exception('`callback` not set');
        }

        return $this->_options['callback'];
    }
}
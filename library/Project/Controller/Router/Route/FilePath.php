<?php

class Project_Controller_Router_Route_FilePath
    extends Zend_Controller_Router_Route_Abstract
{
    protected $_defaults = array();

    protected $_variables = array();

    /**
     * Instantiates route based on passed Zend_Config structure
     *
     * @param Zend_Config $config Configuration object
     */
    public static function getInstance(Zend_Config $config)
    {
        $defs   = ($config->defaults instanceof Zend_Config) ? $config->defaults->toArray() : array();
        return new self($defs);
    }

    public function __construct($defaults = array())
    {
        $this->_defaults = (array)$defaults;
    }

    public function match($request, $partial = false)
    {
        $data = $this->_defaults;

        $path = $request->getRequestUri();
        $path = explode(self::URI_DELIMITER, $path);

        foreach ($path as &$node) {
            $node = urldecode($node);
        }

        if (!count($path)) {
            return false;
        }

        $this->setMatchedPath($request->getRequestUri());

        $this->_variables = array(
            'file' => implode('/', $path)
        );

        return $this->_variables + $this->_defaults;
    }

    public function assemble($data = array(), $reset = false, $encode = false)
    {
        $def = $this->_defaults;
        if (!$reset) {
            $def = array_merge($def, $this->_variables);
        }
        $data = array_merge($def, $data);

        if (!isset($data['file'])) {
            throw new Exception("`file` not specified");
        }

        $encoded = explode('/', $data['file']);
        if ($encode) {
            foreach ($encoded as &$value) {
                $value = urlencode($value);
            }
            unset($value);
        }

        return implode(self::URI_DELIMITER, $encoded);
    }
}
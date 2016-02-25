<?php

namespace Autowp\Controller\Router\Route;

use Exception;

use Zend_Config;

class Most extends AbstractRoute
{
    protected $_defaults = array(
        'module'     => 'default',
        'controller' => 'most',
        'action'     => 'index'
    );

    /**
     * Instantiates route based on passed Zend_Config structure
     *
     * @param Zend_Config $config Configuration object
     */
    public static function getInstance(Zend_Config $config)
    {
        return new self();
    }

    protected function _assembleMatch(array $data)
    {
        $result = array_merge($this->_defaults, $data);
        $this->_variables = $result;
        return $result;
    }

    public function match($path)
    {
        $data = $this->_defaults;

        $path = trim($path, self::DELIMETER);
        $path = explode(self::DELIMETER, $path);

        foreach ($path as &$node) {
            $node = urldecode($node);
        }

        if (!count($path)) {
            return false;
        }

        if ($path[0] != 'mosts') {
            return false;
        }

        array_shift($path);

        if (!$path) {
            // most
            return $this->_assembleMatch(array(
                'action' => 'index'
            ));
        }

        $most = array_shift($path);

        if (!$path) {
            // most/:most
            return $this->_assembleMatch(array(
                'action'       => 'index',
                'most_catname' => $most
            ));
        }

        $shape = array_shift($path);

        if (!$path) {
            // most/:most/:shape
            return $this->_assembleMatch(array(
                'action'        => 'index',
                'most_catname'  => $most,
                'shape_catname' => $shape
            ));
        }

        $years = array_shift($path);

        if (!$path) {
            // most/:most/:shape/:years
            return $this->_assembleMatch(array(
                'action'        => 'index',
                'most_catname'  => $most,
                'shape_catname' => $shape,
                'years_catname' => $years
            ));
        }

        return false;
    }

    public function assemble($data = array(), $reset = false, $encode = false)
    {
        $def = $this->_defaults;
        if (!$reset) {
            $def = array_merge($def, $this->_variables);
        }
        $data = array_merge($def, $data);

        if ($encode) {
            foreach ($data as &$value) {
                if (is_string($value)) {
                    $value = urlencode($value);
                }
            }
        }

        $url = array('mosts');

        switch ($data['action']) {
            case 'index':
                if (isset($data['most_catname']) && $data['most_catname']) {
                    $url[] = $data['most_catname'];
                    if (isset($data['shape_catname']) && $data['shape_catname']) {
                        $url[] = $data['shape_catname'];
                        if (isset($data['years_catname']) && $data['years_catname']) {
                            $url[] = $data['years_catname'];
                        }
                    }
                }
                break;
            default:
                throw new Exception("Unexcepected action name {$data['action']}");
        }

        return implode(self::DELIMETER, $url) . self::DELIMETER;
    }
}

<?php

class Project_Controller_Router_Route_Inbox extends Project_Controller_Router_Route_Abstract
{
    protected $_defaults = array(
        'module'     => 'default',
        'controller' => 'inbox',
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

        if ($path[0] != $this->_defaults['controller']) {
            return false;
        }

        array_shift($path);

        if (!$path) {
            // inbox
            return $this->_assembleMatch(array(
                'action' => 'index'
            ));
        }

        if (preg_match('|^([0-9]{4})-([0-9]{2})-([0-9]{2})$|', $path[0], $match)) {
            $date = $match[1].'-'.$match[2].'-'.$match[3];
            array_shift($path);

            if (!$path) {
                // inbox/:date
                return $this->_assembleMatch(array(
                    'action' => 'index',
                    'date'   => $date
                ));
            }

            if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                $page = intval($match[1]);
                array_shift($path);

                if (!$path) {
                    // inbox/:date/pageX
                    return $this->_assembleMatch(array(
                        'action' => 'index',
                        'date'   => $date,
                        'page'   => $page
                    ));
                }

                return false;
            }

            return false;
        }

        $brandTable = new Brands();
        if ($row = $brandTable->findRowByCatname($path[0])) {
            $brand = $row->folder;
            array_shift($path);

            if (!$path) {
                // inbox/:brand
                return $this->_assembleMatch(array(
                    'action' => 'index',
                    'brand'  => $brand
                ));
            }

            if (preg_match('|^([0-9]{4})-([0-9]{2})-([0-9]{2})$|', $path[0], $match)) {
                $date = $match[1].'-'.$match[2].'-'.$match[3];
                array_shift($path);

                if (!$path) {
                    // inbox/:brand/:date
                    return $this->_assembleMatch(array(
                        'action' => 'index',
                        'brand'  => $brand,
                        'date'   => $date
                    ));
                }

                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $page = intval($match[1]);
                    array_shift($path);

                    if (!$path) {
                        // inbox/:brand/:date/pageX
                        return $this->_assembleMatch(array(
                            'action' => 'index',
                            'brand'  => $brand,
                            'date'   => $date,
                            'page'   => $page
                        ));
                    }

                    return false;
                }

                return false;
            }

            return false;
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

        $url = array($data['controller']);

        switch ($data['action']) {
            case 'index':
                if (isset($data['brand']) && $data['brand']) {
                    $url[] = $data['brand'];
                }

                if (isset($data['date']) && $data['date']) {
                    $url[] = $data['date'];
                    if (isset($data['page']) && $data['page'] > 1) {
                        $url[] = 'page' . $data['page'];
                    }
                }
                break;
            default:
                throw new Exception("Unexcepected action name {$data['action']}");
        }

        return implode(self::DELIMETER, $url) . self::DELIMETER;
    }
}

<?php

namespace Autowp\Controller\Router\Route;

use Zend_Config;

class Users extends AbstractRoute
{
    protected $_defaults = array(
        'controller' => 'users',
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
        $path = trim($path, self::DELIMETER);
        $path = explode(self::DELIMETER, $path);

        if (!count($path))
            return false;

        if ($path[0] != $this->_defaults['controller']) {
            return false;
        }

        array_shift($path);

        $match = null;

        if (!$path) {
            // users
            return $this->_assembleMatch(array(
                'action' => 'index',
            ));
        }

        switch ($path[0]) {
            case 'rating':
                array_shift($path);

                if (!$path) {
                    // users/rating
                    return $this->_assembleMatch(array(
                        'action' => 'rating',
                    ));
                }

                if ($path[0] == 'pictures') {
                    return $this->_assembleMatch(array(
                        'action' => 'rating',
                        'rating' => 'pictures'
                    ));
                }

                return false;
                break;
        }

        if (preg_match('|^user([0-9]+)$|', $path[0], $match)) {
            $userId = (int)$match[1];
            array_shift($path);

            if (!$path) {
                // users/userX
                return $this->_assembleMatch(array(
                    'action'  => 'user',
                    'user_id' => $userId
                ));
            }

            switch ($path[0]) {
                case 'pictures':
                    //$data['action'] = 'pictures';
                    array_shift($path);

                    if (!$path) {
                        // users/userX/pictures
                        return $this->_assembleMatch(array(
                            'action'  => 'pictures',
                            'user_id' => $userId
                        ));
                    }

                    $data['action'] = 'brandpictures';
                    $brandCatname = array_shift($path);

                    if (!$path) {
                        // users/userX/pictures/:brand
                        return $this->_assembleMatch(array(
                            'action'        => 'brandpictures',
                            'brand_catname' => $brandCatname,
                            'user_id'       => $userId
                        ));
                    }

                    if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                        $page = intval($match[1]);
                        array_shift($path);

                        if (!$path) {
                            // users/userX/pictures/:brand/pageX
                            return $this->_assembleMatch(array(
                                'action'        => 'brandpictures',
                                'brand_catname' => $brandCatname,
                                'user_id'       => $userId,
                                'page'          => $page
                            ));
                        }
                    }

                    return false;
                    break;
            }
        }

        $userIdentity = array_shift($path);

        if (!$path) {
            // users/userX
            return $this->_assembleMatch(array(
                'action'   => 'user',
                'identity' => $userIdentity
            ));
        }

        switch ($path[0]) {
            case 'pictures':
                //$data['action'] = 'pictures';
                array_shift($path);

                if (!$path) {
                    // users/userX/pictures
                    return $this->_assembleMatch(array(
                        'action'   => 'pictures',
                        'identity' => $userIdentity
                    ));
                }

                $data['action'] = 'brandpictures';
                $brandCatname = array_shift($path);

                if (!$path) {
                    // users/userX/pictures/:brand
                    return $this->_assembleMatch(array(
                        'action'        => 'brandpictures',
                        'brand_catname' => $brandCatname,
                        'identity'      => $userIdentity
                    ));
                }

                if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                    $page = intval($match[1]);
                    array_shift($path);

                    if (!$path) {
                        // users/userX/pictures/:brand/pageX
                        return $this->_assembleMatch(array(
                            'action'        => 'brandpictures',
                            'brand_catname' => $brandCatname,
                            'identity'      => $userIdentity,
                            'page'          => $page
                        ));
                    }
                }

                return false;
                break;
        }

        return false;
    }

    public function assemble($data = array(), $reset = false, $encode = false)
    {
        $def = $this->_defaults;
        if (!$reset)
            $def = array_merge($def, $this->_variables);
        $data = array_merge($def, $data);

        if ($encode)
            foreach ($data as &$value)
                if (is_string($value))
                    $value = urlencode($value);

        $url = array($data['controller']);

        switch ($data['action'])
        {
            case 'user':
                if (isset($data['identity'])) {
                    $url[] = $data['identity'];
                } else {
                    $url[] = 'user'.$data['user_id'];
                }
                break;

            case 'pictures':
                if (isset($data['identity'])) {
                    $url[] = $data['identity'];
                } else {
                    $url[] = 'user'.$data['user_id'];
                }
                $url[] = 'pictures';
                break;

            case 'rating':
                $url[] = 'rating';
                if (isset($data['rating'])) {
                    $url[] = $data['rating'];
                }
                break;

            case 'brandpictures':
                if (isset($data['identity'])) {
                    $url[] = $data['identity'];
                } else {
                    $url[] = 'user'.$data['user_id'];
                }
                $url[] = 'pictures';
                $url[] = $data['brand_catname'];
                if (isset($data['page']) && $data['page'] > 1)
                    $url[] = 'page' . $data['page'];
                break;

            case 'index':
            default:
                break;
        }

        return implode(self::DELIMETER, $url);
    }
}

<?php

namespace Autowp\Controller\Router\Route;

use Zend_Config;

class Account extends AbstractRoute
{
    protected $_defaults = array(
        'controller'    => 'account',
        'action'        => 'profile'
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

    public function match($path)
    {
        $data = $this->_defaults;

        $path = trim($path, self::DELIMETER);
        $path = explode(self::DELIMETER, $path);

        foreach ($path as &$node)
            $node = urldecode($node);

        if (!count($path))
            return false;

        if ($path[0] != $this->_defaults['controller'])
            return false;

        array_shift($path);

        if ($path) {
            switch ($path[0]) {
                case 'delete-personal-message':
                case 'send-personal-message':
                case 'contacts':
                case 'email':
                    $data['action'] = $path[0];
                    array_shift($path);
                    break;

                case 'profile':
                    $data['action'] = $path[0];
                    array_shift($path);
                    if ($path) {
                        if ($path[0] == 'form') {
                            $data['form'] = isset($path[1]) ? $path[1] : null;
                            array_shift($path);
                            array_shift($path);
                        }
                    }

                    break;

                case 'not-taken-pictures':
                    $data['action'] = $path[0];
                    array_shift($path);
                    if ($path && preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                        $data['page'] = intval($match[1]);
                        array_shift($path);
                    }
                    break;

                case 'forums':
                    $data['action'] = $path[0];
                    array_shift($path);
                    if ($path)
                    {
                        if ($path[0] == 'unsubscribe')
                        {
                            $data['action'] = 'forums-unsubscribe';
                            array_shift($path);
                            $data['topic_id'] = (int)$path[0];
                        }
                    }
                    break;

                case 'pm':
                    $data['action'] = 'personal-messages-inbox';
                    array_shift($path);

                    if ($path)
                    {
                        if (preg_match('|^user([0-9]+)$|isu', $path[0], $match))
                        {
                            $data['action'] = 'personal-messages-user';
                            $data['user_id'] = (int)$match[1];
                            array_shift($path);
                        }
                        else
                        {
                            switch ($path[0])
                            {
                                case 'sent':
                                    $data['action'] = 'personal-messages-sent';
                                    array_shift($path);
                                    break;

                                case 'system':
                                    $data['action'] = 'personal-messages-system';
                                    array_shift($path);
                                    break;
                            }
                        }
                    }

                    if ($path && preg_match('|^page([0-9]+)$|', $path[0], $match))
                    {
                        $data['page'] = intval($match[1]);
                        array_shift($path);
                    }
                    break;

                case 'emailcheck':
                    $data['action'] = $path[0];
                    array_shift($path);
                    $data['email_check_code'] = array_shift($path);
                    break;

                case 'clear-system-messages':
                    $data['action'] = 'clear-system-messages';
                    break;

                case 'clear-sent-messages':
                    $data['action'] = 'clear-sent-messages';
                    break;

                case 'delete':
                    $data['action'] = 'delete';
                    break;

                case 'accounts':
                    $data['action'] = 'accounts';
                    break;

                case 'access':
                   $data['action'] = 'access';
                   break;

                case 'accounts-cb':
                case 'remove-account':
                case 'specs-conflicts':
                    $data['action'] = $path[0];
                    array_shift($path);
                    while ($path) {
                        $key = array_shift($path);
                        if ($path) {
                            $value = array_shift($path);

                            $data[$key] = $value;
                        }
                    }
                    break;
            }
        }

        $this->_variables = $data;

        return $data;
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
            unset($value);
        }


        $url = array($data['controller']);
        switch ($data['action'])
        {
            case 'profile':
                $url[] = $data['action'];
                if (isset($data['form'])) {
                    $url[] = 'form';
                    $url[] = $data['form'];
                }
                break;
            case 'contacts':
            case 'email':
            case 'forums':
            case 'send-personal-message':
            case 'delete-personal-message':
                $url[] = $data['action'];
                break;

            case 'forums-unsubscribe':
                $url[] = 'forums';
                $url[] = 'unsubscribe';
                $url[] = $data['topic_id'];
                break;

            case 'not-taken-pictures':
                $url[] = $data['action'];
                if (isset($data['page']) && $data['page'] > 1)
                    $url[] = 'page' . $data['page'];
                break;

            case 'personal-messages-inbox':
                $url[] = 'pm';
                if (isset($data['page']) && $data['page'] > 1)
                    $url[] = 'page' . $data['page'];
                break;

            case 'personal-messages-sent':
                $url[] = 'pm';
                $url[] = 'sent';
                if (isset($data['page']) && $data['page'] > 1)
                    $url[] = 'page' . $data['page'];
                break;

            case 'personal-messages-system':
                $url[] = 'pm';
                $url[] = 'system';
                if (isset($data['page']) && $data['page'] > 1)
                    $url[] = 'page' . $data['page'];
                break;

            case 'personal-messages-user':
                $url[] = 'pm';
                $url[] = 'user'.$data['user_id'];
                if (isset($data['page']) && $data['page'] > 1)
                    $url[] = 'page' . $data['page'];
                break;

            case 'clear-system-messages':
                $url[] = 'clear-system-messages';
                break;

            case 'clear-sent-messages':
                $url[] = 'clear-sent-messages';
                break;

            case 'accounts':
            case 'access':
            case 'delete':
                $url[] = $data['action'];
                break;

            case 'remove-account':
            case 'accounts-cb':
            case 'specs-conflicts':
                $url[] = $data['action'];

                $params = $data;
                foreach (array('module', 'controller', 'action') as $key) {
                    unset($params[$key]);
                }

                foreach ($params as $var => $value) {
                    if ($value !== null) {
                        $url[] = $var;
                        $url[] = $value;
                    }
                }
                break;

            default:

                break;
        }

        return implode(self::DELIMETER, $url) . self::DELIMETER;
    }
}

<?php

namespace Autowp\Controller\Router\Route;

use Brands;;

use Zend_Config;

class Articles extends AbstractRoute
{
    protected $_defaults = array(
        'controller' => 'articles',
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

        $match = null;

        if ($path) {
            $brandTable = new Brands();

            $isBrandFolder = (bool)$brandTable->fetchRow(array(
                'folder = ?' => $path[0]
            ));

            if ($isBrandFolder)
            {
                $data['action'] = 'index';
                $data['brand_catname'] = $path[0];
                array_shift($path);

                if ($path && preg_match('|^page([0-9]+)$|', $path[0], $match))
                {
                    $data['page'] = intval($match[1]);
                    array_shift($path);
                }
            }
            else
            {
                if (preg_match('|^page([0-9]+)$|', $path[0], $match))
                {
                    $data['page'] = intval($match[1]);
                    array_shift($path);
                }
                else
                {
                    $data['action'] = 'article';
                    $data['article_catname'] = $path[0];
                    array_shift($path);
                }
            }
        }

        $this->_variables = $data;

        return $data;
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
            case 'article':
                $url[] = $data['article_catname'];
                break;

            /*case 'pictures':
                $url[] = 'user'.$data['user_id'];
                $url[] = 'pictures';
                break;

            case 'brandpictures':
                $url[] = 'user'.$data['user_id'];
                $url[] = 'pictures';
                $url[] = $data['brand_catname'];
                if (isset($data['page']) && $data['page'] > 1)
                    $url[] = 'page' . $data['page'];
                break;*/

            case 'index':
                if (isset($data['brand_catname']))
                    $url[] = $data['brand_catname'];

                if (isset($data['page']) && $data['page'] > 1)
                    $url[] = 'page'.$data['page'];
            default:
                break;
        }

        return implode(self::DELIMETER, $url) . self::DELIMETER;
    }
}

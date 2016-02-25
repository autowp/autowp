<?php

namespace Autowp\Controller\Router\Route;

use Brands;

use Zend_Config;

class Twins extends AbstractRoute
{
    protected $_defaults = array(
        'module'     => 'default',
        'controller' => 'twins',
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
            // twins
            return $this->_assembleMatch(array(
                'action' => 'index'
            ));
        }

        if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
            $page = intval($match[1]);
            array_shift($path);

            if (!$path) {
                // twins/pageX
                return $this->_assembleMatch(array(
                    'action'         => 'index',
                    'page'           => $page
                ));
            }

            return false;
        }

        if (preg_match('|^group([0-9]+)$|', $path[0], $match)) {
            $twinsGroupId = intval($match[1]);
            array_shift($path);

            if (!$path) {
                // twins/groupX
                return $this->_assembleMatch(array(
                    'action'         => 'group',
                    'twins_group_id' => $twinsGroupId
                ));
            }

            switch ($path[0]) {
                case 'specifications':
                    array_shift($path);

                    if (!$path) {
                        // twins/groupX/specifications
                        return $this->_assembleMatch(array(
                            'action'         => 'specifications',
                            'twins_group_id' => $twinsGroupId
                        ));
                    }
                    break;

                case 'pictures':
                    array_shift($path);

                    if (!$path) {
                        // twins/groupX/pictures
                        return $this->_assembleMatch(array(
                            'action'         => 'pictures',
                            'twins_group_id' => $twinsGroupId
                        ));
                    }

                    if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                        $page = intval($match[1]);
                        array_shift($path);

                        if (!$path) {
                            // twins/groupX/pictures/pageX
                            return $this->_assembleMatch(array(
                                'action'         => 'pictures',
                                'twins_group_id' => $twinsGroupId,
                                'page'           => $page
                            ));
                        }

                        return false;
                    }

                    $pictureId = $path[0];
                    array_shift($path);

                    if (!$path) {
                        // twins/groupX/pictures/:picture
                        return $this->_assembleMatch(array(
                            'action'         => 'picture',
                            'twins_group_id' => $twinsGroupId,
                            'picture_id'     => $pictureId
                        ));
                    }

                    if ($path[0] == 'gallery') {
                        array_shift($path);

                        if (!$path) {
                            // twins/groupX/pictures/:picture
                            return $this->_assembleMatch(array(
                                'action'         => 'picture-gallery',
                                'twins_group_id' => $twinsGroupId,
                                'picture_id'     => $pictureId
                            ));
                        }

                        return false;
                    }

                    break;
            }

            return false;
        }

        $brandTable = new Brands();
        if ($row = $brandTable->findRowByCatname($path[0])) {
            $brandCatname = $row->folder;
            array_shift($path);

            if (!$path) {
                // twins/:brand
                return $this->_assembleMatch(array(
                    'action'        => 'brand',
                    'brand_catname' => $brandCatname
                ));
            }

            if (preg_match('|^page([0-9]+)$|', $path[0], $match)) {
                $page = intval($match[1]);
                array_pop($path);

                if (!$path) {
                    // twins/:brand/pageX
                    return $this->_assembleMatch(array(
                        'action'        => 'brand',
                        'brand_catname' => $brandCatname,
                        'page'          => $page
                    ));
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
            case 'brand':
                $url[] = $data['brand_catname'];
                if (isset($data['page']) && $data['page'] > 1) {
                    $url[] = 'page' . $data['page'];
                }
                break;

            case 'group':
                $url[] = 'group' . $data['twins_group_id'];
                break;

            case 'specifications':
                $url[] = 'group' . $data['twins_group_id'];
                $url[] = 'specifications';
                break;

            case 'pictures':
                $url[] = 'group' . $data['twins_group_id'];
                $url[] = 'pictures';
                if (isset($data['page']) && $data['page'] > 1) {
                    $url[] = 'page' . $data['page'];
                }
                break;

            case 'picture':
                $url[] = 'group' . $data['twins_group_id'];
                $url[] = 'pictures';
                $url[] = $data['picture_id'];
                break;

            case 'picture-gallery':
                $url[] = 'group' . $data['twins_group_id'];
                $url[] = 'pictures';
                $url[] = $data['picture_id'];
                $url[] = 'gallery';
                break;

            case 'index':
                if (isset($data['page']) && $data['page'] > 1) {
                    $url[] = 'page' . $data['page'];
                }

            default:
                break;
        }

        return implode(self::DELIMETER, $url) . self::DELIMETER;
    }
}

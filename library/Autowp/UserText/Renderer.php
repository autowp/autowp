<?php

namespace Autowp\UserText;

use Picture;
use Users;

use Zend_View;
use Zend_Config;
use Zend_Controller_Front;
use Zend_Controller_Request_Http;
use Zend_Controller_Router_Rewrite;
use Zend_Uri_Exception;

use Zend\Uri;

use Autowp\UserText\Exception;

class Renderer
{
    /**
     * @var Zend_View
     */
    private $_view;

    /**
     * @var array
     */
    private $_parseUrlHosts = [
        'www.autowp.ru',
        'en.autowp.ru',
        'ru.autowp.ru',
        'autowp.ru',
        'fr.wheelsage.org',
        'en.wheelsage.org',
        'wheelsage.org'
    ];

    /**
     * @param Zend_View $view
     */
    public function __construct(Zend_View $view)
    {
        $this->_view = $view;
    }

    private static function _getRouter()
    {
        static $router = null;
        if (!$router) {
            $options = Zend_Controller_Front::getInstance()->getParam('bootstrap')->getOption('resources');
            if (isset($options['router'])) {
                $router = new Zend_Controller_Router_Rewrite();
                $router->addConfig(new Zend_Config($options['router']['routes']));
            }
        }

        return $router;
    }

    /**
     * @param string $text
     * @return string
     */
    private function preparePlainText($text)
    {
        $out = $this->_view->escape($text);
        $out = str_replace("\r", '', $out);
        $out = str_replace("\n", '<br />', $out);
        return $out;
    }

    /**
     * @param string $text
     * @throws Exception
     * @return string
     */
    public function render($text)
    {
        $out = [];

        $regexp = '@(https?://[[:alnum:]:\.,/?&_=~+%#\'!|\(\)-]{3,})|(www\.[[:alnum:]\.,/?&_=~+%#\'!|\(\)-]{3,})@isu';
        while ($text && preg_match($regexp, $text, $regs)) {
            if ($regs[1]) {
                $umatch = $regs[1];
                $url = $umatch;
            } else {
                $umatch = $regs[2];
                $url = 'http://' . $umatch;
            }

            $linkPos = mb_strpos($text, $umatch);
            $matchLength = mb_strlen($umatch);
            if ($linkPos === false) {
                throw new Exception("Error during parse urls");
            }

            $out[] = $this->preparePlainText(mb_substr($text, 0, $linkPos));

            $out[] = $this->processHref($url);

            $text = mb_substr($text, $linkPos + $matchLength);
        }
        if ($text) {
            $out[] = $this->preparePlainText($text);
        }

        $out = implode($out);

        return $out;
    }

    private function processHref($url)
    {
        try {
            $uri = Uri\UriFactory::factory($url);
        } catch (Uri\Exception\InvalidArgumentException $e) {
            $uri = null;
        }

        $hostAllowed = false;
        if ($uri instanceof Uri\Uri) {
            $hostAllowed = in_array($uri->getHost(), $this->_parseUrlHosts);
        }

        if ($hostAllowed) {

            try {
                $request = new Zend_Controller_Request_Http($url);

                $result = self::_getRouter()->route($request);

                $params = $result->getParams();

                $result = $this->_tryUserLinkParams($params);
                if ($result !== false) {
                    return $result;
                }

                $result = $this->_tryPictureLinkParams($params);
                if ($result !== false) {
                    return $result;
                }

            } catch (Zend_Uri_Exception $e) {

            }

        }

        return '<a href="'.$this->_view->escape($url).'">' . $this->_view->escape($url) . '</a>';
    }

    /**
     * @param array $params
     * @return boolean
     */
    private function _tryUserLinkParams(array $params)
    {
        $map = [
            array(
                'controller' => 'users',
                'action'     => 'user'
            )
        ];

        $userId = null;
        $userIdentity = null;
        foreach ($map as $pattern) {
            $match = true;
            foreach ($pattern as $key => $value) {
                if (!isset($params[$key]) || $params[$key] != $pattern[$key]) {
                    $match = false;
                    break;
                }
            }

            if ($match && isset($params['user_id'])) {
                $userId = $params['user_id'];
                break;
            }

            if ($match && isset($params['identity'])) {
                $userIdentity = $params['identity'];
                break;
            }
        }

        if ($userId) {
            $userTable = new Users();
            $user = $userTable->find($userId)->current();

            if ($user) {
                return $this->_view->user($user)->__toString();
            }
        }

        if ($userIdentity) {
            $userTable = new Users();
            $user = $userTable->fetchRow([
                'identity = ?' => $userIdentity
            ]);

            if ($user) {
                return $this->_view->user($user)->__toString();
            }
        }

        return false;
    }

    /**
     * @param array $params
     * @return boolean
     */
    private function _tryPictureLinkParams(array $params)
    {
        $map = [
            array(
                'controller' => 'picture',
                'action'     => 'index'
            ),
            array(
                'controller' => 'catalogue',
                'action'     => 'brand-car-picture'
            )
        ];

        $pictureId = null;
        foreach ($map as $pattern) {
            $match = true;
            foreach ($pattern as $key => $value) {
                if (!isset($params[$key]) || $params[$key] != $pattern[$key]) {
                    $match = false;
                    break;
                }
            }

            if ($match && isset($params['picture_id'])) {
                $pictureId = $params['picture_id'];
            }
        }

        if ($pictureId) {
            $pictureTable = new Picture();
            $picture = $pictureTable->fetchRow(array(
                'id = ?' => $pictureId,
                'identity IS NULL'
            ));

            if (!$picture) {
                $picture = $pictureTable->fetchRow(array(
                    'identity = ?' => $pictureId
                ));
            }

            if ($picture) {
                return $this->_view->inlinePicture($picture);
            }
        }

        return false;
    }
}
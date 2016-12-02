<?php

namespace Application\View\Helper;

use Zend\Http\Request;
use Zend\Router\Http\TreeRouteStack;
use Zend\Uri;
use Zend\View\Helper\AbstractHtmlElement;

use Autowp\User\Model\DbTable\User as UserTable;

use Application\Model\DbTable\Picture;

use Exception;

class UserText extends AbstractHtmlElement
{
    /**
     * @var array
     */
    private $parseUrlHosts = [
        'www.autowp.ru',
        'en.autowp.ru',
        'ru.autowp.ru',
        'autowp.ru',
        'fr.wheelsage.org',
        'en.wheelsage.org',
        'zh.wheelsage.org',
        'wheelsage.org'
    ];

    /**
     * @var TreeRouteStack
     */
    private $router;

    public function __construct($router)
    {
        $this->router = $router;
    }

    public function __invoke($text)
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

    /**
     * @param string $text
     * @return string
     */
    private function preparePlainText($text)
    {
        $out = $this->view->escapeHtml($text);
        $out = str_replace("\r", '', $out);
        $out = str_replace("\n", '<br />', $out);
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
            $hostAllowed = in_array($uri->getHost(), $this->parseUrlHosts);
        }

        if ($hostAllowed) {
            try {
                $request = new Request();
                $request->setUri($url);

                $match = $this->router->match($request);
                if ($match) {
                    $params = $match->getParams();



                    $result = $this->tryUserLinkParams($params);
                    if ($result !== false) {
                        return $result;
                    }

                    $result = $this->tryPictureLinkParams($params);
                    if ($result !== false) {
                        return $result;
                    }
                }
            } catch (Zend_Uri_Exception $e) {
            }
        }

        return '<a href="'.$this->view->escapeHtmlAttr($url).'">' . $this->view->escapeHtml($url) . '</a>';
    }

    /**
     * @param array $params
     * @return boolean
     */
    private function tryUserLinkParams(array $params)
    {
        $map = [
            [
                'controller' => \Application\Controller\UsersController::class,
                'action'     => 'user'
            ]
        ];

        $userId = null;
        $userIdentity = null;
        foreach ($map as $pattern) {
            $match = true;
            foreach ($pattern as $key => $value) {
                if (! isset($params[$key]) || $params[$key] != $pattern[$key]) {
                    $match = false;
                    break;
                }
            }

            if ($match && isset($params['user_id'])) {
                $userId = $params['user_id'];
                break;
            }
        }

        if (preg_match('|user([0-9]+)|isu', $userId, $matches)) {
            $userId = $matches[1];
        } else {
            $userIdentity = $userId;
            $userId = null;
        }

        if ($userId) {
            $userTable = new UserTable();
            $user = $userTable->find($userId)->current();

            if ($user) {
                return $this->view->user($user)->__toString();
            }
        }

        if ($userIdentity) {
            $userTable = new UserTable();
            $user = $userTable->fetchRow([
                'identity = ?' => $userIdentity
            ]);

            if ($user) {
                return $this->view->user($user)->__toString();
            }
        }

        return false;
    }

    /**
     * @param array $params
     * @return boolean
     */
    private function tryPictureLinkParams(array $params)
    {
        $map = [
            [
                'controller' => \Application\Controller\PictureController::class,
                'action'     => 'index'
            ],
            [
                'controller' => \Application\Controller\CatalogueController::class,
                'action'     => 'brand-item-picture'
            ]
        ];

        $pictureId = null;
        foreach ($map as $pattern) {
            $match = true;
            foreach ($pattern as $key => $value) {
                if (! isset($params[$key]) || $params[$key] != $pattern[$key]) {
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
            $picture = $pictureTable->fetchRow([
                'id = ?' => $pictureId,
                'identity IS NULL'
            ]);

            if (! $picture) {
                $picture = $pictureTable->fetchRow([
                    'identity = ?' => $pictureId
                ]);
            }

            if ($picture) {
                return $this->view->inlinePicture($picture);
            }
        }

        return false;
    }
}

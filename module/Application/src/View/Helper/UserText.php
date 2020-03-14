<?php

namespace Application\View\Helper;

use Application\Model\Picture;
use Autowp\User\Model\User as UserModel;
use Exception;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\Uri;
use Laminas\View\Helper\AbstractHtmlElement;

use function implode;
use function in_array;
use function mb_strlen;
use function mb_strpos;
use function mb_substr;
use function preg_match;
use function str_replace;
use function strlen;

class UserText extends AbstractHtmlElement
{
    private array $parseUrlHosts = [
        'www.autowp.ru',
        'en.autowp.ru',
        'ru.autowp.ru',
        'autowp.ru',
        'fr.wheelsage.org',
        'en.wheelsage.org',
        'zh.wheelsage.org',
        'be.wheelsage.org',
        'br.wheelsage.org',
        'uk.wheelsage.org',
        'wheelsage.org',
    ];

    private TreeRouteStack $router;

    private Picture $picture;

    private UserModel $userModel;

    public function __construct(TreeRouteStack $router, Picture $picture, UserModel $userModel)
    {
        $this->router    = $router;
        $this->picture   = $picture;
        $this->userModel = $userModel;
    }

    /**
     * @throws Exception
     */
    public function __invoke(string $text): string
    {
        $out = [];

        $regexp = '@(https?://[[:alnum:]:\.,/?&_=~+%#\'!|\(\)-]{3,})|(www\.[[:alnum:]\.,/?&_=~+%#\'!|\(\)-]{3,})@isu';
        while ($text && preg_match($regexp, $text, $regs)) {
            if ($regs[1]) {
                $umatch = $regs[1];
                $url    = $umatch;
            } else {
                $umatch = $regs[2];
                $url    = 'http://' . $umatch;
            }

            $linkPos     = mb_strpos($text, $umatch);
            $matchLength = mb_strlen($umatch);
            if ($linkPos === false) {
                throw new Exception("Error during parse urls");
            }

            $out[] = $this->preparePlainText(mb_substr($text, 0, $linkPos));

            $out[] = $this->processHref($url);

            $text = mb_substr($text, $linkPos + $matchLength);
        }

        if (strlen($text) > 0) {
            $out[] = $this->preparePlainText($text);
        }

        $out = implode($out);

        return $out;
    }

    private function preparePlainText(string $text): string
    {
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $out = $this->view->escapeHtml($text);
        $out = str_replace("\r", '', $out);
        $out = str_replace("\n", '<br />', $out);
        return $out;
    }

    /**
     * @throws Exception
     * @SuppressWarnings(PHPMD.EmptyCatchBlock)
     */
    private function processHref(string $url): string
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
            $result = $this->tryUserLink($uri);
            if ($result !== null) {
                return $result;
            }

            /*try {
                $request = new Request();
                $request->setUri($uri);

                $match = $this->router->match($request);
                if ($match) {
                    $params = $match->getParams();

                    $result = $this->tryPictureLinkParams($params);
                    if ($result !== false) {
                        return $result;
                    }
                }
            } catch (InvalidArgumentException $e) {
            }*/
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return '<a href="' . $this->view->escapeHtmlAttr($url) . '">' . $this->view->escapeHtml($url) . '</a>';
    }

    private function tryUserLink(Uri\Uri $uri): ?string
    {
        $match = preg_match('|^/users/([^/]+)$|isu', $uri->getPath(), $matches);

        if (! $match) {
            return null;
        }

        $userId       = null;
        $userIdentity = $matches[1];

        $match = preg_match('|^user([0-9]+)$|isu', $userIdentity, $matches);
        if ($match) {
            $userIdentity = null;
            $userId       = (int) $matches[1];
        }

        if ($userId) {
            $user = $this->userModel->getRow(['id' => (int) $userId]);

            if ($user) {
                /* @phan-suppress-next-line PhanUndeclaredMethod */
                return $this->view->user($user)->__toString();
            }
        }

        if ($userIdentity) {
            $user = $this->userModel->getRow([
                'identity' => (string) $userIdentity,
            ]);

            if ($user) {
                /* @phan-suppress-next-line PhanUndeclaredMethod */
                return $this->view->user($user)->__toString();
            }
        }

        return null;
    }

    /**
     * @throws Exception

    private function tryPictureLinkParams(array $params): bool
    {
        $map = [

        ];

        $pictureId = null;
        foreach ($map as $pattern) {
            $match = true;
            foreach ($pattern as $key => $value) {
                if (! isset($params[$key]) || $params[$key] != $value) {
                    $match = false;
                    break;
                }
            }

            if ($match && isset($params['picture_id'])) {
                $pictureId = $params['picture_id'];
            }
        }

        if ($pictureId) {
            $picture = $this->picture->getRow([
                'identity' => $pictureId
            ]);

            if ($picture) {
                * @phan-suppress-next-line PhanUndeclaredMethod *
                return $this->view->inlinePicture($picture);
            }
        }

        return false;
    }*/
}

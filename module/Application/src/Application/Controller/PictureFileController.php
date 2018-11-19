<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Referer;

class PictureFileController extends AbstractActionController
{
    /**
     * @var string
     */
    private $picturesHostname = null;

    /**
     * @var Referer
     */
    private $referer;

    public function __construct($picturesHostname, Referer $referer)
    {
        $this->picturesHostname = $picturesHostname;
        $this->referer = $referer;
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
    public function indexAction()
    {
        $request = $this->getRequest();

        $file = $this->params('file');

        if ($this->picturesHostname) {
            $hostname = $this->params('hostname');
            if ($hostname != $this->picturesHostname) {
                $sourceUrl = $this->url()->fromRoute('picture-file', [
                    'hostname' => $this->picturesHostname,
                    'file'     => $file
                ], [
                    'force_canonical' => true
                ]);

                return $this->redirect()->toUrl($sourceUrl);
            }
        }

        $file = str_replace('/../', '/', $file);
        $file = str_replace('~', '', $file);
        $file = str_replace('/./', '', $file);

        $filepath = realpath(implode(DIRECTORY_SEPARATOR, [PUBLIC_DIR, $file]));

        if (! file_exists($filepath)) {
            return $this->notFoundAction();
        }

        if (! is_file($filepath)) {
            return $this->notFoundAction();
        }

        $imageType = exif_imagetype($filepath);
        $contentType = null;
        if ($imageType !== false) {
            $contentType = image_type_to_mime_type($imageType);
        }

        // referer
        $referer = (string)$request->getServer('HTTP_REFERER');

        if ($referer) {
            $blacklisted = $this->referer->isUrlBlacklisted($referer);

            /*if ($blacklistRow && $blacklistRow['hard']) {
                return $this->getResponse()
                    ->setStatusCode(429)
                    ->getHeaders()
                    ->addHeaders([
                        'Content-Type'     => 'image/gif',
                        'X-Accel-Redirect' => '/hotlinking.gif'
                    ]);
            }*/

            $accept = (string)$request->getServer('HTTP_ACCEPT');

            if ($accept && $blacklisted && $this->referer->isImageRequest($accept)) {
                return $this->getResponse()->setStatusCode(429);
            }

            $this->referer->addUrl($referer, $accept);
        }

        $expiresTime = 86400 * 60;

        if ($contentType) {
            $this->getResponse()->getHeaders()
                ->addHeaderLine('Content-Type', $contentType);
        }

        $this->getResponse()
            ->getHeaders()
            ->addHeaders([
                'Expires'          => gmdate("D, d M Y H:i:s", time() + $expiresTime)." GMT",
                'Pragma'           => 'public',
                'Content-Length'   => filesize($filepath),
                'Cache-Control'    => "max-age=".$expiresTime.", public, must-revalidate",
                'X-Accel-Redirect' => '/pic-accel/' . $file
            ]);

        return $this->getResponse();
    }
}

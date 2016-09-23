<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

use Referer;
use Referer_Blacklist;

class PictureFileController extends AbstractActionController
{
    public function indexAction()
    {
        $request = $this->getRequest();

        $hostname = $this->params('hostname');
        $file = $this->params('file');

        if ($hostname != 'i.wheelsage.org') {
            $sourceUrl = $this->url()->fromRoute('picture-file', [
                'hostname' => 'i.wheelsage.org',
                'file'     => $file
            ]);

            return $this->redirect()->toUrl($sourceUrl);
        }

        $file = str_replace('/../', '/', $file);
        $file = str_replace('~', '', $file);
        $file = str_replace('/./', '', $file);

        $filepath = realpath(implode(DIRECTORY_SEPARATOR, [PUBLIC_DIR, $file]));

        if (!file_exists($filepath)) {
            return $this->notFoundAction();
        }

        if (!is_file($filepath)) {
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
            $blacklist = new Referer_Blacklist();
            $blacklistRow = $blacklist->fetchRowByUrl($referer);
            if ($blacklistRow && $blacklistRow->hard) {
                return $this->getResponse()
                    ->setHttpResponseCode(509)
                    ->getHeaders()
                    ->addHeaders([
                        'Content-Type' => 'image/gif',
                        'X-Accel-Redirect', '/img/hotlinking.gif'
                    ]);
            }

            $accept = (string)$request->getServer('HTTP_ACCEPT');

            $refererTable = new Referer();
            if ($accept && $refererTable->isImageRequest($accept) && $blacklistRow) {
                return $this->getResponse()->setHttpResponseCode(509);
            }

            $refererTable->addUrl($referer, $accept);
        }

        $expiresTime = 86400*60;

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

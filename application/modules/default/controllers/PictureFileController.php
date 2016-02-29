<?php

class PictureFileController extends Zend_Controller_Action
{
    public function indexAction()
    {
        /*if (!$this->_helper->user()->inheritsRole('moder')) {
            $this->_helper->redirector->setCode(307);
            return $this->_redirect('/maintenance');
        }*/

        $request = $this->getRequest();

        $hostname = $request->getHttpHost();
        $file = $this->_getParam('file');

        if ($hostname != 'i.wheelsage.org') {
            $sourceUrl = $this->_helper->url->url(array(
                'file' => 'pictures/' . $file
            ), 'picture-source', true);

            return $this->_redirect($sourceUrl);
        }

        $file = str_replace('/../', '/', $file);
        $file = str_replace('~', '', $file);
        $file = str_replace('/./', '', $file);

        $filepath = realpath(implode(DIRECTORY_SEPARATOR, array(PUBLIC_DIR, $file)));

        if (!file_exists($filepath)) {
            return $this->_forward('notfound', 'error');
        }

        if (!is_file($filepath)) {
            return $this->_forward('notfound', 'error');
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
                   $this->getResponse()
                       ->setHttpResponseCode(509)
                       ->setHeader('Content-Type', 'image/gif', true)
                       ->setHeader('X-Accel-Redirect', '/img/hotlinking.gif', true);

                   $this->_helper->viewRenderer->setNoRender(true);
                   $this->_helper->layout->disableLayout();
                   return;
            }

            $accept = (string)$request->getServer('HTTP_ACCEPT');

            $refererTable = new Referer();
            if ($accept && $refererTable->isImageRequest($accept) && $blacklistRow) {
                $this->getResponse()->setHttpResponseCode(509);

                $this->_helper->viewRenderer->setNoRender(true);
                $this->_helper->layout->disableLayout();
                return;
            }

            $refererTable->addUrl($referer, $accept);
        }

        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout->disableLayout();

        $expiresTime = 86400*60;

        if ($contentType) {
            $this->getResponse()
                ->setHeader('Content-Type', $contentType, true);
        }

        $this->getResponse()
            ->setHeader('Expires', gmdate("D, d M Y H:i:s", time() + $expiresTime)." GMT", true)
            ->setHeader('Pragma', 'public', true)
            ->setHeader('Content-Length', filesize($filepath), true)
            ->setHeader('Cache-Control', "max-age=".$expiresTime.", public, must-revalidate", true)
            ->setHeader('X-Accel-Redirect', '/pic-accel/' . $file, true);
    }

}
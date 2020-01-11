<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;

/**
 * Class IndexController
 * @package Application\Controller
 *
 */
class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        $path = $this->params('path');

        if ($path) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $uri = $this->getRequest()->getUri();

            $query = $uri->getQuery();

            $url = '/#!/' . $path . ($query ? '?' . $query : '');
            return $this->redirect()->toUrl($url);
        }
    }
}

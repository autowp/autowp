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
    /**
     * @suppress PhanDeprecatedFunction
     */
    public function indexAction()
    {
        return $this->redirect()->toUrl('/ng/');
    }

    public function ngAction()
    {
        $path = $this->params('path');

        if ($path) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $uri = $this->getRequest()->getUri();

            $query = $uri->getQuery();

            return $this->redirect()->toRoute('ng', [
                'path' => ''
            ], [
                'fragment' => '!/' . $path . ($query ? '?' . $query : '')
            ], false);
        }
    }
}

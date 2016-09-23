<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;

use Application\Model\Message;

class Sidebar extends AbstractHtmlElement
{
    public function __invoke()
    {
        $newPersonalMessages = null;
        if ($this->view->user()->logedIn()) {
            $mModel = new Message();
            $count = $mModel->getNewCount($this->view->user()->get()->id);

            $newPersonalMessages = $count;
        }
        return $this->view->partial('application/sidebar-right', [
            'newPersonalMessages' => $newPersonalMessages
        ]);
    }
}
<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;

use Application\Model\Message;

class Sidebar extends AbstractHtmlElement
{
    /**
     * @var Message
     */
    private $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function __invoke()
    {
        $newPersonalMessages = null;
        if ($this->view->user()->logedIn()) {
            $count = $this->message->getNewCount($this->view->user()->get()->id);

            $newPersonalMessages = $count;
        }
        return $this->view->partial('application/sidebar-right', [
            'newPersonalMessages' => $newPersonalMessages
        ]);
    }
}
<?php

namespace Application\View\Helper;

use Autowp\Message\MessageService;
use Laminas\View\Helper\AbstractHtmlElement;

class Sidebar extends AbstractHtmlElement
{
    /** @var MessageService */
    private $message;

    public function __construct(MessageService $message)
    {
        $this->message = $message;
    }

    public function __invoke($data = false)
    {
        $newPersonalMessages = null;
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        if ($this->view->user()->logedIn()) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $count = $this->message->getNewCount($this->view->user()->get()['id']);

            $newPersonalMessages = (int) $count;
        }

        if ($data) {
            return [
                'newPersonalMessages' => $newPersonalMessages,
            ];
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        return $this->view->partial('application/sidebar-right', [
            'newPersonalMessages' => $newPersonalMessages,
        ]);
    }
}

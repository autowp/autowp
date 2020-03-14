<?php

namespace Application\View\Helper;

use Application\Model\Picture;
use Autowp\Comments;
use Laminas\View\Helper\AbstractHtmlElement;

class ModerMenu extends AbstractHtmlElement
{
    private Comments\CommentsService $comments;

    private Picture $picture;

    public function __construct(Comments\CommentsService $comments, Picture $picture)
    {
        $this->comments = $comments;
        $this->picture  = $picture;
    }

    public function __invoke(): array
    {
        $items = [];

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        if ($this->view->user()->inheritsRole('moder')) {
            $inboxCount = $this->picture->getCount([
                'status' => Picture::STATUS_INBOX,
            ]);

            $items[] = [
                'href'  => '/moder/pictures?order=1&status=inbox',
                'label' => $this->view->translate('moder-menu/inbox'),
                'count' => $inboxCount,
                'icon'  => 'fa fa-th',
            ];

            $attentionCount = $this->comments->getTotalMessagesCount([
                'attention' => Comments\Attention::REQUIRED,
            ]);

            $items[] = [
                'href'  => '/moder/comments?moderator_attention=1',
                'label' => $this->view->page(110)->name,
                'count' => $attentionCount,
                'icon'  => 'fa fa-comment',
            ];

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            if ($this->view->user()->inheritsRole('pages-moder')) {
                $items[] = [
                    'href'  => '/moder/pages',
                    'label' => $this->view->page(68)->name,
                    'icon'  => 'fa fa-book',
                ];
            }

            $items[] = [
                'href'  => '/moder/items',
                'label' => $this->view->page(131)->name,
                'icon'  => 'fa fa-car',
            ];
        }

        return $items;
    }
}

<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;

use Autowp\Comments;

use Application\Model\DbTable\Picture;

class ModerMenu extends AbstractHtmlElement
{
    /**
     * @var Comments\CommentsService
     */
    private $comments;

    public function __construct(Comments\CommentsService $comments)
    {
        $this->comments = $comments;
    }

    public function __invoke($data = false)
    {
        $items = [];

        if ($this->view->user()->inheritsRole('moder')) {
            $pTable = new Picture();
            $inboxCount = $pTable->getAdapter()->fetchOne(
                $pTable->getAdapter()->select()
                    ->from($pTable->info('name'), 'count(1)')
                    ->where('status = ?', Picture::STATUS_INBOX)
            );

            $items[] = [
                'href'  => '/moder/pictures/index/order/1/status/inbox',
                'label' => $this->view->translate('moder-menu/inbox'),
                'count' => $inboxCount,
                'icon'  => 'fa fa-th'
            ];

            $attentionCount = $this->comments->getTotalMessagesCount([
                'attention' => Comments\Attention::REQUIRED
            ]);

            $items[] = [
                'href'  => $this->view->url('ng', ['path' => 'moder/comments'], [
                    'query' => [
                        'moderator_attention' => Comments\Attention::REQUIRED
                    ]
                ]),
                'label' => $this->view->page(110)->name,
                'count' => $attentionCount,
                'icon'  => 'fa fa-comment'
            ];

            if ($this->view->user()->inheritsRole('pages-moder')) {
                $items[] = [
                    'href'  => $this->view->url('moder/pages'),
                    'label' => $this->view->page(68)->name,
                    'icon'  => 'fa fa-book'
                ];
            }

            $items[] = [
                'href'  => $this->view->url('moder/cars'),
                'label' => $this->view->page(131)->name,
                'icon'  => 'fa fa-car'
            ];
        }
        
        if ($data) {
            return $items;
        }

        return $this->view->partial('application/moder-menu', [
            'items' => $items
        ]);
    }
}

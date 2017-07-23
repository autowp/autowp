<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;

use Autowp\Comments;

use Application\Model\DbTable;

class ModerMenu extends AbstractHtmlElement
{
    /**
     * @var Comments\CommentsService
     */
    private $comments;

    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    public function __construct(Comments\CommentsService $comments, DbTable\Picture $pictureTable)
    {
        $this->comments = $comments;
        $this->pictureTable = $pictureTable;
    }

    public function __invoke($data = false)
    {
        $items = [];

        if ($this->view->user()->inheritsRole('moder')) {
            $inboxCount = $this->pictureTable->getAdapter()->fetchOne(
                $this->pictureTable->getAdapter()->select()
                    ->from($this->pictureTable->info('name'), 'count(1)')
                    ->where('status = ?', DbTable\Picture::STATUS_INBOX)
            );

            $items[] = [
                'href'  => '/ng/moder/pictures?order=1&status=inbox',
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
                    'href'  => '/ng/moder/pages',
                    'label' => $this->view->page(68)->name,
                    'icon'  => 'fa fa-book'
                ];
            }

            $items[] = [
                'href'  => '/ng/moder/items',
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

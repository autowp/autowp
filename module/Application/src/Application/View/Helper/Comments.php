<?php

namespace Application\View\Helper;

use Application\Model\Comments as CommentsModel;

use Zend\View\Helper\AbstractHelper;

class Comments extends AbstractHelper
{
    public function __construct($form)
    {
        $this->form = $form;
    }

    public function __invoke(array $options)
    {
        $defaults = [
            'type'    => null,
            'item_id' => null
        ];
        $options = array_replace($defaults, $options);

        $type = (int)$options['type'];
        $item = (int)$options['item_id'];

        $user = $this->view->user()->get();

        $model = new CommentsModel();

        $comments = $model->get($type, $item, $user);

        if ($user) {
            $model->updateTopicView($type, $item, $user->id);
        }

        $canAddComments = (bool)$user;
        $canRemoveComments = $this->view->user()->isAllowed('comment', 'remove');

        $form = null;
        if ($canAddComments) {
            $form = $this->form;

            $form->setAttribute('action', $this->view->url('comments/add', [
                'type_id'    => $type,
                'item_id'    => $item
            ]));
            // TODO: 'canModeratorAttention' => $this->view->user()->isAllowed('comment', 'moderator-attention'),
        }

        return $this->view->partial('application/comments/comments', [
            'comments'          => $comments,
            'itemId'            => $item,
            'type'              => $type,
            'canAddComments'    => $canAddComments,
            'canRemoveComments' => $canRemoveComments,
            'form'              => $form
        ]);
    }
}

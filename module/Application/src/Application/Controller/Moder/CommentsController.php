<?php

namespace Application\Controller\Moder;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

use Autowp\Comments;
use Autowp\Commons\Paginator\Adapter\Zend1DbTableSelect;
use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable;
use Application\StringUtils;
use Autowp\Comments\CommentsService;

class CommentsController extends AbstractActionController
{
    /**
     * @var Form
     */
    private $form;

    /**
     * @var Comments\CommentsService
     */
    private $comments;

    public function __construct(Form $form, Comments\CommentsService $comments)
    {
        $this->form = $form;
        $this->comments = $comments;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = new DbTable\Item();

        if ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
            unset($params['submit']);
            foreach ($params as $key => $value) {
                if (strlen($value) <= 0) {
                    $params[$key] = null;
                }
            }
            return $this->redirect()->toUrl($this->url()->fromRoute('moder/comments/params', $params));
        }

        $commentTable = new Comments\Model\DbTable\Message();

        $select = $commentTable->select(true)
            ->order(['comments_messages.datetime DESC']);

        $this->form->setData($this->params()->fromRoute());

        if ($this->form->isValid()) {
            $values = $this->form->getData();

            if ($values['user']) {
                if (! is_numeric($values['user'])) {
                    $userTable = new User();
                    $userRow = $userTable->fetchRow([
                        'identity = ?' => $values['user']
                    ]);
                    if ($userRow) {
                        $values['user'] = $userRow->id;
                    }
                }

                $select->where('comments_messages.author_id = ?', $values['user']);
            }

            if (strlen($values['moderator_attention'])) {
                switch ($values['moderator_attention']) {
                    case Comments\Model\DbTable\Message::MODERATOR_ATTENTION_NONE:
                    case Comments\Model\DbTable\Message::MODERATOR_ATTENTION_REQUIRED:
                    case Comments\Model\DbTable\Message::MODERATOR_ATTENTION_COMPLETED:
                        $select->where('comments_messages.moderator_attention = ?', $values['moderator_attention']);
                        break;
                }
            }

            if ($values['item_id']) {
                $select
                    ->where('comments_messages.type_id = ?', Comments\Model\DbTable\Message::PICTURES_TYPE_ID)
                    ->join('pictures', 'comments_messages.item_id = pictures.id', null)
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $values['item_id']);
            }
        }

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage(50)
            ->setCurrentPageNumber($this->params('page'));

        $comments = [];
        foreach ($paginator->getCurrentItems() as $commentRow) {
            $status = '';
            if ($commentRow->type_id == Comments\Model\DbTable\Message::PICTURES_TYPE_ID) {
                $pictures = $this->catalogue()->getPictureTable();
                $picture = $pictures->find($commentRow->item_id)->current();
                if ($picture) {
                    switch ($picture->status) {
                        case DbTable\Picture::STATUS_ACCEPTED:
                            $status = '<span class="label label-success">' .
                                    $this->translate('moder/picture/acceptance/accepted') .
                                '</span>';
                            break;
                        case DbTable\Picture::STATUS_NEW:
                            $status = '<span class="label label-warning">' .
                                    $this->translate('moder/picture/acceptance/new') .
                                '</span>';
                            break;
                        case DbTable\Picture::STATUS_INBOX:
                            $status = '<span class="label label-warning">' .
                                    $this->translate('moder/picture/acceptance/inbox') .
                                '</span>';
                            break;
                        case DbTable\Picture::STATUS_REMOVED:
                            $status = '<span class="label label-danger">' .
                                    $this->translate('moder/picture/acceptance/removed') .
                                '</span>';
                            break;
                        case DbTable\Picture::STATUS_REMOVING:
                            $status = '<span class="label label-danger">' .
                                    $this->translate('moder/picture/acceptance/removing') .
                                '</span>';
                            break;
                    }
                }
            }

            $comments[] = [
                'url'     => $this->comments->getUrl($commentRow),
                'message' => StringUtils::getTextPreview($commentRow->message, [
                    'maxlines'  => 1,
                    'maxlength' => CommentsService::PREVIEW_LENGTH
                ]),
                'user'    => $commentRow->findParentRow(User::class),
                'status'  => $status,
                'new'     => $this->comments->isNewMessage($commentRow, $this->user()->get()->id)
            ];
        }

        return [
            'form'      => $this->form,
            'paginator' => $paginator,
            'comments'  => $comments,
            'urlParams' => $this->params()->fromRoute()
        ];
    }
}

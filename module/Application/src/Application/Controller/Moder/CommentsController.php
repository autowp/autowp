<?php

namespace Application\Controller\Moder;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

use Autowp\Commons\Paginator\Adapter\Zend1DbSelect;
use Autowp\User\Model\DbTable\User;

use Application\Comments;
use Application\Model\DbTable;

class CommentsController extends AbstractActionController
{
    /**
     * @var Form
     */
    private $form;

    /**
     * @var Comments
     */
    private $comments;

    public function __construct(Form $form, Comments $comments)
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
        $userTable = new \Autowp\User\Model\DbTable\User();

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

        $options = [
            'order' => 'comments_messages.datetime DESC'
        ];

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

                $options['user'] = $values['user'];
            }

            if (strlen($values['moderator_attention'])) {
                $options['attention'] = $values['moderator_attention'];
            }

            if ($values['item_id']) {
                $options['type'] = \Application\Comments::PICTURES_TYPE_ID;
                $options['callback'] = function(\Zend_Db_Select $select) use ($values) {
                    $select
                        ->join('pictures', 'comments_messages.item_id = pictures.id', null)
                        ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                        ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                        ->where('item_parent_cache.parent_id = ?', $values['item_id']);
                };
            }
        }

        $paginator = $this->comments->service()->getMessagesPaginator($options);

        $paginator
            ->setItemCountPerPage(50)
            ->setCurrentPageNumber($this->params('page'));

        $comments = [];
        foreach ($paginator->getCurrentItems() as $commentRow) {
            $status = '';
            if ($commentRow['type_id'] == \Application\Comments::PICTURES_TYPE_ID) {
                $pictures = $this->catalogue()->getPictureTable();
                $picture = $pictures->find($commentRow['item_id'])->current();
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
                'url'     => $this->comments->getMessageRowUrl($commentRow),
                'message' => $this->comments->getMessagePreview($commentRow['message']),
                'user'    => $userTable->find($commentRow['author_id'])->current(),
                'status'  => $status,
                'new'     => $this->comments->service()->isNewMessage($commentRow, $this->user()->get()->id)
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

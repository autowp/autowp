<?php

namespace Application\Controller\Moder;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;

use Autowp\User\Model\DbTable\User;

use Application\Model\DbTable;
use Application\Paginator\Adapter\Zend1DbTableSelect;

class CommentsController extends AbstractActionController
{
    /**
     * @var Form
     */
    private $form;

    public function __construct(Form $form)
    {
        $this->form = $form;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = new DbTable\Item();

        $brandRows = $itemTable->fetchAll(
            $itemTable->select(true)
                ->where('item_type_id = ?', DbTable\Item\Type::BRAND)
                /*->join('brand_item', 'brands.id = brand_item.brand_id', null)
                ->join('item_parent_cache', 'brand_item.item_id = item_parent_cache.parent_id', null)
                ->join('pictures', 'pictures.item_id = item_parent_cache.item_id', null)
                ->join('comments_messages', 'comments_messages.item_id = pictures.id', null)
                ->where('comments_messages.type_id = ?', DbTable\Comment\Message::PICTURES_TYPE_ID)
                ->group('brands.id')*/
                ->order(['item.position', 'item.name'])
        );
        $brandOptions = [
            '' => '--'
        ];
        foreach ($brandRows as $brandRow) {
            $brandOptions[$brandRow->id] = $brandRow->name;
        }

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

        $commentTable = new DbTable\Comment\Message();

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
                    case DbTable\Comment\Message::MODERATOR_ATTENTION_NONE:
                    case DbTable\Comment\Message::MODERATOR_ATTENTION_REQUIRED:
                    case DbTable\Comment\Message::MODERATOR_ATTENTION_COMPLETED:
                        $select->where('comments_messages.moderator_attention = ?', $values['moderator_attention']);
                        break;
                }
            }

            if ($values['item_id']) {
                $select
                    ->where('comments_messages.type_id = ?', DbTable\Comment\Message::PICTURES_TYPE_ID)
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
            if ($commentRow->type_id == DbTable\Comment\Message::PICTURES_TYPE_ID) {
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
                'url'     => $commentRow->getUrl(),
                'message' => $commentRow->getMessagePreview(),
                'user'    => $commentRow->findParentRow(User::class),
                'status'  => $status,
                'new'     => $commentRow->isNew($this->user()->get()->id)
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

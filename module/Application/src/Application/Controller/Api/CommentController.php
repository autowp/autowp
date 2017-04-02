<?php

namespace Application\Controller\Api;

use Zend\Db\Sql;
use Zend\Form\Form;
use Zend\Http\Request;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\User\Model\DbTable\User;

use Application\Comments;
use Application\Hydrator\Api\RestHydrator;
use Application\Model\DbTable;

class CommentController extends AbstractRestfulController
{
    /**
     * @var Form
     */
    private $form;

    /**
     * @var Comments
     */
    private $comments;
    
    /**
     * @var RestHydrator
     */
    private $hydrator;

    public function __construct(Comments $comments, Form $form, RestHydrator $hydrator)
    {
        $this->comments = $comments;
        $this->form = $form;
        $this->hydrator = $hydrator;
    }

    public function subscribeAction()
    {
        $user = $this->user()->get();
        if (! $user) {
            return $this->forbiddenAction();
        }

        $itemId = (int)$this->params('item_id');
        $typeId = (int)$this->params('type_id');

        switch ($this->getRequest()->getMethod()) {
            case Request::METHOD_POST:
            case Request::METHOD_PUT:
                $this->comments->service()->subscribe($typeId, $itemId, $user['id']);

                return new JsonModel([
                    'status' => true
                ]);
                break;

            case Request::METHOD_DELETE:
                $this->comments->service()->unSubscribe($typeId, $itemId, $user['id']);

                return new JsonModel([
                    'status' => true
                ]);
                break;
        }

        return $this->notFoundAction();
    }
    
    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }
        
        $user = $this->user()->get();
        
        $itemTable = new DbTable\Item();
        $userTable = new User();
        
        $options = [
            'order' => 'comment_message.datetime DESC'
        ];
        
        $this->form->setData($this->params()->fromQuery());
        
        if (! $this->form->isValid()) {
            $this->getResponse()->setStatusCode(400);
            return new JsonModel($this->form->getMessages());
        }
        
        $values = $this->form->getData();
    
        if ($values['user']) {
            if (! is_numeric($values['user'])) {
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
            $options['callback'] = function (Sql\Select $select) use ($values) {
                $select
                    ->join('pictures', 'comment_message.item_id = pictures.id', [])
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', [])
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', [])
                    ->where(['item_parent_cache.parent_id = ?' => $values['item_id']]);
            };
        }

        $paginator = $this->comments->service()->getMessagesPaginator($options);
        
        $paginator
            ->setItemCountPerPage(50)
            ->setCurrentPageNumber($this->params()->fromQuery('page'));
        
        $this->hydrator->setOptions([
            //'fields'   => $data['fields'],
            'user_id' => $user ? $user['id'] : null
        ]);
            
        $comments = [];
        foreach ($paginator->getCurrentItems() as $commentRow) {
            $comments[] = $this->hydrator->extract($commentRow);
        }
        
        return new JsonModel([
            'paginator' => get_object_vars($paginator->getPages()),
            'comments'  => $comments
        ]);
    }
}

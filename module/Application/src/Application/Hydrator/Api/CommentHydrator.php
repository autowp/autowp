<?php

namespace Application\Hydrator\Api;

use DateTime;
use DateInterval;

use Zend\Hydrator\AbstractHydrator;

use Autowp\User\Model\DbTable\User;

use Application\Comments;
use Application\Model\DbTable;

class CommentHydrator extends AbstractHydrator
{
    /**
     * @var Comments
     */
    private $comments;
    
    /**
     * @var DbTable\Picture
     */
    private $pictureTable;
    
    /**
     * @var User
     */
    private $userTable;
    
    private $hydratorManager;
    
    public function __construct($hydratorManager, Comments $comments, $router)
    {
        $this->hydratorManager = $hydratorManager;
        $this->comments = $comments;
        $this->router = $router;
        
        $this->pictureTable = new DbTable\Picture();
        $this->userTable = new User();
        
        $this->currentUserId = null;
    }
    
    public function extract($object)
    {
        $status = null;
        if ($object['type_id'] == \Application\Comments::PICTURES_TYPE_ID) {
            $picture = $this->pictureTable->find($object['item_id'])->current();
            if ($picture) {
                switch ($picture->status) {
                    case DbTable\Picture::STATUS_ACCEPTED:
                        $status = [
                            'class' => 'success',
                            'name'  => 'moder/picture/acceptance/accepted'
                        ];
                        break;
                    case DbTable\Picture::STATUS_INBOX:
                        $status = [
                            'class' => 'warning',
                            'name'  => 'moder/picture/acceptance/inbox'
                        ];
                        break;
                    case DbTable\Picture::STATUS_REMOVED:
                        $status = [
                            'class' => 'danger',
                            'name'  => 'moder/picture/acceptance/removed'
                        ];
                        break;
                    case DbTable\Picture::STATUS_REMOVING:
                        $status = [
                            'class' => 'danger',
                            'name'  => 'moder/picture/acceptance/removing'
                        ];
                        break;
                }
            }
        }
        
        $user = null;
        if ($object['author_id']) {
            $userRow = $this->userTable->fetchRow([
                'id = ?' => $object['author_id']
            ]);
            if ($userRow) {
                $userHydrator = $this->hydratorManager->get(UserHydrator::class);
                $user = $userHydrator->extract($userRow);
            }
        }
        
        return [
            'url'     => $this->comments->getMessageRowUrl($object),
            'preview' => $this->comments->getMessagePreview($object['message']),
            'user'    => $user,
            'status'  => $status,
            'new'     => $this->comments->service()->isNewMessage($object, $this->currentUserId)
        ];
    }
    
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }
}
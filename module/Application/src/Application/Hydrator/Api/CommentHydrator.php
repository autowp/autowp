<?php

namespace Application\Hydrator\Api;

use Traversable;

use Zend\Db\TableGateway\TableGateway;
use Zend\Hydrator\Strategy\DateTimeFormatterStrategy;
use Zend\Stdlib\ArrayUtils;

use Autowp\Commons\Db\Table\Row;
use Autowp\User\Model\User;

use Application\Comments;
use Application\Model\Picture;
use Application\View\Helper\UserText;
use Application\Hydrator\Api\Filter\PropertyFilter;
use Application\Hydrator\Api\Strategy\HydratorStrategy;

class CommentHydrator extends RestHydrator
{
    /**
     * @var Comments
     */
    private $comments;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var int|null
     */
    private $userId = null;

    private $userRole = null;

    /**
     * @var User
     */
    private $userModel;

    /**
     * @var UserText
     */
    private $userText;

    /**
     * @var TableGateway
     */
    private $voteTable;

    private $acl;

    /**
     * @var int
     */
    private $limit;

    public function __construct($serviceManager)
    {
        parent::__construct();

        $this->comments = $serviceManager->get(\Application\Comments::class);
        $this->router = $serviceManager->get('HttpRouter');

        $this->picture = $serviceManager->get(Picture::class);
        $this->userModel = $serviceManager->get(\Autowp\User\Model\User::class);

        $this->userText = $serviceManager->get('ViewHelperManager')->get('userText');

        $this->userId = null;

        $this->acl = $serviceManager->get(\Zend\Permissions\Acl\Acl::class);

        $tables = $serviceManager->get('TableManager');
        $this->voteTable = $tables->get('comment_vote');

        $strategy = new DateTimeFormatterStrategy();
        $this->addStrategy('datetime', $strategy);

        $strategy = new Strategy\Comments($serviceManager);
        $this->addStrategy('replies', $strategy);

        $strategy = new Strategy\User($serviceManager);
        $this->addStrategy('user', $strategy);
    }

    /**
     * @param  array|Traversable $options
     * @return RestHydrator
     * @throws \Zend\Hydrator\Exception\InvalidArgumentException
     */
    public function setOptions($options)
    {
        parent::setOptions($options);

        if ($options instanceof \Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new \Zend\Hydrator\Exception\InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }

        if (isset($options['user_id'])) {
            $this->setUserId($options['user_id']);
        }

        if (isset($options['limit'])) {
            $this->limit = (int)$options['limit'];
        }

        return $this;
    }

    /**
     * @param int|null $userId
     * @return CommentHydrator
     */
    public function setUserId($userId = null)
    {
        $this->userId = $userId;

        $this->getStrategy('user')->setUserId($userId);
        //$this->getStrategy('replies')->setUser($user);

        return $this;
    }

    public function extract($object)
    {
        $canRemove = false;
        $isModer = false;
        $role = $this->getUserRole();
        if ($role) {
            $canRemove = $this->acl->isAllowed($role, 'comment', 'remove');
            $isModer = $this->acl->inheritsRole($role, 'moder');
        }

        $result = [
            'id'      => (int)$object['id'],
            'deleted' => (bool) $object['deleted'],
            'item_id' => (int)$object['item_id'],
            'type_id' => (int)$object['type_id'],
        ];

        if ($this->filterComposite->filter('is_new')) {
            $result['is_new'] = $this->comments->service()->isNewMessage($object, $this->userId);
        }

        if ($this->filterComposite->filter('datetime')) {
            $addDate = Row::getDateTimeByColumnType('timestamp', $object['datetime']);
            $result['datetime'] = $this->extractValue('datetime', $addDate);
        }

        if ($this->filterComposite->filter('user')) {
            $user = null;
            if ($object['author_id']) {
                $userRow = $this->userModel->getRow((int) $object['author_id']);
                if ($userRow) {
                    $user = $this->extractValue('user', $userRow);
                }
            }

            $result['user'] = $user;
        }

        if ($canRemove || ! $object['deleted']) {
            if ($this->filterComposite->filter('preview')) {
                $result['preview'] = $this->comments->getMessagePreview($object['message']);
            }

            if ($this->filterComposite->filter('url')) {
                $result['url'] = $this->comments->getMessageRowUrl($object);
            }

            if ($this->filterComposite->filter('text_html')) {
                $result['text_html'] = $this->userText->__invoke($object['message']);
            }

            if ($this->filterComposite->filter('vote')) {
                $result['vote'] = (int)$object['vote'];
            }

            if ($this->filterComposite->filter('user_vote')) {
                $vote = null;
                if ($this->userId) {
                    $voteRow = $this->voteTable->select([
                        'comment_id = ?' => $object['id'],
                        'user_id = ?'    => (int)$this->userId
                    ])->current();
                    $vote = $voteRow ? $voteRow['vote'] : null;
                }

                $result['user_vote'] = $vote;
            }
        }

        if ($this->filterComposite->filter('replies')) {
            $paginator = $this->comments->service()->getMessagesPaginator([
                'item_id'   => $object['item_id'],
                'type'      => $object['type_id'],
                'parent_id' => $object['id'],
                'order'     => 'comment_message.datetime ASC'
            ]);

            $paginator->setItemCountPerPage(500); // limit for safety

            $result['replies'] = $this->extractValue('replies', $paginator->getCurrentItems());
        }

        if ($this->filterComposite->filter('status')) {
            if ($isModer) {
                $status = null;
                if ($object['type_id'] == \Application\Comments::PICTURES_TYPE_ID) {
                    $picture = $this->picture->getRow(['id' => (int)$object['item_id']]);
                    if ($picture) {
                        switch ($picture['status']) {
                            case Picture::STATUS_ACCEPTED:
                                $status = [
                                    'class' => 'success',
                                    'name'  => 'moder/picture/acceptance/accepted'
                                ];
                                break;
                            case Picture::STATUS_INBOX:
                                $status = [
                                    'class' => 'warning',
                                    'name'  => 'moder/picture/acceptance/inbox'
                                ];
                                break;
                            case Picture::STATUS_REMOVED:
                                $status = [
                                    'class' => 'danger',
                                    'name'  => 'moder/picture/acceptance/removed'
                                ];
                                break;
                            case Picture::STATUS_REMOVING:
                                $status = [
                                    'class' => 'danger',
                                    'name'  => 'moder/picture/acceptance/removing'
                                ];
                                break;
                        }
                    }
                }

                $result['status'] = $status;
            }
        }
        if ($this->filterComposite->filter('page') && $this->limit > 0) {
            $result['page'] = $this->comments->service()->getMessagePage($object, $this->limit);
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }

    public function setFields(array $fields)
    {
        $this->getFilter('fields')->addFilter('fields', new PropertyFilter(array_keys($fields)));

        foreach ($fields as $name => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (! isset($this->strategies[$name])) {
                continue;
            }

            $strategy = $this->strategies[$name];

            if ($strategy instanceof HydratorStrategy) {
                $strategy->setFields($value);
            }
        }

        if (isset($fields['replies'])) {
            $strategy = $this->strategies['replies'];

            if ($strategy instanceof HydratorStrategy) {
                $strategy->setFields($fields);
            }
        }

        return $this;
    }

    private function getUserRole()
    {
        if (! $this->userId) {
            return null;
        }

        if (! $this->userRole) {
            $this->userRole = $this->userModel->getUserRole($this->userId);
        }

        return $this->userRole;
    }
}

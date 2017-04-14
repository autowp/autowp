<?php

namespace Application\Hydrator\Api;

use DateTime;
use DateInterval;

use Zend\Hydrator\Strategy\DateTimeFormatterStrategy;
use Zend\Permissions\Acl\Acl;

use Autowp\User\Model\DbTable\User;

use Application\Hydrator\Api\Strategy\Image as HydratorImageStrategy;

class UserHydrator extends RestHydrator
{
    /**
     * @var int|null
     */
    protected $userId = null;

    private $userRole = null;

    private $acl;

    private $router;

    public function __construct($serviceManager)
    {
        parent::__construct();

        $this->router = $serviceManager->get('HttpRouter');
        $this->acl = $serviceManager->get(\Zend\Permissions\Acl\Acl::class);

        $strategy = new DateTimeFormatterStrategy();
        $this->addStrategy('last_online', $strategy);
        $this->addStrategy('reg_date', $strategy);

        $strategy = new HydratorImageStrategy($serviceManager);
        $this->addStrategy('image', $strategy);
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

        return $this;
    }

    public function extract($object)
    {
        $deleted = (bool)$object['deleted'];

        if ($deleted) {
            $user = [
                'id'       => null,
                'name'     => null,
                'deleted'  => $deleted,
                'url'      => null,
                'longAway' => false,
                'green'    => false
            ];
        } else {
            $longAway = false;
            if ($lastOnline = $object->getDateTime('last_online')) {
                $date = new DateTime();
                $date->sub(new DateInterval('P6M'));
                if ($date > $lastOnline) {
                    $longAway = true;
                }
            } else {
                $longAway = true;
            }

            $isGreen = $object->role && $this->acl->isAllowed($object->role, 'status', 'be-green');

            $user = [
                'id'        => (int)$object['id'],
                'name'      => $object['name'],
                'deleted'   => $deleted,
                'url'       => $this->router->assemble([
                    'user_id' => $object->identity ? $object->identity : 'user' . $object->id
                ], [
                    'name' => 'users/user'
                ]),
                'long_away' => $longAway,
                'green'     => $isGreen
            ];

            if ($this->filterComposite->filter('last_online')) {
                $lastOnline = $object->getDateTime('last_online');
                $user['last_online'] = $this->extractValue('last_online', $lastOnline);
            }

            if ($this->filterComposite->filter('reg_date')) {
                $lastOnline = $object->getDateTime('reg_date');
                $user['reg_date'] = $this->extractValue('reg_date', $lastOnline);
            }

            if ($this->filterComposite->filter('image')) {
                $user['image'] = $this->extractValue('image', [
                    'image'  => $object['img']
                ]);
            }

            $canViewEmail = $object['id'] == $this->userId;
            if (! $canViewEmail) {
                $canViewEmail = $this->isModer();
            }

            if ($canViewEmail && $this->filterComposite->filter('email')) {
                $user['email'] = $object['e_mail'];
            }

            $canViewLogin = $object['id'] == $this->userId;
            if (! $canViewLogin) {
                $canViewLogin= $this->isModer();
            }

            if ($canViewLogin && $this->filterComposite->filter('login')) {
                $user['login'] = $object['login'];
            }
        }

        return $user;
    }

    private function isModer()
    {
        $role = $this->getUserRole();
        if (! $role) {
            return false;
        }

        return $this->acl->inheritsRole($role, 'moder');
    }

    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }

    public function setUserId($userId)
    {
        if ($this->userId != $userId) {
            $this->userId = $userId;
            $this->userRole = null;
        }

        return $this;
    }

    private function getUserRole()
    {
        if (! $this->userId) {
            return null;
        }

        if (! $this->userRole) {
            $table = new User();
            $db = $table->getAdapter();
            $this->userRole = $db->fetchOne(
                $db->select()
                    ->from($table->info('name'), ['role'])
                    ->where('id = ?', $this->userId)
            );
        }

        return $this->userRole;
    }
}
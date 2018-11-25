<?php

namespace Application\Hydrator\Api;

use Traversable;

use Zend\Hydrator\Strategy\DateTimeFormatterStrategy;
use Zend\Stdlib\ArrayUtils;

use Autowp\Traffic\TrafficControl;
use Autowp\User\Model\User;

class IpHydrator extends RestHydrator
{
    /**
     * @var int|null
     */
    private $userId = null;

    private $userRole = null;

    private $acl;

    /**
     * @var TrafficControl
     */
    private $trafficControl;

    /**
     * @var User
     */
    private $userModel;

    public function __construct(
        $serviceManager
    ) {
        parent::__construct();

        $this->acl = $serviceManager->get(\Zend\Permissions\Acl\Acl::class);
        $this->trafficControl = $serviceManager->get(TrafficControl::class);
        $this->userModel = $serviceManager->get(\Autowp\User\Model\User::class);

        $strategy = new Strategy\User($serviceManager);
        $this->addStrategy('user', $strategy);

        $strategy = new DateTimeFormatterStrategy();
        $this->addStrategy('up_to', $strategy);
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

    /**
     * @param int|null $userId
     * @return IpHydrator
     */
    public function setUserId($userId = null)
    {
        $this->userId = $userId;

        //$this->getStrategy('content')->setUser($user);
        //$this->getStrategy('replies')->setUser($user);

        return $this;
    }

    public function extract($ip)
    {
        $result = [
            'address' => $ip
        ];
        if ($this->filterComposite->filter('hostname')) {
            $result['hostname'] = gethostbyaddr($ip);
        }

        if ($this->filterComposite->filter('blacklist')) {
            $canView = false;
            $role = $this->getUserRole();
            if ($role) {
                $canView = $this->acl->inheritsRole($role, 'moder');
            }

            if ($canView) {
                $result['blacklist'] = null;
                $ban = $this->trafficControl->getBanInfo($ip);
                if ($ban) {
                    $user = $this->userModel->getRow((int)$ban['by_user_id']);
                    $ban['user'] = $user ? $this->extractValue('user', $user) : null;
                    $ban['up_to'] = $this->extractValue('up_to', $ban['up_to']);

                    $result['blacklist'] = $ban;
                }
            }
        }

        if ($this->filterComposite->filter('rights')) {
            $canBan = false;

            $role = $this->getUserRole();
            if ($role) {
                $canBan = $this->acl->isAllowed($role, 'user', 'ban');
            }

            $result['rights'] = [
                'add_to_blacklist'      => $canBan,
                'remove_from_blacklist' => $canBan,
            ];



            /*if ($canBan) {
                $this->banForm->setAttribute('action', $this->url()->fromRoute('ban/ban-ip', [
                    'ip' => inet_ntop($picture['ip'])
                ]));
                $this->banForm->populateValues([
                    'submit' => 'ban/ban'
                ]);
            }*/
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

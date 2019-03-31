<?php

namespace Autowp\User\Controller\Plugin;

use ArrayObject;
use Zend\Authentication\AuthenticationService;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Permissions\Acl\Acl;

use Autowp\User\Model\User as UserModel;

class User extends AbstractPlugin
{
    /**
     * @var Acl
     */
    private $acl;

    /**
     * @var UserModel
     */
    private $userModel = null;

    /**
     * @var array
     */
    private $users = [];

    /**
     * @var array|ArrayObject
     */
    private $user = null;

    public function __construct(Acl $acl, UserModel $userModel)
    {
        $this->acl = $acl;
        $this->userModel = $userModel;
    }

    /**
     * @param int $id
     * @return array|ArrayObject
     */
    private function user($id)
    {
        if (! $id) {
            return null;
        }

        if (! array_key_exists($id, $this->users)) {
            $this->users[$id] = $this->userModel->getRow(['id' => (int)$id]);
        }

        return $this->users[$id];
    }

    /**
     * @param mixed $user
     * @return User
     */
    public function __invoke($user = null)
    {
        if ($user === null) {
            $user = $this->getLogedInUser();
        }

        if (! (is_array($user) || $user instanceof ArrayObject)) {
            $user = $this->user($user);
        }

        $this->user = $user;

        return $this;
    }

    /**
     * @return array|ArrayObject
     */
    private function getLogedInUser()
    {
        $auth = new AuthenticationService();

        if (! $auth->hasIdentity()) {
            return null;
        }

        return $this->user($auth->getIdentity());
    }

    /**
     * @return bool
     */
    public function logedIn()
    {
        return (bool)$this->getLogedInUser();
    }

    /**
     * @return array|ArrayObject
     */
    public function get()
    {
        return $this->user;
    }

    /**
     * @param  string $resource
     * @param  string $privilege
     * @return boolean
     */
    public function isAllowed($resource = null, $privilege = null)
    {
        return $this->user
            && $this->user['role']
            && $this->acl->isAllowed($this->user['role'], $resource, $privilege);
    }

    /**
     * @param  string $inherit
     * @return boolean
     */
    public function inheritsRole($inherit)
    {
        return $this->user
            && $this->user['role']
            && $this->acl->hasRole($inherit)
            && $this->acl->inheritsRole($this->user['role'], $inherit);
    }

    public function timezone()
    {
        return $this->user && $this->user['timezone']
            ? $this->user['timezone']
            : 'UTC';
    }
}

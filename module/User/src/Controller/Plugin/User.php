<?php

namespace Autowp\User\Controller\Plugin;

use Zend\Authentication\AuthenticationService;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Permissions\Acl\Acl;

use Autowp\User\Model\DbTable\User as UserTable;
use Autowp\User\Model\DbTable\User\Row as UserRow;

class User extends AbstractPlugin
{
    /**
     * @var Acl
     */
    private $acl;

    /**
     * @var UserTable
     */
    private $userTable = null;

    /**
     * @var array
     */
    private $users = [];

    /**
     * @var UserRow
     */
    private $user = null;

    public function __construct(Acl $acl)
    {
        $this->acl = $acl;
    }

    /**
     * @return UserTable
     */
    private function getUserTable()
    {
        return $this->userTable
            ? $this->userTable
            : $this->userTable = new UserTable();
    }

    /**
     * @param int $id
     * @return UserRow
     */
    private function user($id)
    {
        if (! $id) {
            return null;
        }

        if (! array_key_exists($id, $this->users)) {
            $this->users[$id] = $this->getUserTable()->find($id)->current();
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

        if (! $user instanceof UserRow) {
            $user = $this->user($user);
        }

        $this->user = $user;

        return $this;
    }

    /**
     * @return UserRow
     */
    private function getLogedInUser()
    {
        $auth = new AuthenticationService();

        if (! $auth->hasIdentity()) {
            return false;
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
     * @return UserRow
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
            && $this->user->role
            && $this->acl->isAllowed($this->user->role, $resource, $privilege);
    }

    /**
     * @param  string $inherit
     * @return boolean
     */
    public function inheritsRole($inherit)
    {
        return $this->user
            && $this->user->role
            && $this->acl->hasRole($inherit)
            && $this->acl->inheritsRole($this->user->role, $inherit);
    }

    public function timezone()
    {
        return $this->user && $this->user->timezone
            ? $this->user->timezone
            : 'UTC';
    }
}

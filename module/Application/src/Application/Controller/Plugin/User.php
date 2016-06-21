<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Application\Acl;

use Zend_Acl_Resource_Interface;
use Zend_Acl_Role_Interface;
use Zend_Auth;
use Zend_Registry;

use Users;
use Users_Row;

class User extends AbstractPlugin
{
    /**
     * @var Acl
     */
    private $acl;

    /**
     * @var Users
     */
    private $userTable = null;

    /**
     * @var array
     */
    private $users = [];

    /**
     * @var Users_Row
     */
    private $user = null;

    public function __construct(Acl $acl)
    {
        $this->acl = $acl;
    }

    /**
     * @return Users
     */
    private function getUserTable()
    {
        return $this->userTable
            ? $this->userTable
            : $this->userTable = new Users();
    }

    /**
     * @param int $id
     * @return Users_Row
     */
    private function user($id)
    {
        if (!$id) {
            return null;
        }

        if (!array_key_exists($id, $this->users)) {
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

        if (!$user instanceof Users_Row) {
            $user = $this->user($user);
        }

        $this->user = $user;

        return $this;
    }

    /**
     * @return Users_Row
     */
    private function getLogedInUser()
    {
        $auth = Zend_Auth::getInstance();

        if (!$auth->hasIdentity()) {
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
     * @return Users_Row
     */
    public function get()
    {
        return $this->user;
    }

    /**
     * @param  Zend_Acl_Resource_Interface|string $resource
     * @param  string                             $privilege
     * @return boolean
     */
    public function isAllowed($resource = null, $privilege = null)
    {
        return $this->user
            && $this->user->role
            && $this->acl->isAllowed($this->user->role, $resource, $privilege);
    }

    /**
     * @param  Zend_Acl_Role_Interface|string $inherit
     * @return boolean
     */
    public function inheritsRole($inherit)
    {
        return $this->user
            && $this->user->role
            && $this->acl->inheritsRole($this->user->role, $inherit);
    }

    public function clearRememberCookie()
    {
        $domain = Zend_Registry::get('cookie_domain');
        setcookie('remember', '', time() - 3600*24*30, '/', $domain);
    }

    public function setRememberCookie($hash)
    {
        $domain = Zend_Registry::get('cookie_domain');
        setcookie('remember', $hash, time() + 3600*24*30, '/', $domain);
    }

    public function timezone()
    {
        return $this->user && $this->user->timezone
            ? $this->user->timezone
            : 'UTC';
    }
}
<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Zend\Permissions\Acl\Acl;

use Zend_Acl_Resource_Interface;
use Zend_Acl_Role_Interface;
use Zend_Auth;

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

    /**
     * @var array
     */
    private $hosts = [];

    public function __construct(Acl $acl, array $hosts)
    {
        $this->acl = $acl;
        $this->hosts = $hosts;
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
        $language = $this->getController()->language();

        if (!isset($this->hosts[$language])) {
            throw new Exception("Host `$language` not found");
        }
        $domain = $this->hosts[$language]['cookie'];
        setcookie('remember', '', time() - 3600*24*30, '/', $domain);
    }

    public function setRememberCookie($hash)
    {
        $language = $this->getController()->language();

        if (!isset($this->hosts[$language])) {
            throw new Exception("Host `$language` not found");
        }
        $domain = $this->hosts[$language]['cookie'];
        setcookie('remember', $hash, time() + 3600*24*30, '/', $domain);
    }

    public function timezone()
    {
        return $this->user && $this->user->timezone
            ? $this->user->timezone
            : 'UTC';
    }
}

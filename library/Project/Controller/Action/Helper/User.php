<?php

class Project_Controller_Action_Helper_User extends Zend_Controller_Action_Helper_Abstract
{
    /**
     * @var Users
     */
    protected $_userTable = null;

    /**
     * @var array
     */
    protected $_users = array();

    /**
     * @var Users_Row
     */
    protected $_user = null;

    /**
     * @return Users
     */
    protected function _getUserTable()
    {
        return $this->_userTable
            ? $this->_userTable
            : $this->_userTable = new Users();
    }

    /**
     * @param int $id
     * @return Users_Row
     */
    protected function _user($id)
    {
        if (!isset($this->_users[$id])) {
            $this->_users[$id] = $this->_getUserTable()->find($id)->current();
        }

        return $this->_users[$id];
    }

    /**
     * @param mixed $user
     * @return Project_Controller_Action_Helper_User
     */
    public function direct($user = null)
    {
        if ($user === null) {
            $user = $this->_getLogedInUser();
        }

        if (!$user instanceof Users_Row) {
            $user = $this->_user($user);
        }

        $this->_user = $user;

        return $this;
    }

    /**
     * @return Users_Row
     */
    protected function _getLogedInUser()
    {
        $auth = Zend_Auth::getInstance();

        if (!$auth->hasIdentity()) {
            return false;
        }

        return $this->_user($auth->getIdentity());
    }

    /**
     * @return bool
     */
    public function logedIn()
    {
        return (bool)$this->_getLogedInUser();
    }

    /**
     * @return Users_Row
     */
    public function get()
    {
        return $this->_user;
    }

    /**
     * @param  Zend_Acl_Resource_Interface|string $resource
     * @param  string                             $privilege
     * @return boolean
     */
    public function isAllowed($resource = null, $privilege = null)
    {
        return $this->_user
            && $this->_user->role
            && Zend_Controller_Action_HelperBroker::getStaticHelper('acl')
                ->direct()
                ->isAllowed($this->_user->role, $resource, $privilege);
    }

    /**
     * @param  Zend_Acl_Role_Interface|string $inherit
     * @return boolean
     */
    public function inheritsRole($inherit)
    {
        return $this->_user
            && $this->_user->role
            && Zend_Controller_Action_HelperBroker::getStaticHelper('acl')
                ->direct()
                ->inheritsRole($this->_user->role, $inherit);
    }

    public function clearRememberCookie()
    {
        setcookie('remember', '', time() - 3600*24*30, '/', '.autowp.ru');
    }

    public function setRememberCookie($hash)
    {
        setcookie('remember', $hash, time() + 3600*24*30, '/', '.autowp.ru');
    }

    public function timezone()
    {
        return $this->_user && $this->_user->timezone
            ? $this->_user->timezone
            : 'UTC';
    }
}
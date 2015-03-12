<?php

class Project_View_Helper_User extends Zend_View_Helper_Abstract
{
    protected $_userModel;
    protected $_users = array();

    protected $_user = null;

    protected function _user($id)
    {
        if (!$id) {
            return null;
        }

        if (!isset($this->_users[$id])) {
            if (!$this->_userModel) {
                $this->_userModel = new Users();
            }
            $this->_users[$id] = $this->_userModel->find($id)->current();
        }

        return $this->_users[$id];
    }

    public function user($user = null, array $options = array())
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

    public function __toString()
    {
        $result = '';

        try {

            $user = $this->_user;

            if ($user) {

                if ($user->deleted) {
                    return '<span class="muted"><span class="glyphicon glyphicon-user"></span> удалённый пользователь</span>';
                }

                $url = $this->view->url(array(
                    'module'     => 'default',
                    'controller' => 'users',
                    'action'     => 'user',
                    'identity'   => $user->identity,
                    'user_id'    => $user->id
                ), 'users', true);

                $classes = array('user');
                if ($lastOnline = $user->getDate('last_online')) {
                    if (Zend_Date::now()->subMonth(6)->isLater($lastOnline)) {
                        $classes[] = 'long-away';
                    }
                } else {
                    $classes[] = 'long-away';
                }

                if ($this->isAllowed('status', 'be-green')) {
                    $classes[] = 'green-man';
                }

                $result =
                    '<span class="'.implode(' ', $classes).'">' .
                        '<span class="glyphicon glyphicon-user"></span>&#xa0;' .
                        $this->view->htmlA($url, $user->getCompoundName()) .
                    '</span>';
            }

        } catch (Exception $e) {

            $result = $e->getMessage();

        }

        return $result;
    }

    public function avatar()
    {
        $user = $this->_user;

        if ($user) {
            if ($user->img) {
                $image = $this->view->img($user->img, array(
                    'format' => 'avatar',
                ));

                if ($image && $image->exists()) {
                    return $image;
                }
            }

            if ($user->e_mail) {
                // gravatar
                return $this->view->gravatar($user->e_mail, array(
                    'img_size'    => 70,
                    'default_img' => 'http://www.autowp.ru/_.gif'
                ));
            }
        }

        return '';
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
            && $this->view->acl()
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
            && $this->view->acl()
                ->inheritsRole($this->_user->role, $inherit);
    }

    public function timezone()
    {
        return $this->_user && $this->_user->timezone
            ? $this->_user->timezone
            : 'UTC';
    }

    public function humanTime($time = null)
    {
        if ($time === null) {
            require_once 'Zend/View/Exception.php';
            throw new Zend_View_Exception('Expected parameter $time was not provided.');
        }

        require_once 'Zend/Date.php';
        if (!($time instanceof Zend_Date)) {
            $time = new Zend_Date($time);
        }

        $time->setTimeZone($this->timezone());

        return $this->view->humanTime($time);
    }

    public function humanDate($time = null)
    {
        if ($time === null) {
            require_once 'Zend/View/Exception.php';
            throw new Zend_View_Exception('Expected parameter $time was not provided.');
        }

        require_once 'Zend/Date.php';
        if (!($time instanceof Zend_Date)) {
            $time = new Zend_Date($time);
        }

        $time->setTimeZone($this->timezone());

        return $this->view->humanDate($time);
    }
}
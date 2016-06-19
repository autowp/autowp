<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Zend_Auth;
use Zend_Acl_Role_Interface;
use Zend_View_Exception;
use Zend_Date;

use DateTime;
use DateTimeZone;

use Users;
use Users_Row;

class User extends AbstractHelper
{
    private $_userModel;

    private $_users = array();

    private $_user = null;

    private function _user($id)
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

    public function __invoke($user = null, array $options = [])
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
    private function _getLogedInUser()
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
                    return '<span class="muted"><i class="fa fa-user"></i> ' .
                               $this->view->escape($this->view->translate('deleted-user')).
                           '</span>';
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
                        '<i class="fa fa-user"></i>&#xa0;' .
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
            throw new Zend_View_Exception('Expected parameter $time was not provided.');
        }

        $tz = $this->timezone();

        if ($time instanceof DateTime) {
            $time->setTimezone(new DateTimeZone($tz));
        } else {
            require_once 'Zend/Date.php';
            if (!($time instanceof Zend_Date)) {
                $time = new Zend_Date($time);
            }
            $time->setTimeZone($tz);
        }

        return $this->view->humanTime($time);
    }

    public function humanDate($time = null)
    {
        if ($time === null) {
            throw new Zend_View_Exception('Expected parameter $time was not provided.');
        }

        $tz = $this->timezone();

        if ($time instanceof DateTime) {
            $time->setTimezone(new DateTimeZone($tz));
        } else {
            if (!($time instanceof Zend_Date)) {
                $time = new Zend_Date($time);
            }

            $time->setTimeZone($tz);
        }

        return $this->view->humanDate($time);
    }
}
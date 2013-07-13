<?php

class Project_View_Helper_User extends Zend_View_Helper_Abstract
{
    protected $_groupModel;
    protected $_groups = array();

    protected $_userModel;
    protected $_users = array();

    protected $_user = null;

    protected function _group($id)
    {
        if (!$this->_groupModel) {
            $this->_groupModel = new User_Groups();
        }

        if (!isset($this->_groups[$id])) {
            $this->_groups[$id] = $this->_groupModel->find($id)->current();
        }

        return $this->_groups[$id];
    }

    protected function _user($id)
    {
        if (!isset($this->_users[$id])) {
            if (!$this->_userModel) {
                $this->_userModel = new Users();
            }
            $this->_users[$id] = $this->_userModel->find($id)->current();
        }

        return $this->_users[$id];
    }

    public function user($user, array $options = array())
    {
        if (!$user instanceof Users_Row) {
            $user = $this->_user($user);
        }

        $this->_user = $user;

        return $this;
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

                $group = $this->_group($user->group_id);

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
                }

                if ($group->color == '339933') {
                    $classes[] = 'green-man';
                    $style = null;
                } elseif ($group->color == '3333CC') {
                    $style = null;
                } else {
                    $style = 'color:#'.$group->color;
                }

                $result =
                    '<span class="'.implode(' ', $classes).'">' .
                        '<i class="glyphicon glyphicon-user"></i>&#xa0;' .
                        $this->view->htmlA(array(
                            'href'  => $url,
                            'style' => $style
                        ), $user->getCompoundName()) .
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
            $image = $this->view->image($user, 'photo', array(
                'format' => '15',
            ));

            if ($image && $image->exists()) {
                return $image;
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
}
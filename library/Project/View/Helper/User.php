<?php

class Project_View_Helper_User extends Zend_View_Helper_Abstract
{
	protected $_groupModel;
	protected $_groups = array();

	protected $_userModel;
	protected $_users = array();

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
		if ($user->deleted) {
			return '<span class="muted"><i class="icon-user"></i> удалённый пользователь</span>';
		}
		
		$group = $this->_group($user->group_id);

		if (!$user instanceof Users_Row) {
			$user = $this->_user($user);
		}

		$result = '';

		if ($user) {
			
			$url = $this->view->url(array(
				'module'		=>	'default',
				'controller'	=>	'users',
				'action'		=>	'user',
				'identity'		=>	$user->identity,
				'user_id'		=>	$user->id
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
					'<i class="icon-user"></i>&#xa0;' .
					$this->view->htmlA(array(
						'href'	=>	$url,
						'style'	=>	$style
					), $user->getCompoundName()) .
				'</span>';
		}

		return $result;
	}
}
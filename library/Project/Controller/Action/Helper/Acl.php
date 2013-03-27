<?php

class Project_Controller_Action_Helper_Acl extends Zend_Controller_Action_Helper_Abstract
{
	/**
	 * @return Project_Acl
	 */
	public function direct()
	{
		return $bootstrap = $this->getActionController()
			->getInvokeArg('bootstrap')->getResource('acl');
	}
}

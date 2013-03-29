<?php

class Project_Form_Element_Select_Db extends Zend_Form_Element_Select
{
	protected $_nonename = '--';

	public function setNonename($name)
	{
		$this->_nonename = $name;
	}
}

<?php
class Project_Controller_Action_Helper_Catalogue extends Zend_Controller_Action_Helper_Abstract
{
	/**
	 * @var Catalogue
	 */
	protected $_catalogue;
	
	/**
	 * @return Catalogue
	 */
	public function direct()
	{
		return $this->getCatalogue();
	}
	
	/**
	 * @return Catalogue
	 */
	public function getCatalogue()
	{
		return $this->_catalogue
			? $this->_catalogue
			: $this->_catalogue = new Catalogue();
	}
}
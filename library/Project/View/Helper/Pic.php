<?php

class Project_View_Helper_Pic extends Zend_View_Helper_HtmlElement
{
    /**
     * @var Pictures_Row
     */
    protected $_picture = null;

    public function pic(Pictures_Row $picture = null)
    {
        $this->_picture = $picture;

        return $this;
    }

    public function url()
    {
        if ($this->_picture) {
            $identity = $this->_picture->identity ? $this->_picture->identity : $this->_picture->id;

            return $this->view->url(array(
                'module'     => 'default',
                'controller' => 'picture',
                'action'     => 'index',
                'picture_id' => $identity
            ), 'picture', true);
        }
        return false;
    }
}
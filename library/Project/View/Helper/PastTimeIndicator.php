<?php

class Project_View_Helper_PastTimeIndicator extends Zend_View_Helper_Abstract
{
    /**
     * @var Zend_Date
     */
    protected $_pastLimit;

    public function __construct()
    {
        $this->_pastLimit = Zend_Date::now()->subDay(1);
    }

    public function pastTimeIndicator(Zend_Date $date)
    {
        $icon = $date->isLater($this->_pastLimit) ? 'glyphicon-time' : 'glyphicon-calendar';

        return '<span class="glyphicon ' . $icon . '"></span> ' . $this->view->escape($this->view->humanTime($date));
    }
}
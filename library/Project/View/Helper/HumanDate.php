<?php
class Project_View_Helper_HumanDate extends Zend_View_Helper_Abstract
{
    /**
     * Converts time to fuzzy time strings
     *
     * @param string|integer|Zend_Date|array $time
     */
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

        if ($time->isToday()) {
            $s = $this->view->translate('today');
        } elseif ($time->isYesterday()) {
            $s = $this->view->translate('yesterday');
        } else {
            $s = $time->get(Zend_Date::DATE_MEDIUM);
        }

        return $s;
    }
}

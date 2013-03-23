<?php
class Project_View_Helper_HumanTime extends Zend_View_Helper_Abstract
{
    /**
     * @var Zend_View_Interface
     */
    public $view;
    
    
    // ------------------------------------------------------------------------
    public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }
    /**
     * Converts time to fuzzy time strings
     *
     * @param string|integer|Zend_Date|array $time
     */
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

        $now = new Zend_Date();
        $now->sub($time);
        
        $diff = $now->getTimestamp();

        if ($diff <= 50) {
            //less than 50 seconds
            $s = $this->view->translate('few seconds ago');

        } elseif ($diff > 50 && $diff < (60+30)) {
            //more than 50 seconds
            //less than minute and 30 seconds
            $s = $this->view->translate('a minute ago');
        } elseif ($diff >= (60+30) && $diff < (60*55)) {
            //more than minute and 30 seconds
            //less than 55 minutes
            $minutes = $diff / 60;
            $minutes = round($minutes, 0);
            $s = $this->view->translate('%1$s minutes ago/'.numeralCase($minutes), $minutes);
        } elseif ($diff >= (60*55) && $diff < (60*60+60*30)) {
            //more than 55 minutes
            //less than hour and 30 minutes
            $s = $this->view->translate('an hour ago');
        } elseif ($diff >= (60*60+60*30) && $diff < (60 * 60 * 23.5)) {
            //more than hour and 30 minutes
            //less than 23 and half hour
            $hours = $diff / (60*60);
            $hours = round($hours, 0);
            $s = $this->view->translate('%1$s hours ago/'.numeralCase($hours), $hours);
        } else {
            $s = $this->view->humanDate($time);
        }
        
        return $s;
        
    }
}

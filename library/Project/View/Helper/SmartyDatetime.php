<?php

class Project_View_Helper_SmartyDatetime extends Zend_View_Helper_HtmlElement
{
    public function smartyDatetime($time)
    {
        $months = array(
            1 => 'января',    2 => 'февраля',  3 => 'марта',   4 => 'апреля',
            5 => 'мая',       6 => 'июня',     7 => 'июля',    8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
        );
        
        $result = array();

        $d = getdate($time);
        $cd = getdate();
    
        if ($d['year'] == $cd['year'])
        {
            if ($d['mon'] == $cd['mon'])
            {
                if ($d['mday'] == $cd['mday'])
                {
                    $result[] = 'сегодня';
                }
                elseif ($d['mday'] == $cd['mday']-1)
                {
                    $result[] = 'вчера';
                }
                else
                {
                    $result[] = $d['mday'].' '.$months[$d['mon']];
                }
            }
            else
            {
                $result[] = $d['mday'].' '.$months[$d['mon']];
            }
        }
        else
            $result[] = $d['mday'].' '.$months[$d['mon']].' '.$d['year'].' года';
            
        $result[] = date('H:i:s', $time);
        
        return implode(' ', $result);
    }
}